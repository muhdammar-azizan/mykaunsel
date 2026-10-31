<?php

namespace Tests\Feature\Organization;

use App\Enums\AccessModel;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Exceptions\MembershipRefused;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\OrgInviteCode;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\MembershipApproved;
use App\Notifications\MembershipDeclined;
use App\Notifications\MemberWaitingApproval;
use App\Services\MembershipService;
use App\Services\OrgMemberService;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** An organization admin's Members page: reference codes, and approving or declining the people who used one. */
class OrgMembersPageTest extends TestCase
{
    use RefreshDatabase;

    private Organization $acme;

    private Organization $beta;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlatformSeeder::class);
        $this->acme = $this->org('Acme Berhad');
        $this->beta = $this->org('Beta Sdn Bhd');
        $this->hr = $this->person('Hana HR');
        $this->join($this->hr, $this->acme, MembershipRole::OrgAdmin, MembershipStatus::Active);
    }

    private function org(string $name): Organization
    {
        return Organization::create(['name' => $name, 'org_type' => OrgType::Corporate, 'access_model' => AccessModel::Closed, 'subscription_status' => OrgStatus::Active]);
    }

    private function person(string $name = 'Siti Aminah'): User
    {
        return User::factory()->create(['name' => $name, 'email_verified_at' => now(), 'timezone' => 'Asia/Kuala_Lumpur']);
    }

    private function join(User $user, Organization $org, MembershipRole $role, MembershipStatus $status): Membership
    {
        return Membership::create(['user_id' => $user->id, 'org_id' => $org->id, 'role' => $role, 'status' => $status, 'joined_at' => now()]);
    }

    /** Someone who used a code of the given organization and is waiting. */
    private function waiting(string $name = 'Siti Aminah', ?Organization $org = null): Membership
    {
        $org ??= $this->acme;

        return app(MembershipService::class)->joinViaInviteCode($this->person($name), OrgInviteCode::issue($org, null, null, null)->code);
    }

    private function hr(?Organization $org = null)
    {
        return $this->actingAs($this->hr)->withSession(['current_org_id' => ($org ?? $this->acme)->id, 'current_role' => 'org_admin']);
    }

    private function audit(string $action)
    {
        return AuditLog::where('action', $action)->get();
    }

    public function test_only_an_organization_admin_opens_the_page(): void
    {
        $this->get(route('org.members'))->assertRedirect();

        $employee = $this->person();
        $this->join($employee, $this->acme, MembershipRole::Employee, MembershipStatus::Active);
        $this->actingAs($employee)->withSession(['current_org_id' => $this->acme->id, 'current_role' => 'employee'])
            ->get(route('org.members'))->assertStatus(403);

        $this->hr()->get(route('org.members'))->assertOk()->assertSee('Nobody is waiting')->assertSee('No codes yet');
    }

    public function test_the_page_shows_who_is_waiting_the_codes_and_the_members_of_this_organization_only(): void
    {
        $this->waiting('Siti Aminah');
        $this->waiting('Orang Beta', $this->beta);
        $member = $this->person('Aiman Employee');
        $this->join($member, $this->acme, MembershipRole::Employee, MembershipStatus::Active);
        $code = OrgInviteCode::issue($this->acme, $this->hr, 5, null);

        $this->hr()->get(route('org.members'))->assertOk()
            ->assertSee('Waiting for your approval (1)')->assertSee('Siti Aminah')
            ->assertSee('Members (1)')->assertSee('Aiman Employee')
            ->assertSee($code->display())->assertSee('0 of 5')
            ->assertDontSee('Orang Beta')
            ->assertDontSee('text-gray-900">Hana HR</p>', false);
    }

    public function test_each_working_code_comes_with_words_to_send_that_say_where_to_type_it(): void
    {
        $on = OrgInviteCode::issue($this->acme, $this->hr, null, null);
        $off = OrgInviteCode::issue($this->acme, $this->hr, null, null);
        $off->update(['revoked_at' => now()]);

        $page = $this->hr()->get(route('org.members'))->assertOk()
            ->assertSee('Message to send with this code')
            ->assertSee('Open Account, then Your organization, and enter this code: '.$on->display());

        $this->assertSame(1, substr_count($page->getContent(), 'Message to send with this code</summary>'), 'only for a code that still works');
        $page->assertDontSee('enter this code: '.$off->display());
    }

    public function test_creating_a_code_sets_its_limits_and_is_audited(): void
    {
        $this->hr()->post(route('org.codes.store'), ['expires' => '7', 'max_uses' => '25'])->assertSessionHas('status');

        $code = OrgInviteCode::sole();
        $this->assertSame($this->acme->id, $code->org_id);
        $this->assertSame($this->hr->id, $code->created_by_user_id);
        $this->assertSame(25, $code->max_uses);
        $this->assertTrue($code->expires_at->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()));
        $this->assertSame($code->id, $this->audit('member.code_created')->sole()->metadata['code_id']);

        $this->hr()->post(route('org.codes.store'), ['expires' => 'never', 'max_uses' => ''])->assertSessionHas('status');
        $this->assertNull(OrgInviteCode::latest('id')->first()->expires_at);
        $this->assertNull(OrgInviteCode::latest('id')->first()->max_uses);
    }

    public function test_a_code_needs_a_sensible_limit_and_there_is_a_cap_on_open_codes(): void
    {
        $this->hr()->post(route('org.codes.store'), ['expires' => '7', 'max_uses' => '0'])->assertSessionHasErrors('max_uses');
        $this->hr()->post(route('org.codes.store'), ['expires' => 'forever'])->assertSessionHasErrors('expires');
        $this->assertSame(0, OrgInviteCode::count());

        for ($i = 0; $i < OrgMemberService::MAX_OPEN_CODES; $i++) {
            OrgInviteCode::issue($this->acme, $this->hr, null, null);
        }

        $this->hr()->post(route('org.codes.store'), ['expires' => '7'])->assertSessionHas('error');
        $this->assertSame(OrgMemberService::MAX_OPEN_CODES, OrgInviteCode::count());

        OrgInviteCode::first()->update(['revoked_at' => now()]);
        $this->hr()->post(route('org.codes.store'), ['expires' => '7'])->assertSessionHas('status');
    }

    public function test_switching_a_code_off_stops_new_people_and_keeps_those_waiting(): void
    {
        $waiting = $this->waiting();
        $code = OrgInviteCode::issue($this->acme, $this->hr, null, null);

        $this->hr()->post(route('org.codes.revoke', $code->id))->assertSessionHas('status');
        $this->hr()->post(route('org.codes.revoke', $code->id))->assertSessionHas('status');

        $this->assertNotNull($code->fresh()->revoked_at);
        $this->assertSame(1, $this->audit('member.code_revoked')->count(), 'repeating changes and records nothing more');
        $this->assertSame(MembershipStatus::PendingApproval, $waiting->fresh()->status);

        try {
            app(MembershipService::class)->joinViaInviteCode($this->person('Late'), $code->code);
            $this->fail('A switched-off code must be refused.');
        } catch (MembershipRefused $e) {
            $this->assertSame('invalid_code', $e->reason);
        }
    }

    public function test_one_organization_can_not_touch_the_codes_or_requests_of_another(): void
    {
        $theirCode = OrgInviteCode::issue($this->beta, null, null, null);
        $theirs = $this->waiting('Orang Beta', $this->beta);

        $this->hr()->post(route('org.codes.revoke', $theirCode->id))->assertNotFound();
        $this->hr()->post(route('org.members.approve', $theirs->id))->assertNotFound();
        $this->hr()->post(route('org.members.decline', $theirs->id))->assertNotFound();

        $this->assertNull($theirCode->fresh()->revoked_at);
        $this->assertSame(MembershipStatus::PendingApproval, $theirs->fresh()->status);
    }

    public function test_approving_opens_the_door_tells_the_person_and_is_audited_without_the_email(): void
    {
        Notification::fake();
        $row = $this->waiting();

        $this->hr()->post(route('org.members.approve', $row->id))->assertSessionHas('status');

        $row = $row->fresh();
        $this->assertSame(MembershipStatus::Active, $row->status);
        $this->assertSame(MembershipRole::Employee, $row->role);
        Notification::assertSentTo($row->user, MembershipApproved::class);

        $log = $this->audit('member.join_approved')->sole();
        $this->assertSame($row->user_id, $log->target_id);
        $this->assertSame($this->hr->id, $log->actor_user_id);
        $this->assertStringNotContainsString('@', json_encode($log->metadata));
    }

    public function test_declining_frees_the_place_tells_the_person_and_they_may_ask_again(): void
    {
        Notification::fake();
        $row = $this->waiting();
        $person = $row->user;

        $this->hr()->post(route('org.members.decline', $row->id))->assertSessionHas('status');

        $this->assertSame(MembershipStatus::Offboarded, $row->fresh()->status);
        Notification::assertSentTo($person, MembershipDeclined::class);
        $this->assertCount(1, $this->audit('member.join_declined'));

        $again = app(MembershipService::class)->joinViaInviteCode($person, OrgInviteCode::issue($this->acme, null, null, null)->code);
        $this->assertSame($row->id, $again->id);
        $this->assertSame(MembershipStatus::PendingApproval, $again->fresh()->status);
    }

    public function test_a_request_somebody_else_already_answered_is_not_answered_twice(): void
    {
        Notification::fake();
        $row = $this->waiting();

        $this->hr()->post(route('org.members.approve', $row->id));
        $this->hr()->post(route('org.members.decline', $row->id))->assertSessionHas('error');
        $this->hr()->post(route('org.members.approve', $row->id))->assertSessionHas('error');

        $this->assertSame(MembershipStatus::Active, $row->fresh()->status);
        Notification::assertSentToTimes($row->user, MembershipApproved::class, 1);
        Notification::assertNotSentTo($row->user, MembershipDeclined::class);
        $this->assertCount(0, $this->audit('member.join_declined'));
    }

    public function test_a_failing_mail_does_not_undo_the_approval(): void
    {
        $row = $this->waiting();
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 1]);

        $this->hr()->post(route('org.members.approve', $row->id))->assertSessionHas('status');

        $this->assertSame(MembershipStatus::Active, $row->fresh()->status);
    }

    public function test_the_admins_get_one_e_mail_saying_how_many_wait_and_not_who(): void
    {
        Notification::fake();
        $member = $this->person('Aiman Employee');
        $this->join($member, $this->acme, MembershipRole::Employee, MembershipStatus::Active);
        $code = OrgInviteCode::issue($this->acme, $this->hr, null, null);
        $service = app(MembershipService::class);

        $service->joinViaInviteCode($this->person('Siti Aminah'), $code->code);

        Notification::assertSentTo($this->hr, MemberWaitingApproval::class, function ($n) {
            $mail = $n->toMail($this->hr);

            return str_contains($mail->subject, 'One person is waiting')
                && ! str_contains(implode(' ', $mail->introLines).$mail->subject, 'Siti')
                && $mail->actionUrl === route('org.members');
        });
        Notification::assertNotSentTo($member, MemberWaitingApproval::class);

        // everyone who asks in the next minutes is covered by that one e-mail
        $service->joinViaInviteCode($this->person('Budi'), $code->code);
        Notification::assertSentToTimes($this->hr, MemberWaitingApproval::class, 1);

        // later, a new e-mail says how many wait now
        $this->travel(11)->minutes();
        $service->joinViaInviteCode($this->person('Dina'), $code->code);
        Notification::assertSentToTimes($this->hr, MemberWaitingApproval::class, 2);
        Notification::assertSentTo($this->hr, MemberWaitingApproval::class, fn ($n) => str_contains($n->toMail($this->hr)->subject, '3 people are waiting'));
    }

    public function test_a_refused_code_tells_nobody_and_an_organization_without_admins_does_not_break_joining(): void
    {
        Notification::fake();
        $service = app(MembershipService::class);

        try {
            $service->joinViaInviteCode($this->person('Siti Aminah'), 'WRONGCODE1');
        } catch (MembershipRefused) {
        }

        Notification::assertNothingSent();

        $lonely = $this->org('No Admin Co');
        $joined = $service->joinViaInviteCode($this->person('Budi'), OrgInviteCode::issue($lonely, null, null, null)->code);
        $this->assertSame(MembershipStatus::PendingApproval, $joined->status);
    }

    public function test_a_failing_mail_does_not_undo_joining(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 1]);

        $joined = app(MembershipService::class)->joinViaInviteCode($this->person('Siti Aminah'), OrgInviteCode::issue($this->acme, $this->hr, null, null)->code);

        $this->assertSame(MembershipStatus::PendingApproval, $joined->fresh()->status);
    }
}
