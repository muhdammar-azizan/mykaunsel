<?php

namespace Tests\Feature\Platform;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\SubscriptionAssignment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\PlatformSettings;
use App\Services\SubscriptionPlanService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Counselor\CounselorTestCase;

/**
 * Pricing & Promotions, step 1: the Plans tab and the commission setting. There is no payment: everything here is a record.
 */
class PricingPlansTest extends CounselorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSamples();
    }

    private function plan(string $type = 'university', string $name = 'Starter', int $min = 1, ?int $max = 500, int $price = 120000, bool $active = true): SubscriptionPlan
    {
        return SubscriptionPlan::create(['org_type' => $type, 'name' => $name, 'min_members' => $min, 'max_members' => $max, 'price_cents' => $price, 'active' => $active, 'version' => 1]);
    }

    private function form(array $over = []): array
    {
        return array_replace(['org_type' => 'university', 'name' => 'Starter', 'min_members' => 1, 'max_members' => 500, 'price' => '1200.00', 'active' => '1'], $over);
    }

    private function add(array $over = [])
    {
        return $this->as()->post(route('platform.pricing.plans.store'), $this->form($over));
    }

    private function edit(SubscriptionPlan $plan, array $over = [], ?int $version = null)
    {
        $base = [
            'org_type' => $plan->org_type->value, 'name' => $plan->name, 'min_members' => $plan->min_members, 'max_members' => $plan->max_members,
            'price' => \App\Support\Money::plain($plan->price_cents), 'active' => $plan->active ? '1' : null, 'version' => $version ?? $plan->version,
        ];

        return $this->as()->post(route('platform.pricing.plans.update', $plan), array_replace($base, $over));
    }
    /** A fresh reader each time: the settings class remembers what it read for the rest of the request. */
    private function commission(): float
    {
        return (new PlatformSettings)->commissionPercent();
    }

    private function logs(string $action)
    {
        return AuditLog::where('action', $action)->get();
    }

    private function umpsa(): Organization
    {
        return Organization::where('name', 'like', 'Universiti Malaysia Pahang%')->firstOrFail();
    }

    /** Gives an organization a plan the way a real assignment will: a row with the prices as they were. */
    private function assign(Organization $org, SubscriptionPlan $plan, ?int $final = null): SubscriptionAssignment
    {
        $a = SubscriptionAssignment::create([
            'org_id' => $org->id, 'plan_id' => $plan->id, 'plan_price_cents' => $plan->price_cents, 'discount_cents' => ($plan->price_cents - ($final ?? $plan->price_cents)),
            'final_price_cents' => $final ?? $plan->price_cents, 'starts_on' => today(), 'ends_on' => today()->addYear(),
        ]);
        $org->forceFill(['current_assignment_id' => $a->id])->save();

        return $a;
    }

    // ---- who may see it --------------------------------------------------------------------------------------

    public function test_only_platform_admins_can_use_the_page_and_its_actions(): void
    {
        $orgAdmin = User::where('email', 'afif@umpsa.edu.my')->firstOrFail();
        $plan = $this->plan();
        $session = ['current_org_id' => $this->umpsa()->id, 'current_role' => 'org_admin'];

        foreach ([
            fn ($t) => $t->get(route('platform.pricing')),
            fn ($t) => $t->post(route('platform.pricing.plans.store'), $this->form(['name' => 'Sneaky'])),
            fn ($t) => $t->post(route('platform.pricing.plans.update', $plan), $this->form(['name' => 'Hacked', 'version' => 1])),
            fn ($t) => $t->post(route('platform.pricing.commission'), ['commission_percent' => '99']),
        ] as $call) {
            $this->flushSession();
            $call($this->actingAs($orgAdmin)->withSession($session))->assertForbidden();
        }

        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->get(route('platform.pricing'))->assertRedirect(route('login'));

        $this->assertSame(['Starter'], SubscriptionPlan::pluck('name')->all());
        $this->assertSame(10.0, $this->commission());
    }

    public function test_the_page_says_nothing_is_charged_and_is_in_the_menu(): void
    {
        $this->as()->get(route('platform.pricing'))->assertOk()
            ->assertSee('No payment is processed')->assertSee('examples')->assertSee('exclude tax (SST)')
            ->assertSee('Pricing &amp; Promotions', false);

        $this->as()->get(route('platform.dashboard'))->assertSee(route('platform.pricing'), false);
    }

    public function test_each_group_has_an_empty_state(): void
    {
        $this->as()->get(route('platform.pricing'))->assertOk()
            ->assertSee('No university plans yet')->assertSee('No corporate plans yet')->assertSee('Add plan');
    }

    // ---- adding a plan ---------------------------------------------------------------------------------------

    public function test_a_plan_is_stored_in_whole_sen_and_audited_with_ids_and_numbers_only(): void
    {
        $this->add(['name' => 'Starter <b>Plan</b>', 'price' => '1,200.50'])->assertSessionHasNoErrors()->assertSessionHas('status');

        $plan = SubscriptionPlan::sole();
        $this->assertSame([120050, 1, 500, true, 1], [$plan->price_cents, $plan->min_members, $plan->max_members, $plan->active, $plan->version]);

        $log = $this->logs('plan.created')->sole();
        $this->assertSame(['plan', $plan->id, $this->admin->id], [$log->target_type, (int) $log->target_id, (int) $log->actor_user_id]);
        $this->assertSame(['plan_id' => $plan->id, 'org_type' => 'university', 'min_members' => 1, 'max_members' => 500, 'price_cents' => 120050, 'active' => true], $log->metadata);
        $this->assertStringNotContainsString('Starter', json_encode($log->metadata), 'the name is not in the metadata');
    }

    public function test_prices_are_read_exactly(): void
    {
        foreach (['1200' => 120000, '1200.5' => 120050, '1,200.05' => 120005, '0.01' => 1, '999999.99' => 99999999] as $typed => $cents) {
            SubscriptionPlan::query()->delete();
            $this->add(['price' => (string) $typed])->assertSessionHasNoErrors();
            $this->assertSame($cents, SubscriptionPlan::sole()->price_cents, $typed);
        }
    }

    public function test_bad_prices_are_refused_and_nothing_is_saved(): void
    {
        foreach (['0', '0.00', '-5', '12.345', 'abc', 'RM 100', '1e3', '', '1,20', '1000000.01', '12..5'] as $bad) {
            $this->add(['price' => $bad])->assertSessionHasErrorsIn('plan-new-university', 'price');
        }

        $this->assertSame(0, SubscriptionPlan::count());
        $this->assertCount(0, $this->logs('plan.created'));
    }

    public function test_the_member_range_is_checked(): void
    {
        $this->add(['min_members' => 600, 'max_members' => 500])->assertSessionHasErrorsIn('plan-new-university', 'max_members');
        $this->add(['min_members' => 0])->assertSessionHasErrorsIn('plan-new-university', 'min_members');
        $this->add(['min_members' => ''])->assertSessionHasErrorsIn('plan-new-university', 'min_members');
        $this->add(['min_members' => '12.5'])->assertSessionHasErrorsIn('plan-new-university', 'min_members');
        $this->add(['name' => ''])->assertSessionHasErrorsIn('plan-new-university', 'name');

        $this->assertSame(0, SubscriptionPlan::count());

        // Equal is fine; empty maximum means no upper limit.
        $this->add(['min_members' => 500, 'max_members' => 500])->assertSessionHasNoErrors();
        $this->add(['name' => 'Big', 'min_members' => 501, 'max_members' => ''])->assertSessionHasNoErrors();
        $this->assertNull(SubscriptionPlan::where('name', 'Big')->sole()->max_members);
    }

    public function test_only_universities_and_corporates_have_plans(): void
    {
        foreach (['clinic', 'platform', 'individual', ''] as $type) {
            $this->as()->post(route('platform.pricing.plans.store'), $this->form(['org_type' => $type]))->assertStatus(422);
        }

        $this->assertSame(0, SubscriptionPlan::count());
    }

    // ---- overlaps and gaps -----------------------------------------------------------------------------------

    public function test_active_plans_of_the_same_type_cannot_overlap(): void
    {
        $this->plan(name: 'Starter', min: 1, max: 500);

        // Sharing even one number is an overlap.
        $this->add(['name' => 'Clash', 'min_members' => 500, 'max_members' => 900])->assertSessionHasErrorsIn('plan-new-university', 'form');
        $this->add(['name' => 'Inside', 'min_members' => 10, 'max_members' => 20])->assertSessionHasErrorsIn('plan-new-university', 'form');
        $this->add(['name' => 'Open', 'min_members' => 100, 'max_members' => ''])->assertSessionHasErrorsIn('plan-new-university', 'form');
        $this->assertSame(1, SubscriptionPlan::count());

        $message = session('errors')->getBag('plan-new-university')->first('form');
        $this->assertStringContainsString('"Starter"', $message);
        $this->assertStringContainsString('1–500', $message);

        // The next number is not.
        $this->add(['name' => 'Next', 'min_members' => 501, 'max_members' => 900])->assertSessionHasNoErrors();
        $this->assertSame(2, SubscriptionPlan::count());
    }

    public function test_an_open_ended_plan_overlaps_everything_above_its_start(): void
    {
        $this->plan(name: 'Campus', min: 5000, max: null);

        $this->add(['name' => 'Huge', 'min_members' => 9000, 'max_members' => 9500])->assertSessionHasErrorsIn('plan-new-university', 'form');
        $this->add(['name' => 'Below', 'min_members' => 1, 'max_members' => 4999])->assertSessionHasNoErrors();
    }

    public function test_inactive_plans_and_other_types_do_not_count_for_overlaps(): void
    {
        $this->plan(name: 'Old', min: 1, max: 500, active: false);
        $this->plan(type: 'corporate', name: 'Corp', min: 1, max: 500);

        $this->add(['name' => 'New', 'min_members' => 1, 'max_members' => 500])->assertSessionHasNoErrors();
        $this->add(['name' => 'Also inactive', 'min_members' => 1, 'max_members' => 500, 'active' => null])->assertSessionHasNoErrors();
        $this->assertSame(4, SubscriptionPlan::count());
    }

    public function test_editing_into_an_overlap_or_reactivating_into_one_is_refused(): void
    {
        $a = $this->plan(name: 'A', min: 1, max: 500);
        $b = $this->plan(name: 'B', min: 501, max: 900);
        $old = $this->plan(name: 'Old', min: 100, max: 200, active: false);

        $this->edit($b, ['min_members' => 400])->assertSessionHasErrorsIn("plan-{$b->id}", 'form');
        $this->assertSame(501, $b->fresh()->min_members);

        $this->edit($old, ['active' => '1'])->assertSessionHasErrorsIn("plan-{$old->id}", 'form');
        $this->assertFalse($old->fresh()->active);
        $this->assertSame(1, $a->fresh()->version);
    }

    public function test_a_gap_in_coverage_is_a_warning_not_an_error(): void
    {
        $this->add(['name' => 'Starter', 'min_members' => 1, 'max_members' => 500])->assertSessionHasNoErrors()
            ->assertSessionHas('warnings', fn ($w) => str_contains($w[0], '501+') && str_contains($w[0], 'University'));

        $this->add(['name' => 'Big', 'min_members' => 1000, 'max_members' => ''])->assertSessionHasNoErrors()
            ->assertSessionHas('warnings', fn ($w) => str_contains($w[0], '501–999') && ! str_contains($w[0], '1,000+'));

        $this->as()->get(route('platform.pricing'))->assertSee('501–999');

        $this->add(['name' => 'Middle', 'min_members' => 501, 'max_members' => 999])->assertSessionHasNoErrors()->assertSessionMissing('warnings');
    }

    public function test_deactivating_a_plan_is_allowed_and_shows_the_range_it_leaves_uncovered(): void
    {
        $this->plan(name: 'A', min: 1, max: 500);
        $mid = $this->plan(name: 'B', min: 501, max: 900);
        $this->plan(name: 'C', min: 901, max: null);

        $this->edit($mid, ['active' => null])->assertSessionHasNoErrors()
            ->assertSessionHas('warnings', fn ($w) => str_contains($w[0], '501–900'));

        $this->assertFalse($mid->fresh()->active);
        $this->assertCount(1, $this->logs('plan.deactivated'));
    }

    public function test_the_coverage_calculation(): void
    {
        $service = app(SubscriptionPlanService::class);

        $this->assertSame([[1, null]], $service->coverageGaps('university'));
        $this->plan(name: 'A', min: 1, max: 500);
        $this->assertSame([[501, null]], $service->coverageGaps('university'));
        $this->plan(name: 'B', min: 501, max: null);
        $this->assertSame([], $service->coverageGaps('university'));

        $this->plan(type: 'corporate', name: 'C', min: 10, max: 20);
        $this->plan(type: 'corporate', name: 'D', min: 30, max: 40, active: true);
        $this->plan(type: 'corporate', name: 'E', min: 1, max: 5, active: false);
        $this->assertSame([[1, 9], [21, 29], [41, null]], $service->coverageGaps('corporate'));
    }

    // ---- editing ---------------------------------------------------------------------------------------------

    public function test_editing_a_price_is_audited_with_the_old_and_new_price(): void
    {
        $plan = $this->plan(price: 120000);

        $this->edit($plan, ['price' => '1500.00'])->assertSessionHasNoErrors()->assertSessionHas('status');

        $this->assertSame([150000, 2], [$plan->fresh()->price_cents, $plan->fresh()->version]);
        $log = $this->logs('plan.updated')->sole();
        $this->assertSame(['plan_id' => $plan->id, 'changes' => ['price_cents' => ['old' => 120000, 'new' => 150000]]], $log->metadata);
        $this->assertCount(0, $this->logs('plan.activated') ?? []);
    }

    public function test_activating_and_deactivating_have_their_own_entries(): void
    {
        $plan = $this->plan();

        $this->edit($plan, ['active' => null]);
        $this->edit($plan->fresh(), ['active' => '1']);

        $this->assertSame(['plan.deactivated', 'plan.activated'], AuditLog::whereIn('action', ['plan.deactivated', 'plan.activated'])->orderBy('id')->pluck('action')->all());
        $this->assertCount(0, $this->logs('plan.updated'), 'only the switch changed');
        $this->assertSame(3, $plan->fresh()->version);
    }

    public function test_saving_without_a_change_makes_no_new_version_and_no_entry(): void
    {
        $plan = $this->plan();
        $before = AuditLog::count();

        $this->edit($plan)->assertSessionHas('status', 'Nothing was changed.');

        $this->assertSame([1, $before], [$plan->fresh()->version, AuditLog::count()]);
    }

    public function test_the_edit_form_warns_when_organizations_are_on_the_plan(): void
    {
        $plan = $this->plan();
        $other = $this->plan(name: 'Other', min: 501, max: 900);
        $this->assign($this->umpsa(), $plan);

        $page = $this->as()->get(route('platform.pricing'))->assertOk();

        $page->assertSee('Existing organizations keep their current price. Only new assignments use the new price.');
        $this->assertSame(1, substr_count($page->getContent(), 'Existing organizations keep their current price.'), 'only for the plan that is in use');
        $this->assertNotNull($other);
    }

    public function test_editing_a_price_never_changes_what_organizations_were_given(): void
    {
        $plan = $this->plan(price: 120000);
        $assignment = $this->assign($this->umpsa(), $plan, final: 96000);

        $this->edit($plan, ['price' => '3000.00'])->assertSessionHasNoErrors();

        $assignment->refresh();
        $this->assertSame([120000, 24000, 96000], [$assignment->plan_price_cents, $assignment->discount_cents, $assignment->final_price_cents]);
        $this->assertSame(300000, $plan->fresh()->price_cents);
        $this->assertSame('RM 960.00', \App\Support\Money::rm($this->umpsa()->currentAssignment->final_price_cents));
    }

    public function test_the_type_of_a_plan_cannot_change_once_an_organization_has_been_given_it(): void
    {
        $plan = $this->plan();
        $free = $this->plan(name: 'Free to move', min: 600, max: 700);
        $this->assign($this->umpsa(), $plan);

        $this->edit($plan, ['org_type' => 'corporate'])->assertSessionHasErrorsIn("plan-{$plan->id}", 'form');
        $this->assertSame('university', $plan->fresh()->org_type->value);

        // Not even after the organization moved to another plan: the history still points here.
        $this->assign($this->umpsa(), $free);
        $this->edit($plan->fresh(), ['org_type' => 'corporate'])->assertSessionHasErrorsIn("plan-{$plan->id}", 'form');

        // A plan nobody was ever given can change type.
        $unused = $this->plan(name: 'Unused', min: 800, max: 900);
        $this->edit($unused, ['org_type' => 'corporate'])->assertSessionHasNoErrors();
        $this->assertSame('corporate', $unused->fresh()->org_type->value);
    }

    public function test_two_admins_cannot_silently_overwrite_each_other(): void
    {
        $plan = $this->plan(price: 120000);
        $seen = $plan->version;

        $this->edit($plan, ['price' => '1500.00'], $seen)->assertSessionHasNoErrors();      // the first admin saves
        $this->edit($plan, ['price' => '2000.00'], $seen)                                    // the second still has the old page
            ->assertSessionHasErrorsIn("plan-{$plan->id}", 'form');

        $this->assertSame(150000, $plan->fresh()->price_cents, 'the first save stands');
        $this->assertStringContainsString('Someone else changed this plan', session('errors')->getBag("plan-{$plan->id}")->first('form'));
        $this->assertCount(1, $this->logs('plan.updated'));
    }

    public function test_a_missing_or_bad_version_is_refused(): void
    {
        $plan = $this->plan();

        foreach ([null, 0, 'x'] as $bad) {
            $this->as()->post(route('platform.pricing.plans.update', $plan), $this->form(['name' => 'Renamed', 'version' => $bad]))
                ->assertSessionHasErrorsIn("plan-{$plan->id}", 'version');
        }

        $this->assertSame('Starter', $plan->fresh()->name);
    }

    // ---- the service does not trust its caller ---------------------------------------------------------------

    public function test_the_service_repeats_every_shape_rule_so_no_caller_can_skip_the_form(): void
    {
        $service = app(SubscriptionPlanService::class);
        $good = ['org_type' => 'university', 'name' => 'Starter', 'min_members' => 1, 'max_members' => 500, 'price_cents' => 120000, 'active' => true];

        foreach ([
            ['price_cents' => 0], ['price_cents' => -1], ['price_cents' => 100_000_001], ['min_members' => 0], ['min_members' => 1_000_001],
            ['max_members' => 0], ['min_members' => 10, 'max_members' => 9], ['name' => '   '], ['name' => str_repeat('x', 101)],
            ['org_type' => 'clinic'], ['org_type' => 'platform'],
        ] as $bad) {
            try {
                $service->create($this->admin, array_replace($good, $bad));
                $this->fail('accepted '.json_encode($bad));
            } catch (\App\Exceptions\PricingChangeBlocked) {
                // expected
            }
        }

        $this->assertSame(0, SubscriptionPlan::count());
        $this->assertCount(0, $this->logs('plan.created'));
        $this->assertSame(1, $service->create($this->admin, $good)['plan']->version);
    }

    public function test_the_service_refuses_a_stale_version_and_the_save_itself_is_conditional_on_it(): void
    {
        $service = app(SubscriptionPlanService::class);
        $plan = $this->plan(price: 120000);
        $data = ['org_type' => 'university', 'name' => 'Starter', 'min_members' => 1, 'max_members' => 500, 'price_cents' => 150000, 'active' => true];

        $service->update($this->admin, $plan, 1, $data);

        try {
            $service->update($this->admin, $plan, 1, ['price_cents' => 200000] + $data);
            $this->fail('a stale version was accepted');
        } catch (\App\Exceptions\PricingChangeBlocked $e) {
            $this->assertTrue($e->conflict);
        }
        $this->assertSame([150000, 2], [$plan->fresh()->price_cents, $plan->fresh()->version]);

        // The save itself is conditional on the version (so a write that slips in between the check and the update still
        // changes nothing): the UPDATE statement carries "version = ?" in its WHERE.
        DB::enableQueryLog();
        $service->update($this->admin, $plan->fresh(), 2, ['price_cents' => 160000] + $data);
        $update = collect(DB::getQueryLog())->first(fn ($q) => str_starts_with($q['query'], 'update "subscription_plans"'));
        DB::disableQueryLog();

        $this->assertNotNull($update);
        $this->assertMatchesRegularExpression('/where "subscription_plans"\."id" = \? and "version" = \?/', $update['query']);
        $this->assertSame(160000, $plan->fresh()->price_cents);
    }

    // ---- never deleted ---------------------------------------------------------------------------------------

    public function test_plans_cannot_be_deleted_from_the_page_or_the_database_once_used(): void
    {
        $plan = $this->plan();

        $this->as()->delete('/platform/pricing/plans/'.$plan->id)->assertStatus(405);
        $this->as()->post('/platform/pricing/plans/'.$plan->id.'/delete')->assertNotFound();
        $this->assertNotNull($plan->fresh());

        $this->assign($this->umpsa(), $plan);

        $this->expectException(QueryException::class);
        DB::table('subscription_plans')->where('id', $plan->id)->delete();
    }

    public function test_an_inactive_plan_stays_with_the_organizations_on_it_and_shows_how_many(): void
    {
        $plan = $this->plan();
        $this->assign($this->umpsa(), $plan);

        $this->edit($plan, ['active' => null])->assertSessionHasNoErrors();

        $this->assertSame($plan->id, $this->umpsa()->fresh()->currentAssignment->plan_id);
        $this->as()->get(route('platform.pricing'))->assertSee('Inactive')->assertSee('1 organization uses this plan');
    }

    // ---- output ----------------------------------------------------------------------------------------------

    public function test_the_page_shows_names_ranges_and_prices_and_escapes_text(): void
    {
        $this->plan(name: '<script>alert(1)</script>', min: 5000, max: null, price: 120050);

        $page = $this->as()->get(route('platform.pricing'))->assertOk();

        $page->assertSee('RM 1,200.50')->assertSee('5,000+ members')->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }

    // ---- the commission --------------------------------------------------------------------------------------

    public function test_the_commission_starts_at_the_example_value_and_says_it_is_a_record_only(): void
    {
        $this->as()->get(route('platform.pricing'))->assertSee('Record only. No payments are processed.')->assertSee('value="10"', false);
        $this->assertSame(10.0, $this->commission());
    }

    public function test_the_commission_is_saved_with_an_audit_entry(): void
    {
        $this->as()->post(route('platform.pricing.commission'), ['commission_percent' => '12.5'])->assertSessionHasNoErrors()->assertSessionHas('status');

        $this->assertSame(12.5, $this->commission());
        $log = $this->logs('commission.updated')->sole();
        $this->assertEquals(['changes' => ['commission_percent' => ['old' => 10, 'new' => 12.5]]], $log->metadata);
        $this->assertSame($this->admin->id, (int) $log->actor_user_id);

        $this->as()->get(route('platform.pricing'))->assertSee('value="12.5"', false);
    }

    public function test_saving_the_same_commission_again_changes_and_logs_nothing(): void
    {
        $this->as()->post(route('platform.pricing.commission'), ['commission_percent' => '10'])->assertSessionHas('status', 'Nothing was changed.');
        $this->as()->post(route('platform.pricing.commission'), ['commission_percent' => '10.00'])->assertSessionHas('status', 'Nothing was changed.');

        $this->assertCount(0, $this->logs('commission.updated'));
    }

    public function test_the_commission_must_be_from_0_to_100(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class); // many tries in a row; the limit has its own test

        foreach (['101', '100.01', '-1', 'abc', '', '10.555', '1e1', '1000', '10%'] as $bad) {
            $this->as()->post(route('platform.pricing.commission'), ['commission_percent' => $bad])->assertSessionHasErrorsIn('commission', 'commission_percent');
        }

        $this->assertSame(10.0, $this->commission());

        foreach (['0', '100', '0.01', '99.99'] as $good) {
            $this->as()->post(route('platform.pricing.commission'), ['commission_percent' => $good])->assertSessionHasNoErrors();
            $this->assertSame((float) $good, $this->commission());
        }
    }
}
