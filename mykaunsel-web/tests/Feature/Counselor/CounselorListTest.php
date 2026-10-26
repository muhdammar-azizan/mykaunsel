<?php

namespace Tests\Feature\Counselor;

use App\Models\CounselorProfile;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;

class CounselorListTest extends CounselorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSamples();
    }

    public function test_only_platform_admins_can_open_the_page(): void
    {
        $orgAdmin = User::where('email', 'afif@umpsa.edu.my')->firstOrFail();
        $umpsa = Organization::where('name', 'like', 'Universiti Malaysia Pahang%')->firstOrFail();

        $this->flushSession();
        $this->actingAs($orgAdmin)->withSession(['current_org_id' => $umpsa->id, 'current_role' => 'org_admin'])
            ->get(route('platform.counselors'))->assertForbidden();

        $this->flushSession();
        $counselor = User::where('email', 'ahmad.zulkarnain@umpsa.edu.my')->firstOrFail();
        $this->actingAs($counselor)->withSession(['current_org_id' => $umpsa->id, 'current_role' => 'counselor'])
            ->get(route('platform.counselors'))->assertForbidden();
        $this->actingAs($counselor)->withSession(['current_org_id' => $umpsa->id, 'current_role' => 'counselor'])
            ->get(route('platform.counselors.show', $this->profile('KB10234')))->assertForbidden();

        $this->flushSession();
        auth()->logout();
        $this->get(route('platform.counselors'))->assertRedirect();
    }

    public function test_tabs_show_live_counts(): void
    {
        $this->assertSame(['pending' => 4, 'active' => 5, 'suspended' => 2, 'rejected' => 1], $this->listPage()->viewData('counts'));

        $this->listPage()->assertOk()->assertSee('Counselor status')->assertSee('Pending')->assertSee('Active')->assertSee('Suspended')->assertSee('Rejected');
    }

    public function test_pending_is_oldest_first_and_active_is_soonest_pa_expiry_first(): void
    {
        $this->assertSame(['KB11702', 'KB11256', 'KB11489', 'KB11023'], $this->kbs($this->listPage()), 'waiting 9, 6, 3, 2 days');
        $this->assertSame(['KB11935', 'KB10234', 'KB10567', 'KB10891', 'KB14245'], $this->kbs($this->listPage(['tab' => 'active'])));
    }

    public function test_organization_counselors_only_appear_under_active_and_suspended(): void
    {
        $orgCounselors = ['KB10234', 'KB10567', 'KB14245'];

        $this->assertEmpty(array_intersect($orgCounselors, $this->kbs($this->listPage())));
        $this->assertEmpty(array_intersect($orgCounselors, $this->kbs($this->listPage(['tab' => 'rejected']))));
        $this->assertEqualsCanonicalizing($orgCounselors, array_intersect($orgCounselors, $this->kbs($this->listPage(['tab' => 'active']))));

        // Even if one were (wrongly) marked pending, there is no approval flow for them here.
        $this->profile('KB10234')->update(['verification_status' => 'pending']);
        $this->assertNotContains('KB10234', $this->kbs($this->listPage()));
    }

    public function test_search_by_name_kb_number_or_email(): void
    {
        $this->assertSame(['KB11489'], $this->kbs($this->listPage(['q' => 'Lee Mei'])));
        $this->assertSame(['KB11489'], $this->kbs($this->listPage(['q' => 'kb11489'])), 'KB number, any case');
        $this->assertSame(['KB11489'], $this->kbs($this->listPage(['q' => 'lee-mei-ling@example'])), 'email');
        $this->assertSame([], $this->kbs($this->listPage(['q' => 'nobody-like-this'])));
        $this->assertSame([], $this->kbs($this->listPage(['q' => '%'])), '"%" is not a wildcard');
        $this->assertSame([], $this->kbs($this->listPage(['q' => 'KB1_489'])), '"_" is not a wildcard');
    }

    public function test_the_search_applies_to_the_tab_counts_too(): void
    {
        $counts = $this->listPage(['q' => 'KB12'])->viewData('counts');

        $this->assertSame(['pending' => 0, 'active' => 0, 'suspended' => 1, 'rejected' => 1], $counts, 'KB12168 suspended and KB12391 rejected');
    }

    public function test_filter_by_type_and_organization(): void
    {
        $umpsa = Organization::where('name', 'like', 'Universiti Malaysia Pahang%')->firstOrFail();
        $klinik = Organization::where('name', 'Klinik Kaunseling Damai')->firstOrFail();
        $tab = fn (array $q) => $this->kbs($this->listPage(['tab' => 'active'] + $q));

        $this->assertEqualsCanonicalizing(['KB10891', 'KB11935'], $tab(['type' => 'independent']));
        $this->assertEqualsCanonicalizing(['KB10234', 'KB10567', 'KB14245'], $tab(['type' => 'organization']));
        $this->assertEqualsCanonicalizing(['KB10234', 'KB10567'], $tab(['type' => 'organization:'.$umpsa->id]));
        $this->assertSame(['KB14245'], $tab(['type' => 'organization:'.$klinik->id]));
        $this->assertSame([], $tab(['type' => 'organization:999999']));

        $response = $this->listPage(['tab' => 'active']);
        $this->assertEqualsCanonicalizing([$umpsa->name, $klinik->name], $response->viewData('organizations')->values()->all());
        $response->assertSee('Organization: '.$klinik->name);
    }

    public function test_filter_by_pa_status(): void
    {
        $this->assertSame(['KB11935'], $this->kbs($this->listPage(['tab' => 'active', 'pa' => 'expiring'])), 'runs out in 12 days');
        $this->assertSame(['KB13090'], $this->kbs($this->listPage(['tab' => 'suspended', 'pa' => 'expired'])));
        $this->assertSame(['KB12168'], $this->kbs($this->listPage(['tab' => 'suspended', 'pa' => 'valid'])));
        $this->assertCount(5, $this->kbs($this->listPage(['tab' => 'active', 'pa' => 'valid'])));
        $this->assertSame([], $this->kbs($this->listPage(['tab' => 'active', 'pa' => 'expired'])));
    }

    public function test_filter_flagged_means_a_duplicate_kb_attempt(): void
    {
        $this->assertSame(['KB11256'], $this->kbs($this->listPage(['flagged' => 1])));
        $this->assertSame(['KB10891'], $this->kbs($this->listPage(['tab' => 'active', 'flagged' => 1])));
    }

    public function test_filters_combine(): void
    {
        $this->assertSame(['KB10891'], $this->kbs($this->listPage(['tab' => 'active', 'type' => 'independent', 'flagged' => 1, 'pa' => 'valid', 'q' => 'tan'])));
        $this->assertSame([], $this->kbs($this->listPage(['tab' => 'active', 'type' => 'organization', 'flagged' => 1])));
    }

    public function test_invalid_filters_are_ignored_and_explained(): void
    {
        $response = $this->listPage(['tab' => 'hacked', 'type' => 'everyone', 'pa' => 'forever', 'q' => str_repeat('a', 101)]);

        $response->assertOk()
            ->assertSee('Some filters in the link were not valid and were ignored')
            ->assertSee('Unknown tab.')->assertSee('Unknown counselor type.')->assertSee('Unknown PA status.');
        $this->assertSame('pending', $response->viewData('tab'));
        $this->assertCount(4, $this->kbs($response), 'nothing was filtered by the bad values');
        $this->listPage(['q' => ['array']])->assertOk();
    }

    public function test_pending_cards_show_waiting_time_lkm_result_documents_and_badges(): void
    {
        $page = $this->listPage()->assertOk();

        $page->assertSee('Waiting 9 days')->assertSee('Waiting 6 days')->assertSee('Waiting 3 days')->assertSee('Waiting 2 days')
            ->assertSee('Resubmitted')
            ->assertSee('Duplicate KB attempt')
            ->assertSee('Matches LKM')
            ->assertSee('Does not fully match LKM')       // Nur Ain: the LKM date changed after she applied
            ->assertSee('Needs replacement')              // Haziq's ID
            ->assertSee('KB certificate:', false)->assertSee('PA certificate:', false)->assertSee('ID / passport:', false)
            ->assertSee('PA valid until')
            ->assertSee(route('platform.counselors.show', $this->profile('KB11023')), false);

        // Tones follow the same thresholds as organizations: amber from 5 days, red from 8.
        $this->assertSame(['red', 'amber', 'ok', 'ok'], collect($this->kbs($page))->map(fn ($kb) => $this->profile($kb)->waitingTone())->all());
    }

    public function test_active_table_shows_pa_colours_open_complaints_and_the_approved_date(): void
    {
        $page = $this->listPage(['tab' => 'active'])->assertOk();

        $page->assertSee('Open complaints')->assertSee('KB10891')->assertSee('Independent')
            ->assertSee('Universiti Malaysia Pahang')->assertSee('Klinik Kaunseling Damai')
            ->assertSee('Expires in 12 days', false); // the amber PA badge's tooltip

        $profiles = $page->viewData('profiles')->getCollection()->keyBy('kb_number');
        $this->assertSame(1, $profiles['KB10891']->open_complaints_count, 'one open, one resolved complaint on Tan Wei Ming');
        $this->assertSame(0, $profiles['KB11935']->open_complaints_count);
    }

    public function test_suspended_tab_shows_reason_date_who_and_source(): void
    {
        $page = $this->listPage(['tab' => 'suspended'])->assertOk();

        $page->assertSee('Practising certificate expired on 30 Nov 2025.')
            ->assertSee('System (PA expired)')
            ->assertSee('by System')
            ->assertSee('Two clients reported unprofessional conduct')
            ->assertSee('Complaint')
            ->assertSee('by Platform Administrator');
        $this->assertSame(['KB12168', 'KB13090'], $this->kbs($page), 'newest suspension first');
    }

    public function test_rejected_tab_shows_reason_flagged_documents_and_attempts(): void
    {
        $this->listPage(['tab' => 'rejected'])->assertOk()
            ->assertSee('does not match the name on the LKM record')
            ->assertSee('Documents to replace')->assertSee('KB certificate')
            ->assertSee('Attempt 1')
            ->assertSee('by Platform Administrator');
    }

    public function test_pagination_and_empty_states(): void
    {
        $this->assertSame(['pending' => 4, 'active' => 5, 'suspended' => 2, 'rejected' => 1], $this->listPage()->viewData('counts'));

        // 12 more pending applicants → two pages.
        $template = $this->profile('KB11023');
        foreach (range(1, 12) as $i) {
            $user = User::factory()->create();
            Membership::create(['user_id' => $user->id, 'org_id' => Organization::where('org_type', 'platform')->value('id'), 'role' => 'counselor', 'status' => 'active', 'joined_at' => now()]);
            CounselorProfile::create(['user_id' => $user->id, 'kb_number' => 'KB9'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'pa_number' => 'PA9'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'pa_valid_until' => now()->addYear(), 'verification_type' => 'platform_verified', 'verification_status' => 'pending']);
        }
        $this->assertCount(10, $this->kbs($this->listPage()));
        $this->assertCount(6, $this->kbs($this->listPage(['page' => 2])));
        $this->listPage(['page' => 9])->assertRedirect(route('platform.counselors', ['page' => 2]));

        // Empty states.
        CounselorProfile::query()->where('verification_status', 'rejected')->update(['verification_status' => 'pending']);
        $this->listPage(['tab' => 'rejected'])->assertSee('No rejected applications');
        $this->listPage(['tab' => 'suspended', 'q' => 'zzz'])->assertSee('No counselors match your search')->assertDontSee('No suspended counselors');
        CounselorProfile::query()->update(['verification_status' => 'approved']);
        $this->listPage(['tab' => 'suspended'])->assertSee('No suspended counselors');
        $this->listPage()->assertSee('No applications waiting');
        $template->refresh();
    }

    public function test_the_sidebar_link_works_and_the_old_placeholder_is_gone(): void
    {
        $this->listPage()->assertDontSee('Counselor verification and management will appear here');
        $this->as()->get(route('platform.dashboard'))->assertOk()->assertSee(route('platform.counselors'), false);
    }
}
