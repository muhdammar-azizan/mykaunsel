<?php

namespace Database\Seeders;

use App\Enums\AdminInvitationStatus;
use App\Models\AdminInvitation;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Sample invitations for development (one in each state) so the Invitations tab has something to show.
 * The tokens are random and thrown away, so none of these links can be opened. Local and testing only.
 */
class AdminInvitationSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('AdminInvitationSeeder only runs in the local and testing environments.');
        }

        $sender = User::where('email', 'admin@mykaunsel.com')->first();

        if ($sender === null) {
            return;
        }

        $rows = [
            ['Sample Pending', 'sample.pending@example.com', AdminInvitationStatus::Pending, now()->addHours(30), []],
            ['Sample Expired', 'sample.expired@example.com', AdminInvitationStatus::Pending, now()->subHours(5), []],
            ['Sample Revoked', 'sample.revoked@example.com', AdminInvitationStatus::Revoked, now()->addHours(10), ['revoked_at' => now()->subHours(2)]],
            ['Sample Accepted', 'sample.accepted@example.com', AdminInvitationStatus::Accepted, now()->addHours(10), ['accepted_at' => now()->subHour()]],
        ];

        foreach ($rows as [$name, $email, $status, $expires, $extra]) {
            AdminInvitation::create([
                'name' => $name,
                'email' => $email,
                'token_hash' => hash('sha256', random_bytes(32)),
                'active_email' => $status === AdminInvitationStatus::Pending ? $email : null,
                'status' => $status->value,
                'invited_by_user_id' => $sender->id,
                'expires_at' => $expires,
                'last_sent_at' => now()->subHours(3),
                'sent_count' => 1,
            ] + $extra);
        }
    }
}
