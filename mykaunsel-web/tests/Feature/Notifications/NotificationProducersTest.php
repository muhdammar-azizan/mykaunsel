<?php

namespace Tests\Feature\Notifications;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\VerificationStatus;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserNotification;
use App\Notifications\NewCounselorApplication;
use App\Notifications\NewOrganizationApplication;
use App\Services\AdminInvitationService;
use App\Services\CounselorKbAttemptRecorder;
use App\Services\CounselorStatusService;
use App\Services\LkmReviewService;
use App\Services\PlatformAdminService;
use App\Services\PlatformAdminNotifier;
use App\Services\PlatformSettings;
use Database\Seeders\CounselorReviewSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Counselor\CounselorTestCase;
use Tests\Feature\Platform\AdminInvitationHelpers;

/**
 * The events that produce a notification, on the real sample data: each tells every ACTIVE Platform Admin exactly once (not the person who
 * did it, not a removed or suspended admin), a retry or a re-run adds nothing, a failure never blocks the action, and the email (which
 * follows the Account preference) and the in-app item come from the same place.
 */
class NotificationProducersTest extends CounselorTestCase
{
    use AdminInvitationHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('counselor_documents');
        Storage::fake('lkm_review_evidence');
        $this->seedSamples();
    }

    private function makeAdmin(string $name, ?array $prefs = null, MembershipStatus $status = MembershipStatus::Active): User
    {
        $user = User::factory()->create(['name' => $name, 'notification_preferences' => $prefs]);
        Membership::create(['user_id' => $user->id, 'org_id' => $this->session['current_org_id'], 'role' => MembershipRole::PlatformAdmin, 'status' => $status, 'joined_at' => now()]);

        return $user;
    }

    private function of(string $type)
    {
        return UserNotification::query()->where('type', $type);
    }

    /** @return list<int> the admins that have this type, sorted */
    private function recipients(string $type): array
    {
        return $this->of($type)->orderBy('recipient_user_id')->pluck('recipient_user_id')->map(fn ($id) => (int) $id)->all();
    }

    private function pdf(string $name = 'a.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 40, 'application/pdf');
    }

    private function signupPayload(array $overrides = []): array
    {
        return $overrides + [
            'org_type' => 'university', 'org_name' => 'Universiti Baharu', 'org_size' => '1,000 - 5,000', 'access_model' => 'closed',
            'domains' => [['domain' => 'baharu.edu.my', 'role' => 'Student']],
            'admin_name' => 'Aminah Baharu', 'admin_title' => 'Registrar', 'admin_email' => 'aminah@baharu.edu.my',
            'admin_password' => 'Secret#Pass99', 'admin_password_confirmation' => 'Secret#Pass99', 'confirm_authorized' => 1, 'confirm_terms' => 1,
        ];
    }

    private function applicant(VerificationStatus $status = VerificationStatus::Pending, string $kb = 'KB10001'): User
    {
        $user = User::factory()->create(['name' => 'Siti Pemohon']);
        Membership::create(['user_id' => $user->id, 'org_id' => $this->session['current_org_id'], 'role' => MembershipRole::Counselor, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        \App\Models\CounselorProfile::create([
            'user_id' => $user->id, 'kb_number' => $kb, 'pa_number' => 'PA10001', 'pa_valid_until' => now()->addYear(),
            'verification_type' => \App\Enums\VerificationType::PlatformVerified, 'verification_status' => $status,
        ]);

        return $user;
    }

    // ---- organization.application_submitted -----------------------------------------------------------------------------------------------------

    public function test_a_new_organization_tells_every_active_admin_once_and_the_email_follows_the_preference(): void
    {
        Notification::fake();
        $default = $this->makeAdmin('Default');                                                    // email on by default
        $emailOff = $this->makeAdmin('Email Off', ['new_organization_application' => false]);
        $suspended = $this->makeAdmin('Suspended', null, MembershipStatus::Suspended);
        $removed = $this->makeAdmin('Removed', null, MembershipStatus::Offboarded);

        $this->post(route('organizations.signup.store'), $this->signupPayload())->assertRedirect();

        $organization = Organization::where('name', 'Universiti Baharu')->firstOrFail();
        $everyone = [$this->admin->id, $default->id, $emailOff->id];
        sort($everyone);

        $this->assertSame($everyone, $this->recipients('organization.application_submitted'), 'in-app: every ACTIVE admin, whatever their email setting');
        $row = $this->of('organization.application_submitted')->first();
        $this->assertSame([$organization->id, 'organization', 'Universiti Baharu'], [$row->target_id, $row->target_type, $row->label]);

        Notification::assertSentTo([$this->admin, $default], NewOrganizationApplication::class);
        Notification::assertNotSentTo([$emailOff, $suspended, $removed], NewOrganizationApplication::class);
        $this->assertSame(0, UserNotification::query()->whereIn('recipient_user_id', [$suspended->id, $removed->id])->count());
    }

    public function test_running_the_same_notification_again_adds_nothing(): void
    {
        Notification::fake();
        $this->post(route('organizations.signup.store'), $this->signupPayload());
        $organization = Organization::where('name', 'Universiti Baharu')->firstOrFail();
        $before = UserNotification::count();

        app(PlatformAdminNotifier::class)->newOrganizationApplication($organization);
        app(PlatformAdminNotifier::class)->newOrganizationApplication($organization);

        $this->assertSame($before, UserNotification::count());
    }

    public function test_a_signup_that_fails_validation_creates_no_notification_and_no_email(): void
    {
        Notification::fake();

        $this->post(route('organizations.signup.store'), $this->signupPayload(['org_name' => '']))->assertSessionHasErrors('org_name');

        $this->assertSame(0, UserNotification::count());
        Notification::assertNothingSent();
    }

    public function test_a_failure_to_create_the_notification_never_blocks_the_signup_or_its_email(): void
    {
        \Illuminate\Support\Facades\Exceptions::fake();
        Notification::fake();
        Schema::drop('user_notifications');

        $this->post(route('organizations.signup.store'), $this->signupPayload())->assertRedirect();

        $this->assertNotNull(Organization::where('name', 'Universiti Baharu')->first(), 'the organization was created');
        Notification::assertSentTo($this->admin, NewOrganizationApplication::class);
        \Illuminate\Support\Facades\Exceptions::assertReported(\Illuminate\Database\QueryException::class);
    }

    // ---- counselor.application_submitted / resubmitted ----------------------------------------------------------------------------------------------------

    public function test_a_counselor_sending_all_documents_is_one_notification_per_admin_and_a_later_upload_adds_none(): void
    {
        Notification::fake();
        $other = $this->makeAdmin('Other Admin');
        $applicant = $this->applicant();

        $this->actingAs($applicant)->post(route('counselors.signup.documents.store'), ['cert' => $this->pdf('c.pdf'), 'pa' => $this->pdf('p.pdf')]);
        $this->assertSame(0, $this->of('counselor.application_submitted')->count(), 'not complete yet: nothing');

        $this->actingAs($applicant)->post(route('counselors.signup.documents.store'), ['ic' => $this->pdf('i.pdf')]);

        $this->assertSame([$this->admin->id, $other->id], $this->recipients('counselor.application_submitted'));
        $this->assertSame('Siti Pemohon', $this->of('counselor.application_submitted')->first()->label);
        Notification::assertSentTo([$this->admin, $other], NewCounselorApplication::class);

        $this->actingAs($applicant)->post(route('counselors.signup.documents.store'), ['ic' => $this->pdf('i2.pdf')]);
        $this->assertSame(2, $this->of('counselor.application_submitted')->count(), 'replacing a document is not a new application');
    }

    public function test_a_resubmission_is_its_own_notification_for_each_attempt(): void
    {
        Notification::fake();
        $applicant = $this->applicant(VerificationStatus::Rejected);
        $profile = $applicant->counselorProfile;
        foreach (['cert', 'pa', 'ic'] as $type) {
            $profile->documents()->create(['type' => $type, 'path' => "x/{$type}.pdf", 'original_name' => "{$type}.pdf", 'size_bytes' => 10, 'uploaded_at' => now()]);
        }
        $profile->forceFill(['submitted_at' => now()->subDays(20), 'submission_count' => 1])->save();

        $this->actingAs($applicant)->post(route('counselors.signup.documents.store'), ['cert' => $this->pdf('new.pdf')]);

        $row = $this->of('counselor.application_resubmitted')->sole();
        $this->assertSame($this->admin->id, $row->recipient_user_id);
        $this->assertSame([$profile->id, 'counselor_profile', 'Siti Pemohon'], [$row->target_id, $row->target_type, $row->label]);
        $this->assertStringEndsWith(':2', $row->occurrence_key, 'attempt number 2');
        $this->assertSame(0, $this->of('counselor.application_submitted')->count());

        // The same attempt told again (a retry) adds nothing; a third attempt would be new.
        app(PlatformAdminNotifier::class)->newCounselorApplication($profile->fresh(), resubmitted: true);
        $this->assertSame(1, $this->of('counselor.application_resubmitted')->count());
    }

    // ---- counselor.kb_duplicate_flagged -----------------------------------------------------------------------------------------------------------------

    public function test_repeated_attempts_on_one_kb_number_are_one_notification_with_a_count(): void
    {
        $recorder = app(CounselorKbAttemptRecorder::class);

        $recorder->record('KB10891', 'Someone', '203.0.113.50');
        $recorder->record('KB10891', 'Someone', '203.0.113.50');          // same address within the hour: not recorded at all
        $recorder->record('KB10891', 'Someone Else', '203.0.113.51');
        $recorder->record('KB10891', 'A Third', '203.0.113.52');

        $row = $this->of('counselor.kb_duplicate_flagged')->sole();
        $this->assertSame($this->admin->id, $row->recipient_user_id);
        $this->assertSame(3, $row->count);
        $this->assertSame('Tan Wei Ming', $row->label, 'the existing account holder, never the name typed by the other person');
        $this->assertSame($this->profile('KB10891')->id, $row->target_id);

        $recorder->record('KB11256', 'Another', '203.0.113.60');
        $this->assertSame(2, $this->of('counselor.kb_duplicate_flagged')->count(), 'a different KB number is a different notification');

        Carbon::setTestNow(now()->addHours(25));
        $recorder->record('KB10891', 'Next Day', '203.0.113.70');
        $this->assertSame([3, 1], $this->of('counselor.kb_duplicate_flagged')->where('target_id', $this->profile('KB10891')->id)->orderBy('id')->pluck('count')->all(), 'a day later it starts a new one');
    }

    // ---- lkm.review_requested -----------------------------------------------------------------------------------------------------------------------------

    public function test_an_lkm_recheck_request_tells_the_admins_without_its_details(): void
    {
        $other = $this->makeAdmin('Other Admin');
        $profile = $this->profile('KB10234');

        $review = app(LkmReviewService::class)->request($profile, ['pa_valid_until' => now()->addYears(2)->toDateString()], UploadedFile::fake()->createWithContent('my-secret-renewal.pdf', CounselorReviewSeeder::pdf('Renewed certificate')));

        $this->assertSame([$this->admin->id, $other->id], $this->recipients('lkm.review_requested'));
        $row = $this->of('lkm.review_requested')->first();
        $this->assertSame([$review->id, 'lkm_review_request', $profile->user->name], [$row->target_id, $row->target_type, $row->label]);

        $dump = json_encode(DB::table('user_notifications')->get());
        foreach (['my-secret-renewal', $profile->pa_number, $profile->kb_number, $profile->user->email, now()->addYears(2)->format('Y')] as $private) {
            $this->assertStringNotContainsString($private, $dump);
        }
    }

    // ---- admin.invitation_accepted -----------------------------------------------------------------------------------------------------------------------

    public function test_a_new_admin_joining_tells_the_others_but_not_themselves(): void
    {
        $other = $this->makeAdmin('Other Admin');
        $removed = $this->makeAdmin('Removed Admin', null, MembershipStatus::Offboarded);
        [, $url] = $this->invite('Nur Aisyah', 'aisyah@mykaunsel.com');
        preg_match('#/admin-invitation/([a-f0-9]{64})#', $url, $match);

        $joined = app(AdminInvitationService::class)->accept($match[1], self::NEW_PASSWORD);

        $this->assertSame([$this->admin->id, $other->id], $this->recipients('admin.invitation_accepted'));
        $this->assertSame(0, UserNotification::query()->where('recipient_user_id', $joined->id)->count(), 'they did it themselves');
        $this->assertSame(0, UserNotification::query()->where('recipient_user_id', $removed->id)->count());
        $row = $this->of('admin.invitation_accepted')->first();
        $this->assertSame([$joined->id, 'user', 'Nur Aisyah'], [$row->target_id, $row->target_type, $row->label]);
        $this->assertStringNotContainsString('aisyah@mykaunsel.com', json_encode(DB::table('user_notifications')->get()), 'no email address');
    }

    // ---- counselor.pa_expired_suspended (the daily check) ------------------------------------------------------------------------------------------------

    public function test_the_daily_pa_check_tells_the_admins_once_even_when_it_runs_again(): void
    {
        $other = $this->makeAdmin('Other Admin');
        $profile = $this->profile('KB10891');                                  // an approved independent counselor
        $profile->forceFill(['pa_valid_until' => now()->subDay()->toDateString()])->save();
        \App\Models\LkmDirectorySnapshot::where('kb_number', 'KB10891')->delete();   // no newer date on the LKM record to extend it from

        $this->artisan('counselors:check-pa-expiry')->assertSuccessful();
        $this->assertSame(VerificationStatus::Suspended, $profile->fresh()->verification_status);

        $this->assertSame([$this->admin->id, $other->id], $this->recipients('counselor.pa_expired_suspended'));
        $this->assertSame([$profile->id, 'counselor_profile', $profile->user->name], [$this->of('counselor.pa_expired_suspended')->first()->target_id, 'counselor_profile', $this->of('counselor.pa_expired_suspended')->first()->label]);

        $this->artisan('counselors:check-pa-expiry')->assertSuccessful();
        $this->artisan('counselors:check-pa-expiry')->assertSuccessful();
        $this->assertSame(2, $this->of('counselor.pa_expired_suspended')->count(), 'one per admin, however many times it runs');
    }

    public function test_an_admins_own_suspension_is_not_news_to_the_admins(): void
    {
        app(CounselorStatusService::class)->suspend($this->profile('KB11935'), $this->admin, 'Repeated complaints about conduct.');

        $this->assertSame(0, $this->of('counselor.pa_expired_suspended')->count());
        $this->assertSame(0, UserNotification::count());
    }

    // ---- system.maintenance_auto_reopened ---------------------------------------------------------------------------------------------------------------

    public function test_maintenance_switching_itself_off_tells_the_admins_once(): void
    {
        $other = $this->makeAdmin('Other Admin');
        app(PlatformSettings::class)->setMaintenance($this->admin, true, null, now()->addMinutes(5)->toImmutable());

        $this->artisan('maintenance:reopen-due')->assertSuccessful();
        $this->assertSame(0, $this->of('system.maintenance_auto_reopened')->count(), 'not due yet');

        Carbon::setTestNow(now()->addMinutes(6));
        $this->artisan('maintenance:reopen-due')->assertSuccessful();
        $this->artisan('maintenance:reopen-due')->assertSuccessful();

        $this->assertSame([$this->admin->id, $other->id], $this->recipients('system.maintenance_auto_reopened'));
        $row = $this->of('system.maintenance_auto_reopened')->first();
        $this->assertNull($row->target_id);
        $this->assertNull($row->label);
    }

    // ---- an admin who is removed -------------------------------------------------------------------------------------------------------------------------

    public function test_removing_an_admin_deletes_their_notifications_and_only_theirs(): void
    {
        $leaving = $this->makeAdmin('Leaving Admin');
        $this->post(route('organizations.signup.store'), $this->signupPayload());
        $this->assertSame(1, UserNotification::query()->where('recipient_user_id', $leaving->id)->count());

        app(PlatformAdminService::class)->remove($this->admin, $leaving, 'Left the team.');

        $this->assertSame(0, UserNotification::query()->where('recipient_user_id', $leaving->id)->count());
        $this->assertSame(1, UserNotification::query()->where('recipient_user_id', $this->admin->id)->count(), 'the others are untouched');

        // …and from now on they receive nothing.
        $this->post(route('organizations.signup.store'), $this->signupPayload(['org_name' => 'Syarikat Lain', 'admin_email' => 'lain@lain.edu.my', 'domains' => [['domain' => 'lain.edu.my', 'role' => 'Student']]]));
        $this->assertSame(0, UserNotification::query()->where('recipient_user_id', $leaving->id)->count());
    }

    // ---- what is NOT produced ----------------------------------------------------------------------------------------------------------------------------

    public function test_the_types_with_no_source_yet_are_defined_but_nothing_creates_them(): void
    {
        $this->post(route('organizations.signup.store'), $this->signupPayload());
        app(CounselorKbAttemptRecorder::class)->record('KB10891', 'X', '203.0.113.9');

        foreach (['organization.application_resubmitted', 'complaint.received', 'complaint.escalated'] as $type) {
            $this->assertTrue(app(\App\Support\Inbox\NotificationRegistry::class)->has($type));
            $this->assertSame(0, $this->of($type)->count(), $type);
        }
    }

    public function test_nothing_private_from_any_of_the_flows_is_in_the_table_or_the_panel(): void
    {
        Notification::fake();
        $this->post(route('organizations.signup.store'), $this->signupPayload());
        $applicant = $this->applicant(kb: 'KB10002');
        $this->actingAs($applicant)->post(route('counselors.signup.documents.store'), ['cert' => $this->pdf('secret-cert.pdf'), 'pa' => $this->pdf('secret-pa.pdf'), 'ic' => $this->pdf('secret-ic.pdf')]);
        app(CounselorKbAttemptRecorder::class)->record('KB10891', 'Typed By Someone Else', '203.0.113.9');

        $dump = json_encode(DB::table('user_notifications')->get());
        $panel = $this->as()->get(route('platform.notifications.panel'))->getContent();

        foreach (['KB10002', 'KB10891', 'PA10001', 'secret-', 'Typed By Someone Else', 'baharu.edu.my', 'aminah@', 'Secret#Pass99', $applicant->email, 'tan.weiming'] as $private) {
            $this->assertStringNotContainsString($private, $dump, $private);
            $this->assertStringNotContainsString($private, $panel, $private);
        }
        $this->assertStringContainsString('Universiti Baharu', $panel, 'the names are there');
    }
}
