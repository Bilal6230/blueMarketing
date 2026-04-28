<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Credit Voucher</title>

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
        if ($voucher->type == 'CR') {
            $voucherBadge = '<div class="voucher-name">CREDIT VOUCHER</div>';
        } elseif ($voucher->type == 'CP' || $voucher->type == 'BO') {
            $voucherBadge = '<div class="voucher-name">PAYMENT VOUCHER</div>';
        }
    @endphp

    <div class="voucher">

        <!-- HEADER -->
        @include('admin.partials.print_branding_header', [
            'branding' => $branding,
            'wrapperClass' => 'header-container',
            'logoClass' => 'logo',
            'centerClass' => 'center',
            'centerStyle' => Setting::getValue('print_status') == '0' ? 'display:none !important;' : '',
            'rightStyle' => Setting::getValue('print_status') == '0' ? 'display:none !important;' : '',
            'extraHtml' => $voucherBadge,
        ])

        <!-- Voucher Details -->
        <div style="display: flex; justify-content: space-between;">
                        <div style="text-align:center; font-size:16px; font-weight:bold;">Voucher No: <span
                    class="under-line">{{ $voucher->voucher_number }}</span> (
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
                if($voucher->type == 'CP' || $voucher->type == 'BO'){
                    $amount = $voucher->amount_out;
                }else if($voucher->type == 'CR'){
                    $amount = $voucher->amount_in;
                }
                $amount = (float) ($amount ?? 0);
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
        <table class="signature-row">
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
