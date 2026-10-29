<?php

namespace App\Services;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgType;
use App\Exceptions\AdminChangeBlocked;
use App\Facades\Audit;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\PlatformAdminRemoved;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Who is a Platform Admin: removing them. (Adding is done through invitations, see AdminInvitationService.)
 *
 * An admin is a membership with the platform_admin role in the Platform organization. Removing keeps the row
 * (status offboarded, with the date), so the history stays and the person can be invited again later.
 * Every change and its audit entry share one transaction; emails go out only after the commit.
 */
class PlatformAdminService
{
    public function __construct(private AccountSessionEnder $sessions, private AdminInvitationService $invitations, private NotificationCenter $inbox) {}

    public function platformOrganization(): Organization
    {
        return Organization::query()->where('org_type', OrgType::Platform->value)->firstOrFail();
    }

    /**
     * Take Platform Admin access away. Never yourself, and never the last active admin (two admins removing each other
     * at the same time cannot both succeed: the admin rows are locked while the count is checked).
     *
     * The person is also signed out everywhere at once, in the same transaction: their browser sessions (when sessions are
     * kept in the database), their remember-me token and their API tokens. (CheckMembershipStatus is the second line: it
     * signs them out on their next request even if sessions are kept somewhere else.) The invitations they sent that are
     * still waiting are revoked. They get a short email without the reason, queued and sent after the commit. Older audit
     * entries they wrote are not touched.
     *
     * @throws AdminChangeBlocked
     */
    public function remove(User $actor, User $admin, string $reason): void
    {
        DB::transaction(function () use ($actor, $admin, $reason) {
            $platform = $this->platformOrganization();

            $admins = Membership::query()
                ->where('org_id', $platform->id)
                ->where('role', MembershipRole::PlatformAdmin->value)
                ->lockForUpdate()
                ->get();

            $membership = $admins->firstWhere('user_id', $admin->id);

            if ($membership === null || $membership->status !== MembershipStatus::Active) {
                throw new AdminChangeBlocked(["{$admin->name} is no longer a Platform Admin."]);
            }

            if ($admin->is($actor)) {
                throw new AdminChangeBlocked(['You cannot remove yourself. Ask another Platform Admin to do it.']);
            }

            if ($admins->where('status', MembershipStatus::Active)->count() <= 1) {
                throw new AdminChangeBlocked(['This is the last active Platform Admin, so they cannot be removed.']);
            }

            // Logged first: while they are still active the entry keeps their name (non-staff people are stored by id only).
            Audit::log('admin.removed', $admin, ['reason' => $reason, 'signed_out' => true], $actor);

            $membership->update([
                'status' => MembershipStatus::Offboarded->value,
                'offboarded_at' => now(),
                'offboard_reason' => $reason,
            ]);

            $this->sessions->endAll($admin);
            $this->invitations->revokeSentBy($admin);
            $this->inbox->deleteAllFor($admin);   // their inbox is deleted with their access
        });

        $this->sendRemoved($admin);
    }

    /** Queued and sent after the commit; a mail problem never undoes the removal. */
    private function sendRemoved(User $user): void
    {
        try {
            $user->notify(new PlatformAdminRemoved);
        } catch (Throwable $e) {
            Log::warning('Could not email a removed Platform Admin.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }
}
