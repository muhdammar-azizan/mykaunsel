<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Http\Response;

/**
 * The PDF file of an invoice (docs/PAYMENTS_PLAN.md, "Invoices and receipts"): made on demand from the invoice's own snapshot, never stored, so it can
 * never differ from the invoice. The drawing itself is PdfRenderer's.
 */
class InvoicePdf
{
    public function __construct(private readonly PdfRenderer $pdf) {}

    /** The PDF as bytes. */
    public function render(Invoice $invoice): string
    {
        return $this->pdf->render(view('pdf.invoice', ['invoice' => $invoice->loadMissing('receipt')])->render());
    }

    /** "INV-2026-0001.pdf". */
    public function filename(Invoice $invoice): string
    {
        return PdfRenderer::filename($invoice->number);
    }

    public function download(Invoice $invoice): Response
    {
        return $this->pdf->download($this->render($invoice), $this->filename($invoice));
    }
}
