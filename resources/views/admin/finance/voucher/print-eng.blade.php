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
            border-top: 1px solid #000;
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
            margin-top: 10px;
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
    </style>
</head>

<body>

    <div class="voucher">

        <!-- HEADER -->
        <div class="header-container" {!! Setting::getValue('print_status') == '0' ? 'style="display:none !important;"' : '' !!}>
            <img class="logo" src="{{ asset('images/logo/blue-marketing-logo.png') }}">

            <div class="center">
                <h2 class="title">Planet Architects & Builders</h2>
                <div class="address">Shop # C-215, A-Block Phase-1 Etihad Garden, Rahim Yar Khan</div>
                <div class="voucher-name">CREDIT VOUCHER</div>
            </div>

            <div>
                <div>0322-2237861</div>
                <div>068-2096888</div>
            </div>
        </div>

        <!-- Voucher Details -->
        <div style="display: flex; justify-content: space-between;">
            <div style="text-align:center; font-size:12px; font-weight:bold;">Voucher No: <span
                    class="under-line">{{ $voucher->voucher_number }}</span> ( {{ $voucher->projectHeadSubhead->headAccounting->name }} )</div>
            <div style="text-align:center; font-size:12px; font-size:12px; font-weight:bold;">Page No:__________</div>
            <div style="text-align:center; font-size:12px; font-size:12px; font-weight:bold;">Date :<span
                    class="under-line">{{ $voucher->date }}</span></span></div>

        </div>

        <!-- Account Name & Description -->


        <!-- Amount Table -->
        <table>
            <thead>
                <tr>
                    <td style="width:70%; text-align:justify; border:none; font-size:12px; font-weight:bold;">Account
                        Name: <span class="under-line"
                            style="display: inline-block;width: 60%;text-align: center;">{{ $voucher->projectHeadSubhead->subheadAccounting->name }}</span>
                    </td>
                    <td style="width:30%; text-align:center;">Amount</td>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <th style="width:70%; text-align:justify;">Detail: {{ $voucher->detail }}</th>
                    <th style="width:30%; text-align:center;"></th>
                </tr>
                <tr>
                    <td></td>
                    <td>{{ number_format($voucher->amount_in, 2) }}</td>
                </tr>
                <tr>
                    <td style="text-align:right; font-weight:bold;">Total</td>
                    <td style="font-weight:bold;">{{ number_format($voucher->amount_in, 2) }}</td>
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
