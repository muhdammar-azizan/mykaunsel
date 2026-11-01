<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Receipt;
use App\Notifications\InvoiceIssuedMail;
use App\Notifications\ReceiptIssuedMail;
use Illuminate\Support\Collection;
use Throwable;

/**
 * The invoice and the receipt by e-mail (docs/PAYMENTS_PLAN.md, "Invoices and receipts"): to EVERY active admin of the organization, with the PDF attached.
 * Asked for inside the transaction that makes the document, but only sent once it has been committed (a rolled back invoice is never mailed), and a failing
 * e-mail never undoes or fails the payment or the invoice: it is only reported.
 */
class BillingMail
{
    public function __construct(private readonly MembershipService $memberships) {}

    public function invoiceIssued(Invoice $invoice): void
    {
        $id = $invoice->getKey();
        $org = $invoice->org_id;

        \Illuminate\Support\Facades\DB::afterCommit(fn () => $this->send($org, new InvoiceIssuedMail($id)));
    }

    public function receiptIssued(Receipt $receipt): void
    {
        $id = $receipt->getKey();
        $org = $receipt->org_id;

        \Illuminate\Support\Facades\DB::afterCommit(fn () => $this->send($org, new ReceiptIssuedMail($id)));
    }

    /** The admins who can be written to: active, with an address they confirmed. @return Collection<int, \App\Models\User> */
    public function recipients(Organization $org): Collection
    {
        return $this->memberships->admins($org)->filter(fn ($admin) => $admin->email_verified_at !== null && $admin->deleted_at === null)->values();
    }

    private function send(int $orgId, object $notification): void
    {
        try {
            $org = Organization::query()->find($orgId);

            if ($org === null) {
                return;
            }

            foreach ($this->recipients($org) as $admin) {
                $admin->notify($notification);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
