<?php

namespace Tests\Feature\Platform;

use App\Models\Complaint;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\Feature\Counselor\CounselorTestCase;

/**
 * The Complaints list (reference search, the All tab, filed-between dates in the admin's own days, the three orders) and the
 * "Counselor history" card on a complaint (how many, from whom, how each ended, and the pattern notice).
 * The clock is fixed at 2026-10-27 10:00 UTC (18:00 in Kuala Lumpur).
 */
class ComplaintListAndHistoryTest extends CounselorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSamples();
        Complaint::query()->delete(); // the sample counselors come with a few complaints; these tests make their own
        Carbon::setTestNow(Carbon::parse('2026-10-27 10:00:00', 'UTC'));
    }

    private function reporter(string $name = 'Klien Contoh'): User
    {
        return User::firstWhere('email', 'klien.contoh@example.test') ?? User::factory()->create(['name' => $name]);
    }

    private function complaint(string $kb, string $status = 'open', ?string $filed = null, ?User $reporter = null, ?string $touched = null): Complaint
    {
        $c = new Complaint([
            'reporter_user_id' => ($reporter ?? $this->reporter())->id,
            'counselor_user_id' => $this->profile($kb)->user_id,
            'description' => "Private text of the complaint filed {$filed} about {$kb}.",
            'status' => $status,
        ]);
        $c->created_at = Carbon::parse($filed ?? '2026-10-20 08:00:00', 'UTC');
        $c->updated_at = Carbon::parse($touched ?? $filed ?? '2026-10-20 08:00:00', 'UTC');
        $c->save();

        return $c;
    }

    private function list(array $query = [])
    {
        return $this->as()->get(route('platform.complaints', $query))->assertOk();
    }

    private function ids(array $query = []): array
    {
        return $this->list($query)->viewData('complaints')->getCollection()->pluck('id')->all();
    }

    // ---- search by reference ---------------------------------------------------------------------------------

    public function test_the_reference_finds_a_complaint_as_12_hash_12_or_cmp_12(): void
    {
        $this->complaint('KB10234');
        $target = $this->complaint('KB10567');
        $other = $this->complaint('KB10891');
        $this->assertGreaterThan(2, $target->id);

        // "#12" and "CMP-12" can only mean the reference.
        foreach (["#{$target->id}", "CMP-{$target->id}", "cmp{$target->id}", "  #{$target->id}  ", "Cmp-{$target->id}"] as $term) {
            $this->assertSame([$target->id], $this->ids(['q' => $term, 'tab' => 'all']), $term);
        }

        // A plain number finds it too (and may also match a KB number or a name that contains it).
        $this->assertContains($target->id, $this->ids(['q' => (string) $target->id, 'tab' => 'all']));
        $this->assertNotContains($other->id, $this->ids(['q' => "#{$target->id}", 'tab' => 'all']));
    }

    public function test_a_reference_search_is_only_for_a_number_and_never_reads_the_text(): void
    {
        $c = $this->complaint('KB10234');

        $this->assertSame([], $this->ids(['q' => "{$c->id}abc", 'tab' => 'all']));
        $this->assertSame([], $this->ids(['q' => 'CMP-', 'tab' => 'all']));
        $this->assertSame([], $this->ids(['q' => 'Private text', 'tab' => 'all']));
        $this->assertSame([], $this->ids(['q' => '999999', 'tab' => 'all']));
    }

    public function test_a_number_can_still_match_a_name_and_the_reference_together(): void
    {
        $c = $this->complaint('KB10234');
        $this->profile('KB10234')->user->update(['name' => "Counselor {$c->id}"]);

        $this->assertSame([$c->id], $this->ids(['q' => (string) $c->id, 'tab' => 'all']));
        $this->list(['q' => "#{$c->id}", 'tab' => 'all'])->assertSee("#{$c->id}", false);
    }

    // ---- the All tab and the orders --------------------------------------------------------------------------------

    public function test_the_all_tab_counts_and_lists_every_status_and_open_is_still_the_default_tab(): void
    {
        $this->complaint('KB10234', 'open');
        $this->complaint('KB10234', 'under_review');
        $this->complaint('KB10234', 'resolved');
        $this->complaint('KB10234', 'dismissed');

        $page = $this->list();
        $this->assertSame('open', $page->viewData('tab'));
        $this->assertSame(['all' => 4, 'open' => 1, 'under_review' => 1, 'resolved' => 1, 'dismissed' => 1], $page->viewData('counts'));
        $page->assertSeeInOrder(['All', 'Open', 'Under review', 'Resolved', 'Dismissed']);

        $this->assertCount(4, $this->ids(['tab' => 'all']));
        $this->list(['tab' => 'all'])->assertSee('aria-current="page"', false);
    }

    public function test_most_urgent_puts_what_still_needs_a_decision_first_longest_waiting_first_then_the_closed_ones(): void
    {
        $oldOpen = $this->complaint('KB10234', 'open', '2026-10-01 08:00:00');
        $newerOpen = $this->complaint('KB10234', 'open', '2026-10-15 08:00:00');
        $review = $this->complaint('KB10234', 'under_review', '2026-10-10 08:00:00');
        $resolvedRecently = $this->complaint('KB10234', 'resolved', '2026-09-01 08:00:00', touched: '2026-10-26 08:00:00');
        $dismissedEarlier = $this->complaint('KB10234', 'dismissed', '2026-10-05 08:00:00', touched: '2026-10-12 08:00:00');

        $this->assertSame(
            [$oldOpen->id, $review->id, $newerOpen->id, $resolvedRecently->id, $dismissedEarlier->id],
            $this->ids(['tab' => 'all']),
            'open and under review, longest waiting first; then closed, most recently touched first',
        );
        $this->assertSame([$oldOpen->id, $newerOpen->id], $this->ids(['tab' => 'open']));
        $this->assertSame([$resolvedRecently->id], $this->ids(['tab' => 'resolved']));
    }

    public function test_newest_and_oldest_order_by_the_day_filed_whatever_the_status(): void
    {
        $a = $this->complaint('KB10234', 'resolved', '2026-10-01 08:00:00');
        $b = $this->complaint('KB10234', 'open', '2026-10-10 08:00:00');
        $c = $this->complaint('KB10234', 'dismissed', '2026-10-20 08:00:00');

        $this->assertSame([$c->id, $b->id, $a->id], $this->ids(['tab' => 'all', 'sort' => 'newest']));
        $this->assertSame([$a->id, $b->id, $c->id], $this->ids(['tab' => 'all', 'sort' => 'oldest']));
    }

    public function test_the_order_is_in_the_url_only_when_it_is_not_the_default_and_survives_tabs_and_pages(): void
    {
        foreach (range(1, 12) as $i) {
            $this->complaint('KB10234', 'open', "2026-10-{$i} 08:00:00");
        }

        $html = $this->list(['sort' => 'newest'])->assertSee('Order: Newest')->getContent();
        $this->assertStringContainsString('sort=newest', $html, 'kept in the tab links and the page links');
        $this->assertStringNotContainsString('sort=urgent', $this->list()->getContent());
        $this->assertSame(2, $this->list(['sort' => 'newest', 'page' => 2])->viewData('complaints')->count());
    }

    public function test_an_unknown_order_or_tab_is_ignored_and_explained(): void
    {
        $this->complaint('KB10234');

        $this->list(['sort' => 'random', 'tab' => 'nonsense'])->assertSee('Unknown order.')->assertSee('Unknown tab.')->assertSee('Some filters in the link were not valid');
    }

    // ---- filed between ---------------------------------------------------------------------------------------------

    public function test_filed_from_and_to_are_the_admins_own_days_both_ends_included(): void
    {
        // 16:30 UTC on the 26th is 00:30 on the 27th in Kuala Lumpur.
        $lateNight = $this->complaint('KB10234', 'open', '2026-10-26 16:30:00');
        $before = $this->complaint('KB10234', 'open', '2026-10-26 15:30:00');   // 23:30 on the 26th in Kuala Lumpur
        $endOfDay = $this->complaint('KB10234', 'open', '2026-10-27 15:59:00');  // 23:59 on the 27th
        $after = $this->complaint('KB10234', 'open', '2026-10-27 16:01:00');     // 00:01 on the 28th

        $this->assertEqualsCanonicalizing([$lateNight->id, $endOfDay->id], $this->ids(['tab' => 'all', 'from' => '2026-10-27', 'to' => '2026-10-27']));
        $this->assertEqualsCanonicalizing([$before->id, $lateNight->id, $endOfDay->id, $after->id], $this->ids(['tab' => 'all', 'from' => '2026-10-26', 'to' => '2026-10-28']));
        $this->assertEqualsCanonicalizing([$before->id], $this->ids(['tab' => 'all', 'to' => '2026-10-26']));
        $this->assertEqualsCanonicalizing([$after->id], $this->ids(['tab' => 'all', 'from' => '2026-10-28']));
    }

    public function test_the_dates_narrow_the_tab_counts_too_and_count_as_a_filter(): void
    {
        $this->complaint('KB10234', 'open', '2026-10-05 08:00:00');
        $this->complaint('KB10234', 'resolved', '2026-10-25 08:00:00');

        $page = $this->list(['from' => '2026-10-20']);

        $this->assertSame(['all' => 1, 'open' => 0, 'under_review' => 0, 'resolved' => 1, 'dismissed' => 0], $page->viewData('counts'));
        $page->assertSee('Clear filters')->assertSee('2026-10-20');
    }

    public function test_a_bad_date_or_a_range_the_wrong_way_round_is_ignored_and_explained(): void
    {
        $this->complaint('KB10234', 'open', '2026-10-30 08:00:00');

        $this->list(['from' => '27/10/2026'])->assertSee('must look like 2026-10-27');
        $this->list(['from' => '2026-10-28', 'to' => '2026-10-20'])->assertSee('cannot be before the "from" date');
        $this->assertCount(1, $this->ids(['from' => '2026-10-28', 'to' => '2026-10-20']), 'the bad "to" is dropped, "from" is kept, nothing breaks');
        $this->assertCount(1, $this->ids(['from' => '27/10/2026']), 'the bad date is dropped');
    }

    public function test_the_filters_combine(): void
    {
        $a = $this->complaint('KB10234', 'open', '2026-10-25 08:00:00');
        $this->complaint('KB10567', 'open', '2026-10-25 08:00:00');
        $this->complaint('KB10234', 'open', '2026-09-01 08:00:00');

        $this->assertSame([$a->id], $this->ids(['q' => 'KB10234', 'from' => '2026-10-20', 'tab' => 'all']));
    }

    // ---- the history card ------------------------------------------------------------------------------------------

    private function show(Complaint $c)
    {
        return $this->as()->get(route('platform.complaints.show', $c))->assertOk();
    }

    public function test_the_history_card_says_how_many_from_how_many_people_and_how_each_ended(): void
    {
        $second = User::factory()->create(['name' => 'Second Reporter']);
        $this->complaint('KB10234', 'dismissed', '2026-08-01 08:00:00');
        $this->complaint('KB10234', 'resolved', '2026-09-01 08:00:00', $second);
        $c = $this->complaint('KB10234', 'open', '2026-10-20 08:00:00');

        $this->show($c)->assertSee('Counselor history')->assertSeeText('3 complaints, 2 reporters, 1 open, 1 resolved, 1 dismissed');
    }

    public function test_a_counselor_with_one_complaint_has_a_one_line_history(): void
    {
        $c = $this->complaint('KB10234', 'under_review');

        $this->show($c)->assertSeeText('1 complaint, 1 reporter, 1 under review')->assertSee('No other complaints about this counselor.')->assertDontSee('Pattern:');
    }

    public function test_the_other_complaints_are_listed_with_a_link_date_and_status_but_never_their_text(): void
    {
        $old = $this->complaint('KB10234', 'dismissed', '2026-08-01 08:00:00');
        $c = $this->complaint('KB10234', 'open', '2026-10-20 08:00:00');
        $unrelated = $this->complaint('KB10567', 'open', '2026-10-20 08:00:00');

        $html = $this->show($c)->assertSee("Complaint #{$old->id}")->assertSee(route('platform.complaints.show', $old), false)->assertSee('filed 1 Aug 2026')->getContent();

        $this->assertStringNotContainsString($old->description, $html, 'another complaint\'s text is not on this page');
        $this->assertStringNotContainsString("Complaint #{$unrelated->id}", $html);
        $this->assertStringContainsString($c->description, $html, 'this complaint\'s own text, as before');
    }

    public function test_three_complaints_within_90_days_show_the_pattern_notice_and_two_do_not(): void
    {
        $this->complaint('KB10234', 'dismissed', '2026-09-01 08:00:00');
        $c = $this->complaint('KB10234', 'open', '2026-10-20 08:00:00');
        $this->show($c)->assertDontSee('Pattern:');

        $this->complaint('KB10234', 'open', '2026-10-25 08:00:00');
        $this->show($c)->assertSee('Pattern:')->assertSeeText('3 complaints about this counselor in the last 90 days')->assertSee('nothing happens automatically');
    }

    public function test_complaints_older_than_90_days_do_not_count_towards_the_pattern(): void
    {
        $this->complaint('KB10234', 'dismissed', '2026-06-01 08:00:00');   // 148 days ago
        $this->complaint('KB10234', 'resolved', '2026-07-01 08:00:00');    // 118 days ago
        $c = $this->complaint('KB10234', 'open', '2026-10-20 08:00:00');

        $this->show($c)->assertDontSee('Pattern:')->assertSeeText('3 complaints, 1 reporter');
    }

    public function test_the_pattern_threshold_and_window_are_in_configuration(): void
    {
        $this->assertSame(['count' => 3, 'days' => 90], config('complaints.pattern'));

        config(['complaints.pattern' => ['count' => 2, 'days' => 10]]);
        $this->complaint('KB10234', 'dismissed', '2026-10-22 08:00:00');
        $c = $this->complaint('KB10234', 'open', '2026-10-25 08:00:00');

        $this->show($c)->assertSee('Pattern:')->assertSeeText('2 complaints about this counselor in the last 10 days')->assertSee('starts at 2');
    }

    public function test_the_pattern_is_only_a_notice_and_changes_nothing(): void
    {
        $a = $this->complaint('KB10234', 'open', '2026-10-20 08:00:00');
        $this->complaint('KB10234', 'open', '2026-10-21 08:00:00');
        $this->complaint('KB10234', 'open', '2026-10-22 08:00:00');
        $status = $this->profile('KB10234')->verification_status;

        $this->show($a)->assertSee('Pattern:');

        $this->assertSame($status, $this->profile('KB10234')->fresh()->verification_status);
        $this->assertSame(3, Complaint::where('status', 'open')->count());
    }
}
