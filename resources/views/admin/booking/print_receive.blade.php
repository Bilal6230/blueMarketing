<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Slip Book</title>

    <style>
        @page {
            size: A5 landscape;
            margin: 10mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #ffffff;
            color: #000000;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            line-height: 1.35;
        }

        .slip {
            width: 100%;
            max-width: 270mm;
            margin: 0 auto;
            padding: 6px 8px;
        }

        .header {
            display: table;
            width: 100%;
            table-layout: fixed;
            margin-bottom: 14px;
        }

        .header-left,
        .header-center,
        .header-right {
            display: table-cell;
            vertical-align: top;
        }

        .header-left {
            width: 28%;
            /* padding-top: 20px; */
            font-size: 15px;
            font-weight: 700;
            line-height: 1.35;
        }

        .header-center {
            width: 44%;
            text-align: center;
        }

        .header-right {
            width: 28%;
            text-align: right;
            padding-top: 4px;
        }

        .slip-book {
            font-size: 30px;
            font-weight: 700;
            margin-bottom: 16px;
        }

        .logo {
            max-width: 90px;
            max-height: 90px;
            object-fit: contain;
            display: block;
            /* margin: 0 auto 2px; */
            margin-left: auto;
        }

        .company-name {
            margin: 0;
            font-size: 26px;
            font-weight: 700;
            line-height: 1.1;
        }
        .company{
            margin: 0;
        }

        .company-subtitle {
            font-size: 13px;
            margin-top: 2px;
        }

        .form-row {
            display: table;
            width: 100%;
            table-layout: fixed;
            margin-bottom: 18px;
        }

        .form-col {
            display: table-cell;
            vertical-align: bottom;
            padding-right: 20px;
        }

        .form-col:last-child {
            padding-right: 0;
        }

        .field {
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        .label {
            display: table-cell;
            width: 50px;
            font-weight: 700;
            white-space: nowrap;
            vertical-align: bottom;
            padding-right: 6px;
            text-align: left;
            font-size: 18px;
        }

        .label.small {
            width: 75px;
        }

        .label.medium {
            width: 100px;
        }

        .label.large {
            width: 125px;
        }

        .value {
            display: table-cell;
            min-height: 24px;
            border-bottom: 1px solid #000000;
            padding: 0 8px 3px;
            vertical-align: bottom;
            word-break: break-word;
            font-size: 16px;
        }

        .value.center {
            text-align: center;
        }

        .value.amount {
            text-align: center;
            font-weight: 700;
            font-size: 16px;
        }

        .value.long {
            min-height: 34px;
        }

        .bottom-row {
            margin-top: 24px;
        }
        .short{
            width: 135px !important;
        }

        @media print {
            body {
                font-size: 13px;
            }

            .slip {
                max-width: none;
            }
        }
    </style>
</head>

<body>
    @php
        $ledger = $voucher->ledger ?? null;
        $projectHeadSubhead = optional($ledger)->projectHeadSubhead;
        $customer = $voucher->customer_list ?? null;
        $fallbackCustomer = optional($projectHeadSubhead)->subheadAccounting;
        $plot = $voucher->plot_list ?? null ?: optional($projectHeadSubhead)->plot;
        $project = optional($projectHeadSubhead)->project ?: $voucher->project_list ?? null;

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
                return $ones[$number] ?? (string) $number;
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

        $receiptNo = optional($ledger)->voucher_number ?? (optional($ledger)->voucher ?? '-');

        $customerName = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
        if ($customerName === '') {
            $customerName = $fallbackCustomer->name ?? '-';
        }

        $phone = $customer->phone_number ?? ($customer->mobile_number ?? ($fallbackCustomer->phone ?? '-'));

        $guardianName = $customer->father_name ?? ($customer->relate ?? '-');

        $address = $customer->home_address ?? ($fallbackCustomer->address ?? '-');

        $receivedThrough = $voucher->note ?? '-';

        $plotLabel = '-';
        if ($plot) {
            $plotType = Setting::getPlotTypeShort($plot->type);
            $plotLabel = trim($plotType . '-' . ($plot->name ?? ''), '-');
            $plotLabel = $plotLabel !== '' ? $plotLabel.' ('.$plot->size.')' : '-';
        }

        $amount = (float) ($voucher->amount_out ?? 0);
        $amountDisplay = number_format($amount, 2);

        $wholeAmount = (int) floor($amount);
        $decimalAmount = (int) round(($amount - $wholeAmount) * 100);

        $amountInWords = $toWords($wholeAmount) . ' Rupees';
        if ($decimalAmount > 0) {
            $amountInWords .= ' and ' . $toWords($decimalAmount) . ' Paisa';
        }
        $amountInWords .= ' Only';

        $paymentFor = $voucher->payment_for ?? ($voucher->purpose ?? '-');

        $additionalDetails = $voucher->description ?? (optional($ledger)->detail ?? '-');

        $cash = getPaymentTypeDetails($voucher->payment_type)['name'] ?? '-';

        $totalBalanceAmount = $balance;
        $shortAmount = $balances;

        $branding = function_exists('getProjectPrintBranding') ? getProjectPrintBranding($project) : [];

        $companyName = $branding['title'] ?? 'Jallundhar Commercial Center';
        $companySubtitle = $branding['address'] ?? 'Commercial Center';
        $companyPhone = $branding['phone'] ?? '0322-6777570 / 0300-3821088';
        $logo = $branding['logo'] ?? null;
    @endphp

    <div class="slip">
        @include('admin.partials.print_branding_header', [
            'branding' => $branding,
            'wrapperClass' => 'header',
            'logoClass' => 'logo header-cell header-left',
            'centerClass' => 'header-cell header-center',
            'rightClass' => 'header-cell header-right',
            'titleClass' => 'company',
            'extraHtml' => '<div class="title">Receive Plot Payment Slip</div>',
        ])

        <div class="form-row">
            <div class="form-col" style="width: 33.33%;">
                <div class="field">
                    <div class="label">Date</div>
                    <div class="value">{{ $voucher->date ?? '-' }}</div>
                </div>
            </div>

            <div class="form-col" style="width: 33.33%;">
                <div class="field">
                    <div class="label small">Phone</div>
                    <div class="value">{{ $phone }}</div>
                </div>
            </div>

            <div class="form-col" style="width: 33.33%;">
                <div class="field">
                    <div class="label medium">SR Number</div>
                    <div class="value center">{{ $receiptNo }}</div>
                </div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-col" style="width: 50%;">
                <div class="field">
                    <div class="label">Name</div>
                    <div class="value">{{ $customerName }}</div>
                </div>
            </div>

            <div class="form-col" style="width: 50%;">
                <div class="field">
                    <div class="label">S/O</div>
                    <div class="value">{{ $guardianName }}</div>
                </div>
            </div>

        </div>

        <div class="form-row">
            <div class="form-col" style="width: 65%;">
                <div class="field">
                    <div class="label small">Address</div>
                    <div class="value">{{ $address }}</div>
                </div>
            </div>

            <div class="form-col" style="width: 35%;">
                <div class="field">
                    <div class="label medium">Plot / Shop</div>
                    <div class="value">{{ $plotLabel }}</div>
                </div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-col" style="width: 30%;">
                <div class="field">
                    <div class="label small">Amount</div>
                    <div class="value amount">{{ $amountDisplay }}</div>
                </div>
            </div>
            <div class="form-col" style="width: 70%;">
                <div class="field">
                    <div class="label medium">
                     in Words</div>
                    <div class="value">{{ $amountInWords }}</div>
                </div>
            </div>


        </div>
        <div class="form-row">

            <div class="form-col" style="width: 25%;">
                <div class="field">
                    <div class="label small">Cash</div>
                    <div class="value">{{ $cash }}</div>
                </div>
            </div>
            <div class="form-col" style="width: 45%;">
                <div class="field">
                    <div class="label short">Payment For</div>
                    <div class="value">{{ $paymentFor }}</div>
                </div>
            </div>
            <div class="form-col" style="width: 30%;">
                <div class="field">
                    <div class="label medium">Through</div>
                    <div class="value"></div>
                </div>
            </div>
        </div>

        <div class="form-row">


            <div class="form-col" style="width: 100%;">
                <div class="field">
                    <div class="label large">Add. Details</div>
                    <div class="value long">{{ $additionalDetails }}</div>
                </div>
            </div>
        </div>

        <div class="form-row bottom-row">
            <div class="form-col" style="width: 33.33%;">
                <div class="field">
                    <div class="label large">Total Balance</div>
                    <div class="value center">{{ $totalBalanceAmount }}</div>
                </div>
            </div>

            <div class="form-col" style="width: 36.33%;">
                <div class="field">
                    <div class="label short">PayAble Short</div>
                    <div class="value center">{{ $shortAmount }}</div>
                </div>
            </div>

            <div class="form-col" style="width: 30.33%;">
                <div class="field">
                    <div class="label short">Receiver Sign</div>
                    <div class="value"></div>
                </div>
            </div>
        </div>

    </div>

    <script>
        window.onload = () => window.print();
    </script>
</body>

</html>
