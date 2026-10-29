<?php

namespace App\Services;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\CounselorProfile;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\NewCounselorApplication;
use App\Notifications\NewOrganizationApplication;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells Platform Admins about new applications, in two ways from this ONE place (so the two can never drift apart):
 * - the in-app notification (the bell): always, for every active admin;
 * - the email: only for admins who have it switched on (Account → Notifications). The preference controls email only.
 * Neither ever throws: a problem must not break the sign-up or the submission that triggered it. Callers run it after the commit.
 */
class PlatformAdminNotifier
{
    public function __construct(private readonly NotificationCenter $inbox) {}

    public const NEW_ORGANIZATION_APPLICATION = 'new_organization_application';

    public const NEW_COUNSELOR_APPLICATION = 'new_counselor_application';

    /** @return int how many admins were emailed */
    public function newOrganizationApplication(Organization $organization): int
    {
        // The occurrence is the application's number, so a retry of the same submission adds nothing.
        $this->inbox->publish('organization.application_submitted', $organization, $organization->name, (string) max(1, (int) $organization->submission_count));

        $sent = 0;

        foreach ($this->adminsWanting(self::NEW_ORGANIZATION_APPLICATION) as $admin) {
            try {
                $admin->notify(new NewOrganizationApplication($organization));
                $sent++;
            } catch (Throwable $e) {
                Log::warning('Could not email a Platform Admin about a new application.', ['admin_id' => $admin->id, 'org_id' => $organization->id, 'error' => $e->getMessage()]);
            }
        }

        return $sent;
    }

    /** @return int how many admins were emailed */
    public function newCounselorApplication(CounselorProfile $profile, bool $resubmitted = false): int
    {
        // A first submission and each resubmission (its attempt number) are separate occurrences.
        $this->inbox->publish(
            $resubmitted ? 'counselor.application_resubmitted' : 'counselor.application_submitted',
            $profile,
            $profile->loadMissing('user')->user?->name,
            (string) max(1, (int) $profile->submission_count),
        );

        $sent = 0;

        foreach ($this->adminsWanting(self::NEW_COUNSELOR_APPLICATION) as $admin) {
            try {
                $admin->notify(new NewCounselorApplication($profile, $resubmitted));
                $sent++;
            } catch (Throwable $e) {
                Log::warning('Could not email a Platform Admin about a counselor application.', ['admin_id' => $admin->id, 'profile_id' => $profile->id, 'error' => $e->getMessage()]);
            }
        }

        return $sent;
    }

    /** @return Collection<int, User> active Platform Admins who have this notification switched on */
    private function adminsWanting(string $key): Collection
    {
        return User::query()
            ->whereHas('memberships', fn ($q) => $q
                ->where('role', MembershipRole::PlatformAdmin->value)
                ->where('status', MembershipStatus::Active->value))
            ->get()
            ->filter(fn (User $admin) => $admin->wantsNotification($key))
            ->values();
    }
}
