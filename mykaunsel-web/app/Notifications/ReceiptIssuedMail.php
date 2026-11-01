<?php

namespace App\Notifications;

use App\Models\Receipt;
use App\Services\ReceiptPdf;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Your receipt", to an organization admin, with the receipt as a PDF file. Always sent. Only the receipt's id is queued; the PDF is made when the mail
 * is built, from the receipt as it is stored. Queued and sent after the database commit: a queue worker must be running (`php artisan queue:work`).
 */
class ReceiptIssuedMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private int $receiptId)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $receipt = Receipt::query()->with('invoice')->findOrFail($this->receiptId);
        $pdf = app(ReceiptPdf::class);
        $paidOn = $receipt->paid_at->copy()->timezone((string) config('platform-settings.public_timezone', 'Asia/Kuala_Lumpur'))->format('j M Y');

        return (new MailMessage)
            ->subject("Receipt {$receipt->number} from MyKaunsel")
            ->greeting("Hi {$notifiable->name},")
            ->line('Thank you. We received your payment.')
            ->line("Receipt: {$receipt->number}")
            ->line("For invoice: {$receipt->invoice->number}")
            ->line('Amount received: '.Money::rm($receipt->total_cents))
            ->line("Date paid: {$paidOn}")
            ->action('Open the receipt', route('org.subscription.receipt', $receipt->public_id))
            ->line('The receipt is attached as a PDF.')
            ->attachData($pdf->render($receipt), $pdf->filename($receipt), ['mime' => 'application/pdf']);
    }
}
