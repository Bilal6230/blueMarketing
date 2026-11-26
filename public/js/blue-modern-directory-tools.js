/* Shared modern directory exports. Uses the existing DataTables Buttons/JSZip/pdfmake
   assets only for file generation, not for the visible directory's rendering. */
(function($){
'use strict';
if(!$)return;
var counter=0;
function mount(options){
  var $table=$(options.table).first(),$card=$(options.card).first();
  if(!$table.length||!$card.length||$card.data('bm-export-mounted'))return null;
  $card.data('bm-export-mounted',true);
  var id=$table.attr('id')||('bmExportTable'+(++counter));
  $table.attr('id',id);
  var titles=$table.find('thead th').map(function(){return $.trim($(this).find('span').first().text()||$(this).clone().children('button').remove().end().text());}).get();
  var actions=titles.map(function(t,i){return /^(actions?|options?)$/i.test(t)?i:-1;}).filter(function(i){return i>=0;});
  var enabled=titles.map(function(){return true;});
  var $toolbar=$('<div>',{'class':'bm-modern-export-toolbar','aria-label':'Table exports and column controls'});
  var formats=[['copy','Copy'],['csv','CSV'],['excel','Excel'],['pdf','PDF'],['print','Print']];
  var $visibility=$('<div>',{'class':'bm-modern-column-menu'});
  var $toggle=$('<button>',{type:'button','class':'bm-modern-export-btn bm-modern-col-toggle','aria-expanded':'false',text:'Column Visibility'});
  var $menu=$('<div>',{'class':'bm-modern-column-options',role:'group','aria-label':'Visible table columns'}).hide();
  titles.forEach(function(title,i){
    var $label=$('<label>',{'class':'bm-modern-column-option'});
    var $check=$('<input>',{type:'checkbox',checked:true,'data-column':i});
    $check.on('change',function(){
      enabled[i]=this.checked;
      // Retain a minimum of one data column.
      if(!enabled.some(function(on,j){return on&&actions.indexOf(j)<0;})){
        enabled[i]=true;$check.prop('checked',true);return;
      }
      applyVisibility();
    });
    $label.append($check,$('<span>',{text:title||('Column '+(i+1))}));$menu.append($label);
  });
  $toggle.on('click',function(event){
    event.stopPropagation();
    $menu.toggle();$toggle.attr('aria-expanded',$menu.is(':visible')?'true':'false');
  });
  $visibility.append($toggle,$menu);$toolbar.append($visibility);
  $(document).on('click.bmModernExports'+id,function(event){
    if(!$(event.target).closest($visibility).length){$menu.hide();$toggle.attr('aria-expanded','false');}
  });
  var $mirror=null,dt=null;
  function applyVisibility(){
    $table.find('tr').each(function(){
      $(this).children('th,td').each(function(i){$(this).toggle(enabled[i]!==false);});
    });
  }
  function exportNow(format){
    if(!$.fn.DataTable||!$.fn.dataTable.Buttons){
      if(window.toastr)window.toastr.error('Export library is unavailable. Please refresh the page.');
      return;
    }
    var indices=titles.map(function(_,i){return i;}).filter(function(i){return enabled[i]&&actions.indexOf(i)<0;});
    var data=options.getRows();
    if(!Array.isArray(data))data=[];
    // Source rows are filtered and sorted, not merely the current pagination page.
    var records=data.map(function(row){
      return indices.map(function(i){
        var v=Array.isArray(row)?row[i]:'';
        return v===null||v===undefined?'':String(v);
      });
    });
    if(dt){dt.destroy();$mirror.remove();dt=null;}
    $mirror=$('<table>',{'class':'bm-export-mirror','aria-hidden':'true'}).css({
      position:'fixed',left:'-10000px',top:'0',width:'900px',opacity:0,pointerEvents:'none'
    }).appendTo('body');
    var $head=$('<thead>').appendTo($mirror),$tr=$('<tr>').appendTo($head);
    indices.forEach(function(i){$tr.append($('<th>',{text:titles[i]}));});
    $mirror.append($('<tbody>'));
    var buttonName={copy:'copyHtml5',csv:'csvHtml5',excel:'excelHtml5',pdf:'pdfHtml5',print:'print'}[format];
    var title=options.title||'Directory';
    dt=$mirror.DataTable({
      dom:'B',data:records,columns:indices.map(function(){return {render:$.fn.dataTable.render.text()};}),
      paging:false,searching:false,ordering:false,info:false,autoWidth:false,
      buttons:[{extend:buttonName,title:title,filename:title.replace(/[^a-z0-9_-]+/gi,'_'),
        exportOptions:{columns:':visible',modifier:{search:'none',order:'index',page:'all'}}}]
    });
    dt.button(0).trigger();
  }
  formats.forEach(function(pair){
    $toolbar.append($('<button>',{type:'button','class':'bm-modern-export-btn','data-format':pair[0],text:pair[1]})
      .on('click',function(){exportNow(pair[0]);}));
  });
  var $placeholder=$card.find('.bm-modern-export-toolbar.bm-export-placeholder').first();
  $placeholder.find('.bm-user-toolbar-actions,.bm-permission-toolbar-actions,.bm-plot-toolbar-actions').appendTo($toolbar);
  $placeholder.remove();
  var $wrap=$table.closest('.bm-account-table-wrap,.project-table-wrap,.sector-table-wrap,.area-table-wrap');
  if($wrap.length)$toolbar.insertBefore($wrap);else $card.prepend($toolbar);
  return {refresh:applyVisibility};
}
/* Show loading placeholders only in table body cells, leaving toolbar, headings
   and pagination interactive and stationary. No document-level overlay. */
function skeleton(selector){
  var $wrap=$(selector).first(),$table=$wrap.find('table').first(),$body=$table.find('tbody').first();
  if(!$body.length)return function(){};
  var $old=$body.children().detach(),columns=$table.find('thead tr').last().children('th').length;
  columns=Math.max(1,columns);
  for(var r=0;r<6;r++){
    var $row=$('<tr>',{'class':'bm-cell-skeleton-row','aria-hidden':'true'});
    for(var i=0;i<columns;i++){
      $row.append($('<td>').append($('<span>',{'class':'bm-cell-skeleton'})));
    }
    $body.append($row);
  }
  $body.attr('aria-busy','true');
  var finished=false;
  return function(){
    if(finished)return;
    finished=true;
    $body.children('.bm-cell-skeleton-row').remove();
    if(!$body.children().length)$body.append($old);
    $body.attr('aria-busy','false');
  };
}
$.blueModernDirectoryTools={mount:mount,skeleton:skeleton};
})(jQuery);
