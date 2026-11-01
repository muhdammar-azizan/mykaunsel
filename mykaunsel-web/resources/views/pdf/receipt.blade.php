@php
    // The PDF of one receipt (see pdf/invoice.blade.php: plain tables and CSS 2.1 for dompdf). Everything printed is the snapshot kept on the receipt.
    $seller = $receipt->seller ?? [];
    $buyer = $receipt->buyer ?? [];
    $rm = fn (int $cents) => \App\Support\Money::rm($cents);
    $rate = rtrim(rtrim(number_format($receipt->tax_rate_bp / 100, 2, '.', ''), '0'), '.');
    $timezone = config('platform-settings.public_timezone', 'Asia/Kuala_Lumpur');
    $methods = ['simulated' => 'Online payment (test)', 'billplz' => 'Online payment', 'manual' => 'Bank transfer', 'no_charge' => 'Nothing to pay'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $receipt->number }}</title>
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
        .status { display: inline-block; padding: 3px 10px; border: 1px solid #0b6b3a; color: #0b6b3a; font-size: 10px; font-weight: bold; letter-spacing: 1px; }
        .parties { margin-top: 26px; }
        .parties td { width: 50%; vertical-align: top; padding-right: 20px; }
        .received { margin-top: 26px; padding: 14px 16px; background: #eef7f1; }
        .received .amount { font-size: 22px; font-weight: bold; margin-top: 4px; }
        .lines { margin-top: 24px; }
        .lines th { text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 0.8px; color: #6b7390; border-bottom: 1px solid #1B2A4E; padding: 6px 0; }
        .lines th.right { text-align: right; }
        .lines td { padding: 9px 0; border-bottom: 1px solid #e3e5ee; vertical-align: top; }
        .total td { border-bottom: none; padding-top: 12px; font-size: 13px; font-weight: bold; }
        .footer { margin-top: 36px; font-size: 9px; color: #6b7390; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                <h1>Receipt</h1>
                <p class="bold" style="margin-top:3px;">{{ $receipt->number }}</p>
            </td>
            <td class="right">
                <span class="status">PAID</span>
                <p style="margin-top:8px;"><span class="muted">Date paid</span> <span class="bold">{{ $receipt->paid_at->copy()->timezone($timezone)->format('j M Y') }}</span></p>
                <p><span class="muted">For invoice</span> <span class="bold">{{ $receipt->invoice->number }}</span></p>
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <h3>Received by</h3>
                <p class="bold">{{ $seller['seller_name'] ?? '' }}</p>
                @if (! empty($seller['seller_address']))<p style="white-space: pre-line;">{{ $seller['seller_address'] }}</p>@endif
                @if (! empty($seller['seller_ssm']))<p class="muted">SSM: {{ $seller['seller_ssm'] }}</p>@endif
                @if (! empty($seller['seller_tax_no']))<p class="muted">{{ $receipt->tax_name ?: 'Tax' }} no.: {{ $seller['seller_tax_no'] }}</p>@endif
            </td>
            <td>
                <h3>Received from</h3>
                <p class="bold">{{ $buyer['name'] ?? '' }}</p>
                @if (! empty($buyer['ssm_number']))<p class="muted">SSM: {{ $buyer['ssm_number'] }}</p>@endif
            </td>
        </tr>
    </table>

    <div class="received">
        <h3>Amount received</h3>
        <p class="amount">{{ $rm($receipt->total_cents) }}</p>
        <p class="muted" style="margin-top:4px;">{{ $methods[$receipt->payment_method] ?? $receipt->payment_method }}@if ($receipt->payment_reference) · reference {{ $receipt->payment_reference }}@endif</p>
    </div>

    <table class="lines">
        <thead>
            <tr><th>In payment of</th><th class="right">Amount ({{ $receipt->currency }})</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $receipt->description }}<br><span class="muted">{{ $receipt->period_starts_on->format('j M Y') }} to {{ $receipt->period_ends_on->format('j M Y') }}</span></td>
                <td class="right">{{ $rm($receipt->subtotal_cents) }}</td>
            </tr>
            @if ($receipt->discount_cents > 0)
                <tr><td>Discount</td><td class="right">- {{ $rm($receipt->discount_cents) }}</td></tr>
            @endif
            <tr><td>{{ $receipt->tax_name ?: 'Tax' }} ({{ $rate }}%)</td><td class="right">{{ $rm($receipt->tax_cents) }}</td></tr>
            <tr class="total"><td class="right">Total paid</td><td class="right">{{ $rm($receipt->total_cents) }}</td></tr>
        </tbody>
    </table>

    <p class="footer">This receipt was made by MyKaunsel when the invoice was paid. It shows the amounts, the tax and the parties of that day.</p>
</body>
</html>
