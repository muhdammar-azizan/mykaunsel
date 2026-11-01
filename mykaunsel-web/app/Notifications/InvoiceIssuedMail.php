<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Services\InvoicePdf;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Your invoice", to an organization admin, with the invoice as a PDF file (docs/PAYMENTS_PLAN.md, "Invoices and receipts"). Always sent: an invoice
 * is not something a person can switch off. It carries the organization's own figures and nothing about anyone else.
 *
 * Only the invoice's id is queued; the PDF is made when the mail is built, from the invoice as it is stored. Queued and sent after the database commit:
 * a queue worker must be running (`php artisan queue:work`).
 */
class InvoiceIssuedMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private int $invoiceId)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invoice = Invoice::query()->findOrFail($this->invoiceId);
        $pdf = app(InvoicePdf::class);

        $mail = (new MailMessage)
            ->subject("Invoice {$invoice->number} from MyKaunsel")
            ->greeting("Hi {$notifiable->name},")
            ->line("Here is the invoice for {$invoice->description}.")
            ->line("Invoice: {$invoice->number}")
            ->line('Amount: '.Money::rm($invoice->total_cents).' (tax included)')
            ->line('Period: '.$invoice->period_starts_on->format('j M Y').' to '.$invoice->period_ends_on->format('j M Y'));

        if ($invoice->status === Invoice::PAID) {
            $mail->line('Nothing is left to pay on this invoice.');
        } else {
            $mail->line('Please pay by '.$invoice->due_on->format('j M Y').'. You can pay online from the Subscription page of your MyKaunsel account, or by bank transfer with the details in the attached invoice.')
                ->action('Open the invoice', route('org.subscription.invoice', $invoice->public_id));
        }

        return $mail
            ->line('The invoice is attached as a PDF. When it is paid we send you a receipt.')
            ->attachData($pdf->render($invoice), $pdf->filename($invoice), ['mime' => 'application/pdf']);
    }
}
