<?php

namespace App\Services;

use App\Enums\OrgType;
use App\Exceptions\PricingChangeBlocked;
use App\Facades\Audit;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Yearly subscription plans for universities, corporates and clinics. There is no payment: a plan is a record of a price.
 *
 * - Plans are never deleted, only made inactive. An inactive plan is not offered when assigning, but organizations on it keep it.
 * - ACTIVE plans of the same organization type must not overlap (checked while the plans of that type are locked, so two
 *   admins saving at the same moment cannot both pass). Gaps are allowed and are reported as a warning.
 * - The organization type of a plan cannot change once any organization has been given it.
 * - Two admins editing the same plan: an edit says which version it saw and is refused (with a clear message) when the
 *   plan has changed since.
 * - Every change and its audit entry share one transaction.
 */
class SubscriptionPlanService
{
    public const TYPES = [OrgType::University, OrgType::Corporate, OrgType::Clinic];

    /**
     * @param  array{org_type: string, name: string, min_members: int, max_members: ?int, price_cents: int, active: bool}  $data
     * @return array{plan: SubscriptionPlan, warnings: list<string>}
     *
     * @throws PricingChangeBlocked
     */
    public function create(User $actor, array $data): array
    {
        $this->assertShape($data);

        $plan = DB::transaction(function () use ($actor, $data) {
            $siblings = $this->lockType($data['org_type']);

            if ($data['active']) {
                $this->assertNoOverlap($siblings, $data['min_members'], $data['max_members'], null);
            }

            $plan = SubscriptionPlan::create([
                'org_type' => $data['org_type'],
                'name' => trim($data['name']),
                'min_members' => $data['min_members'],
                'max_members' => $data['max_members'],
                'price_cents' => $data['price_cents'],
                'active' => $data['active'],
                'version' => 1,
            ]);

            Audit::log('plan.created', $plan, [
                'plan_id' => $plan->id,
                'org_type' => $data['org_type'],
                'min_members' => $plan->min_members,
                'max_members' => $plan->max_members,
                'price_cents' => $plan->price_cents,
                'active' => $plan->active,
            ], $actor);

            return $plan;
        });

        return ['plan' => $plan, 'warnings' => $this->gapWarnings([$plan->org_type->value])];
    }

    /**
     * @param  int  $seenVersion  the version the admin was looking at
     * @param  array{org_type: string, name: string, min_members: int, max_members: ?int, price_cents: int, active: bool}  $data
     * @return array{plan: SubscriptionPlan, warnings: list<string>, changed: bool}
     *
     * @throws PricingChangeBlocked
     */
    public function update(User $actor, SubscriptionPlan $plan, int $seenVersion, array $data): array
    {
        $this->assertShape($data);

        $types = DB::transaction(function () use ($actor, $plan, $seenVersion, $data) {
            $current = SubscriptionPlan::query()->lockForUpdate()->findOrFail($plan->getKey());

            if ($current->version !== $seenVersion) {
                throw $this->conflict();
            }

            $oldType = $current->org_type->value;
            $siblings = collect([$oldType, $data['org_type']])->unique()->flatMap(fn ($type) => $this->lockType($type))->unique('id');

            if ($oldType !== $data['org_type'] && $current->assignments()->exists()) {
                throw new PricingChangeBlocked(['The organization type of this plan cannot be changed because organizations have already been given it. Create a new plan for the other type instead.']);
            }

            $new = [
                'org_type' => $data['org_type'],
                'name' => trim($data['name']),
                'min_members' => $data['min_members'],
                'max_members' => $data['max_members'],
                'price_cents' => $data['price_cents'],
                'active' => $data['active'],
            ];

            $changes = [];
            foreach (['org_type', 'min_members', 'max_members', 'price_cents'] as $field) {
                $old = $field === 'org_type' ? $oldType : $current->{$field};
                if ($old !== $new[$field]) {
                    $changes[$field] = ['old' => $old, 'new' => $new[$field]];
                }
            }
            $renamed = $current->name !== $new['name'];
            $activeChanged = $current->active !== $new['active'];

            if ($changes === [] && ! $renamed && ! $activeChanged) {
                return null; // nothing to save: no new version, no audit entry
            }

            if ($new['active']) {
                $this->assertNoOverlap($siblings->where('org_type', OrgType::from($new['org_type'])), $new['min_members'], $new['max_members'], $current->getKey());
            }

            $affected = SubscriptionPlan::query()
                ->whereKey($current->getKey())
                ->where('version', $seenVersion)
                ->update($new + ['version' => DB::raw('version + 1'), 'updated_at' => now()]);

            if ($affected !== 1) {
                throw $this->conflict();
            }

            $current->refresh();

            if ($changes !== [] || $renamed) {
                Audit::log('plan.updated', $current, array_filter([
                    'plan_id' => $current->id,
                    'changes' => $changes ?: null,
                    'name_changed' => $renamed ?: null,
                ], fn ($v) => $v !== null), $actor);
            }

            if ($activeChanged) {
                Audit::log($new['active'] ? 'plan.activated' : 'plan.deactivated', $current, ['plan_id' => $current->id], $actor);
            }

            return array_values(array_unique([$oldType, $new['org_type']]));
        });

        $fresh = $plan->fresh();

        return ['plan' => $fresh, 'warnings' => $types === null ? [] : $this->gapWarnings($types), 'changed' => $types !== null];
    }

    // ---- coverage --------------------------------------------------------------------------------------------

    /**
     * The member counts no ACTIVE plan of this type covers, as [from, to] pairs; `to` is null for "and above".
     *
     * @return list<array{0: int, 1: ?int}>
     */
    public function coverageGaps(string $orgType): array
    {
        $plans = SubscriptionPlan::query()->where('org_type', $orgType)->where('active', true)->orderBy('min_members')->orderBy('id')->get(['min_members', 'max_members']);

        $gaps = [];
        $next = 1; // the lowest count not covered so far; null once everything above is covered

        foreach ($plans as $plan) {
            if ($next === null) {
                break;
            }

            if ($plan->min_members > $next) {
                $gaps[] = [$next, $plan->min_members - 1];
            }

            $next = $plan->max_members === null ? null : max($next, $plan->max_members + 1);
        }

        if ($next !== null) {
            $gaps[] = [$next, null];
        }

        return $gaps;
    }

    /** @return list<string> */
    public function gapWarnings(array $orgTypes): array
    {
        $warnings = [];

        foreach (array_unique($orgTypes) as $type) {
            $gaps = $this->coverageGaps($type);

            if ($gaps === []) {
                continue;
            }

            $label = ucfirst($type);
            $parts = array_map(fn ($gap) => SubscriptionPlan::range($gap[0], $gap[1]), $gaps);

            $warnings[] = count($parts) === 1 && $gaps[0] === [1, null]
                ? "No active {$label} plan covers any size yet, so no {$label} organization can be given a plan."
                : "Active {$label} plans do not cover: ".implode(', ', $parts).' members. Organizations of that size cannot be given a plan until one covers them.';
        }

        return $warnings;
    }

    /** How many organizations have each plan as their CURRENT plan, and which plans were ever given to anyone. */
    public function usage(): array
    {
        $current = DB::table('organizations')
            ->join('subscription_assignments', 'subscription_assignments.id', '=', 'organizations.current_assignment_id')
            ->select('subscription_assignments.plan_id', DB::raw('count(*) as n'))
            ->groupBy('subscription_assignments.plan_id')
            ->pluck('n', 'plan_id')
            ->map(fn ($n) => (int) $n)
            ->all();

        $ever = DB::table('subscription_assignments')->distinct()->pluck('plan_id')->map(fn ($id) => (int) $id)->all();

        return ['current' => $current, 'ever' => $ever];
    }

    // ---- internals -------------------------------------------------------------------------------------------

    /** @return Collection<int, SubscriptionPlan> every plan of the type, locked until the transaction ends */
    private function lockType(string $orgType): Collection
    {
        return SubscriptionPlan::query()->where('org_type', $orgType)->orderBy('id')->lockForUpdate()->get();
    }

    private function assertNoOverlap(Collection $siblings, int $min, ?int $max, ?int $ignoreId): void
    {
        $clash = $siblings
            ->filter(fn (SubscriptionPlan $other) => $other->active && $other->getKey() !== $ignoreId)
            ->first(fn (SubscriptionPlan $other) => $min <= ($other->max_members ?? PHP_INT_MAX) && $other->min_members <= ($max ?? PHP_INT_MAX));

        if ($clash) {
            throw new PricingChangeBlocked(["This range ({$this->label($min, $max)} members) overlaps the active plan \"{$clash->name}\" ({$clash->rangeLabel()}). Active plans of the same type must not overlap: change the range, or make one of them inactive."]);
        }
    }

    private function label(int $min, ?int $max): string
    {
        return SubscriptionPlan::range($min, $max);
    }

    /** The checks that do not depend on other plans. Also done here (not only in the form) so no caller can skip them. */
    private function assertShape(array $data): void
    {
        $errors = [];
        $cap = (int) config('pricing.plan.max_members');
        $maxPrice = (int) config('pricing.plan.max_price_cents');

        if (! in_array($data['org_type'] ?? null, array_map(fn (OrgType $t) => $t->value, self::TYPES), true)) {
            $errors[] = 'Plans are only for universities, corporates and clinics.';
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            $errors[] = 'Give the plan a name of up to 100 characters.';
        }

        $min = $data['min_members'] ?? null;
        $max = $data['max_members'] ?? null;
        if (! is_int($min) || $min < 1 || $min > $cap) {
            $errors[] = "The minimum members must be a whole number from 1 to {$cap}.";
        } elseif ($max !== null && (! is_int($max) || $max < $min || $max > $cap)) {
            $errors[] = 'The maximum members must be a whole number, not less than the minimum, or empty for no upper limit.';
        }

        $price = $data['price_cents'] ?? null;
        if (! is_int($price) || $price <= 0 || $price > $maxPrice) {
            $errors[] = 'The yearly price must be more than RM 0.00 and not more than RM '.number_format($maxPrice / 100, 2).'.';
        }

        if ($errors !== []) {
            throw new PricingChangeBlocked($errors);
        }
    }

    private function conflict(): PricingChangeBlocked
    {
        return new PricingChangeBlocked(['Someone else changed this plan while you were editing it. Nothing was saved. Reload the page to see the latest version, then make your change again.'], conflict: true);
    }
}
