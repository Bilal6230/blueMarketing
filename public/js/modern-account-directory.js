/* Main Account, Party Account and Joint Account: jQuery-only shared directory.
   Existing server-side financial forms, status audit and mapping handlers remain
   the source of truth. This script handles presentation and safe client filtering. */
(function($){
'use strict';
if(!$)return;
$(function(){
 var $root=$('.bm-modern-accounts[data-account-kind]'),$table=$root.find('#bmAccountDirectory');
 if(!$root.length||!$table.length)return;
 // The directory owns vertical scrolling; the document never grows with rows.
 $('body').addClass('bm-account-directory-page');

 var kind=$root.attr('data-account-kind'),$card=$table.closest('.bm-account-card'),
     $body=$table.find('tbody'),$header=$root.find('.content-header').first();
 var columns=$table.find('thead th').length,rows=$body.children('tr').not('.bm-cell-skeleton-row').detach().toArray();
 var currentPage=1,pageSize=25,sortColumn=0,sortDescending=false,filters={},dateRanges={};
 var $newButton=$('<button>',{'class':'btn btn-primary btn-sm bm-account-new',type:'button'})
    .append($('<i>',{'class':'fas fa-plus mr-1'})).append($('<span>',{text:' New '+(kind==='head'?'Main Account':kind==='subhead'?'Party Account':'Joint Account')}));
 var $footer=$('<div>',{'class':'bm-account-footer'}),
     $summary=$('<small>',{'class':'text-muted'}),
     $size=$('<select>',{'aria-label':'Rows per page'}),
     $reset=$('<button>',{'class':'btn btn-outline-secondary btn-sm',type:'button',text:'Reset filters'}),
     $pages=$('<div>',{'class':'bm-account-pages'});
 $.each([10,25,50,100],function(_,v){$size.append($('<option>',{value:v,text:v,selected:v===25}));});
 $footer.append($('<div>',{'class':'bm-account-footer-left'}).append($summary,$size,$reset),$pages);
 $card.find('.bm-account-footer-placeholder').remove();
 $card.append($footer);
 var $createSource=$root.find('.bm-account-create-source').first();
 if($createSource.length&&$createSource.find('form').length){
   var $form=$createSource.find('form').first(),$modal=$('<div>',{
     'class':'modal fade bm-account-create-modal',id:'bmAccountCreateModal',
     tabindex:-1,role:'dialog','aria-hidden':'true'
   });
   var title=kind==='head'?'New Main Account':kind==='subhead'?'New Party Account':'New Joint Account';
   var $content=$('<div>',{'class':'modal-content'}),
       $dialog=$('<div>',{'class':'modal-dialog modal-dialog-centered',role:'document'});
   $content.append($('<div>',{'class':'modal-header'})
      .append($('<h5>',{'class':'modal-title',text:title}))
      .append($('<button>',{type:'button','class':'close','data-dismiss':'modal','aria-label':'Close'})
        .append($('<span>',{'aria-hidden':'true',text:'×'}))));
   var $modalBody=$('<div>',{'class':'modal-body bm-account-creation-form'});
   $form.appendTo($modalBody);
   $content.append($modalBody);$dialog.append($content);$modal.append($dialog);
   $root.append($modal);
   $form.find('button[type=submit]').addClass('btn-sm');
   $newButton.on('click',function(){$modal.modal({backdrop:'static',keyboard:false,show:true});});
   $modal.on('shown.bs.modal',function(){
     if(!$.fn.select2)return;
     $form.find('select.select2').each(function(){
       var $select=$(this);
       if($select.hasClass('select2-hidden-accessible'))$select.select2('destroy');
       $select.select2({dropdownParent:$modal,width:'100%'});
     });
   });
   var $place=$header.find('.row').first();
   if($place.length){
     var $target=$place.children('[class*="col-sm-"]').last();
     if($target.length){
       $target.addClass('d-flex justify-content-end align-items-center');
       $target.find('ol.breadcrumb').remove();
       $target.append($newButton);
     }else $place.append($newButton);
   }else $header.append($newButton);
 }
 // Purely decorative wrapping: preserve original delegated click handlers,
 // modal flow and financial route targets. No action authorization is inferred.

 function cellValue(row,col){return $.trim($(row).children('td').eq(col).text()).replace(/\s+/g,' ');}
 function visibleRows(){
   var list=rows.filter(function(row){
     return Object.keys(filters).every(function(key){
       return cellValue(row,Number(key)).toLowerCase().indexOf(filters[key].toLowerCase())>=0;
     })&&Object.keys(dateRanges).every(function(key){
       var range=dateRanges[key],value=cellValue(row,Number(key)).slice(0,10);
       return (!range.from&&!range.to)||(value!==''&&(!range.from||value>=range.from)&&(!range.to||value<=range.to));
     });
   });
   list.sort(function(a,b){
     var x=cellValue(a,sortColumn),y=cellValue(b,sortColumn);
     var nx=Number(x),ny=Number(y);
     var cmp=x!==''&&y!==''&&!isNaN(nx)&&!isNaN(ny)?nx-ny:x.localeCompare(y,undefined,{numeric:true,sensitivity:'base'});
     if(cmp===0)cmp=Number($(a).find('td').first().text())-Number($(b).find('td').first().text());
     return sortDescending?-cmp:cmp;
   });
   return list;
 }
 var widget=$.financeVoucherComponents&&$.financeVoucherComponents.createFilter
   ?$.financeVoucherComponents.createFilter({kind:'heading',accent:'#2563eb'}):null;
 var activeColumn=null,$activeButton=null;
 function closeFilter(){
   if(widget)widget.close();
   if($activeButton)$activeButton.attr('aria-expanded','false');
   activeColumn=null;$activeButton=null;
 }
 function updateButtons(){
   $table.find('.bm-account-col').each(function(){
     var n=Number($(this).data('col')),range=dateRanges[n]||{};
     $(this).toggleClass('is-filtered',!!filters[n]||!!(range.from||range.to)||sortColumn===n)
       .attr('aria-sort',sortColumn===n?(sortDescending?'descending':'ascending'):'none');
   });
 }
 function render(){
   var matches=visibleRows(),total=matches.length;
   pageSize=Math.max(1,Number($size.val())||25);
   currentPage=Math.max(1,Math.min(currentPage,Math.max(1,Math.ceil(total/pageSize))));
   var start=(currentPage-1)*pageSize,shown=matches.slice(start,start+pageSize);
   if($.blueActionMenu)$.blueActionMenu.close();
   $body.empty();
   if(!shown.length)$body.append($('<tr>').append($('<td>',{colspan:columns,'class':'text-center text-muted py-4',text:'No matching accounts found'})));
   else $.each(shown,function(_,row){$body.append(row);});
   if(exportControls)exportControls.refresh();
   if($.blueActionMenu)$.blueActionMenu.enhance($body.find('td.blue-actions-cell'));
   $summary.text('Showing '+(total?start+1:0)+'–'+Math.min(total,start+pageSize)+' of '+total+' records');
   if($.blueVoucherPagination)$.blueVoucherPagination.render({
      container:$pages,summary:$summary,total:total,length:pageSize,start:start,
      buttonClass:'dv-page-btn',
      onPage:function(next){currentPage=Math.floor(next/pageSize)+1;render();}
   });
   updateButtons();
 }
 $table.find('thead th').each(function(index){
   if(index===columns-1&&/action/i.test($(this).text()))return;
   var $th=$(this),name=$.trim($th.text());
   $th.append($('<button>',{type:'button','class':'bm-account-col','data-col':index,
     'aria-label':'Sort or filter '+name,'aria-expanded':'false','aria-haspopup':'dialog'})
     .append($('<i>',{'class':'fas fa-caret-down','aria-hidden':'true'})));
 });
 $table.on('click','.bm-account-col',function(event){
   event.preventDefault();event.stopPropagation();
   var $btn=$(this),col=Number($btn.data('col'));
   if(activeColumn===col){closeFilter();return;}
   closeFilter();activeColumn=col;$activeButton=$btn;
   $btn.attr('aria-expanded','true');
   if(!widget)return;
   var label=$.trim($btn.closest('th').text());
   widget.setTitle(label);
   if(/status/i.test(label)){
     var $status=$('<select>',{'class':'form-control form-control-sm','aria-label':'Filter status'})
       .append($('<option>',{value:'',text:'All statuses'}),$('<option>',{value:'Active',text:'Active'}),$('<option>',{value:'Inactive',text:'Inactive'}))
       .val(filters[col]||'');
     widget.$control.empty().append($status);
     $status.on('change',function(){
       if($(this).val())filters[col]=$(this).val();else delete filters[col];
       currentPage=1;render();
     });
   }else if(/date|created/i.test(label)){
     widget.dates(dateRanges[col]||{from:'',to:''},function(which,value){
       if(!dateRanges[col])dateRanges[col]={from:'',to:''};
       dateRanges[col][which]=value;currentPage=1;render();
     });
   }else{
     widget.search(filters[col]||'',function(value){
       if(value)filters[col]=value;else delete filters[col];
       currentPage=1;render();
     },'Search '+label+'...');
   }
   widget.position($btn);widget.$control.find('input,select').first().trigger('focus');
 });
 if(widget){
   widget.$popup.find('.bm-col-asc,.bm-col-desc').on('click',function(){
     if(activeColumn===null)return;
     sortColumn=activeColumn;sortDescending=$(this).hasClass('bm-col-desc');
     closeFilter();currentPage=1;render();
   });
   widget.$popup.find('.bm-col-clear').on('click',function(){
     if(activeColumn===null)return;
     delete filters[activeColumn];delete dateRanges[activeColumn];
     if(sortColumn===activeColumn){sortColumn=0;sortDescending=false;}
     closeFilter();currentPage=1;render();
   });
   $(document).on('click.bmAccountingColumns',function(event){
     if(!$(event.target).closest(widget.$popup).length&&!$(event.target).closest('.bm-account-col').length)closeFilter();
   }).on('keydown.bmAccountingColumns',function(event){if(event.key==='Escape')closeFilter();});
 }
 $size.on('change',function(){currentPage=1;render();});
 $reset.on('click',function(){
   filters={};dateRanges={};sortColumn=0;sortDescending=false;currentPage=1;$size.val('25');closeFilter();render();
 });
 function prepareRows(){
   $(rows).each(function(){
     var $cell=$(this).children('td').last();
     var $actions=$cell.find('.btn-edit,.btn-delete,.editBtn,.deleteBtn');
     if(!$actions.length)return;
     $cell.addClass('blue-actions-cell');
     $actions.each(function(){
       var $button=$(this);
       $button.attr('type','button').attr('data-blue-action-label',
         $button.hasClass('btn-delete')||$button.hasClass('deleteBtn')?'Delete':'Edit');
       $button.appendTo($cell);
     });
     $cell.children('.btn-group').remove();
   });
 }
 function notify(message,isError){
   if(window.toastr){isError?window.toastr.error(message):window.toastr.success(message);}
   else if(window.Swal)window.Swal.fire({icon:isError?'error':'success',text:message});
 }
 function reloadRows(){
   var stopSkeleton=$.blueModernDirectoryTools.skeleton($table.closest('.bm-account-table-wrap'));
   return $.ajax({url:window.location.pathname+window.location.search,type:'GET',dataType:'html',
     headers:{Accept:'text/html','X-Requested-With':'XMLHttpRequest'}}).done(function(html){
     var $html=$('<div>').append($.parseHTML(html,document,kind==='lead-approvals'));
     var $fresh=$html.find('#bmAccountDirectory tbody tr').not('.bm-cell-skeleton-row');
     if(!$fresh.length && $html.find('#bmAccountDirectory').length===0){
       notify('Unable to refresh the table. Please try again.',true);return;
     }
     rows=$fresh.detach().toArray();
     prepareRows();currentPage=1;render();
   }).fail(function(){notify('Table refresh failed. Please try again.',true);})
   .always(function(){stopSkeleton();});
 }
 function submitAjax($form,onSuccess){
   var $submit=$form.find('[type=submit]').first();
   var oldLabel=$submit.html();
   $form.find('.bm-account-form-error').remove();
   $form.find('.is-invalid').removeClass('is-invalid');
   $submit.prop('disabled',true).text('Saving...');
   $.ajax({url:$form.attr('action'),type:'POST',data:$form.serialize(),dataType:'json',
     headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}})
   .done(function(data){
     notify(data.message||'Account saved.',false);
     onSuccess();reloadRows();
   }).fail(function(xhr){
     var payload=xhr.responseJSON||{},errors=payload.errors||{};
     $.each(errors,function(name,messages){
       var $control=$form.find('[name="'+name+'"]').first().addClass('is-invalid');
       $control.after($('<small>',{'class':'bm-account-form-error text-danger d-block',
         text:messages[0]||'Invalid value'}));
     });
     var message=payload.message||'Unable to save account. Please review the fields.';
     if(!Object.keys(errors).length)$form.prepend($('<div>',{'class':'bm-account-form-error text-danger p-2',text:message}));
     notify(message,true);
   }).always(function(){$submit.prop('disabled',false).html(oldLabel);});
 }
 if($createSource.length){
   $root.find('.bm-account-create-modal form').on('submit.bmAccount',function(event){
     event.preventDefault();
     var $form=$(this);
     submitAjax($form,function(){$root.find('.bm-account-create-modal').modal('hide');$form[0].reset();});
   });
 }
 if(kind==='head'||kind==='subhead'){
   $('#modal-edit form').on('submit.bmAccount',function(event){
     event.preventDefault();submitAjax($(this),function(){$('#modal-edit').modal('hide');});
   });
   $('#modal-delete form').on('submit.bmAccount',function(event){
     event.preventDefault();
     var $form=$(this);
     $.ajax({url:$form.attr('action'),type:'POST',data:$form.serialize(),dataType:'json',
       headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}})
     .done(function(data){notify(data.message||'Action completed.',false);$('#modal-delete').modal('hide');reloadRows();})
     .fail(function(xhr){notify((xhr.responseJSON||{}).message||'This accounting definition cannot be deleted.',true);});
   });
 }
 // Shared no-navigation CRUD for modern User and Permission directories.
 if(kind==='user'||kind==='permission'){
   var scope=kind==='user'?'user':'permission';
   $('#modal-tambah form,#modal-edit form,#modal-delete form').on('submit.bmDirectoryAjax',function(event){
     event.preventDefault();
     var $form=$(this),$modal=$form.closest('.modal'),$submit=$form.find('[type=submit]').first();
     if($submit.prop('disabled'))return;
     var original=$submit.html(),payload=new FormData(this);
     $form.find('.bm-account-form-error').remove();
     $form.find('.is-invalid').removeClass('is-invalid');
     $submit.prop('disabled',true).text('Saving...');
     $.ajax({url:$form.attr('action'),type:'POST',data:payload,processData:false,contentType:false,
       dataType:'json',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}})
     .done(function(response){
       if(response.success===false){notify(response.message||'Operation failed.',true);return;}
       $modal.modal('hide');
       if($modal.attr('id')==='modal-tambah')$form[0].reset();
       notify(response.message||'Saved successfully.',false);
       reloadRows();
     }).fail(function(xhr){
       var data=xhr.responseJSON||{},errors=data.errors||{};
       $.each(errors,function(name,messages){
         var $field=$form.find('[name="'+name+'"]').first();
         $field.addClass('is-invalid').after($('<small>',{'class':'bm-account-form-error text-danger d-block',text:messages[0]||'Invalid value'}));
       });
       notify(data.message||'Unable to save '+scope+'. Please review the form.',true);
     }).always(function(){$submit.prop('disabled',false).html(original);});
   });
   if(kind==='permission'){
     $root.find('.bm-permission-toolbar-actions a[href]').filter(function(){
       return /reload/i.test($(this).text());
     }).on('click.bmDirectoryReload',function(event){
       event.preventDefault();
       var $button=$(this);
       if($button.data('bm-busy'))return;
       $button.data('bm-busy',true).addClass('disabled').attr('aria-disabled','true');
       $.ajax({url:$button.attr('href'),type:'GET',dataType:'json',
         headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}})
       .done(function(response){notify(response.message||'Permissions refreshed.',false);reloadRows();})
       .fail(function(xhr){notify((xhr.responseJSON||{}).message||'Unable to reload permissions.',true);})
       .always(function(){$button.data('bm-busy',false).removeClass('disabled').removeAttr('aria-disabled');});
     });
   }
 }
 $(document).on('bm:account-refresh',function(){reloadRows();});
 var exportControls=$.blueModernDirectoryTools&&$.blueModernDirectoryTools.mount({
   card:$card,table:$table,title:kind==='head'?'Main Accounts':kind==='subhead'?'Party Accounts':kind==='joint'?'Joint Accounts':kind==='lead-approvals'?'Lead Assignment Requests':kind==='permission'?'Permissions':kind==='user'?'Users':'Accountants List',
   getRows:function(){
     return visibleRows().map(function(row){
       return $(row).children('td').map(function(){return $.trim($(this).text()).replace(/\s+/g,' ');}).get();
     });
   }
 });
 if(kind==='user'){
   var $userActions=$root.find('.bm-user-card-actions .card-title');
   var $exportBar=$card.find('.bm-modern-export-toolbar');
   if($userActions.length&&$exportBar.length){
     $userActions.appendTo($exportBar).addClass('bm-user-toolbar-actions');
     $root.find('.bm-user-card-actions').remove();
   }
 }
 if(kind==='permission'){
   var $permissionActions=$root.find('.bm-permission-card-actions .card-title');
   var $permissionBar=$card.find('.bm-modern-export-toolbar');
   if($permissionActions.length&&$permissionBar.length){
     $permissionActions.appendTo($permissionBar).addClass('bm-permission-toolbar-actions');
     $root.find('.bm-permission-card-actions').remove();
   }
 }
 prepareRows();
 $body.children('.bm-cell-skeleton-row').remove();
 $root.find('.bm-account-loading').remove();
 $table.removeClass('bm-pending-directory').css('visibility','visible');
 render();
});
})(jQuery);
