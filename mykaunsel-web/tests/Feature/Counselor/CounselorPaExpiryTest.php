<?php

namespace Tests\Feature\Counselor;

use App\Enums\BookingRequestStatus;
use App\Enums\VerificationStatus;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\CounselorProfile;
use App\Models\LkmDirectorySnapshot;
use App\Models\User;
use App\Notifications\ClientSessionNotice;
use App\Notifications\CounselorPaRenewalReminder;
use App\Notifications\CounselorSuspended;
use App\Notifications\CounselorSuspendedForOrganization;
use App\Services\CounselorPaExpiryService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class CounselorPaExpiryTest extends CounselorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSamples();
        Carbon::setTestNow(Carbon::parse('2026-10-25 00:10:00', config('app.timezone')));
    }

    /** Sets the PA date on the profile AND on its LKM record (so the daily check's LKM look-up agrees). */
    private function setPa(string $kb, string $date, ?string $lkmDate = null, ?string $lkmStatus = null): CounselorProfile
    {
        $profile = $this->profile($kb);
        $profile->update(['pa_valid_until' => $date, 'pa_reminder_for' => null, 'pa_reminder_days' => null]);
        LkmDirectorySnapshot::where('kb_number', $kb)->update(array_filter(['pa_valid_until' => $lkmDate ?? $date, 'status' => $lkmStatus]));

        return $profile->fresh();
    }

    private function check(bool $dryRun = false): array
    {
        return app(CounselorPaExpiryService::class)->run($dryRun);
    }

    private function logs(string $action, CounselorProfile $profile)
    {
        return AuditLog::where('action', $action)->where('target_type', 'counselor')->where('target_id', $profile->id)->get();
    }

    // ---- expired => suspended -----------------------------------------------------------------

    public function test_an_expired_pa_suspends_the_counselor_by_the_system(): void
    {
        Notification::fake();
        $profile = $this->setPa('KB10891', '2026-10-20');
        $this->assertTrue($profile->isListable() === false);

        $result = $this->check();

        $this->assertContains('KB10891', $result['suspended']);
        $profile->refresh();
        $this->assertSame(VerificationStatus::Suspended, $profile->verification_status);
        $this->assertSame('system', $profile->suspension_source);
        $this->assertNull($profile->suspended_by);
        $this->assertNotNull($profile->suspended_at);
        $this->assertStringContainsString('expired on 20 Oct 2026', $profile->suspension_reason);

        $log = $this->logs('counselor.pa_expired_suspended', $profile)->sole();
        $this->assertSame('system', $log->actor_type);
        $this->assertNull($log->actor_user_id);
        $this->assertSame(['status' => ['old' => 'approved', 'new' => 'suspended']], $log->metadata['changes']);
        $this->assertSame(['system', 'keep'], [$log->metadata['source'], $log->metadata['sessions']]);
        $this->assertCount(0, $this->logs('counselor.suspended', $profile));

        Notification::assertSentTo($profile->user, CounselorSuspended::class, fn ($n) => str_contains(implode(' ', $n->toMail($profile->user)->introLines), 'expired on 20 Oct 2026'));
    }

    public function test_booked_sessions_are_kept_and_pending_requests_are_closed_with_clients_told(): void
    {
        Notification::fake();
        $this->setPa('KB10891', '2026-10-20');
        $before = Booking::where('counselor_user_id', $this->profile('KB10891')->user_id)->pluck('status', 'id')->map->value->all();
        $client = User::where('email', 'klien.contoh@example.test')->firstOrFail();
        $request = BookingRequest::create([
            'user_id' => $client->id, 'counselor_user_id' => $this->profile('KB10891')->user_id,
            'requested_date' => now()->addDays(5)->toDateString(), 'requested_time' => '10:00:00',
            'status' => BookingRequestStatus::Pending, 'expires_at' => now()->addHours(48),
        ]);

        $this->check();

        $this->assertSame($before, Booking::where('counselor_user_id', $this->profile('KB10891')->user_id)->pluck('status', 'id')->map->value->all());
        $this->assertSame(BookingRequestStatus::Expired, $request->fresh()->status);
        Notification::assertSentTo($client, ClientSessionNotice::class);
    }

    public function test_the_organization_admins_are_told_when_their_counselor_is_suspended(): void
    {
        Notification::fake();
        $this->setPa('KB10234', '2026-10-20');

        $this->check();

        Notification::assertSentTo(User::where('email', 'afif@umpsa.edu.my')->firstOrFail(), CounselorSuspendedForOrganization::class);
    }

    public function test_the_last_valid_day_counts_in_full(): void
    {
        Notification::fake();
        $this->setPa('KB10891', '2026-10-24');

        // Last second of 24 Oct: still valid.
        Carbon::setTestNow(Carbon::parse('2026-10-24 23:59:59', config('app.timezone')));
        $this->assertSame([], $this->check()['suspended']);
        $this->assertSame(VerificationStatus::Approved, $this->profile('KB10891')->verification_status);

        // First second of 25 Oct: expired.
        Carbon::setTestNow(Carbon::parse('2026-10-25 00:00:00', config('app.timezone')));
        $this->assertContains('KB10891', $this->check()['suspended']);
    }

    public function test_a_renewed_pa_in_the_lkm_directory_extends_instead_of_suspending(): void
    {
        Notification::fake();
        $this->setPa('KB10891', '2026-10-20', lkmDate: '2027-10-20');

        $result = $this->check();

        $this->assertSame([], $result['suspended']);
        $this->assertContains('KB10891', $result['extended']);
        $profile = $this->profile('KB10891');
        $this->assertSame(VerificationStatus::Approved, $profile->verification_status);
        $this->assertSame('2027-10-20', $profile->pa_valid_until->toDateString());

        $log = $this->logs('counselor.lkm_rechecked', $profile)->sole();
        $this->assertSame('system', $log->actor_type);
        $this->assertSame(['old' => '2026-10-20', 'new' => '2027-10-20'], $log->metadata['changes']['pa_valid_until']);
        Notification::assertNotSentTo($profile->user, CounselorSuspended::class);
    }

    public function test_an_lkm_record_that_is_not_active_does_not_save_them(): void
    {
        Notification::fake();
        $this->setPa('KB10891', '2026-10-20', lkmDate: '2027-10-20', lkmStatus: 'Digantung');

        $this->assertContains('KB10891', $this->check()['suspended']);
        $this->assertSame('2026-10-20', $this->profile('KB10891')->pa_valid_until->toDateString());
    }

    public function test_running_twice_suspends_only_once(): void
    {
        Notification::fake();
        $profile = $this->setPa('KB10891', '2026-10-20');

        $this->check();
        $second = $this->check();

        $this->assertSame([], $second['suspended']);
        $this->assertCount(1, $this->logs('counselor.pa_expired_suspended', $profile));
        Notification::assertSentToTimes($profile->user, CounselorSuspended::class, 1);
    }

    public function test_only_approved_counselors_are_touched(): void
    {
        Notification::fake();
        // Pending / rejected / already suspended with an old PA date.
        $others = CounselorProfile::whereIn('verification_status', ['pending', 'rejected', 'suspended'])->get();
        $before = $others->pluck('verification_status', 'id')->map->value->all();
        CounselorProfile::whereIn('id', $others->pluck('id'))->update(['pa_valid_until' => '2020-01-01']);
        $about = fn () => AuditLog::where('target_type', 'counselor')->whereIn('target_id', $others->pluck('id'))->count();
        $auditBefore = $about();

        $result = $this->check();

        $this->assertSame([], $result['suspended']);
        $this->assertEquals($before, CounselorProfile::whereIn('id', $others->pluck('id'))->pluck('verification_status', 'id')->map->value->all());
        $this->assertSame($auditBefore, $about());
    }

    // ---- reminders ---------------------------------------------------------------------------

    public function test_reminders_go_out_once_per_step(): void
    {
        Notification::fake();
        $profile = $this->setPa('KB10891', '2026-11-14'); // 20 days left on 25 Oct

        $this->assertContains('KB10891', $this->check()['reminded']);                       // step 30
        $this->assertNotContains('KB10891', $this->check()['reminded']);                    // same day again

        Carbon::setTestNow(Carbon::parse('2026-11-04 00:10:00', config('app.timezone'))); // 10 left: still the 30 step
        $this->assertNotContains('KB10891', $this->check()['reminded']);

        Carbon::setTestNow(Carbon::parse('2026-11-07 00:10:00', config('app.timezone'))); // 7 left: the 7 step
        $this->assertContains('KB10891', $this->check()['reminded']);

        Carbon::setTestNow(Carbon::parse('2026-11-10 00:10:00', config('app.timezone'))); // 4 left: 7 step already sent
        $this->assertNotContains('KB10891', $this->check()['reminded']);

        Carbon::setTestNow(Carbon::parse('2026-11-13 00:10:00', config('app.timezone'))); // 1 left: the 1 step
        $this->assertContains('KB10891', $this->check()['reminded']);

        Carbon::setTestNow(Carbon::parse('2026-11-14 00:10:00', config('app.timezone'))); // last valid day: nothing more
        $this->assertNotContains('KB10891', $this->check()['reminded']);

        Notification::assertSentToTimes($profile->user, CounselorPaRenewalReminder::class, 3);
        $this->assertCount(3, $this->logs('counselor.renewal_reminder_sent', $profile));
        $this->assertSame('system', $this->logs('counselor.renewal_reminder_sent', $profile)->first()->actor_type);
    }

    public function test_a_late_run_sends_one_reminder_for_the_current_step_only(): void
    {
        Notification::fake();
        $profile = $this->setPa('KB10891', '2026-10-28'); // 3 days left: the 7 step

        $this->check();
        $this->check();

        Notification::assertSentToTimes($profile->user, CounselorPaRenewalReminder::class, 1);
        $this->assertSame(7, $profile->fresh()->pa_reminder_days);
    }

    public function test_a_renewed_pa_starts_the_reminders_again(): void
    {
        Notification::fake();
        $profile = $this->setPa('KB10891', '2026-10-28');
        $this->check();

        // Renewed for another year, then it runs low again.
        $profile->update(['pa_valid_until' => '2026-11-20']);
        $this->assertContains('KB10891', $this->check()['reminded']);
    }

    public function test_no_reminder_when_the_pa_is_far_off_or_the_counselor_is_not_approved(): void
    {
        Notification::fake();
        $this->setPa('KB10891', '2027-06-01');
        $this->setPa('KB11935', '2026-11-05');
        CounselorProfile::where('kb_number', 'KB11935')->update(['verification_status' => 'suspended']);

        $result = $this->check();

        $this->assertNotContains('KB10891', $result['reminded']);
        $this->assertNotContains('KB11935', $result['reminded']);
    }

    public function test_the_reminder_is_not_recorded_when_the_email_fails(): void
    {
        $profile = $this->setPa('KB10891', '2026-10-28');
        $failing = new class extends User {
            public function notify($instance)
            {
                throw new \RuntimeException('mail down');
            }
        };
        // Swap the profile's user for one whose mail fails.
        $service = app(CounselorPaExpiryService::class);
        $profile->setRelation('user', $failing);
        $reflection = new \ReflectionMethod($service, 'remind');
        $reflection->setAccessible(true);
        $result = ['suspended' => [], 'extended' => [], 'reminded' => [], 'errors' => 0];

        try {
            $reflection->invokeArgs($service, [$profile, [1, 7, 30], false, &$result]);
            $this->fail('The failure should surface so the run counts it.');
        } catch (\RuntimeException) {
            // expected: the caller catches it and counts an error
        }

        $this->assertNull($this->profile('KB10891')->pa_reminder_days);
        $this->assertCount(0, $this->logs('counselor.renewal_reminder_sent', $profile));
    }

    // ---- command, schedule, after-effects ----------------------------------------------------

    public function test_dry_run_changes_and_sends_nothing(): void
    {
        Notification::fake();
        $this->setPa('KB10891', '2026-10-20');
        $this->setPa('KB11935', '2026-11-05');
        $auditBefore = AuditLog::count();

        $result = $this->check(dryRun: true);

        $this->assertContains('KB10891', $result['suspended']);
        $this->assertContains('KB11935', $result['reminded']);
        $this->assertSame(VerificationStatus::Approved, $this->profile('KB10891')->verification_status);
        $this->assertNull($this->profile('KB11935')->pa_reminder_days);
        $this->assertSame($auditBefore, AuditLog::count());
        Notification::assertNothingSent();
    }

    public function test_the_command_reports_what_it_did_and_is_scheduled_daily_after_the_last_valid_second(): void
    {
        Notification::fake();
        $this->setPa('KB10891', '2026-10-20');

        $this->artisan('counselors:check-pa-expiry')
            ->expectsOutputToContain('suspend 1')
            ->expectsOutputToContain('KB10891')
            ->assertSuccessful();

        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'counselors:check-pa-expiry'));
        $this->assertNotNull($event);
        $this->assertSame('10 0 * * *', $event->expression);
    }

    public function test_a_counselor_suspended_by_the_system_can_be_reactivated_once_lkm_shows_a_valid_pa(): void
    {
        Notification::fake();
        $this->setPa('KB10891', '2026-10-20');
        $this->check();
        $profile = $this->profile('KB10891');

        // Still expired: the admin is refused.
        $this->as()->post(route('platform.counselors.reactivate', $profile))->assertSessionHas('error');
        $this->assertSame(VerificationStatus::Suspended, $profile->fresh()->verification_status);

        // LKM now shows a renewed PA.
        LkmDirectorySnapshot::where('kb_number', 'KB10891')->update(['pa_valid_until' => '2027-10-20']);
        $this->as()->post(route('platform.counselors.reactivate', $profile))->assertSessionHas('status');

        $this->assertSame(VerificationStatus::Approved, $profile->fresh()->verification_status);
        $this->assertSame('2027-10-20', $profile->fresh()->pa_valid_until->toDateString());
    }

    public function test_the_counselor_page_shows_the_automatic_suspension(): void
    {
        Notification::fake();
        $profile = $this->setPa('KB10891', '2026-10-20');
        $this->check();

        $this->as()->get(route('platform.counselors.show', $profile))->assertOk()
            ->assertSee('Suspended automatically: PA expired')
            ->assertSee('the system (practising certificate expired)');
    }

    public function test_it_runs_cleanly_on_the_sample_data(): void
    {
        Notification::fake();

        $result = $this->check();

        $this->assertSame(0, $result['errors']);
    }
}
