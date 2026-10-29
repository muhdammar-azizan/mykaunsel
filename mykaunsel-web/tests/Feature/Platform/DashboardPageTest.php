<?php

namespace Tests\Feature\Platform;

use App\Enums\ComplaintStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\CounselorDocument;
use App\Models\LkmDirectorySnapshot;
use App\Models\Membership;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\PlatformSettings;
use App\Services\WaitlistHasher;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The Dashboard page: who may open it, that every widget is wired to DashboardService and has an empty state, the banner, the
 * greeting in the admin's timezone, that nothing private is printed, and what a full page load costs.
 */
class DashboardPageTest extends TestCase
{
    use AnalyticsFixtures;
    use RefreshDatabase;

    private const NOW = '2026-10-20 12:00:00';

    /** The most queries a full Dashboard load may run. Measured: 27 (SQLite, with the sign-in and layout queries). A jump means an N+1 crept in. */
    private const MAX_QUERIES = 40;

    private User $admin;

    private array $session;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::NOW);
        $this->admin = User::factory()->create(['name' => 'Aminah binti Yusof', 'timezone' => 'Asia/Kuala_Lumpur']);
        Membership::create(['user_id' => $this->admin->id, 'org_id' => $this->platform()->id, 'role' => MembershipRole::PlatformAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        $this->session = ['current_org_id' => $this->platform()->id, 'current_role' => 'platform_admin'];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function dashboard(?User $user = null)
    {
        return $this->actingAs($user ?? $this->admin)->withSession($this->session)->get(route('platform.dashboard'));
    }

    private function ago(int $days): string
    {
        return Carbon::parse(self::NOW)->subDays($days)->format('Y-m-d H:i:s');
    }

    private function approval(string $action, string $name, string $when, string $by = 'Admin Satu'): void
    {
        AuditLog::create([
            'actor_type' => 'user', 'actor_name' => $by, 'actor_role' => 'platform_admin', 'action' => $action,
            'category' => str_starts_with($action, 'organization') ? 'organizations' : 'counselors',
            'target_type' => str_starts_with($action, 'organization') ? 'organization' : 'counselor', 'target_id' => 1, 'target_name' => $name, 'created_at' => $when,
        ]);
    }

    // ---- access --------------------------------------------------------------------------------------------------------------------

    public function test_only_a_platform_admin_can_open_it(): void
    {
        $this->get(route('platform.dashboard'))->assertRedirect();

        $orgAdmin = User::factory()->create();
        $org = $this->org('university', 'active', $this->ago(30));
        Membership::create(['user_id' => $orgAdmin->id, 'org_id' => $org->id, 'role' => MembershipRole::OrgAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        $this->actingAs($orgAdmin)->withSession(['current_org_id' => $org->id, 'current_role' => 'org_admin'])
            ->get(route('platform.dashboard'))->assertForbidden();

        $this->actingAs($this->admin)->withSession(['current_org_id' => $this->platform()->id, 'current_role' => 'student'])
            ->get(route('platform.dashboard'))->assertForbidden();

        $this->dashboard()->assertOk();
    }

    // ---- empty --------------------------------------------------------------------------------------------------------------------

    public function test_an_empty_database_shows_every_widget_with_its_empty_state(): void
    {
        $page = $this->dashboard()->assertOk();

        $page->assertSee('Good evening, Aminah')->assertSee('Needs your attention')
            ->assertSee('Pending organizations')->assertSee('Pending counselors')->assertSee('Active complaints')->assertSee('Skipped DNS verification')
            ->assertSee("Nothing is waiting for review. You're all caught up.", false)
            ->assertSee('Platform stats')->assertSee('Overall')->assertSee('No session data yet')
            ->assertSee('Recent approvals')->assertSee('No approvals yet')
            ->assertDontSee('Sign-ups closed')->assertDontSee('more waiting');
    }

    // ---- greeting -------------------------------------------------------------------------------------------------------------------

    public function test_the_greeting_follows_the_admins_timezone(): void
    {
        Carbon::setTestNow('2026-10-20 22:00:00');                       // 06:00 in Kuala Lumpur, 18:00 in New York

        $this->dashboard()->assertSee('Good morning, Aminah');

        $this->admin->forceFill(['timezone' => 'America/New_York'])->save();
        $this->dashboard($this->admin->fresh())->assertSee('Good evening, Aminah')->assertDontSee('Good morning, Aminah');
    }

    // ---- cards, list, stats ---------------------------------------------------------------------------------------------------------

    public function test_the_cards_show_the_counts_and_link_to_the_tabs(): void
    {
        $this->org('university', 'pending', null, $this->ago(2));
        $this->org('university', 'pending', null, $this->ago(2));
        $this->counselor('independent', 'pending', null, $this->ago(2));
        $this->complaintOf(ComplaintStatus::Open);
        $this->complaintOf(ComplaintStatus::UnderReview);

        $page = $this->dashboard()->assertOk();

        $page->assertSee(route('platform.organizations', ['tab' => 'pending']), false)
            ->assertSee(route('platform.organizations', ['tab' => 'skipped']), false)
            ->assertSee(route('platform.counselors', ['tab' => 'pending']), false)
            ->assertSee(route('platform.complaints', ['tab' => 'open']), false)
            ->assertSee('1 open, 1 under review.');

        $cards = $page->viewData('data')['cards'];
        $this->assertSame([2, 1, 2, 0], array_column($cards, 'count'));
        // The badge is the sum of the four cards, none of which overlap now.
        $page->assertSee('<span class="grid h-[22px] min-w-[22px] place-items-center rounded-full bg-brand-blue px-1.5 text-[11px] font-bold text-white">5</span>', false);
    }

    private function complaintOf(ComplaintStatus $status, string $text = 'Ordinary words.'): Complaint
    {
        return Complaint::create(['reporter_user_id' => User::factory()->create()->id, 'counselor_user_id' => $this->counselor('independent', 'approved')->user_id, 'description' => $text, 'status' => $status]);
    }

    public function test_attention_rows_show_the_wait_in_amber_or_red_and_link_to_the_record(): void
    {
        $red = $this->org('university', 'pending', null, $this->ago(9));
        $amber = $this->org('corporate', 'pending', null, $this->ago(6));

        $page = $this->dashboard()->assertOk();

        $page->assertSee($red->name.' — organization awaiting approval')->assertSee($amber->name.' — organization awaiting approval');   // names are shown: it is a staff-only page
        $page->assertSee('<p class="text-[12px] font-semibold text-brand-red">Waiting 9 days</p>', false)
            ->assertSee('<p class="text-[12px] font-semibold text-[#B98417]">Waiting 6 days</p>', false)
            ->assertSee(route('platform.organizations.show', $red), false)
            ->assertSee(route('platform.organizations.show', $amber), false);

        $html = $page->getContent();
        $this->assertLessThan(strpos($html, 'Waiting 6 days'), strpos($html, 'Waiting 9 days'), 'the red one comes first');
    }

    public function test_more_than_eight_items_show_eight_and_a_view_all_line_with_where_to_find_the_rest(): void
    {
        foreach (range(6, 17) as $days) {
            $this->org('university', 'pending', null, $this->ago($days));
        }

        $page = $this->dashboard()->assertOk();

        $this->assertSame(8, substr_count($page->getContent(), 'Review →'));
        $page->assertSee('4 more waiting. View all:')
            ->assertSee('Organizations waiting for review (4)')
            ->assertSee(route('platform.organizations', ['tab' => 'pending']), false);
    }

    public function test_the_stats_use_the_new_definitions_and_the_labels_say_so(): void
    {
        $this->org('university', 'active', $this->ago(40));
        $this->org('corporate', 'suspended', $this->ago(40));

        $page = $this->dashboard()->assertOk();

        $page->assertSee('Sessions this month')->assertSee('Overall')
            ->assertDontSee('Bookings this month')->assertDontSee('This month</p>', false);
        $this->assertSame(2, $page->viewData('data')['stats']['organizations'], 'active + suspended');
    }

    public function test_with_session_data_the_no_session_note_goes_away(): void
    {
        $counselor = $this->counselor('independent', 'approved');
        $this->booking($counselor, $this->org('university', 'active', $this->ago(40)), 'completed', '2026-10-05 10:00');

        $this->dashboard()->assertOk()->assertDontSee('No session data yet');
    }

    // ---- recent approvals -----------------------------------------------------------------------------------------------------------

    public function test_recent_approvals_show_name_type_who_and_date_in_the_admins_timezone(): void
    {
        $this->approval('organization.approved', 'Universiti Contoh', '2026-10-19 17:00:00', 'Admin Satu');   // 20 Oct 01:00 in Kuala Lumpur
        $this->approval('counselor.approved', 'Dr. Aisyah', '2026-10-10 04:00:00', 'Admin Dua');

        $page = $this->dashboard()->assertOk();

        $page->assertSeeInOrder(['Name', 'Type', 'By', 'Date'])
            ->assertSeeInOrder(['Universiti Contoh', 'Organization', 'Admin Satu', '20 Oct 2026', 'Dr. Aisyah', 'Counselor', 'Admin Dua', '10 Oct 2026'])
            ->assertSee(route('platform.audit-log', ['actions' => 'organization.approved,counselor.approved']), false);
    }

    // ---- banner ----------------------------------------------------------------------------------------------------------------------

    private function waiting(string $type, int $howMany): void
    {
        foreach (range(1, $howMany) as $i) {
            $email = "w.{$type}.{$i}@example.test";
            WaitlistEntry::create(['type' => $type, 'email' => $email, 'email_hash' => app(WaitlistHasher::class)->emailHash($type, $email), 'status' => 'waiting']);
        }
    }

    public function test_the_banner_has_three_states_and_is_hidden_when_all_is_normal(): void
    {
        $this->dashboard()->assertOk()->assertDontSee('Sign-ups closed')->assertDontSee('Maintenance mode is ON')->assertDontSee('All sign-ups are closed');

        $settings = app(PlatformSettings::class);
        $settings->setRegistration($this->admin, ['organization', 'individual'], false);
        $this->waiting('organization', 3);
        $this->dashboard()->assertSee('Sign-ups closed: Organizations, Individuals.')->assertSee('3 organizations on the waitlist.')
            ->assertSee(route('platform.registration'), false)->assertDontSee('Maintenance mode is ON');

        $settings->setRegistration($this->admin, ['counselor'], false);
        $this->waiting('counselor', 1);
        $this->dashboard()->assertSee('All sign-ups are closed.')->assertSee('3 organizations, 1 counselor on the waitlist.');

        $settings->setMaintenance($this->admin, true);
        $page = $this->dashboard();
        $this->assertSame(1, substr_count($page->getContent(), 'Maintenance mode is ON'), 'one maintenance bar (the layout\'s), not two');
    }

    // ---- privacy ---------------------------------------------------------------------------------------------------------------------

    public function test_no_complaint_text_document_name_or_note_is_printed(): void
    {
        $profile = $this->counselor('independent', 'pending', null, $this->ago(9));
        LkmDirectorySnapshot::create(['kb_number' => $profile->kb_number, 'pa_number' => $profile->pa_number, 'full_name' => $profile->user->name, 'status' => 'Aktif', 'pa_valid_from' => '2025-01-01', 'pa_valid_until' => '2030-01-01']);
        CounselorDocument::create(['counselor_profile_id' => $profile->id, 'type' => 'cert', 'path' => 'docs/SECRET-PATH.pdf', 'original_name' => 'SECRET-DOCUMENT-NAME.pdf', 'size_bytes' => 10, 'status' => 'unreviewed', 'note' => 'SECRET-DOCUMENT-NOTE', 'uploaded_at' => now()]);
        $profile->reviewNotes()->create(['user_id' => $this->admin->id, 'body' => 'SECRET-COUNSELOR-NOTE']);
        $org = $this->org('university', 'pending', null, $this->ago(9));
        $org->reviewNotes()->create(['user_id' => $this->admin->id, 'body' => 'SECRET-ORG-NOTE']);
        $complaint = $this->complaintOf(ComplaintStatus::Open, 'SECRET-COMPLAINT-TEXT');
        $complaint->reviewNotes()->create(['user_id' => $this->admin->id, 'body' => 'SECRET-COMPLAINT-NOTE']);

        $html = $this->dashboard()->assertOk()->assertSee('Complaint #'.$complaint->id)->getContent();

        $this->assertStringContainsString($profile->user->name, $html, 'the fixture really is on the page');
        $this->assertStringNotContainsString('SECRET', $html);
    }

    // ---- hooks: Ask anything, quick actions, search, bell, menu ----------------------------------------------------------------------

    public function test_ask_anything_is_switched_off_and_does_not_pretend_to_be_ai(): void
    {
        $html = $this->dashboard()->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<input id="ai-ask"[^>]*\sdisabled\s/', $html);
        $this->assertMatchesRegularExpression('/disabled aria-disabled="true" aria-label="Send \(coming soon\)"/', $html);
        $this->assertMatchesRegularExpression('/disabled aria-disabled="true" aria-label="Attach \(coming soon\)"/', $html);
        $this->assertStringContainsString('Coming soon', $html);
        $this->assertStringNotContainsString('Powered by AI', $html);
        $this->assertStringNotContainsString('@submit', $html, 'nothing is submitted anywhere');
        $this->assertStringNotContainsString('<form class="group mt-5', $html);
    }

    public function test_the_quick_action_pills_are_links_to_pages_that_open(): void
    {
        $page = $this->dashboard()->assertOk();

        preg_match('/<nav[^>]*aria-label="Quick actions"[^>]*>(.*?)<\/nav>/s', $page->getContent(), $nav);
        preg_match_all('/<a href="([^"]+)"/', $nav[1], $links);
        $urls = array_map('html_entity_decode', $links[1]);

        $this->assertSame([
            route('platform.organizations', ['tab' => 'pending']),
            route('platform.counselors', ['tab' => 'pending']),
            route('platform.complaints', ['tab' => 'open']),
            route('platform.audit-log'),
            route('platform.lkm'),
        ], $urls);

        foreach ($urls as $url) {
            $this->actingAs($this->admin)->withSession($this->session)->get($url)->assertOk();
        }

        foreach (['Review organizations', 'Review counselors', 'View complaints', 'Audit log', 'LKM directory'] as $label) {
            $this->assertStringContainsString($label, $nav[1]);
        }
    }

    public function test_the_bell_is_switched_off_and_shows_no_false_dot(): void
    {
        $html = $this->dashboard()->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<button type="button" disabled aria-disabled="true" aria-label="Notifications \(coming soon\)"/', $html);
        $this->assertStringNotContainsString('border-2 border-cream bg-brand-red', $html, 'no red dot claiming something is waiting');
        $this->assertStringContainsString('Notifications — coming soon', $html);
    }

    public function test_the_search_palette_lists_the_sidebar_pages_only_and_searches_no_data(): void
    {
        $this->org('university', 'active', $this->ago(40))->forceFill(['name' => 'Secret Palette Org'])->saveQuietly();

        $palette = \Illuminate\Support\Facades\Blade::render('<x-platform.search-palette />');

        $this->assertStringContainsString('Go to a page', $palette);
        $this->assertStringNotContainsString('Search organizations, counselors, complaints', $palette);
        $this->assertStringNotContainsString('Secret Palette Org', $palette, 'no data is searched');

        foreach (collect(config('platform-nav'))->flatMap(fn ($section) => $section['items']) as $item) {
            $this->assertStringContainsString($item['label'], str_replace('\\\\u0026', '&', html_entity_decode($palette)));
        }

        $this->dashboard()->assertOk()->assertSee('Go to a page')->assertDontSee('Search platform');
    }

    public function test_the_avatar_menu_links_open_and_the_logo_links_home(): void
    {
        $page = $this->dashboard()->assertOk()->assertSee($this->admin->email)->assertSee('Platform Admin');

        foreach ([route('platform.account'), route('platform.account.security'), route('platform.audit-log'), route('platform.account.notifications')] as $url) {
            $page->assertSee('href="'.$url.'"', false);
            $this->actingAs($this->admin)->withSession($this->session)->get($url)->assertOk();
        }

        $page->assertSee('href="'.route('platform.account.security').'#activity"', false)
            ->assertSee('<a href="'.route('platform.dashboard').'" aria-label="MyKaunsel"', false)
            ->assertSee(asset('images/mykaunsel-icon.png'), false);
        $this->assertFileExists(public_path('images/mykaunsel-icon.png'));
    }

    // ---- no sample text left ----------------------------------------------------------------------------------------------------------

    public function test_no_sample_text_from_the_design_is_left_in_the_platform_views(): void
    {
        $samples = ['Universiti XYZ', 'Delta Logistics', 'Ahmad bin Ismail', 'Sarah Lim', 'Farid Rahman', 'Nurul Huda', 'Lorem ipsum'];
        $folders = [resource_path('views/platform'), resource_path('views/components/platform'), resource_path('js')];

        foreach ($folders as $folder) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS)) as $file) {
                $text = file_get_contents($file->getPathname());

                foreach ($samples as $sample) {
                    $this->assertStringNotContainsString($sample, $text, $file->getPathname());
                }
            }
        }
    }

    // ---- cost ------------------------------------------------------------------------------------------------------------------------

    public function test_a_full_page_load_stays_under_the_query_limit_however_much_data_there_is(): void
    {
        $this->dashboard()->assertOk();            // warm-up (settings, first-use work)

        $count = function () {
            $n = 0;
            DB::listen(function () use (&$n) {
                $n++;
            });
            $this->dashboard()->assertOk();

            return $n;
        };

        foreach (range(1, 4) as $i) {
            $this->org('university', 'pending', null, $this->ago(6 + $i));
            $this->counselor('independent', 'pending', null, $this->ago(6 + $i));
            $this->complaintOf(ComplaintStatus::Open);
            $this->approval('organization.approved', "Org {$i}", $this->ago($i));
        }
        $few = $count();

        foreach (range(1, 30) as $i) {
            $this->org('corporate', 'pending', null, $this->ago(6 + $i));
            $this->counselor('independent', 'pending', null, $this->ago(6 + $i));
            $this->complaintOf(ComplaintStatus::Open);
            $this->approval('counselor.approved', "Counselor {$i}", $this->ago($i));
        }
        $many = $count();

        $this->assertSame($few, $many, 'the same queries for 4 or for 30 of each: no N+1');
        $this->assertLessThanOrEqual(self::MAX_QUERIES, $many, 'a full load is '.$many.' queries');
    }
}
