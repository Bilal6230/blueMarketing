@extends('admin.layouts.master')
@section('title', $title)
@section('content')
<style>
body.jv-directory-active{overflow:hidden!important}
.main-content.page-content:has(> .jv-page){height:auto!important;min-height:0!important;overflow:visible!important;padding-bottom:0!important}
.jv-page{padding:8px 10px;background:#edf3ff;height:var(--jv-viewport-height,calc(100dvh - 155px))!important;max-height:var(--jv-viewport-height,calc(100dvh - 155px))!important;min-height:0!important;display:flex;flex-direction:column;gap:8px;overflow:hidden!important;box-sizing:border-box}
.jv-head,.jv-filters{flex:0 0 auto}
.jv-head{background:#e8f0ff;border-bottom:2px solid #0673f9;border-radius:8px;display:flex;align-items:center;justify-content:space-between;padding:11px 14px;gap:10px}
.jv-head h4{margin:0;font-size:19px;font-weight:700;color:#0861d4}
.jv-head small{color:#6c7d91;font-size:13px}
.jv-filters,.jv-card{background:#fff;border:1px solid #c9d9f2;border-top:3px solid #0780ff;border-radius:10px;padding:11px}
.jv-filter-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr)) auto;align-items:end;gap:9px}
.jv-filter-grid label{font-size:13px;font-weight:700;margin:0 0 4px}
.jv-filter-grid .form-control{height:34px;font-size:13px}
.jv-filter-buttons{display:flex;gap:5px;align-items:center}
.jv-card{flex:1 1 auto;min-height:0;display:flex;flex-direction:column;padding:8px;overflow:hidden}
.jv-table-wrap{min-height:0;flex:1 1 auto;overflow:hidden;display:flex;flex-direction:column}
.jv-record-scroll{flex:1 1 auto;min-height:0;overflow-y:auto;overflow-x:auto;overscroll-behavior:contain}
.jv-record-scroll .jv-table{margin-bottom:0}
.jv-card .dataTables_wrapper>.d-flex{flex:0 0 auto}
.jv-card .dataTables_wrapper>.jv-footer{flex:0 0 auto;min-height:35px;background:#fff}
.jv-table{width:100%!important;margin:0;font-size:13px}
.jv-table th{background:#e7efff!important;border-bottom:2px solid #087cff!important;font-weight:700;color:#192a46!important}
.jv-table td{vertical-align:middle}
.jv-table thead tr.jv-column-search th{top:35px!important;padding:4px!important;background:#e7efff!important}
.jv-table thead tr:first-child th{position:sticky;top:0;z-index:3}
.jv-table thead tr.jv-column-search th{position:sticky;z-index:2}
.jv-column-search input,.jv-column-search select{height:27px!important;min-height:27px!important;padding:2px 5px!important;font-size:12px!important;width:100%;background:#fff;box-sizing:border-box}
.jv-column-search select{appearance:auto!important;-webkit-appearance:menulist!important}
.jv-actions{display:flex;flex-wrap:nowrap;gap:4px;align-items:center}
.jv-actions .btn{padding:4px 8px;white-space:nowrap;font-size:13px}
.jv-footer{border-top:1px solid #dde6f4;padding-top:8px}
.jv-footer .dataTables_info,.jv-footer .dataTables_paginate{font-size:13px}
.jv-card .dataTables_wrapper{display:flex;flex-direction:column;flex:1 1 auto;min-height:0;overflow:hidden}
.jv-card .dataTables_wrapper>.row{margin-left:0;margin-right:0}
.jv-card .dataTables_processing{z-index:5}
.jv-modal .modal-dialog{max-width:min(1180px,96vw);width:96vw;height:calc(100vh - 48px);margin:24px auto}
.jv-modal .modal-content{height:100%;overflow:hidden}
.jv-modal .modal-header{background:#e9f1ff;border-bottom:2px solid #0676ff;padding:10px 14px}
.jv-modal .modal-header .modal-title{font-size:17px;color:#0861d4;font-weight:700}
.jv-modal .modal-body{padding:0;min-height:0;position:relative}
.jv-modal iframe{width:100%;height:100%;border:0;display:block}
.jv-modal .jv-load{position:absolute;inset:0;background:#fff;z-index:2;display:flex;align-items:center;justify-content:center;gap:9px;color:#1764c3}
.jv-modal .jv-load.d-none{display:none!important}
.jv-modal .modal-footer{padding:7px 12px}
.jv-page .btn-primary{background:#087cfa;border-color:#087cfa}
.jv-page .btn,.jv-card .dataTables_length,.jv-card .dataTables_filter{font-size:13px}
.jv-page .badge-warning{background:#f5b51e;color:#231d12}
@media(max-width:1000px){.jv-filter-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.jv-filter-buttons{grid-column:1/-1}}
@media(max-width:650px){.jv-filter-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}

.jv-table{border-collapse:separate;border-spacing:0}
.jv-table th,.jv-table td{border-right:1px solid #d3dfec!important;border-bottom:1px solid #d3dfec!important}
.jv-filter-heading{white-space:nowrap}
.jv-excel-trigger{float:right;background:transparent;border:1px solid transparent;border-radius:4px;color:#64748b;min-width:24px}
.jv-excel-trigger:hover,.jv-excel-trigger.jv-filter-on,.jv-excel-trigger[aria-expanded=true]{background:white;border-color:#087cff;color:#087cff}
.jv-excel-pop{position:fixed;z-index:1100;width:285px;max-width:calc(100vw - 20px);border:1px solid #cad5e2;border-radius:8px;background:white;box-shadow:0 12px 36px rgba(12,32,55,.22);padding:12px;font-size:12px}
.jv-excel-pop.d-none{display:none!important}
.jv-excel-sort{display:block;width:100%;border:0;background:white;text-align:left;padding:8px;border-radius:4px}
.jv-excel-sort:hover{background:#eef5ff}
.jv-excel-items{height:185px;overflow:auto;border:1px solid #dbe4ed;border-radius:4px;margin-top:8px;padding:4px}
.jv-excel-choice{display:flex;align-items:center;gap:7px;padding:5px 2px;margin:0;cursor:pointer;overflow-wrap:anywhere}
.jv-excel-choice:hover{background:#f0f5fc}
.jv-excel-choice input{margin:0;flex-shrink:0}
.jv-excel-bottom{display:flex;gap:5px;justify-content:flex-end;margin-top:10px}
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
     <tr>
      @foreach(['id'=>'ID','voucher_number'=>'Voucher #','reference'=>'Reference','date'=>'Date','description'=>'Description','total_debit'=>'Amount'] as $field=>$title)
      <th class="jv-filter-heading"><span>{{ $title }}</span><button type="button" class="jv-excel-trigger" data-field="{{ $field }}" data-col="{{ $loop->index }}" aria-label="Sort or filter {{ $title }}" aria-haspopup="dialog" aria-expanded="false"><i class="fas fa-caret-down"></i></button></th>
      @endforeach
      @if($type==='JV')<th class="jv-filter-heading"><span>Status</span><button type="button" class="jv-excel-trigger" data-field="approval_status" data-col="6" aria-label="Filter status" aria-haspopup="dialog" aria-expanded="false"><i class="fas fa-caret-down"></i></button></th>@endif
      <th>Actions</th>
     </tr>
    </thead><tbody></tbody>
   </table>
  </div>
 </div>
</div>
<div id="jv-excel-pop" class="jv-excel-pop d-none" role="dialog" aria-label="Journal voucher column filter"><strong id="jv-excel-title"></strong>
 <button type="button" class="jv-excel-sort" data-dir="asc">Sort A to Z / Small to Large</button><button type="button" class="jv-excel-sort" data-dir="desc">Sort Z to A / Large to Small</button>
 <label for="jv-excel-search" class="sr-only">Search filter values</label><input type="search" id="jv-excel-search" class="form-control form-control-sm" placeholder="Search values…" autocomplete="off">
 <div id="jv-excel-items" class="jv-excel-items"></div><div class="jv-excel-bottom">
 <button type="button" class="btn btn-sm btn-light" id="jv-excel-clear">Clear</button><button type="button" class="btn btn-sm btn-light" id="jv-excel-cancel">Cancel</button><button type="button" class="btn btn-sm btn-primary" id="jv-excel-apply">Apply</button></div></div>
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
 var jvExcelSelections={},jvExcelExclusions={},jvExcelCurrent=null,jvExcelOptions=[],jvExcelDraft=new Set(),jvExcelRequest=null,$jvExcel=$('#jv-excel-pop');
 $('body').addClass('jv-directory-active');
 function fitJournalDirectory(){
  var $page=$('.jv-page'),top=$page.offset().top;
  var footer=$('.main-footer:visible').first().outerHeight()||38;
  var available=Math.max(320,$(window).height()-top-footer-3);
  $page.css('--jv-viewport-height',available+'px');
 }
 fitJournalDirectory();
 $(window).on('resize.journalDirectory',fitJournalDirectory);
 var baseUrl=@json($type==='SV'?route('sales.voucher.index'):route('journal.voucher.index'));
 var createUrl=@json(route('journal.voucher.create'));
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
   $.each(jvExcelSelections,function(field,values){req.excel_values??={};req.excel_values[field]=values;});
   $.each(jvExcelExclusions,function(field,values){req.excel_excluded??={};req.excel_excluded[field]=values;});
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
 // DataTables creates its toolbar and pagination outside the table.
 // Scroll only the rows/header region; toolbar and pagination remain visible.
 $table.wrap('<div class="jv-record-scroll"></div>');
 fitJournalDirectory();
 $('#jv-top-filters').on('submit',function(e){e.preventDefault();apply();});
 $('#jv-reset').on('click',function(){
  $('#jv-top-filters')[0].reset();$.each(jvExcelSelections,function(field){delete jvExcelSelections[field];});
  $.each(jvExcelExclusions,function(field){delete jvExcelExclusions[field];});closeJvExcel();updateJvExcelButtons();
  table.columns().search('');table.search('');apply();
 });

 function closeJvExcel(){
  $jvExcel.addClass('d-none');$('.jv-excel-trigger').attr('aria-expanded','false');
  if(jvExcelRequest){jvExcelRequest.abort();jvExcelRequest=null;}jvExcelCurrent=null;
 }
 function updateJvExcelButtons(){
  $('.jv-excel-trigger').each(function(){
   $(this).toggleClass('jv-filter-on',Object.prototype.hasOwnProperty.call(jvExcelSelections,$(this).data('field'))||
    Object.prototype.hasOwnProperty.call(jvExcelExclusions,$(this).data('field')));
  });
 }
 function renderJvExcel(){
  var search=$.trim($('#jv-excel-search').val()).toLocaleLowerCase(),$box=$('#jv-excel-items').empty();
  var visible=jvExcelOptions.filter(function(v){return String(v).toLocaleLowerCase().includes(search);});
  $box.append($('<label>',{'class':'jv-excel-choice'})
   .append($('<input>',{type:'checkbox','class':'jv-check-all',
    checked:visible.length>0&&visible.every(function(v){return jvExcelDraft.has(String(v));})}))
   .append($('<span>',{text:'(Select All Results)'})));
  $.each(visible,function(_,v){
   var str=String(v);
   $box.append($('<label>',{'class':'jv-excel-choice'})
    .append($('<input>',{type:'checkbox','class':'jv-excel-item',value:str,checked:jvExcelDraft.has(str)}))
    .append($('<span>',{text:str||'(Blank)'})));
  });
  if(!visible.length)$box.append($('<div>',{'class':'small text-muted p-2',text:'No values found'}));
 }
 $('.jv-excel-trigger').on('click',function(e){
  e.preventDefault();e.stopPropagation();
  var $btn=$(this),field=$btn.data('field');
  if(jvExcelCurrent===field){closeJvExcel();return;}
  closeJvExcel();jvExcelCurrent=field;jvExcelOptions=[];
  jvExcelDraft=new Set(jvExcelSelections[field]||[]);
  $('.jv-excel-trigger').attr('aria-expanded','false');$btn.attr('aria-expanded','true');
  $('#jv-excel-title').text($btn.closest('th').find('span:first').text());
  $('#jv-excel-search').val('');$('#jv-excel-items').text('Loading filter values…');
  $jvExcel.removeClass('d-none');
  var pos=$btn.offset();
  $jvExcel.css({left:Math.max(8,Math.min(pos.left-$(window).scrollLeft(),$(window).width()-300))+'px',
   top:Math.max(8,Math.min(pos.top-$(window).scrollTop()+$btn.outerHeight(),$(window).height()-345))+'px'});
  $jvExcel.find('.jv-excel-sort').toggle(field!=='approval_status');
  var params={excel_facet:field,filter_voucher_number:$('#jv-from').val(),filter_date:$('#jv-date').val(),
   filter_amount_min:$('#jv-min').val(),filter_amount_max:$('#jv-max').val(),
   filter_reference:$('#jv-reference').val()};
  jvExcelRequest=$.ajax({url:baseUrl,type:'GET',data:params,dataType:'json',
   headers:{'X-Requested-With':'XMLHttpRequest'}}).done(function(res){
    if(jvExcelCurrent!==field)return;
    jvExcelOptions=(res.options||[]).map(String);
    if(Object.prototype.hasOwnProperty.call(jvExcelExclusions,field))
     jvExcelDraft=new Set(jvExcelOptions.filter(function(v){return !jvExcelExclusions[field].includes(v);}));
    else if(!Object.prototype.hasOwnProperty.call(jvExcelSelections,field))
     $.each(jvExcelOptions,function(_,v){jvExcelDraft.add(v);});
    renderJvExcel();
    if(res.more_options)$('#jv-excel-items').prepend($('<p>',{'class':'small text-warning',
     text:'First 500 values shown. Narrow the date/search range.'}));
   }).fail(function(xhr,status){
    if(status!=='abort'&&jvExcelCurrent===field)
     $('#jv-excel-items').text(xhr.responseJSON?.message||'Unable to load values.');
   });
 });
 $jvExcel.on('change','.jv-excel-item',function(){
  if($(this).is(':checked'))jvExcelDraft.add($(this).val());else jvExcelDraft.delete($(this).val());
  renderJvExcel();
 }).on('change','.jv-check-all',function(){
  var search=$.trim($('#jv-excel-search').val()).toLocaleLowerCase(),enabled=$(this).is(':checked');
  $.each(jvExcelOptions,function(_,v){if(String(v).toLocaleLowerCase().includes(search)){
   if(enabled)jvExcelDraft.add(String(v));else jvExcelDraft.delete(String(v));
  }});renderJvExcel();
 });
 $('#jv-excel-search').on('input',renderJvExcel);
 $('#jv-excel-apply').on('click',function(){
  if(!jvExcelCurrent)return;
  if(!jvExcelDraft.size&&jvExcelOptions.length){
   $('#jv-excel-items').prepend($('<p>',{'class':'small text-danger',text:'Select a value or Clear.'}));return;
  }
  var removed=jvExcelOptions.filter(function(v){return !jvExcelDraft.has(String(v));});
  if(!removed.length){
   delete jvExcelSelections[jvExcelCurrent];delete jvExcelExclusions[jvExcelCurrent];
  }else if(removed.length<jvExcelDraft.size){
   delete jvExcelSelections[jvExcelCurrent];jvExcelExclusions[jvExcelCurrent]=removed;
  }else{
   delete jvExcelExclusions[jvExcelCurrent];jvExcelSelections[jvExcelCurrent]=[...jvExcelDraft];
  }
  closeJvExcel();updateJvExcelButtons();apply();
 });
 $('#jv-excel-cancel').on('click',closeJvExcel);
 $('#jv-excel-clear').on('click',function(){
  if(!jvExcelCurrent)return;delete jvExcelSelections[jvExcelCurrent];
  delete jvExcelExclusions[jvExcelCurrent];closeJvExcel();updateJvExcelButtons();apply();
 });
 $jvExcel.on('click','.jv-excel-sort',function(){
  if(!jvExcelCurrent)return;
  var field=jvExcelCurrent,idx=Number($('.jv-excel-trigger[data-field="'+field+'"]').data('col'));
  var dir=$(this).data('dir');closeJvExcel();table.order([[idx,dir]]).draw();
 });
 $(document).on('pointerdown.jvExcel',function(e){
  if(!$(e.target).closest('#jv-excel-pop,.jv-excel-trigger').length)closeJvExcel();
 }).on('keydown.jvExcel',function(e){if(e.key==='Escape')closeJvExcel();});
 $('.jv-record-scroll').on('scroll',function(){if(jvExcelCurrent)closeJvExcel();});
 function openEntry(url,title){
  entryUrl=url;var separator=url.indexOf('?')>=0?'&':'?';
  $('#jv-entry-title').text(title);
  $('#jv-open-tab').attr('href',url);
  $('#jv-loading').removeClass('d-none');
  $('#jv-entry-frame').attr('src',url+separator+'embed=1');
  $('#jv-entry').modal('show');
 }
 $('#jv-add').on('click',function(){openEntry(createUrl,'Add New Journal Voucher');});
 $table.on('click','a[href*="/journal-vouchers/"],a[href*="/journal-voucher/"]',function(e){
  // The real edit route is /admin/journal-vouchers/{id}/edit (plural).
  // Handle only the JV page; print links remain unchanged.
  if($(this).attr('target')==='_blank')return;
  var url=$(this).attr('href');
  if(!url || url.indexOf('/edit')===-1)return;
  e.preventDefault();
  openEntry(url,url.indexOf('resubmit=1')!==-1?'Revise & Resubmit Rejected Journal Voucher':'Edit Journal Voucher');
 });
 $('#jv-entry-frame').on('load',function(){
  if(!entryUrl)return;
  try{
   var $doc=$(this).contents(),$form=$doc.find('#journalForm');
   // Nested master layout has its own global preloader. Remove it in embed mode.
   $doc.find('#load_screen,.preloader').hide();
   // The nested page must not inherit AdminLTE's collapsed sidebar offset.
   $doc.find('body').removeClass('sidebar-mini sidebar-collapse layout-fixed layout-navbar-fixed');
   $doc.find('.main-content.page-content,.content-wrapper').css({'margin-left':'0','margin-right':'0','width':'100%','max-width':'100%'});
   $doc.find('.content-wrapper > .content > .container-fluid').css({'margin-left':'0','margin-right':'0','padding-left':'0','padding-right':'0'});
   if($doc.find('.alert-success').length){
    $('#jv-entry').modal('hide');table.ajax.reload(null,false);return;
   }
   // Native form redirects back to index after create. Do not leave an index inside the modal.
   if(!$form.length){
    var $success=$doc.find('.alert-success');
    if($success.length){$('#jv-entry').modal('hide');table.ajax.reload(null,false);return;}
    $('#jv-loading').html('<span class="text-danger">Voucher form failed to render. Use Open in new tab.</span>');return;
   }
   $('#jv-loading').addClass('d-none');
  }catch(ex){$('#jv-loading').html('<span class="text-danger">Form unavailable. Open in a new tab.</span>');}
 });
 $('#jv-entry').on('hidden.bs.modal',function(){entryUrl='';$('#jv-entry-frame').attr('src','about:blank');$('#jv-loading').removeClass('d-none').text('Loading voucher form...');});
 $table.on('click','.delete-btn',function(){var id=$(this).data('id');$('#deleteForm').attr('action',@json(route('journal.voucher.delete',':id')).replace(':id',id));$('#deleteModal').modal('show');});
});
</script>
@endsection
