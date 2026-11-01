@php
    // The PDF of one invoice. dompdf draws plain HTML and CSS 2.1 (tables, no flex or grid, no Tailwind), so this page is written for it. Everything printed
    // is the snapshot kept on the invoice: editing a plan, a setting or an organization later never changes a PDF made from it.
    $seller = $invoice->seller ?? [];
    $buyer = $invoice->buyer ?? [];
    $rm = fn (int $cents) => \App\Support\Money::rm($cents);
    $rate = rtrim(rtrim(number_format($invoice->tax_rate_bp / 100, 2, '.', ''), '0'), '.');
    $paid = $invoice->status === \App\Models\Invoice::PAID;
    $void = $invoice->status === \App\Models\Invoice::VOID;
    $timezone = config('platform-settings.public_timezone', 'Asia/Kuala_Lumpur');
    $methods = ['simulated' => 'Online payment (test)', 'billplz' => 'Online payment', 'manual' => 'Bank transfer', 'no_charge' => 'Nothing to pay'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->number }}</title>
    <style>
        @page { margin: 36px 40px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1B2A4E; line-height: 1.45; }
        h1 { font-size: 22px; margin: 0; letter-spacing: -0.3px; }
        h3 { font-size: 9px; text-transform: uppercase; letter-spacing: 0.8px; color: #6b7390; margin: 0 0 4px; }
        p { margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        .muted { color: #6b7390; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .head td { vertical-align: top; }
        .status { display: inline-block; padding: 3px 10px; border: 1px solid #1B2A4E; font-size: 10px; font-weight: bold; letter-spacing: 1px; }
        .status.paid { color: #0b6b3a; border-color: #0b6b3a; }
        .status.void { color: #b3261e; border-color: #b3261e; }
        .parties { margin-top: 26px; }
        .parties td { width: 50%; vertical-align: top; padding-right: 20px; }
        .lines { margin-top: 28px; }
        .lines th { text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 0.8px; color: #6b7390; border-bottom: 1px solid #1B2A4E; padding: 6px 0; }
        .lines th.right { text-align: right; }
        .lines td { padding: 9px 0; border-bottom: 1px solid #e3e5ee; vertical-align: top; }
        .total td { border-bottom: none; padding-top: 12px; font-size: 14px; font-weight: bold; }
        .box { margin-top: 24px; padding: 10px 14px; background: #f1f3f8; }
        .footer { margin-top: 36px; font-size: 9px; color: #6b7390; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                <h1>Invoice</h1>
                <p class="bold" style="margin-top:3px;">{{ $invoice->number }}</p>
            </td>
            <td class="right">
                <span class="status {{ $paid ? 'paid' : ($void ? 'void' : '') }}">{{ strtoupper($invoice->statusLabel()) }}</span>
                <p style="margin-top:8px;"><span class="muted">Issued</span> <span class="bold">{{ $invoice->issued_on->format('j M Y') }}</span></p>
                <p><span class="muted">Due</span> <span class="bold">{{ $invoice->due_on->format('j M Y') }}</span></p>
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <h3>From</h3>
                <p class="bold">{{ $seller['seller_name'] ?? '' }}</p>
                @if (! empty($seller['seller_address']))<p style="white-space: pre-line;">{{ $seller['seller_address'] }}</p>@endif
                @if (! empty($seller['seller_ssm']))<p class="muted">SSM: {{ $seller['seller_ssm'] }}</p>@endif
                @if (! empty($seller['seller_tax_no']))<p class="muted">{{ $invoice->tax_name ?: 'Tax' }} no.: {{ $seller['seller_tax_no'] }}</p>@endif
            </td>
            <td>
                <h3>Billed to</h3>
                <p class="bold">{{ $buyer['name'] ?? '' }}</p>
                @if (! empty($buyer['ssm_number']))<p class="muted">SSM: {{ $buyer['ssm_number'] }}</p>@endif
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr><th>Description</th><th class="right">Amount ({{ $invoice->currency }})</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $invoice->description }}<br><span class="muted">{{ $invoice->period_starts_on->format('j M Y') }} to {{ $invoice->period_ends_on->format('j M Y') }}</span></td>
                <td class="right">{{ $rm($invoice->subtotal_cents) }}</td>
            </tr>
            @if ($invoice->discount_cents > 0)
                <tr><td>Discount</td><td class="right">- {{ $rm($invoice->discount_cents) }}</td></tr>
            @endif
            <tr><td>{{ $invoice->tax_name ?: 'Tax' }} ({{ $rate }}%)</td><td class="right">{{ $rm($invoice->tax_cents) }}</td></tr>
            <tr class="total"><td class="right">Total</td><td class="right">{{ $rm($invoice->total_cents) }}</td></tr>
        </tbody>
    </table>

    @if ($paid)
        <div class="box">
            <h3>Payment</h3>
            <p>Paid on <span class="bold">{{ $invoice->paid_at->copy()->timezone($timezone)->format('j M Y') }}</span> · {{ $methods[$invoice->payment_method] ?? $invoice->payment_method }}@if ($invoice->payment_reference) · reference {{ $invoice->payment_reference }}@endif</p>
            @if ($invoice->receipt)<p class="muted">Receipt {{ $invoice->receipt->number }}</p>@endif
        </div>
    @elseif ($void)
        <div class="box"><h3>Cancelled</h3><p>This invoice was cancelled and must not be paid.</p></div>
    @elseif (! empty($seller['bank_account_no']))
        <div class="box">
            <h3>Pay by bank transfer</h3>
            <p>{{ $seller['bank_name'] ?? '' }} · {{ $seller['bank_account_name'] ?? '' }} · {{ $seller['bank_account_no'] }}</p>
            <p class="muted">Use {{ $invoice->number }} as the reference. You can also pay online from the Subscription page of your MyKaunsel account.</p>
        </div>
    @else
        <div class="box"><h3>How to pay</h3><p>Pay online from the Subscription page of your MyKaunsel account.</p></div>
    @endif

    <p class="footer">This invoice was made by MyKaunsel. The amounts, the tax and the parties are those of the day it was issued.</p>
</body>
</html>
