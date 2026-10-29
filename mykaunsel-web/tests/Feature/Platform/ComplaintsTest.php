<?php

namespace Tests\Feature\Platform;

use App\Enums\ComplaintStatus;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintReviewNote;
use App\Models\Organization;
use App\Models\User;
use App\Services\ComplaintTimeline;
use Database\Seeders\ComplaintReviewSeeder;
use Tests\Feature\Counselor\CounselorTestCase;

class ComplaintsTest extends CounselorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSamples();
        $this->seed(ComplaintReviewSeeder::class);
    }

    private function listing(array $query = [])
    {
        return $this->as()->get(route('platform.complaints', $query))->assertOk();
    }

    /** @return list<string> the first words of each complaint's text, in the order shown */
    private function texts(array $query = []): array
    {
        $ids = $this->listing($query)->viewData('complaints')->getCollection()->pluck('id');

        return $ids->map(fn ($id) => Str::of(Complaint::find($id)->description)->limit(40, '')->value())->all();
    }

    private function ids(array $query = []): array
    {
        return $this->listing($query)->viewData('complaints')->getCollection()->pluck('id')->all();
    }

    private function about(string $kb)
    {
        return Complaint::where('counselor_user_id', $this->profile($kb)->user_id);
    }

    private function find(string $needle): Complaint
    {
        return Complaint::where('description', 'like', "%{$needle}%")->firstOrFail();
    }

    private function show(Complaint $complaint)
    {
        return $this->as()->get(route('platform.complaints.show', $complaint))->assertOk();
    }

    // ---- access ------------------------------------------------------------------------------

    public function test_only_platform_admins_can_see_complaints(): void
    {
        $complaint = $this->find('20 minutes late');
        $orgAdmin = User::where('email', 'afif@umpsa.edu.my')->firstOrFail();
        $umpsa = Organization::where('name', 'like', 'Universiti Malaysia Pahang%')->firstOrFail();

        foreach ([
            fn ($t) => $t->get(route('platform.complaints')),
            fn ($t) => $t->get(route('platform.complaints.show', $complaint)),
            fn ($t) => $t->post(route('platform.complaints.notes.store', $complaint), ['body' => 'Nota dari luar']),
        ] as $call) {
            $this->flushSession();
            $call($this->actingAs($orgAdmin)->withSession(['current_org_id' => $umpsa->id, 'current_role' => 'org_admin']))->assertForbidden();
        }

        // Not even the counselor it is about.
        $this->flushSession();
        $counselor = $this->profile('KB10234')->user;
        $this->actingAs($counselor)->withSession(['current_org_id' => $umpsa->id, 'current_role' => 'counselor'])->get(route('platform.complaints.show', $complaint))->assertForbidden();

        $this->flushSession();
        auth()->logout();
        $this->get(route('platform.complaints'))->assertRedirect();
        $this->assertSame(1, ComplaintReviewNote::count(), 'only the seeded note: nobody outside added one');
    }

    // ---- list --------------------------------------------------------------------------------

    public function test_tabs_have_live_counts_and_the_dashboard_agrees(): void
    {
        $counts = $this->listing()->viewData('counts');

        $this->assertSame(['all' => 9, 'open' => 5, 'under_review' => 1, 'resolved' => 2, 'dismissed' => 1], $counts);
        $this->assertSame(6, $this->as()->get(route('platform.dashboard'))->viewData('data')['cards']['active_complaints']['count'], 'open + under review');
        $this->listing()->assertSee('Complaint status')->assertSee('Open')->assertSee('Under review')->assertSee('Resolved')->assertSee('Dismissed');
    }

    public function test_open_complaints_are_oldest_first_and_closed_ones_most_recently_touched_first(): void
    {
        $open = $this->listing()->viewData('complaints')->getCollection();

        $this->assertSame($open->sortBy('created_at')->pluck('id')->all(), $open->pluck('id')->all());
        $this->assertStringContainsString('20 minutes late', $this->find('20 minutes late')->description);
        $this->assertSame($this->find('20 minutes late')->id, $open->first()->id, 'the 9-day-old one first');

        $closed = $this->listing(['tab' => 'resolved'])->viewData('complaints')->getCollection();
        $this->assertSame($closed->sortByDesc('updated_at')->pluck('id')->all(), $closed->pluck('id')->all());
    }

    public function test_the_list_never_shows_or_even_loads_the_complaint_text(): void
    {
        $page = $this->listing();
        $page->assertDontSee('[Sample]')->assertDontSee('20 minutes late')->assertDontSee('<script>', false);

        $complaint = $page->viewData('complaints')->getCollection()->first();
        $this->assertArrayNotHasKey('description', $complaint->getAttributes());
    }

    public function test_rows_show_counselor_kb_reporter_session_age_and_status(): void
    {
        $this->listing()
            ->assertSee('Ahmad Zulkarnain bin Yusof')->assertSee('KB10234')
            ->assertSee('Pelajar Contoh (made up)')
            ->assertSee('Universiti Malaysia Pahang')
            ->assertSee('Klinik Kaunseling Damai')
            ->assertSee('Independent practice')
            ->assertSee('No session linked')
            ->assertSee('9 days')->assertSee('6 days')
            ->assertSee('Review →')
            ->assertSee(route('platform.complaints.show', $this->find('20 minutes late')), false);

        $this->listing(['tab' => 'resolved'])->assertSee('View →')->assertDontSee('days</span>', false);
    }

    public function test_waiting_time_tone_matches_the_applications(): void
    {
        $this->assertSame('red', $this->find('20 minutes late')->waitingTone());
        $this->assertSame('amber', $this->find('dismissive')->waitingTone());
        $this->assertSame('ok', $this->find('clinic charged')->waitingTone());
    }

    public function test_search_by_counselor_kb_or_reporter_but_not_by_the_text(): void
    {
        $this->assertEqualsCanonicalizing($this->about('KB10234')->where('status', 'open')->pluck('id')->all(), $this->ids(['q' => 'ahmad']));
        $this->assertEqualsCanonicalizing($this->about('KB10234')->where('status', 'open')->pluck('id')->all(), $this->ids(['q' => 'kb10234']));
        $this->assertCount(3, $this->ids(['q' => 'Pelajar Contoh']), 'the student filed three of the open ones');
        $this->assertSame([], $this->ids(['q' => 'late and did not apologise']), 'the text is not searched');
        $this->assertSame([], $this->ids(['q' => '%']));
        $this->assertSame([], $this->ids(['q' => 'KB1_234']));
    }

    public function test_the_search_and_filters_narrow_the_tab_counts_too(): void
    {
        $this->assertSame(['all' => 3, 'open' => 2, 'under_review' => 0, 'resolved' => 1, 'dismissed' => 0], $this->listing(['q' => 'ahmad'])->viewData('counts'));
    }

    public function test_filter_by_counselor_as_the_counselors_page_links_to_it(): void
    {
        $tan = $this->profile('KB10891');
        $page = $this->listing(['counselor' => $tan->id]);

        $this->assertEqualsCanonicalizing($this->about('KB10891')->where('status', 'open')->pluck('id')->all(), $page->viewData('complaints')->getCollection()->pluck('id')->all());
        $page->assertSee('Showing complaints about one counselor')->assertSee(route('platform.counselors.show', $tan), false);
        $this->assertSame(['all' => 3, 'open' => 1, 'under_review' => 1, 'resolved' => 1, 'dismissed' => 0], $page->viewData('counts'));

        // The link on the counselor's page really is this filter.
        $this->as()->get(route('platform.counselors.show', $tan))->assertSee(route('platform.complaints', ['counselor' => $tan->id]), false);
    }

    public function test_filter_by_organization_as_the_organization_page_links_to_it(): void
    {
        $umpsa = Organization::where('name', 'like', 'Universiti Malaysia Pahang%')->firstOrFail();
        $klinik = Organization::where('name', 'Klinik Kaunseling Damai')->firstOrFail();
        $platform = Organization::where('org_type', 'platform')->firstOrFail();

        $this->assertCount(2, $this->ids(['organization' => $umpsa->id]), 'open, through UMPSA');
        $this->assertCount(1, $this->ids(['organization' => $klinik->id]));
        $this->assertCount(1, $this->ids(['organization' => $platform->id]), "Tan's session");
        $this->assertSame([], $this->ids(['organization' => 999999]));
        $this->assertSame(['all' => 3, 'open' => 2, 'under_review' => 0, 'resolved' => 1, 'dismissed' => 0], $this->listing(['organization' => $umpsa->id])->viewData('counts'));

        // The organization dropdown lists organizations that have complaints, and names the platform "Independent practice".
        $names = $this->listing()->viewData('organizations')->values()->all();
        $this->assertContains('Independent practice', $names);
        $this->assertContains($klinik->name, $names);

        $this->as()->get(route('platform.organizations.show', $umpsa))->assertSee(route('platform.complaints', ['organization' => $umpsa->id]), false);
    }

    public function test_filters_combine_and_bad_values_are_ignored_and_explained(): void
    {
        $umpsa = Organization::where('name', 'like', 'Universiti Malaysia Pahang%')->firstOrFail();
        $this->assertCount(1, $this->ids(['organization' => $umpsa->id, 'q' => 'siti']));
        $this->assertSame([], $this->ids(['organization' => $umpsa->id, 'q' => 'aina']));

        $response = $this->listing(['tab' => 'hacked', 'counselor' => 'abc', 'organization' => '-3', 'q' => str_repeat('a', 101)]);
        $response->assertSee('Some filters in the link were not valid and were ignored')->assertSee('Unknown tab.');
        $this->assertSame('open', $response->viewData('tab'));
        $this->assertCount(5, $response->viewData('complaints')->getCollection());
        $this->listing(['q' => ['array']]);
    }

    public function test_pagination_and_empty_states(): void
    {
        $reporter = User::where('email', 'pelajar.aduan@example.test')->firstOrFail();
        foreach (range(1, 8) as $i) {
            Complaint::create(['reporter_user_id' => $reporter->id, 'counselor_user_id' => $this->profile('KB10234')->user_id, 'description' => "[Sample] extra {$i}", 'status' => ComplaintStatus::Open]);
        }

        $this->assertCount(10, $this->ids());
        $this->assertCount(3, $this->ids(['page' => 2]));
        $this->as()->get(route('platform.complaints', ['page' => 9]))->assertRedirect(route('platform.complaints', ['page' => 2]));

        $this->listing(['q' => 'zzz'])->assertSee('No complaints match your search')->assertDontSee('No open complaints');

        Complaint::query()->update(['status' => ComplaintStatus::Resolved]);
        $this->listing()->assertSee('No open complaints');
        $this->listing(['tab' => 'under_review'])->assertSee('Nothing under review');
        $this->listing(['tab' => 'dismissed'])->assertSee('No dismissed complaints');
    }

    // ---- detail ------------------------------------------------------------------------------

    public function test_the_detail_page_shows_the_text_the_counselor_the_reporter_and_the_session(): void
    {
        $complaint = $this->find('20 minutes late');

        $this->show($complaint)
            ->assertSee('Complaint #'.$complaint->id)->assertSee('Open')->assertSee('Filed')
            ->assertSee('What was reported')->assertSee('never copied into the audit log')
            ->assertSee('The session started 20 minutes late')
            ->assertSee('Ahmad Zulkarnain bin Yusof')->assertSee('KB10234')->assertSee('Approved')->assertSee('Organization counselor')
            ->assertSee(route('platform.counselors.show', $this->profile('KB10234')), false)
            ->assertSee('Pelajar Contoh (made up)')
            ->assertSee('Universiti Malaysia Pahang')->assertSee('Online')->assertSee('Completed')
            ->assertSee(now()->subDays(10)->format('j M Y'));
    }

    public function test_the_reporters_email_is_never_shown_and_html_in_the_text_is_plain_text(): void
    {
        $this->show($this->find('Bold?'))
            ->assertDontSee('pelajar.aduan@example.test')
            ->assertSee('&lt;b&gt;Bold?&lt;/b&gt;', false)
            ->assertSee('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert', false)
            ->assertSee('whitespace-pre-line', false);
    }

    public function test_a_complaint_without_a_session_says_so(): void
    {
        $this->show($this->find('Not linked to any session'))->assertSee('This complaint is not linked to a session.');
    }

    public function test_an_independent_counselors_session_is_independent_practice_and_a_clinic_links_to_its_page(): void
    {
        $tanComplaint = $this->about('KB10891')->whereNotNull('booking_id')->firstOrFail();
        $this->show($tanComplaint)->assertSee('Independent practice')->assertDontSee('Open the organization');

        $this->show($this->find('clinic charged'))->assertSee('Open the organization')
            ->assertSee(route('platform.organizations.show', Organization::where('name', 'Klinik Kaunseling Damai')->first()), false);
    }

    public function test_other_complaints_about_the_same_counselor_is_a_count_with_a_link(): void
    {
        $this->show($this->find('20 minutes late'))->assertSee('Other complaints about them')
            ->assertSee(route('platform.complaints', ['counselor' => $this->profile('KB10234')->id]), false);

        $this->show($this->find('clinic charged'))->assertSee('None');
    }

    public function test_an_unknown_complaint_is_a_404(): void
    {
        $this->as()->get('/platform/complaints/999999')->assertNotFound();
        $this->as()->get('/platform/complaints/abc')->assertNotFound();
        $this->as()->post('/platform/complaints/999999/notes', ['body' => 'x'])->assertNotFound();
    }

    // ---- notes and activity ---------------------------------------------------------------------

    public function test_a_note_is_saved_audited_by_reference_only_and_shown_with_author_and_time(): void
    {
        $complaint = $this->find('20 minutes late');

        $this->as()->post(route('platform.complaints.notes.store', $complaint), ['body' => '  Rang the student — they will send a screenshot.  '])
            ->assertRedirect(route('platform.complaints.show', $complaint).'#notes')->assertSessionHas('status', 'Note added.');

        $note = ComplaintReviewNote::where('complaint_id', $complaint->id)->firstOrFail();
        $this->assertSame(['Rang the student — they will send a screenshot.', $this->admin->id], [$note->body, $note->user_id]);

        $log = AuditLog::where('action', 'complaint.note_added')->where('target_id', $complaint->id)->firstOrFail();
        $this->assertSame(['note_id' => $note->id], $log->metadata);
        $this->assertSame(['complaint', 'Complaint #'.$complaint->id, 'complaints'], [$log->target_type, $log->target_name, $log->category]);
        $this->assertStringNotContainsString('screenshot', json_encode($log->toArray()));

        $this->show($complaint)->assertSee('Rang the student')->assertSee('Platform Administrator')->assertSee('Only Platform Admins can see these')->assertSee('Internal note added');
    }

    public function test_an_empty_or_oversized_note_is_refused(): void
    {
        $complaint = $this->find('20 minutes late');

        foreach (['', '   ', str_repeat('a', 2001)] as $bad) {
            $this->as()->post(route('platform.complaints.notes.store', $complaint), ['body' => $bad])->assertSessionHasErrors('body', null, 'note');
        }

        $this->assertSame(1, ComplaintReviewNote::count(), 'only the seeded one');
    }

    public function test_a_note_that_cannot_be_audited_is_not_saved(): void
    {
        \App\Facades\Audit::shouldReceive('log')->andThrow(new \RuntimeException('audit table unavailable'));
        $this->withoutExceptionHandling();

        try {
            $this->as()->post(route('platform.complaints.notes.store', $this->find('20 minutes late')), ['body' => 'Satu nota']);
            $this->fail('the request should have failed');
        } catch (\RuntimeException) {
        }

        $this->assertSame(1, ComplaintReviewNote::count());
    }

    public function test_the_activity_lists_filing_notes_and_status_changes_newest_first_without_the_text(): void
    {
        $review = $this->find('Not linked to any session');
        $events = app(ComplaintTimeline::class)->for($review);

        $this->assertSame(['Warning issued to the counselor', 'Internal note added', 'Status changed', 'Complaint filed'], $events->pluck('title')->all());
        $this->assertSame('open → under_review', $events[2]['detail']);
        $this->assertStringContainsString('answer clients', $events[0]['detail'], 'the warning message comes from the complaint\'s own records');
        $this->assertSame(['Platform Administrator', 'Platform Administrator', 'Platform Administrator', 'Reporter'], $events->pluck('actor')->all());

        $resolved = $this->find('meeting link did not work');
        $this->assertSame('under_review → resolved — [Sample] Outcome (made up).', app(ComplaintTimeline::class)->for($resolved)->first()['detail']);

        $this->show($review)->assertSee('Complaint filed')->assertSee('Status changed')->assertSee('View full history');
    }

    public function test_no_complaint_text_is_anywhere_in_the_audit_log_or_its_page(): void
    {
        $this->as()->post(route('platform.complaints.notes.store', $this->find('20 minutes late')), ['body' => 'Satu nota']);

        $this->assertStringNotContainsString('[Sample]', json_encode(AuditLog::all()->toArray()));
        $this->as()->get(route('platform.audit-log', ['category' => 'complaints']))->assertOk()
            ->assertSee('Internal note added')->assertSee('Complaint #')->assertDontSee('[Sample]')
            ->assertSee(route('platform.complaints.show', $this->find('Not linked to any session')), false);
    }

    public function test_the_complaint_text_is_never_on_the_counselor_page(): void
    {
        $this->as()->get(route('platform.counselors.show', $this->profile('KB10234')))->assertOk()->assertDontSee('[Sample]')->assertDontSee('20 minutes late');
    }
}
