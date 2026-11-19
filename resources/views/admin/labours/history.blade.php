@extends('admin.layouts.master')

@section('content')
    {{-- PAGE STYLES --}}
    <style>
        .labour-history-content-wrapper {
            min-height: auto;
            background: transparent;
        }

        .labour-history-wrap {
            width: 100%;
            max-width: none;
            margin: 0;
            padding: 16px 12px 20px;
            box-sizing: border-box;
        }

        .page-head h2 {
            margin: 0;
        }

        .muted {
            color: #777;
        }

        .grid-4 {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .card {
            background: #fff;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, .06);
            margin-bottom: 20px;
        }

        .stat span {
            color: #777;
            font-size: 14px;
        }

        .stat b {
            font-size: 24px;
        }

        .stat.danger b {
            color: #e63946;
        }

        .filter .row {
            display: flex;
            gap: 15px;
            align-items: end;
        }

        .filter label {
            font-size: 13px;
            color: #555;
        }

        .filter-btn {
            padding-bottom: 2px;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .table {
            width: 100%;
            min-width: 900px;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        .table th {
            background: #fafafa;
            text-align: left;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            color: #fff;
        }

        .badge.present {
            background: #2a9d8f
        }

        .badge.absent {
            background: #e63946
        }

        .badge.leave {
            background: #f4a261
        }

        .badge.holiday {
            background: #457b9d
        }

        .badge.not-marked {
            background: #999
        }

        .badge.paid {
            background: #2a9d8f
        }

        .badge.unpaid {
            background: #e63946
        }

        .empty {
            text-align: center;
            color: #999;
        }

        pre {
            margin: 0;
            font-size: 12px;
            background: #f6f6f6;
            padding: 6px;
            border-radius: 6px;
        }

        @media (max-width: 991.98px) {
            .grid-4 {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 575.98px) {
            .grid-4 {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="content-wrapper labour-history-content-wrapper">
        <section class="content pt-2 labour-history-page">
            <div class="container-fluid px-2 px-md-3">
                <div class="labour-history-wrap">
                    {{-- PAGE HEADER --}}
                    <div class="page-head">
                        <h2>{{ $labour->name }} Attendance History</h2>
                        <p class="muted">Attendance & Payment Records</p>
                    </div>

                    {{-- SUMMARY CARDS --}}
                    <div class="grid-4">
                        <div class="card stat">
                            <span>Total Days</span>
                            <b>{{ $summary['total_days'] ?? 0 }}</b>
                        </div>

                        <div class="card stat">
                            <span>Total Hours</span>
                            <b>{{ $summary['total_hours'] ?? 0 }}</b>
                        </div>

                        <div class="card stat">
                            <span>Total Earned</span>
                            <b>Rs {{ number_format($summary['total_amount'] ?? 0, 2) }}</b>
                        </div>

                        <div class="card stat danger">
                            <span>Unpaid Balance</span>
                            <b>Rs {{ number_format($summary['unpaid'] ?? 0, 2) }}</b>
                        </div>
                    </div>

                    <div class="card">
                      <h3>Cash Labour Payment Vouchers</h3>
                      <div class="table-wrap"><table class="table" id="labourCpPaymentsTable">
                        <thead><tr><th>CP Source ID</th><th>Date</th><th>Reference</th><th>Amount</th><th>Actions</th></tr></thead>
                        <tbody>
                        @forelse($labourPaymentSources as $payment)
                        <tr><td>{{ $payment->id }}</td><td>{{ $payment->date }}</td>
                          <td>{{ $payment->reference }}</td>
                          <td>{{ number_format((float)$payment->amount_out,2) }}</td>
                          <td>
                            @can('finance cp edit')
                            <button type="button" class="btn btn-sm btn-outline-primary labour-cp-edit"
                              data-source="{{ $payment->id }}">Edit</button>
                            @endcan
                            @can('finance cp delete')
                            <button type="button" class="btn btn-sm btn-outline-danger labour-cp-delete"
                              data-source="{{ $payment->id }}">Delete</button>
                            @endcan
                          </td></tr>
                        @empty
                        <tr><td colspan="5" class="empty">No active cash CP vouchers</td></tr>
                        @endforelse
                        </tbody>
                      </table></div>
                    </div>
                    {{-- ATTENDANCE HISTORY --}}
                    <div class="card">
                        <h3>Attendance History</h3>

                        <div class="table-wrap">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Site</th>
                                        <th>Status</th>
                                        <th>Hours</th>
                                        <th>OT</th>
                                        <th>Rate</th>
                                        <th>Amount</th>
                                        <th>Payment</th>
                                        <th>Running Balance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $runningBalance = 0; @endphp

                                    @forelse($labour->attendances as $att)
                                        @php
                                            $payment = $paymentsByDate[$att->date] ?? 0;
                                            $runningBalance += $att->amount - $payment;
                                        @endphp

                                        <tr>
                                            <td>{{ $att->date }}</td>
                                            <td>{{ $att->site->site_name ?? '-' }}</td>
                                            <td>
                                                <span class="badge {{ $att->status }}">
                                                    {{ ucfirst($att->status) }}
                                                </span>
                                            </td>
                                            <td>{{ $att->hours }}</td>
                                            <td>{{ $att->ot_hours }}</td>
                                            <td>{{ number_format($att->rate ?? 0, 2) }}</td>
                                            <td>Rs {{ number_format($att->amount, 2) }}</td>
                                            <td>Rs {{ number_format($payment, 2) }}</td>
                                            <td>Rs {{ number_format($runningBalance, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="empty">No attendance records found</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@section('js')
<div class="modal fade" id="labourCpEditModal" tabindex="-1" aria-labelledby="labourCpEditTitle">
  <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 id="labourCpEditTitle" class="modal-title">Edit Labour CP</h5>
      <button class="close" type="button" data-dismiss="modal">&times;</button></div>
    <form id="labourCpEditForm"><div class="modal-body">
      <div class="alert alert-info">Original voucher and worker detail IDs remain preserved.</div>
      <div class="form-group"><label>Date</label><input type="date" id="labourCpDate" class="form-control" required></div>
      <div class="form-group"><label>Reference</label><input id="labourCpReference" class="form-control" required maxlength="200"></div>
      <div class="form-group"><label>Narration</label><textarea id="labourCpNarration" class="form-control" maxlength="2000"></textarea></div>
      <table class="table"><thead><tr><th>Labour ID</th><th>Amount</th><th></th></tr></thead>
        <tbody id="labourCpMemberRows"></tbody></table>
      <button type="button" class="btn btn-outline-primary btn-sm" id="labourCpAddMember">Add Worker</button>
      <div id="labourCpEditError" class="alert alert-danger d-none mt-2" role="alert"></div>
    </div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
      <button class="btn btn-primary" type="submit" id="labourCpSave">Update Voucher</button></div></form>
  </div></div>
</div>
<div class="modal fade" id="labourCpDeleteModal" tabindex="-1" aria-labelledby="labourCpDeleteTitle">
 <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <div class="modal-header"><h5 id="labourCpDeleteTitle" class="modal-title">Delete Labour Payment</h5>
    <button class="close" type="button" data-dismiss="modal">&times;</button></div>
  <div class="modal-body">Remove this CP from active worker payments? Original source, members and ledger IDs stay preserved.
    <div class="alert alert-danger d-none" id="labourCpDeleteError" role="alert"></div>
  </div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-dismiss="modal">Cancel</button>
   <button class="btn btn-danger" type="button" id="labourCpDeleteConfirm">Delete Payment</button></div>
 </div></div>
</div>
<script>
$(function(){
    var base=@json(url('admin/labours/payment')),
        feedback=$.financeVoucherFeedback||{};
    var toast=function(type,text){
        if($.isFunction(feedback.toast))feedback.toast(type,text);
        else if(window.toastr&&$.isFunction(window.toastr[type]))window.toastr[type](text);
    };
    var error=function($target,xhr){
        var text=(xhr.responseJSON&&xhr.responseJSON.message)
            ||($.isFunction(feedback.error)?feedback.error(xhr,'Labour payment failed.'):'Labour payment failed.');
        $target.text(text).removeClass('d-none');toast('error',text);
    };
    var refreshHistory=function(){
        $.get(window.location.href).done(function(html){
            var $screen=$('<div>').append($.parseHTML(html,document,false));
            var $new=$screen.find('#labourCpPaymentsTable tbody');
            if($new.length)$('#labourCpPaymentsTable tbody').html($new.html());
            var $newStats=$screen.find('.labour-history-wrap .grid-4');
            if($newStats.length)$('.labour-history-wrap .grid-4').html($newStats.html());
        }).fail(function(){toast('error','Could not refresh payment list.');});
    };
    var addMember=function(row){
        row=row||{};
        var $tr=$('<tr>').attr('data-detail-id',row.detail_id||'');
        $('<td>').append($('<input type="number" class="form-control labour-cp-worker" min="1" required>')
            .val(row.labour_id||'')).appendTo($tr);
        $('<td>').append($('<input type="number" class="form-control labour-cp-amount" min="0.01" step="0.01" required>')
            .val(row.amount||'')).appendTo($tr);
        $('<td>').append($('<button type="button" class="btn btn-sm btn-outline-danger labour-cp-remove">Remove</button>'))
            .appendTo($tr);
        $('#labourCpMemberRows').append($tr);
    };
    $('#labourCpAddMember').on('click',function(){addMember();});
    $('#labourCpMemberRows').on('click','.labour-cp-remove',function(){
        if($('#labourCpMemberRows tr').length===1){toast('error','At least one worker required.');return;}
        $(this).closest('tr').remove();
    });
    $('#labourCpPaymentsTable').on('click','.labour-cp-edit',function(){
        var id=Number($(this).data('source')),$button=$(this);
        $button.prop('disabled',true);
        $.ajax({url:base+'/'+id,type:'GET',dataType:'json',headers:{Accept:'application/json'}})
          .done(function(response){
            var d=response.data||{};
            $('#labourCpEditForm').data('source',id);
            $('#labourCpDate').val(d.date);
            $('#labourCpReference').val(d.reference);
            $('#labourCpNarration').val(d.description);
            $('#labourCpMemberRows').empty();
            $.each(d.members||[],function(_,row){addMember(row);});
            $('#labourCpEditError').addClass('d-none');
            $('#labourCpEditModal').modal('show');
          }).fail(function(xhr){error($('#labourCpEditError'),xhr);})
          .always(function(){$button.prop('disabled',false);});
    });
    $('#labourCpEditForm').on('submit',function(event){
        event.preventDefault();
        var source=Number($(this).data('source')),$button=$('#labourCpSave'),rows=[];
        $('#labourCpMemberRows tr').each(function(){
            var $tr=$(this);
            rows.push({detail_id:$tr.attr('data-detail-id')||'',
                labour_id:$tr.find('.labour-cp-worker').val(),
                amount:$tr.find('.labour-cp-amount').val()});
        });
        $button.prop('disabled',true);
        $.ajax({url:base+'/'+source,type:'PUT',dataType:'json',
            headers:{Accept:'application/json','X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')},
            data:{date:$('#labourCpDate').val(),reference:$('#labourCpReference').val(),
                description:$('#labourCpNarration').val(),members:rows}})
         .done(function(response){$('#labourCpEditModal').modal('hide');
            toast('success',response.message);refreshHistory();})
         .fail(function(xhr){error($('#labourCpEditError'),xhr);})
         .always(function(){$button.prop('disabled',false);});
    });
    $('#labourCpPaymentsTable').on('click','.labour-cp-delete',function(){
        $('#labourCpDeleteConfirm').data('source',Number($(this).data('source')));
        $('#labourCpDeleteError').addClass('d-none');
        $('#labourCpDeleteModal').modal('show');
    });
    $('#labourCpDeleteConfirm').on('click',function(){
        var id=Number($(this).data('source')),$button=$(this);
        $button.prop('disabled',true);
        $.ajax({url:base+'/'+id,type:'DELETE',dataType:'json',
            headers:{Accept:'application/json','X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')}})
         .done(function(response){$('#labourCpDeleteModal').modal('hide');
            toast('success',response.message);refreshHistory();})
         .fail(function(xhr){error($('#labourCpDeleteError'),xhr);})
         .always(function(){$button.prop('disabled',false);});
    });
});
</script>
@endsection
