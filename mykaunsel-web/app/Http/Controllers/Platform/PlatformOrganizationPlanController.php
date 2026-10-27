<?php

namespace App\Http\Controllers\Platform;

use App\Enums\OrgType;
use App\Exceptions\PricingChangeBlocked;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\SubscriptionAssignmentService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Organizations > detail: give an active organization a plan, or change it ("Change plan"), and the price preview used by that form
 * and by the Approve form. There is no payment: this records a plan, a price and an end date.
 */
class PlatformOrganizationPlanController extends Controller
{
    public function change(Request $request, Organization $organization, SubscriptionAssignmentService $plans): RedirectResponse
    {
        abort_if($organization->org_type === OrgType::Platform, 404);

        $data = $request->validateWithBag('plan', [
            'plan_id' => ['required', 'integer'],
            'promo_code' => ['nullable', 'string', 'max:32'],
        ], ['plan_id.required' => 'Choose a plan.']);

        try {
            $assignment = $plans->assign($request->user(), $organization, (int) $data['plan_id'], $data['promo_code'] ?? null);
        } catch (PricingChangeBlocked $e) {
            return back()->withInput()->withErrors(['plan' => implode(' ', $e->reasons)], 'plan');
        }

        return redirect()->route('platform.organizations.show', $organization)
            ->with('status', "{$organization->name} is now on {$assignment->plan->name} until {$assignment->ends_on->format('j M Y')} (recorded at ".Money::rm($assignment->final_price_cents).' a year; no payment is processed).');
    }

    /** What would be recorded, without saving anything and without using up a code. */
    public function preview(Request $request, Organization $organization, SubscriptionAssignmentService $plans): JsonResponse
    {
        abort_if($organization->org_type === OrgType::Platform, 404);

        $data = $request->validate([
            'plan_id' => ['nullable', 'integer'],
            'promo_code' => ['nullable', 'string', 'max:32'],
        ]);

        // A pending organization is previewed as if it were already approved: the price does not depend on that.
        $organization->forceFill(['subscription_status' => \App\Enums\OrgStatus::Active]);

        try {
            $q = $plans->quote($organization, isset($data['plan_id']) ? (int) $data['plan_id'] : null, $data['promo_code'] ?? null);
        } catch (PricingChangeBlocked $e) {
            return response()->json(['message' => implode(' ', $e->reasons)], 422);
        }

        return response()->json([
            'plan_price' => Money::rm($q['plan_price_cents']),
            'discount' => Money::rm($q['discount_cents']),
            'final_price' => Money::rm($q['final_price_cents']),
            'promo_code' => $q['promo']?->code,
            'promo_label' => $q['promo']?->discountLabel(),
            'starts_on' => $q['starts_on']->format('j M Y'),
            'ends_on' => $q['ends_on']->format('j M Y'),
            'warnings' => $q['warnings'],
        ]);
    }
}
