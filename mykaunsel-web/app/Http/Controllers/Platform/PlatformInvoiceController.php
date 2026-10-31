<?php

namespace App\Http\Controllers\Platform;

use App\Exceptions\InvoiceNotOpen;
use App\Facades\Audit;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Organization;
use App\Services\InvoiceService;
use App\Services\Payments\InvoicePayments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Platform Admin → Invoices: every invoice of the yearly subscriptions, one invoice as a document, marking it paid by hand (a bank transfer
 * that has arrived) and voiding one made by mistake. The rules are in InvoiceService. Nothing here moves money.
 */
class PlatformInvoiceController extends Controller
{
    private const PER_PAGE = 20;

    private const TABS = ['open' => 'Open', 'overdue' => 'Overdue', 'paid' => 'Paid', 'void' => 'Void', 'all' => 'All'];

    public function index(Request $request): View
    {
        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? (string) $request->query('tab') : 'open';
        $search = trim((string) $request->query('q'));

        $counts = [
            'open' => Invoice::query()->open()->count(),
            'overdue' => Invoice::query()->overdue()->count(),
            'paid' => Invoice::query()->where('status', Invoice::PAID)->count(),
            'void' => Invoice::query()->where('status', Invoice::VOID)->count(),
            'all' => Invoice::query()->count(),
        ];

        $invoices = Invoice::query()
            ->with('organization:id,name')
            ->when($search !== '', fn ($query) => $query->where(fn ($w) => $w->where('number', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%')
                ->orWhereHas('organization', fn ($o) => $o->where('name', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%'))))
            ->when($tab === 'open', fn ($query) => $query->open())
            ->when($tab === 'overdue', fn ($query) => $query->overdue())
            ->when($tab === 'paid', fn ($query) => $query->where('status', Invoice::PAID))
            ->when($tab === 'void', fn ($query) => $query->where('status', Invoice::VOID))
            ->orderByDesc('issued_on')->orderByDesc('id')
            ->paginate(self::PER_PAGE)->withQueryString();

        if ($invoices->isEmpty() && $invoices->currentPage() > 1) {
            return redirect()->route('platform.invoices', array_filter(['tab' => $tab === 'open' ? null : $tab, 'q' => $search ?: null, 'page' => $invoices->lastPage()]));
        }

        return view('platform.invoices.index', [
            'tab' => $tab,
            'tabs' => collect(self::TABS)->map(fn ($label, $key) => [
                'key' => $key,
                'label' => $label,
                'count' => $counts[$key],
                'url' => route('platform.invoices', array_filter(['tab' => $key === 'open' ? null : $key, 'q' => $search ?: null])),
            ])->values()->all(),
            'search' => $search,
            'invoices' => $invoices,
        ]);
    }

    public function show(Request $request, Invoice $invoice): View
    {
        $invoice->load('organization:id,name');

        return view('platform.invoices.show', [
            'invoice' => $invoice,
            'attempts' => $invoice->payments()->orderByDesc('id')->limit(20)->get(),
            'timezone' => $request->user()->display_timezone,
            'history' => AuditLog::query()->where('target_type', 'invoice')->where('target_id', $invoice->getKey())->orderByDesc('id')->limit(20)->get(),
        ]);
    }

    /** Opens a payment page for an unpaid invoice (until the organization's own Pay button exists, an admin can open one to try the flow). */
    public function payLink(Request $request, Invoice $invoice, InvoicePayments $payments): RedirectResponse
    {
        try {
            $payment = $payments->start($invoice, $request->user());
        } catch (InvoiceNotOpen $e) {
            return back()->with('error', $e->getMessage().' Nothing was changed.');
        }

        Audit::log('invoice.payment_started', $invoice, ['invoice_id' => $invoice->getKey(), 'org_id' => $invoice->org_id, 'payment_id' => $payment->getKey()], $request->user());

        return redirect($payments->gateway()->payUrl($payment));
    }

    public function markPaid(Request $request, Invoice $invoice, InvoiceService $invoices): RedirectResponse
    {
        $today = Invoice::today()->toDateString();
        $data = $request->validateWithBag('paid', [
            'reference' => ['required', 'string', 'max:100'],
            'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$today],
        ], [
            'reference.required' => 'Write the bank reference of the transfer, so the payment can be found later.',
            'paid_on.before_or_equal' => 'The day the money arrived can not be in the future.',
        ]);

        try {
            $invoices->markPaid($invoice, $request->user(), $data['reference'], Carbon::parse($data['paid_on'], (string) config('platform-settings.public_timezone', 'Asia/Kuala_Lumpur')));
        } catch (InvoiceNotOpen $e) {
            return back()->with('error', $e->getMessage().' Nothing was changed.');
        }

        return back()->with('status', "Invoice {$invoice->number} is marked paid.");
    }

    public function void(Request $request, Invoice $invoice, InvoiceService $invoices): RedirectResponse
    {
        $data = $request->validateWithBag('void', ['reason' => ['required', 'string', 'max:200']], ['reason.required' => 'Say why the invoice is cancelled.']);

        try {
            $invoices->void($invoice, $request->user(), $data['reason']);
        } catch (InvoiceNotOpen $e) {
            return back()->with('error', $e->getMessage().' Nothing was changed.');
        }

        return back()->with('status', "Invoice {$invoice->number} is void.");
    }

    /** For an organization that was given its plan before invoices existed (or whose invoice is missing): the invoice of its CURRENT plan. */
    public function issue(Request $request, Organization $organization, InvoiceService $invoices): RedirectResponse
    {
        $assignment = $organization->currentAssignment;

        if ($assignment === null) {
            return back()->with('error', 'This organization has no plan yet, so there is nothing to invoice.');
        }

        $invoice = $invoices->issueForAssignment($assignment, $request->user());

        return redirect()->route('platform.invoices.show', $invoice)->with('status', "Invoice {$invoice->number} is ready.");
    }
}
