<?php

namespace Database\Seeders;

use App\Enums\AccessModel;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Facades\Audit;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * About 120 SAMPLE audit entries so the Audit Log page can be tried: every category, several admins, the system actor,
 * deleted targets, long metadata, and dates spread over four months.
 *
 * Everything here is made up. Each entry is marked "seeded" in its metadata (the drawer shows "Sample data").
 * Safe to run twice: it does nothing if the sample set is already there.
 */
class AuditLogSeeder extends Seeder
{
    private const SET = 'audit-demo';

    private const AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148',
    ];

    /** @var list<User> */
    private array $admins = [];

    private User $orgAdmin;

    /** @var list<User> */
    private array $students = [];

    /** @var list<Organization> */
    private array $orgs = [];

    private int $n = 0;

    public function run(): void
    {
        if (AuditLog::query()->where('metadata->sample_set', self::SET)->exists()) {
            return;
        }

        mt_srand(20261022); // the same sample every time
        $this->people();

        $this->organizations();
        $this->members();
        $this->counselors();
        $this->lkmAndComplaints();
        $this->moderationSettingsPricingAdmins();
        $this->accountAndAuth();
        $this->system();
        $this->edgeCases();
    }

    // ---- people ----------------------------------------------------------------------------

    private function people(): void
    {
        $platform = Organization::query()->where('org_type', OrgType::Platform->value)->first()
            ?? Organization::create(['name' => 'MyKaunsel Platform', 'org_type' => OrgType::Platform, 'access_model' => AccessModel::Open, 'subscription_status' => OrgStatus::Active]);

        $existing = User::whereHas('memberships', fn ($q) => $q->where('role', MembershipRole::PlatformAdmin->value))->get()->all();

        foreach ([['Aisyah Rahman', 'aisyah.audit@mykaunsel.test'], ['Hafiz Omar', 'hafiz.audit@mykaunsel.test']] as [$name, $email]) {
            $user = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => Hash::make(Str::random(32)), 'email_verified_at' => now()]);
            Membership::firstOrCreate(
                ['user_id' => $user->id, 'org_id' => $platform->id],
                ['role' => MembershipRole::PlatformAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()->subMonths(5)],
            );
            $existing[] = $user;
        }
        $this->admins = array_values($existing);

        $this->orgs = Organization::query()->applicants()->get()->all();
        if ($this->orgs === []) {
            $this->orgs[] = Organization::create(['name' => 'Universiti Contoh Audit', 'org_type' => OrgType::University, 'access_model' => AccessModel::Closed, 'subscription_status' => OrgStatus::Active]);
        }

        $this->orgAdmin = User::firstOrCreate(['email' => 'zainab.audit@mykaunsel.test'], ['name' => 'Zainab Hashim', 'password' => Hash::make(Str::random(32)), 'email_verified_at' => now()]);
        Membership::firstOrCreate(
            ['user_id' => $this->orgAdmin->id, 'org_id' => $this->orgs[0]->id],
            ['role' => MembershipRole::OrgAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()->subMonths(4)],
        );

        foreach (range(1, 3) as $i) {
            $student = User::firstOrCreate(['email' => "pelajar{$i}.audit@mykaunsel.test"], ['name' => "Pelajar Demo {$i}", 'password' => Hash::make(Str::random(32)), 'email_verified_at' => now()]);
            Membership::firstOrCreate(
                ['user_id' => $student->id, 'org_id' => $this->orgs[0]->id],
                ['role' => MembershipRole::Student, 'status' => MembershipStatus::Active, 'joined_at' => now()->subMonths(3)],
            );
            $this->students[] = $student;
        }
    }

    // ---- groups ----------------------------------------------------------------------------

    private function organizations(): void
    {
        $steps = [
            ['organization.approved', [], ['pending', 'active']],
            ['organization.rejected', ['reason' => 'SSM number could not be verified. Please check it and apply again.'], ['pending', 'rejected']],
            ['organization.suspended', ['reason' => 'Repeated complaints about unlicensed practitioners.'], ['active', 'suspended']],
            ['organization.reactivated', [], ['suspended', 'active']],
        ];

        for ($i = 0; $i < 12; $i++) {
            [$action, $meta, [$old, $new]] = $steps[$i % 4];
            $org = $this->pick($this->orgs);
            $request = (string) Str::uuid();
            $at = $this->when();
            $actor = $this->pick($this->admins);

            $this->log($action, $org, $meta + ['changes' => ['status' => ['old' => $old, 'new' => $new]]], $actor, $at, ['request_id' => $request]);

            if ($i % 3 === 0) { // a note in the same request
                $this->log('organization.note_added', $org, ['note_id' => 100 + $i], $actor, $at->copy()->addSeconds(1), ['request_id' => $request]);
            }
        }

        foreach (range(1, 5) as $i) {
            $org = $this->pick($this->orgs);
            $this->log('organization.dns_checked', $org, ['summary' => $i % 2 ? '1 of 1 verified' : '0 of 2 verified', 'domains' => ['example.edu.my' => $i % 2 ? 'verified' : 'not found']], $this->pick($this->admins));
        }
        foreach (range(1, 3) as $i) {
            $this->log('organization.dns_reminder_sent', $this->pick($this->orgs), ['summary' => 'Emailed registrar@example.edu.my'], $this->pick($this->admins));
        }
        foreach (range(1, 6) as $i) {
            $this->log('organization.dns_verified', $this->pick($this->orgs), ['domain' => "fakulti{$i}.example.edu.my"], 'system');
        }
    }

    private function members(): void
    {
        $actions = ['member.added', 'member.removed', 'member.role_changed', 'member.offboarded', 'org_counselor.added', 'org_counselor.removed'];

        foreach (range(0, 7) as $i) {
            $action = $actions[$i % count($actions)];
            $meta = ['member_id' => 500 + $i];
            if ($action === 'member.role_changed') {
                $meta['changes'] = ['role' => ['old' => 'student', 'new' => 'staff']];
            }
            if ($action === 'member.offboarded') {
                $meta['reason'] = 'Graduated';
            }

            $this->log($action, null, $meta, $this->orgAdmin, $this->when(), [
                'target_type' => str_starts_with($action, 'org_counselor') ? 'counselor' : 'user',
                'target_id' => 500 + $i,
                'target_name' => null,
                'organization_id' => $this->orgs[0]->id,
            ]);
        }
    }

    private function counselors(): void
    {
        $names = ['Dr. Lim Wei Jie', 'Puan Nurul Huda', 'Encik Faizal Karim', 'Dr. Priya Nair'];
        $map = [
            'counselor.approved' => [],
            'counselor.rejected' => ['reason' => 'LKM registration could not be matched.'],
            'counselor.suspended' => ['reason' => 'Practising certificate expired.'],
            'counselor.reactivated' => [],
            'counselor.note_added' => ['note_id' => 7],
        ];

        $i = 0;
        foreach ($map as $action => $meta) {
            foreach (range(1, $action === 'counselor.approved' ? 3 : 1) as $_) {
                $name = $names[$i % 4];
                $this->log($action, null, $meta, $this->pick($this->admins), $this->when(), ['target_type' => 'counselor', 'target_id' => 20 + $i, 'target_name' => $name]);
                $i++;
            }
        }
    }

    private function lkmAndComplaints(): void
    {
        foreach (['lkm.record_created', 'lkm.record_updated', 'lkm.review_requested', 'lkm.review_resolved'] as $i => $action) {
            $meta = $action === 'lkm.record_updated' ? ['changes' => ['status' => ['old' => 'Active', 'new' => 'Expired'], 'expiry_date' => ['old' => '2026-03-01', 'new' => '2026-09-01']]] : ['record_id' => 900 + $i];
            $this->log($action, null, $meta, $this->pick($this->admins), $this->when(), ['target_type' => 'lkm_record', 'target_id' => 900 + $i, 'target_name' => 'LKM-2025-'.(1000 + $i)]);
        }

        // Complaints: reference ids only, never the complaint text.
        $changes = [['open', 'under_review'], ['under_review', 'escalated'], ['escalated', 'resolved']];
        foreach ($changes as $i => [$old, $new]) {
            $this->log('complaint.status_changed', null, ['complaint_id' => 40 + $i, 'changes' => ['status' => ['old' => $old, 'new' => $new]]], $this->pick($this->admins), $this->when(), ['target_type' => 'complaint', 'target_id' => 40 + $i, 'target_name' => 'Complaint #'.(40 + $i)]);
        }
        $this->log('complaint.warning_issued', null, ['complaint_id' => 41, 'counselor_id' => 22], $this->admins[0], $this->when(), ['target_type' => 'complaint', 'target_id' => 41, 'target_name' => 'Complaint #41']);
        $this->log('complaint.counselor_suspended', null, ['complaint_id' => 42, 'counselor_id' => 23, 'reason' => 'Serious breach of the code of conduct.'], $this->admins[0], $this->when(), ['target_type' => 'complaint', 'target_id' => 42, 'target_name' => 'Complaint #42']);
        $this->log('complaint.status_changed', null, ['complaint_id' => 43, 'changes' => ['status' => ['old' => 'open', 'new' => 'dismissed']]], $this->pick($this->admins), $this->when(), ['target_type' => 'complaint', 'target_id' => 43, 'target_name' => 'Complaint #43']);
    }

    private function moderationSettingsPricingAdmins(): void
    {
        foreach ([
            ['moderation.report_dismissed', []],
            ['moderation.post_removed', ['reason' => 'Contains personal contact details.']],
            ['moderation.user_warned', ['reason' => 'Repeated off-topic posts.']],
            ['moderation.post_removed', ['reason' => 'Harassment']],
        ] as [$action, $meta]) {
            $postId = rand(100, 999);
            $this->log($action, null, $meta + ['post_id' => $postId], $this->pick($this->admins), $this->when(), ['target_type' => 'feed_post', 'target_id' => $postId, 'target_name' => null]);
        }
        $this->log('moderation.report_dismissed', null, ['report_id' => 12], $this->pick($this->admins), $this->when(), ['target_type' => 'report', 'target_id' => 12, 'target_name' => null]);

        $this->log('settings.registration_toggled', null, ['changes' => ['counselor_registration' => ['old' => true, 'new' => false]]], $this->admins[0], $this->when());
        $this->log('settings.registration_toggled', null, ['changes' => ['counselor_registration' => ['old' => false, 'new' => true]]], $this->admins[0], $this->when());
        $this->log('settings.maintenance_toggled', null, ['changes' => ['maintenance_mode' => ['old' => false, 'new' => true]], 'reason' => 'Database upgrade window.'], $this->admins[0], $this->when());

        foreach ([['pricing.plan_created', []], ['pricing.plan_updated', ['changes' => ['monthly_price' => ['old' => '199.00', 'new' => '249.00']]]], ['pricing.plan_deleted', []], ['pricing.promo_created', ['code' => 'RAYA25']], ['pricing.promo_updated', ['changes' => ['discount_percent' => ['old' => 20, 'new' => 25]]]], ['pricing.promo_deleted', ['code' => 'OLD10']]] as $i => [$action, $meta]) {
            $this->log($action, null, $meta, $this->pick($this->admins), $this->when(), ['target_type' => str_contains($action, 'promo') ? 'promo_code' : 'plan', 'target_id' => 3 + $i, 'target_name' => str_contains($action, 'promo') ? ($meta['code'] ?? 'RAYA25') : 'Growth plan']);
        }

        foreach ([['admin.invited', []], ['admin.invite_revoked', []], ['admin.role_changed', ['changes' => ['role' => ['old' => 'viewer', 'new' => 'admin']]]], ['admin.removed', ['reason' => 'Left the company.']], ['admin.invited', []]] as $i => [$action, $meta]) {
            $this->log($action, $this->admins[1 + ($i % 2)] ?? $this->admins[0], $meta, $this->admins[0], $this->when());
        }
    }

    private function accountAndAuth(): void
    {
        $account = ['account.password_changed', 'account.email_change_requested', 'account.email_changed', 'account.two_factor_enabled', 'account.two_factor_disabled', 'account.two_factor_failed', 'account.recovery_code_used', 'account.recovery_codes_regenerated', 'account.other_sessions_revoked', 'account.avatar_changed', 'account.password_changed', 'account.two_factor_failed'];

        foreach ($account as $action) {
            $admin = $this->pick($this->admins);
            $meta = match ($action) {
                'account.other_sessions_revoked' => ['sessions' => rand(1, 4)],
                'account.recovery_code_used' => ['remaining' => rand(2, 7)],
                'account.avatar_changed' => ['change' => 'uploaded'],
                'account.email_change_requested' => ['new_email_domain' => 'mykaunsel.test'],
                default => [],
            };
            $this->log($action, $admin, $meta, $admin, $this->when());
        }

        // Sign-in activity (hidden on the page unless "Include sign-in activity" is on).
        foreach (range(1, 14) as $i) {
            $student = $this->pick($this->students);
            $failed = $i % 4 === 0;
            $this->log($failed ? 'auth.login_failed' : 'auth.login', $student, [], $student, $this->when());
        }
        foreach (range(1, 4) as $i) {
            $admin = $this->pick($this->admins);
            $this->log('auth.login', $admin, [], $admin, $this->when());
        }
        foreach (['nobody@example.com', 'typo@example.com'] as $guess) {
            $this->log('auth.login_failed', null, ['email_hash' => hash('sha256', $guess)], null, $this->when(), ['ip' => '203.0.113.44', 'actor_type' => 'guest']);
        }
    }

    private function system(): void
    {
        $jobs = [
            ['system.booking_requests_expired', ['count' => 4]],
            ['system.booking_requests_expired', ['count' => 1]],
            ['system.chat_purged', ['sessions' => 36, 'older_than_days' => 30]],
            ['system.chat_purged', ['sessions' => 12, 'older_than_days' => 30]],
            ['counselor.pa_expired_suspended', ['counselor_id' => 23, 'reason' => 'Practising certificate expired on 2026-08-31.']],
            ['system.booking_requests_expired', ['count' => 7]],
            ['system.chat_purged', ['sessions' => 51, 'older_than_days' => 30]],
            ['counselor.pa_expired_suspended', ['counselor_id' => 26, 'reason' => 'Practising certificate expired on 2026-09-15.']],
        ];

        foreach ($jobs as [$action, $meta]) {
            $this->log($action, null, $meta, 'system', $this->when(), $action === 'counselor.pa_expired_suspended'
                ? ['target_type' => 'counselor', 'target_id' => $meta['counselor_id'], 'target_name' => 'Dr. Priya Nair'] : []);
        }

        foreach (range(1, 2) as $i) {
            $this->log('audit.exported', null, ['format' => 'csv', 'rows' => rand(50, 400), 'filters' => ['range' => '30d']], $this->pick($this->admins), $this->when());
        }
    }

    private function edgeCases(): void
    {
        // A target that no longer exists (shown as "(deleted)").
        $this->log('organization.suspended', null, ['reason' => 'Closed down — the organization asked to be removed.', 'changes' => ['status' => ['old' => 'active', 'new' => 'suspended']]], $this->admins[0], $this->when(), [
            'target_type' => 'organization', 'target_id' => 99999, 'target_name' => 'Syarikat Lama Sdn Bhd', 'organization_id' => 99999,
        ]);
        $this->log('member.removed', null, ['member_id' => 99998], $this->orgAdmin, $this->when(), ['target_type' => 'user', 'target_id' => 99998, 'target_name' => null, 'organization_id' => $this->orgs[0]->id]);

        // Long text and many details.
        $this->log('organization.rejected', $this->pick($this->orgs), [
            'reason' => 'The application could not be approved. '.str_repeat('The documents provided were incomplete and the registered address did not match the premises photographs. ', 16),
            'changes' => ['status' => ['old' => 'pending', 'new' => 'rejected'], 'rejection_template' => ['old' => null, 'new' => 'documents_incomplete']],
            'reviewer_notes_count' => 3, 'attempt' => 2, 'domains_checked' => 2, 'channel' => 'platform-admin-ui',
        ], $this->admins[0], $this->when());
    }

    // ---- helpers ---------------------------------------------------------------------------

    /** Write one sample entry through the real logger. */
    private function log(string $action, $target, array $metadata, User|string|null $actor, ?Carbon $at = null, array $options = []): void
    {
        $this->n++;

        Audit::log($action, $target, $metadata + ['seeded' => true, 'sample_set' => self::SET], ($options['actor_type'] ?? null) === 'guest' ? null : ($actor ?? 'system'), $options + [
            'at' => $at ?? $this->when(),
            'ip' => $actor === 'system' ? null : '203.0.113.'.rand(2, 250),
            'user_agent' => $actor === 'system' ? null : self::AGENTS[$this->n % count(self::AGENTS)],
            'request_id' => (string) Str::uuid(),
        ]);
    }

    private function when(): Carbon
    {
        return now()->subDays(rand(0, 119))->setTime(rand(7, 21), rand(0, 59), rand(0, 59));
    }

    private function pick(array $items)
    {
        return $items[array_rand($items)];
    }
}
