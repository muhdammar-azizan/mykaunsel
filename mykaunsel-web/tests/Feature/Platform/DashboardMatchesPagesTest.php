<?php

namespace Tests\Feature\Platform;

use App\Models\Organization;
use App\Services\AnalyticsService;
use App\Services\DashboardService;
use Database\Seeders\ComplaintReviewSeeder;
use Database\Seeders\OrganizationReviewSeeder;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Counselor\CounselorTestCase;

/**
 * On the real sample data, every number the Dashboard service gives is the number the owning page shows (its tab count), and an
 * action an admin takes shows in the very next read: nothing is kept in between.
 */
class DashboardMatchesPagesTest extends CounselorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedSamples();
        $this->seed([OrganizationReviewSeeder::class, ComplaintReviewSeeder::class]);
    }

    private function service(): DashboardService
    {
        return app(DashboardService::class);
    }

    public function test_each_card_equals_the_count_on_the_tab_it_links_to(): void
    {
        $cards = $this->service()->cards();

        $organizations = $this->as()->get(route('platform.organizations'))->assertOk()->viewData('counts');
        $this->assertSame($organizations['pending'], $cards['pending_organizations']['count'], 'Organizations → Pending');
        $this->assertSame($organizations['skipped'], $cards['skipped_dns']['count'], 'Organizations → Skipped verification');

        $this->assertSame($this->listPage()->viewData('counts')['pending'], $cards['pending_counselors']['count'], 'Counselors → Pending');

        $complaints = $this->as()->get(route('platform.complaints'))->assertOk()->viewData('counts');
        $this->assertSame($complaints['open'], $cards['active_complaints']['open'], 'Complaints → Open');
        $this->assertSame($complaints['under_review'], $cards['active_complaints']['under_review'], 'Complaints → Under review');
        $this->assertSame($complaints['open'] + $complaints['under_review'], $cards['active_complaints']['count']);

        // The sample data really has something on every card, so the comparison above is not 0 = 0.
        $this->assertGreaterThan(0, $cards['pending_organizations']['count']);
        $this->assertGreaterThan(0, $cards['pending_counselors']['count']);
        $this->assertGreaterThan(0, $cards['skipped_dns']['count']);
        $this->assertGreaterThan(0, $cards['active_complaints']['count']);
    }

    public function test_each_card_link_opens_a_page_that_shows_that_tab(): void
    {
        foreach ($this->service()->cards() as $card) {
            $this->as()->get($card['url'])->assertOk();
        }
    }

    public function test_the_stats_equal_the_analytics_page_cards(): void
    {
        $stats = $this->service()->stats('Asia/Kuala_Lumpur');
        $cards = app(AnalyticsService::class)->report(\App\Support\AnalyticsRange::preset(30, 'Asia/Kuala_Lumpur'))['cards'];

        $this->assertSame($cards['organizations']['value'], $stats['organizations']);
        $this->assertSame($cards['counselors']['value'], $stats['counselors']);
    }

    public function test_approving_an_organization_changes_the_next_read_with_nothing_stale(): void
    {
        Notification::fake();
        $before = $this->service()->build($this->admin);
        $org = Organization::where('name', 'Universiti Teknologi Contoh')->firstOrFail();
        $this->assertSame('pending', $org->subscription_status->value);

        $this->as()->post(route('platform.organizations.approve', $org))->assertSessionHas('status');

        $after = app(DashboardService::class)->build($this->admin);

        $this->assertSame($before['cards']['pending_organizations']['count'] - 1, $after['cards']['pending_organizations']['count']);
        $this->assertSame($before['stats']['organizations'] + 1, $after['stats']['organizations']);
        $this->assertSame('Universiti Teknologi Contoh', $after['approvals']['rows'][0]['name'], 'the audit log entry is already the newest approval');
        $this->assertSame('Organization', $after['approvals']['rows'][0]['type']);
        $this->assertSame($this->admin->name, $after['approvals']['rows'][0]['by']);
        $this->assertCount(count($before['approvals']['rows']) + 1 > 5 ? 5 : count($before['approvals']['rows']) + 1, $after['approvals']['rows']);
    }

    public function test_suspending_a_counselor_changes_the_next_read(): void
    {
        $profile = $this->profile('KB10234');                                  // an approved organization counselor
        $profile->forceFill(['pa_valid_until' => now()->addDays(10)->toDateString()])->saveQuietly();

        $expiringBefore = $this->service()->counselorsWithPaExpiringSoon();
        $this->assertGreaterThanOrEqual(1, $expiringBefore);
        $this->assertSame($this->listPage(['tab' => 'active', 'pa' => 'expiring'])->viewData('profiles')->total(), $expiringBefore, 'the same as the Counselors page filtered to PA expiring');
        $countBefore = $this->service()->stats('UTC')['counselors'];

        $this->as()->post(route('platform.counselors.suspend', $profile), ['reason' => 'Aduan berulang tentang kelakuan tidak profesional.'])->assertSessionHas('status');

        $this->assertSame($expiringBefore - 1, app(DashboardService::class)->counselorsWithPaExpiringSoon(), 'a suspended counselor is no longer an active one running out of PA');
        $this->assertSame($countBefore, app(DashboardService::class)->stats('UTC')['counselors'], 'approved counselors still include the suspended (the same as Analytics)');
    }

    public function test_closing_a_registration_type_shows_in_the_banner_on_the_next_read(): void
    {
        $this->assertSame('hidden', $this->service()->banner('UTC')['state']);

        $this->as()->post(route('platform.registration.toggle', 'counselor'), ['state' => 'closed'])->assertSessionHas('status');
        $closed = app(DashboardService::class)->banner('UTC');
        $this->assertSame('registration', $closed['state']);
        $this->assertSame(['counselor' => 'Counselors'], $closed['closed']);

        $this->as()->post(route('platform.registration.toggle', 'counselor'), ['state' => 'open'])->assertSessionHas('status');
        $this->assertSame('hidden', app(DashboardService::class)->banner('UTC')['state']);
    }

    public function test_a_complaint_taken_up_leaves_the_open_card_and_the_list_at_once(): void
    {
        $before = $this->service()->build($this->admin);
        $open = \App\Models\Complaint::query()->where('status', 'open')->firstOrFail();

        $this->as()->post(route('platform.complaints.status', $open), ['to' => 'under_review', 'from' => 'open'])->assertSessionHas('status');

        $after = app(DashboardService::class)->build($this->admin);
        $this->assertSame($before['cards']['active_complaints']['open'] - 1, $after['cards']['active_complaints']['open']);
        $this->assertSame($before['cards']['active_complaints']['under_review'] + 1, $after['cards']['active_complaints']['under_review']);
        $this->assertSame($before['cards']['active_complaints']['count'], $after['cards']['active_complaints']['count']);
        $this->assertSame($before['attention']['total'] - 1, $after['attention']['total'], 'an open complaint is an item; once taken up it is not');
    }
}
