<?php

namespace App\Http\Controllers\Organization;

use App\Exceptions\InvoiceNotOpen;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\OrgSubscription;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** An organization admin's Subscription page: plan, invoices and receipts, paying, and asking to renew. Always the admin's CURRENT organization. */
class OrgSubscriptionController extends Controller
{
    public function index(Request $request, OrgSubscription $subscription)
    {
        $organization = $this->organization($request);

        return view('organizations.dashboard.subscription', $subscription->for($organization) + ['organization' => $organization]);
    }

    public function invoice(Request $request, string $publicId, OrgSubscription $subscription)
    {
        try {
            $invoice = $subscription->invoice($this->organization($request), $publicId);
        } catch (ModelNotFoundException) {
            abort(404);
        }

        return view('organizations.dashboard.invoice', [
            'invoice' => $invoice,
            'attempts' => $subscription->attempts($invoice),
            'timezone' => $request->user()->display_timezone,
        ]);
    }

    public function pay(Request $request, string $publicId, OrgSubscription $subscription): RedirectResponse
    {
        try {
            return redirect()->away($subscription->startPayment($this->organization($request), $publicId, $request->user()));
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (InvoiceNotOpen $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }
    }

    public function requestRenewal(Request $request, OrgSubscription $subscription): RedirectResponse
    {
        try {
            $subscription->requestRenewal($this->organization($request), $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['renewal' => $e->getMessage()]);
        }

        return back()->with('status', 'Thank you. MyKaunsel has been told and will be in touch about your renewal.');
    }

    private function organization(Request $request): Organization
    {
        return Organization::query()->findOrFail($request->session()->get('current_org_id'));
    }
}
