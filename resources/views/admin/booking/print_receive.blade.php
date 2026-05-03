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
            font-size: 10px;
            line-height: 1.2;
            background: #fff;
        }

        .slip {
            width: 100%;
            max-width: 185mm;
            margin: 0 auto;
            border: 1.2px solid #000;
            padding: 7px 9px 9px;
        }

        .header {
            display: table;
            width: 100%;
            /* table-layout: fixed; */
            border-bottom: 1.2px solid #000;
            padding-bottom: 6px;
            margin-bottom: 7px;
        }

        .header-cell {
            display: table-cell;
            vertical-align: middle;
        }

        .header-left {
            width: 110px;
            text-align: left;
        }

        .logo {
            width: 42px;
            height: 42px;
            object-fit: contain;
        }

        .header-center {
            text-align: center;
            padding: 0 8px;
        }

        .company {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: .15px;
        }

        .address {
            max-width: 82%;
            margin: 2px auto 4px;
            font-size: 9px;
            line-height: 1.28;
        }

        .title {
            display: inline-block;
            padding: 1px 10px 2px;
            border: 1px solid #000;
            font-size: 9.5px;
            font-weight: 700;
            letter-spacing: .65px;
            text-transform: uppercase;
        }

        .header-right {
            width: 110px;
            text-align: right;
            font-size: 9px;
            line-height: 1.3;
            white-space: nowrap;
        }

        .sheet {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .sheet td {
            padding: 2px 4px;
            vertical-align: bottom;
        }

        .field {
            width: 100%;
            border-collapse: collapse;
            table-layout: auto;
        }

        .field td {
            padding: 1px 2px;
            vertical-align: bottom;
        }

        .label {
            width: auto;
            padding-right: 6px;
            font-size: 9.5px;
            font-weight: 700;
            white-space: nowrap;
        }

        .value-line {
            border-bottom: 1px solid #000;
            width: 100%;
            min-height: 18px;
            padding-bottom: 2px;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.2;
            word-break: break-word;
        }

        .value-line.tall {
            min-height: 32px;
        }

        .value-line.amount {
            font-size: 13px;
            font-weight: 800;
            text-align: center;
        }

        .value-line.center {
            text-align: center;
        }

        .row-gap td {
            padding-top: 4px;
        }

        .full-line {
            border-bottom: 1px solid #000;
            min-height: 18px;
            padding-bottom: 2px;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.2;
            word-break: break-word;
        }

        .statement {
            padding-top: 3px;
            min-height: 22px;
            word-break: break-word;
        }

        .statement-line {
            border-bottom: 1px solid #000;
            min-height: 26px;
            padding: 2px 0 3px;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.2;
            word-break: break-word;
        }

        .remarks-row {
            margin-top: 5px;
        }

        .signature-row {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 12px;
        }

        .signature-row td {
            width: 25%;
            padding: 0 6px;
            text-align: center;
            vertical-align: bottom;
        }

        .signature-line {
            border-top: 1px solid #000;
            padding-top: 14px;
            font-size: 10px;
            font-weight: 700;
        }

        .nowrap {
            white-space: nowrap;
        }

        .value-strong {
            font-size: 11px;
            font-weight: 700;
        }

        .value-wrap {
            white-space: normal;
            word-break: break-word;
        }

        .small {
            font-size: 9.2px;
        }

        @media print {
            .slip {
                max-width: none;
                padding: 7px 9px 9px;
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
                $plotSize !== '-' ? 'Block / Size: ' . $plotSize : null,
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

        <table class="sheet">
            <tr>
                <td style="width: 30%;">
                    <table class="field">
                        <tr>
                            <td class="label">Date</td>
                            <td class="value-line">{{ $voucher->date ?? '-' }}</td>
                        </tr>
                    </table>
                </td>
                <td style="width: 25%;">
                    <table class="field">
                        <tr>
                            <td class="label">Slip No</td>
                            <td class="value-line">{{ $receiptNo }}</td>
                        </tr>
                    </table>
                </td>
                <td style="width: 45%;">
                    <table class="field">
                        <tr>
                            <td class="label">Received By</td>
                            <td class="value-line value-strong value-wrap">{{ $customerName }}</td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr class="row-gap">
                <td style="width: 40%;">
                    <table class="field">
                        <tr>
                            <td class="label">Buyer Name</td>
                            <td class="value-line value-wrap">{{ $customerName }}</td>
                        </tr>
                    </table>
                </td>
                <td style="width: 35%;">
                    <table class="field">
                        <tr>
                            <td class="label">Father Name</td>
                            <td class="value-line value-wrap">{{ $guardianName }}</td>
                        </tr>
                    </table>
                </td>
                <td style="width: 25%;">
                    <table class="field">
                        <tr>
                            <td class="label">Phone</td>
                            <td class="value-line">{{ $phone }}</td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr class="row-gap">
                <td>
                    <table class="field">
                        <tr>
                            <td class="label">Plot / Shop</td>
                            <td class="value-line value-wrap">{{ $plotLabel }}</td>
                        </tr>
                    </table>
                </td>
                <td>
                    <table class="field">
                        <tr>
                            <td class="label">Received Through</td>
                            <td class="value-line value-wrap">{{ $receivedThrough }}</td>
                        </tr>
                    </table>
                </td>
                <td>
                    <table class="field">
                        <tr>
                            <td class="label">Bank</td>
                            <td class="value-line value-wrap">{{ $bankName }}</td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr class="row-gap">
                <td colspan="3">
                    <table class="field">
                        <tr>
                            <td style="width: 11%;" class="label">Address</td>
                            <td class="value-line value-wrap">{{ $address }}</td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr class="row-gap">
                <td colspan="3">
                    <table class="field">
                        <tr>
                            <td style="width: 17%;" class="label">Project / Block / Size</td>
                            <td class="value-line value-wrap">{{ $projectBlockSize }}</td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr class="row-gap">
                <td>
                    <table class="field">
                        <tr>
                            <td class="label">Mode</td>
                            <td class="value-line">{{ $paymentMode }}</td>
                        </tr>
                    </table>
                </td>
                <td colspan="2">
                    <table class="field">
                        <tr>
                            <td style="width: 13%;" class="label">Transaction No</td>
                            <td class="value-line value-wrap">{{ $transactionNo }}</td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr class="row-gap">
                <td style="width: 32%;">
                    <table class="field">
                        <tr>
                            <td class="label">Amount</td>
                            <td class="value-line amount">{{ $amountDisplay }}</td>
                        </tr>
                    </table>
                </td>
                <td colspan="2" style="width: 68%;">
                    <div class="label nowrap">Amount in Words</div>
                    <div class="value-line tall value-wrap">{{ $amountInWords }}</div>
                </td>
            </tr>

            <tr class="row-gap">
                <td colspan="3" class="small">
                    Received from <span class="full-line statement">{{ $customerName }}</span>
                    against plot / project <span class="full-line statement">{{ $plotLabel }} / {{ $projectBlockSize }}</span>
                </td>
            </tr>

            <tr class="row-gap remarks-row">
                <td colspan="3">
                    <table class="field">
                        <tr>
                            <td style="width: 8%;" class="label">Remarks</td>
                            <td class="statement-line">{{ $remarks }}</td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr class="row-gap">
                <td>
                    <table class="field">
                        <tr>
                            <td class="label">Short Amount</td>
                            <td class="value-line center">{{ $shortAmount }}</td>
                        </tr>
                    </table>
                </td>
                <td>
                    <table class="field">
                        <tr>
                            <td class="label">Total Balance</td>
                            <td class="value-line center">{{ $balanceAmount }}</td>
                        </tr>
                    </table>
                </td>
                <td></td>
            </tr>
        </table>

        <table class="signature-row">
            <tr>
                <td><div class="signature-line">Prepared By</div></td>
                <td><div class="signature-line">Received By</div></td>
                <td><div class="signature-line">Accounts</div></td>
                <td><div class="signature-line">Authorized Sign</div></td>
            </tr>
        </table>
    </div>

    <script>
        window.onload = () => window.print();
    </script>
</body>

</html>
