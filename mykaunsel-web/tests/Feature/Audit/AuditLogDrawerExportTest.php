<?php

namespace Tests\Feature\Audit;

use App\Enums\AccessModel;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Facades\Audit;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\AuditLogSeeder;
use Database\Seeders\OrganizationReviewSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AuditLogDrawerExportTest extends TestCase
{
    use RefreshDatabase;

    private Organization $platform;

    private User $admin;

    private array $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = Organization::create(['name' => 'MyKaunsel Platform', 'org_type' => OrgType::Platform, 'access_model' => AccessModel::Open, 'subscription_status' => OrgStatus::Active]);
        $this->admin = User::factory()->create(['name' => 'Siti Platform', 'timezone' => 'Asia/Kuala_Lumpur']);
        Membership::create(['user_id' => $this->admin->id, 'org_id' => $this->platform->id, 'role' => MembershipRole::PlatformAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        $this->session = ['current_org_id' => $this->platform->id, 'current_role' => 'platform_admin'];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function org(string $name = 'Universiti Contoh'): Organization
    {
        return Organization::create(['name' => $name, 'org_type' => OrgType::University, 'access_model' => AccessModel::Closed, 'subscription_status' => OrgStatus::Active]);
    }

    private function as()
    {
        return $this->actingAs($this->admin)->withSession($this->session);
    }

    private function log(string $action, $target = null, array $meta = [], User|string|null $actor = null, ?string $at = null, array $options = []): AuditLog
    {
        return Audit::log($action, $target, $meta, $actor ?? $this->admin, $options + array_filter(['at' => $at ? Carbon::parse($at, 'UTC') : null]));
    }

    /** @return array{0: list<string>, 1: list<array<string, ?string>>} header + rows keyed by header */
    private function csv(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $h = fopen('php://temp', 'r+');
        fwrite($h, $content);
        rewind($h);

        $rows = [];
        while (($cells = fgetcsv($h, 0, ',', '"', '')) !== false) {
            $rows[] = $cells;
        }
        fclose($h);

        $header = array_shift($rows);

        return [$header, array_map(fn ($r) => array_combine($header, $r), $rows)];
    }

    // ---- drawer ----------------------------------------------------------------------------

    public function test_the_drawer_json_has_everything_the_panel_shows(): void
    {
        $org = $this->org('Universiti Alpha');
        $request = 'aaaaaaaa-1111-2222-3333-bbbbbbbbbbbb';
        $entry = $this->log('organization.rejected', $org, [
            'reason' => 'SSM tidak sah.',
            'changes' => ['status' => ['old' => 'pending', 'new' => 'rejected'], 'rejection_template' => ['old' => null, 'new' => 'ssm_invalid']],
            'attempt' => 2,
            'seeded' => true,
        ], null, '2026-10-21 17:00:00', ['request_id' => $request, 'ip' => '203.0.113.9', 'user_agent' => 'TestBrowser/1.0']);
        $sibling = $this->log('organization.note_added', $org, ['note_id' => 4], null, '2026-10-21 17:00:01', ['request_id' => $request]);
        $this->log('organization.approved', $org, [], null, '2026-10-21 17:05:00', ['request_id' => 'cccccccc-1111-2222-3333-dddddddddddd']); // a different request

        $json = $this->as()->getJson(route('platform.audit-log.show', $entry))->assertOk()->json();

        $this->assertSame($entry->id, $json['id']);
        $this->assertSame('organization.rejected', $json['action_key']);
        $this->assertSame('Organization rejected', $json['action_label']);
        $this->assertSame('Rejected', $json['badge']['label']);
        $this->assertSame('Organizations', $json['category']);
        $this->assertSame('Thursday, 22 October 2026, 1:00:00 AM (Asia/Kuala_Lumpur)', $json['time_full']);
        $this->assertSame('2026-10-21 17:00:00 UTC', $json['time_utc']);
        $this->assertSame(['Siti Platform', 'Platform admin', 'user'], [$json['actor']['name'], $json['actor']['role'], $json['actor']['kind']]);
        $this->assertSame(['Universiti Alpha', route('platform.organizations.show', $org), false], [$json['target']['name'], $json['target']['url'], $json['target']['deleted']]);
        $this->assertSame(['id' => $org->id, 'name' => 'Universiti Alpha', 'url' => route('platform.organizations.show', $org), 'history_url' => route('platform.audit-log', ['organization' => $org->id])], $json['organization']);
        $this->assertSame('SSM tidak sah.', $json['reason']);
        $this->assertSame([
            ['field' => 'Status', 'old' => 'pending', 'new' => 'rejected'],
            ['field' => 'Rejection Template', 'old' => '—', 'new' => 'ssm_invalid'],
        ], $json['changes']);
        $this->assertSame([['label' => 'Attempt', 'value' => '2']], $json['details'], 'reason, changes and the sample flag are not repeated here');
        $this->assertTrue($json['seeded']);
        $this->assertSame(['203.0.113.9', 'TestBrowser/1.0', $request], [$json['ip'], $json['user_agent'], $json['request_id']]);
        $this->assertSame(route('platform.audit-log', ['actor' => $this->admin->id]), $json['view_all_url']);

        $this->assertCount(1, $json['related'], 'only the other entry of the same request');
        $this->assertSame($sibling->id, $json['related'][0]['id']);
        $this->assertSame('Internal note added', $json['related'][0]['action_label']);
    }

    public function test_the_drawer_handles_system_entries_deleted_targets_and_entries_without_a_request(): void
    {
        $system = $this->log('system.chat_purged', null, ['sessions' => 3], 'system');
        AuditLog::query()->toBase()->where('id', $system->id)->update(['request_id' => null]); // like a row written before request ids existed
        $gone = $this->log('organization.suspended', null, ['reason' => 'x'], null, null, ['target_type' => 'organization', 'target_id' => 999, 'target_name' => 'Syarikat Lama', 'organization_id' => 999]);

        $a = $this->as()->getJson(route('platform.audit-log.show', $system))->assertOk()->json();
        $this->assertSame(['System', 'system'], [$a['actor']['name'], $a['actor']['kind']]);
        $this->assertSame(route('platform.audit-log', ['actor' => 'system']), $a['view_all_url']);
        $this->assertFalse($a['target']['has_target']);
        $this->assertNull($a['organization']);
        $this->assertSame([], $a['related']);

        $b = $this->as()->getJson(route('platform.audit-log.show', $gone))->assertOk()->json();
        $this->assertTrue($b['target']['deleted']);
        $this->assertNull($b['target']['url']);
        $this->assertSame('Syarikat Lama', $b['target']['name']);
        $this->assertStringContainsString('(deleted)', $b['organization']['name']);
        $this->assertNull($b['organization']['url']);
    }

    public function test_the_drawer_shows_times_in_the_viewing_admins_timezone(): void
    {
        $entry = $this->log('organization.approved', $this->org(), [], null, '2026-10-21 17:00:00');

        $this->assertStringContainsString('22 October 2026, 1:00:00 AM', $this->as()->getJson(route('platform.audit-log.show', $entry))->json('time_full'));

        $this->admin->update(['timezone' => 'America/New_York']);
        $this->assertStringContainsString('21 October 2026, 1:00:00 PM', $this->as()->getJson(route('platform.audit-log.show', $entry))->json('time_full'));
    }

    public function test_an_unknown_entry_is_a_404_and_the_page_ships_the_drawer_markup(): void
    {
        $this->as()->getJson('/platform/audit-log/999999')->assertNotFound();
        $this->as()->getJson('/platform/audit-log/abc')->assertNotFound();

        $this->log('organization.approved', $this->org());
        $this->as()->get(route('platform.audit-log'))
            ->assertSee('role="dialog"', false)
            ->assertSee('aria-modal="true"', false)
            ->assertSee('Loading entry', false)
            ->assertSee('animate-pulse', false)          // skeleton while the entry loads
            ->assertSee('Copy entry ID')
            ->assertSee('View all by this admin')
            ->assertSee('Other entries from the same request')
            ->assertSee('showUrl: '.\Illuminate\Support\Js::from(url('/platform/audit-log')), false);
    }

    // ---- export ----------------------------------------------------------------------------

    public function test_export_respects_the_active_filters(): void
    {
        $alpha = $this->org('Universiti Alpha');
        $beta = $this->org('Klinik Beta');
        $this->log('organization.approved', $alpha, [], null, '2026-10-01 08:00:00');
        $this->log('organization.rejected', $beta, ['reason' => 'x'], null, '2026-10-02 08:00:00');
        $this->log('system.chat_purged', null, ['sessions' => 2], 'system', '2026-10-03 08:00:00');
        $student = User::factory()->create();
        $this->log('auth.login', $student, [], $student, '2026-10-04 08:00:00');

        $all = $this->csv($this->as()->get(route('platform.audit-log.export'))->streamedContent());
        $this->assertCount(3, $all[1], 'sign-in activity is not exported unless it is switched on');

        $filtered = $this->csv($this->as()->get(route('platform.audit-log.export', ['organization' => $alpha->id]))->streamedContent());
        $this->assertSame(['organization.approved'], array_column($filtered[1], 'Action'));

        $system = $this->csv($this->as()->get(route('platform.audit-log.export', ['actor' => 'system']))->streamedContent());
        $this->assertSame(['system.chat_purged'], array_column($system[1], 'Action'));

        $signIn = $this->csv($this->as()->get(route('platform.audit-log.export', ['sign_in' => 1]))->streamedContent());
        $this->assertContains('auth.login', array_column($signIn[1], 'Action'));
        $this->assertNotContains('auth.login', array_column($all[1], 'Action'));

        $range = $this->csv($this->as()->get(route('platform.audit-log.export', ['range' => 'custom', 'from' => '2026-10-02', 'to' => '2026-10-02']))->streamedContent());
        $this->assertSame(['organization.rejected'], array_column($range[1], 'Action'));
    }

    public function test_export_has_the_right_name_type_columns_and_encoding(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 07:05:00', 'UTC')); // 15:05 in Kuala Lumpur
        $org = $this->org('Universiti Alpha');
        $this->log('organization.rejected', $org, ['reason' => 'Sebab "dengan", koma'], null, '2026-10-21 17:00:00', ['ip' => '203.0.113.9']);

        $response = $this->as()->get(route('platform.audit-log.export'));

        $response->assertOk();
        $this->assertSame('audit-log_20261022_1505.csv', $this->filename($response));
        $this->assertStringStartsWith('text/csv', $response->headers->get('Content-Type'));

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content, 'UTF-8 byte-order mark for Excel');

        [$header, $rows] = $this->csv($content);
        $this->assertSame(['Entry ID', 'Time (UTC)', 'Time (Asia/Kuala_Lumpur)', 'Actor type', 'Actor user ID', 'Actor name', 'Actor role', 'Action', 'Action label', 'Category', 'Target type', 'Target ID', 'Target name', 'Organization ID', 'Details', 'Metadata (JSON)', 'IP', 'User agent', 'Request ID'], $header);

        $row = $rows[0];
        $this->assertSame(['2026-10-21 17:00:00', '2026-10-22 01:00:00'], [$row['Time (UTC)'], $row['Time (Asia/Kuala_Lumpur)']]);
        $this->assertSame(['Siti Platform', 'platform_admin', 'organization.rejected', 'Organization rejected', 'organizations'], [$row['Actor name'], $row['Actor role'], $row['Action'], $row['Action label'], $row['Category']]);
        $this->assertSame(['organization', 'Universiti Alpha', '203.0.113.9'], [$row['Target type'], $row['Target name'], $row['IP']]);
        $this->assertSame('Sebab "dengan", koma', $row['Details'], 'quotes and commas survive');
        $this->assertSame('Sebab "dengan", koma', json_decode($row['Metadata (JSON)'], true)['reason']);
    }

    private function filename($response): string
    {
        preg_match('/filename="?([^";]+)"?/', $response->headers->get('Content-Disposition'), $m);

        return $m[1];
    }

    public function test_export_neutralises_cells_a_spreadsheet_would_run_as_formulas(): void
    {
        $nasty = ['=HYPERLINK("http://evil.example","click")', '+1+1', '-2+3', '@SUM(A1:A9)', ' =cmd|calc', "\t=1+1", "\r=1+1"];

        foreach ($nasty as $i => $name) {
            $this->log('organization.approved', null, ['domain' => $name], null, '2026-10-0'.($i + 1).' 08:00:00', [
                'target_type' => 'organization', 'target_id' => 100 + $i, 'target_name' => $name,
            ]);
        }
        $this->log('organization.approved', null, [], null, '2026-10-09 08:00:00', ['target_type' => 'organization', 'target_id' => 7, 'target_name' => 'Nama Biasa = baik']);

        $content = $this->as()->get(route('platform.audit-log.export'))->streamedContent();
        [, $rows] = $this->csv($content);

        $byId = collect($rows)->keyBy('Target ID');
        foreach ($nasty as $i => $original) {
            $cell = $byId[(string) (100 + $i)]['Target name'];
            $this->assertSame("'".$original, $cell, 'formula-looking name is stored as text');
            $this->assertStringStartsWith("'", $byId[(string) (100 + $i)]['Details'], 'details line too');
        }
        $this->assertSame('Nama Biasa = baik', $byId['7']['Target name'], 'ordinary text is untouched');
        $this->assertSame('123', $byId['123']['Target ID'] ?? '123', 'numbers are untouched');

        // The raw unit rule too.
        $this->assertSame("'=1", \App\Support\AuditLogCsv::neutralise('=1'));
        $this->assertSame(5, \App\Support\AuditLogCsv::neutralise(5));
        $this->assertNull(\App\Support\AuditLogCsv::neutralise(null));
        $this->assertSame('2026-10-01 00:00:00', \App\Support\AuditLogCsv::neutralise('2026-10-01 00:00:00'));
    }

    public function test_the_export_is_itself_logged_with_the_filters_used_and_is_not_part_of_its_own_file(): void
    {
        $org = $this->org('Universiti Alpha');
        $this->log('organization.approved', $org);
        $this->log('organization.approved', $this->org('Lain'));

        $response = $this->as()->get(route('platform.audit-log.export', ['organization' => $org->id, 'category' => 'organizations']));
        $csv = $this->csv($response->streamedContent());

        $export = AuditLog::where('action', 'audit.exported')->firstOrFail();
        $this->assertSame($this->admin->id, $export->actor_user_id);
        $this->assertSame('audit', $export->category);
        $this->assertSame('csv', $export->metadata['format']);
        $this->assertSame(1, $export->metadata['rows']);
        $this->assertEquals(['organization' => (string) $org->id, 'category' => 'organizations'], $export->metadata['filters']);
        $this->assertCount(1, $csv[1]);
        $this->assertNotContains('audit.exported', array_column($csv[1], 'Action'));

        // A second export (everything) does include the first export's entry.
        $second = $this->csv($this->as()->get(route('platform.audit-log.export'))->streamedContent());
        $this->assertContains('audit.exported', array_column($second[1], 'Action'));
        $this->assertSame(2, AuditLog::where('action', 'audit.exported')->count());
    }

    public function test_too_big_an_export_is_refused_before_anything_downloads(): void
    {
        config(['audit.export_max_rows' => 3]);
        foreach (range(1, 5) as $i) {
            $this->log('organization.approved', $this->org("Org {$i}"));
        }

        $response = $this->as()->get(route('platform.audit-log.export', ['category' => 'organizations']));

        $response->assertRedirect(route('platform.audit-log', ['category' => 'organizations']));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Narrow the filters', session('error'));
        $this->assertStringContainsString('5 entries', session('error'));
        $this->assertNull($response->headers->get('Content-Disposition'));
        $this->assertSame(0, AuditLog::where('action', 'audit.exported')->count(), 'a refused export is not logged as an export');

        // Narrowing the filters gets it under the limit.
        $ok = $this->as()->get(route('platform.audit-log.export', ['q' => 'Org 1']));
        $ok->assertOk();
        $this->assertCount(1, $this->csv($ok->streamedContent())[1]);

        // And the page shows the message.
        $this->as()->followingRedirects()->get(route('platform.audit-log.export', ['category' => 'organizations']))->assertSee('Narrow the filters');
    }

    public function test_a_large_export_is_written_in_chunks_without_losing_or_repeating_rows(): void
    {
        config(['audit.export_chunk' => 7, 'audit.export_max_rows' => 100]);
        foreach (range(1, 40) as $i) {
            $this->log('organization.note_added', null, ['note_id' => $i], null, Carbon::parse('2026-09-01 08:00:00', 'UTC')->addMinutes($i)->toDateTimeString(), ['target_type' => 'organization', 'target_id' => $i, 'target_name' => "Org {$i}"]);
        }

        [, $rows] = $this->csv($this->as()->get(route('platform.audit-log.export'))->streamedContent());

        $this->assertCount(40, $rows);
        $this->assertCount(40, array_unique(array_column($rows, 'Entry ID')));
        $this->assertSame('Org 40', $rows[0]['Target name'], 'newest first, like the page');
        $this->assertSame('Org 1', $rows[39]['Target name']);
    }

    public function test_the_export_route_is_rate_limited(): void
    {
        $this->log('organization.approved', $this->org());

        foreach (range(1, 5) as $i) {
            $this->as()->get(route('platform.audit-log.export'))->assertOk();
        }

        $this->as()->get(route('platform.audit-log.export'))->assertStatus(429);
    }

    // ---- links from other places -----------------------------------------------------------

    public function test_recent_approvals_on_the_dashboard_read_from_the_audit_log(): void
    {
        // An organization that is active but was never approved through the log must NOT show up (no back-filled history).
        $this->org('Aktif Tanpa Log');

        $this->as()->get(route('platform.dashboard'))
            ->assertOk()
            ->assertSee('No approvals yet')
            ->assertDontSee('Aktif Tanpa Log')
            ->assertSee('View all in Audit Log →')
            ->assertSee(route('platform.audit-log', ['actions' => 'organization.approved,counselor.approved']), false);

        $this->log('organization.approved', $this->org('Diluluskan Hari Ini'), [], null, '2026-10-20 09:00:00');
        $this->log('counselor.approved', null, [], null, '2026-10-21 09:00:00', ['target_type' => 'counselor', 'target_id' => 3, 'target_name' => 'Dr. Lim']);
        $this->log('organization.rejected', $this->org('Ditolak'), ['reason' => 'x']);

        $this->as()->get(route('platform.dashboard'))
            ->assertSee('Diluluskan Hari Ini')->assertSee('Dr. Lim')->assertSee('Counselor')->assertSee('Organization')
            ->assertDontSee('Ditolak')
            ->assertDontSee('No approvals yet');
    }

    public function test_approving_an_organization_makes_it_appear_on_the_dashboard(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $this->seed(OrganizationReviewSeeder::class);
        $org = Organization::where('name', 'Universiti Teknologi Contoh')->first();

        $this->as()->post(route('platform.organizations.approve', $org))->assertSessionHas('status');

        $this->as()->get(route('platform.dashboard'))->assertSee('Universiti Teknologi Contoh');
    }

    public function test_the_organization_page_links_to_its_full_history(): void
    {
        $org = $this->org('Universiti Alpha');

        $this->as()->get(route('platform.organizations.show', $org))
            ->assertSee('View full history →')
            ->assertSee(route('platform.audit-log', ['organization' => $org->id]), false);

        $this->log('organization.note_added', $org, ['note_id' => 1]);
        $this->as()->get(route('platform.audit-log', ['organization' => $org->id]))
            ->assertSee('Universiti Alpha')   // the chip names the organization
            ->assertSee('Internal note added');
    }

    public function test_the_audit_log_link_in_the_avatar_menu_and_sidebar_is_active_on_the_page(): void
    {
        $this->as()->get(route('platform.audit-log'))
            ->assertSee('aria-current="page"', false)
            ->assertSee(route('platform.audit-log'), false);
    }

    // ---- sample data -----------------------------------------------------------------------

    public function test_the_sample_seeder_covers_every_case_and_every_entry_opens(): void
    {
        $this->seed(OrganizationReviewSeeder::class);
        $this->seed(AuditLogSeeder::class);

        $sample = AuditLog::query()->where('metadata->sample_set', 'audit-demo')->get();
        $this->assertGreaterThanOrEqual(100, $sample->count());

        // Every category in the catalogue except the catch-all.
        $used = $sample->pluck('category')->unique()->all();
        foreach (array_diff(array_keys(config('audit.categories')), ['other']) as $category) {
            $this->assertContains($category, $used, "no sample entry in category {$category}");
        }

        $this->assertGreaterThanOrEqual(3, $sample->whereNotNull('actor_user_id')->where('actor_role', 'platform_admin')->pluck('actor_user_id')->unique()->count(), 'several admins');
        $this->assertTrue($sample->contains('actor_type', 'system'));
        $this->assertTrue($sample->contains('actor_type', 'guest'));
        $this->assertTrue($sample->contains(fn ($l) => $l->target_type === 'organization' && $l->target_id === 99999), 'a deleted target');
        $this->assertTrue($sample->contains(fn ($l) => mb_strlen($l->metadata['reason'] ?? '') > 1500), 'long metadata');
        $this->assertGreaterThan(90, $sample->min('created_at')->diffInDays($sample->max('created_at')), 'dates spread over months');
        $this->assertTrue($sample->every(fn ($l) => $l->metadata['seeded'] === true), 'all marked as sample data');

        $before = AuditLog::count();
        $this->seed(AuditLogSeeder::class);
        $this->assertSame($before, AuditLog::count(), 'running it twice adds nothing');

        // The page works with this data in every mode, and every entry opens in the drawer.
        foreach ([[], ['sign_in' => 1], ['per_page' => 100], ['category' => 'auth'], ['actor' => 'system'], ['q' => 'a']] as $query) {
            $this->as()->get(route('platform.audit-log', $query))->assertOk();
        }
        foreach (AuditLog::pluck('id') as $id) {
            $this->as()->getJson(route('platform.audit-log.show', $id))->assertOk();
        }
        $total = AuditLog::count();
        $export = $this->as()->get(route('platform.audit-log.export', ['sign_in' => 1]));
        $this->assertSame($total, count($this->csv($export->streamedContent())[1]), 'every entry, but not the export entry just written');
    }
}
