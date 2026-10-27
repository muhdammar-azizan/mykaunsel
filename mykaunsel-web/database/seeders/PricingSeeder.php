<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * EXAMPLE plans for development (the names say so). The prices are made up. There is no payment on MyKaunsel. Local and testing only.
 *
 * University: three active plans that together cover every size (the last has no upper limit, shown as "5,000+").
 * Corporate: one active plan with no upper limit and one inactive plan (to try "inactive plans are not offered").
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
    }
}
