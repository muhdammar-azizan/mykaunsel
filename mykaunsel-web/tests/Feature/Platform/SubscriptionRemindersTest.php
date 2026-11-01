<?php

namespace Tests\Feature\Platform;

use App\Enums\AccessModel;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserNotification;
use App\Notifications\SubscriptionEndedMail;
use App\Notifications\SubscriptionEndingMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** The warnings before a subscription ends (config/subscription-reminders.php): each step once, to the right people, and again after a renewal. */
class SubscriptionRemindersTest extends TestCase
{
    use RefreshDatabase;

    private Organization $acme;

    private User $hana;

    private User $ismail;

    private User $aina;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acme = $this->organization('Acme Berhad', '2027-01-31');
        $this->hana = $this->person($this->acme, MembershipRole::OrgAdmin, 'Hana HR');
        $this->ismail = $this->person($this->acme, MembershipRole::OrgAdmin, 'Ismail HR');
        $this->aina = $this->person($this->acme, MembershipRole::Counselor, 'Aina Counselor');
        $this->on('2026-11-02');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function on(string $day, string $time = '08:30'): void
    {
        Carbon::setTestNow(Carbon::parse($day.' '.$time, 'Asia/Kuala_Lumpur'));
    }

    private function organization(string $name, ?string $lastDay, OrgType $type = OrgType::Corporate, OrgStatus $status = OrgStatus::Active): Organization
    {
        return Organization::create([
            'name' => $name, 'org_type' => $type, 'access_model' => AccessModel::Closed, 'subscription_status' => $status,
            'subscription_ends_at' => $lastDay === null ? null : $lastDay.' 23:59:59',
        ]);
    }

    private function person(Organization $org, MembershipRole $role, string $name, bool $verified = true, MembershipStatus $status = MembershipStatus::Active): User
    {
        $user = User::factory()->create(['name' => $name, 'email_verified_at' => $verified ? now() : null]);
        Membership::create(['user_id' => $user->id, 'org_id' => $org->id, 'role' => $role, 'status' => $status, 'joined_at' => now()]);

        return $user;
    }

    private function remind(): void
    {
        $this->artisan('subscriptions:remind')->assertSuccessful();
    }

    // ---- the steps ---------------------------------------------------------------------------------------------

    public function test_the_admins_are_warned_at_60_30_14_and_7_days_each_step_once(): void
    {
        Notification::fake();

        foreach (['2026-11-30' => 62, '2026-12-02' => 60, '2026-12-03' => 59, '2026-12-17' => 45, '2027-01-01' => 30, '2027-01-02' => 29, '2027-01-17' => 14, '2027-01-24' => 7] as $day => $left) {
            $this->on($day);
            $this->remind();
            $this->remind();                                             // asked twice the same morning
        }

        Notification::assertSentToTimes($this->hana, SubscriptionEndingMail::class, 4);
        Notification::assertSentToTimes($this->ismail, SubscriptionEndingMail::class, 4);
    }

    public function test_the_counselors_are_warned_at_30_and_14_days_only_by_e_mail(): void
    {
        Notification::fake();

        foreach (['2026-12-02', '2027-01-01', '2027-01-17', '2027-01-24'] as $day) {
            $this->on($day);
            $this->remind();
        }

        Notification::assertSentToTimes($this->aina, SubscriptionEndingMail::class, 2);
        $this->assertSame(0, UserNotification::query()->ownedBy($this->aina)->count());
    }

    public function test_a_first_run_that_comes_late_sends_one_warning_the_most_urgent_and_writes_the_earlier_steps_as_done(): void
    {
        Notification::fake();
        $this->on('2027-01-11');                                         // 20 days left: the 60 and the 30 day steps are behind us

        $this->remind();
        Notification::assertSentToTimes($this->hana, SubscriptionEndingMail::class, 1);

        $this->assertSame([60, 30], DB::table('subscription_reminders')->where(['org_id' => $this->acme->id, 'audience' => 'admin'])->orderByDesc('step')->pluck('step')->map(fn ($s) => (int) $s)->all());

        $this->on('2027-01-11');
        $this->remind();
        Notification::assertSentToTimes($this->hana, SubscriptionEndingMail::class, 1);

        $this->on('2027-01-17');                                         // and the next step still comes
        $this->remind();
        Notification::assertSentToTimes($this->hana, SubscriptionEndingMail::class, 2);
    }

    public function test_the_mail_says_when_it_ends_what_happens_and_where_to_renew(): void
    {
        $mail = (new SubscriptionEndingMail($this->acme->id, 30, 'admin', '2027-01-31'))->toMail($this->hana);
        $text = implode("\n", array_map(fn ($line) => (string) $line, $mail->introLines));

        $this->assertSame('Your subscription ends in 30 days (Acme Berhad)', $mail->subject);
        $this->assertStringContainsString('Its last day is 31 Jan 2027', $text);
        $this->assertStringContainsString('no paid day is lost', $text);
        $this->assertSame(route('org.subscription'), $mail->actionUrl);

        $this->assertSame('Your subscription ends tomorrow (Acme Berhad)', (new SubscriptionEndingMail($this->acme->id, 1, 'admin', '2027-01-31'))->toMail($this->hana)->subject);
        $this->assertSame('Your subscription ends today (Acme Berhad)', (new SubscriptionEndingMail($this->acme->id, 0, 'admin', '2027-01-31'))->toMail($this->hana)->subject);

        $counselor = (new SubscriptionEndingMail($this->acme->id, 14, 'counselor', '2027-01-31'))->toMail($this->aina);
        $this->assertStringContainsString('hidden from them', implode("\n", array_map(fn ($line) => (string) $line, $counselor->introLines)));
        $this->assertNull($counselor->actionUrl);
    }

    // ---- the bell ----------------------------------------------------------------------------------------------

    public function test_each_admin_also_gets_one_bell_notification_per_step_that_opens_the_subscription_page(): void
    {
        $this->on('2026-12-02');
        $this->remind();
        $this->remind();
        $this->on('2027-01-01');
        $this->remind();

        $this->assertSame(2, UserNotification::query()->ownedBy($this->hana)->where('type', 'subscription.ending')->count());
        $this->assertSame(2, UserNotification::query()->ownedBy($this->ismail)->where('type', 'subscription.ending')->count());

        $as = fn () => $this->actingAs($this->hana)->withSession(['current_org_id' => $this->acme->id, 'current_role' => 'org_admin']);
        $as()->get(route('portal.notifications'))->assertOk()->assertSee('Your subscription is about to end');
        $id = UserNotification::query()->ownedBy($this->hana)->value('id');
        $as()->post(route('portal.notifications.open', $id))->assertRedirect(route('org.subscription'));
    }

    // ---- after the end -----------------------------------------------------------------------------------------

    public function test_when_it_has_ended_the_admins_are_told_once(): void
    {
        Notification::fake();

        $this->on('2027-02-01');
        $this->remind();
        $this->on('2027-02-02');
        $this->remind();

        Notification::assertSentToTimes($this->hana, SubscriptionEndedMail::class, 1);
        Notification::assertNotSentTo($this->aina, SubscriptionEndedMail::class);
        $this->assertSame(1, UserNotification::query()->ownedBy($this->hana)->where('type', 'subscription.ended')->count());
    }

    public function test_something_that_ended_long_ago_is_not_announced_now(): void
    {
        Notification::fake();
        $old = $this->organization('Old Berhad', '2026-06-30');
        $this->person($old, MembershipRole::OrgAdmin, 'Old Admin');

        $this->remind();

        Notification::assertNothingSent();
        $this->assertSame(0, DB::table('subscription_reminders')->where('org_id', $old->id)->count());
    }

    // ---- a renewal starts the steps again ----------------------------------------------------------------------

    public function test_a_renewal_has_a_new_last_day_so_its_steps_start_again_and_nothing_is_sent_while_it_is_far_off(): void
    {
        Notification::fake();
        $this->on('2027-01-24');
        $this->remind();                                                 // 7 days left: the old plan's steps are done (60, 30, 14 and 7)
        Notification::assertSentToTimes($this->hana, SubscriptionEndingMail::class, 1);

        $this->acme->update(['subscription_ends_at' => '2028-01-31 23:59:59']);      // renewed: a new last day, a year further

        $this->on('2027-01-25');
        $this->remind();
        Notification::assertSentToTimes($this->hana, SubscriptionEndingMail::class, 1);

        $this->on('2027-12-02');                                          // 60 days before the new last day
        $this->remind();
        Notification::assertSentToTimes($this->hana, SubscriptionEndingMail::class, 2);
    }

    // ---- nothing is lost -----------------------------------------------------------------------------------------

    public function test_a_step_is_not_written_down_when_there_is_nobody_to_tell_and_the_next_run_tries_again(): void
    {
        Notification::fake();
        $empty = $this->organization('Empty Berhad', '2027-01-31');
        $later = null;

        $this->on('2026-12-02');
        $this->remind();
        $this->assertSame(0, DB::table('subscription_reminders')->where('org_id', $empty->id)->count());          // nobody to tell: nothing written down

        $later = $this->person($empty, MembershipRole::OrgAdmin, 'Joins Later');                                   // an admin arrives afterwards

        $this->on('2026-12-03');
        $this->remind();

        Notification::assertSentTo($later, SubscriptionEndingMail::class);
    }

    public function test_a_step_is_taken_back_when_not_one_message_could_be_handed_over(): void
    {
        $this->on('2026-12-02');
        $broken = new class extends User
        {
            public function notify($instance): void
            {
                throw new \RuntimeException('mail server down');
            }
        };
        // Every recipient fails: the run must not mark the step as done.
        $billing = new class($broken) extends \App\Services\BillingMail
        {
            public function __construct(private $broken) {}

            public function recipients(Organization $org): \Illuminate\Support\Collection
            {
                return collect([$this->broken->newInstance()->forceFill(['id' => 99999, 'name' => 'X'])]);
            }
        };
        app()->instance(\App\Services\BillingMail::class, $billing);
        $counts = app(\App\Services\SubscriptionReminders::class)->run();

        $this->assertSame(0, $counts['admin_mails']);
        $this->assertSame(0, DB::table('subscription_reminders')->where(['org_id' => $this->acme->id, 'audience' => 'admin'])->count());
    }

    public function test_a_mail_still_says_what_was_true_when_it_was_made_even_after_a_renewal(): void
    {
        $mail = new SubscriptionEndingMail($this->acme->id, 7, 'admin', '2027-01-31');
        $this->acme->update(['subscription_ends_at' => '2028-01-31 23:59:59']);                                   // renewed before the worker got to it

        $text = implode("\n", array_map(fn ($line) => (string) $line, $mail->toMail($this->hana)->introLines));

        $this->assertStringContainsString('Its last day is 31 Jan 2027', $text);
        $this->assertStringContainsString('ended on 31 Jan 2027', implode("\n", array_map(fn ($line) => (string) $line, (new SubscriptionEndedMail($this->acme->id, '2027-01-31'))->toMail($this->hana)->introLines)));
    }

    // ---- the bell tells the truth ----------------------------------------------------------------------------------

    public function test_the_bell_items_show_as_dealt_with_once_the_organization_has_renewed(): void
    {
        $this->on('2027-01-24');
        $this->remind();
        $registry = app(\App\Support\Inbox\NotificationRegistry::class);
        $state = fn () => $registry->get('subscription.ending')->states([$this->acme->id])[$this->acme->id];

        $this->assertSame('open', $state());

        $this->acme->update(['subscription_ends_at' => '2028-01-31 23:59:59']);

        $this->assertSame('handled', $state());

        $ended = fn () => $registry->get('subscription.ended')->states([$this->acme->id])[$this->acme->id];
        $this->acme->update(['subscription_ends_at' => '2027-01-31 23:59:59']);
        $this->on('2027-02-02');
        $this->assertSame('open', $ended());

        $this->acme->update(['subscription_ends_at' => '2028-01-31 23:59:59']);
        $this->assertSame('handled', $ended());
    }

    // ---- who is not written to ---------------------------------------------------------------------------------

    public function test_only_active_people_with_a_confirmed_address_in_organizations_that_have_a_plan_are_written_to(): void
    {
        Notification::fake();
        $unconfirmed = $this->person($this->acme, MembershipRole::OrgAdmin, 'Not Confirmed', verified: false);
        $former = $this->person($this->acme, MembershipRole::OrgAdmin, 'Former', status: MembershipStatus::Offboarded);
        $noPlan = $this->organization('No Plan Berhad', null);
        $noPlanAdmin = $this->person($noPlan, MembershipRole::OrgAdmin, 'No Plan Admin');
        $pending = $this->organization('Pending Berhad', '2027-01-31', status: OrgStatus::Pending);
        $pendingAdmin = $this->person($pending, MembershipRole::OrgAdmin, 'Pending Admin');
        $platform = $this->organization('MyKaunsel Platform', null, OrgType::Platform);
        $independent = $this->person($platform, MembershipRole::Counselor, 'Independent');

        $this->on('2026-12-02');
        $this->remind();

        Notification::assertSentTo([$this->hana, $this->ismail], SubscriptionEndingMail::class);
        Notification::assertNotSentTo([$unconfirmed, $former, $noPlanAdmin, $pendingAdmin, $independent, $this->aina], SubscriptionEndingMail::class);
    }

    public function test_a_dry_run_counts_and_changes_nothing_and_a_real_run_is_audited_with_counts_only(): void
    {
        Notification::fake();
        $this->on('2026-12-02');

        $this->artisan('subscriptions:remind', ['--dry-run' => true])->expectsOutputToContain('Would send 2 warnings to admins')->assertSuccessful();
        $this->assertSame(0, DB::table('subscription_reminders')->count());
        Notification::assertNothingSent();

        $this->remind();
        $entry = AuditLog::where('action', 'subscription.reminders_sent')->sole();
        $this->assertStringNotContainsString('Hana', json_encode($entry->toArray()));
        $this->assertSame(1, AuditLog::where('action', 'subscription.reminders_sent')->count());
    }

    public function test_the_command_is_scheduled_every_day(): void
    {
        $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'subscriptions:remind'));

        $this->assertNotNull($event);
        $this->assertSame('30 0 * * *', $event->expression);
    }
}
