/* S4: modern Settings prefix editor, with legacy numbering still fail-closed. */
(function($){
'use strict';
$(function(){
 var $tab=$('#voucher-settings'),$modal=$('#s4-display-modal'),$form=$('#s4-display-form');
 if(!$tab.length||!$modal.length||!$form.length)return;
 $modal.appendTo('body');
 var $series=$form.find('[name=series]'),$prefix=$form.find('[name=prefix]'),busy=false;
 function notify(message,failed){
  if(window.toastr){failed?toastr.error(message):toastr.success(message);}
  else if(window.Swal)Swal.fire({icon:failed?'error':'success',text:message});
 }
 function fillSeries(series){
  $series.val(series||$series.find('option').first().val());
  $prefix.val($series.find('option:selected').attr('data-prefix')||$series.val());
 }
 $series.on('change',function(){fillSeries($(this).val());});
 $tab.on('click','#s4-configure',function(){fillSeries();$modal.modal('show');});
 $tab.on('click','.s4-edit',function(){fillSeries($(this).attr('data-s4-edit'));$modal.modal('show');});
 $form.on('submit',function(e){
  e.preventDefault();e.stopImmediatePropagation();
  if(busy)return;
  if(!this.checkValidity()){this.reportValidity();return;}
  function send(){
   busy=true;var $button=$form.find('button[type=submit]').prop('disabled',true);
   $.ajax({url:$form.attr('action'),method:'POST',data:$form.serialize(),
    dataType:'json',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}})
   .done(function(data){
    if(data.success===false){notify(data.message||'Unable to save prefix.',true);return;}
    var series=String(data.series||$series.val()),prefix=String(data.prefix||$prefix.val());
    $tab.find('tr[data-s4-series]').filter(function(){return $(this).attr('data-s4-series')===series;})
     .find('.s4-current-prefix').text(prefix);
    $series.find('option:selected').attr('data-prefix',prefix);
    $modal.modal('hide');
    notify(data.message||'Prefix configuration saved.',false);
   }).fail(function(xhr){
    var data=xhr.responseJSON||{},errors=data.errors||{},message=data.message||'Unable to save prefix.';
    $.each(errors,function(_,messages){message=messages[0]||message;return false;});
    notify(message,true);
   }).always(function(){busy=false;$button.prop('disabled',false);});
  }
  // Use the project's Bootstrap modal instead of relying on a globally loaded
  // SweetAlert instance. S4 can run on screens where Swal is not installed.
  $('#s4-prefix-confirm').modal('show');
  $('#s4-prefix-confirm-yes').off('click.s4').one('click.s4',function(){
   $('#s4-prefix-confirm').modal('hide');
   send();
  });
 });
 // Availability is a project-scoped control; never hide history or re-number vouchers.
 $tab.on('change','.s4-availability-toggle',function(){
  var $toggle=$(this),next=$toggle.prop('checked'),series=String($toggle.attr('data-s4-availability')||''),
      $label=$toggle.siblings('.s4-availability-label');
  if($toggle.data('busy'))return;
  $toggle.prop('disabled',true).data('busy',true);
  $.ajax({
   url:$tab.find('.s4-shell').attr('data-s4-status-url'),method:'POST',
   data:{_token:$tab.find('.s4-shell').attr('data-s4-token'),series:series,is_active:next?'1':'0'},
   dataType:'json',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}
  }).done(function(data){
   $label.text(data.is_active?'Active':'Inactive');
   notify(data.message||'Voucher availability updated.',false);
  }).fail(function(xhr){
   $toggle.prop('checked',!next);
   var data=xhr.responseJSON||{},message=data.message||'Unable to change voucher availability.';
   if(data.errors)$.each(data.errors,function(_,messages){message=messages[0]||message;return false;});
   notify(message,true);
  }).always(function(){$toggle.prop('disabled',false).data('busy',false);});
 });
 // Users-style directory actions, exports, sortable column popovers and pagination.
 (function directory(){
  var $table=$('#s4-directory-table'),$card=$table.closest('.s4-directory'),
      $body=$table.find('tbody'),$footer=$card.find('.s4-list-footer');
  if(!$table.length)return;
  var rows=$body.find('tr[data-s4-series]').toArray(),filters={},sortCol=-1,descending=false,
      page=1,pageSize=25,cols=$table.find('thead th').length,widget=null,active=-1,exporter=null;
  var $summary=$('<small>',{'class':'text-muted'}),$size=$('<select>',{'aria-label':'Rows per page'}),
      $reset=$('<button>',{type:'button','class':'btn btn-outline-secondary btn-sm',text:'Reset filters'}),
      $pages=$('<div>',{'class':'bm-account-pages'});
  [10,25,50,100].forEach(function(n){$size.append($('<option>',{value:n,text:n,selected:n===25}));});
  $footer.append($('<div>',{'class':'bm-account-footer-left'}).append($summary,$size,$reset),$pages);
  function textAt(row,col){return $.trim($(row).children('td').eq(col).text()).replace(/\s+/g,' ');}
  function matches(){
   return rows.filter(function(row){
    return Object.keys(filters).every(function(col){
     return textAt(row,Number(col)).toLowerCase().indexOf(filters[col].toLowerCase())!==-1;
    });
   }).sort(function(a,b){
    if(sortCol<0)return 0;
    var result=textAt(a,sortCol).localeCompare(textAt(b,sortCol),undefined,{numeric:true,sensitivity:'base'});
    return descending?-result:result;
   });
  }
  function render(){
   if($.blueActionMenu)$.blueActionMenu.close();
   var items=matches(),total=items.length;
   page=Math.max(1,Math.min(page,Math.max(1,Math.ceil(total/pageSize))));
   var start=(page-1)*pageSize,visible=items.slice(start,start+pageSize);
   rows.forEach(function(row){$(row).toggle(visible.indexOf(row)!==-1);});
   $body.children('.s4-empty').remove();
   if(!total)$body.append($('<tr>',{'class':'s4-empty'})
    .append($('<td>',{colspan:cols,'class':'text-muted text-center py-3',text:'No matching vouchers found.'})));
   if($.blueActionMenu)$.blueActionMenu.enhance($body.find('.blue-actions-cell'));
   if(exporter)exporter.refresh();
   $summary.text('Showing '+(total?start+1:0)+'–'+Math.min(total,start+pageSize)+' of '+total+' records');
   if($.blueVoucherPagination)$.blueVoucherPagination.render({
    container:$pages,summary:$summary,total:total,length:pageSize,start:start,buttonClass:'dv-page-btn',
    onPage:function(n){page=Math.floor(n/pageSize)+1;render();}
   });
   $table.find('.bm-account-col').each(function(){
    var n=Number($(this).attr('data-col'));
    $(this).toggleClass('is-filtered',!!filters[n]||sortCol===n);
   });
  }
  $table.find('thead th').each(function(col){
   if(col===cols-1)return;
   var title=$.trim($(this).text());
   $(this).append($('<button>',{type:'button','class':'bm-account-col','data-col':col,
    'aria-haspopup':'dialog','aria-label':'Sort or filter '+title})
    .append($('<i>',{'class':'fas fa-caret-down','aria-hidden':'true'})));
  });
  widget=$.financeVoucherComponents&&$.financeVoucherComponents.createFilter
    ?$.financeVoucherComponents.createFilter({kind:'heading',accent:'#2563eb'}):null;
  $table.on('click','.bm-account-col',function(event){
   event.preventDefault();event.stopPropagation();
   if(!widget)return;
   active=Number($(this).attr('data-col'));
   var label=$.trim($(this).closest('th').text());
   widget.setTitle(label);
   widget.search(filters[active]||'',function(value){
    if(value)filters[active]=value;else delete filters[active];
    page=1;render();
   },'Search '+label+'...');
   widget.position($(this));
  });
  if(widget){
   widget.$popup.find('.bm-col-asc,.bm-col-desc').on('click',function(){
    if(active<0)return;
    sortCol=active;descending=$(this).hasClass('bm-col-desc');page=1;widget.close();render();
   });
   widget.$popup.find('.bm-col-clear').on('click',function(){
    if(active<0)return;delete filters[active];if(sortCol===active)sortCol=-1;
    page=1;widget.close();render();
   });
  }
  $size.on('change',function(){pageSize=Number($(this).val())||25;page=1;render();});
  $reset.on('click',function(){
   filters={};sortCol=-1;descending=false;page=1;pageSize=25;$size.val('25');
   if(widget)widget.close();render();
  });
  if($.blueModernDirectoryTools)exporter=$.blueModernDirectoryTools.mount({
   card:$card,table:$table,title:'Voucher Display',
   getRows:function(){return matches().map(function(row){
    return $(row).children('td').map(function(){return $.trim($(this).text()).replace(/\s+/g,' ');}).get();
   });}
  });
  render();
 })();
});
})(window.jQuery);
