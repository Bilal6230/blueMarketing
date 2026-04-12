<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Receive Plot Payment</title>
    <style>
        @page {
            size: A4;
            margin: 10mm;
        }

        body {
            font-family: Arial, sans-serif;
            color: #000;
            font-size: 14px;
        }

        .voucher {
            padding: 12px;
        }

        .header {
            text-align: center;
            margin-bottom: 16px;
        }

        .header img {
            width: 72px;
            height: 72px;
        }

        .title {
            font-size: 26px;
            font-weight: 700;
            margin: 4px 0;
        }

        .subtitle {
            display: inline-block;
            border: 1px solid #000;
            padding: 4px 10px;
            font-weight: 700;
            border-radius: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 8px 10px;
            vertical-align: top;
        }

        th {
            text-align: left;
            width: 28%;
        }

        .remarks {
            min-height: 90px;
        }

        .signature-row td {
            border: none;
            padding-top: 40px;
            text-align: center;
        }
    </style>
</head>

<body>
    @php
        $ledger = $voucher->ledger;
        $plot = $voucher->plot_list;
        $customer = $voucher->customer_list;
        $project = $voucher->project_list;
        $plotLabel = $plot ? (($plot->type == 1 ? 'R-' : ($plot->type == 2 ? 'C-' : '')) . $plot->name) : '—';
        $voucherNumber = $ledger->voucher_number ?? $ledger->voucher ?? '—';
        $amount = $voucher->amount_out ?? 0;
        $customerName = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
    @endphp

    <div class="voucher">
        <div class="header">
            <img src="{{ asset('images/logo/blue-marketing-logo.png') }}" alt="Logo">
            <div class="title">Planet Architects & Builders</div>
            <div class="subtitle">RECEIVE PLOT PAYMENT</div>
        </div>

        <table>
            <tr>
                <th>Voucher No.</th>
                <td>{{ $voucherNumber }}</td>
                <th>Date</th>
                <td>{{ $voucher->date ?? '—' }}</td>
            </tr>
            <tr>
                <th>Slip Reference</th>
                <td>{{ $voucher->reference ?? '—' }}</td>
                <th>Status</th>
                <td>{{ approveStatus($voucher->is_approve) }}</td>
            </tr>
            <tr>
                <th>Customer</th>
                <td>{{ $customerName ?: '—' }}</td>
                <th>Project</th>
                <td>{{ $project->project ?? '—' }}</td>
            </tr>
            <tr>
                <th>Plot</th>
                <td>{{ $plotLabel }}</td>
                <th>Plot Size</th>
                <td>{{ $plot?->size ? $plot->size . ' ' . ($plot->unit ?? '') : '—' }}</td>
            </tr>
            <tr>
                <th>Payment Type</th>
                <td>{{ getPaymentTypeDetails($voucher->payment_type)['name'] }}</td>
                <th>Amount</th>
                <td>{{ number_format((float) $amount, 2) }}</td>
            </tr>
            <tr>
                <th>Transaction No.</th>
                <td>{{ $voucher->t_number ?? '—' }}</td>
                <th>Bank</th>
                <td>{{ $voucher->bank_id ? getBankNameById($voucher->bank_id) : '—' }}</td>
            </tr>
            <tr>
                <th>Passing Date</th>
                <td>{{ $voucher->passing_date ?? '—' }}</td>
                <th>Ledger Reference</th>
                <td>{{ $ledger->reference ?? '—' }}</td>
            </tr>
            <tr>
                <th>Phone</th>
                <td>{{ $customer->phone_number ?? $customer->mobile_number ?? '—' }}</td>
                <th>CNIC</th>
                <td>{{ $customer->nic_number ?? '—' }}</td>
            </tr>
            <tr>
                <th>Remarks</th>
                <td colspan="3" class="remarks">{{ $voucher->description ?? '—' }}</td>
            </tr>
        </table>

        <table class="signature-row">
            <tr>
                <td>Prepared By</td>
                <td>Received By</td>
                <td>Accounts</td>
            </tr>
        </table>
    </div>

    <script>
        window.onload = () => window.print();
    </script>
</body>

</html>
