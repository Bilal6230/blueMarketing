<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>{{ $voucherTitle ?? 'Voucher' }}</title>

    <style>
        @page {
            size: 8.5in 5.5in;
            /* EXACT SMALL VOUCHER SIZE */
            margin: 5mm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            color: #000;
        }

        .voucher {
            /* width: 80%; */
            /* border-top: 1px solid #000; */
            padding: 10px;
        }

        .row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .center {
            text-align: center;
            margin: 9px;
        }

        .logo {
            width: 75px;
            height: 75px
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 6px 10px;
            font-size: 14px;
        }

        .signature-row td {
            border: none;
            padding-top: 30px;
            text-align: center;
        }

        .header-container {
            display: flex;
            justify-content: space-around;
            align-items: anchor-center;
        }

        .address {
            margin-bottom: 8px;
        }

        .title {
            margin: 0;
            font-weight: bold;
            font-size: 30px;
        }

        .voucher-name {
            font-weight: bold;
            border: 1px solid #000;
            border-radius: 6px;
            display: inline;
            padding: 2px;
        }

        .under-line {
            border-bottom: 2px solid #000;
        }

        th,
        td {
            font-size: 16px;
        }
    </style>
</head>

<body>
    @php
        $branding = getProjectPrintBranding(optional(optional($voucher)->projectHeadSubhead)->project);
        $voucherBadge = '';
        $voucherNames = [
            'CR'=>'Credit Voucher', 'DR'=>'Debit Voucher',
            'CP'=>'Payment Voucher', 'CCR'=>'Cash Receive Voucher',
            'CDR'=>'Cash Payment Voucher', 'BOR'=>'Bank Receipt Voucher',
            'BOP'=>'Bank Payment Voucher', 'EC'=>'Extra Charge',
            'ECP'=>'Extra Charge Payment', 'PPR'=>'Plot Receive Payment',
        ];
        $voucherBadge = '<div class="voucher-name">'
            .e(strtoupper($voucherTitle ?? ($voucherNames[$voucher->type] ?? $voucher->type)))
            .'</div>';
    @endphp

    <div class="voucher">

        <!-- HEADER -->
        @php
            $display = Setting::getValue($voucher->type . '_Receipt_Header') == '0' ? 'display:none !important;' : '';
        @endphp
        @include('admin.partials.print_branding_header', [
            'branding' => $branding,
            'wrapperClass' => 'header-container',
            'logoClass' => 'logo',
            'centerClass' => 'center',
            'centerStyle' => $display,
            'rightStyle' => $display,
            'extraHtml' => $voucherBadge,
        ])

        <!-- Voucher Details -->
        <div style="display: flex; justify-content: space-between;">
                    <div style="text-align:center; font-size:16px; font-weight:bold;">Voucher No: <span
                    class="under-line">{{ $displayNumber ?: ($voucher->type.'-'.$voucher->voucher_number) }}</span> (
                {{ $voucher->projectHeadSubhead->headAccounting->name }} )</div>

            <div style="text-align:center;  font-size:16px; font-weight:bold;">Date :<span
                    class="under-line">{{ $voucher->date }}</span></span></div>

        </div>

        <!-- Account Name & Description -->


        <!-- Amount Table -->
        <table>
            <thead>
                <tr>
                    <td style="width:80%; text-align:justify; border:none; font-size:18px; font-weight:bold;">Account
                        Name: <span class="under-line"
                            style="display: inline-block;width: 60%;">{{ $voucher->projectHeadSubhead->subheadAccounting->name }} @if($voucher->projectHeadSubhead->subheadAccounting->urdu_name)/ {{ $voucher->projectHeadSubhead->subheadAccounting->urdu_name }}@endif</span>
                    </td>
                    <td style="width:20%; text-align:center;">Amount</td>
                </tr>
            </thead>
            @php
                $amount = in_array((string)$voucher->type,['CP','DR','CDR','BOP','BO'],true)
                    ? (float)$voucher->amount_out
                    : (float)$voucher->amount_in;
                if(in_array((string)$voucher->type,['BOR','BOP'],true)
                    && $voucher->customer_ledger_id){
                    $source=$voucher->customerLedger;
                    $face=$voucher->type==='BOR'
                        ? (float)($source?->amount_in??0)
                        : (float)($source?->amount_out??0);
                    if($face>0)$amount=$face;
                }
            @endphp
            <tbody>
                <tr>
                    <th style="width:80%; text-align:justify; border-bottom: none;">Detail: {{ $voucher->detail }}</th>
                    <th style="width:20%; text-align:center;">{{ number_format($amount, 0, '.', '') }}</th>
                </tr>
                <tr>
                    <td style="border-top: none;"></td>
                    <td style="text-align:center; height: 15px;"></td>
                </tr>
                <tr>
                    <td style="text-align:right; font-weight:bold;">ٹوٹل /
                        {{ numberToUrduWords($amount) . ' روپے' }}</td>
                    <td style="font-weight:bold; text-align:center;">Total =
                        {{ number_format($amount, 0, '.', '') }}/-</td>
                </tr>
            </tbody>
        </table>

        <!-- Signatures -->
        <table class="signature-row" style="{{ $display }}">
            <tr>
                <td>Manager: <span class="under-line"
                        style="display: inline-block;width: 40%;text-align: center;"></span></td>
                <td>Receiver Signature: <span class="under-line"
                        style="display: inline-block;width: 40%;text-align: center;"></span></td>
                <td>Accounts Officer: <span class="under-line"
                        style="display: inline-block;width: 40%;text-align: center;"></span></td>
            </tr>
        </table>

    </div>

    <script>
        window.onload = () => window.print();
    </script>

</body>

</html>
