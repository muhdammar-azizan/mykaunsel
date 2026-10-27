<?php

namespace Tests\Feature\Platform;

use App\Enums\OrgType;
use App\Enums\PromoCodeStatus;
use App\Exceptions\PricingChangeBlocked;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\PromoCode;
use App\Models\SubscriptionAssignment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\PricingStats;
use App\Services\PromoCodeService;
use Database\Seeders\PricingSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Counselor\CounselorTestCase;

/**
 * Pricing & Promotions, step 2: the Promo Codes tab. There is no payment: a code only lowers the recorded price, and only an
 * admin applies it (there is no public place to type a code).
 */
class PricingPromoCodesTest extends CounselorTestCase
{
    private const TZ = 'Asia/Kuala_Lumpur';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSamples();
    }

    /** A code stored directly, valid from yesterday for 30 days unless said otherwise. */
    private function promo(array $over = []): PromoCode
    {
        return PromoCode::create($over + [
            'code' => 'WELCOME10', 'type' => 'percent', 'value' => 10,
            'valid_from' => now()->subDay()->startOfMinute(), 'valid_until' => now()->addDays(30)->startOfMinute()->setSecond(59),
            'usage_limit' => null, 'used_count' => 0, 'applies_to' => 'all', 'disabled' => false, 'version' => 1,
        ]);
    }

    private function local(int $days): string
    {
        return now(self::TZ)->addDays($days)->format('Y-m-d\TH:i');
    }

    private function form(array $over = []): array
    {
        return array_replace([
            'code' => 'welcome-10', 'type' => 'percent', 'value' => '10', 'valid_from' => $this->local(-1), 'valid_until' => $this->local(30), 'usage_limit' => '', 'applies_to' => 'all',
        ], $over);
    }

    private function add(array $over = [])
    {
        return $this->as()->post(route('platform.pricing.promos.store'), $this->form($over));
    }

    private function edit(PromoCode $promo, array $over = [], ?int $version = null)
    {
        $base = [
            'code' => $promo->code, 'type' => $promo->type, 'value' => $promo->type === 'percent' ? (string) $promo->value : \App\Support\Money::plain($promo->value),
            'valid_from' => $promo->valid_from->copy()->timezone(self::TZ)->format('Y-m-d\TH:i'), 'valid_until' => $promo->valid_until->copy()->timezone(self::TZ)->format('Y-m-d\TH:i'),
            'usage_limit' => $promo->usage_limit ?? '', 'applies_to' => $promo->applies_to, 'version' => $version ?? $promo->version,
        ];

        return $this->as()->post(route('platform.pricing.promos.update', $promo), array_replace($base, $over));
    }

    private function logs(string $action)
    {
        return AuditLog::where('action', $action)->get();
    }

    private function umpsa(): Organization
    {
        return Organization::where('name', 'like', 'Universiti Malaysia Pahang%')->firstOrFail();
    }

    /** Links a code to an assignment, as a real redemption will. */
    private function redeemOnce(PromoCode $promo): SubscriptionAssignment
    {
        $plan = SubscriptionPlan::create(['org_type' => 'university', 'name' => 'P', 'min_members' => 1, 'max_members' => null, 'price_cents' => 100000, 'active' => true, 'version' => 1]);

        return SubscriptionAssignment::create([
            'org_id' => $this->umpsa()->id, 'plan_id' => $plan->id, 'promo_code_id' => $promo->id, 'plan_price_cents' => 100000, 'discount_cents' => 10000,
            'final_price_cents' => 90000, 'starts_on' => today(), 'ends_on' => today()->addYear(),
        ]);
    }

    private function noThrottle(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class); // many tries in a row; the limit has its own test
    }

    // ---- who may use it --------------------------------------------------------------------------------------

    public function test_only_platform_admins_can_see_or_change_promo_codes(): void
    {
        $this->noThrottle();
        $orgAdmin = User::where('email', 'afif@umpsa.edu.my')->firstOrFail();
        $promo = $this->promo();
        $session = ['current_org_id' => $this->umpsa()->id, 'current_role' => 'org_admin'];

        foreach ([
            fn ($t) => $t->get(route('platform.pricing', ['tab' => 'promos'])),
            fn ($t) => $t->post(route('platform.pricing.promos.store'), $this->form(['code' => 'SNEAKY'])),
            fn ($t) => $t->post(route('platform.pricing.promos.update', $promo), $this->form(['version' => 1])),
            fn ($t) => $t->post(route('platform.pricing.promos.disable', $promo)),
            fn ($t) => $t->post(route('platform.pricing.promos.enable', $promo)),
            fn ($t) => $t->delete(route('platform.pricing.promos.destroy', $promo)),
        ] as $call) {
            $this->flushSession();
            $call($this->actingAs($orgAdmin)->withSession($session))->assertForbidden();
        }

        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->get(route('platform.pricing', ['tab' => 'promos']))->assertRedirect(route('login'));
        $this->delete(route('platform.pricing.promos.destroy', $promo))->assertRedirect(route('login'));

        $this->assertSame(['WELCOME10'], PromoCode::pluck('code')->all());
        $this->assertFalse($promo->fresh()->disabled);
        $this->assertSame(0, AuditLog::where('category', 'pricing')->count());
    }

    public function test_no_route_to_check_or_use_a_promo_code_is_public(): void
    {
        $found = 0;

        foreach (Route::getRoutes() as $route) {
            if (! preg_match('/promo|coupon|voucher/i', $route->uri())) {
                continue;
            }

            $found++;
            $middleware = $route->gatherMiddleware();
            $this->assertStringStartsWith('platform/', $route->uri(), $route->uri());
            $this->assertContains('auth', $middleware, $route->uri());
            $this->assertContains('role:platform_admin', $middleware, $route->uri());
        }

        $this->assertSame(5, $found, 'add, edit, disable, enable and delete: nothing else mentions a code');
    }

    // ---- the status (worked out in one place) ----------------------------------------------------------------

    public function test_the_status_follows_the_dates_to_the_second(): void
    {
        $promo = $this->promo([
            'valid_from' => Carbon::parse('2027-03-01 00:00:00', 'UTC'), 'valid_until' => Carbon::parse('2027-03-31 23:59:59', 'UTC'),
        ]);
        $at = fn (string $moment) => $promo->status(Carbon::parse($moment, 'UTC'));

        $this->assertSame(PromoCodeStatus::Scheduled, $at('2027-02-28 23:59:59'));
        $this->assertSame(PromoCodeStatus::Active, $at('2027-03-01 00:00:00'));
        $this->assertSame(PromoCodeStatus::Active, $at('2027-03-15 12:00:00'));
        $this->assertSame(PromoCodeStatus::Active, $at('2027-03-31 23:59:59'));
        $this->assertSame(PromoCodeStatus::Expired, $at('2027-04-01 00:00:00'));
    }

    public function test_used_up_disabled_and_the_order_between_the_states(): void
    {
        $inside = Carbon::parse('2027-03-15 12:00:00', 'UTC');
        $dates = ['valid_from' => Carbon::parse('2027-03-01', 'UTC'), 'valid_until' => Carbon::parse('2027-03-31 23:59:59', 'UTC')];

        $limited = $this->promo($dates + ['usage_limit' => 5, 'used_count' => 4]);
        $this->assertSame(PromoCodeStatus::Active, $limited->status($inside));
        $limited->used_count = 5;
        $this->assertSame(PromoCodeStatus::UsedUp, $limited->status($inside));
        $limited->used_count = 9;
        $this->assertSame(PromoCodeStatus::UsedUp, $limited->status($inside));

        $this->assertSame(PromoCodeStatus::Active, $this->promo($dates + ['code' => 'NOLIMIT', 'usage_limit' => null, 'used_count' => 9999])->status($inside), 'unlimited is never used up');

        // Disabled beats everything; used up beats expired and scheduled.
        $limited->disabled = true;
        $this->assertSame(PromoCodeStatus::Disabled, $limited->status($inside));
        $this->assertSame(PromoCodeStatus::Disabled, $limited->status(Carbon::parse('2030-01-01', 'UTC')));
        $limited->disabled = false;
        $this->assertSame(PromoCodeStatus::UsedUp, $limited->status(Carbon::parse('2030-01-01', 'UTC')), 'used up and expired: used up');
        $this->assertSame(PromoCodeStatus::UsedUp, $limited->status(Carbon::parse('2020-01-01', 'UTC')), 'used up and not started: used up');
    }

    public function test_the_list_the_filter_the_counts_and_the_dashboard_number_agree(): void
    {
        $this->seed(PricingSeeder::class);

        $page = $this->as()->get(route('platform.pricing', ['tab' => 'promos']))->assertOk();
        foreach (['EX-WELCOME10', 'EX-SPRING27', 'EX-RAYA26', 'EX-FIRST50', 'EX-PAUSED20'] as $code) {
            $page->assertSee($code);
        }

        $expected = ['active' => 'EX-WELCOME10', 'scheduled' => 'EX-SPRING27', 'expired' => 'EX-RAYA26', 'used_up' => 'EX-FIRST50', 'disabled' => 'EX-PAUSED20'];
        foreach ($expected as $status => $code) {
            $this->assertSame(PromoCodeStatus::from($status), PromoCode::where('code', $code)->sole()->status(), $code);

            $filtered = $this->as()->get(route('platform.pricing', ['tab' => 'promos', 'status' => $status]))->assertOk();
            $filtered->assertSee($code);
            foreach (array_diff($expected, [$status => $code]) as $other) {
                $filtered->assertDontSee($other);
            }
        }

        $this->as()->get(route('platform.pricing', ['tab' => 'promos', 'status' => 'nonsense']))->assertOk()->assertSee('EX-WELCOME10')->assertSee('EX-PAUSED20');

        $this->assertSame(1, app(PricingStats::class)->activePromoCodes());
        $this->as()->get(route('platform.pricing'))->assertSeeInOrder(['Promo Codes', '1']);
    }

    // ---- what the list shows ---------------------------------------------------------------------------------

    public function test_the_list_shows_discount_usage_and_validity_in_the_admins_timezone(): void
    {
        $this->promo([
            'code' => 'RM-OFF', 'type' => 'fixed', 'value' => 50000, 'usage_limit' => 100, 'used_count' => 34,
            'valid_from' => Carbon::parse('2027-01-01 00:00:00', 'UTC'), 'valid_until' => Carbon::parse('2027-01-31 15:59:59', 'UTC'),
        ]);
        $this->promo(['code' => 'PERCENT20', 'value' => 20, 'usage_limit' => null, 'used_count' => 0, 'applies_to' => 'corporate']);

        $this->as()->get(route('platform.pricing', ['tab' => 'promos']))->assertOk()
            ->assertSee('RM 500.00')->assertSee('34 / 100')->assertSee('20%')->assertSee('0 / Unlimited')->assertSee('Corporate only')
            ->assertSee('1 Jan 2027, 8:00 AM')->assertSee('31 Jan 2027, 11:59 PM');
    }

    public function test_the_empty_states(): void
    {
        $this->as()->get(route('platform.pricing', ['tab' => 'promos']))->assertOk()->assertSee('No promo codes yet');

        $this->promo();
        $this->as()->get(route('platform.pricing', ['tab' => 'promos', 'status' => 'expired']))->assertOk()->assertSee('No expired codes.')->assertDontSee('WELCOME10');
    }

    public function test_the_list_is_paged(): void
    {
        foreach (range(1, 20) as $i) {
            $this->promo(['code' => sprintf('CODE-%02d', $i)]);
        }

        $first = $this->as()->get(route('platform.pricing', ['tab' => 'promos']))->assertOk();
        $first->assertSee('CODE-20')->assertSee('CODE-06')->assertDontSee('CODE-05');

        $this->as()->get(route('platform.pricing', ['tab' => 'promos', 'page' => 2]))->assertOk()->assertSee('CODE-05')->assertSee('CODE-01')->assertDontSee('CODE-06');
    }

    public function test_each_tab_shows_only_its_own_content_and_an_unknown_tab_is_the_plans_tab(): void
    {
        $this->promo();

        $this->as()->get(route('platform.pricing', ['tab' => 'promos']))->assertSee('WELCOME10')->assertDontSee('Commission on sessions');
        $this->as()->get(route('platform.pricing'))->assertSee('Commission on sessions')->assertDontSee('WELCOME10');
        $this->as()->get(route('platform.pricing', ['tab' => 'whatever']))->assertSee('Commission on sessions');
    }

    public function test_every_row_can_be_copied_and_the_page_still_says_nothing_is_charged(): void
    {
        $this->promo();

        $this->as()->get(route('platform.pricing', ['tab' => 'promos']))->assertOk()
            ->assertSee('aria-label="Copy the code WELCOME10"', false)->assertSee('navigator.clipboard', false)
            ->assertSee('No payment is processed')->assertSee('no place for the public to type a code');
    }

    // ---- adding a code ---------------------------------------------------------------------------------------

    public function test_a_code_is_capitalised_stored_with_utc_times_and_audited_with_ids_and_numbers(): void
    {
        $this->add(['code' => 'welcome-10', 'valid_from' => '2027-03-01T09:00', 'valid_until' => '2027-03-31T23:59', 'usage_limit' => '100', 'applies_to' => 'university'])
            ->assertSessionHasNoErrors()->assertSessionHas('status');

        $promo = PromoCode::sole();
        $this->assertSame(['WELCOME-10', 'percent', 10, 100, 'university', 0, false, 1, $this->admin->id], [$promo->code, $promo->type, $promo->value, $promo->usage_limit, $promo->applies_to, $promo->used_count, $promo->disabled, $promo->version, $promo->created_by]);
        $this->assertSame('2027-03-01 01:00:00', $promo->valid_from->format('Y-m-d H:i:s'), 'typed in Kuala Lumpur time, stored in UTC');
        $this->assertSame('2027-03-31 15:59:59', $promo->valid_until->format('Y-m-d H:i:s'), 'valid to the end of the last minute');

        $log = $this->logs('promo.created')->sole();
        $this->assertSame(['promo_code', $promo->id, 'WELCOME-10', $this->admin->id], [$log->target_type, (int) $log->target_id, $log->target_name, (int) $log->actor_user_id]);
        $this->assertEqualsCanonicalizing(['promo_id', 'type', 'value', 'valid_from', 'valid_until', 'usage_limit', 'applies_to'], array_keys($log->metadata));
        $this->assertSame(['promo_id' => $promo->id, 'type' => 'percent', 'value' => 10, 'valid_from' => '2027-03-01T01:00:00+00:00', 'valid_until' => '2027-03-31T15:59:59+00:00', 'usage_limit' => 100, 'applies_to' => 'university'], $log->metadata);
    }

    public function test_the_code_must_be_4_to_32_letters_numbers_or_hyphens(): void
    {
        $this->noThrottle();

        foreach (['abc', str_repeat('A', 33), 'ab cd', 'ab_cd', 'ab@cd', 'ab.cd', 'códe1', '<b>abc</b>', '', 'AB/CD'] as $bad) {
            $this->add(['code' => $bad])->assertSessionHasErrorsIn('promo-new', 'code');
        }
        $this->assertSame(0, PromoCode::count());

        foreach (['abcd', 'A-1-B', 'z9z9', str_repeat('x', 32), '----', 'SPRING-2027'] as $good) {
            $this->add(['code' => $good])->assertSessionHasNoErrors();
        }
        $this->assertSame(6, PromoCode::count());
        $this->assertSame('ABCD', PromoCode::orderBy('id')->first()->code);
    }

    public function test_codes_are_unique_without_regard_to_capitals(): void
    {
        $this->noThrottle();
        $this->promo(['code' => 'WELCOME10']);

        foreach (['welcome10', 'Welcome10', 'WELCOME10', ' welcome10 '] as $same) {
            $this->add(['code' => $same])->assertSessionHasErrorsIn('promo-new', 'form');
            $this->assertStringContainsString('already exists', session('errors')->getBag('promo-new')->first('form'));
        }
        $this->assertSame(1, PromoCode::count());
        $this->assertCount(0, $this->logs('promo.created'));

        // The database says no as well.
        $this->expectException(QueryException::class);
        DB::table('promo_codes')->insert(['code' => 'WELCOME10', 'type' => 'percent', 'value' => 5, 'valid_from' => now(), 'valid_until' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_the_discount_is_checked_by_type(): void
    {
        $this->noThrottle();

        foreach (['0', '101', '1.5', 'abc', '', '-5', '10%', '1000'] as $bad) {
            $this->add(['code' => 'PCT-BAD', 'type' => 'percent', 'value' => $bad])->assertSessionHasErrorsIn('promo-new', 'value');
        }
        foreach (['0', '0.00', '-1', '1.234', 'abc', '', 'RM 5', '1000000.01'] as $bad) {
            $this->add(['code' => 'FIX-BAD', 'type' => 'fixed', 'value' => $bad])->assertSessionHasErrorsIn('promo-new', 'value');
        }
        $this->add(['type' => 'weird'])->assertSessionHasErrorsIn('promo-new', 'type');
        $this->assertSame(0, PromoCode::count());

        $this->add(['code' => 'PCT-1', 'type' => 'percent', 'value' => '1'])->assertSessionHasNoErrors();
        $this->add(['code' => 'PCT-100', 'type' => 'percent', 'value' => '100'])->assertSessionHasNoErrors();
        $this->add(['code' => 'FIX-A', 'type' => 'fixed', 'value' => '100'])->assertSessionHasNoErrors();
        $this->add(['code' => 'FIX-B', 'type' => 'fixed', 'value' => '1,000.50'])->assertSessionHasNoErrors();
        $this->add(['code' => 'FIX-C', 'type' => 'fixed', 'value' => '0.01'])->assertSessionHasNoErrors();

        $this->assertSame([1, 100, 10000, 100050, 1], PromoCode::orderBy('id')->pluck('value')->all());
        $this->assertSame('RM 1,000.50', PromoCode::where('code', 'FIX-B')->sole()->discountLabel());
    }

    public function test_the_dates_must_make_sense(): void
    {
        $this->noThrottle();

        foreach ([
            ['valid_from' => '2027-03-01T09:00', 'valid_until' => '2027-03-01T09:00'],
            ['valid_from' => '2027-03-02T09:00', 'valid_until' => '2027-03-01T09:00'],
            ['valid_from' => '', 'valid_until' => '2027-03-01T09:00'],
            ['valid_from' => '2027-03-01T09:00', 'valid_until' => ''],
            ['valid_from' => 'tomorrow', 'valid_until' => '2027-03-01T09:00'],
            ['valid_from' => '2027-03-01', 'valid_until' => '2027-03-05'],
        ] as $bad) {
            $this->add($bad)->assertSessionHasErrorsIn('promo-new');
        }

        $this->assertSame(0, PromoCode::count());
        $this->add(['valid_from' => '2027-03-01T09:00', 'valid_until' => '2027-03-01T09:01'])->assertSessionHasNoErrors();
    }

    public function test_the_usage_limit_and_who_it_applies_to_are_checked(): void
    {
        $this->noThrottle();

        foreach (['0', '-1', '1.5', 'x', '10000001'] as $bad) {
            $this->add(['usage_limit' => $bad])->assertSessionHasErrorsIn('promo-new', 'usage_limit');
        }
        foreach (['clinic', 'everyone', ''] as $bad) {
            $this->add(['applies_to' => $bad])->assertSessionHasErrorsIn('promo-new', 'applies_to');
        }
        $this->assertSame(0, PromoCode::count());

        $this->add(['code' => 'UNLIM', 'usage_limit' => ''])->assertSessionHasNoErrors();
        $this->add(['code' => 'ONE-1', 'usage_limit' => '1', 'applies_to' => 'corporate'])->assertSessionHasNoErrors();
        $this->assertNull(PromoCode::where('code', 'UNLIM')->sole()->usage_limit);
        $this->assertSame(1, PromoCode::where('code', 'ONE-1')->sole()->usage_limit);
    }

    public function test_the_service_repeats_the_shape_rules_so_no_caller_can_skip_the_form(): void
    {
        $service = app(PromoCodeService::class);
        $good = ['code' => 'GOOD-1', 'type' => 'percent', 'value' => 10, 'valid_from' => now(), 'valid_until' => now()->addDay(), 'usage_limit' => null, 'applies_to' => 'all'];

        foreach ([
            ['code' => 'abc'], ['code' => 'bad code'], ['type' => 'bogus'], ['value' => 0], ['value' => 101], ['type' => 'fixed', 'value' => 0],
            ['type' => 'fixed', 'value' => 100_000_001], ['valid_until' => now()->subDay()], ['usage_limit' => 0], ['applies_to' => 'clinic'],
        ] as $bad) {
            try {
                $service->create($this->admin, array_replace($good, $bad));
                $this->fail('accepted '.json_encode(array_keys($bad)));
            } catch (PricingChangeBlocked) {
                // expected
            }
        }

        $this->assertSame(0, PromoCode::count());
        $this->assertSame('GOOD-1', $service->create($this->admin, $good)->code);
    }

    // ---- editing a code that was never used ------------------------------------------------------------------

    public function test_an_unused_code_can_be_changed_in_every_field_and_the_change_is_audited(): void
    {
        $promo = $this->promo(['code' => 'OLDCODE', 'value' => 10]);

        $this->edit($promo, ['code' => 'newcode', 'type' => 'fixed', 'value' => '25.50', 'usage_limit' => '20', 'applies_to' => 'university', 'valid_from' => '2027-06-01T00:00', 'valid_until' => '2027-06-30T23:59'])
            ->assertSessionHasNoErrors()->assertSessionHas('status');

        $promo->refresh();
        $this->assertSame(['NEWCODE', 'fixed', 2550, 20, 'university', 2], [$promo->code, $promo->type, $promo->value, $promo->usage_limit, $promo->applies_to, $promo->version]);

        $log = $this->logs('promo.updated')->sole();
        $this->assertTrue($log->metadata['code_changed']);
        $this->assertSame(['old' => 'percent', 'new' => 'fixed'], $log->metadata['changes']['type']);
        $this->assertSame(['old' => 10, 'new' => 2550], $log->metadata['changes']['value']);
        $this->assertSame(['old' => null, 'new' => 20], $log->metadata['changes']['usage_limit']);
        $this->assertSame('2027-06-30T15:59:59+00:00', $log->metadata['changes']['valid_until']['new']);
        $this->assertStringNotContainsString('OLDCODE', json_encode($log->metadata), 'the code itself is the target, not metadata');
    }

    public function test_renaming_into_another_codes_name_is_refused(): void
    {
        $this->promo(['code' => 'TAKEN']);
        $mine = $this->promo(['code' => 'MINE1']);

        $this->edit($mine, ['code' => 'taken'])->assertSessionHasErrorsIn("promo-{$mine->id}", 'form');

        $this->assertSame('MINE1', $mine->fresh()->code);
        $this->assertCount(0, $this->logs('promo.updated'));
    }

    public function test_saving_without_a_change_makes_no_new_version_and_no_entry(): void
    {
        $promo = $this->promo();
        $before = AuditLog::count();

        $this->edit($promo)->assertSessionHas('status', 'Nothing was changed.');

        $this->assertSame([1, $before], [$promo->fresh()->version, AuditLog::count()]);
    }

    public function test_a_usage_limit_can_never_be_lower_than_what_is_already_used(): void
    {
        $promo = $this->promo(['usage_limit' => 10, 'used_count' => 4]);

        $this->edit($promo, ['usage_limit' => '3'])->assertSessionHasErrorsIn("promo-{$promo->id}", 'form');
        $this->assertSame(10, $promo->fresh()->usage_limit);

        $this->edit($promo, ['usage_limit' => '4'])->assertSessionHasNoErrors();
        $this->assertSame(PromoCodeStatus::UsedUp, $promo->fresh()->status());
    }

    public function test_two_admins_cannot_silently_overwrite_each_other(): void
    {
        $promo = $this->promo(['usage_limit' => 10]);
        $seen = $promo->version;

        $this->edit($promo, ['usage_limit' => '20'], $seen)->assertSessionHasNoErrors();
        $this->edit($promo, ['usage_limit' => '30'], $seen)->assertSessionHasErrorsIn("promo-{$promo->id}", 'form');

        $this->assertSame(20, $promo->fresh()->usage_limit, 'the first save stands');
        $this->assertStringContainsString('Someone else changed this code', session('errors')->getBag("promo-{$promo->id}")->first('form'));
        $this->assertCount(1, $this->logs('promo.updated'));
    }

    public function test_the_save_itself_is_conditional_on_the_version(): void
    {
        $promo = $this->promo(['usage_limit' => 10]);
        $data = ['code' => 'WELCOME10', 'type' => 'percent', 'value' => 10, 'valid_from' => $promo->valid_from, 'valid_until' => $promo->valid_until, 'usage_limit' => 20, 'applies_to' => 'all'];

        DB::enableQueryLog();
        app(PromoCodeService::class)->update($this->admin, $promo, 1, $data);
        $update = collect(DB::getQueryLog())->first(fn ($q) => str_starts_with($q['query'], 'update "promo_codes" set "code"'));
        DB::disableQueryLog();

        $this->assertNotNull($update);
        $this->assertMatchesRegularExpression('/where "promo_codes"\."id" = \? and "version" = \?/', $update['query']);
    }

    public function test_a_missing_or_bad_version_is_refused(): void
    {
        $this->noThrottle();
        $promo = $this->promo();

        foreach ([null, 0, 'x'] as $bad) {
            $this->as()->post(route('platform.pricing.promos.update', $promo), $this->form(['code' => 'RENAMED', 'version' => $bad]))
                ->assertSessionHasErrorsIn("promo-{$promo->id}", 'version');
        }

        $this->assertSame('WELCOME10', $promo->fresh()->code);
    }

    // ---- editing a code that was used ------------------------------------------------------------------------

    public function test_a_used_code_keeps_its_code_type_value_start_and_audience(): void
    {
        $this->noThrottle();
        $promo = $this->promo(['used_count' => 3, 'usage_limit' => 10]);

        foreach ([
            ['code' => 'RENAMED'], ['type' => 'fixed', 'value' => '10'], ['value' => '15'], ['applies_to' => 'university'],
            ['valid_from' => now(self::TZ)->subDays(5)->format('Y-m-d\TH:i')],
        ] as $locked) {
            $this->edit($promo, $locked)->assertSessionHasErrorsIn("promo-{$promo->id}", 'form');
            $this->assertStringContainsString('already been redeemed', session('errors')->getBag("promo-{$promo->id}")->first('form'), json_encode($locked));
        }

        $fresh = $promo->fresh();
        $this->assertSame(['WELCOME10', 'percent', 10, 'all', 1], [$fresh->code, $fresh->type, $fresh->value, $fresh->applies_to, $fresh->version]);
        $this->assertCount(0, $this->logs('promo.updated'));
    }

    public function test_a_used_code_can_still_have_a_new_end_and_limit(): void
    {
        $promo = $this->promo(['used_count' => 3, 'usage_limit' => 10]);

        $this->edit($promo, ['valid_until' => '2030-12-31T23:59', 'usage_limit' => '50'])->assertSessionHasNoErrors()->assertSessionHas('status');

        $fresh = $promo->fresh();
        $this->assertSame([50, 2, '2030-12-31 15:59:59'], [$fresh->usage_limit, $fresh->version, $fresh->valid_until->format('Y-m-d H:i:s')]);
        $this->assertEqualsCanonicalizing(['usage_limit', 'valid_until'], array_keys($this->logs('promo.updated')->sole()->metadata['changes']));
    }

    public function test_a_code_linked_to_an_assignment_counts_as_used_even_with_a_zero_counter(): void
    {
        $promo = $this->promo(['used_count' => 0]);
        $this->redeemOnce($promo);

        $this->assertTrue($promo->fresh()->isRedeemed());
        $this->edit($promo, ['value' => '15'])->assertSessionHasErrorsIn("promo-{$promo->id}", 'form');
        $this->assertSame(10, $promo->fresh()->value);
    }

    public function test_the_edit_form_shows_what_is_locked(): void
    {
        $this->promo(['code' => 'USED-ONE', 'used_count' => 2]);
        $this->promo(['code' => 'FRESH-ONE', 'used_count' => 0]);

        $page = $this->as()->get(route('platform.pricing', ['tab' => 'promos']))->assertOk();

        $this->assertSame(1, substr_count($page->getContent(), 'This code has already been used, so its code, type, value, start date'), 'only for the code that was used');
        $page->assertSee('Used 2 times so far');
    }

    // ---- disable and enable ----------------------------------------------------------------------------------

    public function test_disabling_and_enabling_change_the_status_and_are_audited_once_each(): void
    {
        $promo = $this->promo();

        $this->as()->post(route('platform.pricing.promos.disable', $promo))->assertSessionHas('status');
        $this->assertSame([PromoCodeStatus::Disabled, 2], [$promo->fresh()->status(), $promo->fresh()->version]);

        $this->as()->post(route('platform.pricing.promos.disable', $promo))->assertSessionHas('status', 'The code WELCOME10 was already disabled.');
        $this->assertCount(1, $this->logs('promo.disabled'));
        $this->assertSame(2, $promo->fresh()->version);

        $this->as()->post(route('platform.pricing.promos.enable', $promo))->assertSessionHas('status');
        $this->as()->post(route('platform.pricing.promos.enable', $promo))->assertSessionHas('status', 'The code WELCOME10 was already enabled.');
        $this->assertSame(PromoCodeStatus::Active, $promo->fresh()->status());
        $this->assertCount(1, $this->logs('promo.enabled'));
        $this->assertSame(['promo_id' => $promo->id], $this->logs('promo.enabled')->sole()->metadata);
    }

    public function test_a_used_code_can_be_disabled_and_enabled(): void
    {
        $promo = $this->promo(['used_count' => 5]);

        $this->as()->post(route('platform.pricing.promos.disable', $promo))->assertSessionHas('status');
        $this->assertSame(PromoCodeStatus::Disabled, $promo->fresh()->status());
        $this->assertSame(5, $promo->fresh()->used_count, 'its history stays');

        $this->as()->post(route('platform.pricing.promos.enable', $promo));
        $this->assertSame(PromoCodeStatus::Active, $promo->fresh()->status());
    }

    public function test_editing_a_code_never_changes_whether_it_is_disabled(): void
    {
        $promo = $this->promo(['disabled' => true]);

        $this->edit($promo, ['usage_limit' => '5'])->assertSessionHasNoErrors();

        $this->assertTrue($promo->fresh()->disabled);
    }

    // ---- delete ----------------------------------------------------------------------------------------------

    public function test_a_code_that_was_never_used_can_be_deleted_and_the_deletion_is_audited_by_name(): void
    {
        $promo = $this->promo(['code' => 'DELETE-ME']);

        $this->as()->delete(route('platform.pricing.promos.destroy', $promo))->assertSessionHas('status');

        $this->assertNull(PromoCode::find($promo->id));
        $log = $this->logs('promo.deleted')->sole();
        $this->assertSame(['promo_code', $promo->id, 'DELETE-ME', ['promo_id' => $promo->id]], [$log->target_type, (int) $log->target_id, $log->target_name, $log->metadata]);
    }

    public function test_a_used_code_cannot_be_deleted_only_disabled(): void
    {
        $used = $this->promo(['code' => 'USED-1', 'used_count' => 1]);
        $linked = $this->promo(['code' => 'LINKED-1', 'used_count' => 0]);
        $this->redeemOnce($linked);

        foreach ([$used, $linked] as $promo) {
            $this->as()->delete(route('platform.pricing.promos.destroy', $promo))->assertRedirect()->assertSessionHas('error');
            $this->assertNotNull($promo->fresh());
        }
        $this->assertCount(0, $this->logs('promo.deleted'));

        // Even a direct delete is stopped by the database for a code an assignment points to.
        $this->expectException(QueryException::class);
        DB::table('promo_codes')->where('id', $linked->id)->delete();
    }

    public function test_deleting_a_missing_code_is_not_found_and_the_page_offers_delete_only_for_unused_codes(): void
    {
        $this->as()->delete('/platform/pricing/promos/99999')->assertNotFound();

        $unused = $this->promo(['code' => 'UNUSED-1']);
        $used = $this->promo(['code' => 'USED-2', 'used_count' => 2]);

        $page = $this->as()->get(route('platform.pricing', ['tab' => 'promos']))->assertOk();
        $page->assertSee('aria-label="Delete UNUSED-1"', false)->assertDontSee('aria-label="Delete USED-2"', false);
        $page->assertSee('Delete UNUSED-1?')->assertDontSee('Delete USED-2?');   // the confirmation exists only for the unused one
    }

    // ---- the limits on the actions ---------------------------------------------------------------------------

    public function test_adding_codes_is_rate_limited(): void
    {
        $last = null;
        foreach (range(1, 21) as $i) {
            $last = $this->add(['code' => '']);   // fails validation each time, but still counts
        }

        $last->assertStatus(429);
    }

    public function test_a_code_is_a_plain_label_that_cannot_carry_markup(): void
    {
        $this->add(['code' => '<script>alert(1)</script>'])->assertSessionHasErrorsIn('promo-new', 'code');
        $this->promo(['code' => 'SAFE-1']);

        $this->as()->get(route('platform.pricing', ['tab' => 'promos']))->assertOk()->assertDontSee('<script>alert(1)</script>', false);
        $this->assertTrue((new PromoCode(['applies_to' => 'university']))->appliesToType(OrgType::University));
        $this->assertFalse((new PromoCode(['applies_to' => 'university']))->appliesToType(OrgType::Corporate));
        $this->assertTrue((new PromoCode(['applies_to' => 'all']))->appliesToType(OrgType::Corporate));
    }

    public function test_the_example_codes_are_marked_and_cover_every_state(): void
    {
        $this->seed(PricingSeeder::class);

        $codes = PromoCode::all();
        $this->assertCount(5, $codes);
        $this->assertTrue($codes->every(fn (PromoCode $c) => str_starts_with($c->code, 'EX-')));
        $this->assertEqualsCanonicalizing(array_map(fn ($c) => $c->value, PromoCodeStatus::cases()), $codes->map(fn (PromoCode $c) => $c->status()->value)->all());
    }
}
