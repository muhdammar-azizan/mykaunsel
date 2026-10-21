<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns the Account-page audit_logs table into the platform-wide audit log:
 *  - actor snapshot (type/name/role), category, target name, organization, user agent, request id
 *  - actor_user_id and the organization are plain indexed columns (no foreign keys), so deleting a user or an
 *    organization never rewrites an audit row
 *  - existing rows are converted to the new dotted action keys and short target types, once, here
 *    (this runs before the "append-only" rules apply, which is why it uses DB::table()).
 */
return new class extends Migration
{
    /** old action key => [new action key, category] */
    private const LEGACY_ACTIONS = [
        'login' => ['auth.login', 'auth'],
        'login_failed' => ['auth.login_failed', 'auth'],
        'password_changed' => ['account.password_changed', 'security'],
        'email_change_requested' => ['account.email_change_requested', 'security'],
        'email_changed' => ['account.email_changed', 'security'],
        'two_factor_enabled' => ['account.two_factor_enabled', 'security'],
        'two_factor_disabled' => ['account.two_factor_disabled', 'security'],
        'two_factor_failed' => ['account.two_factor_failed', 'security'],
        'recovery_code_used' => ['account.recovery_code_used', 'security'],
        'recovery_codes_regenerated' => ['account.recovery_codes_regenerated', 'security'],
        'other_sessions_revoked' => ['account.other_sessions_revoked', 'security'],
        'avatar_changed' => ['account.avatar_changed', 'account'],
        'organization_approved' => ['organization.approved', 'organizations'],
        'organization_rejected' => ['organization.rejected', 'organizations'],
        'organization_suspended' => ['organization.suspended', 'organizations'],
        'organization_reactivated' => ['organization.reactivated', 'organizations'],
        'organization_note_added' => ['organization.note_added', 'organizations'],
        'organization_dns_checked' => ['organization.dns_checked', 'organizations'],
        'organization_dns_reminder_sent' => ['organization.dns_reminder_sent', 'organizations'],
    ];

    /** old target_type (class name) => new short key */
    private const LEGACY_TARGET_TYPES = [
        'App\\Models\\Organization' => 'organization',
        'App\\Models\\User' => 'user',
    ];

    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['actor_user_id']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('actor_type', 10)->default('user')->after('id');
            $table->string('actor_name')->nullable()->after('actor_user_id');
            $table->string('actor_role', 30)->nullable()->after('actor_name');
            $table->string('category', 30)->default('other')->after('action');
            $table->string('target_name')->nullable()->after('target_id');
            $table->unsignedBigInteger('organization_id')->nullable()->after('target_name');
            $table->string('user_agent')->nullable()->after('ip');
            $table->string('request_id', 36)->nullable()->after('user_agent');

            $table->index('created_at');
            $table->index(['category', 'created_at']);
            $table->index(['organization_id', 'created_at']);
            $table->index('request_id');
        });

        $this->convertLegacyRows();
    }

    /** Convert rows written by the old code. Safe to run more than once. */
    public function convertLegacyRows(): void
    {
        foreach (self::LEGACY_ACTIONS as $old => [$new, $category]) {
            DB::table('audit_logs')->where('action', $old)->update(['action' => $new, 'category' => $category]);
        }

        foreach (self::LEGACY_TARGET_TYPES as $old => $new) {
            DB::table('audit_logs')->where('target_type', $old)->update(['target_type' => $new]);
        }

        // Organization targets: link the organization and keep its name as it is today (the best we have).
        foreach (DB::table('organizations')->select('id', 'name')->get() as $org) {
            DB::table('audit_logs')
                ->where('target_type', 'organization')->where('target_id', $org->id)
                ->update(['organization_id' => $org->id, 'target_name' => $org->name]);
        }

        // Actor / target names are kept only for staff (platform admin, org admin; counselors for non-sign-in actions).
        $staffRoleByUser = $this->staffRoles();

        foreach (DB::table('audit_logs')->whereNotNull('actor_user_id')->distinct()->pluck('actor_user_id') as $userId) {
            $role = $staffRoleByUser[$userId]['role'] ?? null;

            if ($role === null) {
                continue;
            }

            $name = $staffRoleByUser[$userId]['name'];
            $query = DB::table('audit_logs')->where('actor_user_id', $userId);

            if ($role === 'counselor') {
                $query->where('category', '!=', 'auth');
            }

            $query->update(['actor_name' => $name, 'actor_role' => $role]);
        }

        foreach (DB::table('audit_logs')->where('target_type', 'user')->whereNotNull('target_id')->distinct()->pluck('target_id') as $userId) {
            if (isset($staffRoleByUser[$userId])) {
                DB::table('audit_logs')->where('target_type', 'user')->where('target_id', $userId)
                    ->update(['target_name' => $staffRoleByUser[$userId]['name']]);
            }
        }
    }

    /** @return array<int, array{role: string, name: string}> user id => highest staff role + current name */
    private function staffRoles(): array
    {
        $priority = ['platform_admin', 'org_admin', 'counselor'];
        $out = [];

        $rows = DB::table('memberships')
            ->join('users', 'users.id', '=', 'memberships.user_id')
            ->where('memberships.status', 'active')
            ->whereIn('memberships.role', $priority)
            ->select('memberships.user_id', 'memberships.role', 'users.name')
            ->get();

        foreach ($rows as $row) {
            $current = $out[$row->user_id]['role'] ?? null;

            if ($current === null || array_search($row->role, $priority) < array_search($current, $priority)) {
                $out[$row->user_id] = ['role' => $row->role, 'name' => $row->name];
            }
        }

        return $out;
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['category', 'created_at']);
            $table->dropIndex(['organization_id', 'created_at']);
            $table->dropIndex(['request_id']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['actor_type', 'actor_name', 'actor_role', 'category', 'target_name', 'organization_id', 'user_agent', 'request_id']);
        });
    }
};
