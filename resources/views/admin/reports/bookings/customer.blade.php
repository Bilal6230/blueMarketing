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
                /* background-color:rgb(205, 218, 235) !important; */
                -webkit-print-color-adjust: exact;
            }
        }
        table,tr,td {font-family: sans-serif;border-collapse: collapse;}
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
            <td><a href="{{$url}}" class="btn btn-info"><i class="fa fa-arrow-left"></i> Back</a> </td>
            <td><button onclick="window.print();" class="btn btn-primary"><i class="dripicons-print"></i> Print</button></td>
        </tr>
    </table>
    <br>
</div>
<table style="width: 100%;border-collapse: collapse;">
    <tr>
        <td colspan="2" style="padding:0px 0;width:40%">
            <h1 style="margin:0">{{ $title }}</h1>
        </td>
        <td style="padding:5px -19px;width:30%;text-align:right;">
            <div style="display: flex;justify-content: space-between;border-bottom:1px solid #aaa">
                <span><b>Print Date:</b></span> <span> {{ $today }}</span>
            </div>
        </td>
    </tr>
</table>

@foreach ($data->unique('plot_id') as $plotData)
    

    <table style="width: 100%;border-collapse: collapse; margin-top: 4px;">
        <tbody><tr>
            <td colspan="3" style="padding:4px 0;width:70%;vertical-align:top">
                <h3 style="background-color: rgb(1, 75, 148); color: white; padding:0px 10px; margin-bottom:0">
                    {{ $plotData->customer_list->first_name }} {{ $plotData->customer_list->last_name }} {{ Setting::getPlotTypeShort($plotData->plot_list->type) }}-{{ $plotData->plot_list->name }}
                </h3>
            </td>
            <td colspan="4" style="width:60%">
            </td>
        </tr>
    </tbody></table>

    <table dir="@if( Config::get('app.locale') == 'ar' ){{'rtl'}}@endif" style="width: 100%;border-collapse: collapse;">
        <tr class="table-header" style="background-color: rgb(1, 75, 148); color: white;">
            <td style="border:1px dotted #222;padding:1px 3px;width:35%;text-align:center">Date</td>
            <td style="border:1px dotted #222;padding:1px 3px;width:15%;text-align:center">Description</td>
            <td style="border:1px dotted #222;padding:1px 3px;width:7%;text-align:center">CR</td>
            <td style="border:1px dotted #222;padding:1px 3px;width:7%;text-align:center">DR</td>
            <td style="border:1px dotted #222;padding:1px 3px;width:7%;text-align:center">Balance</td>
        </tr>
        <?php
            $balance = 0;
        ?>

        @foreach ($data->where('plot_id', $plotData->plot_id) as $d )
            <tr>
                <td style="border:1px dotted #222;padding:1px 3px;font-size: 10px;line-height: 1.2;">
                    {{ $d->date }}
                </td>
                <td style="border:1px dotted #222;padding:1px 3px;text-align:center;font-size: 10px;">{{ $d->reference }} {{ $d->description }} </td>
                <td style="border:1px dotted #222;padding:1px 3px;text-align:center;font-size: 10px;">{{ Setting::formatAmount($d->amount_in) }}</td>
                <td style="border:1px dotted #222;padding:1px 3px;width:7%;text-align:center">{{ Setting::formatAmount($d->amount_out)  }}</td>
                <td style="border:1px dotted #222;border-right:1px solid #222;padding:1px 3px;text-align:center;font-size: 10px;">
                @php
                    $balance += $d->amount_in - $d->amount_out;
                @endphp
                {{ Setting::roundformatAmount($balance)  }}
                </td>
            </tr>
        @endforeach
    </table>
@endforeach

<script type="text/javascript">
    localStorage.clear();
    function auto_print() {
        window.print();

    }
    setTimeout(auto_print, 1000);
</script>
</body>
</html>
