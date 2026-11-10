@extends('admin.layouts.master')
@section('title', $title)
@section('content')
<style>
.jv-page{padding:8px 10px;background:#edf3ff;min-height:calc(100vh - 130px);display:flex;flex-direction:column;gap:8px}
.jv-head{background:#e8f0ff;border-bottom:2px solid #0673f9;border-radius:8px;display:flex;align-items:center;justify-content:space-between;padding:11px 14px;gap:10px}
.jv-head h4{margin:0;font-size:19px;font-weight:700;color:#0861d4}
.jv-head small{color:#6c7d91;font-size:12px}
.jv-filters,.jv-card{background:#fff;border:1px solid #c9d9f2;border-top:3px solid #0780ff;border-radius:10px;padding:11px}
.jv-filter-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr)) auto;align-items:end;gap:9px}
.jv-filter-grid label{font-size:11px;font-weight:700;margin:0 0 4px}
.jv-filter-grid .form-control{height:32px;font-size:12px}
.jv-filter-buttons{display:flex;gap:5px;align-items:center}
.jv-card{flex:1;min-height:240px;display:flex;flex-direction:column;padding:8px}
.jv-table-wrap{min-height:0;flex:1;overflow:auto}
.jv-table{width:100%!important;margin:0;font-size:12px}
.jv-table th{background:#e7efff!important;border-bottom:2px solid #087cff!important;font-weight:700;color:#192a46!important}
.jv-table td{vertical-align:middle}
.jv-table thead tr.jv-column-search th{top:35px!important;padding:4px!important;background:#e7efff!important}
.jv-table thead tr:first-child th{position:sticky;top:0;z-index:3}
.jv-table thead tr.jv-column-search th{position:sticky;z-index:2}
.jv-column-search input,.jv-column-search select{height:27px!important;min-height:27px!important;padding:2px 5px!important;font-size:11px!important;width:100%;background:#fff;box-sizing:border-box}
.jv-column-search select{appearance:auto!important;-webkit-appearance:menulist!important}
.jv-actions{display:flex;flex-wrap:nowrap;gap:4px;align-items:center}
.jv-actions .btn{padding:3px 7px;white-space:nowrap}
.jv-footer{border-top:1px solid #dde6f4;padding-top:8px}
.jv-footer .dataTables_info,.jv-footer .dataTables_paginate{font-size:12px}
.jv-card .dataTables_wrapper{display:flex;flex-direction:column;flex:1;min-height:0}
.jv-card .dataTables_wrapper>.row{margin-left:0;margin-right:0}
.jv-card .dataTables_processing{z-index:5}
.jv-modal .modal-dialog{max-width:min(1100px,96vw);height:calc(100vh - 48px);margin:24px auto}
.jv-modal .modal-content{height:100%;overflow:hidden}
.jv-modal .modal-header{background:#e9f1ff;border-bottom:2px solid #0676ff;padding:10px 14px}
.jv-modal .modal-header .modal-title{font-size:17px;color:#0861d4;font-weight:700}
.jv-modal .modal-body{padding:0;min-height:0;position:relative}
.jv-modal iframe{width:100%;height:100%;border:0;display:block}
.jv-modal .jv-load{position:absolute;inset:0;background:#fff;z-index:2;display:flex;align-items:center;justify-content:center;gap:9px;color:#1764c3}
.jv-modal .jv-load.d-none{display:none!important}
.jv-modal .modal-footer{padding:7px 12px}
.jv-page .btn-primary{background:#087cfa;border-color:#087cfa}
.jv-page .badge-warning{background:#f5b51e;color:#231d12}
@media(max-width:1000px){.jv-filter-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.jv-filter-buttons{grid-column:1/-1}}
@media(max-width:650px){.jv-filter-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
<div class="content-wrapper jv-page">
 <div class="jv-head">
  <div><h4>{{ $title }}</h4><small>Selected project records · Approval-aware voucher register</small></div>
  @if($type==='JV')<button type="button" id="jv-add" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i> New Voucher</button>@endif
 </div>
 @if(session('success'))<div class="alert alert-success m-0" role="status">{{ session('success') }}</div>@endif
 @if($errors->any())<div class="alert alert-danger m-0">{{ $errors->first() }}</div>@endif
 <div class="jv-filters">
  <form id="jv-top-filters" class="jv-filter-grid" autocomplete="off">
   <div><label for="jv-from">Voucher Number</label><input type="search" class="form-control" id="jv-from" placeholder="Voucher #"></div>
   <div><label for="jv-date">Date</label><input type="date" class="form-control" id="jv-date"></div>
   <div><label for="jv-min">Amount Min</label><input type="number" step="0.01" min="0" class="form-control" id="jv-min" placeholder="Min amount"></div>
   <div><label for="jv-max">Amount Max</label><input type="number" step="0.01" min="0" class="form-control" id="jv-max" placeholder="Max amount"></div>
   <div><label for="jv-reference">Reference / Details</label><input type="search" class="form-control" id="jv-reference" placeholder="Reference"></div>
   <div class="jv-filter-buttons"><button class="btn btn-sm btn-primary" type="submit"><i class="fas fa-filter mr-1"></i> Apply</button><button class="btn btn-sm btn-outline-secondary" type="button" id="jv-reset">Reset</button></div>
  </form>
 </div>
 <div class="jv-card">
  <div class="jv-table-wrap">
   <table id="jv-directory" class="table table-sm table-bordered table-hover jv-table">
    <thead>
     <tr><th>ID</th><th>Voucher #</th><th>Reference</th><th>Date</th><th>Description</th><th class="text-right">Amount</th>@if($type==='JV')<th>Status</th>@endif<th>Actions</th></tr>
     <tr class="jv-column-search">
      <th><input type="search" data-col="0" class="form-control" placeholder="ID"></th>
      <th><input type="search" data-col="1" class="form-control" placeholder="Filter"></th>
      <th><input type="search" data-col="2" class="form-control" placeholder="Filter"></th>
      <th><input type="date" data-col="3" class="form-control" aria-label="Filter date"></th>
      <th><input type="search" data-col="4" class="form-control" placeholder="Filter"></th>
      <th><input type="number" step="0.01" min="0" data-col="5" class="form-control" placeholder="Amount"></th>
      @if($type==='JV')<th><select class="form-control" data-col="6"><option value="">All</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option><option value="posted">Posted</option></select></th>@endif
      <th></th>
     </tr>
    </thead><tbody></tbody>
   </table>
  </div>
 </div>
</div>
<div class="modal fade jv-modal" id="jv-entry" tabindex="-1" role="dialog" aria-hidden="true">
 <div class="modal-dialog" role="document"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="jv-entry-title">Journal Voucher</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button></div>
  <div class="modal-body"><div class="jv-load" id="jv-loading"><span class="spinner-border spinner-border-sm"></span> Loading voucher form...</div><iframe id="jv-entry-frame" title="Journal Voucher Form" src="about:blank"></iframe></div>
  <div class="modal-footer"><a id="jv-open-tab" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">Open in new tab</a><button class="btn btn-sm btn-secondary" type="button" data-dismiss="modal">Close</button></div>
 </div></div>
</div>
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <div class="modal-header bg-danger text-white"><h5 class="modal-title">Reverse Journal Voucher</h5><button class="close text-white" type="button" data-dismiss="modal"><span>&times;</span></button></div>
  <div class="modal-body">This reverses existing posted accounting entries without removing their history.</div>
  <div class="modal-footer">
   <form id="deleteForm" method="POST">@csrf @method('DELETE')
    <input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
    <label for="jv-reversal-date">Reversal Date *</label><input type="date" id="jv-reversal-date" name="effective_date" value="{{ now()->toDateString() }}" class="form-control mb-2" required>
    <label for="jv-reversal-reason">Reason *</label><input type="text" id="jv-reversal-reason" name="reason" maxlength="200" class="form-control mb-2" required>
    <button class="btn btn-danger btn-sm" type="submit">Confirm Reversal</button>
   </form><button class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancel</button>
  </div>
 </div></div>
</div>
@endsection
@section('js')
<script>
$(function(){
 'use strict';
 var $table=$('#jv-directory'),timer=null,table=null,entryUrl='';
 var baseUrl=@json($type==='SV'?route('sales.voucher.index'):route('journal.voucher.index'));
 var createUrl=@json(route('journal.voucher.create'));
 var canAct=@json($showActions);
 function esc(value){return $('<div>').text(value==null?'':String(value)).html();}
 function apply(){
  table.ajax.reload(null,true);
 }
 table=$table.DataTable({
  processing:true,serverSide:true,autoWidth:false,pageLength:25,
  order:[[0,'desc']],orderCellsTop:true,
  dom:'<"d-flex align-items-center justify-content-between mb-2 px-1"l f>rt<"d-flex align-items-center justify-content-between jv-footer"ip>',
  ajax:{url:baseUrl,type:'GET',data:function(req){
   req.filter_voucher_number=$('#jv-from').val();
   req.filter_date=$('#jv-date').val();
   req.filter_amount_min=$('#jv-min').val();
   req.filter_amount_max=$('#jv-max').val();
   req.filter_reference=$('#jv-reference').val();
  }},
  columns:[
   {data:'id',name:'id'},
   {data:'voucher_number',name:'voucher_number',render:$.fn.dataTable.render.text()},
   {data:'reference',name:'reference',render:$.fn.dataTable.render.text()},
   {data:'date',name:'date',render:$.fn.dataTable.render.text()},
   {data:'description',name:'description',render:$.fn.dataTable.render.text()},
   {data:'total_debit',name:'total_debit',className:'text-right',render:function(v){return Number(v||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});}},
   @if($type==='JV')
   {data:'approval_status',orderable:false,searchable:false,render:function(value,renderType,row){
    if(renderType!=='display')return value||'';
    var colors={pending:'warning',approved:'success',rejected:'danger',posted:'secondary'};
    var key=String(value||'posted').toLowerCase();
    var title=row.approval_approver?'Assigned to '+row.approval_approver:'';
    if(row.approval_reason)title+=' · '+row.approval_reason;
    return '<span class="badge badge-'+(colors[key]||'secondary')+'" title="'+esc(title)+'">'+esc(value||'Posted')+'</span>';
   }},
   @endif
   {data:'actions',orderable:false,searchable:false,render:function(html){return html||'';}}
  ],
  drawCallback:function(){
   $table.find('td .btn-group').removeClass('btn-group').addClass('jv-actions');
   $table.find('td .jv-actions .btn').each(function(){
    var $b=$(this);
    if($b.hasClass('btn-warning'))$b.removeClass('btn-warning').addClass('btn-outline-primary').attr('title','Edit voucher').html('<i class="fas fa-edit"></i>');
    if($b.hasClass('btn-danger'))$b.removeClass('btn-danger').addClass('btn-outline-danger').attr('title','Reverse voucher').html('<i class="fas fa-undo"></i>');
    if($b.hasClass('btn-info'))$b.removeClass('btn-info').addClass('btn-outline-primary').attr('title','Print voucher').html('<i class="fas fa-print"></i>');
   });
  },
  language:{emptyTable:'No vouchers found.',processing:'Loading vouchers...'}
 });
 $('#jv-top-filters').on('submit',function(e){e.preventDefault();apply();});
 $('#jv-reset').on('click',function(){
  $('#jv-top-filters')[0].reset();$table.find('.jv-column-search input,.jv-column-search select').val('');
  table.columns().search('');table.search('');apply();
 });
 $table.find('.jv-column-search').on('input change','input,select',function(){
  var $field=$(this),col=Number($field.attr('data-col'));
  clearTimeout(timer);
  var go=function(){table.column(col).search($field.val()).draw();};
  if($field.is('select,[type=date]'))go();else timer=setTimeout(go,280);
 });
 $table.find('.jv-column-search').on('click keydown','input,select',function(e){e.stopPropagation();});
 function openEntry(url,title){
  entryUrl=url;var separator=url.indexOf('?')>=0?'&':'?';
  $('#jv-entry-title').text(title);
  $('#jv-open-tab').attr('href',url);
  $('#jv-loading').removeClass('d-none');
  $('#jv-entry-frame').attr('src',url+separator+'embed=1');
  $('#jv-entry').modal('show');
 }
 $('#jv-add').on('click',function(){openEntry(createUrl,'Add New Journal Voucher');});
 $table.on('click','a[href*="/journal-voucher/"]',function(e){
  if($(this).attr('target')==='_blank')return;
  var url=$(this).attr('href');
  if(!url||url.indexOf('/edit')===-1)return;
  e.preventDefault();openEntry(url,'Edit Journal Voucher');
 });
 $('#jv-entry-frame').on('load',function(){
  if(!entryUrl)return;
  try{
   var $doc=$(this).contents(),$form=$doc.find('#journalForm');
   if($doc.find('.alert-success').length&&!$form.length){
    $('#jv-entry').modal('hide');table.ajax.reload(null,false);return;
   }
   // Native form redirects back to index after create. Do not leave an index inside the modal.
   if(!$form.length){
    var $success=$doc.find('.alert-success');
    if($success.length){$('#jv-entry').modal('hide');table.ajax.reload(null,false);return;}
    $('#jv-loading').html('<span class="text-danger">Form unavailable. Open in a new tab.</span>');return;
   }
   if($doc.find('.alert-success').length){
    $('#jv-entry').modal('hide');table.ajax.reload(null,false);return;
   }
   $('#jv-loading').addClass('d-none');
  }catch(ex){$('#jv-loading').html('<span class="text-danger">Form unavailable. Open in a new tab.</span>');}
 });
 $('#jv-entry').on('hidden.bs.modal',function(){entryUrl='';$('#jv-entry-frame').attr('src','about:blank');$('#jv-loading').removeClass('d-none').text('Loading voucher form...');});
 $table.on('click','.delete-btn',function(){var id=$(this).data('id');$('#deleteForm').attr('action',@json(route('journal.voucher.delete',':id')).replace(':id',id));$('#deleteModal').modal('show');});
});
</script>
@endsection
