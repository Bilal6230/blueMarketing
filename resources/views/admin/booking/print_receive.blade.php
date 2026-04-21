<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Receive Plot Payment Slip</title>
    <style>
        @page {
            size: A4;
            margin: 10mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #000;
            font-family: Arial, sans-serif;
            font-size: 11.25px;
            line-height: 1.35;
            background: #fff;
        }

        .page {
            width: 100%;
            padding: 4mm 0;
        }

        .receipt {
            width: 100%;
            max-width: 182mm;
            margin: 0 auto;
            border: 1.75px solid #000;
            padding: 10px 12px 12px;
        }

        .header {
            display: table;
            width: 100%;
            table-layout: fixed;
            border-bottom: 1.5px solid #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }

        .header-cell {
            display: table-cell;
            vertical-align: middle;
        }

        .header-left {
            width: 62px;
        }

        .logo {
            width: 50px;
            height: 50px;
            object-fit: contain;
        }

        .header-center {
            text-align: center;
            padding: 0 10px;
        }

        .company {
            margin: 0;
            font-size: 19px;
            font-weight: 700;
            letter-spacing: .15px;
        }

        .address {
            max-width: 78%;
            margin: 3px auto 5px;
            font-size: 9.75px;
            line-height: 1.35;
        }

        .title {
            display: inline-block;
            border: 1px solid #000;
            padding: 3px 10px 4px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .55px;
        }

        .header-right {
            width: 118px;
            text-align: right;
            font-size: 9.75px;
            line-height: 1.45;
            white-space: nowrap;
        }

        .meta-strip {
            display: table;
            width: 100%;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 6px 0;
            margin: 0 -6px 10px;
        }

        .meta-item {
            display: table-cell;
            border: 1px solid #000;
            padding: 5px 8px;
            font-size: 10px;
            font-weight: 700;
        }

        .meta-item:last-child {
            text-align: right;
        }

        .section-grid {
            display: table;
            width: 100%;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 8px 0;
            margin: 0 -8px 8px;
        }

        .section-col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding: 0 8px;
        }

        .panel {
            border: 1px solid #000;
            min-height: 100%;
        }

        .panel-title {
            border-bottom: 1px solid #000;
            padding: 5px 8px;
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .35px;
            background: #f5f5f5;
        }

        .panel-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .panel-table td {
            padding: 6px 8px;
            vertical-align: top;
            border-bottom: 1px solid #000;
        }

        .panel-table tr:last-child td {
            border-bottom: none;
        }

        .label {
            width: 34%;
            font-weight: 700;
        }

        .value {
            width: 66%;
            word-break: break-word;
        }

        .block {
            border: 1px solid #000;
            margin-top: 8px;
        }

        .block-title {
            border-bottom: 1px solid #000;
            padding: 5px 8px;
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .35px;
            background: #f5f5f5;
        }

        .block-body {
            padding: 8px 10px;
        }

        .plot-grid,
        .amount-grid {
            display: table;
            width: 100%;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 10px 0;
        }

        .plot-cell,
        .amount-cell {
            display: table-cell;
            vertical-align: top;
        }

        .plot-cell {
            width: 50%;
        }

        .amount-cell.amount-main {
            width: 34%;
            border-right: 1px solid #000;
            padding-right: 12px;
        }

        .amount-cell.amount-words {
            width: 66%;
            padding-left: 12px;
        }

        .stack-item + .stack-item {
            margin-top: 8px;
        }

        .field-name {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .3px;
            margin-bottom: 3px;
        }

        .field-value {
            word-break: break-word;
        }

        .amount-number {
            font-size: 20px;
            font-weight: 700;
            line-height: 1.1;
        }

        .amount-caption {
            margin-top: 3px;
            font-size: 9.75px;
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        .remarks {
            min-height: 54px;
            word-break: break-word;
        }

        .balance-grid {
            display: table;
            width: 100%;
            table-layout: fixed;
            border-top: 1px solid #000;
            margin-top: 8px;
        }

        .balance-item {
            display: table-cell;
            width: 50%;
            padding: 7px 10px 0;
            vertical-align: top;
        }

        .balance-item + .balance-item {
            border-left: 1px solid #000;
        }

        .signatures {
            display: table;
            width: 100%;
            table-layout: fixed;
            margin-top: 18px;
        }

        .signatures .sign-item {
            display: table-cell;
            width: 33.33%;
            text-align: center;
            vertical-align: bottom;
            padding: 0 8px;
        }

        .sign-line {
            display: block;
            padding-top: 24px;
            border-top: 1px solid #000;
            font-weight: 700;
        }

        @media print {
            body {
                font-size: 11px;
            }

            .page {
                padding: 0;
            }

            .receipt {
                max-width: none;
                border-width: 1.5px;
                padding: 9px 11px 11px;
            }
        }
    </style>
</head>

<body>
    @php
        $ledger = $voucher->ledger;
        $projectHeadSubhead = optional($ledger)->projectHeadSubhead;
        $customer = $voucher->customer_list;
        $fallbackCustomer = optional($projectHeadSubhead)->subheadAccounting;
        $plot = $voucher->plot_list ?: optional($projectHeadSubhead)->plot;
        $project = optional($projectHeadSubhead)->project ?: $voucher->project_list;

        $toWords = function ($number) use (&$toWords) {
            $number = (int) $number;

            $ones = [
                0 => 'Zero',
                1 => 'One',
                2 => 'Two',
                3 => 'Three',
                4 => 'Four',
                5 => 'Five',
                6 => 'Six',
                7 => 'Seven',
                8 => 'Eight',
                9 => 'Nine',
                10 => 'Ten',
                11 => 'Eleven',
                12 => 'Twelve',
                13 => 'Thirteen',
                14 => 'Fourteen',
                15 => 'Fifteen',
                16 => 'Sixteen',
                17 => 'Seventeen',
                18 => 'Eighteen',
                19 => 'Nineteen',
            ];

            $tens = [
                2 => 'Twenty',
                3 => 'Thirty',
                4 => 'Forty',
                5 => 'Fifty',
                6 => 'Sixty',
                7 => 'Seventy',
                8 => 'Eighty',
                9 => 'Ninety',
            ];

            if ($number < 20) {
                return $ones[$number];
            }

            if ($number < 100) {
                $ten = intdiv($number, 10);
                $remainder = $number % 10;
                return $tens[$ten] . ($remainder ? ' ' . $ones[$remainder] : '');
            }

            if ($number < 1000) {
                $hundreds = intdiv($number, 100);
                $remainder = $number % 100;
                return $ones[$hundreds] . ' Hundred' . ($remainder ? ' ' . $toWords($remainder) : '');
            }

            $scales = [
                10000000 => 'Crore',
                100000 => 'Lakh',
                1000 => 'Thousand',
            ];

            foreach ($scales as $divisor => $label) {
                if ($number >= $divisor) {
                    $quotient = intdiv($number, $divisor);
                    $remainder = $number % $divisor;
                    return $toWords($quotient) . ' ' . $label . ($remainder ? ' ' . $toWords($remainder) : '');
                }
            }

            return (string) $number;
        };

        $receiptNo = $ledger->voucher_number ?? $ledger->voucher ?? '-';
        $customerName = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
        if ($customerName === '') {
            $customerName = $fallbackCustomer->name ?? '-';
        }

        $phone = $customer->phone_number ?? $customer->mobile_number ?? $fallbackCustomer->phone ?? '-';
        $guardianName = $customer->father_name ?? $customer->relate ?? '-';
        $address = $customer->home_address ?? '-';
        $plotLabel = $plot ? (Setting::getPlotTypeShort($plot->type) . '-' . $plot->name) : '-';
        $plotSize = $plot && $plot->size ? trim($plot->size . ' ' . ($plot->unit ?? '')) : '-';
        $projectName = $project->project ?? '-';
        $projectBlockSize = trim(
            collect([
                $projectName,
                $plotSize !== '-' ? 'Size: ' . $plotSize : null,
            ])
                ->filter()
                ->implode(' | '),
        );
        $projectBlockSize = $projectBlockSize !== '' ? $projectBlockSize : '-';
        $amount = (float) ($voucher->amount_out ?? 0);
        $amountDisplay = number_format($amount, 2);
        $wholeAmount = (int) floor($amount);
        $decimalAmount = (int) round(($amount - $wholeAmount) * 100);
        $amountInWords = $toWords($wholeAmount) . ' Rupees';
        if ($decimalAmount > 0) {
            $amountInWords .= ' and ' . $toWords($decimalAmount) . ' Paisa';
        }
        $amountInWords .= ' Only';
        $paymentMode = getPaymentTypeDetails($voucher->payment_type)['name'] ?? '-';
        $transactionNo = $voucher->t_number ?? $ledger->reference ?? '-';
        $bankName = $voucher->bank_id ? getBankNameById($voucher->bank_id) : '-';
        $remarks = $voucher->description ?? $ledger->detail ?? '-';
        $receivedThrough = $voucher->note ?? '-';
        $shortAmount = '-';
        $balanceAmount = '-';
        $branding = getProjectPrintBranding($project);
    @endphp

    <div class="page">
        <div class="receipt">
            @include('admin.partials.print_branding_header', [
                'branding' => $branding,
                'wrapperClass' => 'header',
                'logoClass' => 'logo header-cell header-left',
                'centerClass' => 'header-cell header-center',
                'rightClass' => 'header-cell header-right',
                'titleClass' => 'company',
                'extraHtml' => '<div class="title">RECEIVE PLOT PAYMENT SLIP</div>',
            ])

            <div class="meta-strip">
                <div class="meta-item">Receipt No: {{ $receiptNo }}</div>
                <div class="meta-item">Date: {{ $voucher->date ?? '-' }}</div>
            </div>

            <div class="section-grid">
                <div class="section-col">
                    <div class="panel">
                        <div class="panel-title">Buyer Information</div>
                        <table class="panel-table">
                            <tr>
                                <td class="label">Buyer Name</td>
                                <td class="value">{{ $customerName }}</td>
                            </tr>
                            <tr>
                                <td class="label">Phone</td>
                                <td class="value">{{ $phone }}</td>
                            </tr>
                            <tr>
                                <td class="label">Father / Guardian</td>
                                <td class="value">{{ $guardianName }}</td>
                            </tr>
                            <tr>
                                <td class="label">Address</td>
                                <td class="value">{{ $address }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="section-col">
                    <div class="panel">
                        <div class="panel-title">Payment Information</div>
                        <table class="panel-table">
                            <tr>
                                <td class="label">Payment Mode</td>
                                <td class="value">{{ $paymentMode }}</td>
                            </tr>
                            <tr>
                                <td class="label">Transaction No.</td>
                                <td class="value">{{ $transactionNo }}</td>
                            </tr>
                            <tr>
                                <td class="label">Bank</td>
                                <td class="value">{{ $bankName }}</td>
                            </tr>
                            <tr>
                                <td class="label">Status</td>
                                <td class="value">{{ approveStatus($voucher->is_approve) }}</td>
                            </tr>
                            <tr>
                                <td class="label">Received By</td>
                                <td class="value">{{ $receivedThrough }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="block">
                <div class="block-title">Plot / Project Details</div>
                <div class="block-body">
                    <div class="plot-grid">
                        <div class="plot-cell">
                            <div class="stack-item">
                                <div class="field-name">Plot / Shop No.</div>
                                <div class="field-value">{{ $plotLabel }}</div>
                            </div>
                        </div>
                        <div class="plot-cell">
                            <div class="stack-item">
                                <div class="field-name">Project / Block / Size</div>
                                <div class="field-value">{{ $projectBlockSize }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="block">
                <div class="block-title">Amount Summary</div>
                <div class="block-body">
                    <div class="amount-grid">
                        <div class="amount-cell amount-main">
                            <div class="field-name">Amount Received</div>
                            <div class="amount-number">{{ $amountDisplay }}</div>
                            <div class="amount-caption">PKR</div>
                        </div>
                        <div class="amount-cell amount-words">
                            <div class="field-name">Amount in Words</div>
                            <div class="field-value">{{ $amountInWords }}</div>
                        </div>
                    </div>

                    <div class="balance-grid">
                        <div class="balance-item">
                            <div class="field-name">Short Amount</div>
                            <div class="field-value">{{ $shortAmount }}</div>
                        </div>
                        <div class="balance-item">
                            <div class="field-name">Total Balance Amount</div>
                            <div class="field-value">{{ $balanceAmount }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="block">
                <div class="block-title">Additional Details / Remarks</div>
                <div class="block-body remarks">{{ $remarks }}</div>
            </div>

            <div class="signatures">
                <div class="sign-item"><span class="sign-line">Prepared By</span></div>
                <div class="sign-item"><span class="sign-line">Received By</span></div>
                <div class="sign-item"><span class="sign-line">Accounts</span></div>
            </div>
        </div>
    </div>

    <script>
        window.onload = () => window.print();
    </script>
</body>

</html>
