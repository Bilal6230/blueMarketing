@extends('admin.layouts.master')
@section('title', 'Roles & Permissions')

@push('style')
<style>
.bm-roles{--voucher-accent:#2563eb;--fvd-accent:#2563eb;--fvd-pop-accent:#2563eb;padding:15px 16px 22px;font-size:12px;color:#25354b}
.bm-roles *{box-sizing:border-box}.bm-roles .role-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}
.bm-roles .role-heading h2{font-size:19px;font-weight:750;color:#173a75;margin:0}.bm-roles .role-heading small{font-size:11px;color:#7889a0}
.bm-roles .role-card{background:#fff;border:1px solid #d9e4f2;border-top:3px solid var(--voucher-accent);border-radius:11px;box-shadow:0 2px 11px #1634540a;overflow:hidden}
.bm-roles .role-toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:10px;padding:12px;border-bottom:1px solid #e7edf5}
.bm-roles input,.bm-roles select{font-size:12px}.bm-roles .role-search{min-width:230px;flex:1 1 260px}
.bm-roles .role-control{height:33px;border:1px solid #d5e0ec;border-radius:7px;background:white;padding:6px 10px;color:#314762}
.bm-roles .role-control:focus{border-color:#3b82f6;box-shadow:0 0 0 2px #3b82f622;outline:none}
.bm-roles .role-table-scroll{width:100%;overflow:auto;min-height:160px}
.bm-roles .role-table{width:100%;margin:0;table-layout:fixed;font-size:12px}
.bm-roles .role-table thead th{background:#f0f5fc;border-bottom:2px solid var(--voucher-accent);font-weight:700;color:#273e5e;font-size:11px;padding:10px 12px;white-space:nowrap}
.bm-roles .role-table tbody td{padding:10px 12px!important;vertical-align:middle;border-top:1px solid #e9eff6;height:48px!important}
.bm-roles .role-table tbody tr:hover{background:#f8fbff}.bm-roles .role-id{font-weight:650;color:#6b7d92}
.bm-roles .role-name{font-weight:750;color:#1d4c8c}.bm-roles .role-guard{background:#eaf2ff;border-radius:6px;padding:4px 8px;color:#1d5fb6;font-size:11px}
.bm-roles .role-skeleton{display:block;height:12px;max-width:100%;border-radius:5px;background:linear-gradient(90deg,#edf2f8 25%,#f8fbff 48%,#edf2f8 75%);background-size:200% 100%;animation:bmRoleSkeleton 1.4s ease-in-out infinite}
.bm-roles .role-skeleton-id{width:24px}.bm-roles .role-skeleton-name{width:145px}.bm-roles .role-skeleton-guard{width:35px;height:21px}.bm-roles .role-skeleton-date{width:125px}.bm-roles .role-skeleton-person{width:90px}.bm-roles .role-skeleton-action{width:27px;height:28px;border-radius:7px;margin:auto}
@keyframes bmRoleSkeleton{to{background-position-x:-200%}}
@media(prefers-reduced-motion:reduce){.bm-roles .role-skeleton{animation:none}}
.bm-roles .role-foot{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;min-height:44px;gap:9px;padding:7px 12px;border-top:1px solid #e5edf6}
.bm-roles .role-foot .role-pages{display:flex;align-items:center;gap:4px}.bm-roles .role-pages .dv-page-btn{height:29px;min-width:29px;border:1px solid #aacaf5;border-radius:5px;background:white;color:#2563eb;font-size:11px}
.bm-roles .role-pages .dv-page-btn.active{background:#2563eb;color:white}.bm-roles .role-pages .dv-page-btn:disabled{opacity:.45}
.bm-roles .role-filter-trigger{border:0;background:none;color:#496a95;cursor:pointer;padding:3px 5px;margin-left:4px}
.bm-roles .role-filter-trigger:hover,.bm-roles .role-filter-trigger.active{color:#1565c0;background:#e2edff;border-radius:4px}
.role-filter-menu{position:fixed;display:none;z-index:1100;width:265px;border:1px solid #d2dfed;border-top:3px solid #2563eb;border-radius:9px;background:#fff;box-shadow:0 14px 38px #122d4a30;padding:12px;font-size:12px}
.role-filter-menu .menu-title{font-weight:750;color:#234466;margin-bottom:8px}.role-filter-menu .menu-actions{display:flex;gap:6px;justify-content:flex-end;margin-top:10px}
.role-filter-menu button{font-size:11px}
.role-editor .modal-dialog{max-width:1080px;width:calc(100% - 36px);margin:24px auto}
.role-editor .modal-content{border:0;border-radius:15px;box-shadow:0 24px 75px #0e203851;overflow:hidden}
.role-editor .modal-header{padding:15px 20px;background:#f8fbff;border-bottom:1px solid #dfe8f4}
.role-editor .modal-title{font-weight:800;font-size:16px;color:#193d72}
.role-editor .modal-body{padding:16px 20px;max-height:calc(100vh - 180px);overflow:hidden;display:flex;flex-direction:column;gap:12px}
.role-editor .modal-footer{background:white;border-top:1px solid #e2eaf4;padding:12px 20px;display:flex;justify-content:space-between;align-items:center}
.role-editor .role-fields{display:grid;grid-template-columns:minmax(0,2fr) minmax(0,1fr);gap:12px}
.role-editor label{font-size:14px;font-weight:700;color:#334c69;margin-bottom:5px}.role-editor .form-control{height:38px;border-color:#cfdcea;border-radius:7px;font-size:14px}
.role-editor .role-permission-tools{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.role-editor .role-permission-tools input{flex:1 1 250px}.role-editor .role-selected{font-weight:700;color:#1f5fad;background:#e9f2ff;border-radius:15px;padding:5px 10px}
.role-editor .role-permission-grid{display:grid;grid-template-columns:310px minmax(0,1fr);border:1px solid #dce5f1;border-radius:10px;overflow:hidden;height:min(430px,51vh)}
.role-editor .role-navigation{overflow-y:auto;background:#f6f9fe;border-right:1px solid #dfe7f2;padding:9px}
.role-editor .role-nav-item{width:100%;border:0;background:transparent;text-align:left;border-radius:7px;padding:9px 11px;margin-bottom:3px;font-size:14px;color:#344c69;display:flex;justify-content:space-between;gap:5px;align-items:center}
.role-editor .role-nav-item.active{background:#dfeeff;color:#1459a7;font-weight:800}
.role-editor .role-nav-item:hover{background:#eaf3ff}
.role-editor .role-nav-count{font-size:12px;font-weight:700;color:#5e7ba0}
.role-editor .role-nav-item>span:first-child{min-width:0;overflow-wrap:anywhere;line-height:1.35}
.role-editor .role-nav-count{flex-shrink:0}
.role-editor .role-nav-group{padding:12px 9px 5px;color:#72869f;font-size:12px;font-weight:800;letter-spacing:.055em}
.role-editor .role-nav-group:first-child{padding-top:5px}
.role-editor .role-permission-content{overflow:auto;padding:13px 15px}
.role-editor .role-permission-tools,.role-editor .role-permission-content,.role-editor .role-selected,.role-editor .modal-footer{font-size:14px}
.role-editor .role-panel-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:9px}
.role-editor .role-panel-head h5{font-size:14px;font-weight:800;color:#21496f;margin:0}
.role-editor .role-permissions{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:7px}
.role-editor .role-permission-item{font-size:14px;display:flex;align-items:center;gap:7px;padding:9px 10px;border:1px solid #e1eaf4;border-radius:7px;min-height:39px;background:white;cursor:pointer;margin:0!important;font-weight:500;color:#334d67}
.role-editor .role-permission-item:has(input:checked){background:#eaf4ff;border-color:#9dcbfc;color:#17569d}
.role-editor .role-permission-item input{margin:0;accent-color:#236bd1}
.role-editor .role-permission-item.danger{color:#b64242}
.role-editor .role-notice{background:#f4f8fd;border:1px solid #dfebf8;padding:8px 11px;border-radius:7px;color:#637b99;font-size:12px}
.role-editor .role-inline-errors{font-size:12px;color:#b42318;min-height:0}
.bm-role-delete .modal-content{border:0;border-radius:13px}.bm-role-delete .modal-body{text-align:center;padding:25px}
.bm-role-delete .danger-symbol{width:58px;height:58px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#fff0f0;color:#cf3030;margin:auto auto 12px;font-size:24px}
@media(max-width:760px){.role-editor .role-permission-grid{grid-template-columns:180px minmax(0,1fr)}.role-editor .role-permissions{grid-template-columns:1fr 1fr}.role-editor .modal-dialog{margin:12px auto;width:calc(100% - 18px)}}
@media(max-width:540px){.role-editor .role-fields{grid-template-columns:1fr}.role-editor .role-permission-grid{grid-template-columns:110px 1fr}.role-editor .role-permissions{grid-template-columns:1fr}.bm-roles{padding:12px 8px}}
</style>
@endpush

@section('content')
<div class="content-wrapper bm-ui bm-voucher-directory bm-roles" id="rolesApp">
  <div class="role-heading">
    <div><h2>Roles & Permissions</h2><small>Manage access by module and action · Selected project independent</small></div>
    <div class="d-flex align-items-center" style="gap:7px">
      @can('create role')<button type="button" class="btn btn-primary btn-sm" id="roleAdd"><i class="fas fa-plus mr-1"></i> New Role</button>@endcan
      <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">Dashboard</a>
    </div>
  </div>
  <div class="role-card">
    <div class="role-toolbar">
      <input id="roleSearch" class="role-control role-search" aria-label="Search roles" placeholder="Search role name or updated by...">
      <select id="roleGuardFilter" class="role-control" aria-label="Filter by guard"><option value="">All guards</option><option value="web">Web</option><option value="api">API</option></select>
      <select id="rolePageSize" class="role-control" aria-label="Entries per page"><option>10</option><option selected>25</option><option>50</option><option>100</option></select>
      <button type="button" id="roleReset" class="btn btn-outline-secondary btn-sm">Reset</button>
      <span class="text-muted ml-auto" id="roleResultCount"></span>
    </div>
    <div class="role-table-scroll">
      <table class="table table-hover role-table" id="rolesTable">
        <colgroup><col style="width:65px"><col style="width:24%"><col style="width:12%"><col style="width:19%"><col style="width:20%"><col style="width:95px"></colgroup>
        <thead><tr>
          @foreach(['id'=>'ID','name'=>'Name','guard'=>'Guard','updated'=>'Updated','updated_by'=>'Updated By'] as $field=>$label)
            <th><span>{{ $label }}</span><button class="role-filter-trigger" type="button" data-field="{{ $field }}" title="Filter or sort {{ $label }}" aria-label="Filter {{ $label }}"><i class="fas fa-caret-down"></i></button></th>
          @endforeach
          <th>Actions</th>
        </tr></thead>
        <tbody id="roleRows" aria-busy="true">
          {{-- The jQuery role renderer owns the actual rows and shared action menu.
               Show field-shaped skeletons during first paint instead of legacy
               Edit / Archive buttons that disappear after JS enhancement. --}}
          @for ($i = 0; $i < 5; $i++)
            <tr class="role-skeleton-row" aria-hidden="true">
              <td><span class="role-skeleton role-skeleton-id"></span></td>
              <td><span class="role-skeleton role-skeleton-name"></span></td>
              <td><span class="role-skeleton role-skeleton-guard"></span></td>
              <td><span class="role-skeleton role-skeleton-date"></span></td>
              <td><span class="role-skeleton role-skeleton-person"></span></td>
              <td><span class="role-skeleton role-skeleton-action"></span></td>
            </tr>
          @endfor
        </tbody>
      </table>
    </div>
    <div class="role-foot"><small id="roleSummary" class="text-muted"></small><div id="rolePages" class="role-pages"></div></div>
  </div>
</div>
<div class="role-filter-menu" id="roleColumnFilter" role="dialog" aria-label="Column filter">
  <div class="menu-title" id="roleFilterTitle"></div>
  <div class="d-flex flex-column" style="gap:5px">
    <button class="btn btn-light btn-sm text-left role-sort" type="button" data-dir="asc">Sort A to Z / oldest first</button>
    <button class="btn btn-light btn-sm text-left role-sort" type="button" data-dir="desc">Sort Z to A / newest first</button>
    <input id="roleColumnSearch" class="form-control form-control-sm mt-1" placeholder="Filter this column...">
  </div>
  <div class="menu-actions"><button type="button" class="btn btn-outline-secondary btn-sm" id="roleFilterClear">Clear</button><button type="button" class="btn btn-primary btn-sm" id="roleFilterApply">Apply</button></div>
</div>
@endsection

@section('modal')
<div class="modal fade role-editor" id="roleEditor" tabindex="-1" role="dialog" aria-labelledby="roleEditorTitle" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
  <div class="modal-header"><div><h5 class="modal-title" id="roleEditorTitle">Create Role</h5><small class="text-muted">Assign only the permissions this role needs</small></div><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button></div>
  <form id="roleForm" autocomplete="off" data-ajax="off">
   @csrf
   <input type="hidden" name="id" id="roleEditId">
   <div class="modal-body">
    <div class="role-fields">
     <div><label for="roleName">Role Name *</label><input id="roleName" name="name" class="form-control" maxlength="255" required placeholder="e.g. Finance Data Operator"></div>
     <div><label for="roleGuard">Guard *</label><select class="form-control" id="roleGuard" name="guard_name"><option value="web">Web</option><option value="api">API</option></select></div>
    </div>
    <div class="role-permission-tools">
      <input type="search" id="rolePermissionSearch" class="form-control" placeholder="Search vouchers, modules or actions..." aria-label="Search permissions">
      <span class="role-selected" id="roleSelectedCount">0 selected</span>
      <button class="btn btn-outline-secondary btn-sm" id="roleClearVisible" type="button">Clear section</button>
    </div>
    <div class="role-permission-grid">
      <nav class="role-navigation" id="roleNavigation" aria-label="Permission sections"></nav>
      <section class="role-permission-content">
        <div class="role-panel-head"><h5 id="rolePanelTitle">Daily Entries</h5><button type="button" class="btn btn-outline-primary btn-sm" id="roleSelectVisible">Select section</button></div>
        <div class="role-permissions" id="rolePermissionChoices"></div>
        <p class="text-muted small mt-3" id="rolePermissionEmpty" style="display:none">No matching permissions in this section.</p>
      </section>
    </div>
    <div class="role-notice"><i class="fas fa-info-circle mr-1"></i> Legacy generic voucher permissions are hidden to avoid duplicate access choices; existing grants are preserved until the permission usage audit is completed.</div>
    <div class="role-inline-errors" id="roleFormErrors" role="alert" aria-live="polite"></div>
   </div>
   <div class="modal-footer"><button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary btn-sm" id="roleSave"><i class="fas fa-check mr-1"></i> Save Role</button></div>
  </form>
 </div></div>
</div>
<div class="modal fade bm-role-delete" id="roleDeleteModal" tabindex="-1" role="dialog" aria-labelledby="roleDeleteTitle" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
  <div class="modal-body"><div class="danger-symbol"><i class="fas fa-trash-alt"></i></div><h5 id="roleDeleteTitle">Archive Role?</h5>
   <p class="text-muted">Archive <strong id="roleDeleteName"></strong>? The role will not be hard-deleted. Roles assigned to users cannot be archived until reassigned.</p>
   <p class="small text-danger" id="roleDeleteError" role="alert"></p>
   <div class="d-flex justify-content-center" style="gap:8px"><button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancel</button><button type="button" class="btn btn-danger btn-sm" id="roleDeleteConfirm">Archive Role</button></div>
  </div>
 </div></div>
</div>
@endsection

@section('js')
<script>
$(function(){
  'use strict';
  var rows=@json($roleRows),catalog=@json($permissionRows),modules=@json($financeDefinitions);
  var allowEdit=@json(auth()->user()->can('update role')),allowDelete=@json(auth()->user()->can('delete role'));
  var editorMode='create',editId=null,deleteId=null,activeSection='finance:cr',selected=new Set(),sortColumn='id',sortDir='asc',page=1,columnFilters={},filterField='';
  var permissionMap={};$.each(catalog,function(_,p){permissionMap[p.name+'|'+p.guard]=p;});
  var safe=function(value){return $('<span>').text(value===undefined||value===null?'':String(value)).html();};
  var notice=function(message,error){
    if(window.toastr){error?toastr.error(message):toastr.success(message);}
    else if(window.Swal)window.Swal.fire({icon:error?'error':'success',title:error?'Operation failed':'Saved',text:message});
  };
  function visibleRows(){
    var q=$.trim($('#roleSearch').val()).toLowerCase(),guard=$('#roleGuardFilter').val();
    return rows.filter(function(r){
      if(guard && r.guard!==guard)return false;
      if(q && (r.name+' '+r.updated_by+' '+r.guard).toLowerCase().indexOf(q)<0)return false;
      return Object.keys(columnFilters).every(function(k){return String(r[k]??'').toLowerCase().includes(columnFilters[k].toLowerCase());});
    }).sort(function(a,b){
      var x=String(a[sortColumn]??''),y=String(b[sortColumn]??'');
      var result=x.localeCompare(y,undefined,{numeric:true,sensitivity:'base'});
      if(result===0) result=Number(a.id)-Number(b.id);
      return (sortDir==='asc'?1:-1)*result;
    });
  }
  function render(){
    var matches=visibleRows(),length=Number($('#rolePageSize').val())||25,totalPages=Math.max(1,Math.ceil(matches.length/length));
    page=Math.min(page,totalPages);
    var start=(page-1)*length,shown=matches.slice(start,start+length),$body=$('#roleRows').empty();
    if($.blueActionMenu)$.blueActionMenu.close();
    if(!shown.length)$body.append($('<tr>').append($('<td>',{colspan:6,'class':'text-center text-muted py-4',text:'No matching roles'})));
    $.each(shown,function(_,r){
      var $row=$('<tr>');
      $('<td>',{'class':'role-id',text:r.id}).appendTo($row);
      $('<td>',{'class':'role-name',text:r.name}).appendTo($row);
      $('<td>').append($('<span>',{'class':'role-guard',text:r.guard})).appendTo($row);
      $('<td>',{text:r.updated||'—'}).appendTo($row);
      $('<td>',{text:r.updated_by||'—'}).appendTo($row);
      var $action=$('<td>',{'class':'blue-actions-cell'});
      if(!r.locked && allowEdit)$action.append($('<button>',{type:'button','class':'btn btn-outline-primary btn-sm btn-edit','data-id':r.id,'data-blue-action-label':'Edit role',text:'Edit'}));
      if(!r.locked && allowDelete)$action.append($('<button>',{type:'button','class':'btn btn-outline-danger btn-sm btn-delete','data-id':r.id,'data-blue-action-label':'Archive role',text:'Archive'}));
      if(r.locked)$action.append($('<span>',{'class':'text-muted',title:'Protected system role'}).append($('<i>',{'class':'fas fa-lock'})));
      $row.append($action);$body.append($row);
    });
    if($.blueActionMenu)$.blueActionMenu.enhance($body.find('td.blue-actions-cell'));
    $body.attr('aria-busy','false');
    var summary='Showing '+(matches.length?start+1:0)+'–'+Math.min(matches.length,start+length)+' of '+matches.length+' roles';
    $('#roleSummary').text(summary);$('#roleResultCount').text(matches.length+' records');
    if($.blueVoucherPagination)$.blueVoucherPagination.render({container:'#rolePages',summary:'#roleSummary',total:matches.length,length:length,start:start,buttonClass:'dv-page-btn',onPage:function(next){page=Math.floor(next/length)+1;render();}});
  }
  $('#roleSearch').on('input',function(){page=1;render();});
  $('#roleGuardFilter,#rolePageSize').on('change',function(){page=1;render();});
  $('#roleReset').on('click',function(){columnFilters={};$('#roleSearch,#roleGuardFilter').val('');$('#rolePageSize').val('25');page=1;sortColumn='id';sortDir='asc';render();});
  function closeFilter(){$('#roleColumnFilter').hide();filterField='';}
  $('.role-filter-trigger').on('click',function(e){
    e.stopPropagation();filterField=$(this).data('field');var $menu=$('#roleColumnFilter'),offset=$(this).offset();
    $('#roleFilterTitle').text('Filter '+$(this).closest('th').find('span').first().text());
    $('#roleColumnSearch').val(columnFilters[filterField]||'');
    $menu.css({left:Math.min(Math.max(8,offset.left-$(window).scrollLeft()),$(window).width()-275),top:Math.min(offset.top-$(window).scrollTop()+25,$(window).height()-235)}).show();
  });
  $('.role-sort').on('click',function(){if(!filterField)return;sortColumn=filterField;sortDir=$(this).data('dir');page=1;closeFilter();render();});
  $('#roleFilterApply').on('click',function(){var q=$.trim($('#roleColumnSearch').val());if(q)columnFilters[filterField]=q;else delete columnFilters[filterField];page=1;closeFilter();render();});
  $('#roleFilterClear').on('click',function(){delete columnFilters[filterField];page=1;closeFilter();render();});
  $(document).on('click.roleFilters',function(e){if(!$(e.target).closest('#roleColumnFilter,.role-filter-trigger').length)closeFilter();});

  function sectionList(){
    var guard=$('#roleGuard').val(),term=$.trim($('#rolePermissionSearch').val()).toLowerCase();
    var available=function(p){return p.guard===guard&&p.category!=='legacy';};
    var sections=[];
    $.each(modules,function(_,m){
      // PPR is the same voucher family as Plot Receive Payment. Its daily
      // voucher permission names still exist and must remain effective.
      // Display one action choice that grants both applicable scopes.
      if(m.module==='ppr')return;
      var family=m.module==='plot-receipt'?['plot-receipt','ppr']:[m.module];
      var byAction={};
      $.each(family,function(_,module){
        var definition=modules.find(function(d){return d.module===module;});
        if(!definition)return;
        $.each(definition.actions,function(_,action){
          var permissionName='finance '+module+' '+action;
          if(!catalog.some(function(p){return available(p)&&p.name===permissionName;}))return;
          if(!byAction[action])byAction[action]={name:permissionName,names:[],label:action};
          byAction[action].names.push(permissionName);
        });
      });
      var items=Object.keys(byAction).map(function(action){return byAction[action];});
      if(items.length)sections.push({id:'finance:'+m.module,title:m.label,subtitle:'Finance & Daily Entries',
        group:'finance',items:items});
    });
    // Keep one tab for each actual module, instead of dumping every
    // non-finance permission into one overwhelming "Other Modules" tab.
    var general=catalog.filter(function(p){return available(p)&&p.category==='general';});
    var grouped={};
    $.each(general,function(_,p){
      var module=p.module||'other';
      if(!grouped[module])grouped[module]={id:'general:'+module,title:p.module_label||module,
        subtitle:'Application modules',group:'application',items:[]};
      grouped[module].items.push({name:p.name,names:[p.name],label:p.name});
    });
    $.each(Object.keys(grouped).sort(function(a,b){
      return grouped[a].title.localeCompare(grouped[b].title);
    }),function(_,key){sections.push(grouped[key]);});
    if(term){
      sections=sections.map(function(s){return $.extend({},s,{items:s.items.filter(function(i){return (s.title+' '+i.label+' '+i.names.join(' ')).toLowerCase().includes(term);})});}).filter(function(s){return s.items.length;});
    }
    return sections;
  }
  function renderPermissions(){
    var sections=sectionList(),$nav=$('#roleNavigation').empty();
    if(!sections.some(function(s){return s.id===activeSection;}))activeSection=sections.length?sections[0].id:'';
    var previousGroup='';
    $.each(sections,function(_,s){
      if(s.group!==previousGroup){
        previousGroup=s.group;
        $nav.append($('<div>',{'class':'role-nav-group',text:s.group==='finance'?'FINANCE & DAILY ENTRIES':'OTHER MODULES'}));
      }
      var count=s.items.filter(function(i){return i.names.every(function(n){return selected.has(n);});}).length;
      $nav.append($('<button>',{type:'button','class':'role-nav-item'+(s.id===activeSection?' active':''),'data-section':s.id}).append($('<span>',{text:s.title}),$('<span>',{'class':'role-nav-count',text:count+'/'+s.items.length})));
    });
    var current=sections.find(function(s){return s.id===activeSection;}),$items=$('#rolePermissionChoices').empty();
    $('#rolePanelTitle').text(current?current.title:'No results');
    $('#rolePermissionEmpty').toggle(!current);
    if(current)$.each(current.items,function(_,p){
      var $input=$('<input>',{type:'checkbox','data-permission':p.name,checked:p.names.every(function(n){return selected.has(n);})});
      $input.prop('indeterminate',p.names.some(function(n){return selected.has(n);})&&!p.names.every(function(n){return selected.has(n);}));
      $input.data('permission-names',p.names);
      var $label=$('<label>',{'class':'role-permission-item'+(/delete|reject|reverse|return/.test(p.label)?' danger':'')});
      $label.append($input,$('<span>',{text:p.label.replace(/(^|\s)\S/g,function(t){return t.toUpperCase();})}));
      $items.append($label);
    });
    var grant=$('#roleGuard').val(),valid=Array.from(selected).filter(function(n){return !!permissionMap[n+'|'+grant];}).length;
    $('#roleSelectedCount').text(valid+' selected');
  }
  $('#roleNavigation').on('click','.role-nav-item',function(){activeSection=$(this).data('section');renderPermissions();});
  $('#rolePermissionChoices').on('change','input[data-permission]',function(){
    var checked=this.checked;
    $.each($(this).data('permission-names')||[$(this).data('permission')],function(_,name){
      if(checked)selected.add(name);else selected.delete(name);
    });
    renderPermissions();
  });
  $('#rolePermissionSearch').on('input',renderPermissions);
  $('#roleGuard').on('change',function(){selected.clear();renderPermissions();});
  function sectionChange(check){
    var section=sectionList().find(function(s){return s.id===activeSection;});
    if(!section)return;
    $.each(section.items,function(_,p){$.each(p.names,function(_,name){if(check)selected.add(name);else selected.delete(name);});});
    renderPermissions();
  }
  $('#roleSelectVisible').on('click',function(){sectionChange(true);});
  $('#roleClearVisible').on('click',function(){sectionChange(false);});
  function openEditor(role){
    editorMode=role?'edit':'create';editId=role?role.id:null;
    $('#roleEditorTitle').text(role?'Edit Role':'Create Role');
    $('#roleName').val(role?role.name:'');$('#roleEditId').val(role?role.id:'');
    $('#roleGuard').val(role?role.guard:'web');$('#rolePermissionSearch,#roleFormErrors').val('').text('');
    $('#roleFormErrors').text('');selected=new Set(role?role.permissions.filter(function(n){return permissionMap[n+'|'+role.guard]&&permissionMap[n+'|'+role.guard].category!=='legacy';}):[]);
    activeSection='finance:cr';renderPermissions();$('#roleEditor').modal({backdrop:'static',keyboard:false,show:true});
  }
  $('#roleAdd').on('click',function(){openEditor(null);});
  $('#roleRows').on('click','.btn-edit',function(){
    var id=Number($(this).data('id')),r=rows.find(function(x){return x.id===id;});if(r)openEditor(r);
  });
  $('#roleRows').on('click','.btn-delete',function(){
    deleteId=Number($(this).data('id'));var r=rows.find(function(x){return x.id===deleteId;});
    if(!r)return;$('#roleDeleteName').text(r.name);$('#roleDeleteError').text('');$('#roleDeleteModal').modal('show');
  });
  function messageFrom(xhr){
    var body=xhr.responseJSON||{},errors=body.errors||{};
    var first=Object.keys(errors)[0];return first?(errors[first][0]||body.message):body.message||'Unable to save. Check your permissions and try again.';
  }
  function refreshRows(callback){
    $.ajax({url:@json(route('role.index')),headers:{Accept:'application/json'},dataType:'json'})
      .done(function(data){rows=data.data||[];render();if(callback)callback();})
      .fail(function(){notice('Saved, but the list could not be refreshed. Reload the page.',true);});
  }
  $('#roleForm').on('submit',function(e){
    e.preventDefault();var $button=$('#roleSave').prop('disabled',true),guard=$('#roleGuard').val();
    $('#roleFormErrors').text('');
    var payload={name:$.trim($('#roleName').val()),guard_name:guard,permissions:Array.from(selected).filter(function(n){return permissionMap[n+'|'+guard]&&permissionMap[n+'|'+guard].category!=='legacy';}),_token:@json(csrf_token())};
    if(editorMode==='edit'){payload.id=editId;payload._method='PUT';}
    $.ajax({url:editorMode==='edit'?@json(route('role.update')):@json(route('role.store')),type:'POST',data:payload,headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},dataType:'json'})
      .done(function(res){$('#roleEditor').modal('hide');notice(res.message||'Role saved.');refreshRows();})
      .fail(function(xhr){$('#roleFormErrors').text(messageFrom(xhr));notice('Role could not be saved.',true);})
      .always(function(){$button.prop('disabled',false);});
  });
  $('#roleDeleteConfirm').on('click',function(){
    var $button=$(this).prop('disabled',true);$('#roleDeleteError').text('');
    $.ajax({url:@json(route('role.destroy')),type:'POST',data:{_token:@json(csrf_token()),_method:'DELETE',id:deleteId},headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},dataType:'json'})
      .done(function(res){$('#roleDeleteModal').modal('hide');notice(res.message||'Role archived.');refreshRows();})
      .fail(function(xhr){$('#roleDeleteError').text(messageFrom(xhr));})
      .always(function(){$button.prop('disabled',false);});
  });
  render();
});
</script>
@endsection
