<?php

namespace Tests\Feature\Platform;

use App\Enums\AccessModel;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * The way back in during maintenance mode. Only a Platform Admin may use MyKaunsel then, so the whole sign-in has to work
 * from a signed-out browser: sign-in -> (two-factor) -> /dashboard -> role chooser -> Platform Admin area.
 * (An earlier version let the sign-in PAGE through but not its submit, which would have locked every admin out.)
 */
class MaintenanceAccessTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Secret#123';

    private Organization $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);

        $this->platform = Organization::create(['name' => 'MyKaunsel Platform', 'org_type' => OrgType::Platform, 'access_model' => AccessModel::Open, 'subscription_status' => OrgStatus::Active]);
    }

    private function user(string $name, MembershipRole $role, ?Organization $org = null, MembershipStatus $status = MembershipStatus::Active): User
    {
        $user = User::factory()->create(['name' => $name, 'password' => self::PASSWORD]);

        $this->join($user, $role, $org ?? $this->platform, $status);

        return $user;
    }

    private function join(User $user, MembershipRole $role, Organization $org, MembershipStatus $status = MembershipStatus::Active): void
    {
        Membership::create(['user_id' => $user->id, 'org_id' => $org->id, 'role' => $role, 'status' => $status, 'joined_at' => now()]);
    }

    private function university(): Organization
    {
        return Organization::create(['name' => 'Test University', 'org_type' => OrgType::University, 'access_model' => AccessModel::Open, 'subscription_status' => OrgStatus::Active]);
    }

    /** Writes the setting straight to the database (as another process would), then forgets what this request remembered. */
    private function maintenance(bool $on): void
    {
        PlatformSetting::findOrFail('maintenance')->update(['value' => $on]);
        $this->app->forgetInstance(PlatformSettings::class);
    }

    private function signedOut(): void
    {
        $this->flushSession();
        auth()->logout();
    }

    private function signIn(User $user)
    {
        return $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);
    }

    // ---- the way in ---------------------------------------------------------------------------

    public function test_a_platform_admin_can_sign_in_and_reach_the_platform_area_while_maintenance_is_on(): void
    {
        $admin = $this->user('Ammar Azizan', MembershipRole::PlatformAdmin);
        $this->maintenance(true);

        // The page is shown (and says maintenance is on for everybody else)...
        $this->get('/')->assertStatus(503);
        $this->get(route('login'))->assertOk();

        // ...and so is its submit: this was the 503 that locked admins out.
        $this->signIn($admin)->assertRedirect(route('dashboard', absolute: false))->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($admin);

        // Every hop after it: /dashboard -> role chooser (a single role is chosen for them) -> /dashboard -> Platform Admin area.
        $this->followingRedirects()->get(route('dashboard'))->assertOk()->assertSee('Maintenance mode is ON');
        $this->assertSame('platform_admin', session('current_role'));

        // And they can really work there, including switching maintenance off.
        $this->post(route('platform.registration.maintenance'), ['state' => 'off'])->assertSessionHas('status');
        $this->assertFalse(app(PlatformSettings::class)->maintenanceOn());
    }

    public function test_the_whole_sign_in_in_one_go_follows_every_redirect_to_the_platform_dashboard(): void
    {
        $admin = $this->user('Ammar Azizan', MembershipRole::PlatformAdmin);
        $this->maintenance(true);

        $this->followingRedirects()->post('/login', ['email' => $admin->email, 'password' => self::PASSWORD])
            ->assertOk()->assertSee('Maintenance mode is ON')->assertDontSee('down for maintenance');
    }

    public function test_a_wrong_password_during_maintenance_is_a_normal_sign_in_error_not_a_maintenance_page(): void
    {
        $admin = $this->user('Ammar Azizan', MembershipRole::PlatformAdmin);
        $this->maintenance(true);

        $this->from('/login')->post('/login', ['email' => $admin->email, 'password' => 'not-the-password'])
            ->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_an_admin_with_two_factor_on_can_finish_the_second_step_during_maintenance(): void
    {
        $admin = $this->user('Ammar Azizan', MembershipRole::PlatformAdmin);

        // Turn two-factor on the real way, before maintenance.
        $session = ['current_org_id' => $this->platform->id, 'current_role' => 'platform_admin'];
        $this->actingAs($admin)->withSession($session)->post(route('platform.account.two-factor.begin'), ['current_password' => self::PASSWORD])->assertRedirect();
        $secret = $admin->fresh()->two_factor_secret;
        $this->actingAs($admin)->withSession($session)->post(route('platform.account.two-factor.confirm'), ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertSessionHas('recovery_codes');
        $this->signedOut();

        $this->maintenance(true);

        $this->signIn($admin)->assertRedirect(route('two-factor.challenge'));
        $this->get(route('two-factor.challenge'))->assertOk();

        // The code of the NEXT 30 seconds: the current one was used up by setup (replay protection).
        $google = new Google2FA;
        $this->post(route('two-factor.challenge.store'), ['code' => $google->oathTotp($secret, $google->getTimestamp() + 1)])->assertRedirect();
        $this->assertAuthenticatedAs($admin);

        $this->followingRedirects()->get(route('dashboard'))->assertOk()->assertSee('Maintenance mode is ON');
    }

    public function test_forgot_and_reset_password_still_work_during_maintenance(): void
    {
        $admin = $this->user('Ammar Azizan', MembershipRole::PlatformAdmin);
        $this->maintenance(true);

        $this->get(route('password.request'))->assertOk();
        $this->post(route('password.email'), ['email' => $admin->email])->assertSessionHasNoErrors()->assertRedirect();
        $this->get(route('password.reset', 'sometoken'))->assertOk();
    }

    public function test_an_admin_who_belongs_to_more_than_one_organization_is_asked_which_role_and_can_switch_back(): void
    {
        $admin = $this->user('Ammar Azizan', MembershipRole::PlatformAdmin);
        $university = $this->university();
        $this->join($admin, MembershipRole::Counselor, $university);
        $this->maintenance(true);

        $this->signIn($admin)->assertRedirect(route('dashboard', absolute: false));
        $this->followingRedirects()->get(route('dashboard'))->assertOk()->assertSee('Test University'); // the chooser

        // Picks the counselor role by mistake: stopped at the maintenance page, which points to the chooser (not to a sign-in that would loop).
        $this->post(route('context.store'), ['org_id' => $university->id, 'role' => 'counselor'])->assertRedirect();
        $this->get(route('counselor.dashboard'))->assertStatus(503)->assertSee(route('context.select'), false)->assertSee('choose your role');
        $this->get(route('dashboard'))->assertRedirect(); // the dashboard hop is open to them, so they are not stuck

        $this->get(route('context.select'))->assertOk();
        $this->post(route('context.store'), ['org_id' => $this->platform->id, 'role' => 'platform_admin'])->assertRedirect();
        $this->get(route('platform.dashboard'))->assertOk();
    }

    // ---- everybody else stays out -------------------------------------------------------------

    public function test_other_roles_can_sign_in_but_every_page_after_it_is_the_maintenance_page(): void
    {
        $counselor = $this->user('Aina Syazwani', MembershipRole::Counselor, $this->university());
        $this->maintenance(true);

        $this->signIn($counselor)->assertRedirect(); // the form works; where they land does not
        $this->get(route('dashboard'))->assertStatus(503)->assertSee('down for maintenance');
        $this->get(route('platform.dashboard'))->assertStatus(503);
        $this->get(route('platform.registration'))->assertStatus(503);
        $this->post(route('platform.registration.maintenance'), ['state' => 'off'])->assertStatus(503);
        $this->assertTrue(app(PlatformSettings::class)->maintenanceOn());

        // They can sign out.
        $this->post('/logout')->assertRedirect();
        $this->assertGuest();
    }

    public function test_a_signed_in_counselor_is_not_let_through_the_dashboard_hop(): void
    {
        $counselor = $this->user('Aina Syazwani', MembershipRole::Counselor, $this->university());
        $this->maintenance(true);

        $this->actingAs($counselor)->get(route('dashboard'))->assertStatus(503);
        // The role chooser lists only their own roles, and whichever they pick still ends at the maintenance page.
        $this->actingAs($counselor)->get(route('context.select'))->assertRedirect(route('dashboard'));
        $this->actingAs($counselor)->withSession(['current_org_id' => $counselor->memberships()->first()->org_id, 'current_role' => 'counselor'])->get(route('counselor.dashboard'))->assertStatus(503);
    }

    public function test_someone_who_is_no_longer_a_platform_admin_is_not_let_through_the_dashboard_hop(): void
    {
        $removed = $this->user('Former Admin', MembershipRole::PlatformAdmin, status: MembershipStatus::Offboarded);
        $this->maintenance(true);

        $this->actingAs($removed)->get(route('dashboard'))->assertStatus(503);
        $this->actingAs($removed)->get(route('platform.dashboard'))->assertStatus(503);
    }

    public function test_a_signed_out_visitor_cannot_use_the_dashboard_hop(): void
    {
        $this->maintenance(true);

        // Not signed in: sent to the sign-in page (which is open), never into the platform.
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('platform.dashboard'))->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk();
    }

    public function test_the_maintenance_page_points_visitors_to_sign_in_and_signed_in_people_to_the_role_chooser(): void
    {
        $counselor = $this->user('Aina Syazwani', MembershipRole::Counselor, $this->university());
        $this->maintenance(true);

        $this->get('/')->assertStatus(503)->assertSee('Platform Admin sign in')->assertSee(route('login'), false)->assertDontSee('choose your role');
        $this->actingAs($counselor)->get('/')->assertStatus(503)->assertSee('choose your role')->assertSee(route('context.select'), false);
    }

    // ---- the rest of what must keep working -----------------------------------------------------------

    public function test_the_health_check_and_the_apps_status_call_answer_during_maintenance_but_the_api_does_not(): void
    {
        $this->maintenance(true);

        $this->get('/up')->assertOk();
        $this->getJson('/api/v1/status')->assertOk()->assertJsonPath('maintenance.on', true);
        $this->postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'x', 'device_name' => 'phone'])->assertStatus(503)->assertHeader('Retry-After');
    }

    // ---- a guard against forgetting this again ----------------------------------------------------------

    public function test_every_route_of_the_sign_in_flow_is_named_and_let_through_by_the_maintenance_gate(): void
    {
        $allowed = config('platform-settings.maintenance_allowed_routes');
        $missing = [];

        foreach (Route::getRoutes() as $route) {
            // The "guest" routes are the ones used before anyone is signed in. Sign-UP (guarded by registration.open) is
            // blocked by maintenance on purpose; everything else must get through.
            $middleware = $route->gatherMiddleware();

            $signUp = collect($middleware)->contains(fn ($m) => is_string($m) && str_starts_with($m, 'registration.open')) || $route->getName() === 'register.type';

            if (! in_array('guest', $middleware, true) || $signUp) {
                continue;
            }

            if (! in_array($route->getName(), $allowed, true)) {
                $missing[] = $route->methods()[0].' /'.$route->uri().' ('.($route->getName() ?? 'no name').')';
            }
        }

        $this->assertSame([], $missing, "These sign-in routes would answer 503 during maintenance and lock admins out. Give them a name and list it in config/platform-settings.php:\n".implode("\n", $missing));
        $this->assertContains('logout', $allowed);
        $this->assertContains('login.store', $allowed);
    }

    public function test_every_name_in_the_allow_lists_is_a_real_route(): void
    {
        foreach ([...config('platform-settings.maintenance_allowed_routes'), ...config('platform-settings.maintenance_admin_entry_routes')] as $name) {
            $this->assertTrue(Route::has($name), "{$name} is listed in config/platform-settings.php but no route has that name");
        }
    }
}
