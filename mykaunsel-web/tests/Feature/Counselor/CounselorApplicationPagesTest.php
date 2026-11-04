<?php

namespace Tests\Feature\Counselor;

use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Models\CounselorDocument;
use App\Models\CounselorProfile;
use App\Models\LkmDirectorySnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Api\AvailabilityFixtures;
use Tests\TestCase;

/** What an applicant sees of their own documents and their LKM check: the upload page and the Profile page. Clock: Monday 2 November 2026, 09:00 Malaysian time. */
class CounselorApplicationPagesTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    private CounselorProfile $aina;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();
        $this->aina = $this->counselor('Aina', [$this->platform], ['status' => VerificationStatus::Pending, 'kb' => 'KB12345', 'pa' => 'PA12345']);
        $this->aina->forceFill(['verification_type' => VerificationType::PlatformVerified])->save();
        LkmDirectorySnapshot::create(['kb_number' => 'KB12345', 'pa_number' => 'PA12345', 'full_name' => 'Aina', 'status' => 'Aktif', 'pa_valid_from' => '2026-01-01', 'pa_valid_until' => $this->aina->pa_valid_until->toDateString()]);
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

    private function document(string $type, string $status = CounselorDocument::STATUS_UNREVIEWED, ?string $note = null, string $name = 'scan.pdf'): CounselorDocument
    {
        return $this->aina->documents()->create(['type' => $type, 'path' => 'random/'.$type.'.bin', 'original_name' => $name, 'size_bytes' => 1000, 'status' => $status, 'note' => $note, 'uploaded_at' => now()]);
    }

    // ---- the upload page ----------------------------------------------------------------------------------------

    public function test_the_upload_page_shows_what_is_already_on_file_and_how_it_was_received(): void
    {
        $this->document('cert', name: 'my-kb.pdf');
        $this->document('pa', CounselorDocument::STATUS_OK, name: 'pa.jpg');

        $this->as()->get(route('counselors.signup.documents'))->assertOk()
            ->assertSee('my-kb.pdf')->assertSee('pa.jpg')->assertSee('Received, not checked yet')->assertSee('Checked and fine')
            ->assertSee('Already uploaded');
    }

    public function test_a_document_marked_needs_replacement_shows_the_mark_and_the_note_for_the_applicant(): void
    {
        $this->document('ic', CounselorDocument::STATUS_NEEDS_REPLACEMENT, 'The photo is too dark to read.', 'ic-front.png');

        $this->as()->get(route('counselors.signup.documents'))->assertOk()
            ->assertSee('Needs replacement')->assertSee('Please upload this document again.')->assertSee('Note from our team: The photo is too dark to read.')
            // The old file is not shown as "ready": a new one is wanted.
            ->assertDontSee('ic-front.png');
    }

    public function test_the_upload_fields_have_labels_and_the_dialog_is_marked_as_one(): void
    {
        $html = $this->as()->get(route('counselors.signup.documents'))->assertOk()->getContent();

        foreach (['cert', 'pa', 'ic'] as $type) {
            $this->assertStringContainsString('<label for="doc-'.$type.'"', $html);
            $this->assertStringContainsString('id="doc-'.$type.'"', $html);
        }
        $this->assertStringContainsString('role="dialog" aria-modal="true"', $html);
    }

    public function test_a_document_that_is_not_there_yet_is_not_listed_as_received(): void
    {
        $this->as()->get(route('counselors.signup.documents'))->assertOk()->assertDontSee('Received, not checked yet')->assertDontSee('Already uploaded');
    }

    // ---- the Profile page (credentials) -------------------------------------------------------------------------

    public function test_the_profile_page_shows_the_lkm_result_and_the_documents_with_their_marks_of_an_applicant(): void
    {
        $this->aina->update(['verification_status' => VerificationStatus::Suspended]);   // pending applicants are sent to the status page: a suspended one has the portal
        $this->document('cert');
        $this->document('pa', CounselorDocument::STATUS_NEEDS_REPLACEMENT, 'Not the current certificate.');

        $this->as()->get(route('counselor.profile'))->assertOk()
            ->assertSee('Your details match the LKM directory record we hold.')->assertSee('data-lkm-result="ok"', false)
            ->assertSee('Your documents')->assertSee('KB certificate')->assertSee('PA certificate')->assertSee('ID / passport')
            ->assertSee('Received, not checked yet')->assertSee('Needs replacement')->assertSee('Note from our team: Not the current certificate.')->assertSee('Not uploaded yet');
    }

    public function test_the_lkm_result_says_when_something_does_not_match_or_is_gone(): void
    {
        $this->aina->update(['verification_status' => VerificationStatus::Suspended]);

        LkmDirectorySnapshot::where('kb_number', 'KB12345')->update(['full_name' => 'Someone Else']);
        $this->as()->get(route('counselor.profile'))->assertOk()->assertSee('Some of your details differ from the LKM directory record we hold.')->assertSee('data-lkm-result="warn"', false);

        LkmDirectorySnapshot::where('kb_number', 'KB12345')->update(['status' => 'Digantung']);
        $this->as()->get(route('counselor.profile'))->assertSee('The LKM directory record we hold is not active.');

        LkmDirectorySnapshot::where('kb_number', 'KB12345')->delete();
        $this->as()->get(route('counselor.profile'))->assertSee('We could not find your KB number in the LKM directory record we hold.');
    }

    public function test_the_page_never_claims_a_live_connection_and_never_shows_a_file(): void
    {
        $this->aina->update(['verification_status' => VerificationStatus::Suspended]);
        $this->document('cert');

        $html = $this->as()->get(route('counselor.profile'))->assertOk()->getContent();

        $this->assertStringNotContainsStringIgnoringCase('live', strip_tags($this->between($html, 'Verification', 'How clients see')));
        $this->assertStringNotContainsString('random/cert.bin', $html, 'the stored path of a file is never shown');
    }

    public function test_a_counselor_an_organization_assigned_sees_no_application_card(): void
    {
        $org = $this->counselor('Org One', [$this->platform], ['status' => VerificationStatus::Approved]);

        $this->as($org)->get(route('counselor.profile'))->assertOk()->assertDontSee('Your documents')->assertDontSee('data-lkm-result', false);
    }

    private function between(string $html, string $from, string $to): string
    {
        $start = strpos($html, $from);
        $end = strpos($html, $to, $start ?: 0);

        return substr($html, $start ?: 0, ($end ?: strlen($html)) - ($start ?: 0));
    }
}
