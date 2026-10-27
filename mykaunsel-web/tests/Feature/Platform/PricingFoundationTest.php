<?php

namespace Tests\Feature\Platform;

use App\Facades\Audit;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\SubscriptionAssignment;
use App\Models\SubscriptionPlan;
use App\Services\PricingStats;
use App\Services\SubscriptionPlanService;
use App\Support\AuditCatalogue;
use App\Support\Money;
use Database\Seeders\PricingSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;
use Tests\Feature\Counselor\CounselorTestCase;

/**
 * Pricing & Promotions, step 1, the parts around the page: money, the audit catalogue, the old tier column and its migration,
 * the mapping command, the example plans and the dashboard numbers.
 */
class PricingFoundationTest extends CounselorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSamples();
    }

    private function umpsa(): Organization
    {
        return Organization::where('name', 'like', 'Universiti Malaysia Pahang%')->firstOrFail();
    }

    private function klinik(): Organization
    {
        return Organization::where('org_type', 'clinic')->firstOrFail();
    }

    private function plan(string $name, string $type = 'university', int $min = 1, ?int $max = null, int $price = 120000, bool $active = true): SubscriptionPlan
    {
        return SubscriptionPlan::create(['org_type' => $type, 'name' => $name, 'min_members' => $min, 'max_members' => $max, 'price_cents' => $price, 'active' => $active, 'version' => 1]);
    }

    /** Brings back the old text column, as it is in a database that has not been migrated yet. */
    private function withLegacyColumn(): void
    {
        Schema::table('organizations', fn (Blueprint $table) => $table->string('subscription_tier')->nullable());
    }

    private function tier(Organization $org, ?string $text): void
    {
        DB::table('organizations')->where('id', $org->id)->update(['subscription_tier' => $text]);
    }

    private function dropMigration(): object
    {
        return require database_path('migrations/2026_11_02_000002_drop_legacy_subscription_tier_from_organizations.php');
    }

    // ---- money -----------------------------------------------------------------------------------------------

    public function test_money_is_shown_to_the_sen(): void
    {
        $this->assertSame('RM 0.00', Money::rm(0));
        $this->assertSame('RM 0.05', Money::rm(5));
        $this->assertSame('RM 0.99', Money::rm(99));
        $this->assertSame('RM 1.00', Money::rm(100));
        $this->assertSame('RM 1,200.00', Money::rm(120000));
        $this->assertSame('RM 1,200.50', Money::rm(120050));
        $this->assertSame('RM 1,200.05', Money::rm(120005));
        $this->assertSame('RM 1,000,000.00', Money::rm(100000000));
        $this->assertSame('1200.50', Money::plain(120050));
    }

    public function test_a_typed_amount_is_read_digit_by_digit_and_never_as_a_float(): void
    {
        $this->assertSame(120000, Money::parseRm('1200'));
        $this->assertSame(120000, Money::parseRm(' 1,200.00 '));
        $this->assertSame(10, Money::parseRm('0.1'));
        $this->assertSame(1999, Money::parseRm('19.99'), 'a float would give 1998.9999…');
        $this->assertSame(29, Money::parseRm('0.29'), 'a float would give 28.99999…');

        foreach (['', 'abc', '-1', '1.234', '1,00', '1e3', 'RM1', '1.', '.5', '1 000', '1.2.3', str_repeat('9', 13)] as $bad) {
            $this->assertNull(Money::parseRm($bad), "[{$bad}]");
        }
    }

    // ---- the audit catalogue ---------------------------------------------------------------------------------

    public function test_the_new_keys_exist_in_the_right_categories_and_there_is_no_plan_deleted(): void
    {
        $actions = config('audit.actions');

        foreach (['plan.created', 'plan.updated', 'plan.activated', 'plan.deactivated', 'promo.created', 'promo.updated', 'promo.enabled', 'promo.disabled', 'promo.deleted', 'promo.redeemed', 'commission.updated'] as $key) {
            $this->assertSame('pricing', $actions[$key]['category'], $key);
        }
        foreach (['organization.plan_assigned', 'organization.plan_changed'] as $key) {
            $this->assertSame('organizations', $actions[$key]['category'], $key);
        }
        $this->assertArrayNotHasKey('plan.deleted', $actions);
    }

    public function test_the_old_pricing_keys_are_no_longer_writable_but_old_rows_stay_readable(): void
    {
        foreach (['pricing.plan_created', 'pricing.plan_updated', 'pricing.plan_deleted', 'pricing.promo_created', 'pricing.promo_updated', 'pricing.promo_deleted'] as $old) {
            $this->assertArrayNotHasKey($old, config('audit.actions'), $old);
            $this->assertArrayHasKey($old, config('audit.legacy_actions'), $old);

            $shown = AuditCatalogue::describe($old);
            $this->assertSame('pricing', $shown['category'], $old);
            $this->assertNotSame('other', $shown['category']);
        }
        $this->assertSame('Plan deleted', AuditCatalogue::describe('pricing.plan_deleted')['label']);

        $this->expectException(InvalidArgumentException::class);
        Audit::log('pricing.plan_created', null, [], $this->admin);
    }

    public function test_an_old_row_in_the_database_is_not_rewritten_and_shows_on_the_audit_page(): void
    {
        $row = AuditLog::create(['actor_type' => 'user', 'actor_user_id' => $this->admin->id, 'action' => 'pricing.plan_updated', 'category' => 'pricing', 'metadata' => ['changes' => ['monthly_price' => ['old' => '199.00', 'new' => '249.00']]]]);

        $this->as()->get(route('platform.audit-log'))->assertOk()->assertSee('Plan updated');

        $this->assertSame('pricing.plan_updated', $row->fresh()->action, 'the stored key is untouched');
    }

    // ---- the old tier column ---------------------------------------------------------------------------------

    public function test_the_column_is_gone_after_the_migrations_and_the_page_reads_the_plan(): void
    {
        $this->assertFalse(Schema::hasColumn('organizations', 'subscription_tier'));
        $this->assertTrue(Schema::hasColumn('organizations', 'current_assignment_id'));

        $org = $this->umpsa();
        $this->as()->get(route('platform.organizations.show', $org))->assertOk()->assertSee('Not set');

        $plan = $this->plan('Starter');
        $a = SubscriptionAssignment::create(['org_id' => $org->id, 'plan_id' => $plan->id, 'plan_price_cents' => 120000, 'discount_cents' => 0, 'final_price_cents' => 120000, 'starts_on' => today(), 'ends_on' => today()->addYear()]);
        $org->forceFill(['current_assignment_id' => $a->id])->save();

        $this->as()->get(route('platform.organizations.show', $org))->assertOk()->assertSee('Starter')->assertSee('RM 1,200.00 a year');
    }

    public function test_the_migration_stops_before_changing_anything_and_names_the_organizations_it_cannot_map(): void
    {
        $this->withLegacyColumn();
        $a = $this->umpsa();
        $b = $this->klinik();
        $this->tier($a, 'Gold');
        $this->tier($b, 'Silver');

        try {
            $this->dropMigration()->up();
            $this->fail('the migration should have stopped');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString("organization ids: {$a->id}, {$b->id}", $e->getMessage());
            $this->assertStringContainsString('2 organization(s)', $e->getMessage());
            $this->assertStringContainsString('pricing:map-legacy-tiers', $e->getMessage());
            $this->assertStringContainsString('Nothing was changed', $e->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('organizations', 'subscription_tier'), 'the column is still there');
        $this->assertSame(['Gold', 'Silver'], DB::table('organizations')->whereIn('id', [$a->id, $b->id])->orderBy('id')->pluck('subscription_tier')->sort()->values()->all());
    }

    public function test_an_organization_whose_tier_was_taken_over_by_a_plan_does_not_block_the_migration(): void
    {
        $this->withLegacyColumn();
        $org = $this->umpsa();
        $plan = $this->plan('Gold');
        $a = SubscriptionAssignment::create(['org_id' => $org->id, 'plan_id' => $plan->id, 'plan_price_cents' => 1, 'final_price_cents' => 1, 'starts_on' => today(), 'ends_on' => today()->addYear()]);
        $org->forceFill(['current_assignment_id' => $a->id])->save();
        $this->tier($org, 'Gold');
        $this->tier($this->klinik(), '   ');   // blank text is not a value

        $this->dropMigration()->up();

        $this->assertFalse(Schema::hasColumn('organizations', 'subscription_tier'));
    }

    // ---- the mapping command ---------------------------------------------------------------------------------

    public function test_the_command_does_nothing_when_there_is_nothing_to_map(): void
    {
        $this->artisan('pricing:map-legacy-tiers')->expectsOutputToContain('already gone')->assertSuccessful();

        $this->withLegacyColumn();
        $this->artisan('pricing:map-legacy-tiers')->expectsOutputToContain('No organization has an old tier text')->assertSuccessful();
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $this->withLegacyColumn();
        $org = $this->umpsa();
        $this->plan('Gold');
        $this->tier($org, 'gold');

        $this->artisan('pricing:map-legacy-tiers', ['--dry-run' => true])->expectsOutputToContain("Would map organization #{$org->id}")->assertSuccessful();

        $this->assertNull($org->fresh()->current_assignment_id);
        $this->assertSame('gold', DB::table('organizations')->where('id', $org->id)->value('subscription_tier'));
        $this->assertSame(0, SubscriptionAssignment::count());
    }

    public function test_mapping_gives_a_normal_assignment_clears_the_text_and_is_audited_as_the_system(): void
    {
        $this->withLegacyColumn();
        $org = $this->umpsa();
        $plan = $this->plan('Gold', price: 250000);
        $this->tier($org, '  GOLD ');

        $this->artisan('pricing:map-legacy-tiers')->assertSuccessful();

        $org->refresh();
        $a = $org->currentAssignment;
        $this->assertSame([$plan->id, 250000, 0, 250000, null, null], [$a->plan_id, $a->plan_price_cents, $a->discount_cents, $a->final_price_cents, $a->promo_code_id, $a->assigned_by]);
        $this->assertSame($a->starts_on->copy()->addYear()->toDateString(), $a->ends_on->toDateString());
        $this->assertNull(DB::table('organizations')->where('id', $org->id)->value('subscription_tier'));
        $this->assertNotNull($org->subscription_ends_at);

        $log = AuditLog::where('action', 'organization.plan_assigned')->sole();
        $this->assertSame(['system', null, 'legacy_tier_mapping', $plan->id], [$log->actor_type, $log->actor_user_id, $log->metadata['source'], $log->metadata['plan_id']]);

        // And now the migration can run.
        $this->dropMigration()->up();
        $this->assertFalse(Schema::hasColumn('organizations', 'subscription_tier'));
    }

    public function test_the_plan_must_be_of_the_organizations_own_type_and_unmatched_ones_are_listed_and_left_alone(): void
    {
        $this->withLegacyColumn();
        $uni = $this->umpsa();
        $this->plan('Gold', type: 'corporate');   // same name, wrong type
        $this->tier($uni, 'Gold');

        $this->artisan('pricing:map-legacy-tiers')->expectsOutputToContain("#{$uni->id} \"Gold\"")->assertFailed();

        $this->assertNull($uni->fresh()->current_assignment_id);
        $this->assertSame('Gold', DB::table('organizations')->where('id', $uni->id)->value('subscription_tier'));
        $this->assertSame(0, AuditLog::where('action', 'organization.plan_assigned')->count());
    }

    public function test_a_partly_matching_run_maps_what_it_can_and_still_reports_the_rest(): void
    {
        $this->withLegacyColumn();
        $this->plan('Gold');
        $this->tier($this->umpsa(), 'Gold');
        $this->tier($this->klinik(), 'Nonsense');

        $this->artisan('pricing:map-legacy-tiers')->assertFailed();

        $this->assertNotNull($this->umpsa()->fresh()->current_assignment_id);
        $this->assertNull($this->klinik()->fresh()->current_assignment_id);
        $this->assertSame('Nonsense', DB::table('organizations')->where('id', $this->klinik()->id)->value('subscription_tier'));
    }

    // ---- the example data ------------------------------------------------------------------------------------

    public function test_the_example_plans_are_labelled_cover_every_university_size_and_include_an_inactive_corporate_plan(): void
    {
        $this->seed(PricingSeeder::class);

        $plans = SubscriptionPlan::orderBy('id')->get();
        $this->assertCount(5, $plans);
        $this->assertTrue($plans->every(fn ($p) => str_contains($p->name, '(example)')));
        $this->assertSame(3, $plans->where('org_type.value', 'university')->count());
        $this->assertSame([], app(SubscriptionPlanService::class)->coverageGaps('university'));
        $this->assertSame([], app(SubscriptionPlanService::class)->coverageGaps('corporate'), 'the one active corporate plan has no upper limit');
        $this->assertNull($plans->firstWhere('name', 'University Campus (example)')->max_members);
        $this->assertSame(1, $plans->where('org_type.value', 'corporate')->where('active', false)->count());

        $this->as()->get(route('platform.pricing'))->assertOk()->assertSee('5,000+ members')->assertSee('RM 1,200.00')->assertSee('Inactive');
    }

    public function test_the_example_seeder_refuses_to_run_on_a_real_server(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        app(PricingSeeder::class)->run();
    }

    public function test_the_full_seed_includes_the_example_plans_after_the_organizations(): void
    {
        $list = file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        $this->assertStringContainsString('PricingSeeder::class', $list);
        $this->assertLessThan(strpos($list, 'PricingSeeder::class'), strpos($list, 'OrganizationSeeder::class'));
    }

    // ---- the numbers for the dashboard -----------------------------------------------------------------------

    public function test_the_dashboard_numbers(): void
    {
        $stats = app(PricingStats::class);
        $this->assertSame([0, 0], [$stats->activePlans(), $stats->subscriptionsEndingWithin(30)]);

        $plan = $this->plan('Active one');
        $this->plan('Off', min: 600, max: 700, active: false);
        $this->assertSame(1, $stats->activePlans());

        $make = fn (Organization $org, int $days) => tap(SubscriptionAssignment::create([
            'org_id' => $org->id, 'plan_id' => $plan->id, 'plan_price_cents' => 1, 'final_price_cents' => 1, 'starts_on' => today()->subYear(), 'ends_on' => today()->addDays($days),
        ]), fn ($a) => $org->forceFill(['current_assignment_id' => $a->id])->save());

        $make($this->umpsa(), 10);
        $this->assertSame(1, $stats->subscriptionsEndingWithin(30));

        $make($this->umpsa()->fresh(), 45);   // the newer assignment is the current one; the 10-day one is history
        $this->assertSame(0, $stats->subscriptionsEndingWithin(30));
        $this->assertSame(1, $stats->subscriptionsEndingWithin(60));

        $make($this->klinik(), -3);            // already ended: not "ending soon"
        $this->assertSame(0, $stats->subscriptionsEndingWithin(30));
    }
}
