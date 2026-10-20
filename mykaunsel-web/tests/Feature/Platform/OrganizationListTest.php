<?php

namespace Tests\Feature\Platform;

use App\Enums\AccessModel;
use App\Enums\DomainMatchType;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Models\Membership;
use App\Models\OrgDomain;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\OrganizationReviewSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private array $session;

    protected function setUp(): void
    {
        parent::setUp();

        $platform = Organization::create([
            'name' => 'MyKaunsel Platform',
            'org_type' => OrgType::Platform,
            'access_model' => AccessModel::Open,
            'subscription_status' => OrgStatus::Active,
        ]);

        $this->admin = User::factory()->create();
        Membership::create([
            'user_id' => $this->admin->id,
            'org_id' => $platform->id,
            'role' => MembershipRole::PlatformAdmin,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);
        $this->session = ['current_org_id' => $platform->id, 'current_role' => 'platform_admin'];
    }

    private function openList(array $query = [])
    {
        return $this->actingAs($this->admin)->withSession($this->session)
            ->get(route('platform.organizations', $query));
    }

    private function org(string $name, OrgStatus $status = OrgStatus::Pending, OrgType $type = OrgType::Corporate, array $extra = []): Organization
    {
        return Organization::create($extra + [
            'name' => $name,
            'org_type' => $type,
            'access_model' => AccessModel::Closed,
            'subscription_status' => $status,
            'submitted_at' => now()->subDay(),
        ]);
    }

    private function admin(Organization $org, string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        Membership::create(['user_id' => $user->id, 'org_id' => $org->id, 'role' => MembershipRole::OrgAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        return $user;
    }

    private function domain(Organization $org, bool $verified): void
    {
        OrgDomain::create([
            'org_id' => $org->id, 'domain' => strtolower(str_replace(' ', '', $org->name)).'.edu.my',
            'default_role' => MembershipRole::Student, 'match_type' => DomainMatchType::Exact, 'dns_verified' => $verified,
        ]);
    }

    public function test_only_platform_admins_can_open_the_page(): void
    {
        $this->openList()->assertOk();

        $org = $this->org('Org Biasa', OrgStatus::Active, OrgType::University);
        $member = $this->admin($org, 'orgadmin@example.com');
        $this->flushSession();
        $this->actingAs($member)->withSession(['current_org_id' => $org->id, 'current_role' => 'org_admin'])
            ->get(route('platform.organizations'))->assertForbidden();

        auth()->logout();
        $this->get(route("platform.organizations"))->assertRedirect();
    }

    public function test_tab_counts_separate_pending_skipped_approved_and_rejected(): void
    {
        $this->org('Pending Satu');
        $this->org('Pending Dua');
        $skipped = $this->org('Skipped Satu', type: OrgType::University);
        $this->domain($skipped, false);
        $verified = $this->org('Verified Satu', type: OrgType::University);
        $this->domain($verified, true);
        $this->org('Active Satu', OrgStatus::Active);
        $this->org('Suspended Satu', OrgStatus::Suspended);
        $this->org('Rejected Satu', OrgStatus::Rejected);

        $counts = $this->openList()->viewData('counts');

        $this->assertSame(['pending' => 3, 'skipped' => 1, 'approved' => 2, 'rejected' => 1], $counts);
    }

    public function test_platform_organization_is_never_listed(): void
    {
        $this->openList(['tab' => 'approved'])->assertDontSee('MyKaunsel Platform');
        $this->assertSame(0, $this->openList()->viewData('counts')['approved']);
    }

    public function test_default_order_is_oldest_waiting_first_and_sort_can_change(): void
    {
        $this->org('Baru', extra: ['submitted_at' => now()->subDay()]);
        $this->org('Lama', extra: ['submitted_at' => now()->subDays(7)]);
        $this->org('Sederhana', extra: ['submitted_at' => now()->subDays(3)]);

        $names = fn ($r) => $r->viewData('organizations')->pluck('name')->all();

        $this->assertSame(['Lama', 'Sederhana', 'Baru'], $names($this->openList()));
        $this->assertSame(['Baru', 'Sederhana', 'Lama'], $names($this->openList(['sort' => 'newest'])));
        $this->assertSame(['Baru', 'Lama', 'Sederhana'], $names($this->openList(['sort' => 'name'])));
    }

    public function test_search_matches_name_or_admin_email_and_ignores_wildcards(): void
    {
        $a = $this->org('Syarikat Alpha');
        $this->admin($a, 'siti@alpha.com');
        $b = $this->org('Syarikat Beta');
        $this->admin($b, 'ali@beta.com');

        $names = fn ($q) => $this->openList(['q' => $q])->viewData('organizations')->pluck('name')->all();

        $this->assertSame(['Syarikat Alpha'], $names('alp'));
        $this->assertSame(['Syarikat Beta'], $names('ali@beta'));
        $this->assertCount(2, $names('syarikat'));
        $this->assertSame([], $names('tiada'));
        // "%" must not turn into "match everything"
        $this->assertCount(2, $names('%'), 'wildcards are stripped, leaving an empty search');
        $this->assertSame([], $names('zz%zz'));
    }

    public function test_search_and_type_filter_also_narrow_the_tab_counts(): void
    {
        $this->org('Kolej Contoh', type: OrgType::University);
        $this->org('Klinik Contoh', type: OrgType::Clinic);
        $this->org('Syarikat Lain', type: OrgType::Corporate);

        $this->assertSame(1, $this->openList(['type' => 'clinic'])->viewData('counts')['pending']);
        $this->assertSame(2, $this->openList(['q' => 'contoh'])->viewData('counts')['pending']);
        $this->assertSame(1, $this->openList(['q' => 'contoh', 'type' => 'university'])->viewData('counts')['pending']);
    }

    public function test_bad_query_values_fall_back_to_defaults(): void
    {
        $this->org('Satu');

        $response = $this->openList(['tab' => 'hacked', 'type' => 'platform', 'sort' => 'drop table', 'q' => ['array']]);

        $response->assertOk();
        $this->assertSame('pending', $response->viewData('tab'));
        $this->assertNull($response->viewData('type'));
        $this->assertSame('oldest', $response->viewData('sort'));
    }

    public function test_waiting_time_tone_turns_amber_at_5_days_and_red_at_8(): void
    {
        $this->assertSame('ok', $this->org('A', extra: ['submitted_at' => now()->subDays(4)])->waitingTone());
        $this->assertSame('amber', $this->org('B', extra: ['submitted_at' => now()->subDays(5)])->waitingTone());
        $this->assertSame('amber', $this->org('C', extra: ['submitted_at' => now()->subDays(7)])->waitingTone());
        $this->assertSame('red', $this->org('D', extra: ['submitted_at' => now()->subDays(8)])->waitingTone());
    }

    public function test_pending_cards_show_checks_and_resubmitted_badge(): void
    {
        $this->seed(OrganizationReviewSeeder::class);

        $pending = $this->openList();
        $pending->assertOk()
            ->assertSee('Universiti Teknologi Contoh')
            ->assertSee('Klinik Minda Sihat')
            ->assertSee('Syarikat Tanpa SSM Sdn Bhd')
            ->assertSee('Syarikat Hantar Semula Berhad')
            ->assertSee('Resubmitted')
            ->assertSee('Not applicable')   // clinic: no domain check
            ->assertSee('Missing')          // corporate without SSM
            ->assertDontSee('Kolej Universiti Skip DNS'); // lives in the skipped tab

        $this->openList(['tab' => 'skipped'])->assertSee('Kolej Universiti Skip DNS')->assertSee('Skipped');
    }

    public function test_approved_tab_shows_member_count_and_status(): void
    {
        $org = $this->org('Universiti Aktif', OrgStatus::Active, OrgType::University, ['reviewed_at' => now()->subDays(10)]);
        foreach (range(1, 3) as $i) {
            $m = User::factory()->create();
            Membership::create(['user_id' => $m->id, 'org_id' => $org->id, 'role' => MembershipRole::Student, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        }
        $off = User::factory()->create();
        Membership::create(['user_id' => $off->id, 'org_id' => $org->id, 'role' => MembershipRole::Student, 'status' => MembershipStatus::Offboarded, 'joined_at' => now()]);
        $this->admin($org, 'a@aktif.com'); // org admins are not "members"
        $this->org('Klinik Digantung', OrgStatus::Suspended, OrgType::Clinic);

        $response = $this->openList(['tab' => 'approved']);

        $response->assertOk()->assertSee('Universiti Aktif')->assertSee('Klinik Digantung')->assertSee('Suspended')->assertSee('Active');
        $row = $response->viewData('organizations')->firstWhere('name', 'Universiti Aktif');
        $this->assertSame(3, $row->members_count);
    }

    public function test_rejected_tab_shows_reason_and_who_rejected(): void
    {
        $this->org('Syarikat Ditolak', OrgStatus::Rejected, extra: [
            'rejection_reason' => 'SSM tamat tempoh', 'reviewed_by' => $this->admin->id, 'reviewed_at' => now()->subDay(),
        ]);

        $this->openList(['tab' => 'rejected'])->assertSee('Syarikat Ditolak')->assertSee('SSM tamat tempoh')->assertSee($this->admin->name);
    }

    public function test_pagination_and_empty_states(): void
    {
        foreach (range(1, 12) as $i) {
            $this->org(sprintf('Org %02d', $i));
        }

        $page1 = $this->openList();
        $this->assertCount(10, $page1->viewData('organizations'));
        $this->assertCount(2, $this->openList(['page' => 2])->viewData('organizations'));

        $this->openList(['tab' => 'rejected'])->assertSee('No rejected applications');
        $this->openList(['q' => 'tiada-langsung'])->assertSee('No organizations match your search');
    }
}
