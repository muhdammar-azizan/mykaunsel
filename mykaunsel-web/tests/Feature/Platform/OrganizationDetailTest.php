<?php

namespace Tests\Feature\Platform;

use App\Enums\AccessModel;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\OrganizationReviewSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Organization $platform;

    private array $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = Organization::create([
            'name' => 'MyKaunsel Platform',
            'org_type' => OrgType::Platform,
            'access_model' => AccessModel::Open,
            'subscription_status' => OrgStatus::Active,
        ]);

        $this->admin = User::factory()->create();
        Membership::create([
            'user_id' => $this->admin->id,
            'org_id' => $this->platform->id,
            'role' => MembershipRole::PlatformAdmin,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);
        $this->session = ['current_org_id' => $this->platform->id, 'current_role' => 'platform_admin'];
    }

    private function show(Organization $org)
    {
        return $this->actingAs($this->admin)->withSession($this->session)
            ->get(route('platform.organizations.show', $org));
    }

    private function named(string $name): Organization
    {
        return Organization::where('name', $name)->firstOrFail();
    }

    public function test_only_platform_admins_can_open_a_detail_page(): void
    {
        $this->seed(OrganizationReviewSeeder::class);
        $org = $this->named('Universiti Teknologi Contoh');
        $orgAdmin = $org->registrant->user;

        $this->flushSession();
        $this->actingAs($orgAdmin)->withSession(['current_org_id' => $org->id, 'current_role' => 'org_admin'])
            ->get(route('platform.organizations.show', $org))->assertForbidden();
    }

    public function test_the_platform_organization_itself_has_no_detail_page(): void
    {
        $this->show($this->platform)->assertNotFound();
    }

    public function test_unknown_organization_is_404(): void
    {
        $this->actingAs($this->admin)->withSession($this->session)
            ->get('/platform/organizations/999999')->assertNotFound();
    }

    public function test_university_with_verified_dns_shows_all_cards(): void
    {
        $this->seed(OrganizationReviewSeeder::class);

        $this->show($this->named('Universiti Teknologi Contoh'))
            ->assertOk()
            ->assertSee('Universiti Teknologi Contoh')
            ->assertSee('Attempt 1')
            ->assertSee('Organization info')
            ->assertSee('5,000 - 20,000')
            ->assertSee('Closed')
            ->assertSee('Domain verification')
            ->assertSee('utc.edu.my')
            ->assertSee('student.utc.edu.my')
            ->assertSee('2 of 2 verified')
            ->assertDontSee('Needs a manual check')
            ->assertSee('Optional for university')
            ->assertSee('No location provided — can be added later')
            ->assertSee('Dr. Hafiz Rahman')
            ->assertSee('Registrar')
            ->assertSee('hafiz@utc.edu.my')
            ->assertSee('Matches utc.edu.my')
            ->assertSee('Confirmed');
    }

    public function test_skipped_dns_shows_a_manual_check_warning(): void
    {
        $this->seed(OrganizationReviewSeeder::class);

        $this->show($this->named('Kolej Universiti Skip DNS'))
            ->assertOk()
            ->assertSee('Needs a manual check')
            ->assertSee('0 of 1 verified')
            ->assertSee('Pending')
            ->assertSee('Auto-checks until')
            ->assertSee('3 checks');
    }

    public function test_corporate_without_ssm_shows_not_provided_and_missing(): void
    {
        $this->seed(OrganizationReviewSeeder::class);

        $this->show($this->named('Syarikat Tanpa SSM Sdn Bhd'))
            ->assertOk()
            ->assertSee('Not provided')
            ->assertSee('Missing — required')
            ->assertSee('Required for corporate')
            ->assertSee('https://www.ssm.com.my', false);
    }

    public function test_ssm_format_is_validated_to_twelve_digits(): void
    {
        $org = new Organization(['ssm_number' => '202301045678']);
        $this->assertTrue($org->hasValidSsmFormat());

        foreach (['2023010456', '2023010456789', '2023-0104-5678', 'ABCDEFGHIJKL', null, ''] as $bad) {
            $this->assertFalse((new Organization(['ssm_number' => $bad]))->hasValidSsmFormat(), var_export($bad, true));
        }

        $org = Organization::create(['name' => 'Salah', 'org_type' => OrgType::Corporate, 'access_model' => AccessModel::Closed, 'subscription_status' => OrgStatus::Pending, 'ssm_number' => '12345']);
        $this->show($org)->assertSee('Not a 12-digit number');
    }

    public function test_clinic_hides_domain_card_and_shows_location_and_photos(): void
    {
        $this->seed(OrganizationReviewSeeder::class);

        $this->show($this->named('Klinik Minda Sihat'))
            ->assertOk()
            ->assertDontSee('Domain verification')
            ->assertSee('Klinik Minda Sihat - Shah Alam')
            ->assertSee('Jalan Kemuning 33/2')
            ->assertSee('Premises photos')
            ->assertSee('Premises photo 3')
            ->assertSee('No domain to compare');
    }

    public function test_clinic_without_location_or_photos_is_flagged(): void
    {
        $clinic = Organization::create(['name' => 'Klinik Kosong', 'org_type' => OrgType::Clinic, 'access_model' => AccessModel::Open, 'subscription_status' => OrgStatus::Pending]);

        $this->show($clinic)
            ->assertOk()
            ->assertSee('No location provided — required for clinics')
            ->assertSee('No premises photos — required for clinics');
    }

    public function test_resubmitted_application_shows_attempt_and_earlier_rejection(): void
    {
        $this->seed(OrganizationReviewSeeder::class);

        $this->show($this->named('Syarikat Hantar Semula Berhad'))
            ->assertOk()
            ->assertSee('Attempt 2')
            ->assertSee('Resubmitted')
            ->assertSee('Rejected earlier')
            ->assertSee('SSM number does not match the company name');
    }

    public function test_suspended_and_rejected_organizations_show_their_reason(): void
    {
        $this->seed(OrganizationReviewSeeder::class);

        $this->show($this->named('Pusat Kaunseling Digantung'))
            ->assertOk()->assertSee('Suspended')->assertSee('Repeated complaints about unlicensed practitioners');

        $this->show($this->named('Syarikat Ditolak Enterprise'))
            ->assertOk()->assertSee('Rejected')->assertSee('SSM certificate provided is expired');
    }

    public function test_admin_email_that_does_not_match_the_domain_is_flagged(): void
    {
        $this->seed(OrganizationReviewSeeder::class);
        $org = $this->named('Universiti Teknologi Contoh');
        $org->registrant->user->update(['email' => 'someone@gmail.com']);

        $this->show($org->fresh())->assertSee('Does not match any claimed domain');
    }

    public function test_list_links_to_the_detail_page(): void
    {
        $this->seed(OrganizationReviewSeeder::class);
        $org = $this->named('Universiti Teknologi Contoh');

        $this->actingAs($this->admin)->withSession($this->session)
            ->get(route('platform.organizations'))
            ->assertSee(route('platform.organizations.show', $org), false);
    }
}
