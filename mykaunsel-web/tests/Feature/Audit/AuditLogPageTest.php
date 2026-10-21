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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AuditLogPageTest extends TestCase
{
    use RefreshDatabase;

    private Organization $platform;

    private User $admin;

    private array $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = Organization::create(['name' => 'MyKaunsel Platform', 'org_type' => OrgType::Platform, 'access_model' => AccessModel::Open, 'subscription_status' => OrgStatus::Active]);
        $this->admin = $this->staff('Siti Platform', MembershipRole::PlatformAdmin, $this->platform, ['timezone' => 'Asia/Kuala_Lumpur']);
        $this->session = ['current_org_id' => $this->platform->id, 'current_role' => 'platform_admin'];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---- helpers ---------------------------------------------------------------------------

    private function staff(string $name, MembershipRole $role, Organization $org, array $attrs = []): User
    {
        $user = User::factory()->create(['name' => $name] + $attrs);
        Membership::create(['user_id' => $user->id, 'org_id' => $org->id, 'role' => $role, 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        return $user;
    }

    private function org(string $name = 'Universiti Contoh', OrgStatus $status = OrgStatus::Active): Organization
    {
        return Organization::create(['name' => $name, 'org_type' => OrgType::University, 'access_model' => AccessModel::Closed, 'subscription_status' => $status]);
    }

    private function log(string $action, $target = null, array $meta = [], User|string|null $actor = null, string|Carbon|null $at = null, array $options = []): AuditLog
    {
        return Audit::log($action, $target, $meta, $actor ?? $this->admin, $options + array_filter(['at' => $at ? Carbon::parse($at, 'UTC') : null]));
    }

    private function page(array $query = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->admin)->withSession($this->session)->get(route('platform.audit-log', $query));
    }

    private function ids($response): array
    {
        return $response->viewData('logs')->getCollection()->pluck('id')->all();
    }

    // ---- access ----------------------------------------------------------------------------

    public function test_only_platform_admins_can_open_the_page_the_drawer_or_the_export(): void
    {
        $entry = $this->log('organization.approved', $this->org());
        $org = $this->org('Org Aktif');
        $orgAdmin = $this->staff('Pentadbir Org', MembershipRole::OrgAdmin, $org);
        $student = $this->staff('Pelajar', MembershipRole::Student, $org);

        foreach ([[$orgAdmin, 'org_admin', $org], [$student, 'student', $org]] as [$user, $role, $o]) {
            $session = ['current_org_id' => $o->id, 'current_role' => $role];
            $this->flushSession();
            $this->actingAs($user)->withSession($session)->get(route('platform.audit-log'))->assertForbidden();
            $this->actingAs($user)->withSession($session)->get(route('platform.audit-log.show', $entry))->assertForbidden();
            $this->actingAs($user)->withSession($session)->get(route('platform.audit-log.export'))->assertForbidden();
        }

        $this->flushSession();
        auth()->logout();
        $this->get(route('platform.audit-log'))->assertRedirect();
        $this->get(route('platform.audit-log.show', $entry))->assertRedirect();
        $this->get(route('platform.audit-log.export'))->assertRedirect();
    }

    public function test_the_page_has_the_expected_header_note_and_table_structure(): void
    {
        $this->log('organization.approved', $this->org());

        $this->page()->assertOk()
            ->assertSee('Audit Log')
            ->assertSee('Record of all administrative actions')
            ->assertSee('read-only')
            ->assertSee('cannot be edited or deleted')
            ->assertSee('Export log')
            ->assertSee('<caption class="sr-only">', false)
            ->assertSeeInOrder(['Timestamp', 'Admin', 'Action', 'Target', 'Details'])
            ->assertSee('scope="col"', false)
            ->assertSee('sticky top-0', false);
    }

    public function test_the_sidebar_and_avatar_menu_link_to_it(): void
    {
        $this->page()
            ->assertSee(route('platform.audit-log'), false)
            ->assertSee('Audit log')           // avatar dropdown
            ->assertDontSee('Audit Log</span>', false); // no longer a "Soon" placeholder in the sidebar
    }

    // ---- order, sign-in activity -----------------------------------------------------------

    public function test_newest_entries_come_first(): void
    {
        $old = $this->log('organization.approved', null, [], null, '2026-06-01 10:00:00', ['target_type' => 'organization', 'target_id' => 1, 'target_name' => 'Lama']);
        $new = $this->log('organization.approved', null, [], null, '2026-09-01 10:00:00', ['target_type' => 'organization', 'target_id' => 2, 'target_name' => 'Baru']);
        $mid = $this->log('organization.approved', null, [], null, '2026-07-01 10:00:00', ['target_type' => 'organization', 'target_id' => 3, 'target_name' => 'Tengah']);

        $this->assertSame([$new->id, $mid->id, $old->id], $this->ids($this->page()));
    }

    public function test_sign_in_activity_is_hidden_unless_asked_for(): void
    {
        $student = $this->staff('Pelajar', MembershipRole::Student, $this->org());
        $login = $this->log('auth.login', $student, [], $student);
        $admin = $this->log('organization.approved', $this->org('Org B'));

        $this->assertSame([$admin->id], $this->ids($this->page()));
        $this->assertEqualsCanonicalizing([$login->id, $admin->id], $this->ids($this->page(['sign_in' => 1])));
        $this->assertSame([$login->id], $this->ids($this->page(['category' => 'auth'])), 'asking for the auth category shows it');
        $this->page(['sign_in' => 1])->assertSee('Include sign-in activity')->assertSee('checked', false);
    }

    // ---- filters ---------------------------------------------------------------------------

    public function test_combined_filters_return_exactly_the_matching_entries(): void
    {
        $orgA = $this->org('Universiti Alpha');
        $orgB = $this->org('Klinik Beta');
        $aisyah = $this->staff('Aisyah Rahman', MembershipRole::PlatformAdmin, $this->platform);

        $a1 = $this->log('organization.approved', $orgA, [], $this->admin, '2026-10-01 08:00:00');
        $a2 = $this->log('organization.rejected', $orgA, ['reason' => 'x'], $aisyah, '2026-10-02 08:00:00');
        $b1 = $this->log('organization.approved', $orgB, [], $aisyah, '2026-10-03 08:00:00');
        $b2 = $this->log('organization.suspended', $orgB, ['reason' => 'y'], $this->admin, '2026-08-03 08:00:00');
        $sys = $this->log('system.chat_purged', null, ['sessions' => 3], 'system', '2026-10-04 08:00:00');
        $ctr = $this->log('counselor.approved', null, [], $aisyah, '2026-10-05 08:00:00', ['target_type' => 'counselor', 'target_id' => 5, 'target_name' => 'Dr. Lim']);

        $ids = fn (array $q) => $this->ids($this->page($q));

        $this->assertEqualsCanonicalizing([$a1->id, $a2->id, $b1->id, $b2->id, $sys->id, $ctr->id], $ids([]));
        $this->assertEqualsCanonicalizing([$a1->id, $a2->id, $b1->id, $b2->id], $ids(['category' => 'organizations']));
        $this->assertEqualsCanonicalizing([$a1->id, $b1->id], $ids(['action' => 'organization.approved']));
        $this->assertEqualsCanonicalizing([$a2->id, $b1->id, $ctr->id], $ids(['actor' => $aisyah->id]));
        $this->assertSame([$sys->id], $ids(['actor' => 'system']));
        $this->assertEqualsCanonicalizing([$a1->id, $a2->id], $ids(['organization' => $orgA->id]));
        $this->assertSame([$ctr->id], $ids(['target_type' => 'counselor']));

        // Combinations narrow further, they never widen.
        $this->assertSame([$b1->id], $ids(['category' => 'organizations', 'actor' => $aisyah->id, 'organization' => $orgB->id, 'action' => 'organization.approved']));
        $this->assertSame([$a2->id], $ids(['actor' => $aisyah->id, 'organization' => $orgA->id]));
        $this->assertSame([], $ids(['category' => 'counselors', 'actor' => $this->admin->id]));
        $this->assertSame([$a1->id], $ids(['q' => 'alpha', 'actor' => $this->admin->id, 'category' => 'organizations']));
    }

    public function test_search_matches_admin_name_target_name_and_action_key_and_treats_wildcards_literally(): void
    {
        $org = $this->org('Universiti Alpha');
        $aisyah = $this->staff('Aisyah Rahman', MembershipRole::PlatformAdmin, $this->platform);
        $byAdmin = $this->log('organization.approved', $org, [], $aisyah);
        $suspended = $this->log('organization.suspended', $this->org('Klinik Beta'), ['reason' => 'z']);

        $this->assertSame([$byAdmin->id], $this->ids($this->page(['q' => 'aisyah'])), 'admin name');
        $this->assertSame([$byAdmin->id], $this->ids($this->page(['q' => 'ALPHA'])), 'target name, any case');
        $this->assertSame([$suspended->id], $this->ids($this->page(['q' => 'organization.suspended'])), 'action key');
        $this->assertSame([], $this->ids($this->page(['q' => 'organization_suspended'])), '"_" is not a wildcard');
        $this->assertSame([], $this->ids($this->page(['q' => '%'])), '"%" is not a wildcard');
        $this->assertSame([], $this->ids($this->page(['q' => 'nothing-like-this'])));
    }

    public function test_the_date_filter_uses_the_admins_timezone_not_utc(): void
    {
        // "Now" is 22 Oct 2026, 11:00 in Kuala Lumpur (03:00 UTC).
        Carbon::setTestNow(Carbon::parse('2026-10-22 03:00:00', 'UTC'));

        $justAfterMidnightKl = $this->log('organization.approved', $this->org('A'), [], null, '2026-10-21 17:00:00'); // 22 Oct 01:00 KL
        $lateEveningKl = $this->log('organization.approved', $this->org('B'), [], null, '2026-10-21 15:00:00');       // 21 Oct 23:00 KL
        $sixDaysAgo = $this->log('organization.approved', $this->org('C'), [], null, '2026-10-16 05:00:00');          // 16 Oct 13:00 KL
        $sevenDaysAgo = $this->log('organization.approved', $this->org('D'), [], null, '2026-10-15 05:00:00');        // 15 Oct 13:00 KL
        $old = $this->log('organization.approved', $this->org('E'), [], null, '2026-09-10 05:00:00');                  // 10 Sep

        $ids = fn (array $q) => $this->ids($this->page($q));

        $this->assertSame([$justAfterMidnightKl->id], $ids(['range' => 'today']), 'UTC would still call this yesterday');
        $this->assertEqualsCanonicalizing([$justAfterMidnightKl->id, $lateEveningKl->id, $sixDaysAgo->id], $ids(['range' => '7d']));
        $this->assertEqualsCanonicalizing([$justAfterMidnightKl->id, $lateEveningKl->id, $sixDaysAgo->id, $sevenDaysAgo->id], $ids(['range' => '30d']));
        $this->assertSame([$justAfterMidnightKl->id], $ids(['range' => 'custom', 'from' => '2026-10-22', 'to' => '2026-10-22']));
        $this->assertSame([$lateEveningKl->id], $ids(['range' => 'custom', 'from' => '2026-10-21', 'to' => '2026-10-21']));
        $this->assertSame([$old->id], $ids(['range' => 'custom', 'to' => '2026-09-30']));
        $this->assertSame([$old->id], $ids(['from' => '2026-09-10', 'to' => '2026-09-10']), 'dates without a preset mean custom');

        // The same data, seen from UTC: 21 Oct 23:00 KL is 15:00 UTC on the 21st, 01:00 KL on the 22nd is 17:00 UTC on the 21st.
        $this->admin->update(['timezone' => 'UTC']);
        $this->assertSame([], $ids(['range' => 'today']), 'in UTC nothing happened yet today (22 Oct)');
        $this->assertEqualsCanonicalizing([$justAfterMidnightKl->id, $lateEveningKl->id], $ids(['range' => 'custom', 'from' => '2026-10-21', 'to' => '2026-10-21']));
    }

    public function test_times_are_shown_in_the_admins_timezone_with_utc_in_the_tooltip(): void
    {
        $this->log('organization.approved', $this->org(), [], null, '2026-10-21 17:00:00');

        $this->page()
            ->assertSee('22 Oct 2026, 1:00:00 AM')
            ->assertSee('title="2026-10-21 17:00:00 UTC"', false)
            ->assertSee('datetime="2026-10-21T17:00:00+00:00"', false)
            ->assertSee('(Asia/Kuala_Lumpur)', false);

        $this->admin->update(['timezone' => 'America/New_York']);
        $this->page()->assertSee('21 Oct 2026, 1:00:00 PM')->assertDontSee('22 Oct 2026, 1:00:00 AM');

        $this->admin->update(['timezone' => '']); // empty falls back to the platform default (Asia/Kuala_Lumpur)
        $this->page()->assertSee('22 Oct 2026, 1:00:00 AM');
    }

    public function test_invalid_filters_are_ignored_and_explained_never_trusted(): void
    {
        $entry = $this->log('organization.approved', $this->org());

        $response = $this->page([
            'category' => 'not-a-category', 'actor' => "1' OR '1'='1", 'range' => 'forever', 'per_page' => 7,
            'organization' => 'abc', 'action' => 'DROP TABLE audit_logs', 'target_type' => '../etc', 'from' => '22/10/2026',
        ]);

        $response->assertOk()
            ->assertSee('Some filters in the link were not valid and were ignored')
            ->assertSee('Unknown category.')
            ->assertSee('Unknown admin.')
            ->assertSee('Choose 25, 50 or 100 rows per page.');
        $this->assertSame([$entry->id], $this->ids($response), 'nothing was filtered by the bad values');
        $this->assertSame(25, $response->viewData('filters')->perPage());
        $this->assertSame(1, AuditLog::count());

        $this->page(['range' => 'custom', 'from' => '2026-10-10', 'to' => '2026-10-01'])->assertSee('The end date cannot be before the start date.');
        $this->page(['q' => ['array']])->assertOk();
        $this->page(['q' => str_repeat('a', 101)])->assertOk()->assertSee('Some filters');
    }

    public function test_active_filter_chips_can_be_removed_one_by_one_or_all_at_once(): void
    {
        $org = $this->org('Universiti Alpha');
        $this->log('organization.approved', $org);

        $query = ['q' => 'alpha', 'category' => 'organizations', 'organization' => $org->id, 'range' => '7d'];
        $response = $this->page($query);

        $chips = collect($response->viewData('chips'));
        $this->assertEqualsCanonicalizing(['Search', 'Category', 'Organization', 'Date'], $chips->pluck('label')->all());
        $this->assertSame('Universiti Alpha', $chips->firstWhere('label', 'Organization')['value']);
        $this->assertSame(['category' => 'organizations', 'organization' => (string) $org->id, 'range' => '7d'], $chips->firstWhere('label', 'Search')['remove'], 'removing the search chip keeps the rest');

        $response->assertSee('aria-label="Remove filter Category: Organizations"', false)->assertSee('Clear all');
        $this->page()->assertDontSee('aria-label="Active filters"', false);
    }

    public function test_the_filter_state_lives_in_the_url_and_survives_paging(): void
    {
        foreach (range(1, 30) as $i) {
            $this->log('organization.approved', $this->org("Org {$i}"));
        }

        $page = $this->page(['category' => 'organizations', 'per_page' => 25]);

        $page->assertSee('category=organizations', false)->assertSee('per_page=25', false)->assertSee('page=2', false);
    }

    // ---- pagination and empty states -------------------------------------------------------

    public function test_pagination_shows_ranges_and_totals_and_honours_page_size(): void
    {
        $org = $this->org();
        foreach (range(1, 60) as $i) {
            $this->log('organization.note_added', $org, ['note_id' => $i]);
        }

        $this->page()->assertSee('1–25')->assertSee('of')->assertSee('60');
        $this->assertCount(25, $this->ids($this->page()));
        $this->assertCount(10, $this->ids($this->page(['page' => 3])));
        $this->page(['page' => 3])->assertSee('51–60');
        $this->assertCount(50, $this->ids($this->page(['per_page' => 50])));
        $this->assertCount(60, $this->ids($this->page(['per_page' => 100])));
        $this->page(['per_page' => 100])->assertSee('1–60');
        $this->page(['per_page' => 50])->assertSee('Rows per page')->assertSee('<option value="50" selected>', false);

        $this->page(['page' => 9])->assertRedirect(route('platform.audit-log', ['page' => 3, 'per_page' => 25]));
    }

    public function test_empty_states_differ_for_an_empty_log_and_for_filters_that_match_nothing(): void
    {
        $this->page()->assertOk()->assertSee('Nothing has been recorded yet')->assertDontSee('No entries match these filters');

        $this->log('organization.approved', $this->org());

        $this->page(['q' => 'zzz'])->assertSee('No entries match these filters')->assertSee('Clear all filters')->assertDontSee('Nothing has been recorded yet');
        $this->page(['q' => 'zzz'])->assertSee('Sign-in activity is hidden unless you include it');
    }

    // ---- what each row shows ---------------------------------------------------------------

    public function test_rows_show_actor_badge_target_and_details(): void
    {
        $org = $this->org('Universiti Alpha');
        $this->log('organization.approved', $org, ['changes' => ['status' => ['old' => 'pending', 'new' => 'active']]]);
        $this->log('organization.rejected', $this->org('Klinik Beta'), ['reason' => 'SSM tidak sah']);
        $this->log('system.chat_purged', null, ['sessions' => 3], 'system');
        $this->log('audit.exported', null, ['rows' => 5]);
        $this->log('account.password_changed', $this->admin);
        $this->log('member.removed', null, [], null, null, ['target_type' => 'user', 'target_id' => 404, 'target_name' => null]);

        $this->page()->assertOk()
            ->assertSee('Siti Platform')->assertSee('Platform admin')
            ->assertSee('System')->assertSee('Scheduled job')
            ->assertSee('Approved')->assertSee('Rejected')->assertSee('Exported')->assertSee('Security')->assertSee('Deleted')
            ->assertSee('Organization approved')
            ->assertSee('pending → active')
            ->assertSee('SSM tidak sah')
            ->assertSee('Universiti Alpha')
            ->assertSee(route('platform.organizations.show', $org), false);
    }

    public function test_a_target_that_still_exists_is_linked_and_one_that_is_gone_is_not(): void
    {
        $alive = $this->org('Masih Ada');
        $gone = $this->org('Sudah Padam');
        $this->log('organization.approved', $alive);
        $this->log('organization.approved', $gone);
        $goneId = $gone->id;
        $gone->delete();

        $response = $this->page()->assertOk();

        $response->assertSee(route('platform.organizations.show', $alive), false)
            ->assertSee('Sudah Padam')
            ->assertSee('(deleted)')
            ->assertDontSee(route('platform.organizations.show', $goneId), false);
    }

    public function test_the_platform_organization_is_never_linked(): void
    {
        $this->log('organization.dns_checked', $this->platform, ['summary' => 'x']);

        $this->page()->assertSee('MyKaunsel Platform')->assertDontSee(route('platform.organizations.show', $this->platform), false);
    }

    public function test_staff_are_shown_by_their_saved_name_even_after_the_account_is_gone_and_others_by_id(): void
    {
        $leaver = $this->staff('Bekas Pentadbir', MembershipRole::PlatformAdmin, $this->platform);
        $student = $this->staff('Pelajar Satu', MembershipRole::Student, $this->org('Uni'));
        $this->log('organization.approved', $this->org('X'), [], $leaver);
        $this->log('account.password_changed', $student, [], $student);
        Membership::whereIn('user_id', [$leaver->id, $student->id])->delete();
        $leaver->delete();
        $student->delete();

        $this->page()->assertSee('Bekas Pentadbir')->assertSee('User #'.$student->id)->assertDontSee('Pelajar Satu');
    }

    public function test_a_guest_and_long_details_are_handled(): void
    {
        $this->log('auth.login_failed', null, ['email_hash' => str_repeat('a', 64)], null, null, ['actor_type' => 'guest']);
        $this->log('organization.rejected', $this->org(), ['reason' => str_repeat('panjang ', 100)]);

        $response = $this->page(['sign_in' => 1])->assertOk()->assertSee('Not signed in');

        $summary = $response->viewData('rows')->firstWhere('action_key', 'organization.rejected')['summary'];
        $this->assertLessThanOrEqual(161, mb_strlen($summary));
    }
}
