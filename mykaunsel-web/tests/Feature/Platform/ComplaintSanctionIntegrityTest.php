<?php

namespace Tests\Feature\Platform;

use App\Enums\ComplaintStatus;
use App\Enums\VerificationStatus;
use App\Facades\Audit;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\CounselorProfile;
use App\Models\User;
use App\Notifications\ClientSessionNotice;
use App\Notifications\CounselorComplaintWarning;
use App\Notifications\CounselorSuspended;
use App\Notifications\CounselorSuspendedForOrganization;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\Feature\Counselor\CounselorTestCase;

/**
 * A sanction (warning, suspension) and a decision on a complaint are all-or-nothing with their audit entry, cannot be applied
 * twice by two admins, and what other people are told never holds the complaint or who made it.
 */
class ComplaintSanctionIntegrityTest extends CounselorTestCase
{
    private const TEXT = 'The counselor shouted at me during the session and refused to refund the fee.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSamples();
    }

    private function client(): User
    {
        return User::where('email', 'klien.contoh@example.test')->firstOrFail();
    }

    /** A complaint by the sample client about the counselor with that KB number. */
    private function complaintAbout(string $kb, string $status = 'open'): Complaint
    {
        return Complaint::create([
            'reporter_user_id' => $this->client()->id,
            'counselor_user_id' => $this->profile($kb)->user_id,
            'description' => self::TEXT,
            'status' => $status,
        ]);
    }

    private function second(): User
    {
        $other = User::factory()->create(['name' => 'Second Admin']);
        $other->memberships()->create(['org_id' => $this->session['current_org_id'], 'role' => 'platform_admin', 'status' => 'active', 'joined_at' => now()]);

        return $other;
    }

    private function warn(Complaint $c, string $text = 'Please be on time and keep every session calm.')
    {
        return $this->as()->post(route('platform.complaints.warn', $c), ['reason' => $text]);
    }

    private function suspend(Complaint $c, array $extra = [], ?User $as = null)
    {
        if ($as !== null) {
            $this->flushSession(); // (the session of the first admin would otherwise sign the second one out)
        }

        return $this->as($as)->post(route('platform.complaints.suspend-counselor', $c), $extra + ['reason' => 'Repeated complaints about conduct.', 'sessions' => 'keep']);
    }

    private function text(object $notification, User $to): string
    {
        $mail = $notification->toMail($to);

        return strip_tags(implode(' ', array_merge([$mail->subject, $mail->greeting], $mail->introLines, $mail->outroLines)));
    }

    // ---- a second warning on the same complaint -------------------------------------------------------------

    public function test_a_second_warning_on_the_same_complaint_within_24_hours_is_refused_and_the_counselor_gets_one_email(): void
    {
        Notification::fake();
        $c = $this->complaintAbout('KB10234');

        $this->warn($c)->assertSessionHas('status');
        $this->warn($c, 'Another message sent a moment later.')->assertSessionHas('error', fn ($m) => str_contains($m, 'already sent') && str_contains($m, '24 hours'));

        Notification::assertSentToTimes($c->counselor, CounselorComplaintWarning::class, 1);
        $this->assertSame(1, AuditLog::where('action', 'complaint.warning_issued')->where('target_id', $c->id)->count());
    }

    public function test_after_24_hours_a_new_warning_is_allowed_and_another_complaints_warning_is_not_blocked(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-27 10:00:00', 'UTC'));
        $c = $this->complaintAbout('KB10234');
        $other = $this->complaintAbout('KB10234');

        $this->warn($c)->assertSessionHas('status');
        $this->warn($other)->assertSessionHas('status'); // a different complaint

        Carbon::setTestNow(now()->addHours(25));
        $this->warn($c, 'A second warning, a day later.')->assertSessionHas('status');

        Notification::assertSentToTimes($c->counselor, CounselorComplaintWarning::class, 3);
    }

    public function test_the_cooldown_is_configurable(): void
    {
        Notification::fake();
        config(['complaints.warning_cooldown_hours' => 1]);
        Carbon::setTestNow(Carbon::parse('2026-10-27 10:00:00', 'UTC'));
        $c = $this->complaintAbout('KB10234');

        $this->warn($c)->assertSessionHas('status');
        Carbon::setTestNow(now()->addMinutes(61));
        $this->warn($c, 'After the shorter cooldown.')->assertSessionHas('status');
    }

    // ---- suspending twice, or from a complaint another admin just closed -----------------------------------------

    public function test_two_admins_suspending_the_same_counselor_only_one_succeeds_and_nobody_is_emailed_twice(): void
    {
        Notification::fake();
        $a = $this->complaintAbout('KB10234');
        $b = $this->complaintAbout('KB10234');
        $other = $this->second();

        $this->suspend($a)->assertSessionHas('status');
        $this->suspend($b, as: $other)->assertSessionHas('error', fn ($m) => str_contains($m, 'not currently approved'));

        $profile = $this->profile('KB10234');
        $this->assertSame(VerificationStatus::Suspended, $profile->verification_status);
        $this->assertSame('complaint', $profile->suspension_source);
        $this->assertSame(1, AuditLog::where('action', 'complaint.counselor_suspended')->count());
        $this->assertSame(1, AuditLog::where('action', 'counselor.suspended')->where('target_id', $profile->id)->count());
        Notification::assertSentToTimes($a->counselor, CounselorSuspended::class, 1);
        $this->assertGreaterThan(0, $this->orgAdminCount('KB10234'));
        Notification::assertSentTimes(CounselorSuspendedForOrganization::class, $this->orgAdminCount('KB10234'));
    }

    private function orgAdminCount(string $kb): int
    {
        return $this->profile($kb)->memberships()->where('role', 'counselor')->with('organization')->get()
            ->flatMap(fn ($m) => $m->organization->memberships()->where('role', 'org_admin')->where('status', 'active')->get())->unique('user_id')->count();
    }

    public function test_suspending_from_a_complaint_another_admin_has_just_closed_changes_nothing(): void
    {
        Notification::fake();
        $c = $this->complaintAbout('KB10234');

        // The other admin closes it; this admin's page is older than that.
        $this->as()->post(route('platform.complaints.status', $c), ['to' => 'dismissed', 'from' => 'open', 'reason' => 'No evidence of any misconduct.'])->assertSessionHas('status');
        $this->suspend($c)->assertSessionHas('error', fn ($m) => str_contains($m, 'already closed'));

        $this->assertSame(VerificationStatus::Approved, $this->profile('KB10234')->verification_status);
        $this->assertSame(0, AuditLog::where('action', 'complaint.counselor_suspended')->count());
        Notification::assertNothingSent();
    }

    public function test_the_checks_and_the_locks_are_inside_the_transaction_in_a_fixed_order(): void
    {
        $c = $this->complaintAbout('KB10234');
        $inside = [];

        // Record, in order, which rows are read FOR UPDATE and whether a transaction is open at that moment.
        \DB::listen(function ($query) use (&$inside) {
            if (preg_match('/from "(complaints|counselor_profiles)"/', $query->sql, $m) && ! str_contains($query->sql, 'count(')) {
                $inside[] = [$m[1], \DB::transactionLevel() > 0];
            }
        });

        Notification::fake();
        $this->suspend($c)->assertSessionHas('status');

        $this->assertNotEmpty($inside);
        $firstComplaint = collect($inside)->search(fn ($x) => $x[0] === 'complaints' && $x[1]);
        $firstProfile = collect($inside)->search(fn ($x) => $x[0] === 'counselor_profiles' && $x[1]);
        $this->assertNotFalse($firstComplaint, 'the complaint is read inside the transaction');
        $this->assertNotFalse($firstProfile, 'the profile is read inside the transaction');
        $this->assertLessThan($firstProfile, $firstComplaint, 'complaint first, then the counselor profile');
    }

    public function test_suspending_a_counselor_without_a_profile_or_not_approved_is_refused_with_no_email(): void
    {
        Notification::fake();
        $c = $this->complaintAbout('KB10234');
        CounselorProfile::where('kb_number', 'KB10234')->update(['verification_status' => VerificationStatus::Suspended]);

        $this->suspend($c)->assertSessionHas('error', fn ($m) => str_contains($m, 'not currently approved'));

        $reporterOnly = Complaint::create(['reporter_user_id' => $this->client()->id, 'counselor_user_id' => User::factory()->create()->id, 'description' => self::TEXT, 'status' => 'open']);
        $this->suspend($reporterOnly)->assertSessionHas('error', fn ($m) => str_contains($m, 'not currently approved'));

        Notification::assertNothingSent();
    }

    // ---- all or nothing with the audit entry --------------------------------------------------------------------------------

    public function test_a_status_change_is_not_saved_when_its_audit_entry_cannot_be_written(): void
    {
        $c = $this->complaintAbout('KB10234');
        Audit::shouldReceive('log')->andThrow(new RuntimeException('audit table unavailable'));
        $this->withoutExceptionHandling();

        try {
            $this->as()->post(route('platform.complaints.status', $c), ['to' => 'resolved', 'from' => 'open', 'reason' => 'Sorted out with both sides.']);
            $this->fail('should have failed');
        } catch (RuntimeException) {
        }

        $this->assertSame(ComplaintStatus::Open, $c->fresh()->status);
    }

    public function test_a_warning_is_not_sent_when_its_audit_entry_cannot_be_written(): void
    {
        Notification::fake();
        $c = $this->complaintAbout('KB10234');
        Audit::shouldReceive('log')->andThrow(new RuntimeException('audit table unavailable'));
        $this->withoutExceptionHandling();

        try {
            $this->warn($c);
            $this->fail('should have failed');
        } catch (RuntimeException) {
        }

        Notification::assertNothingSent();
    }

    public function test_a_suspension_is_not_saved_and_nobody_is_emailed_when_an_audit_entry_cannot_be_written(): void
    {
        Notification::fake();
        $c = $this->complaintAbout('KB10234');
        $before = $this->profile('KB10234')->only(['verification_status', 'suspension_reason', 'suspended_at']);
        Audit::shouldReceive('log')->andThrow(new RuntimeException('audit table unavailable'));
        $this->withoutExceptionHandling();

        try {
            $this->suspend($c);
            $this->fail('should have failed');
        } catch (RuntimeException) {
        }

        $this->assertEquals($before, $this->profile('KB10234')->only(['verification_status', 'suspension_reason', 'suspended_at']));
        Notification::assertNothingSent();
    }

    public function test_a_suspension_is_rolled_back_when_the_complaints_own_audit_entry_fails_after_the_counselor_one(): void
    {
        Notification::fake();
        $c = $this->complaintAbout('KB10234');

        // The counselor's entry is written, then the complaint's entry fails: both must be undone.
        $calls = 0;
        Audit::shouldReceive('log')->andReturnUsing(function ($action) use (&$calls) {
            if (++$calls >= 2) {
                throw new RuntimeException('second entry failed');
            }

            return new AuditLog;
        });
        $this->withoutExceptionHandling();

        try {
            $this->suspend($c);
            $this->fail('should have failed');
        } catch (RuntimeException) {
        }

        $this->assertSame(VerificationStatus::Approved, $this->profile('KB10234')->verification_status);
        Notification::assertNothingSent();
    }

    // ---- what other people are told ----------------------------------------------------------------------------------------

    public function test_a_suspension_from_a_complaint_tells_the_counselor_and_the_organizations_admins_without_the_complaint_or_who_made_it(): void
    {
        Notification::fake();
        $c = $this->complaintAbout('KB10234');
        $reporter = $this->client();

        $this->suspend($c, ['reason' => 'Repeated complaints about conduct.'])->assertSessionHas('status');

        Notification::assertSentTo($c->counselor, CounselorSuspended::class);
        $admins = 0;

        // sentNotifications(): notifiable class => id => notification class => [each one sent].
        foreach (Notification::sentNotifications() as $byId) {
            foreach ($byId as $id => $byClass) {
                foreach ($byClass as $list) {
                    foreach ($list as $entry) {
                        $notification = $entry['notification'];
                        $text = $this->text($notification, User::findOrFail($id));

                        $this->assertStringNotContainsString(self::TEXT, $text, get_class($notification));
                        $this->assertStringNotContainsString('shouted', $text, get_class($notification));
                        $this->assertStringNotContainsString($reporter->name, $text, get_class($notification));
                        $this->assertStringNotContainsString($reporter->email, $text, get_class($notification));

                        if ($notification instanceof CounselorSuspendedForOrganization) {
                            $this->assertStringNotContainsStringIgnoringCase('complaint', $text, 'organization admins are told nothing about a complaint');
                            $this->assertStringNotContainsString('Repeated complaints', $text, 'nor the reason the platform gave');
                            $admins++;
                        }
                    }
                }
            }
        }

        $this->assertGreaterThan(0, $admins, 'the organization admins were told');
        $this->assertSame($this->orgAdminCount('KB10234'), $admins);
    }

    public function test_the_session_options_keep_or_cancel_upcoming_sessions_and_tell_clients_only_the_counselors_name_and_time(): void
    {
        Notification::fake();
        $upcoming = Booking::where('counselor_user_id', $this->profile('KB10891')->user_id)->where('status', 'confirmed')->count();
        $this->assertGreaterThan(0, $upcoming, 'the sample data has an upcoming session for this counselor');
        $c = $this->complaintAbout('KB10891');

        $this->suspend($c, ['sessions' => 'keep'])->assertSessionHas('status');
        Notification::assertNotSentTo($this->client(), ClientSessionNotice::class);
        $this->assertSame($upcoming, Booking::where('counselor_user_id', $this->profile('KB10891')->user_id)->where('status', 'confirmed')->count(), 'kept');
    }

    public function test_cancelling_upcoming_sessions_from_a_complaint_tells_each_client_without_the_reason_or_the_complaint(): void
    {
        Notification::fake();
        $c = $this->complaintAbout('KB10891');

        $this->suspend($c, ['sessions' => 'cancel_upcoming', 'reason' => 'Serious conduct concerns raised by clients.'])->assertSessionHas('status');

        Notification::assertSentTo($this->client(), ClientSessionNotice::class, function ($n) {
            $text = $this->text($n, $this->client());

            return ! str_contains($text, 'conduct concerns') && ! str_contains($text, self::TEXT) && str_contains($text, 'Tan Wei Ming');
        });
        $this->assertSame(0, Booking::where('counselor_user_id', $this->profile('KB10891')->user_id)->where('status', 'confirmed')->count());
    }

    public function test_the_warning_goes_only_to_the_counselor_and_says_nothing_of_who_complained(): void
    {
        Notification::fake();
        $c = $this->complaintAbout('KB10234');

        $this->warn($c, 'Please be on time and keep every session calm.')->assertSessionHas('status');

        Notification::assertSentTimes(CounselorComplaintWarning::class, 1);
        Notification::assertSentTo($c->counselor, CounselorComplaintWarning::class, function ($n) use ($c) {
            $text = $this->text($n, $c->counselor);

            return str_contains($text, 'be on time') && ! str_contains($text, self::TEXT) && ! str_contains($text, $this->client()->name) && ! str_contains($text, $this->client()->email);
        });
        Notification::assertNothingSentTo($this->client());
    }

    public function test_the_person_who_complained_is_never_emailed_about_any_action(): void
    {
        Notification::fake();
        $c = $this->complaintAbout('KB10234');
        $d = $this->complaintAbout('KB10567');

        $this->warn($c)->assertSessionHas('status');
        $this->as()->post(route('platform.complaints.status', $c), ['to' => 'under_review', 'from' => 'open']);
        $this->as()->post(route('platform.complaints.status', $c), ['to' => 'resolved', 'from' => 'under_review', 'reason' => 'Warned the counselor; matter closed.']);
        $this->as()->post(route('platform.complaints.status', $d), ['to' => 'dismissed', 'from' => 'open', 'reason' => 'No evidence of any misconduct.']);

        Notification::assertNothingSentTo($this->client());
    }
}
