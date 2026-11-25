@extends('admin.layouts.master')
@section('title', 'Areas List')

@push('style')
<style>
.bm-areas{--voucher-accent:#2563eb;--fvd-accent:#2563eb;--fvd-pop-accent:#2563eb;padding:15px 16px 10px;color:#263b56;font-size:13px;min-height:calc(100vh - 175px);display:flex;flex-direction:column}
.bm-areas *{box-sizing:border-box}
.bm-areas .area-heading{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:13px}
.bm-areas .area-heading h2{font-size:19px;font-weight:750;color:#173a75;margin:0}
.bm-areas .area-heading small{font-size:11px;color:#7889a0}
.bm-areas .area-card{background:#fff;border:1px solid #dae4f2;border-top:3px solid #2563eb;border-radius:11px;overflow:hidden;box-shadow:0 3px 13px #122b460a;flex:1 1 auto;min-height:350px;display:flex;flex-direction:column}
.bm-areas .area-table-wrap{width:100%;overflow:auto;min-height:0;flex:1 1 auto}
.bm-areas .area-table{width:100%;margin:0;font-size:14px;white-space:nowrap;border-collapse:separate!important;border-spacing:0!important}
.bm-areas .area-table thead th{background:#f0f5fc;color:#294366;font-weight:750;font-size:13px;padding:11px 12px;border-bottom:2px solid #2563eb!important;border-right:1.5px solid #b6c7da!important}
/* Keep column names and filter controls visible while scrolling table rows. */
.bm-areas .area-table thead th{position:sticky;top:0;z-index:3;background:#f0f5fc!important;background-clip:padding-box}
.bm-areas .area-table tbody td{padding:11px 12px!important;border-top:1.5px solid #c5d2e2!important;border-right:1.5px solid #c5d2e2!important;vertical-align:middle;height:48px!important}
.bm-areas .area-table thead th:last-child,.bm-areas .area-table tbody td:last-child{border-right:0!important}
.bm-areas .area-table tbody tr:nth-child(even){background:#f1f5fa!important}
.bm-areas .area-table tbody tr:nth-child(odd){background:#ffffff!important}
.bm-areas .area-table tbody tr:hover{background:#e8f2ff!important}
.bm-areas .area-name{font-weight:750;color:#1d54a0}
.bm-areas .area-status{display:inline-flex;align-items:center;border-radius:14px;font-size:12px;padding:4px 9px;background:#e8f8ef;color:#16754a;font-weight:700}
.bm-areas .area-status.inactive{background:#f3f4f6;color:#69788b}
.bm-areas .area-table th .area-col-trigger{float:right;border:0;background:transparent;color:#607b9e;border-radius:5px;padding:2px 6px;cursor:pointer;font-size:11px}
.bm-areas .area-table th .area-col-trigger:hover,.bm-areas .area-table th .area-col-trigger.area-col-active{background:#dfedff;color:#155fc3}
.bm-areas .area-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;padding:8px 12px;border-top:1px solid #e7edf6;min-height:46px;flex:0 0 auto;margin-top:auto}
.bm-areas .area-footer-controls{display:flex;align-items:center;gap:8px}
.bm-areas .area-footer-controls select{height:29px;padding:3px 7px;font-size:12px;border:1px solid #d5e0ec;border-radius:5px;background:white}
.bm-areas .area-footer-controls button{font-size:11px}
.bm-areas .area-pages{display:flex;gap:4px;align-items:center}
.bm-areas .area-pages .dv-page-btn{border:1px solid #adcaf0;background:white;color:#2563eb;border-radius:5px;min-width:30px;height:30px;font-size:11px}
.bm-areas .area-pages .dv-page-btn.active{background:#2563eb;color:white}
.bm-areas .area-pages .dv-page-btn:disabled{opacity:.45}
.bm-areas .area-skeleton{height:12px;display:block;max-width:100%;border-radius:5px;background:linear-gradient(90deg,#edf2f8 25%,#f9fbfe 50%,#edf2f8 75%);background-size:200% 100%;animation:bmAreaShimmer 1.4s ease infinite}
.bm-areas .area-skeleton.long{width:140px}.bm-areas .area-skeleton.mid{width:90px}.bm-areas .area-skeleton.short{width:38px}
@keyframes bmAreaShimmer{to{background-position-x:-200%}}
@media(prefers-reduced-motion:reduce){.bm-areas .area-skeleton{animation:none}}
.bm-area-editor .modal-dialog{max-width:650px;width:calc(100% - 32px);margin:25px auto}
.bm-area-editor .modal-content{border:0;border-radius:13px;overflow:hidden;box-shadow:0 22px 65px #0e203850}
.bm-area-editor .modal-header{background:#f7faff;border-bottom:1px solid #dce7f3;padding:15px 20px}
.bm-area-editor .modal-title{font-size:16px;font-weight:800;color:#193d73}
.bm-area-editor .modal-body{padding:17px 20px}
.bm-area-editor .modal-footer{background:#fff;padding:12px 20px;border-top:1px solid #e1eaf4}
.bm-area-editor label{font-size:13px;font-weight:700;color:#344d6c;margin-bottom:5px}
.bm-area-editor .form-control{font-size:13px;border-color:#d2deeb;border-radius:7px;min-height:36px}
.bm-area-editor .area-field-error{display:block;color:#b42318;font-size:12px;margin-top:4px}
@media(max-width:767px){.bm-areas{min-height:calc(100vh - 160px)}.bm-areas .area-table-wrap{overflow-x:auto}}
</style>
@endpush

@section('content')
<div class="content-wrapper bm-ui bm-voucher-directory bm-areas" id="areasApp">
  <div class="area-heading">
    <div><h2>Areas List</h2><small>Manage business areas and their status</small></div>
    <div class="d-flex align-items-center" style="gap:7px">
      @can('create area')<button class="btn btn-primary btn-sm" type="button" id="areaAdd"><i class="fas fa-plus mr-1"></i> New Area</button>@endcan
      <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">Dashboard</a>
    </div>
  </div>
  <div class="area-card">
    <div class="bm-modern-export-toolbar bm-export-placeholder" aria-label="Table export toolbar loading">
  <button type="button" class="bm-modern-export-btn" disabled>Copy</button>
  <button type="button" class="bm-modern-export-btn" disabled>CSV</button>
  <button type="button" class="bm-modern-export-btn" disabled>Excel</button>
  <button type="button" class="bm-modern-export-btn" disabled>PDF</button>
  <button type="button" class="bm-modern-export-btn" disabled>Print</button>
  <button type="button" class="bm-modern-export-btn bm-modern-col-toggle" disabled>Column Visibility</button>
</div>
    <div class="area-table-wrap">
      <table class="table table-hover area-table" id="areasTable">
        <thead><tr>
          @foreach (['id'=>'#','name'=>'Area Name','is_active'=>'Status','created_at'=>'Created'] as $field=>$label)
            <th><span>{{ $label }}</span><button type="button" class="area-col-trigger" data-field="{{ $field }}" aria-label="Sort or filter {{ $label }}" aria-expanded="false" aria-haspopup="dialog"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
          @endforeach
          @canany(['update area','delete area'])<th>Actions</th>@endcanany
        </tr></thead>
        <tbody id="areaRows" aria-busy="true">
          @for ($i=0;$i<5;$i++)
          <tr aria-hidden="true">
            <td><span class="area-skeleton short"></span></td>
            <td><span class="area-skeleton long"></span></td>
            <td><span class="area-skeleton mid"></span></td>
            <td><span class="area-skeleton mid"></span></td>
            @canany(['update area','delete area'])<td><span class="area-skeleton short"></span></td>@endcanany
          </tr>
          @endfor
        </tbody>
      </table>
    </div>
    <div class="area-foot">
      <div class="area-footer-controls">
        <small class="text-muted" id="areaSummary"></small>
        <select id="areaPageSize" aria-label="Rows per page"><option>10</option><option selected>25</option><option>50</option><option>100</option></select>
        <button class="btn btn-outline-secondary btn-sm" type="button" id="areaReset">Reset filters</button>
      </div>
      <div class="area-pages" id="areaPages"></div>
    </div>
  </div>
</div>
@endsection

@section('modal')
<div class="modal fade bm-area-editor" id="areaEditor" tabindex="-1" role="dialog" aria-labelledby="areaEditorTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
    <div class="modal-header">
      <div><h5 class="modal-title" id="areaEditorTitle">New Area</h5><small class="text-muted">Business area details</small></div>
      <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
    <form id="areaForm" data-ajax="off" method="POST" action="{{ route('area.store') }}" novalidate>
      @csrf
      <input type="hidden" name="id" id="areaEditId">
      <div class="modal-body">
        <div class="form-group">
          <label for="areaName">Area Name *</label>
          <input class="form-control" name="name" id="areaName" required maxlength="255">
          <span class="area-field-error" data-error="name"></span>
        </div>
        <div class="form-group mb-0">
          <label for="areaActive">Status *</label>
          <select name="is_active" id="areaActive" class="form-control"><option value="1">Active</option><option value="0">Inactive</option></select>
          <span class="area-field-error" data-error="is_active"></span>
        </div>
        <div class="area-field-error mt-2" id="areaFormErrors" role="alert" aria-live="polite"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm" id="areaSave"><i class="fas fa-check mr-1"></i> Save Area</button>
      </div>
    </form>
  </div></div>
</div>
<div class="modal fade bm-area-editor" id="areaDeleteModal" tabindex="-1" role="dialog" aria-labelledby="areaDeleteTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:470px" role="document"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="areaDeleteTitle">Delete Area?</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
    <div class="modal-body">
      <p>Delete <strong id="areaDeleteName"></strong>?</p>
      <p class="text-muted mb-0">Only an unlinked area can be deleted. Areas referenced by business records are protected.</p>
      <div class="area-field-error mt-2" id="areaDeleteError" role="alert"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancel</button><button type="button" class="btn btn-danger btn-sm" id="areaDeleteConfirm">Delete Area</button></div>
  </div></div>
</div>
@endsection

@section('js')
<script>
$(function(){
  'use strict';
  var areas=@json($areaRows),canEdit=@json(auth()->user()->can('update area')),canDelete=@json(auth()->user()->can('delete area'));
  var currentPage=1,sortKey='id',sortDescending=false,editing=null;
  var columnSearch={},dateRanges={},statusSelection='';
  function value(v){return v===undefined||v===null?'':String(v);}
  function columnValue(s,key){
    if(key==='is_active')return Number(s.is_active)===1?'Active':'Inactive';
    return value(s[key]);
  }
  function visibleRows(){
    return areas.filter(function(s){
      if(statusSelection && columnValue(s,'is_active')!==statusSelection)return false;
      if(!Object.keys(columnSearch).every(function(k){return columnValue(s,k).toLowerCase().indexOf(columnSearch[k].toLowerCase())>=0;}))return false;
      return Object.keys(dateRanges).every(function(k){
        var range=dateRanges[k],date=value(s[k]).slice(0,10);
        return (!range.from&&!range.to)||(date!==''&&(!range.from||date>=range.from)&&(!range.to||date<=range.to));
      });
    }).sort(function(a,b){
      var x=a[sortKey],y=b[sortKey],result;
      if(['id','is_active'].indexOf(sortKey)>=0)result=Number(x)-Number(y);
      else result=columnValue(a,sortKey).localeCompare(columnValue(b,sortKey),undefined,{numeric:true,sensitivity:'base'});
      if(result===0)result=Number(a.id)-Number(b.id);
      return sortDescending?-result:result;
    });
  }
  var widget=$.financeVoucherComponents.createFilter({kind:'heading',accent:'#2563eb'});
  var activeColumn=null,activeButton=null;
  function closeColumn(){
    widget.close();if(activeButton)activeButton.attr('aria-expanded','false');
    activeColumn=null;activeButton=null;
  }
  function updateHeadings(){
    $('#areasTable .area-col-trigger').each(function(){
      var key=$(this).data('field'),range=dateRanges[key]||{};
      $(this).toggleClass('area-col-active',!!columnSearch[key]||!!(range.from||range.to)||(key==='is_active'&&!!statusSelection)||sortKey===key)
        .attr('aria-sort',sortKey===key?(sortDescending?'descending':'ascending'):'none');
    });
  }
  function render(){
    var matches=visibleRows(),size=Number($('#areaPageSize').val())||25;
    var pages=Math.max(1,Math.ceil(matches.length/size));currentPage=Math.min(currentPage,pages);
    var start=(currentPage-1)*size,shown=matches.slice(start,start+size),$body=$('#areaRows').empty();
    if($.blueActionMenu)$.blueActionMenu.close();
    if(!shown.length)$body.append($('<tr>').append($('<td>',{colspan:(canEdit||canDelete)?5:4,'class':'text-center text-muted py-4',text:'No matching areas'})));
    $.each(shown,function(_,s){
      var $tr=$('<tr>');
      $('<td>',{text:s.id}).appendTo($tr);
      $('<td>',{'class':'area-name',text:s.name}).appendTo($tr);
      $('<td>').append($('<span>',{'class':'area-status'+(Number(s.is_active)===1?'':' inactive'),text:columnValue(s,'is_active')})).appendTo($tr);
      $('<td>',{'class':'text-muted',text:s.created_at||'—'}).appendTo($tr);
      if(canEdit||canDelete){
        var $action=$('<td>',{'class':'blue-actions-cell'});
        if(canEdit)$action.append($('<button>',{type:'button','class':'btn btn-outline-primary btn-sm btn-edit','data-id':s.id,'data-blue-action-label':'Edit area',text:'Edit'}));
        if(canDelete)$action.append($('<button>',{type:'button','class':'btn btn-outline-danger btn-sm btn-delete','data-id':s.id,'data-blue-action-label':'Delete area',text:'Delete'}));
        $tr.append($action);
      }
      $body.append($tr);
    });
    if($.blueActionMenu)$.blueActionMenu.enhance($body.find('td.blue-actions-cell'));
    $body.attr('aria-busy','false');
    if(exportControls)exportControls.refresh();
    updateHeadings();
    $('#areaSummary').text('Showing '+(matches.length?start+1:0)+'–'+Math.min(start+size,matches.length)+' of '+matches.length+' areas');
    if($.blueVoucherPagination)$.blueVoucherPagination.render({container:'#areaPages',summary:'#areaSummary',total:matches.length,length:size,start:start,buttonClass:'dv-page-btn',onPage:function(next){currentPage=Math.floor(next/size)+1;render();}});
  }
  $('#areasTable').on('click','.area-col-trigger',function(event){
    event.preventDefault();event.stopPropagation();
    var $button=$(this),key=$button.data('field');
    if(activeColumn===key){closeColumn();return;}
    closeColumn();activeColumn=key;activeButton=$button;
    $button.attr('aria-expanded','true');
    widget.setTitle($.trim($button.closest('th').find('span').first().text()));
    if(key==='is_active'){
      var $select=$('<select>',{'class':'form-control form-control-sm','aria-label':'Filter status'})
        .append($('<option>',{value:'',text:'All statuses'}),$('<option>',{value:'Active',text:'Active'}),$('<option>',{value:'Inactive',text:'Inactive'}))
        .val(statusSelection);
      widget.$control.empty().append($select);
      $select.on('change',function(){statusSelection=$(this).val();currentPage=1;render();});
    }else if(key==='created_at'){
      widget.dates(dateRanges[key]||{from:'',to:''},function(part,date){
        if(!dateRanges[key])dateRanges[key]={from:'',to:''};
        dateRanges[key][part]=date;currentPage=1;render();
      });
    }else{
      widget.search(columnSearch[key]||'',function(query){
        if(query)columnSearch[key]=query;else delete columnSearch[key];
        currentPage=1;render();
      },'Search '+$.trim($button.closest('th').text())+'...');
    }
    widget.position($button);
    widget.$control.find('input,select').first().trigger('focus');
  });
  widget.$popup.find('.bm-col-asc,.bm-col-desc').on('click',function(){
    if(activeColumn===null)return;
    sortKey=activeColumn;sortDescending=$(this).hasClass('bm-col-desc');
    closeColumn();currentPage=1;render();
  });
  widget.$popup.find('.bm-col-clear').on('click',function(){
    if(activeColumn===null)return;
    delete columnSearch[activeColumn];delete dateRanges[activeColumn];
    if(activeColumn==='is_active')statusSelection='';
    if(sortKey===activeColumn){sortKey='id';sortDescending=false;}
    closeColumn();currentPage=1;render();
  });
  $(document).on('click.areaFilters',function(e){
    if(!$(e.target).closest(widget.$popup).length&&!$(e.target).closest('.area-col-trigger').length)closeColumn();
  }).on('keydown.areaFilters',function(e){if(e.key==='Escape')closeColumn();});
  $('#areaPageSize').on('change',function(){currentPage=1;render();});
  $('#areaReset').on('click',function(){
    columnSearch={};dateRanges={};statusSelection='';sortKey='id';sortDescending=false;currentPage=1;
    $('#areaPageSize').val('25');closeColumn();render();
  });
  function inform(message,error){
    if(window.toastr){error?toastr.error(message):toastr.success(message);}
    else if(window.Swal)window.Swal.fire({icon:error?'error':'success',text:message});
    else $('#areaFormErrors').text(message);
  }
  function openEditor(area){
    editing=area||null;
    $('#areaForm')[0].reset();$('#areaFormErrors,.area-field-error').text('');
    $('#areaEditorTitle').text(area?'Edit Area':'New Area');
    $('#areaEditId').val(area?area.id:'');
    $('#areaName').val(area?area.name:'');
    $('#areaActive').val(area?String(area.is_active):'1');
    $('#areaEditor').modal({backdrop:'static',keyboard:false,show:true});
  }
  $('#areaAdd').on('click',function(){openEditor(null);});
  $('#areaRows').on('click','.btn-edit',function(){
    var id=Number($(this).data('id')),s=areas.find(function(item){return item.id===id;});
    if(s)openEditor(s);
  });
  var pendingDeleteId=null;
  $('#areaRows').on('click','.btn-delete',function(){
    pendingDeleteId=Number($(this).data('id'));
    var area=areas.find(function(item){return item.id===pendingDeleteId;});
    if(!area)return;
    $('#areaDeleteName').text(area.name);
    $('#areaDeleteError').text('');
    $('#areaDeleteModal').modal({backdrop:'static',keyboard:false,show:true});
  });
  $('#areaDeleteConfirm').on('click',function(){
    if(!pendingDeleteId)return;
    var $button=$(this).prop('disabled',true);
    $('#areaDeleteError').text('');
    $.ajax({url:@json(route('area.destroy')),type:'POST',
      data:{_token:@json(csrf_token()),_method:'DELETE',id:pendingDeleteId},
      dataType:'json',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}})
      .done(function(response){
        $('#areaDeleteModal').modal('hide');inform(response.message||'Area deleted.');
        (function(){var stopSkeleton=$.blueModernDirectoryTools.skeleton('.area-table-wrap');return $.ajax({url:@json(route('area.index')),dataType:'json',headers:{Accept:'application/json'}})
          .done(function(reply){areas=reply.data||[];render();})
          .fail(function(){inform('Deleted, but area list could not refresh.',true);}).always(function(){stopSkeleton();});}());
      }).fail(function(xhr){
        var message=(xhr.responseJSON||{}).message||'Area could not be deleted.';
        $('#areaDeleteError').text(message);inform(message,true);
      }).always(function(){$button.prop('disabled',false);});
  });
  $('#areaForm').on('submit',function(event){
    event.preventDefault();
    $('#areaFormErrors,.area-field-error').text('');
    if(!$.trim($('#areaName').val())){$('#areaFormErrors').text('Area name is required.');return;}
    var $save=$('#areaSave').prop('disabled',true).text('Saving...');
    var payload=$(this).serializeArray();
    if(editing)payload.push({name:'_method',value:'PUT'});
    $.ajax({url:editing?@json(route('area.update')):@json(route('area.store')),
      type:'POST',data:$.param(payload),dataType:'json',
      headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}})
    .done(function(response){
      $('#areaEditor').modal('hide');inform(response.message||'Area saved.');
      (function(){var stopSkeleton=$.blueModernDirectoryTools.skeleton('.area-table-wrap');return $.ajax({url:@json(route('area.index')),dataType:'json',headers:{Accept:'application/json'}})
        .done(function(reply){areas=reply.data||[];render();})
        .fail(function(){inform('Area saved, but list refresh failed. Reload the page.',true);}).always(function(){stopSkeleton();});}());
    }).fail(function(xhr){
      var body=xhr.responseJSON||{},errors=body.errors||{};
      $.each(errors,function(key,messages){$('#areaForm').find('[data-error="'+key+'"]').text(messages[0]||'Invalid value');});
      $('#areaFormErrors').text(body.message||'Unable to save area.');
      inform(body.message||'Unable to save area.',true);
    }).always(function(){$save.prop('disabled',false).html('<i class="fas fa-check mr-1"></i> Save Area');});
  });
  var exportControls=$.blueModernDirectoryTools.mount({
    card:'.area-card',table:'#areasTable',title:'Areas List',
    getRows:function(){return visibleRows().map(function(s){return [s.id,s.name,columnValue(s,'is_active'),s.created_at||'—'];});}
  });
  render();
});
</script>
@endsection
