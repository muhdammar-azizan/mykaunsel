<?php

namespace Tests\Feature\Account;

use App\Enums\AccessModel;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgType;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\MemberLeft;
use App\Notifications\MemberWaitingApproval;
use App\Support\AccountArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Account pages (profile, security, notifications) for a counselor and an organization admin: the same pages as Platform Admin, in the portal shell. */
class PortalAccountTest extends TestCase
{
    use RefreshDatabase;

    private const OLD = 'Secret#123';

    private const NEW = 'Brand#New9pass';

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create(['name' => 'Acme Berhad', 'org_type' => OrgType::Corporate, 'access_model' => AccessModel::Closed, 'subscription_status' => 'active']);
    }

    private function person(MembershipRole $role, string $name = 'Hana Person'): array
    {
        $this->flushSession();
        $user = User::factory()->create(['name' => $name, 'password' => self::OLD, 'email_verified_at' => now()]);
        Membership::create(['user_id' => $user->id, 'org_id' => $this->org->id, 'role' => $role, 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        return [$user, ['current_org_id' => $this->org->id, 'current_role' => $role->value]];
    }

    /** One request as this person. A session keeps the password hash of the person before, so each request starts a clean one. */
    private function as(User $user, array $session)
    {
        $this->flushSession();

        return $this->actingAs($user)->withSession($session);
    }

    public function test_the_three_pages_open_inside_the_portal_for_a_counselor_and_an_organization_admin(): void
    {
        foreach ([MembershipRole::Counselor, MembershipRole::OrgAdmin] as $role) {
            [$user, $session] = $this->person($role);
            $as = function () use ($user, $session) {
                $this->flushSession();

                return $this->as($user, $session);
            };

            $as()->get(route('portal.account'))->assertOk()->assertSee('Personal information')->assertSee('Acme Berhad')->assertSee(route('portal.account.security'), false);
            $as()->get(route('portal.account.security'))->assertOk()->assertSee('Change password')->assertSee('Two-factor authentication');
            $as()->get(route('portal.account.notifications'))->assertOk()->assertSee('Email notifications');
        }
    }

    public function test_the_account_menu_of_the_portal_leads_to_it(): void
    {
        [$user, $session] = $this->person(MembershipRole::OrgAdmin);

        $this->as($user, $session)->get(route('org.dashboard'))->assertOk()->assertSee(route('portal.account'), false);
    }

    public function test_saving_the_profile_and_changing_the_password_return_to_the_portal_pages(): void
    {
        [$user, $session] = $this->person(MembershipRole::Counselor);
        $as = fn () => $this->as($user, $session);

        $as()->patch(route('portal.account.update'), ['name' => 'Hana Renamed', 'timezone' => 'Asia/Kuala_Lumpur'])->assertRedirect(route('portal.account'));
        $this->assertSame('Hana Renamed', $user->fresh()->name);

        $as()->put(route('portal.account.security.password'), ['current_password' => self::OLD, 'password' => self::NEW, 'password_confirmation' => self::NEW])
            ->assertRedirect(route('portal.account.security'));
    }

    public function test_each_role_sees_and_may_switch_off_only_its_own_e_mails(): void
    {
        [$counselor, $counselorSession] = $this->person(MembershipRole::Counselor, 'Aina');
        [$hr, $hrSession] = $this->person(MembershipRole::OrgAdmin, 'Hana');

        $this->as($counselor, $counselorSession)->get(route('portal.account.notifications'))
            ->assertSee('Sessions and time requests')->assertDontSee('Members')->assertDontSee('New organization application');
        $this->as($hr, $hrSession)->get(route('portal.account.notifications'))
            ->assertSee('Email me when people are waiting for my approval')->assertDontSee('Sessions and time requests')->assertDontSee('Weekly platform summary');

        $this->as($counselor, $counselorSession)->patchJson(route('portal.account.notifications.update'), ['key' => 'counselor_session_emails', 'enabled' => false])->assertOk();
        $this->assertFalse($counselor->fresh()->wantsNotification('counselor_session_emails'));

        $this->as($counselor, $counselorSession)->patchJson(route('portal.account.notifications.update'), ['key' => 'org_member_emails', 'enabled' => false])->assertUnprocessable();
        $this->as($counselor, $counselorSession)->patchJson(route('portal.account.notifications.update'), ['key' => 'weekly_summary', 'enabled' => false])->assertUnprocessable();
    }

    public function test_switching_a_choice_off_stops_that_e_mail_and_leaves_the_others(): void
    {
        [$hr] = $this->person(MembershipRole::OrgAdmin);

        $this->assertSame(['mail'], (new MemberWaitingApproval('Acme', 2))->via($hr));
        $this->assertSame(['mail'], (new MemberLeft('Acme', 'Siti'))->via($hr));

        $hr->forceFill(['notification_preferences' => ['org_member_emails' => false]])->save();

        $this->assertSame([], (new MemberWaitingApproval('Acme', 2))->via($hr->fresh()));
        $this->assertSame([], (new MemberLeft('Acme', 'Siti'))->via($hr->fresh()));
    }

    public function test_other_roles_and_signed_out_visitors_are_turned_away(): void
    {
        [$employee, $session] = $this->person(MembershipRole::Employee);

        $this->as($employee, $session)->get(route('portal.account'))->assertForbidden();
        $this->as($employee, $session)->patch(route('portal.account.update'), ['name' => 'X', 'timezone' => 'UTC'])->assertForbidden();
    }

    public function test_a_signed_out_visitor_is_sent_to_sign_in(): void
    {
        $this->get(route('portal.account'))->assertRedirect();
    }

    public function test_the_area_follows_the_route_name(): void
    {
        [$user, $session] = $this->person(MembershipRole::Counselor);
        $this->as($user, $session)->get(route('portal.account'));

        $this->assertSame('portal', AccountArea::prefix());
        $this->assertSame(route('portal.account.security'), AccountArea::route('account.security'));
    }
}
