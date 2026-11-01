<?php

namespace App\Services;

use App\Enums\MembershipStatus;
use App\Exceptions\InvoiceNotOpen;
use App\Facades\Audit;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Receipt;
use App\Models\User;
use App\Services\Payments\InvoicePayments;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * An organization admin's Subscription page (docs/PRICING.md, docs/PAYMENTS_PLAN.md): the plan and its end date, the invoices, paying an unpaid
 * invoice online (a test page for now), and asking MyKaunsel to renew.
 *
 * - Only the admin's OWN organization's invoices; another organization's invoice is a 404. A voided invoice is not shown.
 * - Paying opens a try on the payment provider (InvoicePayments) and sends the admin to its page; the invoice is marked paid only by the provider's
 *   signed result, never by this page.
 * - Asking to renew sets a flag on the organization (once until MyKaunsel gives a new plan) and tells the Platform Admins. Nothing is charged.
 */
class OrgSubscription
{
    public function __construct(private readonly SubscriptionAssignmentService $plans, private readonly InvoicePayments $payments) {}

    /** @return array<string, mixed> */
    public function for(Organization $org): array
    {
        $current = $this->plans->currentSubscription($org);
        $members = Membership::query()->where('org_id', $org->getKey())->whereIn('role', array_map(fn ($r) => $r->value, MembershipService::CLIENT_ROLES))
            ->where('status', MembershipStatus::Active->value)->count();
        $max = $org->currentAssignment?->plan?->max_members;

        return [
            'applicable' => $this->plans->canHavePlan($org),
            'current' => $current,
            'state' => $org->subscriptionState(),
            'ends_on' => $org->subscription_ends_at,
            'members' => $members,
            'max_members' => $max,
            'over_limit' => $max !== null && $members > $max,
            'renewal_requested_at' => $org->renewal_requested_at,
            'invoices' => Invoice::query()->where('org_id', $org->getKey())->where('status', '!=', Invoice::VOID)->with('receipt')->orderByDesc('issued_on')->orderByDesc('id')->limit(100)->get(),
        ];
    }

    /** One of this organization's invoices (not voided), with its tries to pay. @throws ModelNotFoundException */
    public function invoice(Organization $org, string $publicId): Invoice
    {
        return Invoice::query()->where('org_id', $org->getKey())->where('status', '!=', Invoice::VOID)->where('public_id', strtolower($publicId))->firstOrFail();
    }

    /** One of this organization's receipts. @throws ModelNotFoundException */
    public function receipt(Organization $org, string $publicId): Receipt
    {
        return Receipt::query()->where('org_id', $org->getKey())->where('public_id', strtolower($publicId))->with('invoice')->firstOrFail();
    }

    /**
     * Opens a payment try for an unpaid invoice and returns the address to pay at.
     *
     * @throws ModelNotFoundException
     * @throws InvoiceNotOpen
     */
    public function startPayment(Organization $org, string $publicId, User $admin): string
    {
        $invoice = $this->invoice($org, $publicId);
        $payment = $this->payments->start($invoice, $admin);

        Audit::log('invoice.payment_started', $invoice, ['invoice_id' => $invoice->getKey(), 'org_id' => $invoice->org_id, 'payment_id' => $payment->getKey()], $admin);

        return $this->payments->gateway()->payUrl($payment);
    }

    /** The latest tries on an invoice, newest first. @return \Illuminate\Support\Collection<int, InvoicePayment> */
    public function attempts(Invoice $invoice)
    {
        return $invoice->payments()->orderByDesc('id')->limit(10)->get();
    }

    /** @throws RuntimeException in plain words */
    public function requestRenewal(Organization $org, User $admin): void
    {
        if (! $this->plans->canHavePlan($org)) {
            throw new RuntimeException('This kind of organization has no subscription to renew.');
        }

        DB::transaction(function () use ($org, $admin) {
            $locked = Organization::query()->lockForUpdate()->findOrFail($org->getKey());

            if ($locked->renewal_requested_at !== null) {
                throw new RuntimeException('You already asked MyKaunsel to renew. They will be in touch.');
            }

            $locked->forceFill(['renewal_requested_at' => now(), 'renewal_requested_by' => $admin->getKey()])->save();
            Audit::log('subscription.renewal_requested', $locked, ['organization_id' => $locked->getKey()], $admin);

            DB::afterCommit(fn () => app(NotificationCenter::class)->publish('subscription.renewal_requested', $locked, $locked->name, (string) $locked->renewal_requested_at->getTimestamp()));
        });
    }
}
