<?php

namespace Tests\Feature\Counselor;

use App\Enums\BookingStatus;
use App\Exceptions\BookingRefused;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\CounselorProfile;
use App\Models\Review;
use App\Models\User;
use App\Services\ReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Api\AvailabilityFixtures;
use Tests\TestCase;

/** Client reviews and the counselor's Reviews page (docs/REVIEWS.md). Clock: Monday 2 November 2026, 09:00 Malaysian time. */
class CounselorReviewsTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    private CounselorProfile $aina;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();
        $this->aina = $this->counselor('Aina', [$this->platform]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function as(?CounselorProfile $profile = null)
    {
        return $this->actingAs(($profile ?? $this->aina)->user)->withSession(['current_org_id' => $this->platform->id, 'current_role' => 'counselor']);
    }

    private function held(string $when, BookingStatus $status = BookingStatus::Completed, ?User $client = null, ?CounselorProfile $who = null): Booking
    {
        return $this->book($this->slot($who ?? $this->aina, $this->platform, $when), $client ?? User::factory()->create(['name' => 'Siti Client']), $status);
    }

    private function review(Booking $booking, int $rating = 5, ?string $comment = 'Very kind and calm.', array $tags = ['listened']): array
    {
        return app(ReviewService::class)->submit($booking->user, $booking->public_id, $rating, $tags, $comment);
    }

    private function refusal(callable $do): string
    {
        try {
            $do();
        } catch (BookingRefused $refused) {
            return $refused->reason;
        }

        return 'none';
    }

    // ---- the client's rules --------------------------------------------------------------------------------------

    public function test_a_completed_session_can_be_reviewed_from_the_day_after_it_once(): void
    {
        $today = $this->held('2026-11-02 07:00');                      // ended today
        $this->assertSame('review_not_open', $this->refusal(fn () => $this->review($today)));

        $yesterday = $this->held('2026-11-01 10:00');
        ['review' => $review, 'held' => $held] = $this->review($yesterday);

        $this->assertFalse($held);
        $this->assertTrue($review->attendance_confirmed);
        $this->assertSame('review_exists', $this->refusal(fn () => $this->review($yesterday)));
    }

    public function test_no_show_cancelled_and_other_peoples_sessions_can_not_be_reviewed(): void
    {
        $this->assertSame('review_not_open', $this->refusal(fn () => $this->review($this->held('2026-10-30 10:00', BookingStatus::NoShow))));
        $this->assertSame('review_not_open', $this->refusal(fn () => $this->review($this->held('2026-10-30 11:00', BookingStatus::Cancelled))));

        $theirs = $this->held('2026-10-30 12:00');
        $this->assertSame('booking_not_found', $this->refusal(fn () => app(ReviewService::class)->submit(User::factory()->create(), $theirs->public_id, 5, [], null)));
    }

    public function test_an_unmarked_session_can_be_reviewed_after_48_hours_as_attendance_not_confirmed(): void
    {
        $recent = $this->held('2026-10-31 10:00', BookingStatus::Confirmed);     // ended about 47 hours before the clock
        $older = $this->held('2026-10-30 10:00', BookingStatus::Confirmed);

        $this->assertSame('review_not_open', $this->refusal(fn () => $this->review($recent)));
        $this->assertFalse($this->review($older)['review']->attendance_confirmed);
    }

    public function test_the_window_closes_30_days_after_the_session_and_editing_7_days_after_writing(): void
    {
        $old = $this->held('2026-09-20 10:00');
        $this->assertSame('review_closed', $this->refusal(fn () => $this->review($old)));

        $booking = $this->held('2026-11-01 10:00');
        $review = $this->review($booking, 4)['review'];
        $service = app(ReviewService::class);

        $service->edit($booking->user, $review->public_id, 2, [], 'Changed my mind');
        $this->assertSame(2, $review->fresh()->rating);

        Carbon::setTestNow(now()->addDays(8));
        $this->assertSame('review_edit_closed', $this->refusal(fn () => $service->edit($booking->user, $review->public_id, 5, [], null)));
    }

    public function test_phone_numbers_emails_and_identity_numbers_are_removed_from_a_comment(): void
    {
        $review = $this->review($this->held('2026-11-01 10:00'), 5, 'Call me 012-345 6789 or a@b.com, IC 990101-14-5678, great help')['review'];

        $this->assertSame('Call me [removed] or [removed], IC [removed], great help', $review->comment);
    }

    public function test_a_comment_with_a_danger_phrase_is_held_but_the_stars_count(): void
    {
        ['review' => $review, 'held' => $held] = $this->review($this->held('2026-11-01 10:00'), 1, 'I felt unsafe in the room');

        $this->assertTrue($held);
        $this->assertSame(Review::HELD, $review->status);
        $this->assertSame(1, app(ReviewService::class)->summary($this->aina->user)['count']);

        $this->as()->get(route('counselor.reviews'))->assertOk()->assertSee('waiting for the MyKaunsel team')->assertDontSee('felt unsafe');
    }

    public function test_the_comment_is_stored_encrypted_and_the_audit_log_never_holds_words(): void
    {
        $this->review($this->held('2026-11-01 10:00'), 5, 'A private secret remark');

        $this->assertStringNotContainsString('secret remark', (string) DB::table('reviews')->value('comment'));
        $this->assertStringNotContainsString('secret remark', json_encode(AuditLog::all()->toArray()));
        $this->assertSame(1, AuditLog::where('action', 'review.submitted')->count());
    }

    // ---- the counselor's page ------------------------------------------------------------------------------------

    public function test_the_page_shows_my_reviews_without_any_client_name_and_not_anothers(): void
    {
        $bakar = $this->counselor('Bakar', [$this->platform]);
        $this->review($this->held('2026-11-01 10:00'), 5, 'Lovely session');
        $this->review($this->held('2026-11-01 11:00', who: $bakar), 1, 'Not for Aina');

        $page = $this->as()->get(route('counselor.reviews'))->assertOk();

        $page->assertSee('Lovely session')->assertSee('Felt listened to')->assertDontSee('Not for Aina')->assertDontSee('Siti Client');
        $this->assertSame(1, app(ReviewService::class)->summary($this->aina->user)['count']);
    }

    public function test_the_average_needs_five_reviews_to_count_as_public(): void
    {
        $service = app(ReviewService::class);

        foreach ([5, 4, 3, 5] as $i => $stars) {
            $this->review($this->held('2026-10-'.(20 + $i).' 10:00'), $stars, null, []);
        }

        $summary = $service->summary($this->aina->user);
        $this->assertSame(4, $summary['count']);
        $this->assertSame(4.3, $summary['average']);
        $this->assertFalse($summary['enough']);
        $this->as()->get(route('counselor.reviews'))->assertSee('only from 5 reviews (4 so far)');

        $this->review($this->held('2026-10-25 10:00'), 5, null, []);
        $this->assertTrue($service->summary($this->aina->user)['enough']);
    }

    public function test_one_reply_only_and_it_is_final(): void
    {
        $review = $this->review($this->held('2026-11-01 10:00'))['review'];

        $this->as()->post(route('counselor.reviews.reply', $review->public_id), ['reply' => 'Thank you.'])->assertSessionHas('status');
        $this->as()->post(route('counselor.reviews.reply', $review->public_id), ['reply' => 'Changed'])->assertSessionHasErrors('review');

        $this->assertSame('Thank you.', $review->fresh()->reply);
        $this->as()->get(route('counselor.reviews'))->assertSee('Thank you.')->assertDontSee('Publish reply');
    }

    public function test_a_review_can_be_flagged_once_and_it_stays_visible(): void
    {
        $review = $this->review($this->held('2026-11-01 10:00'), 1, 'Awful')['review'];

        $this->as()->post(route('counselor.reviews.flag', $review->public_id), ['reason' => 'false'])->assertSessionHas('status');
        $this->as()->post(route('counselor.reviews.flag', $review->public_id), ['reason' => 'abusive'])->assertSessionHasErrors('review');
        $this->as()->post(route('counselor.reviews.flag', $review->public_id), ['reason' => 'nonsense'])->assertSessionHasErrors('reason');

        $this->assertSame('open', $review->fresh()->flag_status);
        $this->assertSame('false', $review->fresh()->flag_reason);
        $this->as()->get(route('counselor.reviews'))->assertSee('Awful')->assertSee('being looked at');
    }

    public function test_someone_elses_review_is_a_404_and_a_removed_review_is_gone(): void
    {
        $bakar = $this->counselor('Bakar', [$this->platform]);
        $theirs = $this->review($this->held('2026-11-01 10:00', who: $bakar))['review'];
        $this->as()->post(route('counselor.reviews.reply', $theirs->public_id), ['reply' => 'Hi'])->assertNotFound();
        $this->as()->post(route('counselor.reviews.flag', $theirs->public_id), ['reason' => 'false'])->assertNotFound();

        $mine = $this->review($this->held('2026-11-01 11:00'))['review'];
        $mine->update(['status' => Review::REMOVED]);

        $this->assertSame(0, app(ReviewService::class)->summary($this->aina->user)['count']);
        $this->as()->get(route('counselor.reviews'))->assertDontSee('Very kind and calm.');
    }
}
