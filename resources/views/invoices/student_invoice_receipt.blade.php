<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, print-scale: 0.9">
    <title>Invoice Receipt - {{ $invoice->invoice_uuid ?? '#' . $invoice->id }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Helvetica', 'Arial', sans-serif;
            background: #eef2f5;
            color: #1e2a3e;
            padding: 24px 16px;
        }

        /* COMPACT CONTAINER — less height, crisp bill style */
        .invoice-container {
            max-width: 880px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            transition: all 0.2s;
        }

        /* inner spacing reduced: less page height */
        .invoice-inner {
            padding: 24px 28px 28px 28px;
        }

        /* ========= HEADER ========= */
        .bill-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            border-bottom: 2px solid #e9edf2;
            padding-bottom: 16px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .brand h2 {
            font-size: 26px;
            font-weight: 700;
            letter-spacing: -0.3px;
            color: #0f3b5c;
            margin-bottom: 2px;
            line-height: 1.2;
        }

        .brand p {
            font-size: 11px;
            color: #5b6e8c;
            letter-spacing: 0.3px;
        }

        .invoice-ref {
            text-align: right;
        }

        .invoice-ref .inv-label {
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
            color: #5b6e8c;
            letter-spacing: 0.5px;
        }

        .invoice-ref .inv-number {
            font-size: 20px;
            font-weight: 800;
            color: #1e4663;
            line-height: 1.2;
        }

        .invoice-ref .inv-uuid {
            font-size: 10px;
            color: #7c8ba0;
            margin-top: 4px;
            font-family: monospace;
        }

        /* ========= COMPACT DATE ROW (grid / flex) ========= */
        .dates-bar {
            background: #f8fafd;
            border-radius: 14px;
            padding: 10px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
            border: 1px solid #eef2f8;
        }

        .date-chip {
            display: flex;
            align-items: baseline;
            gap: 8px;
            flex-wrap: wrap;
        }

        .date-chip .label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: #5f7d9c;
            letter-spacing: 0.3px;
        }

        .date-chip .value {
            font-size: 14px;
            font-weight: 600;
            color: #1f3b4c;
        }

        .date-chip .value.due-warning {
            color: #c23d2b;
            background: #fff0ed;
            padding: 2px 10px;
            border-radius: 40px;
            font-size: 13px;
        }

        .badge-light {
            background: #eef2fa;
            padding: 2px 8px;
            border-radius: 40px;
            font-size: 12px;
        }

        /* ========= INFO SECTION - BILL STYLE, JUSTIFIED ========= */
        .info-block {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px 24px;
            background: #ffffff;
            border: 1px solid #ecf0f5;
            border-radius: 14px;
            padding: 14px 18px;
            margin-bottom: 22px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            border-bottom: 1px dashed #eef2f8;
            padding: 6px 0;
        }

        .info-item .key {
            font-size: 12px;
            font-weight: 500;
            color: #5b7393;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .info-item .val {
            font-weight: 600;
            color: #1f3a4b;
            font-size: 14px;
        }

        /* ========= FEES TABLE: BILL JUSTIFIED ========= */
        .fees-wrapper {
            margin-bottom: 18px;
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid #eef2f8;
        }

        .fees-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
        }

        .fees-table th {
            background: #f1f5f9;
            color: #1f4970;
            font-weight: 600;
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .fees-table td {
            padding: 10px 16px;
            border-bottom: 1px solid #f0f3f8;
            color: #1f2f3e;
        }

        .fees-table tr:last-child td {
            border-bottom: none;
        }

        .amount-column {
            text-align: right;
            font-weight: 600;
            color: #186f65;
            font-family: monospace;
            font-size: 13.5px;
        }

        /* totals panel — compact & crisp */
        .totals-panel {
            background: #fbfdfe;
            border-radius: 14px;
            border: 1px solid #e6edf4;
            padding: 12px 18px;
            margin-bottom: 20px;
        }

        .total-line {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 14px;
        }

        .total-line.total {
            font-weight: 700;
            border-top: 1px solid #e2e9f0;
            margin-top: 6px;
            padding-top: 8px;
            font-size: 16px;
        }

        .total-line.due-line {
            font-weight: 800;
            color: #bc3f2e;
            background: #fff6f4;
            margin-top: 6px;
            padding: 8px 12px;
            border-radius: 40px;
            margin-bottom: 0;
            font-size: 16px;
        }

        .paid-amount {
            color: #2b7a4b;
            font-weight: 700;
        }

        .due-amount {
            color: #bc3f2e;
        }

        /* status badge mini */
        .status-warning {
            text-align: center;
            margin-top: 6px;
        }

        .warning-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fff1ea;
            color: #b54532;
            font-size: 11px;
            font-weight: 600;
            padding: 5px 14px;
            border-radius: 30px;
            letter-spacing: 0.3px;
        }

        /* footer minimal */
        .bill-footer {
            text-align: center;
            margin-top: 8px;
            padding-top: 12px;
            border-top: 1px solid #edf2f7;
            font-size: 11px;
            color: #6b7f99;
        }

        .bill-footer p {
            margin: 4px 0;
        }

        /* responsive compactness */
        @media (max-width: 640px) {
            .invoice-inner {
                padding: 16px;
            }

            .info-block {
                grid-template-columns: 1fr;
                gap: 4px;
            }

            .dates-bar {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        /* print adjustments: reduce page height even more */
        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }

            .invoice-container {
                box-shadow: none;
                border-radius: 0;
            }

            .invoice-inner {
                padding: 12px 16px;
            }

            .status-warning,
            .bill-footer {
                break-inside: avoid;
            }
        }
    </style>
</head>

<body>
    <div class="invoice-container">
        <div class="invoice-inner">
            <!-- HEADER: less height, clean -->
            <div class="bill-header">
                <div class="brand">
                    <h2>S HUB</h2>
                    <p>Institute Billing · Official Receipt</p>
                </div>
                <div class="invoice-ref">
                    <div class="inv-label">INVOICE #</div>
                    <div class="inv-number">INV-{{ str_pad($invoice->id, 5, '0', STR_PAD_LEFT) }}</div>
                    @if(isset($invoice->invoice_uuid) && $invoice->invoice_uuid)
                        <div class="inv-uuid">{{ $invoice->invoice_uuid }}</div>
                    @endif
                </div>
            </div>

            <!-- DATES: merged row compact -->
            <div class="dates-bar">
                <!-- <div class="date-chip">
                    <span class="label">Issue date</span>
                    <span class="value">{{ $issueDate->format('d M Y') }}</span>
                </div> -->
                <div class="date-chip">
                    <span class="label">Invoice date</span>
                    <span class="value">{{ $invoice->created_at->format('d M Y') }}</span>
                </div>
                <!-- <div class="date-chip">
                    <span class="label">Due date</span>
                    <span
                        class="value due-warning @if($dueAmount > 0) due-warning @endif">{{ $dueDate->format('d M Y') }}</span>
                </div> -->
                <!-- @if($dueAmount > 0)
                    <div class="date-chip badge-light">
                        <span>Pending</span>
                    </div>
                @else
                    <div class="date-chip badge-light" style="background:#e2f3e8; color:#1b6b43;">
                        <span>Paid in full</span>
                    </div>
                @endif -->
            </div>

            <!-- INFO: compact justified grid (bill details) -->
            <div class="info-block">
                <div class="info-item">
                    <span class="key">Institute </span>
                    <span class="val">{{ $institution->name ?? '—' }}</span>
                </div>
                <div class="info-item">
                    <span class="key">Class </span>
                    <span class="val">{{ $classroom->name ?? '—' }}</span>
                </div>
                <div class="info-item">
                    <span class="key">Student name</span>
                    <span class="val">{{ $student->first_name }} {{ $student->sur_name }}</span>
                </div>
                <div class="info-item">
                    <span class="key">Registration No </span>
                    <span class="val">{{ $student->registration_number ?? 'N/A' }}</span>
                </div>
            </div>

            <!-- FEES TABLE: bill justified alignment (amounts right, descriptions left) -->
            <div class="fees-wrapper">
                <table class="fees-table">
                    <thead>
                        <tr>
                            <th>Fee description</th>
                            <th style="text-align: right">Amount (USD)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $feeBreakdown = [
                                'Tuition Fee' => $studentFee->tuition_fee ?? 0,
                                'Uniform Fee' => $studentFee->uniform_fee ?? 0,
                                'Meals' => $studentFee->meals_fee ?? 0,
                                'Books & Supplies' => $studentFee->books_fee ?? 0,
                                'Other Charges' => $studentFee->other_fee ?? 0,
                                'Total' => $totalAmount ?? 0,
                            ];
                        @endphp
                        @foreach($feeBreakdown as $label => $amount)
                            @if($amount > 0 || $loop->last)
                                <tr>
                                    <td>{{ $label }}</td>
                                    <td class="amount-column">${{ number_format($amount, 2) }}</td>
                                </tr>
                            @endif
                        @endforeach
                        <!-- Show empty row only if all zero? not needed but fine -->
                    </tbody>
                </table>
            </div>

            <!-- TOTALS SECTION — compact & clear -->
            <div class="totals-panel">
                <div class="total-line">
                    <span>Invoice amount :</span>
                    <span><strong>${{ number_format($invoice_amount, 2) }}</strong></span>
                </div>
                <div class="total-line">
                    <span>Total paid amount :</span>
                    <span class="paid-amount">${{ number_format($paidAmount, 2) }}</span>
                </div>
                @if($dueAmount > 0)
                    <div class="total-line due-line">
                        <span>Due payment :</span>
                        <span class="due-amount">${{ number_format($dueAmount, 2) }}</span>
                    </div>
                @else
                    <div class="status-warning">
                        <div class="warning-badge" style="background:#e8f5ed; color:#2b6e47;">
                            Thank you
                        </div>
                    </div>
                @endif
            </div>

            <!-- FOOTER: minimal & bill friendly -->
            <div class="bill-footer">
                <p>This is a system-generated invoice — valid with payment reference</p>
                <p style="font-size: 10px; opacity: 0.7;">Thank you for your timely cooperation</p>
            </div>
        </div>
    </div>
</body>

</html>