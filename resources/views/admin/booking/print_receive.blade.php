<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Receive Plot Payment Slip</title>
    <style>
        @page {
            size: A4;
            margin: 9mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #000;
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.35;
            background: #fff;
        }

        .receipt {
            width: 100%;
            border: 2px solid #000;
            padding: 10px;
        }

        .header {
            display: table;
            width: 100%;
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }

        .header-cell {
            display: table-cell;
            vertical-align: middle;
        }

        .header-left {
            width: 78px;
        }

        .logo {
            width: 64px;
            height: 64px;
            object-fit: contain;
        }

        .header-center {
            text-align: center;
        }

        .company {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: .3px;
            margin: 0;
        }

        .address {
            font-size: 11px;
            margin: 4px 0;
        }

        .title {
            display: inline-block;
            border: 1px solid #000;
            padding: 4px 14px;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: .8px;
        }

        .header-right {
            width: 130px;
            text-align: right;
            font-size: 11px;
            line-height: 1.5;
        }

        .grid {
            width: 100%;
            border-collapse: collapse;
        }

        .grid td,
        .grid th {
            border: 1px solid #000;
            padding: 6px 8px;
            vertical-align: top;
        }

        .label {
            width: 17%;
            font-weight: 700;
            white-space: nowrap;
            background: #f7f7f7;
        }

        .value {
            width: 33%;
        }

        .full-label {
            width: 17%;
            font-weight: 700;
            background: #f7f7f7;
        }

        .full-value {
            width: 83%;
        }

        .amount-box {
            text-align: right;
            font-weight: 700;
            font-size: 14px;
            white-space: nowrap;
        }

        .remarks-box {
            min-height: 54px;
        }

        .signatures {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
        }

        .signatures td {
            width: 33.33%;
            border: none;
            padding: 24px 10px 0;
            text-align: center;
            font-weight: 700;
        }

        .sign-line {
            display: block;
            border-top: 1px solid #000;
            padding-top: 6px;
        }

        @media print {
            body {
                font-size: 11px;
            }

            .receipt {
                border-width: 1.5px;
                padding: 8px;
            }

            .grid td,
            .grid th {
                padding: 5px 7px;
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

        $receiptNo = $ledger->voucher_number ?? $ledger->voucher ?? '—';
        $customerName = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
        if ($customerName === '') {
            $customerName = $fallbackCustomer->name ?? '—';
        }

        $phone = $customer->phone_number ?? $customer->mobile_number ?? $fallbackCustomer->phone ?? '—';
        $guardianName = $customer->father_name ?? $customer->relate ?? '—';
        $address = $customer->home_address ?? '—';
        $plotLabel = $plot ? (Setting::getPlotTypeShort($plot->type) . '-' . $plot->name) : '—';
        $plotSize = $plot && $plot->size ? trim($plot->size . ' ' . ($plot->unit ?? '')) : '—';
        $projectName = $project->project ?? null;
        $projectBlockSize = trim(collect([
            $projectName,
            $plotSize !== '—' ? 'Size: ' . $plotSize : null,
        ])->filter()->implode(' | '));
        $projectBlockSize = $projectBlockSize !== '' ? $projectBlockSize : '—';
        $amount = (float) ($voucher->amount_out ?? 0);
        $amountDisplay = number_format($amount, 2);
        $wholeAmount = (int) floor($amount);
        $decimalAmount = (int) round(($amount - $wholeAmount) * 100);
        $amountInWords = $toWords($wholeAmount) . ' Rupees';
        if ($decimalAmount > 0) {
            $amountInWords .= ' and ' . $toWords($decimalAmount) . ' Paisa';
        }
        $amountInWords .= ' Only';
        $paymentMode = getPaymentTypeDetails($voucher->payment_type)['name'] ?? '—';
        $transactionNo = $voucher->t_number ?? $ledger->reference ?? '—';
        $bankName = $voucher->bank_id ? getBankNameById($voucher->bank_id) : '—';
        $remarks = $voucher->description ?? $ledger->detail ?? '—';
        $receivedThrough = $voucher->note ?? '—';
        $shortAmount = '—';
        $balanceAmount = '—';
    @endphp

    <div class="receipt">
        <div class="header">
            <div class="header-cell header-left">
                <img class="logo" src="{{ asset('images/logo/blue-marketing-logo.png') }}" alt="Logo">
            </div>
            <div class="header-cell header-center">
                <h1 class="company">Planet Architects &amp; Builders</h1>
                <div class="address">Shop # C-215, A-Block Phase-1 Etihad Garden, Rahim Yar Khan</div>
                <div class="title">RECEIVE PLOT PAYMENT SLIP</div>
            </div>
            <div class="header-cell header-right">
                <div>0322-2237861</div>
                <div>068-2096888</div>
            </div>
        </div>

        <table class="grid">
            <tr>
                <td class="label">Receipt No.</td>
                <td class="value">{{ $receiptNo }}</td>
                <td class="label">Date</td>
                <td class="value">{{ $voucher->date ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Phone</td>
                <td class="value">{{ $phone }}</td>
                <td class="label">Buyer Name</td>
                <td class="value">{{ $customerName }}</td>
            </tr>
            <tr>
                <td class="label">Father / Guardian Name</td>
                <td class="value">{{ $guardianName }}</td>
                <td class="label">Received Through / By</td>
                <td class="value">{{ $receivedThrough }}</td>
            </tr>
            <tr>
                <td class="full-label">Address</td>
                <td class="full-value" colspan="3">{{ $address }}</td>
            </tr>
            <tr>
                <td class="label">Plot / Shop No.</td>
                <td class="value">{{ $plotLabel }}</td>
                <td class="label">Project / Block / Size</td>
                <td class="value">{{ $projectBlockSize }}</td>
            </tr>
            <tr>
                <td class="label">Amount</td>
                <td class="value amount-box">{{ $amountDisplay }}</td>
                <td class="label">Amount in Words</td>
                <td class="value">{{ $amountInWords }}</td>
            </tr>
            <tr>
                <td class="label">Payment Mode</td>
                <td class="value">{{ $paymentMode }}</td>
                <td class="label">Transaction No.</td>
                <td class="value">{{ $transactionNo }}</td>
            </tr>
            <tr>
                <td class="label">Bank</td>
                <td class="value">{{ $bankName }}</td>
                <td class="label">Status</td>
                <td class="value">{{ approveStatus($voucher->is_approve) }}</td>
            </tr>
            <tr>
                <td class="full-label">Additional Details / Remarks</td>
                <td class="full-value remarks-box" colspan="3">{{ $remarks }}</td>
            </tr>
            <tr>
                <td class="label">Short Amount</td>
                <td class="value">{{ $shortAmount }}</td>
                <td class="label">Total Balance Amount</td>
                <td class="value">{{ $balanceAmount }}</td>
            </tr>
        </table>

        <table class="signatures">
            <tr>
                <td><span class="sign-line">Prepared By</span></td>
                <td><span class="sign-line">Received By</span></td>
                <td><span class="sign-line">Accounts</span></td>
            </tr>
        </table>
    </div>

    <script>
        window.onload = () => window.print();
    </script>
</body>

</html>
