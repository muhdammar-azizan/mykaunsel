<?php

namespace Tests\Feature\Platform;

use App\Enums\VerificationStatus;
use App\Models\CounselorProfile;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\OrganizationReviewSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\Feature\Counselor\CounselorTestCase;

/**
 * What a closed sign-up must NOT stop. Closing a kind of sign-up (here: all of them) only stops NEW accounts at the first
 * step. Someone who already started, people with an account, and the platform's own invitations carry on as before.
 */
class ClosedRegistrationFlowsTest extends CounselorTestCase
{
    private const PASSWORD = 'Str0ng-Pass-123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSamples();
        $this->seed(OrganizationReviewSeeder::class);

        $this->as()->post(route('platform.registration.all'), ['state' => 'closed'])->assertSessionHas('status');
        $this->flushSession();
        auth()->logout();
    }

    private function pdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->create($name, 50, 'application/pdf');
    }

    private function applicant(string $kb): User
    {
        return $this->profile($kb)->user;
    }

    // ---- the new sign-ups really are closed ---------------------------------------------------------------

    public function test_with_everything_closed_a_newcomer_can_do_nothing(): void
    {
        $this->get(route('register.type'))->assertForbidden()->assertSee('not accepting new account sign-ups');
        $this->get(route('organizations.signup.create'))->assertForbidden();
        $this->post(route('organizations.signup.store'), [])->assertForbidden();
        $this->get(route('counselors.signup.create'))->assertForbidden();
        $this->post(route('counselors.signup.store'), [])->assertForbidden();
        $this->get(route('register'))->assertForbidden();
        $this->postJson('/api/v1/auth/register', ['name' => 'A B', 'email' => 'new@example.com', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])->assertForbidden();

        $this->assertNull(User::where('email', 'new@example.com')->first());
    }

    // ---- someone who already started carries on (counselors) ------------------------------------------------------

    public function test_a_counselor_who_already_started_can_open_and_finish_the_document_step(): void
    {
        $applicant = $this->applicant('KB11023'); // pending
        $profile = $applicant->counselorProfile;

        $this->actingAs($applicant)->get(route('counselors.signup.documents'))->assertOk();
        $this->actingAs($applicant)->get(route('counselors.signup.waiting-approval'))->assertOk();

        $this->actingAs($applicant)->post(route('counselors.signup.documents.store'), ['cert' => $this->pdf('new-cert.pdf')])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('new-cert.pdf', $profile->documents()->where('type', 'cert')->firstOrFail()->original_name);
    }

    public function test_a_rejected_counselor_can_resubmit_while_sign_ups_are_closed(): void
    {
        $profile = $this->profile('KB12391'); // rejected
        $this->assertSame(VerificationStatus::Rejected, $profile->verification_status);

        $this->actingAs($profile->user)->get(route('counselors.signup.documents'))->assertOk();
        $this->actingAs($profile->user)->post(route('counselors.signup.documents.store'), ['cert' => $this->pdf('corrected.pdf')])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('corrected.pdf', $profile->documents()->where('type', 'cert')->firstOrFail()->original_name);
        $this->assertSame(VerificationStatus::Pending, $profile->fresh()->verification_status, 'back in the review queue');
    }

    // ---- ...and organizations -------------------------------------------------------------------------------------------

    public function test_an_organization_that_already_applied_can_still_follow_its_application(): void
    {
        $organization = Organization::where('subscription_status', 'pending')->whereHas('memberships', fn ($q) => $q->where('role', 'org_admin'))->firstOrFail();
        $admin = Membership::where('org_id', $organization->id)->where('role', 'org_admin')->firstOrFail()->user;

        $this->actingAs($admin)->get(route('organizations.signup.waiting-approval', $organization))->assertOk();

        $domain = $this->actingAs($admin)->get(route('organizations.signup.verify-domain', $organization));
        $this->assertNotContains($domain->getStatusCode(), [403, 404, 503], 'the domain step is not blocked');
    }

    // ---- everyone who already has an account ----------------------------------------------------------------------

    public function test_people_with_an_account_can_still_sign_in_on_the_web(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);

        $this->get(route('login'))->assertOk();
        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_app_can_still_sign_in_an_existing_user(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => self::PASSWORD, 'device_name' => 'phone'])
            ->assertOk()->assertJsonStructure(['token']);
    }

    public function test_forgot_and_reset_password_still_work(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => self::PASSWORD]);

        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasNoErrors();
        Notification::assertSentTo($user, ResetPassword::class);

        $token = Password::broker()->createToken($user);
        $this->post(route('password.store'), ['token' => $token, 'email' => $user->email, 'password' => 'An0ther-Pass-456', 'password_confirmation' => 'An0ther-Pass-456'])
            ->assertSessionHasNoErrors()->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('An0ther-Pass-456', $user->fresh()->password));
    }

    public function test_the_confirmation_email_link_still_confirms_the_address(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]);

        $this->actingAs($user)->get($url)->assertRedirect();

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    // ---- the platform's own invitations are not sign-ups ----------------------------------------------------------------

    public function test_inviting_and_accepting_a_platform_admin_works_while_every_sign_up_is_closed(): void
    {
        $this->as()->post(route('platform.admins.store'), ['name' => 'New Admin', 'email' => 'new.admin@example.com'])->assertSessionHasNoErrors()->assertSessionHas('status');
        $invitation = \App\Models\AdminInvitation::where('email', 'new.admin@example.com')->sole();
        $this->assertNull(User::where('email', 'new.admin@example.com')->first(), 'nothing is created until the person accepts');

        // Accepting creates the account although every sign-up is closed: an invitation is not a sign-up.
        $url = URL::temporarySignedRoute('admin-invitation.show', now()->addHour(), ['token' => $this->tokenFor($invitation)]);
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->post($url, ['password' => 'Str0ng!Passw0rd', 'password_confirmation' => 'Str0ng!Passw0rd'])->assertRedirect(route('login'));

        $this->assertNotNull(User::where('email', 'new.admin@example.com')->first());
    }

    /** The invitation stores only a hash, so give it a token we know. */
    private function tokenFor(\App\Models\AdminInvitation $invitation): string
    {
        $token = bin2hex(random_bytes(32));
        $invitation->update(['token_hash' => hash('sha256', $token)]);

        return $token;
    }
    public function test_closing_sign_ups_changes_no_existing_counselor(): void
    {
        $before = CounselorProfile::query()->pluck('verification_status', 'id')->all();

        $this->as()->get(route('platform.counselors'))->assertOk();

        $this->assertEquals($before, CounselorProfile::query()->pluck('verification_status', 'id')->all());
    }
}
