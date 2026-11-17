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
        maxOptions:200,maxItems:element.multiple?null:1,
        sortField:{field:'text',direction:'asc'},
        // Keep dropdown anchored to its input; appending to modal-content
        // placed its options at the very bottom of the screen.
        // Accounting tables are scroll containers. Their dropdowns must be
        // portaled to this document body to avoid clipping behind the table.
        // Other modal controls stay anchored to their own field.
        dropdownParent:$element.closest('.fvc-entry-table, .bt-modern-modal').length?'body':null,
        dropdownClass:'ts-dropdown fvc-global-ts-dropdown',
        onInitialize:function(){this.wrapper.classList.add('fvc-ts-ready');}});
    });
 }
 // The actual approved CR skeleton is $.blueTableSkeleton (real table rows).
 // Render that same row markup in a temporary overlay table: DataTables keeps
 // ownership of its tbody and active paging/filter state during AJAX redraws.
 function tableSkeleton($table,show){
    var $node=$($table),$wrap=$node.closest('.dataTables_wrapper');
    if(!$wrap.length)return;
    if(!show){
      $wrap.removeClass('fvc-table-loading').children('.fvc-table-skeleton').remove();
      return;
    }
    if($wrap.children('.fvc-table-skeleton').length)return;
    var $thead=$node.find('thead').first(),$headers=$thead.find('tr:first th');
    var cols=$headers.length||7;
    var $layer=$('<div>',{'class':'fvc-table-skeleton',role:'status',
      'aria-label':'Loading records'});
    var $ghost=$('<table>',{'class':'table table-bordered fvc-skeleton-table'})
      .append($('<tbody>'));
    if($.blueTableSkeleton){
      $.blueTableSkeleton.show($ghost.find('tbody'),{
        rows:10,columns:cols,tone:'neutral'
      });
    }
    // Match the actual live column widths without replacing DataTable nodes.
    var widths=[];
    $headers.each(function(){widths.push($(this).outerWidth());});
    if(widths.length){
      var $group=$('<colgroup>');
      $.each(widths,function(_,width){$group.append($('<col>').css('width',width+'px'));});
      $ghost.prepend($group);
    }
    // Overlay starts exactly BELOW the live heading row, like CR. The
    // toolbar and column headings remain visible throughout the request.
    var $heading=$node.find('thead').first();
    if($heading.length&&$heading.offset()&&$wrap.offset()){
      var top=Math.max(0,Math.ceil($heading.offset().top+
        $heading.outerHeight()-$wrap.offset().top));
      $layer.css('top',top+'px');
    }
    $layer.append($ghost);
    $wrap.addClass('fvc-table-loading').append($layer);
 }
 function bindTableSkeleton(selector){
    var $table=$(selector);
    if(!$table.length||$table.data('fvc-table-skeleton-bound'))return;
    $table.data('fvc-table-skeleton-bound',true);
    $table.on('preXhr.dt.fvcSkeleton',function(){tableSkeleton($table,true);})
      .on('draw.dt.fvcSkeleton error.dt.fvcSkeleton',function(event,settings){
        // Server AJAX redraw closes at completion. Client tables display the
        // exact same CR skeleton for the short filter-redraw transition and
        // close it with the adapter's completion timer.
        if(event.type==='error' || (settings&&settings.oFeatures&&settings.oFeatures.bServerSide))
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
   var $modal=$(this);
   // Extra uses TomSelect on every field, including dynamically relocated
   // collection/settlement controls. Other modules opt in explicitly.
   var $selects=$modal.hasClass('fvc-modal-purple')?
     $modal.find('select').not('[multiple]'):
     $modal.find('select.fvc-tom-select,select[data-posting-allocation],select[data-bank-settlement-account]');
   tom($selects);
 });
 $(document).ajaxSuccess(function(){
   $('.fvc-modern-modal.show select.tomselected, .bm-modern-draft select.tomselected').each(function(){
     if(this.tomselect)this.tomselect.sync();
   });
 });
 $.financeVoucherFeedback={toast:toast,error:safeError,enhanceSelects:tom,skeleton:skeleton,tableSkeleton:tableSkeleton,bindTableSkeleton:bindTableSkeleton};
})(jQuery);
