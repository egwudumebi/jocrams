<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $siteName }} — Payment Receipt</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            font-family: DejaVu Sans, sans-serif;
            color: #0f172a;
            margin: 0;
            padding: 0;
        }
        .page {
            position: relative;
            width: 100%;
            height: 297mm;
            overflow: hidden;
        }
        .overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }
        .overlay img {
            width: 100%;
            height: 100%;
            display: block;
        }
        .content {
            position: absolute;
            top: 152px;
            left: 92px;
            right: 52px;
            bottom: 128px;
            z-index: 1;
            padding: 8px 12px;
        }
        .status-pill {
            display: inline-block;
            background: rgba(220, 252, 231, 0.92);
            color: #166534;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 999px;
            margin-bottom: 14px;
        }
        .amount-box {
            text-align: center;
            background: rgba(255, 255, 255, 0.72);
            border: 1px solid rgba(148, 163, 184, 0.35);
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 16px;
        }
        .amount-label {
            font-size: 9px;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #475569;
        }
        .amount-value {
            font-size: 26px;
            font-weight: bold;
            color: #0a3d91;
            margin-top: 4px;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            background: rgba(255, 255, 255, 0.78);
            border-radius: 10px;
            overflow: hidden;
        }
        .details-table td {
            border: 1px solid rgba(226, 232, 240, 0.9);
            padding: 8px 10px;
            vertical-align: top;
        }
        .details-label {
            width: 36%;
            background: rgba(248, 250, 252, 0.95);
            color: #64748b;
            font-size: 8px;
            letter-spacing: 0.7px;
            text-transform: uppercase;
            font-weight: bold;
        }
        .details-value {
            color: #0f172a;
            font-weight: bold;
        }
        .mono {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 10px;
        }
        /* Fallback when overlay asset is missing */
        .fallback-shell {
            max-width: 680px;
            margin: 28px auto;
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            overflow: hidden;
            background: #ffffff;
        }
        .fallback-header {
            background: #0a3d91;
            color: #ffffff;
            padding: 22px 24px;
            font-size: 22px;
            font-weight: bold;
        }
        .fallback-body { padding: 24px; }
    </style>
</head>
<body>
@if ($overlayDataUri)
    <div class="page">
        <div class="overlay">
            <img src="{{ $overlayDataUri }}" alt="{{ $siteName }} receipt background">
        </div>

        <div class="content">
            <div class="status-pill">Payment successful</div>

            <div class="amount-box">
                <div class="amount-label">Amount paid</div>
                <div class="amount-value">{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</div>
            </div>

            <table class="details-table">
                <tr>
                    <td class="details-label">Receipt reference</td>
                    <td class="details-value mono">{{ $payment->reference }}</td>
                </tr>
                <tr>
                    <td class="details-label">Paid by</td>
                    <td class="details-value">{{ $payerName }}</td>
                </tr>
                @if ($payerEmail)
                <tr>
                    <td class="details-label">Email</td>
                    <td class="details-value">{{ $payerEmail }}</td>
                </tr>
                @endif
                @if ($membershipNumber)
                <tr>
                    <td class="details-label">Membership number</td>
                    <td class="details-value mono">{{ $membershipNumber }}</td>
                </tr>
                @endif
                @if ($tierName)
                <tr>
                    <td class="details-label">Membership tier</td>
                    <td class="details-value">{{ $tierName }}</td>
                </tr>
                @endif
                <tr>
                    <td class="details-label">Purpose</td>
                    <td class="details-value">{{ $purposeLabel }}</td>
                </tr>
                <tr>
                    <td class="details-label">Description</td>
                    <td class="details-value">{{ $details }}</td>
                </tr>
                <tr>
                    <td class="details-label">Payment method</td>
                    <td class="details-value">{{ $gatewayLabel }}</td>
                </tr>
                <tr>
                    <td class="details-label">Date paid</td>
                    <td class="details-value">{{ $paidAt->format('F j, Y g:i A') }}</td>
                </tr>
            </table>
        </div>
    </div>
@else
    <div class="fallback-shell">
        <div class="fallback-header">{{ $siteName }} — Payment Receipt</div>
        <div class="fallback-body">
            <div class="amount-box">
                <div class="amount-label">Amount paid</div>
                <div class="amount-value">{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</div>
            </div>
            <table class="details-table">
                <tr>
                    <td class="details-label">Receipt reference</td>
                    <td class="details-value mono">{{ $payment->reference }}</td>
                </tr>
                <tr>
                    <td class="details-label">Paid by</td>
                    <td class="details-value">{{ $payerName }}</td>
                </tr>
                <tr>
                    <td class="details-label">Date paid</td>
                    <td class="details-value">{{ $paidAt->format('F j, Y g:i A') }}</td>
                </tr>
            </table>
        </div>
    </div>
@endif
</body>
</html>
