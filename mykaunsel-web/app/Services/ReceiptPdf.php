<?php

namespace App\Services;

use App\Models\Receipt;
use Illuminate\Http\Response;

/** The PDF file of a receipt: made on demand from the receipt's own snapshot and never stored. The drawing itself is PdfRenderer's. */
class ReceiptPdf
{
    public function __construct(private readonly PdfRenderer $pdf) {}

    public function render(Receipt $receipt): string
    {
        return $this->pdf->render(view('pdf.receipt', ['receipt' => $receipt->loadMissing('invoice')])->render());
    }

    /** "RCT-2026-0001.pdf". */
    public function filename(Receipt $receipt): string
    {
        return PdfRenderer::filename($receipt->number);
    }

    public function download(Receipt $receipt): Response
    {
        return $this->pdf->download($this->render($receipt), $this->filename($receipt));
    }
}
