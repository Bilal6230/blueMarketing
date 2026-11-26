@extends('admin.layouts.master')
@section('title', 'Project List')

@push('style')
<style>
.bm-projects{--voucher-accent:#2563eb;--fvd-accent:#2563eb;--fvd-pop-accent:#2563eb;padding:15px 16px 10px;color:#263b56;font-size:13px;min-height:calc(100vh - 175px);display:flex;flex-direction:column}
.bm-projects *{box-sizing:border-box}
.bm-projects .project-heading{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:13px}
.bm-projects .project-heading h2{font-size:19px;font-weight:750;color:#173a75;margin:0}
.bm-projects .project-heading small{font-size:11px;color:#7889a0}
.bm-projects .project-card{background:#fff;border:1px solid #dae4f2;border-top:3px solid #2563eb;border-radius:11px;overflow:hidden;box-shadow:0 3px 13px #122b460a;flex:1 1 auto;min-height:350px;display:flex;flex-direction:column}
.bm-projects .project-toolbar{display:flex;align-items:center;gap:9px;flex-wrap:wrap;padding:12px;border-bottom:1px solid #e8eef6}
.bm-projects .project-control{height:34px;border:1px solid #d5e0ec;border-radius:7px;background:#fff;color:#304860;font-size:12px;padding:6px 10px;min-width:90px}
.bm-projects .project-control:focus{border-color:#3b82f6;outline:0;box-shadow:0 0 0 2px #3b82f622}
.bm-projects .project-search{flex:1 1 280px;min-width:190px}
.bm-projects .project-table-wrap{width:100%;overflow:auto;min-height:0;flex:1 1 auto}
.bm-projects .project-table{width:100%;margin:0;font-size:14px;white-space:nowrap}
.bm-projects .project-table{border-collapse:separate!important;border-spacing:0!important}
.bm-projects .project-table thead th{background:#f0f5fc;color:#294366;font-weight:750;font-size:13px;padding:11px 12px;border-bottom:2px solid #2563eb!important;border-right:1.5px solid #b6c7da!important}
.bm-projects .project-table tbody td{padding:11px 12px!important;border-top:1.5px solid #c5d2e2!important;border-right:1.5px solid #c5d2e2!important;vertical-align:middle;height:48px!important}
.bm-projects .project-table thead th:last-child,.bm-projects .project-table tbody td:last-child{border-right:0!important}
.bm-projects .project-table tbody tr:nth-child(even){background:#f1f5fa!important}
.bm-projects .project-table tbody tr:nth-child(odd){background:#ffffff!important}
.bm-projects .project-table tbody tr:hover{background:#e8f2ff!important}
.bm-projects .project-name{font-weight:750;color:#1d54a0}
.bm-projects .project-address{white-space:normal;min-width:165px;max-width:310px}
.bm-projects .project-code{background:#edf4fe;color:#3469a9;border-radius:5px;padding:4px 7px;display:inline-block}
.bm-projects .project-status{display:inline-flex;align-items:center;border-radius:14px;font-size:12px;padding:4px 9px;background:#e8f8ef;color:#16754a;font-weight:700}
.bm-projects .project-status.inactive{background:#f3f4f6;color:#69788b}
.bm-projects .project-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;padding:8px 12px;border-top:1px solid #e7edf6;min-height:46px;flex:0 0 auto;margin-top:auto}
.bm-projects .project-pages{display:flex;gap:4px;align-items:center}
.bm-projects .project-pages .dv-page-btn{border:1px solid #adcaf0;background:white;color:#2563eb;border-radius:5px;min-width:30px;height:30px;font-size:11px}
.bm-projects .project-pages .dv-page-btn.active{background:#2563eb;color:white}
.bm-projects .project-pages .dv-page-btn:disabled{opacity:.45}
.bm-projects .project-table th .project-col-trigger{float:right;border:0;background:transparent;color:#607b9e;border-radius:5px;padding:2px 6px;cursor:pointer;font-size:11px}
.bm-projects .project-table th .project-col-trigger:hover,.bm-projects .project-table th .project-col-trigger.project-col-active{background:#dfedff;color:#155fc3}
.bm-projects .project-footer-controls{display:flex;align-items:center;gap:8px}
.bm-projects .project-footer-controls select{height:29px;padding:3px 7px;font-size:12px;border:1px solid #d5e0ec;border-radius:5px;background:white}
.bm-projects .project-footer-controls button{font-size:11px}
@media(max-width:767px){.bm-projects{min-height:calc(100vh - 160px)}.bm-projects .project-table-wrap{overflow-x:auto}}
.bm-projects .project-skeleton{height:12px;display:block;max-width:100%;border-radius:5px;background:linear-gradient(90deg,#edf2f8 25%,#f9fbfe 50%,#edf2f8 75%);background-size:200% 100%;animation:bmProjectShimmer 1.4s ease infinite}
.bm-projects .project-skeleton.long{width:140px}.bm-projects .project-skeleton.mid{width:90px}.bm-projects .project-skeleton.short{width:38px}
@keyframes bmProjectShimmer{to{background-position-x:-200%}}
@media(prefers-reduced-motion:reduce){.bm-projects .project-skeleton{animation:none}}
.bm-project-editor .modal-dialog{max-width:835px;width:calc(100% - 32px);margin:25px auto}
.bm-project-editor .modal-content{border:0;border-radius:13px;overflow:hidden;box-shadow:0 22px 65px #0e203850}
.bm-project-editor .modal-header{background:#f7faff;border-bottom:1px solid #dce7f3;padding:15px 20px}
.bm-project-editor .modal-title{font-size:16px;font-weight:800;color:#193d73}
.bm-project-editor .modal-body{padding:17px 20px;max-height:calc(100vh - 205px);overflow-y:auto}
.bm-project-editor .modal-footer{background:#fff;padding:12px 20px;border-top:1px solid #e1eaf4}
.bm-project-editor label{font-size:12px;font-weight:700;color:#344d6c;margin-bottom:5px}
.bm-project-editor .form-control{font-size:12px;border-color:#d2deeb;border-radius:7px;min-height:36px}
.bm-project-editor .project-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}
.bm-project-editor .project-span-full{grid-column:1/-1}
.bm-project-editor .project-field-error{display:block;color:#b42318;font-size:11px;min-height:0;margin-top:3px}
.bm-project-editor .project-note{color:#6b7e95;font-size:11px;background:#f5f8fc;border:1px solid #e4ebf4;padding:8px 10px;border-radius:7px}
@media(max-width:650px){.bm-project-editor .project-form-grid{grid-template-columns:1fr}.bm-projects{padding:12px 8px}}
</style>
@endpush

@section('content')
<div class="content-wrapper bm-ui bm-voucher-directory bm-projects" id="projectsApp">
  <div class="project-heading">
    <div><h2>Projects</h2><small>Manage project details, locations and charges · All projects</small></div>
    <div class="d-flex align-items-center" style="gap:7px">
      @can('create project')<button class="btn btn-primary btn-sm" type="button" id="projectAdd"><i class="fas fa-plus mr-1"></i> New Project</button>@endcan
      <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">Dashboard</a>
    </div>
  </div>
  <div class="project-card">
    <div class="bm-modern-export-toolbar bm-export-placeholder" aria-label="Table export toolbar loading">
  <button type="button" class="bm-modern-export-btn" disabled>Copy</button>
  <button type="button" class="bm-modern-export-btn" disabled>CSV</button>
  <button type="button" class="bm-modern-export-btn" disabled>Excel</button>
  <button type="button" class="bm-modern-export-btn" disabled>PDF</button>
  <button type="button" class="bm-modern-export-btn" disabled>Print</button>
  <button type="button" class="bm-modern-export-btn bm-modern-col-toggle" disabled>Column Visibility</button>
</div>
    <div class="project-table-wrap">
      <table class="table table-hover project-table" id="projectsTable">
        <thead><tr>
          @foreach (['id'=>'#','project'=>'Project','project_code'=>'Code','address'=>'Address / Location','opening_date'=>'Project Dates','corner_charge_percent'=>'Corner / Facing','is_active'=>'Status','created_at'=>'Created'] as $field => $label)
            <th><span>{{ $label }}</span><button type="button" class="project-col-trigger" data-field="{{ $field }}" aria-label="Sort or filter {{ $label }}" aria-expanded="false" aria-haspopup="dialog"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
          @endforeach
          @canany(['update project','delete project'])<th>Actions</th>@endcanany
        </tr></thead>
        <tbody id="projectRows" aria-busy="true">
          @for ($i=0;$i<5;$i++)
          <tr aria-hidden="true">
            <td><span class="project-skeleton short"></span></td>
            <td><span class="project-skeleton long"></span></td>
            <td><span class="project-skeleton mid"></span></td>
            <td><span class="project-skeleton long"></span></td>
            <td><span class="project-skeleton mid"></span></td>
            <td><span class="project-skeleton mid"></span></td>
            <td><span class="project-skeleton short"></span></td>
            <td><span class="project-skeleton mid"></span></td>
            @canany(['update project','delete project'])<td><span class="project-skeleton short"></span></td>@endcanany
          </tr>
          @endfor
        </tbody>
      </table>
    </div>
    <div class="project-foot">
      <div class="project-footer-controls">
        <small class="text-muted" id="projectSummary"></small>
        <select id="projectPageSize" aria-label="Rows per page"><option>10</option><option selected>25</option><option>50</option><option>100</option></select>
        <button class="btn btn-outline-secondary btn-sm" type="button" id="projectReset">Reset filters</button>
      </div>
      <div class="project-pages" id="projectPages"></div>
    </div>
  </div>
</div>
@endsection

@section('modal')
<div class="modal fade bm-project-editor" id="projectEditor" tabindex="-1" role="dialog" aria-labelledby="projectEditorTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
    <div class="modal-header">
      <div><h5 class="modal-title" id="projectEditorTitle">New Project</h5><small class="text-muted">Project setup and charge settings</small></div>
      <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
    <form id="projectForm" method="POST" action="{{ route('project.store') }}" enctype="multipart/form-data" data-ajax="off" novalidate>
      @csrf
      <input type="hidden" name="id" id="projectEditId">
      <div class="modal-body">
        <div class="project-form-grid">
          <div><label for="projectName">Project Name *</label><input class="form-control" name="project" id="projectName" maxlength="255" required><span class="project-field-error" data-error="project"></span></div>
          <div><label for="projectCode">Project Code</label><input class="form-control" id="projectCode" value="Assigned automatically" readonly></div>
          <div class="project-span-full"><label for="projectAddress">Address *</label><input class="form-control" name="address" id="projectAddress" maxlength="255" required><span class="project-field-error" data-error="address"></span></div>
          <div class="project-span-full"><label for="projectLocation">Location / Details</label><input class="form-control" name="location" id="projectLocation" maxlength="255"></div>
          <div><label for="projectOpening">Opening Date</label><input class="form-control" name="opening_date" id="projectOpening" type="date"><span class="project-field-error" data-error="opening_date"></span></div>
          <div><label for="projectClosing">Closing Date</label><input class="form-control" name="closing_date" id="projectClosing" type="date"><span class="project-field-error" data-error="closing_date"></span></div>
          <div><label for="projectCorner">Corner Charge (%)</label><input class="form-control" type="number" name="corner_charge_percent" id="projectCorner" min="0" max="100" step="0.01" value="0"></div>
          <div><label for="projectFacing">Facing Charge (%)</label><input class="form-control" type="number" name="facing_charge_percent" id="projectFacing" min="0" max="100" step="0.01" value="0"></div>
          <div id="projectEffectiveField" class="project-span-full" style="display:none">
            <label for="projectEffectiveDate">Charges Effective From *</label>
            <input class="form-control" name="charge_effective_from" id="projectEffectiveDate" type="date">
            <span class="project-field-error" data-error="charge_effective_from"></span>
            <small class="text-muted">Required only when an existing project's corner/facing charges change.</small>
          </div>
          <div><label for="projectActive">Status *</label><select name="is_active" id="projectActive" class="form-control"><option value="1">Active</option><option value="0">Inactive</option></select></div>
          <div><label for="projectLogo">Project Logo (optional)</label><div class="d-flex align-items-center" style="gap:12px"><img id="projectLogoPreview" src="{{ asset('images/logo/project-placeholder.svg') }}" alt="Project logo preview" style="width:52px;height:52px;border:1px solid #cad8e5;border-radius:8px;object-fit:contain;background:#fff"><input type="file" accept="image/*" class="form-control-file" name="logo" id="projectLogo"></div><small class="text-muted d-block mt-1">Current logo is kept if no new image is selected.</small><span class="project-field-error" data-error="logo"></span></div>
          <div class="project-span-full project-note"><i class="fas fa-info-circle mr-1"></i> Project codes and system accounting mappings are managed by the existing backend. Editing charges creates an effective-dated revision.</div>
        </div>
        <div class="project-field-error mt-2" id="projectFormErrors" role="alert" aria-live="polite"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm" id="projectSave"><i class="fas fa-check mr-1"></i> Save Project</button>
      </div>
    </form>
  </div></div>
</div>
<div class="modal fade bm-project-editor" id="projectDeleteModal" tabindex="-1" role="dialog" aria-labelledby="projectDeleteTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:470px" role="document"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="projectDeleteTitle">Delete Project?</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
    <div class="modal-body">
      <p>Delete <strong id="projectDeleteName"></strong>?</p>
      <p class="text-muted mb-0">Only a project without linked business records can be deleted. Linked projects are protected.</p>
      <div class="project-field-error mt-2" id="projectDeleteError" role="alert"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancel</button><button type="button" class="btn btn-danger btn-sm" id="projectDeleteConfirm">Delete Project</button></div>
  </div></div>
</div>
@endsection

@section('js')
<script>
$(function(){
  'use strict';
  var projects=@json($projectRows),canEdit=@json(auth()->user()->can('update project')),canDelete=@json(auth()->user()->can('delete project'));
  var currentPage=1,sortKey='id',sortDescending=false,editing=null;
  var columnSearch={},dateRanges={},statusSelection='';
  function field(value){return value===null||value===undefined?'':String(value);}
  function presentDate(value){return field(value).slice(0,10)||'—';}
  var columns=['id','project','project_code','address','opening_date','corner_charge_percent','is_active','created_at'];
  function columnValue(p,key){
    if(key==='address')return field(p.address)+' '+field(p.location);
    if(key==='opening_date')return field(p.opening_date)+' '+field(p.closing_date);
    if(key==='corner_charge_percent')return Number(p.corner_charge_percent||0).toFixed(2)+' / '+Number(p.facing_charge_percent||0).toFixed(2);
    if(key==='is_active')return Number(p.is_active)===1?'Active':'Inactive';
    return field(p[key]);
  }
  function activeRows(){
    return projects.filter(function(p){
      if(statusSelection && columnValue(p,'is_active')!==statusSelection)return false;
      if(!Object.keys(columnSearch).every(function(k){return columnValue(p,k).toLowerCase().indexOf(columnSearch[k].toLowerCase())>=0;}))return false;
      return Object.keys(dateRanges).every(function(k){
        var from=dateRanges[k].from,to=dateRanges[k].to,value=field(p[k]).slice(0,10);
        return (!from&&!to)||(value!==''&&(!from||value>=from)&&(!to||value<=to));
      });
    }).sort(function(a,b){
      var x=a[sortKey],y=b[sortKey],result;
      if(['id','corner_charge_percent','is_active'].indexOf(sortKey)>=0)result=Number(x)-Number(y);
      else result=columnValue(a,sortKey).localeCompare(columnValue(b,sortKey),undefined,{numeric:true,sensitivity:'base'});
      if(result===0)result=Number(a.id)-Number(b.id);
      return sortDescending?-result:result;
    });
  }
  function render(){
    var list=activeRows(),size=Number($('#projectPageSize').val())||25;
    var pages=Math.max(1,Math.ceil(list.length/size));currentPage=Math.min(currentPage,pages);
    var start=(currentPage-1)*size,items=list.slice(start,start+size),$body=$('#projectRows').empty();
    if($.blueActionMenu)$.blueActionMenu.close();
    if(!items.length){
      $body.append($('<tr>').append($('<td>',{colspan:(canEdit||canDelete)?9:8,'class':'text-center text-muted py-4',text:'No matching projects found'})));
    }
    $.each(items,function(_,p){
      var $row=$('<tr>');
      $('<td>',{text:p.id}).appendTo($row);
      $('<td>',{'class':'project-name',text:p.project}).appendTo($row);
      $('<td>').append($('<span>',{'class':'project-code',text:p.project_code||'Legacy'})).appendTo($row);
      var location=field(p.location),$addr=$('<td>',{'class':'project-address'});
      $addr.append($('<div>',{text:p.address||'—'}));
      if(location)$addr.append($('<small>',{'class':'text-muted',text:location}));
      $row.append($addr);
      $('<td>',{'class':'text-muted',text:presentDate(p.opening_date)+' → '+presentDate(p.closing_date)}).appendTo($row);
      $('<td>',{text:Number(p.corner_charge_percent||0).toFixed(2)+'% / '+Number(p.facing_charge_percent||0).toFixed(2)+'%'}).appendTo($row);
      $('<td>').append($('<span>',{'class':'project-status'+(Number(p.is_active)===1?'':' inactive'),text:Number(p.is_active)===1?'Active':'Inactive'})).appendTo($row);
      $('<td>',{'class':'text-muted',text:field(p.created_at)||'—'}).appendTo($row);
      if(canEdit||canDelete){
        var $action=$('<td>',{'class':'blue-actions-cell'});
        if(canEdit)$action.append($('<button>',{type:'button','class':'btn btn-outline-primary btn-sm btn-edit','data-id':p.id,'data-blue-action-label':'Edit project',text:'Edit'}));
        if(canDelete)$action.append($('<button>',{type:'button','class':'btn btn-outline-danger btn-sm btn-delete','data-id':p.id,'data-blue-action-label':'Delete project',text:'Delete'}));
        $row.append($action);
      }
      $body.append($row);
    });
    if($.blueActionMenu)$.blueActionMenu.enhance($body.find('td.blue-actions-cell'));
    $body.attr('aria-busy','false');
    if(exportControls)exportControls.refresh();
    updateColumnButtons();
    $('#projectSummary').text('Showing '+(list.length?start+1:0)+'–'+Math.min(start+size,list.length)+' of '+list.length+' projects');
    if($.blueVoucherPagination){
      $.blueVoucherPagination.render({container:'#projectPages',summary:'#projectSummary',total:list.length,length:size,start:start,buttonClass:'dv-page-btn',onPage:function(next){currentPage=Math.floor(next/size)+1;render();}});
    }
  }
  // Shared CR/DR popover: sorting and filtering live in each column heading.
  var filterWidget=$.financeVoucherComponents.createFilter({kind:'heading',accent:'#2563eb'});
  var $filterPopup=filterWidget.$popup,activeColumn=null,activeButton=null;
  function closeColumnFilter(){
    filterWidget.close();
    if(activeButton)activeButton.attr('aria-expanded','false');
    activeColumn=null;activeButton=null;
  }
  function updateColumnButtons(){
    $('#projectsTable .project-col-trigger').each(function(){
      var key=$(this).data('field');
      var dates=dateRanges[key]||{};
      var active=!!columnSearch[key]||!!(dates.from||dates.to)||(key==='is_active'&&!!statusSelection);
      $(this).toggleClass('project-col-active',active||sortKey===key)
        .attr('aria-sort',sortKey===key?(sortDescending?'descending':'ascending'):'none');
    });
  }
  function applyColumnChange(){currentPage=1;render();}
  $('#projectsTable').on('click','.project-col-trigger',function(event){
    event.preventDefault();event.stopPropagation();
    var $button=$(this),key=$button.data('field');
    if(activeColumn===key){closeColumnFilter();return;}
    closeColumnFilter();activeColumn=key;activeButton=$button;
    $button.attr('aria-expanded','true');
    filterWidget.setTitle($.trim($button.closest('th').find('span').first().text()));
    if(key==='is_active'){
      var $control=filterWidget.$control.empty();
      var $select=$('<select>',{'class':'form-control form-control-sm','aria-label':'Filter status'})
        .append($('<option>',{value:'',text:'All statuses'}),$('<option>',{value:'Active',text:'Active'}),$('<option>',{value:'Inactive',text:'Inactive'}))
        .val(statusSelection);
      $select.on('change',function(){statusSelection=$(this).val();applyColumnChange();});
      $control.append($select);
    }else if(key==='opening_date'||key==='created_at'){
      filterWidget.dates(dateRanges[key]||{from:'',to:''},function(part,value){
        if(!dateRanges[key])dateRanges[key]={from:'',to:''};
        dateRanges[key][part]=value;applyColumnChange();
      });
    }else{
      filterWidget.search(columnSearch[key]||'',function(value){
        if(value)columnSearch[key]=value;else delete columnSearch[key];
        applyColumnChange();
      },'Search '+$.trim($button.closest('th').text())+'...');
    }
    filterWidget.position($button);
    filterWidget.$control.find('input,select').first().trigger('focus');
  });
  $filterPopup.find('.bm-col-asc,.bm-col-desc').on('click',function(){
    if(activeColumn===null)return;
    sortKey=activeColumn;sortDescending=$(this).hasClass('bm-col-desc');
    closeColumnFilter();applyColumnChange();
  });
  $filterPopup.find('.bm-col-clear').on('click',function(){
    if(activeColumn===null)return;
    delete columnSearch[activeColumn];delete dateRanges[activeColumn];
    if(activeColumn==='is_active')statusSelection='';
    if(sortKey===activeColumn){sortKey='id';sortDescending=false;}
    closeColumnFilter();applyColumnChange();
  });
  $(document).on('click.projectFilters',function(event){
    if(!$(event.target).closest($filterPopup).length&&!$(event.target).closest('.project-col-trigger').length)closeColumnFilter();
  }).on('keydown.projectFilters',function(event){if(event.key==='Escape')closeColumnFilter();});
  $('#projectPageSize').on('change',function(){currentPage=1;render();});
  $('#projectReset').on('click',function(){
    columnSearch={};dateRanges={};statusSelection='';currentPage=1;sortKey='id';sortDescending=false;
    $('#projectPageSize').val('25');closeColumnFilter();render();
  });
  function inform(message,error){
    if(window.toastr){error?toastr.error(message):toastr.success(message);}
    else if(window.Swal)window.Swal.fire({icon:error?'error':'success',text:message});
    else $('#projectFormErrors').text(message);
  }
  function chargeChanged(){
    if(!editing)return false;
    return Number($('#projectCorner').val()||0)!==Number(editing.corner_charge_percent||0)||
      Number($('#projectFacing').val()||0)!==Number(editing.facing_charge_percent||0);
  }
  function chargeHint(){
    $('#projectEffectiveField').toggle(!!editing && chargeChanged());
  }
  $('#projectCorner,#projectFacing').on('input change',chargeHint);
  function openEditor(p){
    editing=p||null;
    $('#projectForm')[0].reset();
    $('#projectFormErrors,.project-field-error').text('');
    $('#projectEditorTitle').text(p?'Edit Project':'New Project');
    $('#projectEditId').val(p?p.id:'');
    $('#projectName').val(p?p.project:'');
    $('#projectCode').val(p?p.project_code:'Assigned automatically');
    $('#projectAddress').val(p?p.address:'');
    $('#projectLocation').val(p?p.location:'');
    $('#projectOpening').val(p?presentDate(p.opening_date).replace('—',''):'');
    $('#projectClosing').val(p?presentDate(p.closing_date).replace('—',''):'');
    $('#projectCorner').val(p?Number(p.corner_charge_percent).toFixed(2):'0');
    $('#projectFacing').val(p?Number(p.facing_charge_percent).toFixed(2):'0');
    $('#projectActive').val(p?String(p.is_active):'1');
    $('#projectLogoPreview').attr('src',p&&p.logo_url?p.logo_url:@json(asset('images/logo/project-placeholder.svg')));
    $('#projectEffectiveDate').val('');
    chargeHint();
    $('#projectEditor').modal({backdrop:'static',keyboard:false,show:true});
  }
  $('#projectLogo').on('change',function(){
    var file=this.files&&this.files[0];
    if(!file)return;
    var reader=new FileReader();
    reader.onload=function(event){$('#projectLogoPreview').attr('src',event.target.result);};
    reader.readAsDataURL(file);
  });
  $('#projectAdd').on('click',function(){openEditor(null);});
  $('#projectRows').on('click','.btn-edit',function(){
    var id=Number($(this).data('id')),project=projects.find(function(p){return p.id===id;});
    if(project)openEditor(project);
  });
  var pendingDeleteId=null;
  $('#projectRows').on('click','.btn-delete',function(){
    pendingDeleteId=Number($(this).data('id'));
    var project=projects.find(function(item){return item.id===pendingDeleteId;});
    if(!project)return;
    $('#projectDeleteName').text(project.project);
    $('#projectDeleteError').text('');
    $('#projectDeleteModal').modal({backdrop:'static',keyboard:false,show:true});
  });
  $('#projectDeleteConfirm').on('click',function(){
    if(!pendingDeleteId)return;
    var $button=$(this).prop('disabled',true);
    $('#projectDeleteError').text('');
    $.ajax({url:@json(route('project.destroy')),type:'POST',
      data:{_token:@json(csrf_token()),_method:'DELETE',id:pendingDeleteId},
      dataType:'json',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}})
      .done(function(response){
        $('#projectDeleteModal').modal('hide');
        inform(response.message||'Project deleted.');
        (function(){var stopSkeleton=$.blueModernDirectoryTools.skeleton('.project-table-wrap');return $.ajax({url:@json(route('project.index')),dataType:'json',headers:{Accept:'application/json'}})
          .done(function(reply){projects=reply.data||[];render();})
          .fail(function(){inform('Deleted, but the list could not refresh.',true);}).always(function(){stopSkeleton();});}());
      }).fail(function(xhr){
        var message=(xhr.responseJSON||{}).message||'Project could not be deleted.';
        $('#projectDeleteError').text(message);inform(message,true);
      }).always(function(){$button.prop('disabled',false);});
  });
  $('#projectForm').on('submit',function(event){
    event.preventDefault();
    var $form=$(this),$save=$('#projectSave');
    $('#projectFormErrors,.project-field-error').text('');
    if(!$.trim($('#projectName').val())||!$.trim($('#projectAddress').val())){
      $('#projectFormErrors').text('Project name and address are required.');return;
    }
    if(chargeChanged()&&!$('#projectEffectiveDate').val()){
      $('#projectFormErrors').text('Select the effective date for changed charges.');return;
    }
    var payload=new FormData($form[0]);
    if(editing)payload.append('_method','PUT');
    $save.prop('disabled',true).text('Saving...');
    $.ajax({url:editing?@json(route('project.update')):@json(route('project.store')),type:'POST',
      data:payload,processData:false,contentType:false,dataType:'json',
      headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}})
      .done(function(response){
        $('#projectEditor').modal('hide');inform(response.message||'Project saved.');
        (function(){var stopSkeleton=$.blueModernDirectoryTools.skeleton('.project-table-wrap');return $.ajax({url:@json(route('project.index')),dataType:'json',headers:{Accept:'application/json'}})
          .done(function(reply){projects=reply.data||[];render();})
          .fail(function(){inform('Saved, but the list could not refresh. Reload the page.',true);}).always(function(){stopSkeleton();});}());
      }).fail(function(xhr){
        var body=xhr.responseJSON||{},errors=body.errors||{};
        $.each(errors,function(key,messages){$('#projectForm').find('[data-error="'+key+'"]').text(messages[0]||'Invalid value');});
        $('#projectFormErrors').text(body.message||'Unable to save project. Please try again.');
        inform(body.message||'Unable to save project.',true);
      }).always(function(){$save.prop('disabled',false).html('<i class="fas fa-check mr-1"></i> Save Project');});
  });
  var exportControls=$.blueModernDirectoryTools.mount({
    card:'.project-card',table:'#projectsTable',title:'Projects List',
    getRows:function(){return activeRows().map(function(p){return [p.id,p.project,p.project_code||'Legacy',(p.address||'')+' '+(p.location||''),presentDate(p.opening_date)+' → '+presentDate(p.closing_date),Number(p.corner_charge_percent||0).toFixed(2)+'% / '+Number(p.facing_charge_percent||0).toFixed(2)+'%',columnValue(p,'is_active'),field(p.created_at)||'—'];});}
  });
  render();
});
</script>
@endsection
