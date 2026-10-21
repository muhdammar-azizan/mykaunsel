<?php

namespace Tests\Feature\Platform;

use App\Facades\Audit;
use App\Enums\AccessModel;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Models\Membership;
use App\Models\OrgReviewNote;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationTimeline;
use Database\Seeders\OrganizationReviewSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrganizationReviewToolsTest extends TestCase
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
        $this->admin = $this->platformAdmin('Admin Satu');
        $this->session = ['current_org_id' => $this->platform->id, 'current_role' => 'platform_admin'];
    }

    private function platformAdmin(string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        Membership::create(['user_id' => $user->id, 'org_id' => $this->platform->id, 'role' => MembershipRole::PlatformAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        return $user;
    }

    private function as(User $user)
    {
        return $this->actingAs($user)->withSession($this->session);
    }

    private function org(string $name = 'Universiti Teknologi Contoh'): Organization
    {
        $this->seed(OrganizationReviewSeeder::class);

        return Organization::where('name', $name)->firstOrFail();
    }

    private function tick(Organization $org, string $key, bool $checked, ?User $as = null)
    {
        return $this->as($as ?? $this->admin)->patchJson(route('platform.organizations.checklist', $org), ['key' => $key, 'checked' => $checked]);
    }

    // ---- Checklist -------------------------------------------------------------------------

    public function test_ticking_a_box_saves_it_with_who_and_when(): void
    {
        $org = $this->org('Kolej Universiti Skip DNS');

        $this->tick($org, 'ssm_valid', true)->assertOk()->assertJson(['checked' => true, 'by' => 'Admin Satu']);

        $saved = $org->fresh()->review_checklist;
        $this->assertSame($this->admin->id, $saved['ssm_valid']['by']);
        $this->assertNotEmpty($saved['ssm_valid']['at']);

        $this->tick($org, 'ssm_valid', false)->assertOk();
        $this->assertNull($org->fresh()->review_checklist, 'an empty checklist is stored as null');
    }

    public function test_other_admins_see_what_was_ticked_and_by_whom(): void
    {
        $org = $this->org('Kolej Universiti Skip DNS');
        $this->tick($org, 'name_matches', true);
        $second = $this->platformAdmin('Admin Dua');

        $this->as($second)->get(route('platform.organizations.show', $org))
            ->assertOk()
            ->assertSee('Review checklist')
            ->assertSee('Checked by', false)
            ->assertSee('Admin Satu');
    }

    public function test_two_admins_ticking_different_boxes_keep_both(): void
    {
        $org = $this->org('Kolej Universiti Skip DNS');
        $second = $this->platformAdmin('Admin Dua');

        $this->tick($org, 'name_matches', true);
        $this->tick($org, 'domain_valid', true, $second);

        $saved = $org->fresh()->review_checklist;
        $this->assertSame($this->admin->id, $saved['name_matches']['by']);
        $this->assertSame($second->id, $saved['domain_valid']['by']);
    }

    public function test_unknown_keys_and_bad_values_are_rejected(): void
    {
        $org = $this->org();

        $this->as($this->admin)->patchJson(route('platform.organizations.checklist', $org), ['key' => 'everything_ok', 'checked' => true])->assertStatus(422);
        $this->as($this->admin)->patchJson(route('platform.organizations.checklist', $org), ['key' => 'ssm_valid'])->assertStatus(422);
    }

    public function test_the_checklist_never_blocks_a_decision(): void
    {
        Notification::fake();
        $org = $this->org('Kolej Universiti Skip DNS'); // nothing ticked at all

        $this->as($this->admin)->post(route('platform.organizations.approve', $org))->assertSessionHas('status');

        $this->assertSame(OrgStatus::Active, $org->fresh()->subscription_status);
    }

    public function test_the_checklist_is_closed_once_the_application_is_decided(): void
    {
        Notification::fake();
        $org = $this->org('Kolej Universiti Skip DNS');
        $this->as($this->admin)->post(route('platform.organizations.approve', $org));

        $this->tick($org, 'name_matches', true)->assertStatus(409);
        $this->assertNull($org->fresh()->review_checklist);

        $this->as($this->admin)->get(route('platform.organizations.show', $org))->assertSee('Closed — this application has been decided.');
    }

    public function test_org_admins_cannot_touch_the_checklist_or_notes(): void
    {
        $org = $this->org();
        $otherOrg = Organization::create(['name' => 'Org Aktif Lain', 'org_type' => OrgType::Corporate, 'access_model' => AccessModel::Closed, 'subscription_status' => OrgStatus::Active]);
        $orgAdmin = User::factory()->create();
        Membership::create(['user_id' => $orgAdmin->id, 'org_id' => $otherOrg->id, 'role' => MembershipRole::OrgAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        $orgSession = ['current_org_id' => $otherOrg->id, 'current_role' => 'org_admin'];

        $this->flushSession();
        $this->actingAs($orgAdmin)->withSession($orgSession)
            ->patchJson(route('platform.organizations.checklist', $org), ['key' => 'ssm_valid', 'checked' => true])->assertForbidden();
        $this->actingAs($orgAdmin)->withSession($orgSession)
            ->post(route('platform.organizations.notes.store', $org), ['body' => 'Saya tulis nota sendiri'])->assertForbidden();

        $this->assertSame(0, OrgReviewNote::where('body', 'Saya tulis nota sendiri')->count());
    }

    // ---- Notes -----------------------------------------------------------------------------

    public function test_adding_a_note_saves_it_audits_it_and_shows_author_and_time(): void
    {
        $org = $this->org('Syarikat Tanpa SSM Sdn Bhd');

        $this->as($this->admin)->post(route('platform.organizations.notes.store', $org), ['body' => '  Telefon pemohon untuk minta SSM.  '])
            ->assertRedirect(route('platform.organizations.show', $org).'#notes')
            ->assertSessionHas('status', 'Note added.');

        $note = OrgReviewNote::where('org_id', $org->id)->firstOrFail();
        $this->assertSame('Telefon pemohon untuk minta SSM.', $note->body);
        $this->assertSame($this->admin->id, $note->user_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'organization.note_added', 'actor_user_id' => $this->admin->id, 'target_id' => $org->id]);

        $this->as($this->admin)->get(route('platform.organizations.show', $org))
            ->assertSee('Telefon pemohon untuk minta SSM.')
            ->assertSee('Admin Satu')
            ->assertSee('Only Platform Admins can see these');
    }

    public function test_an_empty_or_oversized_note_is_refused(): void
    {
        $org = $this->org();

        foreach (['', '   ', str_repeat('a', 2001)] as $bad) {
            $this->as($this->admin)->from(route('platform.organizations.show', $org))
                ->post(route('platform.organizations.notes.store', $org), ['body' => $bad])
                ->assertSessionHasErrors('body', null, 'note');
        }

        $this->assertSame(1, OrgReviewNote::where('org_id', $org->id)->count(), 'only the seeded demo note exists');
    }

    public function test_notes_are_never_visible_to_the_applicant(): void
    {
        $org = $this->org();
        $this->as($this->admin)->post(route('platform.organizations.notes.store', $org), ['body' => 'RAHSIA-DALAMAN-123']);
        $this->as($this->admin)->post(route('platform.organizations.reject', $org), ['reason' => 'Dokumen tidak lengkap.']);

        $orgAdmin = $org->registrant->user;
        $this->flushSession();
        $this->actingAs($orgAdmin)
            ->get(route('organizations.signup.waiting-approval', $org))
            ->assertOk()
            ->assertDontSee('RAHSIA-DALAMAN-123')
            ->assertDontSee('Internal notes');
    }

    // ---- Timeline --------------------------------------------------------------------------

    public function test_timeline_lists_submit_dns_notes_and_decision_newest_first_with_actors(): void
    {
        Notification::fake();
        $org = $this->org();

        $this->as($this->admin)->post(route('platform.organizations.notes.store', $org), ['body' => 'Satu nota baru']);
        $this->as($this->admin)->post(route('platform.organizations.approve', $org));

        $events = app(OrganizationTimeline::class)->for($org->fresh());
        $titles = $events->pluck('title')->all();

        $this->assertSame('Approved', $titles[0]);
        $this->assertSame($this->admin->name, $events[0]['actor']);
        $this->assertContains('Internal note added', $titles);
        $this->assertContains('Domain verified', $titles);
        $this->assertSame('Application submitted', end($titles));
        $this->assertSame('Dr. Hafiz Rahman', $events->last()['actor']);
        $this->assertTrue($events->pluck('at')->every(fn ($at, $i) => $i === 0 || $at <= $events[$i - 1]['at']), 'sorted newest first');
    }

    public function test_timeline_shows_resubmission_and_the_earlier_rejection_reason(): void
    {
        $org = $this->org('Syarikat Hantar Semula Berhad');

        $events = app(OrganizationTimeline::class)->for($org);

        $this->assertSame('Resubmitted (attempt 2)', $events->first()['title']);
        $rejected = $events->firstWhere('title', 'Rejected');
        $this->assertSame('SSM number does not match the company name. Please check and resubmit.', $rejected['detail']);
    }

    public function test_timeline_only_includes_this_organizations_events(): void
    {
        $this->seed(OrganizationReviewSeeder::class);
        $mine = Organization::where('name', 'Kolej Universiti Skip DNS')->first();
        $other = Organization::where('name', 'Universiti Teknologi Contoh')->first();

        Audit::log('organization.note_added', $other, ['note_id' => 1], $this->admin);

        $titles = app(OrganizationTimeline::class)->for($mine)->pluck('title')->all();
        $this->assertNotContains('Internal note added', $titles);
    }

    public function test_detail_page_renders_checklist_notes_and_activity(): void
    {
        $org = $this->org();

        $this->as($this->admin)->get(route('platform.organizations.show', $org))
            ->assertOk()
            ->assertSee('Review checklist')
            ->assertSee('Name matches')
            ->assertSee('Domain valid')
            ->assertSee('SSM valid')
            ->assertSee('Admin authorised')
            ->assertSee('Internal notes')
            ->assertSee('Both domains verified. Still need to confirm the registrar')
            ->assertSee('Activity')
            ->assertSee('Application submitted')
            ->assertSee('Domain verified')
            ->assertSee('does not block approval');
    }
}
