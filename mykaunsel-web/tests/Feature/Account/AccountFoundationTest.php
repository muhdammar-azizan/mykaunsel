<?php

namespace Tests\Feature\Account;

use App\Facades\Audit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_defaults_come_from_config_and_can_be_overridden(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->wantsNotification('escalated_complaint'));

        $user->update(['notification_preferences' => ['escalated_complaint' => false]]);

        $this->assertFalse($user->fresh()->wantsNotification('escalated_complaint'));
        $this->assertTrue($user->fresh()->wantsNotification('weekly_summary'));
        $this->assertFalse($user->wantsNotification('unknown_key'));
    }

    public function test_audit_log_records_actor_action_and_ip(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $log = Audit::log('account.password_changed', $user, ['note' => 'ok']);

        $this->assertSame($user->id, $log->actor_user_id);
        $this->assertSame('account.password_changed', $log->action);
        $this->assertSame('user', $log->target_type);
        $this->assertSame(['note' => 'ok'], $log->metadata);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_login_records_last_login_time_and_ip(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
        $this->assertNotNull($user->last_login_ip);
    }

    public function test_new_users_default_to_kuala_lumpur_timezone(): void
    {
        $this->assertSame('Asia/Kuala_Lumpur', User::factory()->create()->fresh()->timezone);
    }
}
