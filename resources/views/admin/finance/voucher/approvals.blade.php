@extends('admin.layouts.master')
@section('title','Voucher Approval Dashboard')
@section('content')
<style>
.vap-page{padding:12px;background:#f0f5fb;min-height:calc(100vh - 125px)}
.vap-heading{background:#e9f7ed;border-bottom:2px solid #168348;border-radius:7px;padding:12px 15px;margin-bottom:12px}
.vap-heading h4{font-size:21px;color:#087840;font-weight:700;margin:0}
.vap-card{border:1px solid #c2e3ce;border-top:3px solid #168348;border-radius:10px;background:#fff;margin-bottom:12px;overflow:hidden}
.vap-card-head{padding:10px 13px;border-bottom:1px solid #dcebe1;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px}
.vap-table-wrap{max-height:46vh;overflow:auto}
.vap-table{margin:0}
.vap-table thead th{background:#e6f4eb;position:sticky;top:0;z-index:1;border-bottom:2px solid #168348;white-space:nowrap}
.vap-table td{vertical-align:middle;white-space:nowrap}
.vap-table tbody tr:nth-child(even){background:#f6fbf8}
.vap-card-footer{padding:9px 13px;border-top:1px solid #edf2ee}
.vap-actions{display:flex;gap:5px;align-items:center}
.vap-modal .modal-header{background:#e9f7ed;border-bottom:2px solid #168348}
.vap-modal .modal-title{color:#087840;font-weight:700}
.vap-event{font-size:12px;padding:4px 7px;border-left:2px solid #abd9bb;margin:3px 0;background:#f7faf8;white-space:normal}
.vap-status{display:inline-block;min-width:65px;border-radius:4px;text-align:center;padding:3px 7px;font-size:11px;font-weight:700}
.vap-status.pending{background:#fef3d3;color:#775400}
.vap-status.approved{background:#dcf4e5;color:#076235}
.vap-status.rejected{background:#fee5e7;color:#9c1c2d}
.vap-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px 18px}
.vap-detail-grid>div{display:flex;gap:8px;min-width:0;font-size:13px;border-bottom:1px solid #edf1f4;padding:5px 0}
.vap-detail-grid strong{min-width:130px}.vap-detail-grid span{overflow-wrap:anywhere}
.vap-detail-wide{grid-column:1/-1}
.vap-lines th{background:#eaf4fc}
.vap-voucher-no{white-space:nowrap;font-weight:700;color:#087840}
@media(max-width:650px){.vap-detail-grid{grid-template-columns:1fr}}
.vap-error{color:#c52a39;font-size:12px}
.vap-alert{position:fixed;top:78px;right:20px;max-width:420px;z-index:2200;display:none}
.swal2-container{z-index:20000!important}
</style>
<div class="content-wrapper vap-page">
 <div class="container-fluid">
  <div class="vap-heading d-flex justify-content-between align-items-center flex-wrap">
   <div><h4>Voucher Approval Dashboard</h4><small class="text-muted">Project #{{ (int)getSelectedTown() }} · CR, CP &amp; JV approvals · {{ \App\Services\Finance\VoucherApproverAccess::isSuperAdmin(auth()->user()) ? 'Super Admin: all project requests' : 'Assigned requests only' }}</small></div>
   <a href="{{ route('finance.voucher-settings.index') }}" class="btn btn-outline-success btn-sm"><i class="fas fa-cog mr-1"></i> Voucher Settings</a>
  </div>
  <div class="alert alert-info small mb-3">
   New vouchers with approval ON remain inactive until the assigned approver or a Super Admin explicitly approves.
   Rejected vouchers remain inactive. Existing voucher posting checks, project isolation and approval history are retained.
  </div>
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

  <div class="vap-card">
   <div class="vap-card-head"><strong>Pending Approval Queue</strong>
    <div><span class="badge badge-warning mr-2">{{ $queue->total() }} Pending</span>
     <a class="btn btn-sm btn-outline-secondary" href="{{ route('finance.daily-vouchers.approvals.index') }}">Refresh</a>
    </div>
   </div>
   <div class="table-responsive vap-table-wrap">
    <table class="table table-sm table-bordered table-hover vap-table">
     <thead><tr><th>#</th><th>Submitted</th><th>Voucher No</th><th>Submitted By</th><th>Assigned Approver</th><th>Status</th><th>Reason for Revision / Edit</th><th>Actions</th></tr></thead>
     <tbody>
     @forelse($queue as $item)
      <tr id="vap-row-{{ $item->id }}">
       <td>{{ $item->id }}</td>
       <td>{{ $item->created_at?->format('d-M-Y H:i') }}</td>
       <td class="vap-voucher-no">{{ $voucherDetails[$item->id]['number'] ?? '—' }}</td>
       <td>{{ $item->submitter?->name ?? 'User #'.$item->submitted_by }}</td>
       <td>{{ $item->assignedApprover?->name ?? 'User #'.$item->assigned_approver_id }}</td>
       <td><span class="vap-status pending">Pending</span></td>
       @php
          $revisionEvent = $item->events->reverse()->first(fn($event) => in_array($event->event, ['revision_from_approved','revision_from_rejected','resubmitted','legacy_cash_correction','same_source_edit'], true));
          $revisionData = $revisionEvent ? json_decode((string)$revisionEvent->remarks, true) : null;
          $revisionReason = is_array($revisionData) ? ($revisionData['revision_reason'] ?? null) : null;
       @endphp
       <td style="min-width:180px;max-width:330px;white-space:normal">{{ $revisionReason ?: '—' }}</td>
       <td>
        <div class="vap-actions">
         @if(\App\Services\Finance\VoucherApproverAccess::mayDecide(auth()->user(),(int)$item->project_id,(int)$item->assigned_approver_id,(int)$item->submitted_by))
          <button type="button" class="btn btn-sm btn-outline-success vap-decision"
           data-url="{{ route('finance.daily-vouchers.approvals.decide',[$item,'approve']) }}"
           data-action="approve" data-id="{{ $item->id }}" data-type="{{ $item->voucher_type }}"
           data-source="{{ $voucherDetails[$item->id]['number'] ?? '—' }}">Approve</button>
          <button type="button" class="btn btn-sm btn-outline-danger vap-decision"
           data-url="{{ route('finance.daily-vouchers.approvals.decide',[$item,'reject']) }}"
           data-action="reject" data-id="{{ $item->id }}" data-type="{{ $item->voucher_type }}"
           data-source="{{ $voucherDetails[$item->id]['number'] ?? '—' }}">Reject</button>
         @else <small class="text-muted">Assigned approver only</small>@endif
         <button type="button" class="btn btn-sm btn-outline-secondary vap-history" data-target="#vap-events-{{ $item->id }}">Details / History</button>
        </div>
        <div id="vap-events-{{ $item->id }}" class="d-none">
         @include('admin.finance.voucher.partials.approval_detail', ['item'=>$item])
        </div>
       </td>
      </tr>
     @empty
      <tr><td colspan="8" class="text-center text-muted py-4">No pending voucher approvals.</td></tr>
     @endforelse
     </tbody>
    </table>
   </div>
   <div class="vap-card-footer">{{ $queue->links() }}</div>
  </div>

  <div class="vap-card">
   <div class="vap-card-head"><strong>Recent Approval History</strong><span class="text-muted small">Last 50 decisions · Read-only</span></div>
   <div class="table-responsive vap-table-wrap"><table class="table table-sm table-bordered table-hover vap-table">
    <thead><tr><th>Decided At</th><th>Voucher No</th><th>Submitted By</th><th>Decision</th><th>Assigned Approver</th><th>Rejection Reason</th><th>Revision / Edit Reason</th><th>Audit</th></tr></thead>
    <tbody>
     @forelse($history as $item)
      <tr>
       <td>{{ $item->decided_at?->format('d-M-Y H:i:s') }}</td>
       <td class="vap-voucher-no">{{ $voucherDetails[$item->id]['number'] ?? '—' }}</td>
       <td>{{ $item->submitter?->name ?? 'User #'.$item->submitted_by }}</td>
       <td><span class="vap-status {{ $item->status }}">{{ ucfirst($item->status) }}</span></td>
       <td>{{ $item->assignedApprover?->name ?? 'User #'.$item->assigned_approver_id }}</td>
       <td style="max-width:280px;white-space:normal">{{ $item->rejection_reason ?: '—' }}</td>
       @php
          $historyEvent = $item->events->first(fn($event) => in_array($event->event, ['revision_from_approved','revision_from_rejected','resubmitted','legacy_cash_correction','same_source_edit'], true));
          $historyData = $historyEvent ? json_decode((string)$historyEvent->remarks, true) : null;
       @endphp
       <td style="max-width:280px;white-space:normal">{{ is_array($historyData) ? ($historyData['revision_reason'] ?? '—') : '—' }}</td>
       <td>
        <button type="button" class="btn btn-outline-secondary btn-sm vap-history" data-target="#vap-events-{{ $item->id }}">Details / History</button>
        <div id="vap-events-{{ $item->id }}" class="d-none">
         @include('admin.finance.voucher.partials.approval_detail', ['item'=>$item])
        </div>
       </td>
      </tr>
     @empty
      <tr><td colspan="8" class="text-muted text-center py-4">No completed decisions yet.</td></tr>
     @endforelse
    </tbody>
   </table></div>
  </div>
 </div>
</div>
<div class="modal fade vap-modal" id="vap-decision-modal" tabindex="-1" aria-hidden="true" role="dialog">
 <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="vap-decision-title">Approval Decision</h5>
   <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span>&times;</span></button></div>
  <form id="vap-decision-form">@csrf
   <div class="modal-body">
    <p class="mb-2" id="vap-source"></p>
    <div id="vap-decision-note" class="alert alert-warning small"></div>
    <div class="form-group" id="vap-reason-group">
     <label for="vap-reason">Rejection Reason *</label>
     <textarea class="form-control" id="vap-reason" name="reason" rows="3" maxlength="1000" placeholder="Minimum 2 characters"></textarea>
     <small class="vap-error" id="vap-reason-error" role="alert"></small>
    </div>
    <small class="text-muted">Only the assigned approver can act. Decisions and actor/time are recorded in audit history.</small>
   </div>
   <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
    <button type="submit" class="btn btn-success" id="vap-submit">Confirm Decision</button></div>
  </form>
 </div></div>
</div>
<div class="modal fade vap-modal" id="vap-history-modal" tabindex="-1" aria-hidden="true" role="dialog">
 <div class="modal-dialog modal-dialog-centered modal-xl" role="document"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Voucher Details &amp; Approval History</h5><button class="close" type="button" data-dismiss="modal" aria-label="Close"><span>&times;</span></button></div>
  <div class="modal-body"><div id="vap-history-content"></div></div>
  <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-dismiss="modal">Close</button></div>
 </div></div>
</div>
<div class="alert vap-alert" id="vap-alert" role="status"></div>
@endsection
@push('script')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function(){
 'use strict';
 var selected=null,busy=false;
 function toast(msg,failed){
  if(window.toastr&&$.isFunction(window.toastr.error)){
   if(failed)window.toastr.error(msg);else window.toastr.success(msg);
  }else $('#vap-alert').removeClass('alert-success alert-danger')
   .addClass(failed?'alert-danger':'alert-success').text(msg)
   .stop(true,true).fadeIn(100).delay(3500).fadeOut(200);
 }
 $('.vap-history').on('click',function(){
  var $template=$($(this).attr('data-target'));
  var $content=$('#vap-history-content').empty();
  if(!$template.length||!$template.children().length){
   $('<p>').addClass('text-muted').text('No history available.').appendTo($content);
  }else $template.children().clone().removeClass('d-none').appendTo($content);
  $('#vap-history-modal').modal('show');
 });
 $('.vap-decision').on('click',function(){
  var $b=$(this);
  selected={url:$b.attr('data-url'),type:$b.attr('data-type'),
   action:$b.attr('data-action'),source:$b.attr('data-source'),id:$b.attr('data-id')};
  $('#vap-source').text(selected.source);
  $('#vap-reason-error').text('');
  $('#vap-reason').val('');
  $('#vap-reason-group').toggle(selected.action==='reject');
  $('#vap-decision-title').text(selected.action==='approve'?'Approve Voucher':'Reject Voucher');
  $('#vap-decision-note').text(selected.action==='approve'
   ?'Approval will activate the existing guarded voucher posting. This action cannot be repeated.'
   :'Rejecting will keep the voucher posting inactive. A reason is mandatory.');
  $('#vap-submit').toggleClass('btn-success',selected.action==='approve')
   .toggleClass('btn-danger',selected.action==='reject').text(selected.action==='approve'?'Approve & Post':'Reject Voucher');
  $('#vap-decision-modal').modal('show');
 });
 $('#vap-decision-form').on('submit',function(e){
  e.preventDefault();if(busy||!selected)return;
  var reason=$.trim($('#vap-reason').val());
  if(selected.action==='reject'&&reason.length<2){
   $('#vap-reason-error').text('Provide a rejection reason with at least 2 characters.');return;
  }
  $('#vap-reason-error').text('');
  if(!window.Swal||!$.isFunction(window.Swal.fire)){toast('Confirmation dialog unavailable.',true);return;}
  window.Swal.fire({icon:'warning',
   title:selected.action==='approve'?'Confirm Voucher Approval?':'Confirm Voucher Rejection?',
   text:selected.action==='approve'
    ?'This will activate the selected voucher under the existing guarded approval workflow.'
    :'This will reject the voucher; posting remains inactive.',
   showCancelButton:true,confirmButtonText:selected.action==='approve'?'Yes, Approve':'Yes, Reject',
   cancelButtonText:'Cancel',confirmButtonColor:selected.action==='approve'?'#168348':'#bd293b'
  }).then(function(result){
   if(!result.isConfirmed||busy)return;
   busy=true;$('#vap-submit').prop('disabled',true);
   $.ajax({url:selected.url,method:'POST',data:{_token:$('#vap-decision-form [name=_token]').val(),reason:reason},
    dataType:'json',headers:{Accept:'application/json'}})
    .done(function(response){
     $('#vap-decision-modal').modal('hide');
     toast(response.message||'Voucher decision recorded.',false);
     window.Swal.fire({icon:'success',title:'Decision Recorded',text:response.message||'Saved.'})
      .then(function(){window.location.reload();});
    }).fail(function(xhr){
     var message=xhr.responseJSON&&xhr.responseJSON.message?xhr.responseJSON.message:'Unable to process voucher.';
     if(xhr.responseJSON&&xhr.responseJSON.errors)$.each(xhr.responseJSON.errors,function(_,errors){message=errors[0]||message;return false;});
     $('#vap-reason-error').text(message);toast(message,true);
     window.Swal.fire({icon:'error',title:'Decision Not Saved',text:message});
    }).always(function(){busy=false;$('#vap-submit').prop('disabled',false);});
  });
 });
});
</script>
@endpush
