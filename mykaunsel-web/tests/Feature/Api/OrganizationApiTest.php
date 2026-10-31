<?php

namespace Tests\Feature\Api;

use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\OrgInviteCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * GET/POST/DELETE /api/v1/account/organization: the organization a person belongs to (or waits to join), joining with a reference code, and
 * leaving. The clock stands at Monday 2 November 2026, 09:00 Malaysian time.
 */
class OrganizationApiTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    private User $me;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();
        $this->me = User::factory()->create(['email_verified_at' => now()]);
        Sanctum::actingAs($this->me);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function code(): OrgInviteCode
    {
        return OrgInviteCode::issue($this->closed, null, null, null);
    }

    private function join(string $code)
    {
        return $this->postJson('/api/v1/account/organization/join', ['code' => $code]);
    }

    public function test_a_person_with_no_organization_sees_null(): void
    {
        $this->getJson('/api/v1/account/organization')->assertOk()->assertExactJson(['organization' => null, 'invitations' => []])
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_joining_with_a_code_waits_for_approval_and_shows_the_name_and_nothing_else(): void
    {
        $this->join(strtolower($this->code()->display()))->assertCreated()
            ->assertExactJson(['organization' => ['name' => 'Universiti Contoh', 'status' => 'waiting', 'grace_days' => 7]]);

        $this->getJson('/api/v1/account/organization')->assertOk()->assertJsonPath('organization.status', 'waiting');

        Membership::where('user_id', $this->me->id)->update(['status' => MembershipStatus::Active->value]);
        $this->getJson('/api/v1/account/organization')->assertJsonPath('organization.status', 'member');
    }

    public function test_the_refusals_have_a_stable_code_and_the_right_status(): void
    {
        $this->join('WRONGCODE1')->assertStatus(422)->assertJsonPath('code', 'invalid_code');
        $this->postJson('/api/v1/account/organization/join', [])->assertStatus(422)->assertJsonValidationErrors('code');

        $code = $this->code();
        $this->join($code->code)->assertCreated();
        $this->join($code->code)->assertStatus(409)->assertJsonPath('code', 'waiting_approval');

        $this->travel(2)->minutes(); // the limit is five tries a minute

        Membership::where('user_id', $this->me->id)->update(['status' => MembershipStatus::Active->value]);
        $this->join($code->code)->assertStatus(409)->assertJsonPath('code', 'already_member');

        $other = OrgInviteCode::issue($this->open, null, null, null);
        $this->join($other->code)->assertStatus(409)->assertJsonPath('code', 'in_other_organization')
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Universiti Contoh'));
    }

    public function test_an_unverified_email_is_stopped_before_it_gets_here(): void
    {
        Sanctum::actingAs(User::factory()->create(['email_verified_at' => null]));

        $this->join($this->code()->code)->assertStatus(403);
        $this->getJson('/api/v1/account/organization')->assertStatus(403);
    }

    public function test_it_needs_a_signed_in_person(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/account/organization')->assertUnauthorized();
        $this->deleteJson('/api/v1/account/organization')->assertUnauthorized();
    }

    public function test_guessing_codes_is_limited(): void
    {
        foreach (range(1, 5) as $i) {
            $this->join('GUESS0000'.$i)->assertStatus(422);
        }

        $this->join('GUESS00006')->assertStatus(429);
    }

    public function test_leaving_says_what_happened_to_the_sessions(): void
    {
        Notification::fake();
        Membership::create(['user_id' => $this->me->id, 'org_id' => $this->closed->id, 'role' => MembershipRole::Employee, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        $aina = $this->counselor('Aina Syazwani', [$this->closed]);
        $soon = $this->book($this->slot($aina, $this->closed, '2026-11-04 10:00'), $this->me);
        $later = $this->book($this->slot($aina, $this->closed, '2026-11-20 14:00'), $this->me);

        $this->deleteJson('/api/v1/account/organization')->assertOk()
            ->assertExactJson(['left' => ['organization' => 'Universiti Contoh', 'cancelled' => 1, 'kept' => 1]]);

        $this->assertSame(BookingStatus::Cancelled, $later->fresh()->status);
        $this->assertSame(BookingStatus::Confirmed, $soon->fresh()->status);
        $this->getJson('/api/v1/account/organization')->assertExactJson(['organization' => null, 'invitations' => []]);
    }

    public function test_leaving_when_there_is_nothing_to_leave_is_a_404_and_changes_nothing(): void
    {
        $this->deleteJson('/api/v1/account/organization')->assertNotFound()->assertJsonPath('code', 'not_a_member');
    }

    public function test_the_answers_belong_to_the_signed_in_person_only(): void
    {
        $this->join($this->code()->code)->assertCreated();

        Sanctum::actingAs(User::factory()->create(['email_verified_at' => now()]));
        $this->getJson('/api/v1/account/organization')->assertExactJson(['organization' => null, 'invitations' => []]);
        $this->deleteJson('/api/v1/account/organization')->assertNotFound();

        $this->assertSame(MembershipStatus::PendingApproval, Membership::where('user_id', $this->me->id)->sole()->status);
    }
}
