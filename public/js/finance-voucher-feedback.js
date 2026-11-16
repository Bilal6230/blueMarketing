/* Canonical finance feedback, Tom Select and form/iframe skeleton surfaces.
 * jQuery only. Reuse across voucher screens; never echo backend SQL/HTML.
 */
(function($){
 'use strict';
 if(!$ || $.financeVoucherFeedback)return;
 function toast(type,message){
    var value=String(message||'Operation failed. Please try again.').trim();
    var host=window;
    try{if(window.parent && window.parent!==window && window.parent.toastr)host=window.parent;}catch(_){}
    if(host.toastr && typeof host.toastr[type]==='function'){
      host.toastr.options= $.extend({},host.toastr.options||{},{closeButton:true,progressBar:true,timeOut:5500,escapeHtml:true});
      host.toastr[type](value);
    }else{
      // Accessible fallback, not native alert() and never untrusted HTML.
      var $item=$('<div>',{'class':'alert alert-'+(type==='success'?'success':'danger')+' fvc-feedback-fallback',role:'alert',text:value});
      $('body').append($item);$item.delay(6500).fadeOut(300,function(){$item.remove();});
    }
 }
 function safeError(xhr,defaultMessage){
    var data=xhr&&xhr.responseJSON||{};
    var codes={403:'You do not have permission to perform this action.',
      419:'Your session expired. Refresh the page and sign in again.',
      422:'Please correct the highlighted fields.',
      409:'This voucher cannot be saved in its current state.',
      500:'Unable to save. Contact the administrator.'};
    // Never present SQLSTATE, SQL, connection details, stack traces or HTML.
    var msg=String(data.message||codes[xhr&&xhr.status]||defaultMessage||'Unable to save.');
    if(/sqlstate|\bconnection\b|\binsert into\b|\bselect .*from\b|stack trace|\bexception\b/i.test(msg))
      msg=codes[xhr&&xhr.status]||'Unable to save. Contact the administrator.';
    var details=[];
    if(data.errors)$.each(data.errors,function(_,item){
      $.each($.isArray(item)?item:[item],function(_,entry){
        var t=String(entry||'');
        if(!/sqlstate|\bconnection\b|\binsert into\b|\bexception\b/i.test(t))details.push(t);
      });
    });
    return [msg].concat(details.slice(0,4)).join(' ');
 }
 function tom($select){
    if(typeof window.TomSelect!=='function')return;
    $($select).filter('select').each(function(){
      var element=this,$element=$(element);
      if(element.tomselect){element.tomselect.sync();return;}
      if($.fn.select2 && $element.data('select2'))$element.select2('destroy');
      // Old select2 classes and form styles must not paint a second select
      // over the canonical TomSelect control inside a finance modal.
      $element.removeClass('select2 select2-hidden-accessible').removeAttr('data-select2-id');
      new window.TomSelect(element,{create:false,allowEmptyOption:true,
        maxOptions:200,sortField:{field:'text',direction:'asc'},
        dropdownParent:element.closest('.modal-content')||'body',
        onInitialize:function(){this.wrapper.classList.add('fvc-ts-ready');}});
    });
 }
 function tableSkeleton($table,show){
    var $node=$($table),$wrap=$node.closest('.dataTables_wrapper');
    if(!$wrap.length)return;
    if(show){
      if($wrap.children('.fvc-table-skeleton').length)return;
      var $layer=$('<div>',{'class':'fvc-table-skeleton',role:'status','aria-label':'Loading vouchers'});
      for(var i=0;i<5;i++){
        var $row=$('<div>',{'class':'fvc-table-skeleton-row'});
        for(var j=0;j<5;j++)$row.append($('<span>',{'class':'fvc-skeleton-shimmer'}));
        $layer.append($row);
      }
      $wrap.addClass('fvc-table-loading').append($layer);
    }else $wrap.removeClass('fvc-table-loading').children('.fvc-table-skeleton').remove();
 }
 function bindTableSkeleton(selector){
    var $table=$(selector);
    if(!$table.length||$table.data('fvc-table-skeleton-bound'))return;
    $table.data('fvc-table-skeleton-bound',true);
    $table.on('preXhr.dt.fvcSkeleton processing.dt.fvcSkeleton',function(event,settings,state){
      if(event.type==='processing')tableSkeleton($table,!!state);
      else tableSkeleton($table,true);
    }).on('draw.dt.fvcSkeleton error.dt.fvcSkeleton xhr.dt.fvcSkeleton',function(){
      tableSkeleton($table,false);
    });

 }
 function skeleton($target,show){
    var $root=$($target);
    if(!$root.length)return;
    if(show){
      if($root.children('.fvc-modal-skeleton').length)return;
      var $placeholder=$('<div>',{'class':'fvc-modal-skeleton',role:'status','aria-label':'Loading form'});
      for(var i=0;i<4;i++){
        var $row=$('<div>',{'class':'fvc-skeleton-row'});
        for(var n=0;n<(i===2?1:3);n++)$row.append($('<span>',{'class':'fvc-skeleton-shimmer'}));
        $placeholder.append($row);
      }
      $root.addClass('fvc-is-loading').append($placeholder);
    }else{$root.removeClass('fvc-is-loading').children('.fvc-modal-skeleton').remove();}
 }
 // Modal contents can be detached and appended after page-ready (Extra/CV).
 // Initialize only when visible and resync choices populated by legacy AJAX.
 $(document).on('shown.bs.modal.fvcSelects','.fvc-modern-modal',function(){
   tom($(this).find('select').not('[multiple]'));
 });
 $(document).ajaxSuccess(function(){
   $('.fvc-modern-modal.show select.tomselected').each(function(){
     if(this.tomselect)this.tomselect.sync();
   });
 });
 $.financeVoucherFeedback={toast:toast,error:safeError,enhanceSelects:tom,skeleton:skeleton,tableSkeleton:tableSkeleton,bindTableSkeleton:bindTableSkeleton};
})(jQuery);
