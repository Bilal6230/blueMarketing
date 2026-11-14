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
  if($table.data('fvc-filter-bound'))return;
  $table.data('fvc-filter-bound',true);
  var state={filters:{},dates:{from:'',to:''},sort:'',direction:''};
  registry[opts.table]=state;
  var api=$table.DataTable(),fields=opts.fields,active=null;
  // One shared scroll-body pattern. Toolbar and pagination remain outside
  // the scroll viewport at their CR-equivalent top/bottom positions.
  var $wrap=$table.closest('.dataTables_wrapper');
  if($wrap.length){
    var $scroll=$table.parent('.fvd-datatable-scroll,.table-responsive').first();
    if($scroll.length&&$.contains($wrap[0],$scroll[0])){
      $scroll.addClass('fvd-datatable-scroll');
    }else if(!$table.parent().hasClass('fvd-datatable-scroll')){
      $table.wrap('<div class="fvd-datatable-scroll"></div>');
    }
    $wrap.addClass('fvd-datatable-wrapper');
  }
  if(!opts.server){
    // Scope Date From/To checks to this exact DataTable, never other modules.
    $.fn.dataTable.ext.search.push(function(settings,row){
      if(settings.nTable!==$table[0])return true;
      var date=String(row[1]||'').trim().slice(0,10);
      if(state.dates.from&&date<state.dates.from)return false;
      if(state.dates.to&&date>state.dates.to)return false;
      return true;
    });
  }
  // Remove DataTables header-click sorting. The same sorting stays available
  // exclusively inside the canonical heading filter popover.
  $table.find('thead tr:first th').off('click.DT keypress.DT');
  // Shared CR-style filter popup: same markup as Cheque / Online.
  var widget=$.financeVoucherComponents.createFilter({kind:'datatable',
    accent:$table.closest('.bm-voucher-directory').css('--fvd-accent')||
      (opts.id==='Extra'?'#7145bd':'#087cfa')});
  var $popover=widget.$popup;
  function refresh(){
    if(opts.server)api.ajax.reload(null,true);
    else api.draw();
    $table.find('.fvd-column-trigger').each(function(){
      var key=$(this).data('fvd-column');
      $(this).toggleClass('bm-column-active',!!state.filters[key]||state.sort===key||(key==='date'&&(state.dates.from||state.dates.to)));
    });
  }
  function close(){
    widget.close();$table.find('.fvd-column-trigger').attr('aria-expanded','false');active=null;
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
      widget.setTitle($.trim($th.text()));
      var $body=$popover.find('.fvd-pop-filter').empty(),$input;
      if(key==='date'){
        widget.dates(state.dates,function(field,value){
          state.dates[field]=value;refresh();
        });
      }else{
        $input=$('<input>',{'class':'form-control form-control-sm fvd-search-input',
          type:key==='total_debit'?'number':'search',
          placeholder:'Search '+$.trim($th.text()),value:state.filters[key]||''});
        $body.append($input);
        $input.on('input change',function(){
          var val=$(this).val()||'';if(val)state.filters[key]=val;else delete state.filters[key];
          if(!opts.server)api.column(active).search(val);
          refresh();
        });
      }
      widget.position($trigger);
      $trigger.attr('aria-expanded','true');$body.find('input:first').trigger('focus');
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
    if(field==='date'){state.dates.from='';state.dates.to='';}
    if(!opts.server)api.column(active).search('');
    if(state.sort===field){state.sort='';state.direction='';if(!opts.server)api.order([[0,'asc']]);}
    refresh();close();
  });
  $(document).on('pointerdown.fvd'+opts.id,function(e){
    if(!$(e.target).closest($popover).length&&!$(e.target).closest($table.find('.fvd-column-trigger')).length)close();
  }).on('keydown.fvd'+opts.id,function(e){if(e.key==='Escape')close();});
 }
 var configurations=[
  {id:'CV',table:'#journalTable',server:true,fields:{
   0:'id',1:'voucher_number',2:'reference',3:'date',4:'description',5:'total_debit'}},
  {id:'Extra',table:'#example1',server:false,fields:{
   1:'date',2:'customer',3:'plot',4:'reference',5:'description',
   6:'amount',7:'received',8:'pending',9:'outstanding',11:'type',12:'approve'}},
  {id:'Draft',table:'#bmDraftTable',server:false,fields:{
   1:'date',2:'project',3:'head',4:'subhead',5:'detail',6:'amount',7:'type'}}
 ];
 $.each(configurations,function(_,configuration){init(configuration);});
 $(document).on('init.dt.fvcFilter',function(_,settings){
  if(!settings||!settings.nTable)return;
  $.each(configurations,function(_,configuration){
   if('#'+settings.nTable.id===configuration.table){
    // Let DataTables finish its own initialization before adding controls.
    setTimeout(function(){init(configuration);},0);
   }
  });
 });
});
})(jQuery);
