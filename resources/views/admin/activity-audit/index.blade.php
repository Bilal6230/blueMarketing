@extends('admin.layouts.master')
@section('title', 'Activity Audit')
@push('style')
<style>
.activity-audit-page{--dv-accent:#167d42;--dv-pale:#eaf8ef;background:#ecf7f0;min-height:calc(100vh - 100px);padding:10px 9px 18px;font-size:12px}
.activity-audit-page .audit-heading{border-bottom:2px solid var(--dv-accent);background:#e9f8ef;border-radius:9px;padding:15px 13px;margin-bottom:11px;display:flex;align-items:center;justify-content:space-between}
.activity-audit-page .audit-heading h2{font-size:16px;font-weight:750;color:var(--dv-accent);margin:0 0 4px}
.activity-audit-page .audit-heading small{color:#59796b}
.activity-audit-page .audit-register{border:1px solid #d8e5dd;border-top:3px solid var(--dv-accent);background:#fff;border-radius:10px;box-shadow:0 2px 13px #193a2910;overflow:hidden}
.activity-audit-page .dv-table-tools{padding:8px 12px;display:flex;align-items:center;justify-content:space-between;gap:15px;flex-wrap:wrap}
.activity-audit-page .dv-table-tools label{display:flex;align-items:center;gap:6px;margin:0;font-size:11px;font-weight:600}
.activity-audit-page .dv-table-tools select{width:66px}
.activity-audit-page .dv-table-tools input{width:195px}
.activity-audit-page .dv-table-tools .form-control{height:29px;font-size:11px}
.activity-audit-page .audit-table-wrap{max-height:610px;overflow:auto;position:relative}
.activity-audit-page table.audit-table{font-size:11px;width:100%;margin:0;white-space:nowrap}
.activity-audit-page .audit-table thead th{position:sticky;top:0;background:#e8f7ee;color:#27394b;border:1px solid #d8e4de;border-bottom:2px solid var(--dv-accent);padding:7px;vertical-align:middle;font-weight:750;z-index:1}
.activity-audit-page .audit-table td{border:1px solid #e0e8e3;padding:7px;vertical-align:middle;height:42px}
.activity-audit-page .audit-table tbody tr:nth-child(even){background:#f6fbf8}
.activity-audit-page .audit-table tbody tr:hover{background:#eaf7ee}
.activity-audit-page .dv-excel-trigger{float:right;background:transparent;border:1px solid transparent;color:var(--dv-accent);border-radius:4px;min-width:22px;height:22px}
.activity-audit-page .dv-excel-trigger:hover,.activity-audit-page .dv-excel-trigger[aria-expanded=true],.activity-audit-page .dv-excel-trigger.dv-has-filter{border-color:var(--dv-accent);background:white}
.activity-audit-page .dv-pagination-footer{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:7px 12px;flex-wrap:wrap;border-top:1px solid #dbe9de}
.activity-audit-page #dv-pages{display:flex;gap:4px;align-items:center;flex-wrap:wrap}
.activity-audit-page .dv-page-btn{border:1px solid var(--dv-accent);color:var(--dv-accent);background:white;border-radius:5px;min-width:30px;height:29px;font-size:11px}
.activity-audit-page .dv-page-btn.active{background:var(--dv-accent);color:white}
.activity-audit-page .dv-page-btn:disabled{opacity:.4}
.activity-audit-page .dv-page-ellipsis{color:#76867b}
.activity-audit-page .audit-view{background:white;border:1px solid #a7d5b8;color:var(--dv-accent);border-radius:6px;min-width:56px;padding:5px 8px;font-weight:700}
.activity-audit-page .audit-view:hover{background:#e4f5eb}
.activity-audit-page .audit-diff{width:100%;font-size:11px}
.activity-audit-page .audit-diff th,.activity-audit-page .audit-diff td{border:1px solid #d9e8de;padding:6px;word-break:break-word;white-space:normal}
.activity-audit-page .audit-diff th{background:#eaf8ef}
.activity-audit-page .audit-impact{border:1px solid #dce8e1;border-radius:7px;padding:10px;margin:8px 0}
.activity-audit-page .audit-sql-note{padding:9px;background:#fff9e8;border:1px solid #f0e2b1;border-radius:6px}
.dv-excel-popover{position:fixed;z-index:1100;width:288px;max-width:calc(100vw - 20px);background:#fff;border:1px solid #c9d3de;border-radius:8px;box-shadow:0 14px 38px #0d223838;padding:10px;color:#273549;font-size:12px}
.dv-excel-popover.d-none{display:none!important}
.dv-excel-popover .dv-excel-title{font-weight:700;font-size:12px;margin-bottom:7px}
.dv-excel-popover .dv-excel-sort{width:100%;display:block;border:0;background:white;text-align:left;padding:8px;border-radius:4px;color:#273549}
.dv-excel-popover .dv-excel-sort:hover{background:#eef6f1}
.dv-excel-popover .dv-excel-divider{border-top:1px solid #e1e7ed;margin:8px 0}
.dv-excel-popover .dv-excel-values{height:190px;overflow:auto;border:1px solid #dde4ec;border-radius:5px;margin-top:7px;padding:5px}
.dv-excel-popover .dv-excel-choice{display:flex;gap:7px;align-items:center;margin:0;padding:5px 3px}
.dv-excel-popover .dv-excel-choice input{flex:0 0 auto}
.dv-excel-popover .dv-excel-footer{display:flex;gap:6px;justify-content:flex-end;margin-top:10px}
.dv-excel-popover .dv-excel-date-label{display:block;font-size:11px;font-weight:600;margin:8px 0 4px}
.dv-excel-popover.dv-date-mode #dv-excel-values,.dv-excel-popover.dv-date-mode #dv-excel-search-area{display:none}
.dv-excel-popover.dv-date-mode #dv-excel-dates{display:block!important}
.dv-excel-popover.dv-checkbox-mode #dv-excel-dates,.dv-excel-popover.dv-search-mode #dv-excel-dates{display:none!important}
.dv-excel-popover.dv-search-mode #dv-excel-values{display:none}
.dv-excel-popover.dv-date-mode #dv-excel-apply,.dv-excel-popover.dv-date-mode #dv-excel-cancel,.dv-excel-popover.dv-search-mode #dv-excel-apply,.dv-excel-popover.dv-search-mode #dv-excel-cancel{display:none}
.dv-excel-popover .btn-primary{background:#167d42;border-color:#167d42}
.activity-audit-page .bm-skeleton-row td{height:42px}
.activity-audit-page .bm-skeleton-bar{height:12px;display:block;border-radius:5px;background:#e0e9e4}
@media(max-width:700px){.activity-audit-page .dv-table-tools input{width:140px}}
</style>
@endpush

@section('content')
<div class="content-wrapper bm-ui bm-voucher-directory daily-voucher-page activity-audit-page" id="audit-page">
 <div class="audit-heading">
  <div><h2>Activity Audit</h2><small>Authenticated activity · Related database impact</small></div>
  <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">Dashboard</a>
 </div>
 <div class="audit-register">
  <div class="dv-table-tools bm-records-toolbar">
   <label for="dv-page-length">Show <select class="form-control form-control-sm" id="dv-page-length"><option>10</option><option selected>25</option><option>50</option><option>100</option></select> entries</label>
   <label for="dv-table-search">Search: <input type="search" id="dv-table-search" class="form-control form-control-sm" placeholder="Search activities" autocomplete="off"></label>
  </div>
  <div class="audit-table-wrap" id="dv-table-wrap">
   <table class="table bm-table table-sm table-hover audit-table">
    <thead><tr><th>#</th>
    @foreach(['date'=>'Date / Time','actor_id'=>'Actor','method'=>'Action','route'=>'Route','table'=>'Affected Tables','status'=>'Status','ip'=>'IP'] as $field=>$label)
    <th class="dv-filter-heading" data-field="{{ $field }}"><span>{{ $label }}</span>
     <button type="button" class="dv-excel-trigger" data-column="{{ $field }}" title="Sort and filter {{ $label }}" aria-label="Sort and filter {{ $label }}" aria-expanded="false" aria-haspopup="dialog"><i class="fas fa-caret-down"></i></button>
    </th>
    @endforeach
    <th>Actions</th></tr></thead>
    <tbody id="dv-rows"></tbody>
   </table>
  </div>
  <div class="dv-pagination-footer">
   <span id="dv-record-summary" class="dv-total-records">Loading records...</span>
   <nav id="dv-pages" aria-label="Activity pagination"></nav>
  </div>
 </div>
 <x-finance.voucher-column-filters />
 <div class="modal fade" id="audit-detail-modal" tabindex="-1" role="dialog" aria-labelledby="audit-detail-title" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document"><div class="modal-content">
   <div class="modal-header"><div><h5 class="modal-title" id="audit-detail-title">Activity Details</h5><small id="audit-detail-subtitle" class="text-muted"></small></div>
    <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span>&times;</span></button></div>
   <div class="modal-body" id="audit-detail-body" style="max-height:65vh;overflow-y:auto"></div>
   <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button></div>
  </div></div>
 </div>
</div>
@endsection
@push('script')
<script>
$(function(){
 const indexUrl=@json(route('activity-audit.index')),detailBase=@json(url('/admin/activity-audit'));
 const $body=$('#dv-rows');
 let start=0,length=25,filtered=0,total=0,sortColumn='date',sortDirection='desc',xhr=null,timer=null,seq=0;
 const voucherColumns=$.blueVoucherColumnFilters.init({
  initialOptions:@json($initialFilterOptions),formSelector:'#audit-unused-form',
  quickSearchSelector:'#dv-table-search',triggerSelector:'.dv-excel-trigger',
  tableWrapSelector:'#dv-table-wrap',
  searchOnly:['actor_id','route','table','ip'],checkboxes:['method','status'],
  choicesFor:function(field,fallback){return (@json($initialFilterOptions))[field]||fallback;},
  getStart:()=>start,getLength:()=>length,getSortColumn:()=>sortColumn,
  getSortDirection:()=>sortDirection,
  onSort:(field,direction)=>{sortColumn=field;sortDirection=direction;},
  onChange:()=>{start=0;load();}
 });
 function cell(value){return $('<td>').text(value===null||value===undefined||value===''?'—':String(value));}
 function pagination(){
  $.blueVoucherPagination.render({container:'#dv-pages',summary:'#dv-record-summary',
   total:filtered,allTotal:total,start:start,length:length,buttonClass:'dv-page-btn',
   onPage:function(next){start=next;load();$('#dv-table-wrap').scrollTop(0);}
  });
 }
 function load(){
  const current=++seq;if(xhr)xhr.abort();
  $.blueTableSkeleton.show($body,{rows:10,columns:9,tone:'neutral'});
  $('#dv-record-summary').text('Loading records...');$('#dv-pages').empty();
  xhr=$.ajax({url:indexUrl,method:'GET',data:voucherColumns.queryParams(),
   dataType:'json',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}})
   .done(function(result){
    if(current!==seq)return;
    filtered=Number(result.recordsFiltered||0);total=Number(result.recordsTotal||0);
    $body.empty();
    $.each(result.data||[],function(_,row){
     const $tr=$('<tr>');
     $tr.append(cell(row.id),cell(row.created_at),cell((row.actor_name||'Former user')+' (#'+row.actor_id+')'),
      cell(row.action),cell(row.route_name),cell(row.affected_tables),cell(row.response_status),cell(row.ip_address));
     $tr.append($('<td>').append($('<button>',{type:'button','class':'audit-view','data-id':row.id})
      .append($('<i>',{'class':'fas fa-eye mr-1'})).append(' View')));
     $body.append($tr);
    });
    if(!filtered)$body.append($('<tr>').append($('<td>',{colspan:9,'class':'text-center text-muted py-4',text:'No matching activity records.'})));
    pagination();
   }).fail(function(response,status){
    if(status==='abort'||current!==seq)return;
    $body.empty().append($('<tr>').append($('<td>',{colspan:9,'class':'text-center text-danger py-4',text:'Could not load activity records. Check filters and retry.'})));
   }).always(function(){if(current===seq)$.blueTableSkeleton.clear($body);});
 }
 $('#dv-page-length').on('change',function(){length=Number($(this).val())||25;start=0;load();});
 $('#dv-table-search').on('input',function(){clearTimeout(timer);timer=setTimeout(function(){start=0;load();},300);});
 function renderDiff($target,before,after){
  const names=[...new Set([...Object.keys(before||{}),...Object.keys(after||{})])].filter(key=>JSON.stringify((before||{})[key])!==JSON.stringify((after||{})[key]));
  if(!names.length){$target.append($('<div>',{'class':'text-muted',text:'Record identity captured; no allowlisted field values available.'}));return;}
  const $table=$('<table>',{'class':'audit-diff'}),$tbody=$('<tbody>');
  $table.append($('<thead>').append($('<tr>').append($('<th>').text('Field'),$('<th>').text('Before'),$('<th>').text('After'))));
  $.each(names,function(_,key){$tbody.append($('<tr>').append(cell(key),cell((before||{})[key]),cell((after||{})[key])));});
  $target.append($table.append($tbody));
 }
 $('#audit-rows').on('click','.audit-view',function(){
  const id=$(this).data('id'),$modal=$('#audit-detail-modal'),$target=$('#audit-detail-body');
  $('#audit-detail-title').text('Activity #'+id);$('#audit-detail-subtitle').text('');
  $target.empty().append($('<div>',{'class':'text-center text-muted p-4',text:'Loading activity details...'}));
  $modal.modal('show');
  $.getJSON(detailBase+'/'+encodeURIComponent(id)).done(function(data){
   const a=data.event;
   $('#audit-detail-subtitle').text((a.actor_name||'Former user')+' · '+a.action+' · '+a.created_at+' · '+(a.route_name||a.request_path));
   $target.empty();
   const rows=data.impacts.filter(i=>i.capture_type==='eloquent_row'||i.capture_type==='db_trigger_row');
   const sql=data.impacts.filter(i=>i.capture_type==='sql_statement');
   $target.append($('<h6>',{text:'Captured row changes ('+rows.length+')'}));
   if(!rows.length)$target.append($('<p>',{'class':'text-muted',text:'No row-level changes captured for this operation. Non-triggered Query Builder writes do not automatically expose exact row values.'}));
   $.each(rows,function(_,item){
    const $card=$('<div>',{'class':'audit-impact'});
    $card.append($('<div>',{'class':'font-weight-bold mb-2',text:item.table_name+' · '+item.operation+' · Record #'+item.record_id+(item.capture_type==='db_trigger_row'?' · Transactional row capture':' · Model event')}));
    let before=null,after=null;
    try{before=JSON.parse(item.before_values||'{}');after=JSON.parse(item.after_values||'{}');}catch(e){}
    renderDiff($card,before||{},after||{});$target.append($card);
   });
   $target.append($('<h6>',{'class':'mt-3',text:'SQL operations by table ('+sql.length+')'}));
   $target.append($('<p>',{'class':'audit-sql-note',text:'These statements ran during the request. Their table and operation are observed; affected row IDs/counts and final committed changes are NOT established by a SQL statement alone.'}));
   if(!sql.length)$target.append($('<p>',{'class':'text-muted',text:'No SQL write statements captured.'}));
   $.each(sql,function(_,item){$target.append($('<div>',{'class':'audit-impact',text:item.table_name+' · '+item.operation+' · '+item.statement_count+' statement(s) observed'}));});
  }).fail(function(){$target.empty().append($('<p>',{'class':'text-danger',text:'Unable to load activity details.'}));});
 });
 load();
});
</script>
@endpush
