<?php

namespace Tests\Feature\Counselor;

use App\Enums\BookingRequestStatus;
use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\VerificationStatus;
use App\Facades\Audit;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use App\Models\LkmDirectorySnapshot;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ClientSessionNotice;
use App\Notifications\CounselorPaRenewalReminder;
use App\Notifications\CounselorReactivated;
use App\Notifications\CounselorSuspended;
use App\Notifications\CounselorSuspendedForOrganization;
use App\Services\CounselorStatusService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Symfony\Component\Process\Process;

class CounselorStatusTest extends CounselorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSamples();
    }

    private function suspend(string $kb, array $data = ['reason' => 'Aduan berulang tentang kelakuan tidak profesional.'])
    {
        return $this->as()->post(route('platform.counselors.suspend', $this->profile($kb)), $data);
    }

    private function reactivate(string $kb)
    {
        return $this->as()->post(route('platform.counselors.reactivate', $this->profile($kb)));
    }

    private function bookings(string $kb)
    {
        return Booking::where('counselor_user_id', $this->profile($kb)->user_id)->orderBy('id')->get();
    }

    private function client(): User
    {
        return User::where('email', 'klien.contoh@example.test')->firstOrFail();
    }

    private function request(string $kb): BookingRequest
    {
        return BookingRequest::create([
            'user_id' => $this->client()->id, 'counselor_user_id' => $this->profile($kb)->user_id,
            'requested_date' => now()->addDays(5)->toDateString(), 'requested_time' => '10:00:00',
            'status' => BookingRequestStatus::Pending, 'expires_at' => now()->addHours(48),
        ]);
    }

    private function secondAdmin(): User
    {
        $user = User::factory()->create(['name' => 'Admin Dua']);
        Membership::create(['user_id' => $user->id, 'org_id' => $this->session['current_org_id'], 'role' => MembershipRole::PlatformAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        return $user;
    }

    private function log(string $action, CounselorProfile $profile)
    {
        return AuditLog::where('action', $action)->where('target_type', 'counselor')->where('target_id', $profile->id)->latest('id')->first();
    }

    // ---- access ------------------------------------------------------------------------------

    public function test_only_platform_admins_can_use_these_routes(): void
    {
        $profile = $this->profile('KB10891');
        $orgAdmin = User::where('email', 'afif@umpsa.edu.my')->firstOrFail();
        $umpsa = Organization::where('name', 'like', 'Universiti Malaysia Pahang%')->firstOrFail();

        foreach ([
            fn ($t) => $t->post(route('platform.counselors.suspend', $profile), ['reason' => 'Cuba gantung dari luar.']),
            fn ($t) => $t->post(route('platform.counselors.reactivate', $this->profile('KB13090'))),
            fn ($t) => $t->post(route('platform.counselors.renewal-reminder', $this->profile('KB11935'))),
            fn ($t) => $t->post(route('platform.counselors.dev.lkm-date', $profile), ['date' => '2030-01-01']),
        ] as $call) {
            $this->flushSession();
            $call($this->actingAs($orgAdmin)->withSession(['current_org_id' => $umpsa->id, 'current_role' => 'org_admin']))->assertForbidden();
        }

        $this->assertSame(VerificationStatus::Approved, $profile->fresh()->verification_status);
    }

    // ---- suspend: keep sessions --------------------------------------------------------------

    public function test_suspending_removes_the_counselor_from_search_records_who_why_and_emails_them(): void
    {
        Notification::fake();
        $profile = $this->profile('KB10891');
        $this->assertTrue($profile->isListable());

        $this->suspend('KB10891')->assertRedirect(route('platform.counselors.show', $profile))->assertSessionHas('status');

        $profile->refresh();
        $this->assertSame(VerificationStatus::Suspended, $profile->verification_status);
        $this->assertSame('Aduan berulang tentang kelakuan tidak profesional.', $profile->suspension_reason);
        $this->assertSame([$this->admin->id, 'platform'], [$profile->suspended_by, $profile->suspension_source]);
        $this->assertNotNull($profile->suspended_at);
        $this->assertFalse($profile->isListable());
        $this->assertNotContains('KB10891', CounselorProfile::query()->listable()->pluck('kb_number')->all());

        $log = $this->log('counselor.suspended', $profile);
        $this->assertSame($this->admin->id, $log->actor_user_id);
        $this->assertSame('Tan Wei Ming', $log->target_name);
        $this->assertSame(['status' => ['old' => 'approved', 'new' => 'suspended']], $log->metadata['changes']);
        $this->assertSame(['platform', 'keep', []], [$log->metadata['source'], $log->metadata['sessions'], $log->metadata['cancelled_booking_ids']]);

        Notification::assertSentTo($profile->user, CounselorSuspended::class, function ($n) use ($profile) {
            $text = implode(' ', $n->toMail($profile->user)->introLines);

            return str_contains($text, 'Aduan berulang') && str_contains($text, 'have not been changed') && str_contains($text, 'cannot open new slots');
        });
    }

    public function test_keeping_sessions_is_the_default_and_leaves_booked_sessions_and_slots_alone(): void
    {
        Notification::fake();
        $before = $this->bookings('KB10891')->pluck('status', 'id')->map->value->all();
        $slots = CalendarEntry::where('counselor_user_id', $this->profile('KB10891')->user_id)->pluck('is_available', 'id')->all();

        $this->suspend('KB10891'); // no "sessions" field at all

        $this->assertSame($before, $this->bookings('KB10891')->pluck('status', 'id')->map->value->all(), 'every booking untouched');
        $this->assertSame($slots, CalendarEntry::where('counselor_user_id', $this->profile('KB10891')->user_id)->pluck('is_available', 'id')->all(), 'calendar slots untouched');
        Notification::assertNotSentTo($this->client(), ClientSessionNotice::class);

        // Explicit "keep" is the same.
        $this->suspend('KB10234', ['reason' => 'Siasatan dalaman sedang berjalan.', 'sessions' => 'keep']);
        $this->assertSame(VerificationStatus::Suspended, $this->profile('KB10234')->verification_status);
    }

    public function test_pending_session_requests_are_closed_and_the_requesters_told_even_when_sessions_are_kept(): void
    {
        Notification::fake();
        $pending = $this->request('KB10891');
        $answered = $this->request('KB10891');
        $answered->update(['status' => BookingRequestStatus::Approved]);

        $this->suspend('KB10891');

        $this->assertSame(BookingRequestStatus::Expired, $pending->fresh()->status);
        $this->assertSame(BookingRequestStatus::Approved, $answered->fresh()->status, 'already answered requests are not touched');
        $this->assertSame([$pending->id], $this->log('counselor.suspended', $this->profile('KB10891'))->metadata['expired_request_ids']);

        Notification::assertSentTo($this->client(), ClientSessionNotice::class, function ($n) {
            $text = implode(' ', $n->toMail($this->client())->introLines);

            return str_contains($text, 'session request') && str_contains($text, 'Tan Wei Ming') && str_contains($text, now()->addDays(5)->format('j M Y').', 10:00 AM');
        });
        Notification::assertSentTimes(ClientSessionNotice::class, 1);
    }

    // ---- suspend: cancel upcoming ------------------------------------------------------------

    public function test_cancelling_upcoming_sessions_cancels_only_confirmed_future_ones_and_tells_each_client(): void
    {
        Notification::fake();
        $this->suspend('KB10891', ['reason' => 'Perlu siasatan menyeluruh segera.', 'sessions' => 'cancel_upcoming'])->assertSessionHas('status');

        $bookings = $this->bookings('KB10891');
        $this->assertSame(['completed', 'completed', 'completed', 'cancelled', 'cancelled', 'cancelled'], $bookings->map(fn ($b) => $b->status->value)->all(), 'the 2 upcoming are cancelled; past and already-cancelled stay as they were');

        $log = $this->log('counselor.suspended', $this->profile('KB10891'));
        $this->assertSame('cancel_upcoming', $log->metadata['sessions']);
        $this->assertCount(2, $log->metadata['cancelled_booking_ids']);
        $this->assertEqualsCanonicalizing($bookings->slice(4)->pluck('id')->all(), $log->metadata['cancelled_booking_ids']);

        Notification::assertSentTimes(ClientSessionNotice::class, 2);
        Notification::assertSentTo($this->client(), ClientSessionNotice::class, function ($n) {
            $mail = $n->toMail($this->client());
            $text = implode(' ', $mail->introLines);
            $in2days = now()->addDays(2)->format('j M Y').', 2:00 PM';

            return str_contains($mail->subject, 'cancelled')
                && str_contains($text, 'Tan Wei Ming')
                && (str_contains($text, $in2days) || str_contains($text, now()->addDays(9)->format('j M Y').', 2:00 PM'))
                && ! str_contains($text, 'Perlu siasatan')   // the reason is never given to clients
                && ! str_contains($text, 'suspended');
        });
    }

    public function test_the_client_email_is_queued_after_commit_and_carries_only_name_and_time(): void
    {
        $notice = new ClientSessionNotice('Tan Wei Ming', '25 Oct 2026, 2:00 PM');

        $this->assertInstanceOf(ShouldQueue::class, $notice);
        $this->assertTrue($notice->afterCommit);
        $text = implode(' ', $notice->toMail($this->client())->introLines);
        $this->assertStringContainsString('Tan Wei Ming', $text);
        $this->assertStringContainsString('25 Oct 2026, 2:00 PM', $text);
        $this->assertStringNotContainsString('reason', strtolower($text));
    }

    public function test_cancelling_is_safe_to_repeat_only_confirmed_sessions_are_ever_changed(): void
    {
        Notification::fake();
        $service = app(CounselorStatusService::class);
        $profile = $this->profile('KB10891');

        $service->suspend($profile, $this->admin, 'Gantung pertama dengan sebab jelas.', cancelUpcoming: true);
        $firstIds = $this->log('counselor.suspended', $profile)->metadata['cancelled_booking_ids'];
        $this->assertCount(2, $firstIds);

        // A repeat (e.g. a double click) is refused as "no longer approved" and changes nothing more.
        $this->suspend('KB10891', ['reason' => 'Gantung kali kedua dengan sebab.', 'sessions' => 'cancel_upcoming'])->assertSessionHas('error');
        $this->assertSame(1, AuditLog::where('action', 'counselor.suspended')->where('target_id', $profile->id)->count());
        Notification::assertSentTimes(ClientSessionNotice::class, 2);

        // Reactivated, then suspended again: nothing left to cancel, so nothing is cancelled or sent twice.
        $this->reactivate('KB10891')->assertSessionHas('status');
        $service->suspend($this->profile('KB10891'), $this->admin, 'Gantung semula dengan sebab lain.', cancelUpcoming: true);
        $this->assertSame([], $this->log('counselor.suspended', $profile)->metadata['cancelled_booking_ids']);
        Notification::assertSentTimes(ClientSessionNotice::class, 2);
    }

    public function test_sessions_on_the_page_and_in_the_log_never_name_a_client(): void
    {
        Notification::fake();
        $this->suspend('KB10891', ['reason' => 'Perlu siasatan menyeluruh segera.', 'sessions' => 'cancel_upcoming']);

        $page = $this->as()->get(route('platform.counselors.show', $this->profile('KB10891')))->assertOk();
        foreach (['Klien Contoh', 'klien.contoh@example.test'] as $leak) {
            $page->assertDontSee($leak);
            $this->assertStringNotContainsString($leak, json_encode(AuditLog::all()->toArray()), 'not in the audit log either');
        }
        $this->as()->get(route('platform.audit-log', ['category' => 'counselors']))->assertDontSee('Klien Contoh');
    }

    // ---- who else is told ----------------------------------------------------------------------

    public function test_suspending_an_organization_counselor_also_tells_the_organizations_admins_but_not_the_reason(): void
    {
        Notification::fake();
        $orgAdmin = User::where('email', 'afif@umpsa.edu.my')->firstOrFail();

        $this->suspend('KB10234', ['reason' => 'Rahsia siasatan platform sahaja.', 'sessions' => 'cancel_upcoming'])->assertSessionHas('status');

        Notification::assertSentTo($orgAdmin, CounselorSuspendedForOrganization::class, function ($n) use ($orgAdmin) {
            $mail = $n->toMail($orgAdmin);
            $text = $mail->subject.' '.implode(' ', $mail->introLines);

            return str_contains($text, 'Ahmad Zulkarnain')
                && str_contains($text, 'Universiti Malaysia Pahang')
                && str_contains($text, 'cancelled')
                && ! str_contains($text, 'Rahsia siasatan');
        });
        Notification::assertSentTo($this->profile('KB10234')->user, CounselorSuspended::class);
        Notification::assertSentTimes(CounselorSuspendedForOrganization::class, 1);
    }

    public function test_an_independent_counselor_has_no_organization_admin_to_tell(): void
    {
        Notification::fake();

        $this->suspend('KB10891');

        Notification::assertSentTimes(CounselorSuspendedForOrganization::class, 0);
        Notification::assertSentTimes(CounselorSuspended::class, 1);
    }

    // ---- validation, races, all-or-nothing ------------------------------------------------------

    public function test_the_reason_is_required_and_the_session_choice_is_checked(): void
    {
        Notification::fake();
        $profile = $this->profile('KB10891');

        foreach (['', '   ', 'short', str_repeat('a', 1001)] as $bad) {
            $this->suspend('KB10891', ['reason' => $bad])->assertSessionHasErrors('reason', null, 'suspend');
        }
        $this->suspend('KB10891', ['reason' => 'Sebab yang cukup panjang.', 'sessions' => 'delete_everything'])->assertSessionHasErrors('sessions', null, 'suspend');

        $this->assertSame(VerificationStatus::Approved, $profile->fresh()->verification_status);
        $this->assertSame(0, AuditLog::where('action', 'counselor.suspended')->where('target_id', $profile->id)->count());
        Notification::assertNothingSent();
    }

    public function test_only_approved_counselors_can_be_suspended_and_two_admins_cannot_both_do_it(): void
    {
        Notification::fake();
        foreach (['KB11023' => 'pending', 'KB12391' => 'rejected', 'KB13090' => 'suspended'] as $kb => $state) {
            $this->suspend($kb)->assertSessionHas('error');
            $this->assertStringContainsString("already {$state}", session('error'));
        }

        $other = $this->secondAdmin();
        $this->flushSession(); // a different person signs in
        $this->as($other)->post(route('platform.counselors.suspend', $this->profile('KB10891')), ['reason' => 'Admin dua gantung dahulu.'])->assertSessionHas('status');
        $this->flushSession();
        $this->suspend('KB10891', ['reason' => 'Admin satu lambat sedikit.'])->assertSessionHas('error');

        $this->assertStringContainsString('Admin Dua', session('error'));
        $this->assertSame('Admin dua gantung dahulu.', $this->profile('KB10891')->suspension_reason);
        $this->assertSame(1, AuditLog::where('action', 'counselor.suspended')->where('target_id', $this->profile('KB10891')->id)->count());
    }

    public function test_suspension_is_rolled_back_with_everything_when_its_audit_entry_cannot_be_written(): void
    {
        Notification::fake();
        $profile = $this->profile('KB10891');
        $request = $this->request('KB10891');
        $before = $this->bookings('KB10891')->pluck('status', 'id')->map->value->all();

        Audit::shouldReceive('log')->andThrow(new RuntimeException('audit table unavailable'));

        try {
            app(CounselorStatusService::class)->suspend($profile, $this->admin, 'Sebab yang cukup panjang.', cancelUpcoming: true);
            $this->fail('suspend() should have failed');
        } catch (RuntimeException) {
        }

        $profile->refresh();
        $this->assertSame(VerificationStatus::Approved, $profile->verification_status);
        $this->assertNull($profile->suspension_reason);
        $this->assertSame($before, $this->bookings('KB10891')->pluck('status', 'id')->map->value->all(), 'cancelled sessions came back');
        $this->assertSame(BookingRequestStatus::Pending, $request->fresh()->status, 'closed requests came back');
        Notification::assertNothingSent();
    }

    // ---- reactivate ----------------------------------------------------------------------------

    public function test_reactivating_lists_the_counselor_again_clears_the_suspension_and_emails_them(): void
    {
        Notification::fake();
        $this->suspend('KB10891');
        $profile = $this->profile('KB10891');
        $this->assertFalse($profile->isListable());

        $this->reactivate('KB10891')->assertSessionHas('status');

        $profile->refresh();
        $this->assertSame(VerificationStatus::Approved, $profile->verification_status);
        $this->assertTrue($profile->isListable());
        $this->assertSame([null, null, null, null], [$profile->suspension_reason, $profile->suspended_at, $profile->suspended_by, $profile->suspension_source]);

        $log = $this->log('counselor.reactivated', $profile);
        $this->assertSame(['status' => ['old' => 'suspended', 'new' => 'approved']], $log->metadata['changes']);
        $this->assertSame('platform', $log->metadata['previous_suspension_source']);
        Notification::assertSentTo($profile->user, CounselorReactivated::class);
        $this->assertSame('Aduan berulang tentang kelakuan tidak profesional.', $this->log('counselor.suspended', $profile)->metadata['reason'], 'the old reason stays in the audit log');
    }

    public function test_reactivation_is_blocked_while_the_pa_is_expired_and_nothing_changes(): void
    {
        Notification::fake();
        $profile = $this->profile('KB13090'); // suspended by the system, PA expired 30 Nov 2025

        $this->reactivate('KB13090')->assertSessionHas('error');

        $this->assertStringContainsString('Cannot reactivate yet', session('error'));
        $this->assertStringContainsString('expired', session('error'));
        $profile->refresh();
        $this->assertSame(VerificationStatus::Suspended, $profile->verification_status);
        $this->assertSame('system', $profile->suspension_source);
        $this->assertNull($this->log('counselor.reactivated', $profile));
        Notification::assertNothingSent();
    }

    public function test_once_the_lkm_record_shows_a_new_date_reactivation_refreshes_it_and_works(): void
    {
        Notification::fake();
        $profile = $this->profile('KB13090');
        $new = now()->addYear()->toDateString();

        // The dev tool is a shortcut for the LKM review request.
        $this->as()->post(route('platform.counselors.dev.lkm-date', $profile), ['date' => $new])->assertSessionHas('status');
        $this->assertSame($new, LkmDirectorySnapshot::where('kb_number', 'KB13090')->first()->pa_valid_until->toDateString());

        $this->reactivate('KB13090')->assertSessionHas('status');

        $profile->refresh();
        $this->assertSame(VerificationStatus::Approved, $profile->verification_status);
        $this->assertSame($new, $profile->pa_valid_until->toDateString());
        $this->assertSame(['old' => '2025-11-30', 'new' => $new], $this->log('counselor.reactivated', $profile)->metadata['changes']['pa_valid_until']);
        $this->assertTrue($profile->isListable());
        Notification::assertSentTo($profile->user, CounselorReactivated::class);
    }

    public function test_the_last_day_of_the_pa_is_enough_to_reactivate(): void
    {
        Notification::fake();
        LkmDirectorySnapshot::where('kb_number', 'KB13090')->update(['pa_valid_until' => now()->toDateString()]);

        $this->reactivate('KB13090')->assertSessionHas('status');

        $this->assertSame(VerificationStatus::Approved, $this->profile('KB13090')->verification_status);
    }

    public function test_an_inactive_or_missing_lkm_record_blocks_reactivation_and_is_never_written_over(): void
    {
        Notification::fake();
        $profile = $this->profile('KB12168'); // suspended after a complaint, PA still valid
        $date = $profile->pa_valid_until->toDateString();

        LkmDirectorySnapshot::where('kb_number', 'KB12168')->update(['status' => 'Digantung', 'pa_valid_until' => now()->addYears(9)->toDateString()]);
        $this->reactivate('KB12168')->assertSessionHas('error');
        $this->assertStringContainsString('not active', session('error'));
        $this->assertSame($date, $profile->fresh()->pa_valid_until->toDateString(), 'nothing overwritten');

        LkmDirectorySnapshot::where('kb_number', 'KB12168')->delete();
        $this->reactivate('KB12168')->assertSessionHas('error');
        $this->assertStringContainsString('No record for this KB number', session('error'));

        $this->assertSame(VerificationStatus::Suspended, $profile->fresh()->verification_status);
        Notification::assertNothingSent();
    }

    public function test_a_blocked_reactivation_also_rolls_back_the_date_refresh(): void
    {
        $profile = $this->profile('KB12168');
        $date = $profile->pa_valid_until->toDateString();
        LkmDirectorySnapshot::where('kb_number', 'KB12168')->update(['pa_valid_until' => now()->subDay()->toDateString()]); // active but expired

        $this->reactivate('KB12168')->assertSessionHas('error');

        $this->assertSame($date, $profile->fresh()->pa_valid_until->toDateString());
        $this->assertSame(VerificationStatus::Suspended, $profile->fresh()->verification_status);
    }

    public function test_only_suspended_counselors_can_be_reactivated(): void
    {
        foreach (['KB10891' => 'approved', 'KB11023' => 'pending', 'KB12391' => 'rejected'] as $kb => $state) {
            $this->reactivate($kb)->assertSessionHas('error');
            $this->assertStringContainsString("already {$state}", session('error'));
        }
    }

    public function test_reactivation_is_rolled_back_when_its_audit_entry_cannot_be_written(): void
    {
        Notification::fake();
        $profile = $this->profile('KB12168');
        Audit::shouldReceive('log')->andThrow(new RuntimeException('audit table unavailable'));

        try {
            app(CounselorStatusService::class)->reactivate($profile, $this->admin);
            $this->fail('reactivate() should have failed');
        } catch (RuntimeException) {
        }

        $this->assertSame(VerificationStatus::Suspended, $profile->fresh()->verification_status);
        $this->assertNotNull($profile->fresh()->suspension_reason);
        Notification::assertNothingSent();
    }

    // ---- development-only tool -----------------------------------------------------------------

    public function test_the_dev_tool_validates_and_needs_an_lkm_record(): void
    {
        $profile = $this->profile('KB13090');

        $this->as()->post(route('platform.counselors.dev.lkm-date', $profile), ['date' => 'tomorrow'])->assertSessionHasErrors('date');
        $this->as()->post(route('platform.counselors.dev.lkm-date', $profile), [])->assertSessionHasErrors('date');

        LkmDirectorySnapshot::where('kb_number', 'KB13090')->delete();
        $this->as()->post(route('platform.counselors.dev.lkm-date', $profile), ['date' => '2030-01-01'])->assertSessionHas('error');
    }

    public function test_the_dev_tool_refuses_outside_local_and_testing_even_if_the_route_were_there(): void
    {
        $profile = $this->profile('KB13090');
        $this->app['env'] = 'production';

        $this->as()->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('platform.counselors.dev.lkm-date', $profile), ['date' => '2030-01-01'])->assertNotFound();
        $this->as()->get(route('platform.counselors.show', $profile))->assertDontSee('Dev only');
    }

    public function test_the_dev_route_is_not_registered_at_all_in_production(): void
    {
        $run = function (string $env) {
            $process = new Process([PHP_BINARY, 'artisan', 'route:list', '--json', '--path=counselors'], base_path(), array_merge($_SERVER, $_ENV, ['APP_ENV' => $env]));
            $process->run();

            return $process->getOutput();
        };

        $this->assertStringContainsString('counselors.dev.lkm-date', $run('local'));
        $this->assertStringNotContainsString('counselors.dev.lkm-date', $run('production'));
        $this->assertStringContainsString('counselors.suspend', $run('production'), 'the real routes are still there');
    }

    // ---- renewal reminder -----------------------------------------------------------------------

    public function test_a_renewal_reminder_is_emailed_audited_and_rate_limited(): void
    {
        Notification::fake();
        $profile = $this->profile('KB11935'); // PA runs out in 12 days

        $this->as()->post(route('platform.counselors.renewal-reminder', $profile))->assertSessionHas('status');

        Notification::assertSentTo($profile->user, CounselorPaRenewalReminder::class, function ($n) use ($profile) {
            $mail = $n->toMail($profile->user);

            return str_contains($mail->subject, 'about to expire') && str_contains(implode(' ', $mail->introLines), $profile->pa_valid_until->format('j M Y'));
        });
        $log = $this->log('counselor.renewal_reminder_sent', $profile);
        $this->assertSame($this->admin->id, $log->actor_user_id);
        $this->assertStringContainsString('PA valid until', $log->metadata['summary']);

        $this->as()->post(route('platform.counselors.renewal-reminder', $profile))->assertSessionHas('error');
        $this->assertStringContainsString('already sent recently', session('error'));
        Notification::assertSentTimes(CounselorPaRenewalReminder::class, 1);

        $this->travel(25)->hours();
        $this->as()->post(route('platform.counselors.renewal-reminder', $profile))->assertSessionHas('status');
        Notification::assertSentTimes(CounselorPaRenewalReminder::class, 2);
    }

    public function test_the_reminder_for_an_expired_pa_says_so_and_works_for_suspended_counselors(): void
    {
        Notification::fake();
        $profile = $this->profile('KB13090');

        $this->as()->post(route('platform.counselors.renewal-reminder', $profile))->assertSessionHas('status');

        Notification::assertSentTo($profile->user, CounselorPaRenewalReminder::class, fn ($n) => str_contains($n->toMail($profile->user)->subject, 'has expired'));
    }

    public function test_no_reminder_when_the_pa_is_far_off_or_the_counselor_is_not_approved_or_suspended(): void
    {
        Notification::fake();

        $this->as()->post(route('platform.counselors.renewal-reminder', $this->profile('KB10891')))->assertSessionHas('error');
        $this->assertStringContainsString('not close to expiring', session('error'));

        $this->as()->post(route('platform.counselors.renewal-reminder', $this->profile('KB11023')))->assertSessionHas('error');
        $this->assertStringContainsString('Only approved or suspended', session('error'));

        Notification::assertNothingSent();
        $this->assertSame(0, AuditLog::where('action', 'counselor.renewal_reminder_sent')->count());
    }

    public function test_a_failed_reminder_email_is_not_recorded_as_sent(): void
    {
        Notification::shouldReceive('send')->andThrow(new RuntimeException('mail server down'));

        $this->as()->post(route('platform.counselors.renewal-reminder', $this->profile('KB11935')))->assertSessionHas('error');

        $this->assertStringContainsString('could not be sent', session('error'));
        $this->assertSame(0, AuditLog::where('action', 'counselor.renewal_reminder_sent')->count());
    }

    // ---- the page --------------------------------------------------------------------------------

    public function test_the_manage_panel_follows_the_status(): void
    {
        $show = fn (string $kb) => $this->as()->get(route('platform.counselors.show', $this->profile($kb)))->assertOk();

        $show('KB10891')->assertSee('Manage counselor')->assertSee('Suspend counselor')->assertDontSee('Reactivate counselor')
            ->assertSee('Keep existing sessions')->assertSee('Cancel upcoming sessions and notify clients')
            ->assertSee('2 upcoming')->assertDontSee('Send renewal reminder');

        $show('KB10234')->assertSee('Suspend counselor')->assertSee('the admins of their organization');
        $show('KB10891')->assertDontSee('the admins of their organization');

        $show('KB11935')->assertSee('Send renewal reminder');

        $show('KB12168')->assertSee('Reactivate counselor')->assertDontSee('Suspend counselor')->assertDontSee('Send renewal reminder');
        $show('KB13090')->assertSee('Reactivate counselor')->assertSee('Send renewal reminder')
            ->assertSee('Reactivation needs a valid PA on record')->assertSee('LKM Directory → Review Requests')
            ->assertSee('Dev only')->assertSee('dev/lkm-date', false);

        foreach (['KB11023', 'KB12391'] as $kb) {
            $show($kb)->assertDontSee('Manage counselor');
        }
    }

    public function test_the_page_counts_requests_that_would_be_closed(): void
    {
        $this->request('KB10891');
        $this->request('KB10891');

        $this->as()->get(route('platform.counselors.show', $this->profile('KB10891')))->assertSee('(2) are closed');
    }

    public function test_the_activity_shows_suspension_reactivation_and_the_system_suspension(): void
    {
        Notification::fake();
        $this->suspend('KB10891');
        $this->reactivate('KB10891');

        $this->as()->get(route('platform.counselors.show', $this->profile('KB10891')))->assertSee('Suspended')->assertSee('Reactivated');
        $this->as()->get(route('platform.counselors.show', $this->profile('KB13090')))->assertSee('Suspended automatically: PA expired')->assertSee('System');
    }
}
