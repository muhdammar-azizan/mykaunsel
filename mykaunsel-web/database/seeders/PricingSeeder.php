<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\PromoCode;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionAssignmentService;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * EXAMPLE plans for development (the names say so). The prices are made up. There is no payment on MyKaunsel. Local and testing only.
 *
 * University: three active plans that together cover every size (the last has no upper limit, shown as "5,000+").
 * Corporate: one active plan with no upper limit and one inactive plan (to try "inactive plans are not offered").
 * Promo codes (every code starts with EX-): one in each state, Active, Scheduled, Expired, Used up and Disabled.
 */
class PricingSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('PricingSeeder holds example prices, so it only runs in the local and testing environments.');
        }

        $plans = [
            ['university', 'University Starter (example)', 1, 500, 120000, true],
            ['university', 'University Growth (example)', 501, 4999, 480000, true],
            ['university', 'University Campus (example)', 5000, null, 960000, true],
            ['corporate', 'Corporate Business (example)', 1, null, 360000, true],
            ['corporate', 'Corporate Legacy Team (example)', 1, 100, 180000, false],
        ];

        foreach ($plans as $order => [$type, $name, $min, $max, $price, $active]) {
            SubscriptionPlan::create([
                'org_type' => $type,
                'name' => $name,
                'min_members' => $min,
                'max_members' => $max,
                'price_cents' => $price,
                'active' => $active,
                'display_order' => $order,
                'version' => 1,
            ]);
        }

        $this->promoCodes();
        $this->assignExample();
    }

    /** One organization already on a plan with a promo code, to see that editing the plan's price later does not change what it was given. */
    private function assignExample(): void
    {
        $admin = User::where('email', 'admin@mykaunsel.com')->first();
        $org = Organization::where('org_type', 'university')->where('subscription_status', 'active')->orderBy('id')->first();
        $plan = SubscriptionPlan::where('name', 'University Growth (example)')->first();

        if ($admin && $org && $plan && $org->current_assignment_id === null) {
            app(SubscriptionAssignmentService::class)->assign($admin, $org, $plan->id, 'EX-WELCOME10');
        }
    }

    private function promoCodes(): void
    {
        $codes = [
            // code, type, value, valid from, valid until, limit, used, applies to, disabled
            ['EX-WELCOME10', 'percent', 10, now()->subMonth(), now()->addMonths(3), 100, 34, 'all', false],
            ['EX-SPRING27', 'percent', 15, now()->addMonths(2), now()->addMonths(5), null, 0, 'university', false],
            ['EX-RAYA26', 'percent', 20, now()->subMonths(6), now()->subMonths(4), 200, 41, 'all', false],
            ['EX-FIRST50', 'fixed', 50000, now()->subMonths(2), now()->addMonths(2), 50, 50, 'corporate', false],
            ['EX-PAUSED20', 'percent', 20, now()->subMonth(), now()->addMonths(2), null, 3, 'all', true],
        ];

        foreach ($codes as [$code, $type, $value, $from, $until, $limit, $used, $appliesTo, $disabled]) {
            PromoCode::create([
                'code' => $code,
                'type' => $type,
                'value' => $value,
                'valid_from' => $from->copy()->startOfMinute(),
                'valid_until' => $until->copy()->startOfMinute()->setSecond(59),
                'usage_limit' => $limit,
                'used_count' => $used,
                'applies_to' => $appliesTo,
                'disabled' => $disabled,
                'version' => 1,
            ]);
        }
    }
}
