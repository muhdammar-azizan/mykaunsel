<?php

namespace Tests\Feature\Notifications;

use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use App\Models\LkmReviewRequest;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\Inbox\CoreNotificationTypes;
use App\Support\Inbox\NotificationRegistry;
use App\Support\Inbox\NotificationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Feature\Platform\AnalyticsFixtures;
use Tests\TestCase;

/**
 * The type registry: every core type is defined, its page is worked out on the server from the type and the target id, and "already
 * reviewed" / "gone" is decided for a whole list at once (no query per item).
 */
class NotificationRegistryTest extends TestCase
{
    use AnalyticsFixtures;
    use RefreshDatabase;

    private function registry(): NotificationRegistry
    {
        return app(NotificationRegistry::class);
    }

    private function notification(string $type, ?int $targetId, ?string $targetType): UserNotification
    {
        static $next = 0;

        return UserNotification::create([
            'recipient_user_id' => User::factory()->create()->id, 'type' => $type, 'target_type' => $targetType, 'target_id' => $targetId,
            'label' => 'x', 'occurrence_key' => 'k'.++$next, 'occurred_at' => now(),
        ]);
    }

    private function complaint(ComplaintStatus $status): Complaint
    {
        return Complaint::create([
            'reporter_user_id' => User::factory()->create()->id, 'counselor_user_id' => $this->counselor('independent', 'approved')->user_id,
            'description' => 'Ordinary words.', 'status' => $status,
        ]);
    }

    private function lkmRequest(string $status): LkmReviewRequest
    {
        $profile = $this->counselor('independent', 'approved');

        return LkmReviewRequest::create([
            'counselor_profile_id' => $profile->id, 'status' => $status, 'pending_for' => $status === LkmReviewRequest::PENDING ? $profile->id : null,
            'current' => ['pa_valid_until' => '2026-01-01'], 'claimed' => ['pa_valid_until' => '2027-01-01'],
            'evidence_path' => 'x.pdf', 'evidence_name' => 'x.pdf', 'evidence_size' => 10,
        ]);
    }

    // ---- the definitions ------------------------------------------------------------------------------------------------------------------

    public function test_every_type_of_the_brief_is_defined_in_the_right_group(): void
    {
        $expected = [
            'organization.application_submitted' => 'applications', 'organization.application_resubmitted' => 'applications',
            'counselor.application_submitted' => 'applications', 'counselor.application_resubmitted' => 'applications', 'counselor.kb_duplicate_flagged' => 'applications',
            'lkm.review_requested' => 'lkm',
            'complaint.received' => 'complaints', 'complaint.escalated' => 'complaints',
            'admin.invitation_accepted' => 'admins',
            'counselor.pa_expired_suspended' => 'system', 'system.maintenance_auto_reopened' => 'system',
        ];

        foreach ($expected as $key => $group) {
            $this->assertSame($group, $this->registry()->get($key)->group, $key);
            $this->assertNotSame('', $this->registry()->get($key)->title);
        }

        // (The mobile app's own types, group "activity", are checked in NotificationApiTest.)
        $admin = array_filter($this->registry()->all(), fn (NotificationType $t) => $t->group !== 'activity');
        $this->assertSame(array_keys($expected), array_keys($admin));
        $this->assertSame(['counselor.kb_duplicate_flagged'], array_keys(array_filter($this->registry()->all(), fn (NotificationType $t) => $t->coalesces())));
    }

    public function test_the_groups_of_the_full_page_each_have_types(): void
    {
        $this->assertSame(['applications', 'lkm', 'complaints', 'admins', 'system', 'activity'], array_keys(config('notifications.groups')));

        foreach (array_keys(config('notifications.groups')) as $group) {
            $this->assertNotEmpty($this->registry()->keysInGroup($group), $group);
        }
    }

    public function test_a_type_cannot_be_registered_twice_or_in_an_unknown_group(): void
    {
        $registry = new NotificationRegistry;
        CoreNotificationTypes::register($registry);

        foreach ([
            fn () => $registry->register(new NotificationType('lkm.review_requested', 'lkm', 'Again')),
            fn () => $registry->register(new NotificationType('moderation.new', 'nonsense', 'Nope')),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Expected a refusal.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // ---- the page is worked out on the server --------------------------------------------------------------------------------------------

    public function test_each_type_opens_the_page_defined_for_it_and_nothing_else(): void
    {
        $this->assertSame(route('platform.organizations.show', 7), $this->registry()->get('organization.application_submitted')->url(7));
        $this->assertSame(route('platform.organizations.show', 7), $this->registry()->get('organization.application_resubmitted')->url(7));
        $this->assertSame(route('platform.counselors.show', 7), $this->registry()->get('counselor.application_submitted')->url(7));
        $this->assertSame(route('platform.counselors.show', 7), $this->registry()->get('counselor.application_resubmitted')->url(7));
        $this->assertSame(route('platform.counselors.show', 7), $this->registry()->get('counselor.kb_duplicate_flagged')->url(7));
        $this->assertSame(route('platform.counselors.show', 7), $this->registry()->get('counselor.pa_expired_suspended')->url(7));
        $this->assertSame(route('platform.complaints.show', 7), $this->registry()->get('complaint.received')->url(7));
        $this->assertSame(route('platform.complaints.show', 7), $this->registry()->get('complaint.escalated')->url(7));
        $this->assertSame(route('platform.lkm.reviews'), $this->registry()->get('lkm.review_requested')->url(7));
        $this->assertSame(route('platform.admins'), $this->registry()->get('admin.invitation_accepted')->url(7));
        $this->assertSame(route('platform.registration'), $this->registry()->get('system.maintenance_auto_reopened')->url(null));
    }

    public function test_the_address_comes_only_from_the_type_never_from_what_is_stored(): void
    {
        // Whatever a row says in its label or occurrence key, the page is the one the TYPE defines for the target id.
        $row = $this->notification('organization.application_submitted', 5, 'organization');
        $row->forceFill(['label' => 'https://evil.example/phish', 'occurrence_key' => '//evil.example'])->save();

        $url = $this->registry()->get($row->type)->url($row->target_id);

        $this->assertSame(route('platform.organizations.show', 5), $url);
        $this->assertStringNotContainsString('evil', $url);
        $this->assertSame(route('platform.lkm.reviews'), $this->registry()->get('lkm.review_requested')->url(5), 'a type with a fixed page does not depend on the id');
    }

    // ---- already reviewed / gone ----------------------------------------------------------------------------------------------------------

    public function test_organization_and_counselor_applications_are_handled_once_decided(): void
    {
        $pendingOrg = $this->org('university', 'pending');
        $approvedOrg = $this->org('university', 'active');
        $rejectedOrg = $this->org('university', 'rejected');
        $pendingCounselor = $this->counselor('independent', 'pending');
        $approvedCounselor = $this->counselor('independent', 'approved');
        $rejectedCounselor = $this->counselor('independent', 'rejected');

        $org = $this->registry()->get('organization.application_submitted');
        $this->assertSame(
            [$pendingOrg->id => 'open', $approvedOrg->id => 'handled', $rejectedOrg->id => 'handled', 999999 => 'gone'],
            $org->states([$pendingOrg->id, $approvedOrg->id, $rejectedOrg->id, 999999]),
        );

        $counselor = $this->registry()->get('counselor.application_submitted');
        $this->assertSame(
            [$pendingCounselor->id => 'open', $approvedCounselor->id => 'handled', $rejectedCounselor->id => 'handled', 999999 => 'gone'],
            $counselor->states([$pendingCounselor->id, $approvedCounselor->id, $rejectedCounselor->id, 999999]),
        );
    }

    public function test_an_lkm_request_a_complaint_and_a_pa_suspension_follow_their_own_rules(): void
    {
        $waiting = $this->lkmRequest(LkmReviewRequest::PENDING);
        $decided = $this->lkmRequest(LkmReviewRequest::APPROVED);
        $this->assertSame([$waiting->id => 'open', $decided->id => 'handled'], $this->registry()->get('lkm.review_requested')->states([$waiting->id, $decided->id]));

        $open = $this->complaint(ComplaintStatus::Open);
        $underReview = $this->complaint(ComplaintStatus::UnderReview);
        $resolved = $this->complaint(ComplaintStatus::Resolved);
        $dismissed = $this->complaint(ComplaintStatus::Dismissed);
        $this->assertSame(
            [$open->id => 'open', $underReview->id => 'open', $resolved->id => 'handled', $dismissed->id => 'handled'],
            $this->registry()->get('complaint.escalated')->states([$open->id, $underReview->id, $resolved->id, $dismissed->id]),
        );

        $suspended = $this->counselor('independent', 'suspended');
        $reactivated = $this->counselor('independent', 'approved');
        $this->assertSame([$suspended->id => 'open', $reactivated->id => 'handled'], $this->registry()->get('counselor.pa_expired_suspended')->states([$suspended->id, $reactivated->id]));
    }

    public function test_a_heads_up_has_no_handled_state_only_gone_when_deleted(): void
    {
        $profile = $this->counselor('independent', 'approved');
        $user = User::factory()->create();

        $this->assertSame([$profile->id => 'open', 999999 => 'gone'], $this->registry()->get('counselor.kb_duplicate_flagged')->states([$profile->id, 999999]));
        $this->assertSame([$user->id => 'open', 999999 => 'gone'], $this->registry()->get('admin.invitation_accepted')->states([$user->id, 999999]));
    }

    public function test_a_type_without_a_target_is_always_open(): void
    {
        $row = $this->notification('system.maintenance_auto_reopened', null, null);

        $this->assertSame([$row->id => 'open'], $this->registry()->states(collect([$row])));
    }

    public function test_a_whole_list_is_resolved_with_one_query_per_kind_not_one_per_item(): void
    {
        $rows = collect();

        foreach (range(1, 6) as $i) {
            $rows->push($this->notification('organization.application_submitted', $this->org('university', $i % 2 ? 'pending' : 'active')->id, 'organization'));
            $rows->push($this->notification('counselor.application_submitted', $this->counselor('independent', $i % 2 ? 'pending' : 'approved')->id, 'counselor_profile'));
            $rows->push($this->notification('complaint.received', $this->complaint(ComplaintStatus::Open)->id, 'complaint'));
        }
        $rows->push($this->notification('system.maintenance_auto_reopened', null, null));

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $states = $this->registry()->states($rows);

        $this->assertCount(19, $states);
        $this->assertSame(3, $queries, 'three kinds with a target = three queries, for 19 items');
        $this->assertSame(['open', 'handled'], array_values(array_unique(array_slice($states, 0, 2))));
    }

    public function test_an_unknown_type_in_an_old_row_is_shown_as_open_not_broken(): void
    {
        $row = $this->notification('retired.type', 1, 'organization');

        $this->assertSame([$row->id => 'open'], $this->registry()->states(collect([$row])));
    }

    // ---- wording --------------------------------------------------------------------------------------------------------------------------

    public function test_a_coalesced_row_says_how_many_attempts(): void
    {
        $type = $this->registry()->get('counselor.kb_duplicate_flagged');

        $this->assertNull($type->detail(1));
        $this->assertSame('2 attempts', $type->detail(2));
        $this->assertSame('3 attempts', $type->detail(3));
        $this->assertNull($this->registry()->get('lkm.review_requested')->detail(5), 'only coalescing types show a count');
    }
}
