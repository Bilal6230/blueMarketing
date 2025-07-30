<!DOCTYPE html>
<html>
    <head>
        <link rel="icon" type="image/png" href="{{url('public/logo', )}}" />
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
                @page {
                    margin-top: 1.5in;
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
        <table style="width: 100%;border-collapse: collapse; margin-top:4px">
            <tr>
                <td colspan="2" style="padding:0px 0;width:40%; font-size:12px">
                    <h1 style="margin:0">{{ $title }}</h1>
                    
                   
                   
                </td>
                
                <td style="padding:5px -19px;width:10%;text-align:right;">
                    
                    <div style="display: flex;justify-content: space-between;border-bottom:1px solid #aaa">
                        <span><b>Print Date:</b></span> {{ $today }} <span></span>
                    </div>
                    <div style="display: flex;justify-content: space-between;border-bottom:1px solid #aaa">
                        <span><b>Booking:</b></span> BO-{{ get_new_booking_number($data->id) }} <span></span>
                    </div>
                    
                    
                  
                    
                </td>
            </tr>
        </table>

        <table style="width: 100%;border-collapse: collapse; margin-top: 4px;">
            <tbody>
            <tr>
                <td colspan="3" style="padding:2px 12px;width:40%;vertical-align:top">
                    <h2 style="background-color: rgb(1, 75, 148); color: white; padding:0px 10px; margin-bottom:0">Customer Details</h2>
                    <div style="margin-top: 10px;margin-left: 10px">
                        <span style="display: inline-block; width: 70px;" ><b> Name</b></span> <span><b> {{ $data->customer->first_name }} {{ $data->customer->last_name }} {{ $data->customer->relate }}  {{ $data->customer->father_name }} </b></span>
                    </div>
                    
                    <div style="margin-left: 10px">
                        <span style="display: inline-block; width: 70px;" >CNIC:</span>&nbsp;&nbsp;<span> {{ $data->customer->nic_number }}<i class="fa fa-address-book" aria-hidden="true"></i></span>
                    </div>
                    <div style="margin-left: 10px">
                        <span style="display: inline-block; width: 70px;" >Phone:</span>&nbsp;&nbsp;<span> {{ $data->customer->phone_number }}<i class="fa fa-address-book" aria-hidden="true"></i></span>
                    </div>
                    <div style="margin-bottom: 10px;margin-left: 10px">
                        <span style="display: inline-block; width: 70px;" >Address:</span>&nbsp;&nbsp;<span> {{ $data->customer->home_address }} </span>
                    </div>
                </td>
                <td colspan="2" style="padding:2px 12px;width:40%;vertical-align:top">
                    <h2 style="background-color: rgb(1, 75, 148); color: white; padding:0px 10px; margin-bottom:0">Plot Details</h2>

                    <div style="margin-top: 10px;margin-left: 10px">
                        <span style="display: inline-block; width: 70px;" ><b> Project</b></span> &nbsp;<span><b>{{ $data->project->project }}</b></span>
                    </div>

                    <div style="margin-left: 10px">
                        <span style="display: inline-block; width: 70px;" >City :</span>&nbsp;&nbsp;<span>{{ $data->project->address }}<i class="fa fa-address-book" aria-hidden="true"></i></span>
                    </div>


                    <div style="margin-left: 10px; display: flex;">
                        <div style="width: 50%;">
                            <span style="display: inline-block; width: 70px;" >Plot :</span>&nbsp;&nbsp;<span>{{ $data->plot->type == 1 ? 'Residential' : 'Commercial' }}</span> <span>{{ $data->plot->name }}</span>
                        </div>
                        <div style="width: 50%;">
                            <span style="display: inline-block; width: 70px;" >Size :</span>&nbsp;&nbsp; {{ $data->plot->size }} /M
                        </div>
                        
                    </div>
                    <div style="margin-left: 10px; display: flex;">
                        <div style="width: 50%;">
                            <span style="display: inline-block; width: 70px;" >Corner :</span>&nbsp;&nbsp;<span>{{ $data->plot->is_corner == 1 ? 'Yes' : 'No' }}</span>
                        </div>
                        <div style="width: 50%;">
                            <span style="display: inline-block; width: 70px;" >Park Face:</span>&nbsp;&nbsp; {{ $data->plot->facing_id == 2 ? 'Yes' : 'No' }} 
                        </div>
                        
                    </div>

                    
                </td>
            </tr>
        </tbody></table>

        <table style="width: 100%;border-collapse: collapse; margin-top: 4px;">
            <tbody>
                <tr>
                    <td colspan="3" style="padding:2px 12px;width:40%;vertical-align:top">
                        <h2 style="background-color: rgb(1, 75, 148); color: white; padding:0px 10px; margin-bottom:0">Sale Details</h2>
                        <div style="margin-top: 10px;margin-left: 10px">
                            <span style="display: inline-block; margin-right:100px;">

                                <span style="display: inline-block; " ><b> Rate</b></span>&nbsp;&nbsp;<span><b>{{ $data->plot->size }}/M @ {{ Setting::formatAmount($data->plot_rate)  }} </b></span>
                            </span>
                            <span style="display: inline-block; margin-right:100px;">
                                <span style="display: inline-block;  " >Plot Value:</span>&nbsp;&nbsp;<span>{{ Setting::formatAmount($data->total_price + $data->dicount_value) }}<i class="fa fa-address-book" aria-hidden="true"></i></span>
                            </span>
                            <span style="display: inline-block; margin-right:170px;">
                            
                                <span style="display: inline-block; " >Discount:</span>&nbsp;&nbsp;<span>{{ Setting::formatAmount($data->dicount_value)  }}<i class="fa fa-address-book" aria-hidden="true"></i></span>
                            </span>
                            <span style="display: inline-block; margin-right:100px;">
                                <span style="display: inline-block; " >Sale Value:</span>&nbsp;&nbsp;<span>{{ Setting::formatAmount($data->total_price)  }}<i class="fa fa-address-book" aria-hidden="true"></i></span>
                            </span>

                        </div>
                        
                        <div style="margin-left: 10px">
                        </div>
                        <div style="margin-left: 10px">
                        </div>
                        <div style="margin-left: 10px">
                        </div>
                        
                    </td>
                    
                </tr>
            </tbody>
        </table>

        <div style="display: flex;">
            <div style="width: 50%;">
                <table dir="@if( Config::get('app.locale') == 'ar' ){{'rtl'}}@endif" style="width: 100%;border-collapse: collapse;">
                    <tr class="table-header" style="background-color: rgb(1, 75, 148); color: white;">
                        <td style="border:1px dotted #222;padding:1px 3px;width:25%;text-align:center">Date</td>
                        <td style="border:1px dotted #222;padding:1px 3px;width:24%;text-align:center">Description</td>
                        <td style="border:1px dotted #222;padding:1px 3px;width:6%;text-align:center">Amount</td>
                    </tr>
                    @php
                        $grandTotalGroup1 = 0;
                        $halfCount = count($data->bookingDetails) / 2;
                    @endphp
                
                    @foreach ($data->bookingDetails as $key => $d)
                        @if($key < $halfCount)
                            <tr>
                                <td style="@if( Config::get('app.locale') == 'ar' ){{'border-right:1px dotted #222;'}}@endif border:1px dotted #222;padding:1px 3px;text-align: center;">{{ formatDateExperience4($d->due_date) }}</td>
                                <td style="border:1px dotted #222;padding:1px 3px;font-size: 10px;line-height: 1.2;">{{ ucfirst($d->installment_details) }}</td>
                                <td style="border:1px dotted #222;padding:1px 3px;text-align:center;font-size: 10px;">{{ Setting::roundformatAmount($d->amount) }}</td>
                            </tr>
                            @php
                                $grandTotalGroup1 += $d->amount;
                            @endphp
                        @endif
                    @endforeach
                    <tr>
                        <td colspan="2"></td>
                        <td><b>{{ Setting::roundformatAmount($grandTotalGroup1) }}</b></td>
                    </tr>
                </table>
            </div>
            <div style="width: 50%;">
                <table dir="@if( Config::get('app.locale') == 'ar' ){{'rtl'}}@endif" style="width: 100%;border-collapse: collapse;">
                    <tr class="table-header" style="background-color: rgb(1, 75, 148); color: white;">
                        <td style="border:1px dotted #222;padding:1px 3px;width:25%;text-align:center">Date</td>
                        <td style="border:1px dotted #222;padding:1px 3px;width:24%;text-align:center">Description</td>
                        <td style="border:1px dotted #222;padding:1px 3px;width:6%;text-align:center">Amount</td>
                    </tr>
                    @php
                        $grandTotalGroup2 = 0;
                    @endphp
                
                    @foreach ($data->bookingDetails as $key => $d)
                        @if($key >= $halfCount)
                            <tr>
                                <td style="@if( Config::get('app.locale') == 'ar' ){{'border-right:1px dotted #222;'}}@endif border:1px dotted #222;padding:1px 3px;text-align: center;">{{ formatDateExperience4($d->due_date) }}</td>
                                <td style="border:1px dotted #222;padding:1px 3px;font-size: 10px;line-height: 1.2;">{{ ucfirst($d->installment_details) }}</td>
                                <td style="border:1px dotted #222;padding:1px 3px;text-align:center;font-size: 10px;">{{ Setting::roundformatAmount($d->amount) }}</td>
                            </tr>
                            @php
                                $grandTotalGroup2 += $d->amount;
                            @endphp
                        @endif
                    @endforeach
                    <tr>
                        <td colspan="2"></td>
                        <td><b>{{ Setting::roundformatAmount($grandTotalGroup2) }}</b></td>
                    </tr>
                </table>
            </div>
        </div>

        <div style="margin-top: 20px;">
            <div style="display: flex; justify-content: space-between;">
                <div style="width: 40%;">
                    <div style="border-top: 1px solid #000; margin-top: 20px;">
                        Customer Signature
                    </div>
                </div>
                <div style="width: 40%; text-align: right;">
                    <div style="border-top: 1px solid #000; margin-top: 20px;">
                        Project Managment Signature
                    </div>
                </div>
            </div>
        </div>
        
        
        
        
        <script type="text/javascript">
            localStorage.clear();
            function auto_print() {
                window.print();

            }
            setTimeout(auto_print, 1000);
        </script>
    </body>
</html>
