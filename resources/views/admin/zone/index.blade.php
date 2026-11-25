@extends('admin.layouts.master')
@section('title', 'Sectors List')

@push('style')
<style>
.bm-sectors{--voucher-accent:#2563eb;--fvd-accent:#2563eb;--fvd-pop-accent:#2563eb;padding:15px 16px 10px;color:#263b56;font-size:13px;min-height:calc(100vh - 175px);display:flex;flex-direction:column}
.bm-sectors *{box-sizing:border-box}
.bm-sectors .sector-heading{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:13px}
.bm-sectors .sector-heading h2{font-size:19px;font-weight:750;color:#173a75;margin:0}
.bm-sectors .sector-heading small{font-size:11px;color:#7889a0}
.bm-sectors .sector-card{background:#fff;border:1px solid #dae4f2;border-top:3px solid #2563eb;border-radius:11px;overflow:hidden;box-shadow:0 3px 13px #122b460a;flex:1 1 auto;min-height:350px;display:flex;flex-direction:column}
.bm-sectors .sector-table-wrap{width:100%;overflow:auto;min-height:0;flex:1 1 auto}
.bm-sectors .sector-table{width:100%;margin:0;font-size:14px;white-space:nowrap;border-collapse:separate!important;border-spacing:0!important}
.bm-sectors .sector-table thead th{background:#f0f5fc;color:#294366;font-weight:750;font-size:13px;padding:11px 12px;border-bottom:2px solid #2563eb!important;border-right:1.5px solid #b6c7da!important}
.bm-sectors .sector-table tbody td{padding:11px 12px!important;border-top:1.5px solid #c5d2e2!important;border-right:1.5px solid #c5d2e2!important;vertical-align:middle;height:48px!important}
.bm-sectors .sector-table thead th:last-child,.bm-sectors .sector-table tbody td:last-child{border-right:0!important}
.bm-sectors .sector-table tbody tr:nth-child(even){background:#f1f5fa!important}
.bm-sectors .sector-table tbody tr:nth-child(odd){background:#ffffff!important}
.bm-sectors .sector-table tbody tr:hover{background:#e8f2ff!important}
.bm-sectors .sector-name{font-weight:750;color:#1d54a0}
.bm-sectors .sector-status{display:inline-flex;align-items:center;border-radius:14px;font-size:12px;padding:4px 9px;background:#e8f8ef;color:#16754a;font-weight:700}
.bm-sectors .sector-status.inactive{background:#f3f4f6;color:#69788b}
.bm-sectors .sector-table th .sector-col-trigger{float:right;border:0;background:transparent;color:#607b9e;border-radius:5px;padding:2px 6px;cursor:pointer;font-size:11px}
.bm-sectors .sector-table th .sector-col-trigger:hover,.bm-sectors .sector-table th .sector-col-trigger.sector-col-active{background:#dfedff;color:#155fc3}
.bm-sectors .sector-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;padding:8px 12px;border-top:1px solid #e7edf6;min-height:46px;flex:0 0 auto;margin-top:auto}
.bm-sectors .sector-footer-controls{display:flex;align-items:center;gap:8px}
.bm-sectors .sector-footer-controls select{height:29px;padding:3px 7px;font-size:12px;border:1px solid #d5e0ec;border-radius:5px;background:white}
.bm-sectors .sector-footer-controls button{font-size:11px}
.bm-sectors .sector-pages{display:flex;gap:4px;align-items:center}
.bm-sectors .sector-pages .dv-page-btn{border:1px solid #adcaf0;background:white;color:#2563eb;border-radius:5px;min-width:30px;height:30px;font-size:11px}
.bm-sectors .sector-pages .dv-page-btn.active{background:#2563eb;color:white}
.bm-sectors .sector-pages .dv-page-btn:disabled{opacity:.45}
.bm-sectors .sector-skeleton{height:12px;display:block;max-width:100%;border-radius:5px;background:linear-gradient(90deg,#edf2f8 25%,#f9fbfe 50%,#edf2f8 75%);background-size:200% 100%;animation:bmSectorShimmer 1.4s ease infinite}
.bm-sectors .sector-skeleton.long{width:140px}.bm-sectors .sector-skeleton.mid{width:90px}.bm-sectors .sector-skeleton.short{width:38px}
@keyframes bmSectorShimmer{to{background-position-x:-200%}}
@media(prefers-reduced-motion:reduce){.bm-sectors .sector-skeleton{animation:none}}
.bm-sector-editor .modal-dialog{max-width:650px;width:calc(100% - 32px);margin:25px auto}
.bm-sector-editor .modal-content{border:0;border-radius:13px;overflow:hidden;box-shadow:0 22px 65px #0e203850}
.bm-sector-editor .modal-header{background:#f7faff;border-bottom:1px solid #dce7f3;padding:15px 20px}
.bm-sector-editor .modal-title{font-size:16px;font-weight:800;color:#193d73}
.bm-sector-editor .modal-body{padding:17px 20px}
.bm-sector-editor .modal-footer{background:#fff;padding:12px 20px;border-top:1px solid #e1eaf4}
.bm-sector-editor label{font-size:13px;font-weight:700;color:#344d6c;margin-bottom:5px}
.bm-sector-editor .form-control{font-size:13px;border-color:#d2deeb;border-radius:7px;min-height:36px}
.bm-sector-editor .sector-field-error{display:block;color:#b42318;font-size:12px;margin-top:4px}
@media(max-width:767px){.bm-sectors{min-height:calc(100vh - 160px)}.bm-sectors .sector-table-wrap{overflow-x:auto}}
</style>
@endpush

@section('content')
<div class="content-wrapper bm-ui bm-voucher-directory bm-sectors" id="sectorsApp">
  <div class="sector-heading">
    <div><h2>Sectors List</h2><small>Manage business sectors and their status</small></div>
    <div class="d-flex align-items-center" style="gap:7px">
      @can('create sector')<button class="btn btn-primary btn-sm" type="button" id="sectorAdd"><i class="fas fa-plus mr-1"></i> New Sector</button>@endcan
      <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">Dashboard</a>
    </div>
  </div>
  <div class="sector-card">
    <div class="sector-table-wrap">
      <table class="table table-hover sector-table" id="sectorsTable">
        <thead><tr>
          @foreach (['id'=>'#','zone_name'=>'Sector Name','is_active'=>'Status','created_at'=>'Created'] as $field=>$label)
            <th><span>{{ $label }}</span><button type="button" class="sector-col-trigger" data-field="{{ $field }}" aria-label="Sort or filter {{ $label }}" aria-expanded="false" aria-haspopup="dialog"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
          @endforeach
          @canany(['update sector','delete sector'])<th>Actions</th>@endcanany
        </tr></thead>
        <tbody id="sectorRows" aria-busy="true">
          @for ($i=0;$i<5;$i++)
          <tr aria-hidden="true">
            <td><span class="sector-skeleton short"></span></td>
            <td><span class="sector-skeleton long"></span></td>
            <td><span class="sector-skeleton mid"></span></td>
            <td><span class="sector-skeleton mid"></span></td>
            @canany(['update sector','delete sector'])<td><span class="sector-skeleton short"></span></td>@endcanany
          </tr>
          @endfor
        </tbody>
      </table>
    </div>
    <div class="sector-foot">
      <div class="sector-footer-controls">
        <small class="text-muted" id="sectorSummary"></small>
        <select id="sectorPageSize" aria-label="Rows per page"><option>10</option><option selected>25</option><option>50</option><option>100</option></select>
        <button class="btn btn-outline-secondary btn-sm" type="button" id="sectorReset">Reset filters</button>
      </div>
      <div class="sector-pages" id="sectorPages"></div>
    </div>
  </div>
</div>
@endsection

@section('modal')
<div class="modal fade bm-sector-editor" id="sectorEditor" tabindex="-1" role="dialog" aria-labelledby="sectorEditorTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
    <div class="modal-header">
      <div><h5 class="modal-title" id="sectorEditorTitle">New Sector</h5><small class="text-muted">Business sector details</small></div>
      <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
    <form id="sectorForm" data-ajax="off" method="POST" action="{{ route('project.zone.store') }}" novalidate>
      @csrf
      <input type="hidden" name="id" id="sectorEditId">
      <div class="modal-body">
        <div class="form-group">
          <label for="sectorName">Sector Name *</label>
          <input class="form-control" name="zone_name" id="sectorName" required maxlength="255">
          <span class="sector-field-error" data-error="zone_name"></span>
        </div>
        <div class="form-group mb-0">
          <label for="sectorActive">Status *</label>
          <select name="is_active" id="sectorActive" class="form-control"><option value="1">Active</option><option value="0">Inactive</option></select>
          <span class="sector-field-error" data-error="is_active"></span>
        </div>
        <div class="sector-field-error mt-2" id="sectorFormErrors" role="alert" aria-live="polite"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm" id="sectorSave"><i class="fas fa-check mr-1"></i> Save Sector</button>
      </div>
    </form>
  </div></div>
</div>
<div class="modal fade bm-sector-editor" id="sectorDeleteModal" tabindex="-1" role="dialog" aria-labelledby="sectorDeleteTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:470px" role="document"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="sectorDeleteTitle">Delete Sector?</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
    <div class="modal-body">
      <p>Delete <strong id="sectorDeleteName"></strong>?</p>
      <p class="text-muted mb-0">Only an unlinked sector can be deleted. Sectors referenced by business records are protected.</p>
      <div class="sector-field-error mt-2" id="sectorDeleteError" role="alert"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancel</button><button type="button" class="btn btn-danger btn-sm" id="sectorDeleteConfirm">Delete Sector</button></div>
  </div></div>
</div>
@endsection

@section('js')
<script>
$(function(){
  'use strict';
  var sectors=@json($sectorRows),canEdit=@json(auth()->user()->can('update sector')),canDelete=@json(auth()->user()->can('delete sector'));
  var currentPage=1,sortKey='id',sortDescending=false,editing=null;
  var columnSearch={},dateRanges={},statusSelection='';
  function value(v){return v===undefined||v===null?'':String(v);}
  function columnValue(s,key){
    if(key==='is_active')return Number(s.is_active)===1?'Active':'Inactive';
    return value(s[key]);
  }
  function visibleRows(){
    return sectors.filter(function(s){
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
    $('#sectorsTable .sector-col-trigger').each(function(){
      var key=$(this).data('field'),range=dateRanges[key]||{};
      $(this).toggleClass('sector-col-active',!!columnSearch[key]||!!(range.from||range.to)||(key==='is_active'&&!!statusSelection)||sortKey===key)
        .attr('aria-sort',sortKey===key?(sortDescending?'descending':'ascending'):'none');
    });
  }
  function render(){
    var matches=visibleRows(),size=Number($('#sectorPageSize').val())||25;
    var pages=Math.max(1,Math.ceil(matches.length/size));currentPage=Math.min(currentPage,pages);
    var start=(currentPage-1)*size,shown=matches.slice(start,start+size),$body=$('#sectorRows').empty();
    if($.blueActionMenu)$.blueActionMenu.close();
    if(!shown.length)$body.append($('<tr>').append($('<td>',{colspan:(canEdit||canDelete)?5:4,'class':'text-center text-muted py-4',text:'No matching sectors'})));
    $.each(shown,function(_,s){
      var $tr=$('<tr>');
      $('<td>',{text:s.id}).appendTo($tr);
      $('<td>',{'class':'sector-name',text:s.zone_name}).appendTo($tr);
      $('<td>').append($('<span>',{'class':'sector-status'+(Number(s.is_active)===1?'':' inactive'),text:columnValue(s,'is_active')})).appendTo($tr);
      $('<td>',{'class':'text-muted',text:s.created_at||'—'}).appendTo($tr);
      if(canEdit||canDelete){
        var $action=$('<td>',{'class':'blue-actions-cell'});
        if(canEdit)$action.append($('<button>',{type:'button','class':'btn btn-outline-primary btn-sm btn-edit','data-id':s.id,'data-blue-action-label':'Edit sector',text:'Edit'}));
        if(canDelete)$action.append($('<button>',{type:'button','class':'btn btn-outline-danger btn-sm btn-delete','data-id':s.id,'data-blue-action-label':'Delete sector',text:'Delete'}));
        $tr.append($action);
      }
      $body.append($tr);
    });
    if($.blueActionMenu)$.blueActionMenu.enhance($body.find('td.blue-actions-cell'));
    $body.attr('aria-busy','false');
    updateHeadings();
    $('#sectorSummary').text('Showing '+(matches.length?start+1:0)+'–'+Math.min(start+size,matches.length)+' of '+matches.length+' sectors');
    if($.blueVoucherPagination)$.blueVoucherPagination.render({container:'#sectorPages',summary:'#sectorSummary',total:matches.length,length:size,start:start,buttonClass:'dv-page-btn',onPage:function(next){currentPage=Math.floor(next/size)+1;render();}});
  }
  $('#sectorsTable').on('click','.sector-col-trigger',function(event){
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
  $(document).on('click.sectorFilters',function(e){
    if(!$(e.target).closest(widget.$popup).length&&!$(e.target).closest('.sector-col-trigger').length)closeColumn();
  }).on('keydown.sectorFilters',function(e){if(e.key==='Escape')closeColumn();});
  $('#sectorPageSize').on('change',function(){currentPage=1;render();});
  $('#sectorReset').on('click',function(){
    columnSearch={};dateRanges={};statusSelection='';sortKey='id';sortDescending=false;currentPage=1;
    $('#sectorPageSize').val('25');closeColumn();render();
  });
  function inform(message,error){
    if(window.toastr){error?toastr.error(message):toastr.success(message);}
    else if(window.Swal)window.Swal.fire({icon:error?'error':'success',text:message});
    else $('#sectorFormErrors').text(message);
  }
  function openEditor(sector){
    editing=sector||null;
    $('#sectorForm')[0].reset();$('#sectorFormErrors,.sector-field-error').text('');
    $('#sectorEditorTitle').text(sector?'Edit Sector':'New Sector');
    $('#sectorEditId').val(sector?sector.id:'');
    $('#sectorName').val(sector?sector.zone_name:'');
    $('#sectorActive').val(sector?String(sector.is_active):'1');
    $('#sectorEditor').modal({backdrop:'static',keyboard:false,show:true});
  }
  $('#sectorAdd').on('click',function(){openEditor(null);});
  $('#sectorRows').on('click','.btn-edit',function(){
    var id=Number($(this).data('id')),s=sectors.find(function(item){return item.id===id;});
    if(s)openEditor(s);
  });
  var pendingDeleteId=null;
  $('#sectorRows').on('click','.btn-delete',function(){
    pendingDeleteId=Number($(this).data('id'));
    var sector=sectors.find(function(item){return item.id===pendingDeleteId;});
    if(!sector)return;
    $('#sectorDeleteName').text(sector.zone_name);
    $('#sectorDeleteError').text('');
    $('#sectorDeleteModal').modal({backdrop:'static',keyboard:false,show:true});
  });
  $('#sectorDeleteConfirm').on('click',function(){
    if(!pendingDeleteId)return;
    var $button=$(this).prop('disabled',true);
    $('#sectorDeleteError').text('');
    $.ajax({url:@json(route('project.zone.destroy')),type:'POST',
      data:{_token:@json(csrf_token()),_method:'DELETE',id:pendingDeleteId},
      dataType:'json',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}})
      .done(function(response){
        $('#sectorDeleteModal').modal('hide');inform(response.message||'Sector deleted.');
        $.ajax({url:@json(route('project.zone.index')),dataType:'json',headers:{Accept:'application/json'}})
          .done(function(reply){sectors=reply.data||[];render();})
          .fail(function(){inform('Deleted, but sector list could not refresh.',true);});
      }).fail(function(xhr){
        var message=(xhr.responseJSON||{}).message||'Sector could not be deleted.';
        $('#sectorDeleteError').text(message);inform(message,true);
      }).always(function(){$button.prop('disabled',false);});
  });
  $('#sectorForm').on('submit',function(event){
    event.preventDefault();
    $('#sectorFormErrors,.sector-field-error').text('');
    if(!$.trim($('#sectorName').val())){$('#sectorFormErrors').text('Sector name is required.');return;}
    var $save=$('#sectorSave').prop('disabled',true).text('Saving...');
    var payload=$(this).serializeArray();
    if(editing)payload.push({name:'_method',value:'PUT'});
    $.ajax({url:editing?@json(route('project.zone.update')):@json(route('project.zone.store')),
      type:'POST',data:$.param(payload),dataType:'json',
      headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}})
    .done(function(response){
      $('#sectorEditor').modal('hide');inform(response.message||'Sector saved.');
      $.ajax({url:@json(route('project.zone.index')),dataType:'json',headers:{Accept:'application/json'}})
        .done(function(reply){sectors=reply.data||[];render();})
        .fail(function(){inform('Sector saved, but list refresh failed. Reload the page.',true);});
    }).fail(function(xhr){
      var body=xhr.responseJSON||{},errors=body.errors||{};
      $.each(errors,function(key,messages){$('#sectorForm').find('[data-error="'+key+'"]').text(messages[0]||'Invalid value');});
      $('#sectorFormErrors').text(body.message||'Unable to save sector.');
      inform(body.message||'Unable to save sector.',true);
    }).always(function(){$save.prop('disabled',false).html('<i class="fas fa-check mr-1"></i> Save Sector');});
  });
  render();
});
</script>
@endsection
