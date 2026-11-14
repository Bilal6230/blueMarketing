/* Global finance DataTable column filter/sort adapter. Pure jQuery; same UI for CV/Extra.
 * No posting, approvals, or account state changes. */
(function($){
'use strict';
var registry={};
$.financeVoucherDataColumns={
 values:function(selector){return registry[selector]||{filters:{},sort:'',direction:''};}
};
$(function(){
 // JV and SV already implement sort inside their Excel-style column popover.
 // Remove DataTables' second, independent header-click sorter.
 $('#jv-directory thead th').off('click.DT keypress.DT');
 function init(opts){
  var $table=$(opts.table);
  if(!$table.length||!$.fn.DataTable||!$.fn.DataTable.isDataTable($table))return;
  var state={filters:{},sort:'',direction:''};
  registry[opts.table]=state;
  var api=$table.DataTable(),fields=opts.fields,active=null;
  // Remove DataTables header-click sorting. The same sorting stays available
  // exclusively inside the canonical heading filter popover.
  $table.find('thead tr:first th').off('click.DT keypress.DT');
  var $popover=$('<div>',{'class':'fvd-data-popover','role':'dialog','aria-label':'Voucher column filter'})
   .append($('<strong>',{'class':'fvd-pop-title'}))
   .append($('<div>',{'class':'fvd-pop-filter'}))
   .append($('<div>',{'class':'fvd-pop-actions'})
    .append($('<button>',{type:'button','class':'btn btn-outline-primary btn-sm fvd-sort-asc'}).text('Sort ascending'))
    .append($('<button>',{type:'button','class':'btn btn-outline-primary btn-sm fvd-sort-desc'}).text('Sort descending'))
    .append($('<button>',{type:'button','class':'btn btn-outline-secondary btn-sm fvd-clear'}).text('Clear')));
  var $page=$table.closest('.bm-voucher-directory');
  $popover.css('--fvd-accent',$page.css('--fvd-accent')||
    (opts.id==='Extra'?'#7145bd':'#087cfa'));
  $('body').append($popover);
  function refresh(){
    if(opts.server)api.ajax.reload(null,true);
    else api.draw();
    $table.find('.fvd-column-trigger').each(function(){
      var key=$(this).data('fvd-column');
      $(this).toggleClass('bm-column-active',!!state.filters[key]||state.sort===key);
    });
  }
  function close(){
    $popover.hide();$table.find('.fvd-column-trigger').attr('aria-expanded','false');active=null;
  }
  $.each(fields,function(index,key){
    var $th=$table.find('thead tr:first th').eq(Number(index));
    if(!$th.length)return;
    var $trigger=$('<button>',{type:'button','class':'fvd-column-trigger',
      'aria-label':'Filter or sort '+$.trim($th.text()),'aria-haspopup':'dialog','aria-expanded':'false'})
     .append($('<i>',{'class':'fas fa-caret-down'}));
    $th.append($trigger);
    $trigger.on('click',function(event){
      event.preventDefault();event.stopPropagation();
      if(active===Number(index)){close();return;}
      close();active=Number(index);
      $popover.find('.fvd-pop-title').text($.trim($th.text()));
      var $input=$('<input>',{'class':'form-control form-control-sm fvd-search-input',
          type:key==='date'?'date':key==='total_debit'?'number':'search',
          placeholder:key==='date'?'':'Search '+$.trim($th.text()),value:state.filters[key]||''});
      $popover.find('.fvd-pop-filter').empty().append($input);
      $input.on('input change',function(){
        var val=$(this).val()||'';if(val)state.filters[key]=val;else delete state.filters[key];
        if(!opts.server)api.column(active).search(val);
        refresh();
      });
      var offset=$trigger.offset();
      $popover.css({left:Math.max(8,Math.min(offset.left-$(window).scrollLeft(),$(window).width()-290))+'px',
        top:Math.max(8,Math.min(offset.top-$(window).scrollTop()+$trigger.outerHeight()+4,$(window).height()-235))+'px'})
        .show();
      $trigger.attr('aria-expanded','true');$input.trigger('focus');
    });
  });
  function sort(dir){
    if(active===null)return;
    state.sort=fields[active];state.direction=dir;
    if(!opts.server)api.order([[active,dir]]);
    refresh();close();
  }
  $popover.find('.fvd-sort-asc').on('click',function(){sort('asc');});
  $popover.find('.fvd-sort-desc').on('click',function(){sort('desc');});
  $popover.find('.fvd-clear').on('click',function(){
    if(active===null)return;
    var field=fields[active];delete state.filters[field];
    if(!opts.server)api.column(active).search('');
    if(state.sort===field){state.sort='';state.direction='';if(!opts.server)api.order([[0,'asc']]);}
    refresh();close();
  });
  $(document).on('pointerdown.fvd'+opts.id,function(e){
    if(!$(e.target).closest($popover).length&&!$(e.target).closest($table.find('.fvd-column-trigger')).length)close();
  }).on('keydown.fvd'+opts.id,function(e){if(e.key==='Escape')close();});
 }
 init({id:'CV',table:'#journalTable',server:true,fields:{
  0:'id',1:'voucher_number',2:'reference',3:'date',4:'description',5:'total_debit'}});
 init({id:'Extra',table:'#example1',server:false,fields:{
  1:'date',2:'customer',3:'plot',4:'reference',5:'description',
  6:'amount',7:'received',8:'pending',9:'outstanding',11:'type',12:'approve'}});
});
})(jQuery);
