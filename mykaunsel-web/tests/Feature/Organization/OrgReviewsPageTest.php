<?php

namespace Tests\Feature\Organization;

use App\Enums\AccessModel;
use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgType;
use App\Models\CounselorProfile;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Review;
use App\Models\User;
use App\Services\ReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Api\AvailabilityFixtures;
use Tests\TestCase;

/** The organization admin's Reviews page: stars only, never a comment. Clock: Monday 2 November 2026, 09:00 Malaysian time. */
class OrgReviewsPageTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    private Organization $acme;

    private User $hr;

    private CounselorProfile $aina;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();
        config(['org-reports.min_group' => 5, 'reviews.min_for_average' => 5]);
        $this->acme = $this->org('Acme Berhad', AccessModel::Closed, OrgType::Corporate);
        $this->hr = User::factory()->create(['name' => 'Hana HR', 'email_verified_at' => now()]);
        Membership::create(['user_id' => $this->hr->id, 'org_id' => $this->acme->id, 'role' => MembershipRole::OrgAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        $this->aina = $this->counselor('Aina Counselor', [$this->acme]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function hr()
    {
        return $this->actingAs($this->hr)->withSession(['current_org_id' => $this->acme->id, 'current_role' => 'org_admin']);
    }

    /** One review of the given stars, from a client who had a completed session on its own day. */
    private function review(int $stars, ?CounselorProfile $who = null, ?Organization $org = null, string $comment = 'A private remark about the session', int $day = 1): Review
    {
        static $n = 0;
        $n++;

        $booking = $this->book(
            $this->slot($who ?? $this->aina, $org ?? $this->acme, Carbon::parse('2026-10-10 08:00')->addDays($day - 1)->addHours($n % 12)->format('Y-m-d H:i')),
            User::factory()->create(['name' => 'Siti Secret']),
            BookingStatus::Completed,
        );

        return app(ReviewService::class)->submit($booking->user, $booking->public_id, $stars, [], $comment)['review'];
    }

    public function test_an_empty_page_says_no_reviews_yet(): void
    {
        $this->hr()->get(route('org.reviews'))->assertOk()->assertSee('Not enough reviews yet')->assertSee('No reviews yet');
    }

    public function test_the_average_and_the_spread_of_stars_are_shown_from_five_reviews_and_never_a_comment(): void
    {
        foreach ([5, 5, 4, 4, 3] as $i => $stars) {
            $this->review($stars, day: $i + 1);
        }

        $page = $this->hr()->get(route('org.reviews'))->assertOk();

        $page->assertSee('4.2')->assertSeeInOrder(['How the stars are spread', 'Total', '5', '5 stars', '2', '4 stars', '2', '3 stars', 'fewer than 5'])
            ->assertDontSee('private remark')->assertDontSee('Siti Secret');
    }

    public function test_each_counselor_needs_their_own_five_reviews_even_when_the_organization_has_enough(): void
    {
        $bakar = $this->counselor('Bakar Counselor', [$this->acme]);
        foreach ([5, 4, 4, 3] as $i => $stars) {
            $this->review($stars, day: $i + 1);
        }
        $this->review(1, $bakar, day: 6);

        $page = $this->hr()->get(route('org.reviews'))->assertOk();

        $page->assertSee('3.4')->assertSeeInOrder(['Aina Counselor', 'fewer than 5', 'Not enough reviews'])->assertSeeInOrder(['Bakar Counselor', 'fewer than 5', 'Not enough reviews']);
    }

    public function test_another_organizations_reviews_and_removed_reviews_are_not_counted(): void
    {
        foreach ([5, 5, 5, 5, 5] as $i => $stars) {
            $this->review($stars, day: $i + 1);
        }
        $beta = $this->org('Beta', AccessModel::Closed, OrgType::Corporate);
        $other = $this->counselor('Cahaya', [$beta]);
        foreach ([1, 1, 1, 1, 1] as $i => $stars) {
            $this->review($stars, $other, $beta, day: $i + 8);
        }

        $this->hr()->get(route('org.reviews'))->assertSee('5.0')->assertDontSee('Cahaya');

        Review::query()->where('counselor_user_id', $this->aina->user_id)->first()->update(['status' => Review::REMOVED]);
        $this->hr()->get(route('org.reviews'))->assertSee('Not enough reviews yet');
    }

    public function test_the_period_decides_which_reviews_count(): void
    {
        foreach ([5, 5, 5, 5, 5] as $i => $stars) {
            $this->review($stars, day: $i + 1);                  // sessions in October
        }

        $this->hr()->get(route('org.reviews', ['range' => '12months']))->assertSee('5.0');
        $this->hr()->get(route('org.reviews', ['range' => 'month']))->assertSee('Not enough reviews yet');
    }

    public function test_only_an_organization_admin_opens_it_and_it_is_not_cached(): void
    {
        $employee = User::factory()->create();
        Membership::create(['user_id' => $employee->id, 'org_id' => $this->acme->id, 'role' => MembershipRole::Employee, 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        $this->actingAs($employee)->withSession(['current_org_id' => $this->acme->id, 'current_role' => 'employee'])->get(route('org.reviews'))->assertForbidden();
        $this->hr()->get(route('org.reviews'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }
}
