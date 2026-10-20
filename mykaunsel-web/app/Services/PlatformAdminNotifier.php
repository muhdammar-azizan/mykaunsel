<?php

namespace App\Services;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\NewOrganizationApplication;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Emails to Platform Admins. Every method checks each admin's own setting (Account → Notifications),
 * and never throws: a mail problem must not break the action that triggered it.
 */
class PlatformAdminNotifier
{
    public const NEW_ORGANIZATION_APPLICATION = 'new_organization_application';

    /** @return int how many admins were emailed */
    public function newOrganizationApplication(Organization $organization): int
    {
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
