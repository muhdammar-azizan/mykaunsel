<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\BookingRefused;
use App\Facades\Audit;
use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Client reviews of counselors (docs/REVIEWS.md).
 *
 * The client's side
 * - One review per session, only for the client's own session. A completed session; or a confirmed one that is over and that the counselor has
 *   not marked within `unconfirmed_after_hours` ("attendance not confirmed"). Never a cancelled session or a no-show.
 * - It opens the day after the session (a Malaysian day), stays open `window_days` after the session, and can be edited `edit_days` after it was written.
 * - Phone numbers, e-mail addresses and identity card numbers in a comment are removed. A comment with a danger phrase is HELD: the stars count at once,
 *   the words are shown to nobody until the MyKaunsel team has read them, and the caller shows the helpline numbers.
 *
 * The counselor's side
 * - Reviews without the client's name or id. One reply per review (final), and one flag per review for the MyKaunsel team.
 * - Everything is the counselor's OWN reviews: another counselor's review is the same `review_not_found`.
 */
class ReviewService
{
    public function __construct(private readonly AvailabilityService $availability) {}

    // =============================================================================================================
    //  The client
    // =============================================================================================================

    /**
     * @param  list<string>  $tags  keys of config('reviews.tags')
     * @return array{review: Review, held: bool}
     *
     * @throws BookingRefused booking_not_found | review_not_open | review_closed | review_exists | review_invalid
     */
    public function submit(User $client, string $bookingPublicId, int $rating, array $tags, ?string $comment): array
    {
        $this->check($rating, $tags);

        return DB::transaction(function () use ($client, $bookingPublicId, $rating, $tags, $comment) {
            $booking = Booking::query()->with('calendarEntry')->where('user_id', $client->getKey())->where('public_id', strtolower($bookingPublicId))
                ->whereNotNull('calendar_entry_id')->lockForUpdate()->first() ?? throw new BookingRefused('booking_not_found');

            if (Review::query()->where('booking_id', $booking->getKey())->exists()) {
                throw new BookingRefused('review_exists');
            }

            $confirmed = $this->assertReviewable($booking);
            [$text, $held] = $this->prepare($comment);

            $review = Review::create([
                'booking_id' => $booking->getKey(),
                'counselor_user_id' => $booking->counselor_user_id,
                'client_user_id' => $client->getKey(),
                'rating' => $rating,
                'tags' => array_values(array_unique($tags)),
                'comment' => $text,
                'attendance_confirmed' => $confirmed,
                'status' => $held ? Review::HELD : Review::PUBLISHED,
            ]);

            Audit::log('review.submitted', $review, ['review_id' => $review->getKey(), 'booking_id' => $booking->getKey(), 'held' => $held], $client);

            return ['review' => $review, 'held' => $held];
        });
    }

    /**
     * Changes the client's own review, for `edit_days` after it was written.
     *
     * @param  list<string>  $tags
     * @return array{review: Review, held: bool}
     *
     * @throws BookingRefused review_not_found | review_edit_closed | review_invalid
     */
    public function edit(User $client, string $reviewPublicId, int $rating, array $tags, ?string $comment): array
    {
        $this->check($rating, $tags);

        return DB::transaction(function () use ($client, $reviewPublicId, $rating, $tags, $comment) {
            $review = Review::query()->where('client_user_id', $client->getKey())->where('public_id', strtolower($reviewPublicId))->lockForUpdate()->first()
                ?? throw new BookingRefused('review_not_found');

            if ($review->status === Review::REMOVED || $review->created_at->lt(now()->subDays((int) config('reviews.edit_days')))) {
                throw new BookingRefused('review_edit_closed');
            }

            [$text, $held] = $this->prepare($comment);

            $review->update(['rating' => $rating, 'tags' => array_values(array_unique($tags)), 'comment' => $text, 'status' => $held ? Review::HELD : Review::PUBLISHED]);

            Audit::log('review.edited', $review, ['review_id' => $review->getKey(), 'held' => $held], $client);

            return ['review' => $review, 'held' => $held];
        });
    }

    // =============================================================================================================
    //  The counselor
    // =============================================================================================================

    /** @return array{count: int, average: float|null, enough: bool, needed: int, distribution: array<int, int>} */
    public function summary(User $counselor): array
    {
        $rows = Review::query()->counted()->where('counselor_user_id', $counselor->getKey())
            ->selectRaw('rating, COUNT(*) as total')->groupBy('rating')->pluck('total', 'rating');

        $count = (int) $rows->sum();
        $distribution = [];

        foreach ([5, 4, 3, 2, 1] as $stars) {
            $distribution[$stars] = (int) ($rows[$stars] ?? 0);
        }

        $sum = $rows->reduce(fn (int $carry, $total, $stars) => $carry + ((int) $stars * (int) $total), 0);
        $needed = (int) config('reviews.min_for_average');

        return [
            'count' => $count,
            'average' => $count > 0 ? round($sum / $count, 1) : null,
            'enough' => $count >= $needed,
            'needed' => $needed,
            'distribution' => $distribution,
        ];
    }

    /** The counselor's reviews, newest first, without the client's name or id. */
    public function forCounselor(User $counselor, int $page): LengthAwarePaginator
    {
        $paginator = Review::query()->counted()->where('counselor_user_id', $counselor->getKey())
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate((int) config('reviews.per_page'), ['*'], 'page', max(1, $page));

        return $paginator->through(fn (Review $review) => [
            'id' => $review->public_id,
            'rating' => $review->rating,
            'tags' => collect($review->tags ?? [])->map(fn ($key) => config('reviews.tags')[$key] ?? null)->filter()->values()->all(),
            'comment_held' => $review->status === Review::HELD,
            'comment' => $review->status === Review::HELD ? null : $review->comment,
            'attendance_confirmed' => $review->attendance_confirmed,
            'date' => $review->created_at->setTimezone($this->availability->timezone())->format('j M Y'),
            'reply' => $review->reply,
            'replied_on' => $review->replied_at?->setTimezone($this->availability->timezone())->format('j M Y'),
            'can_reply' => $review->reply === null && $review->status === Review::PUBLISHED,
            'flag_status' => $review->flag_status,
            'flag_reason' => $review->flag_reason === null ? null : (config('reviews.flag_reasons')[$review->flag_reason] ?? null),
        ]);
    }

    /**
     * One public reply per review, final. Only to a review whose words have been seen (not a held one).
     *
     * @throws BookingRefused review_not_found | reply_exists | reply_closed
     */
    public function reply(User $counselor, string $reviewPublicId, string $text): void
    {
        DB::transaction(function () use ($counselor, $reviewPublicId, $text) {
            $review = $this->own($counselor, $reviewPublicId);

            if ($review->reply !== null) {
                throw new BookingRefused('reply_exists');
            }

            if ($review->status !== Review::PUBLISHED) {
                throw new BookingRefused('reply_closed');
            }

            $review->update(['reply' => trim($text), 'replied_at' => now()]);

            Audit::log('review.replied', $review, ['review_id' => $review->getKey()], $counselor);
        });
    }

    /**
     * Tells the MyKaunsel team that a review looks unfair (once per review). It stays visible until the team decides.
     *
     * @throws BookingRefused review_not_found | flag_exists | review_invalid
     */
    public function flag(User $counselor, string $reviewPublicId, string $reason): void
    {
        if (! array_key_exists($reason, config('reviews.flag_reasons'))) {
            throw new BookingRefused('review_invalid');
        }

        DB::transaction(function () use ($counselor, $reviewPublicId, $reason) {
            $review = $this->own($counselor, $reviewPublicId);

            if ($review->flagged_at !== null) {
                throw new BookingRefused('flag_exists');
            }

            $review->update(['flagged_at' => now(), 'flag_reason' => $reason, 'flag_status' => 'open']);

            Audit::log('review.flagged', $review, ['review_id' => $review->getKey(), 'reason' => $reason], $counselor);
        });
    }

    // =============================================================================================================

    private function own(User $counselor, string $publicId): Review
    {
        return Review::query()->counted()->where('counselor_user_id', $counselor->getKey())->where('public_id', strtolower($publicId))->lockForUpdate()->first()
            ?? throw new BookingRefused('review_not_found');
    }

    /** @param  list<string>  $tags */
    private function check(int $rating, array $tags): void
    {
        if ($rating < 1 || $rating > 5 || array_diff($tags, array_keys(config('reviews.tags'))) !== []) {
            throw new BookingRefused('review_invalid');
        }
    }

    /**
     * Is this session open for a review right now? Returns whether the counselor confirmed that it took place.
     *
     * @throws BookingRefused review_not_open | review_closed
     */
    private function assertReviewable(Booking $booking): bool
    {
        $entry = $booking->calendarEntry;
        $end = $this->availability->startOf($entry)->addMinutes((int) $entry->duration_minutes);
        $now = $this->availability->now();

        $confirmed = match (true) {
            $booking->status === BookingStatus::Completed => true,
            $booking->status === BookingStatus::Confirmed && $end->addHours((int) config('reviews.unconfirmed_after_hours'))->lte($now) => false,
            default => throw new BookingRefused('review_not_open'),
        };

        $opens = CarbonImmutable::instance($end)->startOfDay()->addDay();

        if ($now->lt($opens)) {
            throw new BookingRefused('review_not_open');
        }

        if ($now->gt($end->addDays((int) config('reviews.window_days')))) {
            throw new BookingRefused('review_closed');
        }

        return $confirmed;
    }

    /**
     * Cleans a comment and decides whether it is held.
     *
     * @return array{0: string|null, 1: bool}
     */
    private function prepare(?string $comment): array
    {
        $text = trim((string) $comment);

        if ($text === '') {
            return [null, false];
        }

        $text = mb_substr($text, 0, (int) config('reviews.comment_max'));

        // Identity card numbers (YYMMDD-PB-####), e-mail addresses, and phone numbers (a run of 8 or more digits with spaces or dashes) go.
        $text = preg_replace('/\b\d{6}[-\s]?\d{2}[-\s]?\d{4}\b/', '[removed]', $text);
        $text = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[removed]', $text);
        $text = preg_replace('/(?<!\w)\+?\d(?:[\s\-]?\d){7,}(?!\w)/', '[removed]', $text);

        $lower = mb_strtolower($text);
        $held = collect(config('reviews.danger_phrases'))->contains(fn (string $phrase) => (bool) preg_match('/(?<![\p{L}\p{N}])'.preg_quote($phrase, '/').'(?![\p{L}\p{N}])/u', $lower));

        return [$text, $held];
    }
}
