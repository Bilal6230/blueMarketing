/* Canonical Finance Voucher UI primitives.
 * Single renderer for shared popup chrome and DataTables pagination.
 * Data adapters retain their own read-only query and permission logic.
 * Never creates/posts/updates financial records.
 */
(function($){
'use strict';
if(!$||$.financeVoucherComponents)return;
function createFilter(options){
 var kind=options.kind==='datatable'?'fvd':'bm';
 var isData=kind==='fvd';
 var popClass=isData?'fvd-data-popover':'bm-heading-column-popover';
 var titleClass=isData?'fvd-pop-title':'bm-heading-column-title';
 var controlClass=isData?'fvd-pop-filter':'bm-heading-column-control';
 var actionsClass=isData?'fvd-pop-actions':'bm-heading-column-actions';
 var $popup=$('<div>',{'class':popClass+' fvc-filter-popover','role':'dialog','aria-label':'Voucher column filter'}).hide();
 var $title=$('<strong>',{'class':titleClass+' fvc-filter-title'});
 var $sort=$('<div>',{'class':'fvc-sort-options fvd-sort-options'});
 function sortButton(direction,label){
  var buttonClass=isData?(direction==='asc'?'fvd-sort-asc':'fvd-sort-desc'):(direction==='asc'?'bm-col-asc':'bm-col-desc');
  return $('<button>',{type:'button','class':buttonClass+' fvc-sort-button','data-direction':direction})
    .append($('<i>',{'class':direction==='asc'?'fas fa-sort-amount-down-alt mr-2':'fas fa-sort-amount-up-alt mr-2','aria-hidden':'true'}))
    .append($('<span>',{text:label}));
 }
 $sort.append(sortButton('asc','Sort A to Z / Small to Large'),sortButton('desc','Sort Z to A / Large to Small'));
 var $control=$('<div>',{'class':controlClass+' fvc-filter-control'});
 var $actions=$('<div>',{'class':actionsClass+' fvc-filter-actions'});
 var $clear=$('<button>',{type:'button','class':(isData?'fvd-clear':'bm-col-clear')+' fvc-clear btn btn-sm btn-light',text:'Clear'});
 $actions.append($clear);
 $popup.append($title,$sort,$control,$actions).css('--fvd-pop-accent',options.accent||'#087cfa');
 $('body').append($popup);
 function close(){$popup.hide().removeClass('fvc-open');}
 function position($anchor){
  var offset=$anchor.offset(),h=$popup.outerHeight()||250,w=$popup.outerWidth()||288;
  var left=Math.max(8,Math.min(offset.left-$(window).scrollLeft(),$(window).width()-w-8));
  var below=offset.top-$(window).scrollTop()+$anchor.outerHeight()+3;
  var top=below+h<=$(window).height()-8?below:Math.max(8,offset.top-$(window).scrollTop()-h-3);
  $popup.css({top:top+'px',left:left+'px'}).show().addClass('fvc-open');
 }
 function dates(values,onChange){
  $control.empty();
  $.each([['From Date','from'],['To Date','to']],function(_,part){
   var $label=$('<label>',{'class':'fvc-date-label fvd-date-range-label',text:part[0]});
   var $input=$('<input>',{type:'date','class':'form-control form-control-sm fvc-date-input fvd-date-control'})
     .val((values||{})[part[1]]||'');
   $input.on('change',function(){onChange(part[1],$(this).val());});
   $control.append($label.append($input));
  });
 }
 function search(value,onChange,placeholder){
  $control.empty();
  var $input=$('<input>',{type:'search','class':'form-control form-control-sm fvc-search-input bm-heading-column-input fvd-search-input',
   placeholder:placeholder||'Search values...',value:value||''});
  $input.on('input',function(){onChange($(this).val());});
  $control.append($input);
 }
 function choices(values,selected,onApply){
  var draft=new Set(selected||values||[]),items=(values||[]).map(String);
  $control.empty();
  var $search=$('<input>',{type:'search','class':'form-control form-control-sm fvc-choices-search',placeholder:'Search values...'});
  var $list=$('<div>',{'class':'fvc-choices-list'});
  var $footer=$('<div>',{'class':'fvc-choices-actions'});
  function render(){
   var query=String($search.val()||'').toLowerCase();
   var shown=items.filter(function(value){return value.toLowerCase().includes(query);});
   $list.empty();
   var $all=$('<label>',{'class':'fvc-choice'})
     .append($('<input>',{type:'checkbox','class':'fvc-check-all',checked:shown.length>0&&shown.every(v=>draft.has(v))}))
     .append($('<span>',{text:'(Select All Results)'}));
   $list.append($all);
   shown.forEach(function(value){
    $list.append($('<label>',{'class':'fvc-choice'})
     .append($('<input>',{type:'checkbox','class':'fvc-check-item',value:value,checked:draft.has(value)}))
     .append($('<span>',{text:value||'(Blank)'})));
   });
  }
  $search.on('input',render);
  $list.on('change','.fvc-check-all',function(){
   var query=String($search.val()||'').toLowerCase(),checked=$(this).is(':checked');
   items.filter(v=>v.toLowerCase().includes(query)).forEach(v=>{if(checked)draft.add(v);else draft.delete(v);});
   render();
  }).on('change','.fvc-check-item',function(){
   if($(this).is(':checked'))draft.add($(this).val());else draft.delete($(this).val());render();
  });
  $footer.append($('<button>',{type:'button','class':'btn btn-sm btn-light fvc-cancel',text:'Cancel'}).on('click',close));
  $footer.append($('<button>',{type:'button','class':'btn btn-sm btn-primary fvc-apply',text:'Apply'}).on('click',function(){
   onApply([...draft]);close();
  }));
  $control.append($search,$list,$footer);render();
 }
 return {$popup,$control,$title,$sort,$clear,close,position,dates,search,choices,
  setTitle:function(title){$title.text(title);}};
}
function attachDataTablePager(selector){
 if(!$.fn.DataTable||!$.blueVoucherPagination)return;
 var $table=$(selector);if(!$table.length||!$.fn.DataTable.isDataTable($table))return;
 if($table.data('fvc-pager'))return;
 $table.data('fvc-pager',true);
 var api=$table.DataTable(),$wrap=$table.closest('.dataTables_wrapper');
 if(!$wrap.length)return;
 // DataTables still owns page state / server-side AJAX; only its UI is replaced.
 var $native=$wrap.find('.dataTables_paginate').first(),$footer=$native.parent();
 if(!$footer.length)$footer=$wrap;
 var $nav=$('<nav>',{'class':'fvc-pagination dv-pagination','aria-label':'Voucher pages'});
 var $summary=$('<div>',{'class':'fvc-pagination-info'});
 $native.hide();
 $footer.addClass('fvc-pagination-footer');
 $footer.find('.dataTables_info').hide();
 $footer.append($summary,$nav);
 function render(){
  var info=api.page.info();if(!info)return;
  $.blueVoucherPagination.render({container:$nav,summary:$summary,total:info.recordsDisplay,
   allTotal:info.recordsTotal,length:info.length,start:info.start,
   buttonClass:'dv-page-btn fvc-page-btn',
   onPage:function(start){api.page(Math.floor(start/Math.max(info.length,1))).draw('page');}});
 }
 $table.on('draw.dt.fvcPaginator length.dt.fvcPaginator',render);
 render();
}
$.financeVoucherComponents={createFilter,attachDataTablePager,
 attachAllPagers:function(){
  ['#jv-directory','#journalTable','#bmDraftTable','#example1'].forEach(attachDataTablePager);
 }};
$(function(){
 $.financeVoucherComponents.attachAllPagers();
 $(document).on('init.dt.fvcPaginator',function(event,settings){
  if(!settings||!settings.nTable)return;
  var id='#'+settings.nTable.id;
  if(['#jv-directory','#journalTable','#bmDraftTable','#example1'].includes(id)){
   // DataTables may initialize after this script depending on the page.
   $.financeVoucherComponents.attachDataTablePager(id);
  }
 });
});
})(jQuery);
