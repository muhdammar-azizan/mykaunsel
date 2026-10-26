<?php

namespace Tests\Feature\Counselor;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AuditLog;
use App\Models\CounselorDocument;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class CounselorDocumentAccessTest extends CounselorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSamples();
    }

    private function document(string $kb = 'KB11023', string $type = 'cert'): CounselorDocument
    {
        return $this->profile($kb)->documents()->where('type', $type)->firstOrFail();
    }

    private function link(CounselorDocument $document, ?User $as = null)
    {
        return $this->as($as)->getJson(route('platform.counselors.documents.link', [$document->counselorProfile, $document]));
    }

    private function views(): int
    {
        return AuditLog::where('action', 'counselor.document_viewed')->count();
    }

    private function secondAdmin(): User
    {
        $other = User::factory()->create(['name' => 'Admin Lain']);
        Membership::create(['user_id' => $other->id, 'org_id' => $this->session['current_org_id'], 'role' => MembershipRole::PlatformAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        return $other;
    }

    // ---- the link ---------------------------------------------------------------------------

    public function test_a_platform_admin_gets_a_short_lived_signed_link_and_nothing_is_logged_yet(): void
    {
        $document = $this->document();
        $before = $this->views();

        $json = $this->link($document)->assertOk()->json();

        $this->assertSame('application/pdf', $json['mime']);
        $this->assertSame('KB certificate', $json['label']);
        $this->assertStringContainsString('signature=', $json['url']);
        $this->assertStringContainsString('expires=', $json['url']);
        $this->assertStringContainsString('/platform/counselor-documents/'.$document->id, $json['url']);
        $this->assertStringNotContainsString($document->path, $json['url'], 'the real file name is never in the link');
        $this->assertSame($before, $this->views(), 'asking for a link is not a view');

        parse_str(parse_url($json['url'], PHP_URL_QUERY), $query);
        $this->assertEqualsWithDelta(now()->addMinutes(2)->timestamp, (int) $query['expires'], 5);
        $this->assertSame((string) $this->admin->id, $query['u']);
    }

    public function test_the_link_endpoint_refuses_everyone_else(): void
    {
        $document = $this->document();
        $orgAdmin = User::where('email', 'afif@umpsa.edu.my')->firstOrFail();
        $umpsa = Organization::where('name', 'like', 'Universiti Malaysia Pahang%')->firstOrFail();

        $this->flushSession();
        $this->actingAs($orgAdmin)->withSession(['current_org_id' => $umpsa->id, 'current_role' => 'org_admin'])
            ->getJson(route('platform.counselors.documents.link', [$document->counselorProfile, $document]))->assertForbidden();

        $this->flushSession();
        auth()->logout();
        $this->getJson(route('platform.counselors.documents.link', [$document->counselorProfile, $document]))->assertUnauthorized();
    }

    public function test_a_document_must_belong_to_the_counselor_in_the_url(): void
    {
        $document = $this->document('KB11023');
        $other = $this->profile('KB11256');

        $this->as()->getJson(route('platform.counselors.documents.link', [$other, $document]))->assertNotFound();
    }

    public function test_missing_purged_and_unsupported_files_get_a_clear_answer_not_a_link(): void
    {
        $purged = $this->document('KB11023', 'ic');
        $purged->update(['path' => null, 'purged_at' => now()]);
        $this->link($purged)->assertStatus(410)->assertJsonPath('message', 'This document was removed under the retention rules.');

        $none = $this->document('KB11256', 'pa');
        $none->update(['path' => null]);
        $this->link($none)->assertStatus(410)->assertJsonPath('message', 'No file was uploaded.');

        $odd = $this->document('KB11489', 'pa');
        $odd->update(['path' => 'abcdefghij.exe']);
        $this->link($odd)->assertStatus(415);
    }

    // ---- serving the file -------------------------------------------------------------------

    public function test_the_signed_link_serves_the_file_with_safe_headers_and_writes_an_audit_event(): void
    {
        $document = $this->document();
        $url = $this->link($document)->json('url');

        $response = $this->as()->get($url)->assertOk();

        $this->assertSame(Storage::disk('counselor_documents')->get($document->path), $response->streamedContent());
        $this->assertStringStartsWith('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString("frame-ancestors 'self'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('inline; filename="cert-document.pdf"', $response->headers->get('Content-Disposition'), 'a neutral name, not what the applicant called it');

        $log = AuditLog::where('action', 'counselor.document_viewed')->firstOrFail();
        $this->assertSame($this->admin->id, $log->actor_user_id);
        $this->assertSame('Platform Administrator', $log->actor_name);
        $this->assertSame('platform_admin', $log->actor_role);
        $this->assertSame('counselor', $log->target_type);
        $this->assertSame($document->counselor_profile_id, $log->target_id);
        $this->assertSame('KB certificate', $log->metadata['document']);
        $this->assertSame($document->id, $log->metadata['document_id']);
        $this->assertSame('security', config('audit.actions')['counselor.document_viewed']['badge']);
    }

    public function test_images_are_served_as_images(): void
    {
        $document = $this->document('KB11023', 'ic');

        $response = $this->as()->get($this->link($document)->json('url'))->assertOk();

        $this->assertStringStartsWith('image/png', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith("\x89PNG", $response->streamedContent());
    }

    public function test_every_view_is_logged_but_ranged_follow_up_requests_are_not(): void
    {
        $document = $this->document();

        $this->as()->get($this->link($document)->json('url'))->assertOk();
        $this->assertSame(1, $this->views());

        $this->as()->get($this->link($document)->json('url'))->assertOk();
        $this->assertSame(2, $this->views(), 'opening it again is another view');

        // A PDF viewer fetching the rest of the same file in pieces is not a new view.
        $this->as()->withHeader('Range', 'bytes=0-99')->get($this->link($document)->json('url'))->assertOk();
        $this->assertSame(2, $this->views());
    }

    public function test_the_activity_list_shows_who_looked_at_which_document(): void
    {
        $document = $this->document();
        $this->as()->get($this->link($document)->json('url'))->assertOk();

        $this->as()->get(route('platform.counselors.show', $document->counselorProfile))
            ->assertSee('Document viewed')->assertSee('KB certificate')->assertSee('Platform Administrator');
        $this->as()->get(route('platform.audit-log', ['category' => 'counselors']))->assertSee('Document viewed');
    }

    // ---- who can NOT reach it -----------------------------------------------------------------

    public function test_an_unsigned_or_tampered_url_is_refused_and_nothing_is_logged(): void
    {
        $document = $this->document();
        $signed = $this->link($document)->json('url');

        $this->as()->get(route('platform.counselor-documents.show', $document))->assertForbidden();                     // no signature at all
        $this->as()->get(route('platform.counselor-documents.show', ['document' => $document, 'u' => $this->admin->id]))->assertForbidden();
        $this->as()->get(str_replace('signature=', 'signature=0', $signed))->assertForbidden();                          // bad signature
        $this->as()->get(preg_replace('/u=\d+/', 'u=999', $signed))->assertForbidden();                                 // changed the admin id
        $other = $this->document('KB11256');
        $this->as()->get(str_replace('/counselor-documents/'.$document->id, '/counselor-documents/'.$other->id, $signed))->assertForbidden(); // a different document

        $this->assertSame(0, $this->views());
    }

    public function test_the_link_expires_after_two_minutes(): void
    {
        $document = $this->document();
        $url = $this->link($document)->json('url');

        $this->travel(119)->seconds();
        $this->as()->get($url)->assertOk();

        $this->travel(2)->seconds();
        $this->as()->get($url)->assertForbidden();
        $this->assertSame(1, $this->views(), 'only the view before it expired');
    }

    public function test_a_link_cannot_be_passed_to_another_admin(): void
    {
        $url = $this->link($this->document())->json('url');
        $other = $this->secondAdmin();

        $this->flushSession();
        $this->actingAs($other)->withSession($this->session)->get($url)->assertForbidden();

        $this->assertSame(0, $this->views());
    }

    public function test_a_valid_link_is_useless_to_anyone_who_is_not_a_platform_admin(): void
    {
        $url = $this->link($this->document())->json('url');
        $orgAdmin = User::where('email', 'afif@umpsa.edu.my')->firstOrFail();
        $umpsa = Organization::where('name', 'like', 'Universiti Malaysia Pahang%')->firstOrFail();

        $this->flushSession();
        $this->actingAs($orgAdmin)->withSession(['current_org_id' => $umpsa->id, 'current_role' => 'org_admin'])->get($url)->assertForbidden();

        $this->flushSession();
        $counselor = $this->profile('KB11023')->user; // even the owner cannot open it through this route
        $this->actingAs($counselor)->withSession(['current_org_id' => $this->session['current_org_id'], 'current_role' => 'counselor'])->get($url)->assertForbidden();

        $this->flushSession();
        auth()->logout();
        $this->get($url)->assertRedirect();   // not signed in: sent to the login page, nothing served

        $this->assertSame(0, $this->views());
    }

    public function test_a_missing_file_is_a_404_even_with_a_valid_link(): void
    {
        $document = $this->document();
        $url = $this->link($document)->json('url');
        Storage::disk('counselor_documents')->delete($document->path);

        $this->as()->get($url)->assertNotFound();
        $this->assertSame(0, $this->views());
    }

    public function test_the_files_are_not_reachable_by_any_public_url(): void
    {
        $document = $this->document();

        $this->assertFalse(Storage::disk('public')->exists($document->path));
        $this->assertFalse(Storage::disk('public')->exists('counselor-documents/'.$document->path));

        foreach (['/storage/'.$document->path, '/storage/counselor-documents/'.$document->path, '/counselor-documents/'.$document->path, '/'.$document->path] as $url) {
            $status = $this->get($url)->getStatusCode();
            $this->assertContains($status, [403, 404], "{$url} answered {$status}");
        }

        // Where the disk really lives on a real server: nowhere under public/.
        $root = str_replace('\\', '/', config('filesystems.disks.counselor_documents.root'));
        $this->assertStringNotContainsString('/public', $root);
        $this->assertStringStartsWith(str_replace('\\', '/', storage_path('app/private')), $root);
    }
}
