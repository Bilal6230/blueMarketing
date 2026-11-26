/* Attendance Settings: reuse the same modern directory primitives as Users.
   Presentation only. Existing device and mapping AJAX handlers remain authoritative. */
(function($){
'use strict';
if(!$)return;
$(function(){
  if(!$('#as-workspace').length)return;
  function setup(tableId,title){
    var $table=$(tableId),$card=$table.closest('.as-card'),$body=$table.children('tbody');
    if(!$table.length)return;
    var columns=$table.find('thead th').length, filters={}, sortColumn=-1,descending=false,
        page=1,size=25, stateRows=[], widget=null,activeColumn=null,$activeButton=null, exports=null;
    var $footer=$('<div>',{'class':'bm-account-footer as-directory-footer'}),
        $summary=$('<small>',{'class':'text-muted'}),
        $pageSize=$('<select>',{'aria-label':'Rows per page'}),
        $reset=$('<button>',{type:'button','class':'btn btn-outline-secondary btn-sm',text:'Reset filters'}),
        $pages=$('<div>',{'class':'bm-account-pages'});
    [10,25,50,100].forEach(function(v){$pageSize.append($('<option>',{value:v,text:v,selected:v===25}));});
    $footer.append($('<div>',{'class':'bm-account-footer-left'}).append($summary,$pageSize,$reset),$pages);
    $card.append($footer);
    function getRows(){return $body.children('tr[data-id],tr[data-mapping-id]').toArray();}
    function value(row,col){return $.trim($(row).children('td').eq(col).text()).replace(/\\s+/g,' ');}
    function matches(){
      return stateRows.filter(function(row){return Object.keys(filters).every(function(key){
        return value(row,Number(key)).toLowerCase().includes(filters[key].toLowerCase());
      });}).sort(function(a,b){
        if(sortColumn<0)return 0;
        var x=value(a,sortColumn),y=value(b,sortColumn),nx=Number(x),ny=Number(y);
        var cmp=x&&y&&!isNaN(nx)&&!isNaN(ny)?nx-ny:x.localeCompare(y,undefined,{numeric:true,sensitivity:'base'});
        return descending?-cmp:cmp;
      });
    }
    function refresh(){
      // Device/mapping AJAX replaces nodes; do not keep stale row references.
      stateRows=getRows();
      if($.blueActionMenu)$.blueActionMenu.close();
      $body.find('.as-no-results').hide();
      var result=matches(), total=result.length;
      page=Math.max(1,Math.min(page,Math.max(1,Math.ceil(total/size))));
      var start=(page-1)*size, selected=result.slice(start,start+size);
      stateRows.forEach(function(row){$(row).toggle(selected.indexOf(row)!==-1);});
      if(!total){
        var $empty=$body.children('.as-no-results').first();
        if(!$empty.length)$empty=$('<tr>',{'class':'as-no-results as-directory-empty'}).append($('<td>',{colspan:columns,'class':'as-table-empty'})).appendTo($body);
        $empty.find('td').text(stateRows.length?'No matching records found.':'No records available.');
        $empty.css('display','table-row');
      }
      if($.blueActionMenu)$.blueActionMenu.enhance($body.find('td.blue-actions-cell'));
      if(exports)exports.refresh();
      $summary.text('Showing '+(total?start+1:0)+'–'+Math.min(total,start+size)+' of '+total+' records');
      if($.blueVoucherPagination)$.blueVoucherPagination.render({
        container:$pages,summary:$summary,total:total,length:size,start:start,buttonClass:'dv-page-btn',
        onPage:function(next){page=Math.floor(next/size)+1;refresh();}
      });
      $table.find('.bm-account-col').each(function(){
        var n=Number($(this).attr('data-col'));
        $(this).toggleClass('is-filtered',!!filters[n]||sortColumn===n);
      });
    }
    $table.find('thead th').each(function(index){
      if(index===columns-1)return;
      var $th=$(this),label=$.trim($th.text());
      $th.append($('<button>',{type:'button','class':'bm-account-col','data-col':index,
        'aria-label':'Sort or filter '+label,'aria-haspopup':'dialog','aria-expanded':'false'})
        .append($('<i>',{'class':'fas fa-caret-down','aria-hidden':'true'})));
    });
    widget=$.financeVoucherComponents&&$.financeVoucherComponents.createFilter?
      $.financeVoucherComponents.createFilter({kind:'heading',accent:'#2563eb'}):null;
    function closePopup(){
      if(widget)widget.close();
      if($activeButton)$activeButton.attr('aria-expanded','false');
      activeColumn=null;$activeButton=null;
    }
    $table.on('click','.bm-account-col',function(event){
      event.preventDefault();event.stopPropagation();
      var $button=$(this),n=Number($button.attr('data-col'));
      if(activeColumn===n){closePopup();return;}
      closePopup();activeColumn=n;$activeButton=$button;$button.attr('aria-expanded','true');
      if(!widget)return;
      var label=$.trim($button.closest('th').text());
      widget.setTitle(label);
      widget.search(filters[n]||'',function(q){
        if(q)filters[n]=q;else delete filters[n];
        page=1;refresh();
      },'Search '+label+'...');
      widget.position($button);widget.$control.find('input').first().trigger('focus');
    });
    if(widget){
      widget.$popup.find('.bm-col-asc,.bm-col-desc').on('click',function(){
        if(activeColumn===null)return;
        sortColumn=activeColumn;descending=$(this).hasClass('bm-col-desc');
        closePopup();page=1;refresh();
      });
      widget.$popup.find('.bm-col-clear').on('click',function(){
        if(activeColumn===null)return;
        delete filters[activeColumn];
        if(sortColumn===activeColumn)sortColumn=-1;
        closePopup();page=1;refresh();
      });
      $(document).on('click.asDirectory'+$table.attr('id'),function(e){
        if(!$(e.target).closest(widget.$popup).length&&!$(e.target).closest($table.find('.bm-account-col')).length)closePopup();
      });
    }
    $reset.on('click',function(){filters={};sortColumn=-1;descending=false;page=1;size=25;$pageSize.val('25');closePopup();refresh();});
    $pageSize.on('change',function(){size=Number($(this).val())||25;page=1;refresh();});
        // Move action buttons into shared source wrapper by enhancing original cells.
    function enhanceActions(){
      $body.find('td.blue-actions-cell').each(function(){
        var $cell=$(this),$actions=$cell.children('.as-actions');
        if(!$actions.length)return;
        $actions.children('button').attr('type','button').appendTo($cell);
        $actions.remove();
        $cell.children('.as-edit-device,.as-edit-mapping').attr('data-blue-action-label','Edit');
        $cell.children('.as-select-device').attr({'data-blue-action-label':'Map IDs','data-blue-action-type':'view'});
        $cell.children('.as-delete-device,.as-delete-mapping').attr('data-blue-action-label','Delete');
      });
    }
    function update(){enhanceActions();refresh();}
    $(document).on('attendance:tables-updated',update);
    update();
  }
  setup('#as-device-table','Attendance Devices');
  setup('#as-mapping-table','Employee ID Mappings');
});
})(window.jQuery);
