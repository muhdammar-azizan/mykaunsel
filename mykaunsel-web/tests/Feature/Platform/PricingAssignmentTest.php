<?php

namespace Tests\Feature\Platform;

use App\Enums\OrgStatus;
use App\Exceptions\PricingChangeBlocked;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\PromoCode;
use App\Models\SubscriptionAssignment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionAssignmentService;
use App\Support\OrganizationSize;
use Database\Seeders\PricingSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Counselor\CounselorTestCase;

/**
 * Pricing & Promotions, step 3: giving organizations a plan (Approve and Change plan), promo code redemption, snapshots and audit.
 * There is no payment: everything here is a record.
 */
class PricingAssignmentTest extends CounselorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSamples();
        $this->seed(\Database\Seeders\OrganizationReviewSeeder::class);
        Notification::fake();
    }

    private function service(): SubscriptionAssignmentService
    {
        return app(SubscriptionAssignmentService::class);
    }

    private function plan(string $name = 'Starter', string $type = 'university', int $min = 1, ?int $max = 500, int $price = 120000, bool $active = true): SubscriptionPlan
    {
        return SubscriptionPlan::create(['org_type' => $type, 'name' => $name, 'min_members' => $min, 'max_members' => $max, 'price_cents' => $price, 'active' => $active, 'version' => 1]);
    }

    private function promo(array $over = []): PromoCode
    {
        return PromoCode::create($over + [
            'code' => 'SAVE10', 'type' => 'percent', 'value' => 10, 'valid_from' => now()->subDay()->startOfMinute(), 'valid_until' => now()->addDays(30)->startOfMinute()->setSecond(59),
            'usage_limit' => null, 'used_count' => 0, 'applies_to' => 'all', 'disabled' => false, 'version' => 1,
        ]);
    }

    private function umpsa(): Organization
    {
        return Organization::where('name', 'like', 'Universiti Malaysia Pahang%')->firstOrFail();
    }

    private function pendingUniversity(?string $size = '51–200 members'): Organization
    {
        $org = Organization::where('subscription_status', OrgStatus::Pending->value)->where('org_type', 'university')->first()
            ?? Organization::where('subscription_status', OrgStatus::Pending->value)->firstOrFail();
        $org->forceFill(['org_type' => 'university', 'estimated_size' => $size])->save();

        return $org;
    }

    private function logs(string $action)
    {
        return AuditLog::where('action', $action)->get();
    }

    // ---- size and fit ----------------------------------------------------------------------------------------

    public function test_the_stated_size_is_read_in_every_format_the_data_has(): void
    {
        foreach ([
            '1–50 members' => [1, 50], '51–200 employees' => [51, 200], '1,000–5,000 members' => [1000, 5000], '5,000+ members' => [5000, null],
            '10 - 50' => [10, 50], '5,000 - 20,000' => [5000, 20000], '200 - 1,000' => [200, 1000], '5000+' => [5000, null],
        ] as $text => $range) {
            $this->assertSame($range, OrganizationSize::parse($text), $text);
        }

        foreach ([null, '', '   ', 'big', 'about 50', '50-10', 'Select a range'] as $unreadable) {
            $this->assertNull(OrganizationSize::parse($unreadable), (string) $unreadable);
        }
    }

    public function test_fit_is_whole_partial_or_none(): void
    {
        $this->assertSame('fits', OrganizationSize::fit(1, 500, [51, 200]));
        $this->assertSame('fits', OrganizationSize::fit(51, 200, [51, 200]));
        $this->assertSame('partial', OrganizationSize::fit(1, 100, [51, 200]));
        $this->assertSame('partial', OrganizationSize::fit(150, null, [51, 200]));
        $this->assertSame('none', OrganizationSize::fit(300, 500, [51, 200]));
        $this->assertSame('fits', OrganizationSize::fit(5000, null, [5000, null]), '"5,000+" is 5,000 up to no limit');
        $this->assertSame('partial', OrganizationSize::fit(1, 9000, [5000, null]));
        $this->assertSame('none', OrganizationSize::fit(1, 4999, [5000, null]));
    }

    public function test_only_active_plans_of_the_right_type_are_offered_best_fit_first(): void
    {
        $org = $this->pendingUniversity('51–200 members');
        $this->plan('Small', min: 1, max: 100);                      // partial
        $this->plan('Fits', min: 1, max: 500, price: 1);              // overlaps Small? no: only active ones are compared in the form, not here
        $this->plan('Far', min: 1000, max: 2000);                     // outside
        $this->plan('Switched off', min: 51, max: 200, active: false);
        $this->plan('Corp', type: 'corporate', min: 1, max: 500);

        $offer = $this->service()->offeredPlans($org);

        $this->assertSame(['Fits', 'Small'], $offer['plans']->map(fn ($r) => $r['plan']->name)->all());
        $this->assertSame(['fits', 'partial'], $offer['plans']->pluck('fit')->all());
        $this->assertSame('51–200', $offer['size_label']);
        $this->assertFalse($offer['none_cover']);
    }

    public function test_when_no_plan_covers_the_size_the_message_shows_and_approval_still_works(): void
    {
        $org = $this->pendingUniversity('201–1,000 members');
        $this->plan('Tiny', min: 1, max: 50);

        $this->assertTrue($this->service()->offeredPlans($org)['none_cover']);
        $this->as()->get(route('platform.organizations.show', $org))->assertOk()->assertSee('No plan covers this size.')->assertSee('You can still continue without a plan.');

        $this->as()->post(route('platform.organizations.approve', $org), ['plan_id' => '', 'promo_code' => ''])->assertSessionHas('status');
        $this->assertSame(OrgStatus::Active, $org->fresh()->subscription_status);
        $this->assertNull($org->fresh()->current_assignment_id);
    }

    public function test_an_unreadable_size_offers_every_active_plan_with_a_note(): void
    {
        $org = $this->pendingUniversity(null);
        $this->plan('A', min: 1, max: 100);
        $this->plan('B', min: 101, max: null);

        $offer = $this->service()->offeredPlans($org);
        $this->assertSame(['unknown', 'unknown'], $offer['plans']->pluck('fit')->all());

        $this->as()->get(route('platform.organizations.show', $org))->assertSee('Size not stated, so every active plan is shown.');
    }

    public function test_the_approve_form_lists_plans_with_fits_and_part_badges(): void
    {
        $org = $this->pendingUniversity('51–200 members');
        $this->plan('Whole', min: 1, max: 500);
        $this->plan('Half', type: 'university', min: 501, max: 900);   // outside: hidden
        $this->plan('Edge', min: 901, max: null, active: false);

        $page = $this->as()->get(route('platform.organizations.show', $org))->assertOk();
        $page->assertSee('Whole')->assertSee('Fits')->assertDontSee('Half')->assertDontSee('Edge')->assertSee('No plan for now')->assertSee('No payment is processed');
    }

    public function test_a_partly_covering_plan_is_shown_with_its_badge_not_hidden(): void
    {
        $org = $this->pendingUniversity('51–200 members');
        $this->plan('Partial', min: 100, max: 900);

        $this->as()->get(route('platform.organizations.show', $org))->assertSee('Covers part of the stated size');
    }

    // ---- the price -------------------------------------------------------------------------------------------

    public function test_percentages_round_to_the_nearest_sen_and_fixed_amounts_are_capped(): void
    {
        $d = fn (int $price, string $type, int $value) => SubscriptionAssignmentService::discount($price, $type, $value);

        $this->assertSame(12000, $d(120000, 'percent', 10));
        $this->assertSame(33, $d(100, 'percent', 33));              // 33.00 sen exactly
        $this->assertSame(2, $d(5, 'percent', 33));                 // 1.65 -> 2
        $this->assertSame(1, $d(5, 'percent', 10));                 // 0.5  -> 1 (half up)
        $this->assertSame(0, $d(4, 'percent', 10));                 // 0.4  -> 0
        $this->assertSame(1000, $d(3333, 'percent', 30), '3333 × 30% = 999.9 -> 1000');
        $this->assertSame(3333, $d(3333, 'percent', 100));
        $this->assertSame(50000, $d(120000, 'fixed', 50000));
        $this->assertSame(120000, $d(120000, 'fixed', 999999), 'a fixed discount never goes above the price');
        $this->assertSame(0, $d(0, 'fixed', 100));
    }

    public function test_the_final_price_is_never_below_zero_and_money_shows_to_the_sen(): void
    {
        $org = $this->umpsa();
        $this->plan(price: 10050);
        $this->promo(['code' => 'HUGE-FIXED', 'type' => 'fixed', 'value' => 99999999]);

        $a = $this->service()->assign($this->admin, $org, SubscriptionPlan::sole()->id, 'huge-fixed');

        $this->assertSame([10050, 10050, 0], [$a->plan_price_cents, $a->discount_cents, $a->final_price_cents]);
        $this->assertSame('RM 0.00', \App\Support\Money::rm($a->final_price_cents));
        $this->assertSame('RM 100.50', \App\Support\Money::rm($a->plan_price_cents));
    }

    public function test_the_preview_shows_the_price_without_using_a_code_or_saving_anything(): void
    {
        $org = $this->pendingUniversity();
        $plan = $this->plan(price: 120050);
        $promo = $this->promo(['value' => 15, 'usage_limit' => 1]);

        $this->as()->postJson(route('platform.organizations.plan-preview', $org), ['plan_id' => $plan->id, 'promo_code' => ' save10 '])->assertOk()
            ->assertJson(['plan_price' => 'RM 1,200.50', 'discount' => 'RM 180.08', 'final_price' => 'RM 1,020.42', 'promo_code' => 'SAVE10', 'promo_label' => '15%']);

        $this->as()->postJson(route('platform.organizations.plan-preview', $org), ['plan_id' => $plan->id])->assertOk()->assertJson(['discount' => 'RM 0.00', 'final_price' => 'RM 1,200.50']);

        $this->assertSame(0, $promo->fresh()->used_count);
        $this->assertSame(0, SubscriptionAssignment::count());
        $this->assertSame(OrgStatus::Pending, $org->fresh()->subscription_status);
        $this->assertCount(0, $this->logs('promo.redeemed'));
    }

    public function test_the_preview_says_why_a_code_cannot_be_used(): void
    {
        $org = $this->umpsa();
        $plan = $this->plan();
        $this->promo(['code' => 'OFF-1', 'disabled' => true]);

        $this->as()->postJson(route('platform.organizations.plan-preview', $org), ['plan_id' => $plan->id, 'promo_code' => 'off-1'])->assertStatus(422)->assertJson(['message' => 'The code OFF-1 is disabled.']);
        $this->as()->postJson(route('platform.organizations.plan-preview', $org), ['plan_id' => $plan->id, 'promo_code' => 'nothere'])->assertStatus(422)->assertJsonFragment(['message' => 'There is no promo code "NOTHERE".']);
    }

    public function test_the_preview_is_for_platform_admins_only(): void
    {
        $org = $this->umpsa();
        $orgAdmin = User::where('email', 'afif@umpsa.edu.my')->firstOrFail();

        $this->flushSession();
        $this->actingAs($orgAdmin)->withSession(['current_org_id' => $org->id, 'current_role' => 'org_admin'])
            ->postJson(route('platform.organizations.plan-preview', $org), ['plan_id' => 1])->assertForbidden();
        $this->flushSession();
        $this->actingAs($orgAdmin)->withSession(['current_org_id' => $org->id, 'current_role' => 'org_admin'])
            ->post(route('platform.organizations.plan', $org), ['plan_id' => 1])->assertForbidden();
    }

    // ---- codes: every rule -----------------------------------------------------------------------------------

    public function test_a_code_is_refused_for_each_reason_and_nothing_is_saved(): void
    {
        $org = $this->umpsa();
        $plan = $this->plan();
        $this->promo(['code' => 'DISABLED-1', 'disabled' => true]);
        $this->promo(['code' => 'EXPIRED-1', 'valid_from' => now()->subDays(10), 'valid_until' => now()->subDay()]);
        $this->promo(['code' => 'FUTURE-1', 'valid_from' => now()->addDay(), 'valid_until' => now()->addDays(9)]);
        $this->promo(['code' => 'USEDUP-1', 'usage_limit' => 2, 'used_count' => 2]);
        $this->promo(['code' => 'CORP-ONLY', 'applies_to' => 'corporate']);

        foreach ([
            'DISABLED-1' => 'disabled', 'EXPIRED-1' => 'expired', 'FUTURE-1' => 'not valid yet', 'USEDUP-1' => 'used up', 'CORP-ONLY' => 'only for corporate', 'MISSING-1' => 'no promo code',
        ] as $code => $why) {
            try {
                $this->service()->assign($this->admin, $org, $plan->id, $code);
                $this->fail("{$code} was accepted");
            } catch (PricingChangeBlocked $e) {
                $this->assertStringContainsString($why, $e->getMessage(), $code);
            }
        }

        $this->assertSame(0, SubscriptionAssignment::count());
        $this->assertNull($org->fresh()->current_assignment_id);
        $this->assertSame(2, PromoCode::where('code', 'USEDUP-1')->value('used_count'));
        $this->assertCount(0, $this->logs('promo.redeemed'));
        $this->assertCount(0, $this->logs('organization.plan_assigned'));
    }

    public function test_a_code_for_the_right_type_works_and_is_counted_once(): void
    {
        $org = $this->umpsa();
        $plan = $this->plan();
        $promo = $this->promo(['applies_to' => 'university', 'usage_limit' => 3]);

        $a = $this->service()->assign($this->admin, $org, $plan->id, 'save10');

        $this->assertSame([12000, 108000, $promo->id], [$a->discount_cents, $a->final_price_cents, $a->promo_code_id]);
        $this->assertSame(1, $promo->fresh()->used_count);
        $this->assertTrue($promo->fresh()->isRedeemed());
    }

    public function test_the_last_use_of_a_code_goes_to_exactly_one_organization(): void
    {
        $plan = $this->plan();
        $promo = $this->promo(['usage_limit' => 1]);
        $second = Organization::where('org_type', 'university')->where('id', '!=', $this->umpsa()->id)->first()
            ?? tap(Organization::create(['name' => 'Second Uni', 'org_type' => 'university', 'access_model' => 'open', 'subscription_status' => 'active']));
        $second->forceFill(['subscription_status' => OrgStatus::Active, 'org_type' => 'university'])->save();

        $ok = 0;
        foreach ([$this->umpsa(), $second] as $org) {
            try {
                $this->service()->assign($this->admin, $org, $plan->id, 'SAVE10');
                $ok++;
            } catch (PricingChangeBlocked) {
                // the second one is refused
            }
        }

        $this->assertSame(1, $ok);
        $this->assertSame(1, $promo->fresh()->used_count);
        $this->assertSame(1, SubscriptionAssignment::whereNotNull('promo_code_id')->count());
        $this->assertCount(1, $this->logs('promo.redeemed'));
    }

    public function test_the_count_goes_up_in_one_conditional_statement_that_cannot_pass_the_limit(): void
    {
        $plan = $this->plan();
        $this->promo(['usage_limit' => 5, 'used_count' => 4]);

        DB::enableQueryLog();
        $this->service()->assign($this->admin, $this->umpsa(), $plan->id, 'SAVE10');
        $update = collect(DB::getQueryLog())->first(fn ($q) => str_contains(self::plainSql($q['query']), 'used_count = used_count + 1'));
        DB::disableQueryLog();

        $this->assertNotNull($update, 'used_count is raised inside the UPDATE, not read and written back');
        $this->assertStringContainsString('used_count < usage_limit', self::plainSql($update['query']));
        $this->assertStringContainsString('disabled = ?', self::plainSql($update['query']));
        $this->assertSame(5, PromoCode::where('code', 'SAVE10')->value('used_count'));

        // A statement that finds no room changes nothing (what a second, simultaneous redemption would hit).
        $rows = PromoCode::query()->where('code', 'SAVE10')->where(fn ($q) => $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'))->update(['used_count' => DB::raw('used_count + 1')]);
        $this->assertSame(0, $rows);
        $this->assertSame(5, PromoCode::where('code', 'SAVE10')->value('used_count'));
    }

    public function test_if_the_code_is_used_up_between_the_check_and_the_update_nothing_is_saved(): void
    {
        $plan = $this->plan();
        $promo = $this->promo(['usage_limit' => 1]);
        $org = $this->umpsa();

        // Another admin takes the last use the instant after our check passes.
        PromoCode::updating(fn () => null);
        DB::listen(function ($query) use ($promo) {
            static $done = false;
            if (! $done && str_starts_with(self::plainSql($query->sql), 'select * from promo_codes where code')) {
                $done = true;
                DB::table('promo_codes')->where('id', $promo->id)->update(['used_count' => 1]);
            }
        });

        try {
            $this->service()->assign($this->admin, $org, $plan->id, 'SAVE10');
            $this->fail('the redemption should have been refused');
        } catch (PricingChangeBlocked $e) {
            $this->assertStringContainsString('just been used up or disabled', $e->getMessage());
        }

        $this->assertSame(0, SubscriptionAssignment::count());
        $this->assertNull($org->fresh()->current_assignment_id);
        $this->assertSame(0, $promo->fresh()->used_count, 'the other admin\'s use happened inside this rolled-back test transaction; ours changed nothing');
        $this->assertCount(0, $this->logs('organization.plan_assigned'));
    }

    public function test_assignment_redemption_and_audit_roll_back_together(): void
    {
        $plan = $this->plan();
        $promo = $this->promo(['usage_limit' => 5]);
        $org = $this->umpsa();

        // The audit write for the redemption fails: the unknown key makes the logger throw after the assignment and the count.
        $keep = config('audit.actions');
        $broken = $keep;
        unset($broken['promo.redeemed']);
        config(['audit.actions' => $broken]);

        try {
            $this->service()->assign($this->admin, $org, $plan->id, 'SAVE10');
            $this->fail('the audit failure should have stopped everything');
        } catch (\InvalidArgumentException) {
            // expected
        }
        config(['audit.actions' => $keep]);

        $this->assertSame(0, SubscriptionAssignment::count());
        $this->assertSame(0, $promo->fresh()->used_count);
        $this->assertNull($org->fresh()->current_assignment_id);
        $this->assertNull($org->fresh()->subscription_ends_at);
        $this->assertCount(0, $this->logs('organization.plan_assigned'));
    }

    // ---- the assignment itself -------------------------------------------------------------------------------

    public function test_an_assignment_records_the_prices_the_dates_and_who_and_audits_it_in_numbers(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-02-28 10:00:00', 'UTC'));
        $org = $this->umpsa();
        $plan = $this->plan(price: 120000);
        $promo = $this->promo();

        $a = $this->service()->assign($this->admin, $org, $plan->id, 'SAVE10');

        $this->assertSame([$org->id, $plan->id, $promo->id, 120000, 12000, 108000, $this->admin->id], [$a->org_id, $a->plan_id, $a->promo_code_id, $a->plan_price_cents, $a->discount_cents, $a->final_price_cents, $a->assigned_by]);
        $this->assertSame(['2027-02-28', '2028-02-28'], [$a->starts_on->toDateString(), $a->ends_on->toDateString()]);

        $org->refresh();
        $this->assertSame($a->id, $org->current_assignment_id);
        $this->assertSame('2028-02-28', $org->subscription_ends_at->toDateString());

        $log = $this->logs('organization.plan_assigned')->sole();
        $this->assertSame(['organization', $org->id, $this->admin->id], [$log->target_type, (int) $log->target_id, (int) $log->actor_user_id]);
        $this->assertSame(['plan_id' => $plan->id, 'assignment_id' => $a->id, 'promo_code_id' => $promo->id, 'plan_price_cents' => 120000, 'discount_cents' => 12000, 'final_price_cents' => 108000, 'starts_on' => '2027-02-28', 'ends_on' => '2028-02-28'], $log->metadata);

        $redeemed = $this->logs('promo.redeemed')->sole();
        $this->assertSame(['promo_code', $promo->id, 'SAVE10'], [$redeemed->target_type, (int) $redeemed->target_id, $redeemed->target_name]);
        $this->assertSame(['promo_id' => $promo->id, 'organization_id' => $org->id, 'assignment_id' => $a->id, 'discount_cents' => 12000, 'used_count' => 1], $redeemed->metadata);
    }

    public function test_a_year_from_the_29th_of_february_stays_inside_february(): void
    {
        Carbon::setTestNow(Carbon::parse('2028-02-29 10:00:00', 'UTC'));
        $a = $this->service()->assign($this->admin, $this->umpsa(), $this->plan()->id, null);

        $this->assertSame('2029-02-28', $a->ends_on->toDateString());
    }

    public function test_changing_the_plan_makes_a_new_assignment_keeps_the_old_one_and_does_not_reuse_the_old_code(): void
    {
        $org = $this->umpsa();
        $small = $this->plan('Small', max: 500, price: 100000);
        $big = $this->plan('Big', min: 501, max: null, price: 300000);
        $promo = $this->promo(['usage_limit' => 10]);

        $first = $this->service()->assign($this->admin, $org, $small->id, 'SAVE10');
        $second = $this->service()->assign($this->admin, $org->fresh(), $big->id, null);

        $this->assertSame(2, SubscriptionAssignment::where('org_id', $org->id)->count());
        $this->assertSame($second->id, $org->fresh()->current_assignment_id);
        $this->assertSame([90000, 100000], [$first->fresh()->final_price_cents, $first->fresh()->plan_price_cents], 'history is untouched');
        $this->assertSame([300000, 0, null], [$second->final_price_cents, $second->discount_cents, $second->promo_code_id], 'no code means no discount');
        $this->assertSame(1, $promo->fresh()->used_count, 'the earlier code is not used again');

        $this->assertCount(1, $this->logs('organization.plan_assigned'));
        $changed = $this->logs('organization.plan_changed')->sole();
        $this->assertSame([$first->id, $second->id], [$changed->metadata['previous_assignment_id'], $changed->metadata['assignment_id']]);
    }

    public function test_changing_the_plan_with_a_code_uses_that_code_once(): void
    {
        $org = $this->umpsa();
        $plan = $this->plan();
        $this->promo(['usage_limit' => 10]);

        $this->service()->assign($this->admin, $org, $plan->id, 'SAVE10');
        $this->service()->assign($this->admin, $org->fresh(), $plan->id, 'SAVE10');

        $this->assertSame(2, PromoCode::where('code', 'SAVE10')->value('used_count'));
        $this->assertCount(2, $this->logs('promo.redeemed'));
    }

    public function test_no_plan_gives_nothing_and_a_code_without_a_plan_is_refused(): void
    {
        $org = $this->umpsa();
        $this->promo();

        $this->assertNull($this->service()->assign($this->admin, $org, null, null));
        $this->assertSame(0, SubscriptionAssignment::count());

        $this->expectException(PricingChangeBlocked::class);
        $this->service()->assign($this->admin, $org, null, 'SAVE10');
    }

    public function test_a_plan_must_be_active_and_of_the_organizations_type(): void
    {
        $org = $this->umpsa();
        $off = $this->plan('Off', active: false);
        $corp = $this->plan('Corp', type: 'corporate', min: 600, max: 700);

        foreach ([$off->id, $corp->id, 99999] as $bad) {
            try {
                $this->service()->assign($this->admin, $org, $bad, null);
                $this->fail("plan {$bad} was accepted");
            } catch (PricingChangeBlocked) {
                // expected
            }
        }

        $this->assertSame(0, SubscriptionAssignment::count());
    }

    public function test_clinics_and_pending_organizations_cannot_be_given_a_plan_by_change_plan(): void
    {
        $plan = $this->plan();
        $clinic = Organization::where('org_type', 'clinic')->where('subscription_status', 'active')->firstOrFail();
        $pending = $this->pendingUniversity();

        foreach ([$clinic, $pending] as $org) {
            $this->as()->post(route('platform.organizations.plan', $org), ['plan_id' => $plan->id])->assertSessionHasErrorsIn('plan', 'plan');
            $this->assertNull($org->fresh()->current_assignment_id);
        }
        $this->assertSame(0, SubscriptionAssignment::count());
        $this->assertFalse($this->service()->offeredPlans($clinic)['applicable']);
    }

    public function test_an_inactive_plan_is_not_offered_but_keeps_its_organizations(): void
    {
        $org = $this->umpsa();
        $plan = $this->plan();
        $this->service()->assign($this->admin, $org, $plan->id, null);

        $this->as()->post(route('platform.pricing.plans.update', $plan), ['org_type' => 'university', 'name' => 'Starter', 'min_members' => 1, 'max_members' => 500, 'price' => '1200.00', 'version' => 1]);   // active unchecked

        $this->assertFalse($plan->fresh()->active);
        $this->assertSame($plan->id, $org->fresh()->currentAssignment->plan_id);
        $this->assertSame([], $this->service()->offeredPlans($org->fresh())['plans']->all());
        $this->as()->get(route('platform.organizations.show', $org))->assertOk()->assertSee('Starter');
    }

    public function test_editing_the_plan_price_later_does_not_change_the_snapshot(): void
    {
        $org = $this->umpsa();
        $plan = $this->plan(price: 120000);
        $this->promo();
        $a = $this->service()->assign($this->admin, $org, $plan->id, 'SAVE10');

        $this->as()->post(route('platform.pricing.plans.update', $plan), ['org_type' => 'university', 'name' => 'Starter', 'min_members' => 1, 'max_members' => 500, 'price' => '9999.00', 'active' => '1', 'version' => 1])->assertSessionHasNoErrors();

        $this->assertSame(999900, $plan->fresh()->price_cents);
        $this->assertSame([120000, 12000, 108000], [$a->fresh()->plan_price_cents, $a->fresh()->discount_cents, $a->fresh()->final_price_cents]);
        $this->assertSame(108000, $this->service()->currentSubscription($org->fresh())['price_cents']);
    }

    // ---- the Approve form ------------------------------------------------------------------------------------

    public function test_approving_without_a_plan_still_approves(): void
    {
        $org = $this->pendingUniversity();
        $this->plan();

        $this->as()->post(route('platform.organizations.approve', $org))->assertSessionHas('status');

        $this->assertSame(OrgStatus::Active, $org->fresh()->subscription_status);
        $this->assertNull($org->fresh()->current_assignment_id);
        $this->assertSame(1, AuditLog::where('action', 'organization.approved')->where('target_id', $org->id)->count());
        $this->assertSame(0, AuditLog::where('action', 'organization.plan_assigned')->where('target_id', $org->id)->count());
    }

    public function test_approving_with_a_plan_and_a_code_does_everything_in_one_go(): void
    {
        $org = $this->pendingUniversity();
        $plan = $this->plan();
        $promo = $this->promo();

        $before = now()->subSecond();
        $this->as()->post(route('platform.organizations.approve', $org), ['plan_id' => $plan->id, 'promo_code' => 'save10'])->assertSessionHasNoErrors()->assertSessionHas('status');

        $org->refresh();
        $this->assertSame(OrgStatus::Active, $org->subscription_status);
        $this->assertSame([108000, $plan->id], [$org->currentAssignment->final_price_cents, $org->currentAssignment->plan_id]);
        $this->assertSame(1, $promo->fresh()->used_count);
        $this->assertSame(['organization.approved', 'organization.plan_assigned', 'promo.redeemed'], AuditLog::whereIn('action', ['organization.approved', 'organization.plan_assigned', 'promo.redeemed'])->where('created_at', '>=', $before)->orderBy('id')->pluck('action')->all());
    }

    public function test_a_bad_code_on_approve_undoes_the_approval_too_and_reopens_the_form(): void
    {
        $org = $this->pendingUniversity();
        $plan = $this->plan();
        $this->promo(['disabled' => true]);

        $this->as()->post(route('platform.organizations.approve', $org), ['plan_id' => $plan->id, 'promo_code' => 'SAVE10'])
            ->assertSessionHasErrorsIn('plan', 'plan');

        $this->assertSame(OrgStatus::Pending, $org->fresh()->subscription_status, 'nothing was saved, not even the approval');
        $this->assertSame(0, AuditLog::where('action', 'organization.approved')->where('target_id', $org->id)->count());
        Notification::assertNothingSent();

        // The form comes back open with the reason.
        $this->as()->get(route('platform.organizations.show', $org))->assertSee('is disabled');
    }

    public function test_the_approval_email_goes_out_only_when_the_whole_thing_succeeded(): void
    {
        $org = $this->pendingUniversity();
        $plan = $this->plan();
        $this->promo();

        $this->as()->post(route('platform.organizations.approve', $org), ['plan_id' => $plan->id, 'promo_code' => 'SAVE10']);

        Notification::assertSentTimes(\App\Notifications\OrganizationApproved::class, 1);
    }

    // ---- Change plan -----------------------------------------------------------------------------------------

    public function test_change_plan_from_the_organization_page(): void
    {
        $org = $this->umpsa();
        $plan = $this->plan();
        $this->promo();

        $this->as()->post(route('platform.organizations.plan', $org), ['plan_id' => $plan->id, 'promo_code' => 'SAVE10'])->assertSessionHasNoErrors()->assertSessionHas('status');

        $this->assertSame(108000, $org->fresh()->currentAssignment->final_price_cents);
        $this->as()->get(route('platform.organizations.show', $org))->assertOk()
            ->assertSee('Starter')->assertSee('RM 1,080.00 a year')->assertSee('discount RM 120.00')->assertSee('SAVE10')->assertSee('Change plan');
    }

    public function test_change_plan_needs_a_plan_and_refuses_a_bad_code_with_nothing_saved(): void
    {
        $org = $this->umpsa();
        $plan = $this->plan();

        $this->as()->post(route('platform.organizations.plan', $org), ['plan_id' => ''])->assertSessionHasErrorsIn('plan', 'plan_id');
        $this->as()->post(route('platform.organizations.plan', $org), ['plan_id' => $plan->id, 'promo_code' => 'NOPE-1'])->assertSessionHasErrorsIn('plan', 'plan');

        $this->assertSame(0, SubscriptionAssignment::count());
    }

    public function test_more_members_than_the_plan_allows_is_only_a_warning(): void
    {
        $org = $this->umpsa();
        $plan = $this->plan(max: 1);
        foreach ([1, 2] as $i) {
            Membership::create(['user_id' => User::factory()->create()->id, 'org_id' => $org->id, 'role' => 'student', 'status' => 'active', 'joined_at' => now()]);
        }
        $this->service()->assign($this->admin, $org, $plan->id, null);
        $members = $this->service()->memberCount($org);
        $this->assertGreaterThan(1, $members);

        $this->as()->get(route('platform.organizations.show', $org))->assertOk()->assertSee('2 members is more than')->assertSee('maximum of 1')->assertSee('Nothing is blocked');
        $this->assertNotEmpty($this->service()->quote($org, $plan->id, null)['warnings']);
        $this->service()->assign($this->admin, $org->fresh(), $plan->id, null);   // not blocked
        $this->assertSame(2, SubscriptionAssignment::count());
    }

    public function test_clinics_see_no_plan_controls(): void
    {
        $clinic = Organization::where('org_type', 'clinic')->where('subscription_status', 'active')->firstOrFail();

        $this->as()->get(route('platform.organizations.show', $clinic))->assertOk()->assertDontSee('Change plan')->assertSee('Clinics have no plan');
    }

    // ---- reading it back -------------------------------------------------------------------------------------

    public function test_the_current_subscription_can_be_read_for_the_organization_admin_later(): void
    {
        $org = $this->umpsa();
        $this->assertNull($this->service()->currentSubscription($org));

        $plan = $this->plan();
        $this->promo();
        $this->service()->assign($this->admin, $org, $plan->id, 'SAVE10');

        $sub = $this->service()->currentSubscription($org->fresh());
        $this->assertSame(['Starter', 120000, 12000, 108000, 'SAVE10', false], [$sub['plan_name'], $sub['list_price_cents'], $sub['discount_cents'], $sub['price_cents'], $sub['promo_code'], $sub['ended']]);

        Carbon::setTestNow(now()->addYears(2));
        $this->assertTrue($this->service()->currentSubscription($org->fresh())['ended']);
    }

    public function test_the_example_data_has_an_organization_on_a_plan_with_a_code(): void
    {
        $this->seed(PricingSeeder::class);

        $org = Organization::whereNotNull('current_assignment_id')->sole();
        $a = $org->currentAssignment;
        $this->assertSame('EX-WELCOME10', $a->promoCode->code);
        $this->assertSame($a->plan_price_cents - $a->discount_cents, $a->final_price_cents);
        $this->assertGreaterThan(0, $a->discount_cents);
        $this->assertSame(35, $a->promoCode->used_count);
    }

    public function test_the_dashboard_counts_subscriptions_ending_soon(): void
    {
        $plan = $this->plan();
        $this->service()->assign($this->admin, $this->umpsa(), $plan->id, null);
        $this->assertSame(0, app(\App\Services\PricingStats::class)->subscriptionsEndingWithin(30));

        Carbon::setTestNow(now()->addDays(340));
        $this->assertSame(1, app(\App\Services\PricingStats::class)->subscriptionsEndingWithin(30));
    }

    public function test_memberships_are_not_touched(): void
    {
        $before = Membership::count();
        $this->service()->assign($this->admin, $this->umpsa(), $this->plan()->id, null);

        $this->assertSame($before, Membership::count());
    }
}
