<?php

namespace Tests\Feature\Platform;

use App\Enums\ComplaintStatus;
use App\Enums\VerificationStatus;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\CounselorComplaintWarning;
use Database\Seeders\ComplaintReviewSeeder;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Counselor\CounselorTestCase;

class ComplaintActionsTest extends CounselorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSamples();
        $this->seed(ComplaintReviewSeeder::class);
    }

    private function openComplaint(): Complaint
    {
        return Complaint::where('status', ComplaintStatus::Open->value)
            ->get()
            ->first(fn ($c) => $c->counselorProfile?->verification_status === VerificationStatus::Approved);
    }

    private function closedComplaint(): Complaint
    {
        return Complaint::whereIn('status', [ComplaintStatus::Resolved->value, ComplaintStatus::Dismissed->value])->firstOrFail();
    }

    private function logs(Complaint $c, string $action)
    {
        return AuditLog::where('target_type', 'complaint')->where('target_id', $c->id)->where('action', $action)->get();
    }

    public function test_start_review_needs_no_reason_and_is_audited(): void
    {
        $c = $this->openComplaint();

        $this->as()->post(route('platform.complaints.status', $c), ['to' => 'under_review', 'from' => 'open'])
            ->assertRedirect(route('platform.complaints.show', $c));

        $this->assertSame(ComplaintStatus::UnderReview, $c->fresh()->status);
        $log = $this->logs($c, 'complaint.status_changed')->sole();
        $this->assertSame(['old' => 'open', 'new' => 'under_review'], $log->metadata['changes']['status']);
    }

    public function test_resolve_and_dismiss_need_a_written_outcome(): void
    {
        $c = $this->openComplaint();

        foreach (['resolved', 'dismissed'] as $to) {
            $this->as()->post(route('platform.complaints.status', $c), ['to' => $to, 'from' => 'open', 'reason' => ''])
                ->assertSessionHasErrorsIn($to, 'reason');
        }
        $this->assertSame(ComplaintStatus::Open, $c->fresh()->status);

        $this->as()->post(route('platform.complaints.status', $c), ['to' => 'resolved', 'from' => 'open', 'reason' => 'Spoke to the counselor, agreed a fix.']);
        $this->assertSame(ComplaintStatus::Resolved, $c->fresh()->status);
        $this->assertSame('Spoke to the counselor, agreed a fix.', $this->logs($c, 'complaint.status_changed')->sole()->metadata['reason']);
    }

    public function test_a_closed_complaint_cannot_be_changed_again(): void
    {
        $c = $this->closedComplaint();
        $before = $c->status;

        $this->as()->post(route('platform.complaints.status', $c), ['to' => 'under_review', 'from' => $before->value])
            ->assertSessionHas('error');
        $this->as()->post(route('platform.complaints.status', $c), ['to' => 'open'])->assertStatus(422);

        $this->assertSame($before, $c->fresh()->status);
    }

    public function test_a_stale_page_is_refused(): void
    {
        $c = $this->openComplaint();
        $this->as()->post(route('platform.complaints.status', $c), ['to' => 'under_review', 'from' => 'open']);

        // Another admin still looking at the "open" page.
        $this->as()->post(route('platform.complaints.status', $c), ['to' => 'dismissed', 'from' => 'open', 'reason' => 'Not a real problem.'])
            ->assertSessionHas('error');

        $this->assertSame(ComplaintStatus::UnderReview, $c->fresh()->status);
        $this->assertCount(1, $this->logs($c, 'complaint.status_changed'));
    }

    public function test_warning_is_emailed_audited_and_leaves_the_status_alone(): void
    {
        Notification::fake();
        $c = $this->openComplaint();

        $this->as()->post(route('platform.complaints.warn', $c), ['reason' => 'Please arrive on time for every session.'])
            ->assertSessionHas('status');

        Notification::assertSentTo($c->counselor, CounselorComplaintWarning::class);
        $this->assertSame(ComplaintStatus::Open, $c->fresh()->status);
        $this->assertSame('Please arrive on time for every session.', $this->logs($c, 'complaint.warning_issued')->sole()->metadata['reason']);
    }

    public function test_warning_needs_a_message_and_not_on_a_closed_complaint(): void
    {
        Notification::fake();
        $c = $this->openComplaint();

        $this->as()->post(route('platform.complaints.warn', $c), ['reason' => ''])->assertSessionHasErrorsIn('warn', 'reason');

        $closed = $this->closedComplaint();
        $this->as()->post(route('platform.complaints.warn', $closed), ['reason' => 'Please arrive on time for every session.'])
            ->assertSessionHas('error');

        Notification::assertNothingSent();
        $this->assertCount(0, $this->logs($closed, 'complaint.warning_issued')->where('metadata.summary', 'Warning emailed to the counselor')->where('created_at', '>=', now()->subMinute()));
    }

    public function test_the_warning_email_never_contains_the_complaint_text(): void
    {
        $c = $this->openComplaint();
        $mail = (new CounselorComplaintWarning('Please arrive on time for every session.'))->toMail($c->counselor);
        $text = strip_tags(implode(' ', array_merge($mail->introLines, $mail->outroLines)));

        $this->assertStringContainsString('arrive on time', $text);
        $this->assertStringNotContainsString($c->description, $text);
        $this->assertStringNotContainsString($c->reporter->name, $text);
    }

    public function test_suspending_from_a_complaint_uses_source_complaint_and_is_audited(): void
    {
        $c = $this->openComplaint();
        $profile = $c->counselorProfile;

        $this->as()->post(route('platform.complaints.suspend-counselor', $c), ['reason' => 'Repeated complaints about conduct.', 'sessions' => 'keep'])
            ->assertSessionHas('status');

        $this->assertSame(VerificationStatus::Suspended, $profile->fresh()->verification_status);
        $this->assertSame('Repeated complaints about conduct.', $this->logs($c, 'complaint.counselor_suspended')->sole()->metadata['reason']);
        $this->assertTrue(AuditLog::where('target_type', 'counselor')->where('target_id', $profile->id)->get()->contains(fn ($l) => ($l->metadata['source'] ?? null) === 'complaint'));
        // It does not close the complaint by itself.
        $this->assertSame(ComplaintStatus::Open, $c->fresh()->status);
    }

    public function test_suspending_needs_a_reason_and_an_approved_counselor(): void
    {
        $c = $this->openComplaint();

        $this->as()->post(route('platform.complaints.suspend-counselor', $c), ['reason' => ''])->assertSessionHasErrorsIn('suspend', 'reason');
        $this->assertSame(VerificationStatus::Approved, $c->counselorProfile->fresh()->verification_status);

        $c->counselorProfile->update(['verification_status' => VerificationStatus::Suspended->value]);
        $this->as()->post(route('platform.complaints.suspend-counselor', $c), ['reason' => 'Repeated complaints about conduct.'])
            ->assertSessionHas('error');
        $this->assertCount(0, $this->logs($c, 'complaint.counselor_suspended'));
    }

    public function test_no_suspending_from_a_closed_complaint(): void
    {
        $c = $this->closedComplaint();

        $this->as()->post(route('platform.complaints.suspend-counselor', $c), ['reason' => 'Repeated complaints about conduct.'])
            ->assertSessionHas('error');

        $this->assertCount(0, $this->logs($c, 'complaint.counselor_suspended'));
    }

    public function test_only_platform_admins_can_act(): void
    {
        $c = $this->openComplaint();
        $orgAdmin = User::where('email', 'afif@umpsa.edu.my')->firstOrFail();
        $umpsa = Organization::where('name', 'like', 'Universiti Malaysia Pahang%')->firstOrFail();

        foreach (['status' => ['to' => 'under_review'], 'warn' => ['reason' => 'Please arrive on time.'], 'suspend-counselor' => ['reason' => 'Repeated conduct complaints.']] as $name => $body) {
            $this->flushSession();
            $this->actingAs($orgAdmin)->withSession(['current_org_id' => $umpsa->id, 'current_role' => 'org_admin'])
                ->post(route("platform.complaints.{$name}", $c), $body)->assertForbidden();
        }

        $this->assertSame(ComplaintStatus::Open, $c->fresh()->status);
    }

    public function test_the_page_shows_actions_while_open_and_the_outcome_once_closed(): void
    {
        $c = $this->openComplaint();
        $this->as()->get(route('platform.complaints.show', $c))->assertOk()
            ->assertSee('Handle this complaint')->assertSee('Start review')->assertSee('Suspend counselor');

        $closed = $this->closedComplaint();
        $this->as()->get(route('platform.complaints.show', $closed))->assertOk()
            ->assertSee('this complaint is closed')->assertDontSee('Start review')->assertDontSee('Suspend counselor');
    }
}
