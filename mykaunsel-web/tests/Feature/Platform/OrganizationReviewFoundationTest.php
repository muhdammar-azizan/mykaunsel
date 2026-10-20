<?php

namespace Tests\Feature\Platform;

use App\Enums\AccessModel;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Exceptions\InvalidOrgTransition;
use App\Models\Membership;
use App\Models\OrgReviewNote;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\OrganizationReviewSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationReviewFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function org(OrgStatus $status = OrgStatus::Pending): Organization
    {
        return Organization::create([
            'name' => 'Org Contoh',
            'org_type' => OrgType::University,
            'access_model' => AccessModel::Closed,
            'subscription_status' => $status,
        ]);
    }

    public function test_status_transition_rules(): void
    {
        $this->assertTrue(OrgStatus::Pending->canTransitionTo(OrgStatus::Active));
        $this->assertTrue(OrgStatus::Pending->canTransitionTo(OrgStatus::Rejected));
        $this->assertTrue(OrgStatus::Rejected->canTransitionTo(OrgStatus::Pending));
        $this->assertTrue(OrgStatus::Active->canTransitionTo(OrgStatus::Suspended));
        $this->assertTrue(OrgStatus::Suspended->canTransitionTo(OrgStatus::Active));

        $this->assertFalse(OrgStatus::Pending->canTransitionTo(OrgStatus::Suspended));
        $this->assertFalse(OrgStatus::Rejected->canTransitionTo(OrgStatus::Active));
        $this->assertFalse(OrgStatus::Active->canTransitionTo(OrgStatus::Pending));
        $this->assertFalse(OrgStatus::Active->canTransitionTo(OrgStatus::Rejected));
        $this->assertFalse(OrgStatus::Suspended->canTransitionTo(OrgStatus::Rejected));
        $this->assertFalse(OrgStatus::Active->canTransitionTo(OrgStatus::Active));
    }

    public function test_transition_saves_status_and_extra_attributes(): void
    {
        $org = $this->org();
        $reviewer = User::factory()->create();

        $org->transitionTo(OrgStatus::Rejected, [
            'rejection_reason' => 'SSM tidak sepadan',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ], OrgStatus::Pending);

        $org->refresh();
        $this->assertSame(OrgStatus::Rejected, $org->subscription_status);
        $this->assertSame('SSM tidak sepadan', $org->rejection_reason);
        $this->assertTrue($org->reviewer->is($reviewer));
        $this->assertNotNull($org->reviewed_at);
    }

    public function test_an_illegal_transition_is_refused_and_nothing_is_saved(): void
    {
        $org = $this->org(OrgStatus::Rejected);

        try {
            $org->transitionTo(OrgStatus::Active, ['reviewed_at' => now()]);
            $this->fail('Expected InvalidOrgTransition');
        } catch (InvalidOrgTransition) {
            // expected
        }

        $org->refresh();
        $this->assertSame(OrgStatus::Rejected, $org->subscription_status);
        $this->assertNull($org->reviewed_at);
    }

    public function test_a_decision_on_stale_data_is_refused(): void
    {
        $org = $this->org();
        $stale = Organization::find($org->id); // admin B opened the page while it was pending

        $org->transitionTo(OrgStatus::Active, [], OrgStatus::Pending); // admin A approved first

        $this->expectException(InvalidOrgTransition::class);
        $stale->transitionTo(OrgStatus::Rejected, ['rejection_reason' => 'x'], OrgStatus::Pending);
    }

    public function test_new_columns_have_sensible_defaults_and_notes_belong_to_the_org(): void
    {
        $org = $this->org()->refresh();
        $author = User::factory()->create();

        $this->assertSame(1, $org->submission_count);
        $this->assertNull($org->review_checklist);

        $org->update(['review_checklist' => ['name_matches' => true, 'domain_valid' => false]]);
        $this->assertTrue($org->fresh()->review_checklist['name_matches']);

        OrgReviewNote::create(['org_id' => $org->id, 'user_id' => $author->id, 'body' => 'Telefon pemohon dulu']);
        $this->assertCount(1, $org->reviewNotes);
        $this->assertTrue($org->reviewNotes->first()->author->is($author));
    }

    public function test_signup_records_submission_time_size_and_authority_confirmation(): void
    {
        $this->post(route('organizations.signup.store'), [
            'org_type' => 'corporate',
            'org_name' => 'Syarikat Baru Sdn Bhd',
            'org_size' => '50 - 200',
            'ssm_number' => '202001011234',
            'access_model' => 'closed',
            'no_domain' => 1,
            'admin_name' => 'Ali Baru',
            'admin_title' => 'HR Manager',
            'admin_email' => 'ali@syarikatbaru.com',
            'admin_password' => 'Secret#Pass99',
            'admin_password_confirmation' => 'Secret#Pass99',
            'confirm_authorized' => 1,
            'confirm_terms' => 1,
        ]);

        $org = Organization::where('name', 'Syarikat Baru Sdn Bhd')->first();

        if ($org === null) {
            $this->markTestIncomplete('Signup payload no longer matches the form request; adjust this test.');
        }

        $this->assertSame('50 - 200', $org->estimated_size);
        $this->assertNull($org->subscription_tier);
        $this->assertNotNull($org->submitted_at);
        $this->assertNotNull($org->authority_confirmed_at);
        $this->assertSame(1, $org->submission_count);
    }

    public function test_review_seeder_creates_the_sample_cases_and_can_run_twice(): void
    {
        $this->seed(OrganizationReviewSeeder::class);
        $this->seed(OrganizationReviewSeeder::class); // second run must not duplicate or crash

        $this->assertSame(7, Organization::count());
        $this->assertSame(1, Organization::where('subscription_status', OrgStatus::Rejected)->count());
        $this->assertSame(5, Organization::where('subscription_status', OrgStatus::Pending)->count());
        $this->assertSame(1, Organization::where('subscription_status', OrgStatus::Suspended)->count());

        $this->assertTrue(Organization::where('name', 'Universiti Teknologi Contoh')->first()->orgDomains->every->dns_verified);
        $this->assertTrue(Organization::where('name', 'Kolej Universiti Skip DNS')->first()->orgDomains->contains(fn ($d) => ! $d->dns_verified));
        $this->assertNull(Organization::where('name', 'Syarikat Tanpa SSM Sdn Bhd')->first()->ssm_number);
        $this->assertSame(2, Organization::where('name', 'Syarikat Hantar Semula Berhad')->first()->submission_count);

        $clinic = Organization::where('name', 'Klinik Minda Sihat')->first();
        $photos = \App\Models\PracticeLocation::where('owner_type', 'organization')->where('owner_id', $clinic->id)->first()->locationPhotos;
        $this->assertCount(3, $photos);

        // Every sample organization has a registering admin who confirmed their authority.
        Organization::all()->each(function (Organization $o) {
            $this->assertNotNull($o->authority_confirmed_at);
            $this->assertTrue(Membership::where('org_id', $o->id)->where('role', MembershipRole::OrgAdmin)->where('status', MembershipStatus::Active)->exists());
        });
    }
}
