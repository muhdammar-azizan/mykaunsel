<?php

namespace Tests\Feature\Counselor;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\VerificationStatus;
use App\Facades\Audit;
use App\Models\AuditLog;
use App\Models\CounselorKbAttempt;
use App\Models\CounselorProfile;
use App\Models\LkmDirectorySnapshot;
use App\Models\Membership;
use App\Models\Organization;
use App\Services\CounselorKbAttemptRecorder;
use App\Services\LkmVerificationService;
use App\Support\CounselorAttention;
use App\Support\LkmComparison;
use Illuminate\Support\Carbon;

class CounselorRulesTest extends CounselorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSamples();
    }

    private function umpsa(): Organization
    {
        return Organization::where('name', 'like', 'Universiti Malaysia Pahang%')->firstOrFail();
    }

    private function listableKbs(): array
    {
        return CounselorProfile::query()->listable()->pluck('kb_number')->sort()->values()->all();
    }

    // ---- who can be found and booked -------------------------------------------------------

    public function test_only_approved_counselors_with_a_valid_pa_in_an_active_organization_are_listable(): void
    {
        // Three organization counselors + the two approved independents. Not: 4 pending, 2 suspended, 1 rejected.
        $this->assertSame(['KB10234', 'KB10567', 'KB10891', 'KB11935', 'KB14245'], $this->listableKbs());
    }

    public function test_pending_rejected_and_suspended_profiles_are_never_listable(): void
    {
        foreach (['KB11023' => 'pending', 'KB12391' => 'rejected', 'KB12168' => 'suspended'] as $kb => $status) {
            $profile = $this->profile($kb);
            $this->assertSame($status, $profile->verification_status->value);
            $this->assertFalse($profile->isListable(), "{$status} must not be listed");
        }

        // Even an otherwise perfect approved counselor drops out the moment they are suspended.
        $rajesh = $this->profile('KB11935');
        $this->assertTrue($rajesh->isListable());
        $rajesh->update(['verification_status' => VerificationStatus::Suspended]);
        $this->assertFalse($rajesh->fresh()->isListable());
        $this->assertNotContains('KB11935', $this->listableKbs());
    }

    public function test_an_expired_pa_blocks_listing_but_the_last_day_still_counts(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-23 15:00:00', config('app.timezone')));
        $rajesh = $this->profile('KB11935');

        $rajesh->update(['pa_valid_until' => '2026-10-24']);
        $this->assertTrue($rajesh->fresh()->isListable(), 'tomorrow');

        $rajesh->update(['pa_valid_until' => '2026-10-23']);
        $this->assertTrue($rajesh->fresh()->isListable(), 'valid until TODAY: still listed all day');
        $this->assertContains('KB11935', $this->listableKbs());

        $rajesh->update(['pa_valid_until' => '2026-10-22']);
        $this->assertFalse($rajesh->fresh()->isListable(), 'yesterday: expired');
        $this->assertNotContains('KB11935', $this->listableKbs());

        Carbon::setTestNow(Carbon::parse('2026-10-24 00:00:01', config('app.timezone')));
        $rajesh->update(['pa_valid_until' => '2026-10-23']);
        $this->assertFalse($rajesh->fresh()->isListable(), 'one second into the next day');
    }

    public function test_an_organization_counselor_needs_an_active_organization_and_an_active_membership(): void
    {
        $ahmad = $this->profile('KB10234');
        $this->assertTrue($ahmad->isListable());

        $this->umpsa()->update(['subscription_status' => OrgStatus::Suspended]);
        $this->assertFalse($ahmad->fresh()->isListable(), 'organization suspended');
        $this->assertNotContains('KB10234', $this->listableKbs());
        $this->assertNotContains('KB10567', $this->listableKbs(), 'every counselor of that organization');
        $this->assertContains('KB11935', $this->listableKbs(), 'other contexts are unaffected');

        $this->umpsa()->update(['subscription_status' => OrgStatus::Pending]);
        $this->assertFalse($ahmad->fresh()->isListable(), 'organization not approved yet');

        $this->umpsa()->update(['subscription_status' => OrgStatus::Active]);
        $this->assertTrue($ahmad->fresh()->isListable());

        Membership::where('user_id', $ahmad->user_id)->update(['status' => MembershipStatus::Offboarded]);
        $this->assertFalse($ahmad->fresh()->isListable(), 'no longer a member');

        Membership::where('user_id', $ahmad->user_id)->delete();
        $this->assertFalse($ahmad->fresh()->isListable(), 'no membership at all');
    }

    public function test_one_working_context_is_enough_but_a_profile_level_block_stops_all_of_them(): void
    {
        $ahmad = $this->profile('KB10234');
        $klinik = Organization::where('name', 'Klinik Kaunseling Damai')->firstOrFail();
        Membership::create(['user_id' => $ahmad->user_id, 'org_id' => $klinik->id, 'role' => MembershipRole::Counselor, 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        $this->umpsa()->update(['subscription_status' => OrgStatus::Suspended]);
        $this->assertTrue($ahmad->fresh()->isListable(), 'still works through the other, active organization');

        $ahmad->update(['pa_valid_until' => now()->subDay()->toDateString()]);
        $this->assertFalse($ahmad->fresh()->isListable(), 'an expired PA blocks every context');

        $ahmad->update(['pa_valid_until' => now()->addYear()->toDateString(), 'verification_status' => VerificationStatus::Suspended]);
        $this->assertFalse($ahmad->fresh()->isListable(), 'a suspended profile blocks every context');
    }

    public function test_independents_are_covered_by_the_same_rule_through_the_platform_organization(): void
    {
        $rajesh = $this->profile('KB11935');
        $platform = Organization::where('org_type', 'platform')->firstOrFail();
        $this->assertTrue($rajesh->memberships->contains(fn ($m) => $m->org_id === $platform->id && $m->role === MembershipRole::Counselor));

        $this->assertTrue($rajesh->isListable());
        Membership::where('user_id', $rajesh->user_id)->update(['role' => MembershipRole::Student]);
        $this->assertFalse($rajesh->fresh()->isListable(), 'being a student there is not a counselor context');
    }

    public function test_the_method_and_the_scope_always_agree(): void
    {
        $this->profile('KB11935')->update(['pa_valid_until' => now()->subDay()->toDateString()]);
        $this->umpsa()->update(['subscription_status' => OrgStatus::Suspended]);

        $scope = $this->listableKbs();

        foreach (CounselorProfile::all() as $profile) {
            $this->assertSame(in_array($profile->kb_number, $scope, true), $profile->isListable(), $profile->kb_number);
        }
    }

    // ---- PA status filters --------------------------------------------------------------------

    public function test_pa_status_scopes_use_whole_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-23 20:00:00', config('app.timezone')));
        $kb = fn (string $s) => CounselorProfile::query()->paStatus($s)->whereIn('kb_number', ['KB11935'])->count();
        $rajesh = $this->profile('KB11935');

        foreach (['2026-10-22' => [0, 0, 1], '2026-10-23' => [1, 1, 0], '2026-11-22' => [1, 1, 0], '2026-11-23' => [1, 0, 0]] as $date => [$valid, $expiring, $expired]) {
            $rajesh->update(['pa_valid_until' => $date]);
            $this->assertSame([$valid, $expiring, $expired], [$kb('valid'), $kb('expiring'), $kb('expired')], "PA valid until {$date}");
        }
    }

    // ---- status moves ---------------------------------------------------------------------------

    public function test_only_the_allowed_status_moves_exist(): void
    {
        $allowed = [
            'pending' => ['approved', 'rejected'],
            'rejected' => ['pending'],
            'approved' => ['suspended'],
            'suspended' => ['approved'],
        ];

        foreach (VerificationStatus::cases() as $from) {
            foreach (VerificationStatus::cases() as $to) {
                $this->assertSame(in_array($to->value, $allowed[$from->value], true), $from->canTransitionTo($to), "{$from->value} → {$to->value}");
            }
        }
    }

    // ---- LKM comparison -------------------------------------------------------------------------

    private function compare(string $kb): LkmComparison
    {
        return app(LkmVerificationService::class)->compare($this->profile($kb)->load('user'));
    }

    public function test_lkm_comparison_outcomes(): void
    {
        $match = $this->compare('KB11023');
        $this->assertSame(LkmComparison::MATCH, $match->outcome);
        $this->assertTrue($match->isMatch());
        $this->assertFalse($match->blocksApproval());
        $this->assertFalse($match->needsAcknowledgement());
        $this->assertSame([], $match->problems);
        $this->assertSame(['Name', 'KB number', 'PA number', 'Status', 'PA valid until'], array_column($match->fields, 'label'));
        $this->assertSame(['match', 'match', 'match', 'match', 'match'], array_column($match->fields, 'state'));

        // The record's date moved after the applicant applied → warning only.
        $changed = $this->compare('KB11702');
        $this->assertSame(LkmComparison::MISMATCH, $changed->outcome);
        $this->assertTrue($changed->needsAcknowledgement());
        $this->assertFalse($changed->blocksApproval());
    }

    public function test_a_name_or_pa_number_that_does_not_match_is_a_warning_not_a_block(): void
    {
        LkmDirectorySnapshot::where('kb_number', 'KB11023')->update(['full_name' => 'Kavitha Muniandy']);
        $name = $this->compare('KB11023');
        $this->assertSame(LkmComparison::MISMATCH, $name->outcome);
        $this->assertSame('mismatch', collect($name->fields)->firstWhere('label', 'Name')['state']);
        $this->assertContains('The name does not match the LKM record.', $name->problems);
        $this->assertFalse($name->blocksApproval());

        LkmDirectorySnapshot::where('kb_number', 'KB11256')->update(['pa_number' => 'PA99999']);
        $pa = $this->compare('KB11256');
        $this->assertSame(LkmComparison::MISMATCH, $pa->outcome);
        $this->assertContains('The PA number does not match the LKM record.', $pa->problems);
    }

    public function test_a_missing_inactive_or_expired_lkm_record_blocks_approval(): void
    {
        LkmDirectorySnapshot::where('kb_number', 'KB11023')->delete();
        $missing = $this->compare('KB11023');
        $this->assertSame(LkmComparison::NOT_FOUND, $missing->outcome);
        $this->assertTrue($missing->blocksApproval());
        $this->assertNull($missing->record);
        $this->assertSame('Not found in LKM', $missing->label());

        LkmDirectorySnapshot::where('kb_number', 'KB11256')->update(['status' => 'Tidak Aktif']);
        $inactive = $this->compare('KB11256');
        $this->assertSame(LkmComparison::INACTIVE, $inactive->outcome);
        $this->assertTrue($inactive->blocksApproval());
        $this->assertStringContainsString('not active', $inactive->problems[0]);

        LkmDirectorySnapshot::where('kb_number', 'KB11489')->update(['pa_valid_until' => now()->subDay()->toDateString()]);
        $expired = $this->compare('KB11489');
        $this->assertSame(LkmComparison::EXPIRED, $expired->outcome);
        $this->assertTrue($expired->blocksApproval());

        // The last day still counts.
        LkmDirectorySnapshot::where('kb_number', 'KB11489')->update(['pa_valid_until' => now()->toDateString()]);
        $this->assertNotSame(LkmComparison::EXPIRED, $this->compare('KB11489')->outcome);
    }

    public function test_comparing_many_profiles_uses_one_lookup_and_is_keyed_by_profile(): void
    {
        $profiles = CounselorProfile::query()->withStatus(VerificationStatus::Pending)->with('user')->get();

        \DB::enableQueryLog();
        $results = app(LkmVerificationService::class)->compareMany($profiles);
        $lkmQueries = collect(\DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'lkm_directory_snapshot'))->count();
        \DB::disableQueryLog();

        $this->assertSame(1, $lkmQueries);
        $this->assertEqualsCanonicalizing($profiles->pluck('id')->all(), $results->keys()->all());
        $this->assertSame(LkmComparison::MATCH, $results[$this->profile('KB11023')->id]->outcome);
        $this->assertSame(LkmComparison::MISMATCH, $results[$this->profile('KB11702')->id]->outcome);
    }

    public function test_the_comparison_never_says_already_registered(): void
    {
        // verificationFailureReason() is for sign-ups and says that for every existing account; compare() is for review.
        $comparison = $this->compare('KB11023');

        $this->assertStringNotContainsString('already registered', implode(' ', $comparison->problems));
        $this->assertSame('This KB number is already registered on MyKaunsel.', app(LkmVerificationService::class)->verificationFailureReason('KB11023', 'PA21034', 'Kavitha a/p Muniandy'));
    }

    // ---- what the Dashboard will read ------------------------------------------------------------

    public function test_attention_counts_for_the_dashboard(): void
    {
        $this->assertSame(['pending' => 4, 'lkm_not_verified' => 0, 'duplicate_kb' => 2], CounselorAttention::counts());

        LkmDirectorySnapshot::where('kb_number', 'KB11023')->update(['full_name' => 'Someone Else']);      // name differs
        LkmDirectorySnapshot::where('kb_number', 'KB11256')->update(['status' => 'Digantung']);            // not active
        LkmDirectorySnapshot::where('kb_number', 'KB11489')->delete();                                     // gone
        LkmDirectorySnapshot::where('kb_number', 'KB11702')->update(['pa_valid_until' => now()->subDay()->toDateString()]); // expired

        $counts = CounselorAttention::counts();
        $this->assertSame(4, $counts['lkm_not_verified']);
        $this->assertEqualsCanonicalizing(['KB11023', 'KB11256', 'KB11489', 'KB11702'], CounselorAttention::lkmNotVerified()->pluck('kb_number')->all());

        // Approved and organization counselors never count as "pending".
        $this->assertNotContains('KB10234', CounselorAttention::pending()->pluck('kb_number')->all());
        $this->assertEqualsCanonicalizing(['KB11256', 'KB10891'], CounselorAttention::duplicateKb()->pluck('kb_number')->all());
    }

    // ---- duplicate KB registration attempts --------------------------------------------------------

    private function register(string $kb, string $name, array $server = [])
    {
        $this->flushSession();
        auth()->logout();

        return $this->withServerVariables($server)->post(route('counselors.signup.store'), [
            'name' => $name, 'email' => 'someone'.uniqid().'@example.test', 'kb_number' => $kb, 'pa_number' => 'PA10891',
            'password' => 'Secret#Pass99', 'password_confirmation' => 'Secret#Pass99', 'confirm_accurate' => 1, 'confirm_terms' => 1,
        ]);
    }

    public function test_registering_a_kb_number_that_already_has_an_account_is_recorded_on_that_account(): void
    {
        $tan = $this->profile('KB10891');
        $before = $tan->kbAttempts()->count();

        $response = $this->register('KB10891', 'Not Tan At All', ['REMOTE_ADDR' => '203.0.113.99']);

        // The person trying sees the usual message — nothing about who owns the number.
        $response->assertSessionHasErrors(['lkm' => 'This KB number is already registered on MyKaunsel.']);
        $response->assertRedirect();
        foreach (['Tan Wei Ming', 'tan.weiming@gmail.com', 'KB10891 belongs'] as $leak) {
            $this->assertStringNotContainsString($leak, json_encode(session('errors')->getBag('default')->all()));
        }

        $this->assertSame($before + 1, $tan->kbAttempts()->count());
        $attempt = $tan->kbAttempts()->latest('id')->first();
        $this->assertSame(['KB10891', 'Not Tan At All', '203.0.113.99'], [$attempt->kb_number, $attempt->submitted_name, $attempt->ip]);

        $log = AuditLog::where('action', 'counselor.kb_duplicate_flagged')->latest('id')->firstOrFail();
        $this->assertSame(['counselor', $tan->id, 'Tan Wei Ming', 'guest'], [$log->target_type, $log->target_id, $log->target_name, $log->actor_type]);
        $this->assertSame(['attempt_id' => $attempt->id], $log->metadata, 'a reference only — the typed name stays out of the audit log');
        $this->assertStringNotContainsString('Not Tan At All', json_encode($log->toArray()));
        $this->assertSame('203.0.113.99', $log->ip);
        $this->assertNull($log->actor_user_id);

        // No second account was created.
        $this->assertSame(1, CounselorProfile::where('kb_number', 'KB10891')->count());
    }

    public function test_one_record_per_kb_and_ip_per_hour(): void
    {
        $tan = $this->profile('KB10891');
        $count = fn () => $tan->kbAttempts()->count();
        $start = $count();

        $this->register('KB10891', 'First Try', ['REMOTE_ADDR' => '203.0.113.50']);
        $this->register('KB10891', 'Second Try', ['REMOTE_ADDR' => '203.0.113.50']);
        $this->register('KB10891', 'Third Try', ['REMOTE_ADDR' => '203.0.113.50']);
        $this->assertSame($start + 1, $count(), 'same KB + same IP within the hour: one record');
        $this->assertSame(1, AuditLog::where('action', 'counselor.kb_duplicate_flagged')->where('metadata->attempt_id', $tan->kbAttempts()->latest('id')->value('id'))->count());

        $this->register('KB10891', 'Other Address', ['REMOTE_ADDR' => '203.0.113.51']);
        $this->assertSame($start + 2, $count(), 'a different IP is a new record');

        $this->travel(61)->minutes();
        $this->register('KB10891', 'Much Later', ['REMOTE_ADDR' => '203.0.113.50']);
        $this->assertSame($start + 3, $count(), 'an hour later the same IP is recorded again');
    }

    public function test_an_unknown_or_unregistered_kb_number_records_nothing(): void
    {
        $before = CounselorKbAttempt::count();

        $this->register('KB99999', 'Nobody');     // not in the LKM directory
        $this->register('KB12624', 'Chong Yee Sheng'); // in LKM, but nobody has registered it

        $this->assertSame($before, CounselorKbAttempt::count());
        $this->assertSame(0, AuditLog::where('action', 'counselor.kb_duplicate_flagged')->where('created_at', '>', now()->subMinute())->count());
    }

    public function test_the_recorder_ignores_unknown_numbers_and_trims_long_names(): void
    {
        $recorder = app(CounselorKbAttemptRecorder::class);

        $this->assertNull($recorder->record('KB00000', 'x', '127.0.0.1'));

        $attempt = $recorder->record('KB10567', str_repeat('n', 400), '203.0.113.7');
        $this->assertSame(255, strlen($attempt->submitted_name));
    }

    public function test_the_samples_cover_every_case_in_the_brief(): void
    {
        $this->assertSame(VerificationStatus::Pending, $this->profile('KB11023')->verification_status);                           // pending, full match
        $this->assertTrue($this->profile('KB11256')->documents->contains->needsReplacement());                                    // one flagged document
        $this->assertSame(2, $this->profile('KB11489')->submission_count);                                                          // resubmitted after rejection
        $this->assertSame(LkmComparison::MISMATCH, $this->compare('KB11702')->outcome);                                            // LKM changed after submission
        $this->assertTrue($this->profile('KB11935')->pa_valid_until->lt(now()->addDays(30)));                                      // PA expiring soon
        $this->assertSame('system', $this->profile('KB13090')->suspension_source);                                                 // suspended by the system
        $this->assertSame('complaint', $this->profile('KB12168')->suspension_source);                                              // suspended by an admin (after a complaint)
        $this->assertSame('org_assigned', $this->profile('KB10234')->verification_type->value);                                    // an organization counselor
        $this->assertGreaterThanOrEqual(3, $this->profile('KB10891')->kbAttempts()->count());                                      // duplicate-KB attempts
        $this->assertSame('counselor.pa_expired_suspended', AuditLog::where('actor_type', 'system')->where('target_type', 'counselor')->value('action'));

        $before = CounselorProfile::count();
        $this->seed(\Database\Seeders\CounselorReviewSeeder::class);
        $this->assertSame($before, CounselorProfile::count(), 'running the sample seeder twice adds nothing');
    }
}
