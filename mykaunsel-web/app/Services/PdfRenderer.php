<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;

/**
 * The one place that runs dompdf (invoices and receipts). It runs with no remote files, no embedded PHP and no JavaScript (a document carries text typed by
 * people, such as an organization's name), and only ever reads from `resources/views/pdf`. A PDF is made on demand from a snapshot and never stored.
 */
class PdfRenderer
{
    /** The PDF as bytes. */
    public function render(string $html): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->set('chroot', [resource_path('views/pdf')]);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('tempDir', storage_path('framework/cache'));

        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return (string) $pdf->output();
    }

    /** A download: not cached by the browser or a shared cache. */
    public function download(string $bytes, string $filename): Response
    {
        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /** "INV-2026-0001.pdf": a document number is made of letters, digits and dashes only. */
    public static function filename(string $number): string
    {
        return preg_replace('/[^A-Za-z0-9\-]/', '-', $number).'.pdf';
    }
}
