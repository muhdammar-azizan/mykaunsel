<?php

namespace Tests\Feature\Counselor;

use App\Models\CounselorDocument;

class CounselorDetailTest extends CounselorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSamples();
    }

    private function show(string $kb)
    {
        return $this->as()->get(route('platform.counselors.show', $this->profile($kb)));
    }

    public function test_a_pending_application_shows_lkm_documents_applicant_and_activity(): void
    {
        $this->show('KB11023')->assertOk()
            ->assertSee('Kavitha a/p Muniandy')
            ->assertSee('KB11023')
            ->assertSee('Independent')
            ->assertSee('Attempt 1')
            ->assertSee('LKM verification')->assertSee('Matches LKM')
            ->assertSee('Applicant submitted')->assertSee('LKM record')
            ->assertSee('PA21034')
            ->assertSee('Documents')->assertSee('KB certificate')->assertSee('PA certificate')->assertSee('ID / passport')
            ->assertSee('kb-certificate.pdf')->assertSee('identity-card.png')
            ->assertSee('Duplicate KB check')->assertSee('No one has tried to register KB11023 again')
            ->assertSee('Applicant')->assertSee('kavitha-ap-muniandy@example.test')
            ->assertSee('Documents submitted')
            ->assertSee('Activity')->assertSee('Application submitted')
            ->assertDontSee('Listed in search');   // not approved yet
    }

    public function test_the_lkm_card_flags_what_changed_since_the_application(): void
    {
        $this->show('KB11702')->assertOk()
            ->assertSee('Does not fully match LKM')
            ->assertSee('The PA valid-until date differs from the LKM record')
            ->assertSee('Applicant submitted');

        $profile = $this->profile('KB11702');
        $comparison = app(\App\Services\LkmVerificationService::class)->compare($profile);
        $row = collect($comparison->fields)->firstWhere('label', 'PA valid until');
        $this->assertSame('mismatch', $row['state']);
        $this->assertSame('20 Jan 2028', $row['submitted']);
        $this->assertSame(now()->addDays(10)->format('j M Y'), $row['record']);
    }

    public function test_a_flagged_document_and_a_duplicate_attempt_are_visible(): void
    {
        $this->show('KB11256')->assertOk()
            ->assertSee('Needs replacement')
            ->assertSee('Note: The photo is blurry')
            ->assertSee('Duplicate KB attempt')
            ->assertSee('Muhammad Haziq Ismail')   // the name typed by whoever tried
            ->assertSee('IP 203.0.113.71')
            ->assertSee('The person trying is never told who owns it');
    }

    public function test_a_resubmitted_application_shows_the_earlier_rejection_and_history(): void
    {
        $this->show('KB11489')->assertOk()
            ->assertSee('Resubmitted')
            ->assertSee('Attempt 2')
            ->assertSee('Rejected earlier')
            ->assertSee('The PA certificate was cut off at the edge')
            ->assertSee('Resubmitted (attempt 2)')
            ->assertSee('Platform Administrator');   // who rejected it, from the audit log
    }

    public function test_an_active_independent_counselor_shows_profile_credentials_stats_and_the_pa_banner(): void
    {
        $this->show('KB11935')->assertOk()
            ->assertSee('Listed in search')
            ->assertSee('Practising certificate runs out in 12 days')
            ->assertSee('suspended automatically')
            ->assertSee('Profile')->assertSee('Independent practice')->assertSee('Bahasa Malaysia')->assertSee('Stres &amp; Kebimbangan', false)
            ->assertSee('Credentials')->assertSee('PA21946')
            ->assertSee('Activity at a glance')
            ->assertSee('Numbers only');
    }

    public function test_the_statistics_are_counts_only_and_never_show_a_client(): void
    {
        $response = $this->show('KB10891')->assertOk();

        $response->assertSee('Total sessions')->assertSee('Upcoming sessions')->assertSee('Open complaints')->assertSee('Past complaints')
            ->assertSee(route('platform.complaints', ['counselor' => $this->profile('KB10891')->id]), false);

        // 3 completed + 2 confirmed, the cancelled one not counted; 2 upcoming; 1 open + 1 resolved complaint.
        $html = $response->getContent();
        $tiles = [];
        preg_match_all('/<p class="mt-1 text-\[24px\][^"]*">([\d,]+)<\/p>/', $html, $tiles);
        $this->assertSame(['5', '2', '1', '1'], $tiles[1]);

        // Nothing identifying a client, a session, or a complaint's text anywhere on the page.
        foreach (['Klien Contoh', 'klien.contoh@example.test', 'Sample complaint', 'Older sample complaint', '14:00'] as $leak) {
            $response->assertDontSee($leak);
        }
    }

    public function test_a_counselor_with_blocked_registration_attempts_lists_them(): void
    {
        $this->show('KB10891')->assertOk()
            ->assertSee('Tan W. M.')->assertSee('Wei Ming Tan')
            ->assertSee('198.51.100.20')
            ->assertSee('Duplicate KB attempt')
            ->assertSee('Someone tried to register this KB number again'); // from the audit log
    }

    public function test_a_system_suspension_for_an_expired_pa_is_explained(): void
    {
        $this->show('KB13090')->assertOk()
            ->assertSee('Suspended')->assertSee('by the system')
            ->assertSee('Practising certificate expired on 30 Nov 2025.')
            ->assertSee('cannot be listed or reactivated until a valid PA is on record')
            ->assertSee('Not listed in search.')
            ->assertSee('Suspended automatically: PA expired')
            ->assertSee('System');
    }

    public function test_a_suspension_after_a_complaint_names_the_source_and_the_admin(): void
    {
        $this->show('KB12168')->assertOk()
            ->assertSee('by a complaint (Platform Administrator)')
            ->assertSee('Two clients reported unprofessional conduct')
            ->assertSee('Not listed in search.');
    }

    public function test_a_rejected_application_shows_its_reason_and_the_marked_document(): void
    {
        $this->show('KB12391')->assertOk()
            ->assertSee('Rejected')
            ->assertSee('The KB certificate does not match the name on the LKM record')
            ->assertSee('Needs replacement')->assertSee('The name on the certificate does not match.');
    }

    public function test_an_organization_counselor_has_no_application_cards(): void
    {
        $this->show('KB10234')->assertOk()
            ->assertSee('Organization counselor')
            ->assertSee('Universiti Malaysia Pahang')
            ->assertSee('Verified by their organization')
            ->assertSee('Added by their organization')
            ->assertSee('there is no approval step here')
            ->assertSee('Listed in search')
            ->assertDontSee('LKM verification')
            ->assertDontSee('Duplicate KB check')
            ->assertDontSee('Documents submitted');
    }

    public function test_document_file_names_on_disk_and_paths_never_appear_in_the_page(): void
    {
        $response = $this->show('KB11023')->assertOk();

        foreach ($this->profile('KB11023')->documents as $document) {
            $response->assertDontSee($document->path);
        }
        $response->assertDontSee('counselor-documents')->assertDontSee('storage/app');
        // The only document URL on the page is the endpoint that issues a short-lived signed link (JS-escaped slashes).
        $response->assertSee(str_replace('/', '\/', '/platform/counselors/'.$this->profile('KB11023')->id.'/documents/'), false);
    }

    public function test_purged_documents_say_so_and_cannot_be_opened(): void
    {
        $doc = $this->profile('KB11023')->documents()->where('type', 'ic')->first();
        $doc->update(['path' => null, 'purged_at' => now()->subDay()]);

        $this->show('KB11023')->assertSee('Removed on')->assertSee('under the retention rules')->assertSee('Removed');
    }

    public function test_an_unknown_counselor_is_a_404(): void
    {
        $this->as()->get('/platform/counselors/999999')->assertNotFound();
        $this->as()->get('/platform/counselors/abc')->assertNotFound();
    }
}
