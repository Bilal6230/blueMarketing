@extends('admin.layouts.master')
@section('title', 'Activity Audit')
@push('style')
<style>
.bm-audit{--audit-blue:#2269d4;padding:14px 16px 24px;font-size:12px;color:#2c3e54}
.bm-audit .audit-title{font-size:19px;font-weight:750;color:#183e78;margin:0}
.bm-audit .audit-panel{border:1px solid #dce6f2;border-top:3px solid var(--audit-blue);border-radius:11px;background:#fff;box-shadow:0 3px 14px #17365b0b;overflow:hidden}
.bm-audit .audit-panel-header{padding:12px 15px;border-bottom:1px solid #e7edf5;display:flex;justify-content:space-between;align-items:center;gap:12px}
.bm-audit .audit-table{width:100%;margin:0;font-size:12px;table-layout:auto}
.bm-audit .audit-table th{background:#f0f5fc;color:#304a6b;font-size:11px;padding:10px 9px;border-bottom:2px solid #c5d9f4;white-space:nowrap}
.bm-audit .audit-table td{padding:10px 9px;vertical-align:middle;border-bottom:1px solid #edf1f7}
.bm-audit .audit-table tbody tr:hover{background:#f8fbff}
.bm-audit .audit-column-filter{border:0;background:transparent;color:#7b90ac;padding:3px 5px;margin-left:5px;border-radius:4px}
.bm-audit .audit-column-filter:hover,.bm-audit .audit-column-filter.active{color:#1766c7;background:#e0edff}
.bm-audit .audit-view{border:1px solid #bbd3f5;background:#eff6ff;color:#165db6;font-size:11px;font-weight:650;padding:5px 10px;border-radius:5px}
.bm-audit .audit-footer{min-height:45px;padding:9px 14px;border-top:1px solid #e3ebf6;display:flex;justify-content:space-between;align-items:center}
.bm-audit .audit-footer button{border:1px solid #bcd4f4;background:#fff;border-radius:5px;color:#2166c4;font-size:11px;padding:5px 12px}
.bm-audit .audit-footer button:disabled{opacity:.45}
.bm-audit .audit-pop{display:none;position:fixed;z-index:1080;background:white;border:1px solid #cfddf1;border-top:3px solid var(--audit-blue);box-shadow:0 12px 34px #0a214333;border-radius:9px;padding:12px;width:260px}
.bm-audit .audit-pop label{font-weight:700;color:#3c5879}
.bm-audit .audit-impact{border:1px solid #e0e9f6;border-radius:8px;margin-bottom:10px;padding:11px;background:#fbfdff}
.bm-audit .audit-diff{width:100%;font-size:11px}
.bm-audit .audit-diff th,.bm-audit .audit-diff td{padding:6px;border:1px solid #e4ecf6;word-break:break-word}
.bm-audit .audit-diff th{background:#edf4ff}
.bm-audit .audit-sql-note{color:#7b5f25;background:#fff9e8;border:1px solid #f0e4b6;border-radius:6px;padding:10px;margin:8px 0}
.bm-audit .bm-skeleton-row td{height:40px}.bm-audit .bm-skeleton-bar{display:block;height:12px;border-radius:5px;background:linear-gradient(90deg,#e9eef7 15%,#d8e3f1 45%,#e9eef7 75%);background-size:220% 100%;animation:auditShimmer 1.4s ease-in-out infinite}
@keyframes auditShimmer{to{background-position:-220% 0}}
@media(prefers-reduced-motion:reduce){.bm-audit .bm-skeleton-bar{animation:none}}
</style>
@endpush

@section('content')
<div class="content-wrapper bm-audit">
 <div class="d-flex align-items-center justify-content-between mb-3">
  <div><h2 class="audit-title">Activity Audit</h2><small class="text-muted">User actions, linked database operations and available field-level changes</small></div>
  <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">Dashboard</a>
 </div>
 <div class="audit-panel">
  <div class="audit-panel-header">
   <div><strong>Activity History</strong><small class="text-muted ml-2" id="audit-count">{{ $events->total() }} records</small></div>
   <button type="button" id="audit-reset" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo mr-1"></i> Reset Filters</button>
  </div>
  <div class="table-responsive">
   <table class="audit-table">
    <thead><tr>
     <th>ID</th>
     <th>When <button class="audit-column-filter" type="button" data-filter="date" title="Filter date"><i class="fas fa-filter"></i></button></th>
     <th>Actor <button class="audit-column-filter" type="button" data-filter="actor_id" title="Filter actor ID"><i class="fas fa-filter"></i></button></th>
     <th>Action <button class="audit-column-filter" type="button" data-filter="method" title="Filter action"><i class="fas fa-filter"></i></button></th>
     <th>Route <button class="audit-column-filter" type="button" data-filter="route" title="Filter route"><i class="fas fa-filter"></i></button></th>
     <th>Table <button class="audit-column-filter" type="button" data-filter="table" title="Filter affected table"><i class="fas fa-filter"></i></button></th>
     <th>Status <button class="audit-column-filter" type="button" data-filter="status" title="Filter status"><i class="fas fa-filter"></i></button></th>
     <th>IP</th><th>Action</th>
    </tr></thead>
    <tbody id="audit-rows">
     @forelse($events as $event)
      <tr><td>{{ $event->id }}</td><td class="text-nowrap">{{ $event->created_at }}</td>
       <td>{{ $event->actor_name ?? 'Former user' }} (#{{ $event->actor_id }})</td>
       <td>{{ $event->action }}</td><td>{{ $event->route_name ?? '—' }}</td>
       <td><small class="text-muted">{{ $event->affected_tables ?? "—" }}</small></td>
       <td>{{ $event->response_status }}</td><td>{{ $event->ip_address ?? '—' }}</td>
       <td><button type="button" class="audit-view" data-id="{{ $event->id }}"><i class="far fa-eye mr-1"></i> View</button></td></tr>
     @empty
      <tr><td colspan="9" class="text-center text-muted py-4">No activity records found.</td></tr>
     @endforelse
    </tbody>
   </table>
  </div>
  <div class="audit-footer">
   <span class="text-muted" id="audit-page-count">Page {{ $events->currentPage() }} of {{ $events->lastPage() }}</span>
   <div><button type="button" id="audit-prev" @disabled($events->onFirstPage())>Previous</button> <button type="button" id="audit-next" @disabled(!$events->hasMorePages())>Next</button></div>
  </div>
 </div>
 <div id="audit-filter-pop" class="audit-pop">
  <div class="font-weight-bold mb-2" id="audit-pop-title">Column filter</div>
  <div id="audit-pop-body"></div>
  <div class="d-flex justify-content-end mt-3">
   <button type="button" class="btn btn-light btn-sm mr-2" id="audit-filter-clear">Clear</button>
   <button type="button" class="btn btn-primary btn-sm" id="audit-filter-apply">Apply</button>
  </div>
 </div>
 <div class="modal fade" id="audit-detail-modal" tabindex="-1" role="dialog" aria-labelledby="audit-detail-title" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
   <div class="modal-content" style="border-radius:13px;overflow:hidden">
    <div class="modal-header bg-light"><div><h5 id="audit-detail-title" class="modal-title">Activity Details</h5><small id="audit-detail-subtitle" class="text-muted"></small></div><button type="button" class="close" data-dismiss="modal">&times;</button></div>
    <div class="modal-body" id="audit-detail-body" style="max-height:65vh;overflow-y:auto"></div>
    <div class="modal-footer"><button class="btn btn-outline-secondary btn-sm" type="button" data-dismiss="modal">Close</button></div>
   </div>
  </div>
 </div>
</div>
@endsection

@push('script')
<script>
$(function(){
 const indexUrl=@json(route('activity-audit.index'));
 const detailBase=@json(url('/admin/activity-audit'));
 const filters={}, $body=$('#audit-rows');
 let page=1,lastPage={{ $events->lastPage() }},xhr=null,activeField=null;
 function cell(value){return $('<td>').text(value==null||value===''?'—':String(value));}
 function load(){
  if(xhr)xhr.abort();
  if($.blueTableSkeleton)$.blueTableSkeleton.show($body,{rows:8,columns:9});
  xhr=$.ajax({url:indexUrl,method:'GET',data:$.extend({page:page},filters),dataType:'json',
    headers:{Accept:'application/json'}}).done(function(data){
    $body.empty();lastPage=data.last_page;page=data.current_page;
    $('#audit-count').text(data.total+' records');
    $('#audit-page-count').text('Page '+page+' of '+lastPage);
    $('#audit-prev').prop('disabled',page<=1);$('#audit-next').prop('disabled',page>=lastPage);
    if(!data.rows.length){$body.append($('<tr>').append($('<td>',{colspan:9,'class':'text-center text-muted py-4',text:'No matching activity records.'})));return;}
    $.each(data.rows,function(_,row){
     const $tr=$('<tr>');
     $tr.append(cell(row.id),cell(row.created_at),cell((row.actor_name||'Former user')+' (#'+row.actor_id+')'),
       cell(row.action),cell(row.route_name),cell(row.affected_tables),cell(row.response_status),cell(row.ip_address));
     $tr.append($('<td>').append($('<button>',{type:'button','class':'audit-view','data-id':row.id})
       .append($('<i>',{'class':'far fa-eye mr-1'})).append(' View')));
     $body.append($tr);
    });
  }).fail(function(response,status){
    if(status==='abort')return;
    $body.empty().append($('<tr>').append($('<td>',{colspan:9,'class':'text-center text-danger py-4',text:'Unable to load activity history.'})));
  }).always(function(){if($.blueTableSkeleton)$.blueTableSkeleton.clear($body);});
 }
 $('#audit-prev').on('click',function(){if(page>1){page--;load();}});
 $('#audit-next').on('click',function(){if(page<lastPage){page++;load();}});
 $('#audit-reset').on('click',function(){Object.keys(filters).forEach(key=>delete filters[key]);page=1;$('.audit-column-filter').removeClass('active');$('#audit-filter-pop').hide();load();});
 $('.audit-column-filter').on('click',function(e){
  e.stopPropagation();activeField=$(this).data('filter');
  const $pop=$('#audit-filter-pop'),$box=$('#audit-pop-body').empty();
  $('#audit-pop-title').text('Filter '+$(this).closest('th').text().trim());
  if(activeField==='date'){
   $box.append($('<label>').text('From'),$('<input>',{type:'date','class':'form-control form-control-sm mb-2',id:'audit-from'}).val(filters.from||''),
    $('<label>').text('To'),$('<input>',{type:'date','class':'form-control form-control-sm',id:'audit-to'}).val(filters.to||''));
  }else if(activeField==='method'){
   const $select=$('<select>',{'class':'form-control form-control-sm',id:'audit-filter-value'});
   $.each(['','POST','PUT','PATCH','DELETE'],function(_,v){$select.append($('<option>',{value:v,text:v||'All actions'}));});
   $box.append($select.val(filters.method||''));
  }else{
   $box.append($('<input>',{type:activeField==='actor_id'||activeField==='status'?'number':'text',
    'class':'form-control form-control-sm',id:'audit-filter-value',placeholder:'Filter by '+activeField}).val(filters[activeField]||''));
  }
  const pos=$(this).offset();
  $pop.css({top:Math.min($(window).height()-290,Math.max(8,pos.top-$(window).scrollTop()+25)),
   left:Math.max(8,Math.min($(window).width()-270,pos.left-$(window).scrollLeft()))}).show();
 });
 $('#audit-filter-apply').on('click',function(){
  if(activeField==='date'){
   const from=$('#audit-from').val(),to=$('#audit-to').val();
   if(from&&to&&from>to){$('#audit-to').addClass('is-invalid');return;}
   if(from)filters.from=from;else delete filters.from;
   if(to)filters.to=to;else delete filters.to;
  }else{
   const value=$('#audit-filter-value').val();
   if(value)filters[activeField]=value;else delete filters[activeField];
  }
  $('.audit-column-filter[data-filter="'+activeField+'"]').toggleClass('active',
   activeField==='date'?!!(filters.from||filters.to):!!filters[activeField]);
  $('#audit-filter-pop').hide();page=1;load();
 });
 $('#audit-filter-clear').on('click',function(){
  if(activeField==='date'){delete filters.from;delete filters.to;}else delete filters[activeField];
  $('.audit-column-filter[data-filter="'+activeField+'"]').removeClass('active');
  $('#audit-filter-pop').hide();page=1;load();
 });
 $(document).on('click',function(e){if(!$(e.target).closest('#audit-filter-pop,.audit-column-filter').length)$('#audit-filter-pop').hide();});
 function renderDiff($target,before,after){
  const names=[...new Set([...Object.keys(before||{}),...Object.keys(after||{})])].filter(key=>JSON.stringify((before||{})[key])!==JSON.stringify((after||{})[key]));
  if(!names.length){$target.append($('<div>',{'class':'text-muted',text:'Record identity captured; no allowlisted field values available.'}));return;}
  const $table=$('<table>',{'class':'audit-diff'}),$tbody=$('<tbody>');
  $table.append($('<thead>').append($('<tr>').append($('<th>').text('Field'),$('<th>').text('Before'),$('<th>').text('After'))));
  $.each(names,function(_,key){$tbody.append($('<tr>').append(cell(key),cell((before||{})[key]),cell((after||{})[key])));});
  $target.append($table.append($tbody));
 }
 $body.on('click','.audit-view',function(){
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
});
</script>
@endpush
