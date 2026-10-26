<?php

namespace Tests\Feature\Counselor;

use App\Models\CounselorProfile;
use App\Models\LkmDirectorySnapshot;

/** The Dashboard reads its counselor numbers from the same place as the Counselors page. */
class DashboardCounselorsTest extends CounselorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSamples();
    }

    private function dashboard()
    {
        return $this->as()->get(route('platform.dashboard'))->assertOk();
    }

    public function test_the_pending_counselors_card_matches_the_pending_tab(): void
    {
        $this->assertSame(4, $this->dashboard()->viewData('counts')['pending_counselors']);
        $this->assertSame(4, $this->listPage()->viewData('counts')['pending']);

        // An organization counselor sitting at "pending" needs no approval, so it is on neither.
        $this->profile('KB10234')->update(['verification_status' => 'pending']);

        $this->assertSame(4, $this->dashboard()->viewData('counts')['pending_counselors']);
        $this->assertSame(4, $this->listPage()->viewData('counts')['pending']);
    }

    public function test_pending_applications_link_straight_to_their_page_and_use_the_submitted_date(): void
    {
        $page = $this->dashboard();
        $oldest = $this->profile('KB11702'); // waiting 9 days

        $page->assertSee('Nur Ain binti Hashim — credentials awaiting verification')
            ->assertSee('Waiting 9 days')
            ->assertSee(route('platform.counselors.show', $oldest), false);
    }

    public function test_an_application_whose_lkm_record_no_longer_backs_it_up_says_so(): void
    {
        $this->dashboard()->assertDontSee('LKM number could not be verified');

        LkmDirectorySnapshot::where('kb_number', 'KB11023')->update(['status' => 'Digantung']);
        LkmDirectorySnapshot::where('kb_number', 'KB11489')->delete();

        $page = $this->dashboard();
        $page->assertSee('Kavitha a/p Muniandy — LKM number could not be verified')
            ->assertSee('Lee Mei Ling — LKM number could not be verified')
            ->assertDontSee('Kavitha a/p Muniandy — credentials awaiting verification', false);

        $this->assertSame(2, \App\Support\CounselorAttention::counts()['lkm_not_verified']);
    }

    public function test_a_duplicate_kb_attempt_shows_up_even_for_an_account_that_is_already_active(): void
    {
        $page = $this->dashboard();

        $page->assertSee('Tan Wei Ming — duplicate KB registration attempt')
            ->assertSee('Muhammad Haziq bin Ismail — duplicate KB registration attempt')
            ->assertSee('Last attempt 1 day ago')
            ->assertSee(route('platform.counselors.show', $this->profile('KB10891')), false);

        $this->assertSame(2, \App\Support\CounselorAttention::counts()['duplicate_kb']);
    }

    public function test_the_list_is_empty_only_when_there_is_really_nothing_to_look_at(): void
    {
        CounselorProfile::query()->where('verification_status', 'pending')->update(['verification_status' => 'approved']);
        \App\Models\CounselorKbAttempt::query()->delete();
        \App\Models\Organization::query()->where('subscription_status', 'pending')->update(['subscription_status' => 'active']);

        $this->dashboard()->assertSee("Nothing is waiting for review. You're all caught up.", false);
    }

    public function test_the_attention_badge_still_counts_only_the_four_cards(): void
    {
        $counts = $this->dashboard()->viewData('counts');

        $this->assertSame(['pending_orgs', 'pending_counselors', 'active_complaints', 'skipped_dns'], array_keys($counts));
    }
}
