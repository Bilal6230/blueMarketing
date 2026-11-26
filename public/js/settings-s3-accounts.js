/* S3 Settings: only jQuery, no page navigations, source-of-truth server rows. */
(function($){
'use strict';
$(function(){
 var $root=$('#s3-account-workspace'),$modal=$('#s3-account-modal'),$form=$('#s3-account-form');
 if(!$root.length||!$modal.length||!$form.length)return;
 $modal.appendTo('body');
 var busy=false,currentType='bank';
 // Each Subhead option carries the actual project-scoped mapping ID. Never
 // reconstruct financial mapping identities from separate numeric IDs.
 var $head=$form.find('#s3-head-account'),$subhead=$form.find('#s3-subhead-account'),
     $mapping=$form.find('[name=account_mapping_id]');
 var coaOptions=$subhead.find('option[value!=""]').map(function(){
  return {id:String(this.value),head:String($(this).attr('data-head-id')),
    subhead:String($(this).attr('data-subhead-id')),label:$(this).text()};
 }).get();
 // Match voucher selectors: searchable Tom Select, with native-select fallback.
 // Dropdowns live at body level so they are not clipped by the scrollable modal.
 var headTs=null,subheadTs=null;
 function setValue($select,value){
  var ts=$select[0]&&$select[0].tomselect;
  if(ts)ts.setValue(String(value||''),true);
  else $select.val(String(value||''));
 }
 function populateSubheads(headId,chosenMapping){
  var head=String(headId||''),requested=String(chosenMapping||'');
  var options=coaOptions.filter(function(item){return !head||item.head===head;});
  if(!options.some(function(item){return item.id===requested;}))requested='';
  if(subheadTs){
   subheadTs.clear(true);
   subheadTs.clearOptions();
   subheadTs.addOptions(options.map(function(item){
    return {value:item.id,text:item.label,head:item.head,subhead:item.subhead};
   }));
   subheadTs.refreshOptions(false);
   subheadTs.setValue(requested,true);
  }else{
   $subhead.empty().append($('<option>',{value:'',text:'Select Subhead Account'}));
   $.each(options,function(_,item){
    $subhead.append($('<option>',{value:item.id,text:item.label,
     'data-head-id':item.head,'data-subhead-id':item.subhead}));
   });
   $subhead.val(requested);
  }
  $mapping.val(requested);
 }
 function selectMapping(mappingId){
  var id=String(mappingId||'');
  var item=coaOptions.find(function(option){return option.id===id;});
  setValue($head,item?item.head:'');
  populateSubheads(item?item.head:'',item?item.id:'');
 }
 function headChanged(value){
  var next=String(value||''),previous=String($mapping.val()||'');
  populateSubheads(next,previous);
 }
 function subheadChanged(value){
  var id=String(value||''),item=coaOptions.find(function(option){return option.id===id;});
  if(item){
   setValue($head,item.head);
   populateSubheads(item.head,item.id);
  }else $mapping.val('');
 }
 if(window.TomSelect){
  headTs=new TomSelect($head[0],{
   create:false,allowEmptyOption:false,maxItems:1,placeholder:'Select Head Account',
   dropdownParent:'body',onChange:headChanged
  });
  subheadTs=new TomSelect($subhead[0],{
   create:false,allowEmptyOption:false,maxItems:1,placeholder:'Select Subhead Account',
   dropdownParent:'body',onChange:subheadChanged
  });
 }else{
  $head.on('change',function(){headChanged($(this).val());});
  $subhead.on('change',function(){subheadChanged($(this).val());});
 }
 populateSubheads('','');

 function notify(message,failed){
  if(window.toastr){failed?toastr.error(message):toastr.success(message);}
  else if(window.Swal)Swal.fire({icon:failed?'error':'success',text:message});
 }
 function error(xhr){
  var data=xhr.responseJSON||{},message=data.message||'Unable to save account.';
  if(data.errors)$.each(data.errors,function(_,items){message=items[0]||message;return false;});
  notify(message,true);
 }
 function resourceUrl(kind,id,status){
  return String($root.attr(status?'data-status':'data-detail')).replace('/bank/','/'+kind+'/')
     .replace('987654321',encodeURIComponent(id));
 }
 function formMode(type,account){
  currentType=type;
  $form[0].reset();
  $form.find('input[name=bank_account_id],input[name=cash_account_id]').val('').prop('disabled',true);
  $form.find('.s3-bank-only :input').prop('disabled',type!=='bank');
  $form.find('.s3-bank-only').toggle(type==='bank');
  $form.find('[name=display_title]').val('');
  selectMapping('');
  $form.find('[name=is_active]').val('1');
  if(account){
   $form.find('[name='+type+'_account_id]').prop('disabled',false).val(account.id);
   $.each(['display_title','bank_name','account_title','branch','is_active'],function(_,key){
    if(account[key]!==undefined)$form.find('[name='+key+']').val(key==='is_active'?(account[key]?'1':'0'):String(account[key]));
   });
   selectMapping(account.account_mapping_id);
   $('#s3-bank-number-note').text(account.account_number_last4?'Saved number ends '+account.account_number_last4+'; leave blank to keep.':'No saved number.');
  }else{
   $('#s3-bank-number-note').text('Encrypted when saved.');
  }
  $('#s3-account-title').text((account?'Edit ':'Configure ')+(type==='bank'?'Bank':'Cash')+' Account');
  $form.attr('action',type==='bank'?$form.data('bank-url'):$form.data('cash-url'));
  $modal.modal('show');
 }
 $form.data('bank-url',$form.attr('data-bank-url')).data('cash-url',$form.attr('data-cash-url'));
 function refreshTables(){
  return $.ajax({url:window.location.pathname+'?tab=bank-accounts',method:'GET',dataType:'html',
   headers:{Accept:'text/html','X-Requested-With':'XMLHttpRequest'}})
  .done(function(html){
   var $doc=$('<div>').append($.parseHTML(html,document,false));
   ['bank','cash'].forEach(function(type){
    var $fresh=$doc.find('#s3-'+type+'-table tbody'),$old=$('#s3-'+type+'-table tbody');
    if($fresh.length&&$old.length){
     if($.blueActionMenu)$.blueActionMenu.close();
     $old.empty().append($fresh.children());
     $('#s3-'+type+'-count').text($old.children('tr[data-id]').length+' accounts');
     $(document).trigger('s3:table-updated',[type]);
    }
   });
  }).fail(function(){notify('Saved, but table refresh failed. Reload the page to see the new data.',true);});
 }
 $root.on('click','[data-s3-new]',function(){formMode($(this).attr('data-s3-new'),null);})
  .on('click','.s3-edit',function(){
   if(busy)return;
   var type=$(this).attr('data-type'),id=$(this).attr('data-id');
   busy=true;
   $.ajax({url:resourceUrl(type,id,false),type:'GET',dataType:'json',
     headers:{Accept:'application/json'}})
    .done(function(data){formMode(type,data.account);})
    .fail(error).always(function(){busy=false;});
  }).on('click','.s3-status',function(){
   if(busy)return;
   var $button=$(this),type=$button.attr('data-type'),id=$button.attr('data-id'),next=$button.attr('data-active');
   function send(){
    busy=true;
    $.ajax({url:resourceUrl(type,id,true),type:'POST',dataType:'json',
      data:{_token:$form.find('[name=_token]').val(),is_active:next},
      headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}})
    .done(function(data){notify(data.message||'Status updated.',false);refreshTables();})
    .fail(error).always(function(){busy=false;});
   }
   if(window.Swal)Swal.fire({icon:'question',title:next==='1'?'Activate account?':'Deactivate account?',
      text:'Existing financial history will be preserved.',showCancelButton:true,confirmButtonText:'Yes, Apply'})
    .then(function(result){if(result.isConfirmed)send();});
   else if(window.confirm('Change account status?'))send();
  });
 $form.on('submit',function(event){
  event.preventDefault();event.stopImmediatePropagation();
  if(busy)return;
  if(!this.checkValidity()){this.reportValidity();return;}
  if(!$mapping.val()){
   notify('Select a valid project Head and Subhead Account mapping.',true);
   $subhead.trigger('focus');
   return;
  }
  function send(){
   busy=true;$form.find('[type=submit]').prop('disabled',true);
   $.ajax({url:$form.attr('action'),type:'POST',data:$form.serialize(),dataType:'json',
     headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}})
    .done(function(data){$modal.modal('hide');notify(data.message||'Account saved.',false);refreshTables();})
    .fail(error)
    .always(function(){busy=false;$form.find('[type=submit]').prop('disabled',false);});
  }
  if(window.Swal)Swal.fire({icon:'question',title:'Save account?',showCancelButton:true,confirmButtonText:'Yes, Save'})
    .then(function(result){if(result.isConfirmed)send();});
  else send();
 });
 function directory(type){
  var $table=$('#s3-'+type+'-table'),$card=$table.closest('.s3-card'),$body=$table.children('tbody');
  if(!$table.length)return;
  var cols=$table.find('thead th').length,filters={},sort=-1,desc=false,page=1,pageSize=25,widget=null,
    active=-1,exports=null;
  var $footer=$('<div>',{'class':'bm-account-footer'}),
      $summary=$('<small>',{'class':'text-muted'}),
      $size=$('<select>',{'aria-label':'Rows per page'}),
      $reset=$('<button>',{type:'button','class':'btn btn-outline-secondary btn-sm',text:'Reset filters'}),
      $pages=$('<div>',{'class':'bm-account-pages'});
  [10,25,50,100].forEach(function(n){$size.append($('<option>',{value:n,text:n,selected:n===25}));});
  $footer.append($('<div>',{'class':'bm-account-footer-left'}).append($summary,$size,$reset),$pages);
  $card.append($footer);
  function dataRows(){return $body.find('tr[data-id]').toArray();}
  function val(row,i){return $.trim($(row).children('td').eq(i).text()).replace(/\\s+/g,' ');}
  function selectedRows(){
   return dataRows().filter(function(row){
    return Object.keys(filters).every(function(i){return val(row,Number(i)).toLowerCase().includes(filters[i].toLowerCase());});
   }).sort(function(a,b){
    if(sort<0)return 0;
    var aV=val(a,sort),bV=val(b,sort),cmp=aV.localeCompare(bV,undefined,{numeric:true,sensitivity:'base'});
    return desc?-cmp:cmp;
   });
  }
  function enhance(){
   $body.find('td.blue-actions-cell').each(function(){
    var $cell=$(this);
    $cell.children('.s3-edit').attr('data-blue-action-label','Edit');
    $cell.children('.s3-status').attr('data-blue-action-label',function(){return $(this).text();});
   });
   if($.blueActionMenu)$.blueActionMenu.enhance($body.find('.blue-actions-cell'));
  }
  function render(){
   var matches=selectedRows(),total=matches.length;
   page=Math.max(1,Math.min(page,Math.max(1,Math.ceil(total/pageSize))));
   var start=(page-1)*pageSize,shown=matches.slice(start,start+pageSize);
   dataRows().forEach(function(row){$(row).toggle(shown.includes(row));});
   $body.find('tr.s3-empty').remove();
   if(!total)$body.append($('<tr>',{'class':'s3-empty'}).append($('<td>',{colspan:cols,'class':'text-muted text-center p-3',text:dataRows().length?'No matching accounts.':'No accounts configured.'})));
   enhance();if(exports)exports.refresh();
   $summary.text('Showing '+(total?start+1:0)+'–'+Math.min(total,start+pageSize)+' of '+total+' records');
   if($.blueVoucherPagination)$.blueVoucherPagination.render({container:$pages,summary:$summary,total:total,length:pageSize,start:start,buttonClass:'dv-page-btn',
    onPage:function(s){page=Math.floor(s/pageSize)+1;render();}});
  }
  $table.find('thead th').each(function(i){
   if(i===cols-1)return;
   var name=$.trim($(this).text());
   $(this).append($('<button>',{type:'button','class':'bm-account-col','data-col':i,'aria-label':'Sort or filter '+name})
     .append($('<i>',{'class':'fas fa-caret-down'})));
  });
  widget=$.financeVoucherComponents&&$.financeVoucherComponents.createFilter?$.financeVoucherComponents.createFilter({kind:'heading',accent:'#2563eb'}):null;
  $table.on('click','.bm-account-col',function(event){
   event.preventDefault();event.stopPropagation();
   if(!widget)return;
   var i=Number($(this).attr('data-col'));active=i;
   widget.setTitle($.trim($(this).closest('th').text()));
   widget.search(filters[i]||'',function(q){if(q)filters[i]=q;else delete filters[i];page=1;render();},'Search column...');
   widget.position($(this));
  });
  if(widget){
   widget.$popup.find('.bm-col-asc,.bm-col-desc').on('click',function(){
    if(active<0)return;sort=active;desc=$(this).hasClass('bm-col-desc');page=1;widget.close();render();
   });
   widget.$popup.find('.bm-col-clear').on('click',function(){
    if(active<0)return;delete filters[active];if(sort===active)sort=-1;page=1;widget.close();render();
   });
  }
  $size.on('change',function(){pageSize=Number($(this).val())||25;page=1;render();});
  $reset.on('click',function(){filters={};sort=-1;desc=false;page=1;pageSize=25;$size.val('25');if(widget)widget.close();render();});
  if($.blueModernDirectoryTools)exports=$.blueModernDirectoryTools.mount({card:$card,table:$table,title:type==='bank'?'Bank Accounts':'Cash Accounts',
   getRows:function(){return selectedRows().map(function(row){return $(row).children('td').map(function(){return $.trim($(this).text());}).get();});}});
  $(document).on('s3:table-updated',function(_,updated){if(updated===type){page=1;render();}});
  render();
 }
 directory('bank');directory('cash');
});
})(window.jQuery);
