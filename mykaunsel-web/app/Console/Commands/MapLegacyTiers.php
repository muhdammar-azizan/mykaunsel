<?php

namespace App\Console\Commands;

use App\Facades\Audit;
use App\Models\Organization;
use App\Models\SubscriptionAssignment;
use App\Models\SubscriptionPlan;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-off helper for databases that still have text in the old organizations.subscription_tier column, before the migration
 * that removes that column can run (it refuses while any organization still has such text).
 *
 * An organization is mapped when a plan of ITS type has exactly that name (capital letters do not matter). It then gets a normal
 * assignment with the plan's price as it is now, no promo code and no discount, starting when it was approved (or created) and
 * lasting until its old end date, or one year. The text is cleared. Organizations with no matching plan are listed and left
 * alone: create the plan first, or clear the text yourself. Each mapping is audited as a system action.
 *
 *   php artisan pricing:map-legacy-tiers --dry-run
 *   php artisan pricing:map-legacy-tiers
 */
class MapLegacyTiers extends Command
{
    protected $signature = 'pricing:map-legacy-tiers {--dry-run : Show what would be mapped, change nothing}';

    protected $description = 'Give organizations that still have an old tier text the plan with the same name';

    public function handle(): int
    {
        if (! Schema::hasColumn('organizations', 'subscription_tier')) {
            $this->info('The old subscription_tier column is already gone. Nothing to map.');

            return self::SUCCESS;
        }

        $legacy = DB::table('organizations')
            ->whereNotNull('subscription_tier')
            ->whereRaw("trim(subscription_tier) <> ''")
            ->whereNull('current_assignment_id')
            ->orderBy('id')
            ->get(['id', 'subscription_tier']);

        if ($legacy->isEmpty()) {
            $this->info('No organization has an old tier text. Nothing to map.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $mapped = 0;
        $left = [];

        foreach ($legacy as $row) {
            $org = Organization::query()->find($row->id);
            $plan = SubscriptionPlan::query()
                ->where('org_type', $org->org_type->value)
                ->whereRaw('lower(name) = ?', [mb_strtolower(trim($row->subscription_tier))])
                ->first();

            if ($plan === null) {
                $left[] = "#{$row->id} \"{$row->subscription_tier}\"";

                continue;
            }

            $this->line(($dryRun ? 'Would map' : 'Mapping')." organization #{$row->id} to plan \"{$plan->name}\".");

            if ($dryRun) {
                $mapped++;

                continue;
            }

            Audit::asSystem(function () use ($org, $plan, $row) {
                DB::transaction(function () use ($org, $plan, $row) {
                    $start = ($org->reviewed_at ?? $org->created_at)->toDateString();
                    $end = $org->subscription_ends_at?->toDateString() ?? Carbon::parse($start)->addYear()->toDateString();

                    $assignment = SubscriptionAssignment::create([
                        'org_id' => $org->id,
                        'plan_id' => $plan->id,
                        'plan_price_cents' => $plan->price_cents,
                        'discount_cents' => 0,
                        'final_price_cents' => $plan->price_cents,
                        'starts_on' => $start,
                        'ends_on' => $end,
                        'assigned_by' => null,
                    ]);

                    DB::table('organizations')->where('id', $org->id)->update([
                        'current_assignment_id' => $assignment->id,
                        'subscription_tier' => null,
                        'subscription_ends_at' => $org->subscription_ends_at ?? $end.' 00:00:00',
                    ]);

                    Audit::log('organization.plan_assigned', $org, [
                        'plan_id' => $plan->id,
                        'assignment_id' => $assignment->id,
                        'plan_price_cents' => $plan->price_cents,
                        'final_price_cents' => $plan->price_cents,
                        'source' => 'legacy_tier_mapping',
                    ], 'system');
                });
            });

            $mapped++;
        }

        $this->info(($dryRun ? 'Would map ' : 'Mapped ').$mapped.' organization(s).');

        if ($left !== []) {
            $this->warn(count($left).' organization(s) have no plan with that name and were left alone: '.implode(', ', $left).'. Create the plan first, or clear the text yourself.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
