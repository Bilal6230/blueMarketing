<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/png" href="{{url('public/logo')}}" />
    <title>{{ $title }}</title>
    <style type="text/css">
        span,td {
            font-size: 13px;
            line-height: 1.4;
        }
        @media print {
            .hidden-print {
                display: none !important;
            }
            tr.table-header {
                background-color:rgb(48, 49, 51) !important;
                -webkit-print-color-adjust: exact;
            }
            td.td-text {
                -webkit-print-color-adjust: exact;
            }
        }
        table, tr, td {font-family: sans-serif; border-collapse: collapse;}
    </style>
</head>
<body>
@if(preg_match('~[0-9]~', url()->previous()))
    @php $url = '../../pos'; @endphp
@else
    @php $url = url()->previous(); @endphp
@endif
<div class="hidden-print">
    <table>
        <tr>
            <td><a href="{{$url}}" class="btn btn-info"><i class="fa fa-arrow-left"></i> Back</a></td>
            <td><button onclick="window.print();" class="btn btn-primary"><i class="dripicons-print"></i> Print</button></td>
        </tr>
    </table>
    <br>
</div>
<table style="width: 100%; border-collapse: collapse;">
    <tr>
        <td colspan="2" style="padding:0px 0; width:40%">
            <h1 style="margin:0">{{ $title }}</h1>
        </td>
        <td style="padding:5px -19px; width:30%; text-align:right;">
            <div style="display: flex; justify-content: space-between; border-bottom:1px solid #aaa">
                <span><b>Print Date:</b></span> {{ $today }} <span></span>
            </div>
            <div style="display: flex; justify-content: space-between; border-bottom:1px solid #aaa">
                <span><b>Booking Number:</b></span> BO-{{ get_new_booking_number($data->id) }} <span></span>
            </div>
        </td>
    </tr>
</table>

<table style="width: 100%; border-collapse: collapse; margin-top: 4px;">
    <tbody>
    <tr>
        <td colspan="3" style="padding:4px 12px; width:40%; vertical-align:top">
            <h2 style="background-color: rgb(1, 75, 148); color: white; padding:0px 10px; margin-bottom:0">Customer Details</h2>
            <div style="margin-top: 10px; margin-left: 10px">
                <span style="display: inline-block; width: 70px;"><b> Name</b></span>
                <span><b> {{ $data->customer->first_name }} {{ $data->customer->last_name }} {{ $data->customer->relate }} {{ $data->customer->father_name }} </b></span>
            </div>
            <div style="margin-left: 10px">
                <span style="display: inline-block; width: 70px;">CNIC:</span>&nbsp;&nbsp;<span> {{ $data->customer->nic_number }}<i class="fa fa-address-book" aria-hidden="true"></i></span>
            </div>
            <div style="margin-left: 10px">
                <span style="display: inline-block; width: 70px;">Phone:</span>&nbsp;&nbsp;<span> {{ $data->customer->phone_number }}<i class="fa fa-address-book" aria-hidden="true"></i></span>
            </div>
            <div style="margin-bottom: 10px; margin-left: 10px">
                <span style="display: inline-block; width: 70px;">Address:</span>&nbsp;&nbsp;<span> {{ $data->customer->home_address }} </span>
            </div>
        </td>
        <td colspan="2" style="padding:4px 12px; width:40%; vertical-align:top">
            <h2 style="background-color: rgb(1, 75, 148); color: white; padding:0px 10px; margin-bottom:0">Plot Details</h2>
            <div style="margin-top: 10px; margin-left: 10px">
                <span style="display: inline-block; width: 70px;"><b> Project</b></span>&nbsp;<span><b>{{ $data->project->project }}</b></span>
            </div>
            <div style="margin-left: 10px">
                <span style="display: inline-block; width: 70px;">City :</span>&nbsp;&nbsp;<span>{{ $data->project->address }}<i class="fa fa-address-book" aria-hidden="true"></i></span>
            </div>
            <div style="margin-left: 10px; display: flex;">
                <div style="width: 50%;">
                    <span style="display: inline-block; width: 70px;">Plot :</span>&nbsp;&nbsp;<span>{{ $data->plot->type == 1 ? 'Residential' : 'Commercial' }}</span> <span>{{ $data->plot->name }}</span>
                </div>
                <div style="width: 50%;">
                    <span style="display: inline-block; width: 70px;">Size :</span>&nbsp;&nbsp; {{ $data->plot->size }} /M
                </div>
            </div>
            <div style="margin-left: 10px; display: flex;">
                <div style="width: 50%;">
                    <span style="display: inline-block; width: 70px;">Corner :</span>&nbsp;&nbsp;<span>{{ $data->plot->is_corner == 1 ? 'Yes' : 'No' }}</span>
                </div>
                <div style="width: 50%;">
                    <span style="display: inline-block; width: 70px;">Park Face:</span>&nbsp;&nbsp; {{ $data->plot->facing_id == 2 ? 'Yes' : 'No' }}
                </div>
            </div>
        </td>
    </tr>
    </tbody>
</table>

<table style="width: 100%; border-collapse: collapse; margin-top: 4px;">
    <tbody>
    <tr>
        <td colspan="3" style="padding:4px 12px; width:40%; vertical-align:top">
            <h2 style="background-color: rgb(1, 75, 148); color: white; padding:0px 10px; margin-bottom:0">Sale Details</h2>
            <div style="margin-top: 10px; margin-left: 10px">
                <span style="display: inline-block; width: 100px;"><b> Rate</b></span>&nbsp;&nbsp;<span><b>{{ $data->plot->size }}/M @ {{ Setting::formatAmount($data->plot_rate) }}</b></span>
            </div>
            <div style="margin-left: 10px">
                <span style="display: inline-block; width: 100px;">Plot Value:</span>&nbsp;&nbsp;<span>{{ Setting::formatAmount($data->total_price + $data->dicount_value) }}<i class="fa fa-address-book" aria-hidden="true"></i></span>
            </div>
            <div style="margin-left: 10px">
                <span style="display: inline-block; width: 100px;">Discount:</span>&nbsp;&nbsp;<span>{{ Setting::formatAmount($data->dicount_value) }}<i class="fa fa-address-book" aria-hidden="true"></i></span>
            </div>
            <div style="margin-left: 10px">
                <span style="display: inline-block; width: 100px;">Sale Value:</span>&nbsp;&nbsp;<span>{{ Setting::formatAmount($data->total_price) }}<i class="fa fa-address-book" aria-hidden="true"></i></span>
            </div>
        </td>
    </tr>
    </tbody>
</table>

<table dir="@if(Config::get('app.locale') == 'ar'){{'rtl'}}@endif" style="width: 100%; border-collapse: collapse;">
    <tr class="table-header" style="background-color: rgb(1, 75, 148); color: white;">
        <td style="border:1px dotted #222; padding:1px 3px; width:15%; text-align:center">Date</td>
        <td style="border:1px dotted #222; padding:1px 3px; width:35%; text-align:center">Description</td>
        <td style="border:1px dotted #222; padding:1px 3px; width:9%; text-align:center">Payable</td>
        <td style="border:1px dotted #222; padding:1px 3px; width:9%; text-align:center">Received</td>
        <td style="border:1px dotted #222; padding:1px 3px; width:9%; text-align:center">Balance</td>
    </tr>
    @php
        $balance = 0;
        $total_amount_in = 0;
        $total_amount_out = 0;
    @endphp
    @foreach ($customer_ledger_record as $record)
        @php
            $amount_in = $record['amount_in'];
            $amount_out = $record['amount_out'];
            $balance += $amount_in - $amount_out;
            $total_amount_in += $amount_in;
            $total_amount_out += $amount_out;
        @endphp
        <tr>
            <td style="border:1px dotted #222; padding:1px 3px; text-align:center">{{ $record['date'] }}</td>
            <td style="border:1px dotted #222; padding:1px 3px; text-align:left">{{ $record['details'] }}</td>
            <td style="border:1px dotted #222; padding:1px 3px; text-align:center">{{ Setting::formatAmount($amount_in) }}</td>
            <td style="border:1px dotted #222; padding:1px 3px; text-align:center">{{ Setting::formatAmount($amount_out) }}</td>
            <td style="border:1px dotted #222; padding:1px 3px; text-align:center">{{ Setting::formatAmount($balance) }}</td>
        </tr>
    @endforeach
    <tr class="table-header" style="background-color: rgb(1, 75, 148); color: white;">
        <td colspan="2" style="border:1px dotted #222; padding:1px 3px; text-align:center"><b>Total</b></td>
        <td style="border:1px dotted #222; padding:1px 3px; text-align:center"><b>{{ Setting::formatAmount($total_amount_in) }}</b></td>
        <td style="border:1px dotted #222; padding:1px 3px; text-align:center"><b>{{ Setting::formatAmount($total_amount_out) }}</b></td>
        <td style="border:1px dotted #222; padding:1px 3px; text-align:center"><b>{{ Setting::formatAmount($balance) }}</b></td>
    </tr>
</table>

<script type="text/javascript">
    localStorage.clear();
    function auto_print() {
        window.print();
    }
    setTimeout(auto_print, 1000);
</script>
</body>
</html>
