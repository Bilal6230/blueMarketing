/* Shared jQuery ledger report table. Read-only: works with existing finance report JSON. */
(function($){
 'use strict';
 $(function(){
  var $root=$('#financeReportRoot');
  if(!$root.length || !$.fn.DataTable)return;
  var party=$root.attr('data-mode')==='party';
  var $table=$('#financeReportTable'),$error=$('#financeReportError');
  var names=party?['Project','Head Account','Party Account','Cash In','Cash Out','Balance']:['Account','Cash In','Cash Out','Balance'];
  var keys=party?['project_name','head_account_name','subhead_account_name','total_amount_in','total_amount_out','balance_amount']:
   ['head_account_name','total_amount_in','total_amount_out','balance_amount'];
  var fields=keys.map(function(key,index){return {
   data:key,name:key,defaultContent:'',title:names[index],render:$.fn.dataTable.render.text()
  };});
  var $search=$('#financeReportSearch'),searchTimer=null,widget=null,opened=-1;
  var loading=false;
  function closeSkeleton(){
   loading=false;
   if($.financeVoucherFeedback&&$.financeVoucherFeedback.tableSkeleton)
    $.financeVoucherFeedback.tableSkeleton($table,false);
  }
  function notify(message){
   $error.text(message).toggleClass('d-none',!message);
   if(message&&$.financeVoucherFeedback&&$.financeVoucherFeedback.error)
    $.financeVoucherFeedback.error(message);
  }
  function cents(raw){
   var value=String(raw==null?'0':raw).replace(/,/g,'').trim();
   var match=value.match(/^(-?)(\d+)(?:\.(\d{1,2}))?$/);
   if(!match)return 0;
   var n=Number(match[2])*100+Number((match[3]||'').padEnd(2,'0'));
   return match[1]==='-'?-n:n;
  }
  function money(value){
   var sign=value<0?'-':'',absolute=Math.abs(value);
   return sign+(Math.floor(absolute/100)).toLocaleString('en-US')+'.'+String(absolute%100).padStart(2,'0');
  }
  function paint($row,data){
   var start=party?3:1;
   $row.find('td').slice(start).each(function(i){
    var amount=cents(data[keys[start+i]]);
    $(this).addClass('fvr-number').toggleClass('fvr-positive',amount>0)
      .toggleClass('fvr-negative',amount<0);
   });
  }
  if($.financeVoucherFeedback&&$.financeVoucherFeedback.bindTableSkeleton)
   $.financeVoucherFeedback.bindTableSkeleton('#financeReportTable');
  $table.on('xhr.dt.fvrReport draw.dt.fvrReport error.dt.fvrReport',function(){closeSkeleton();});
  var api=$table.DataTable({
   processing:true,serverSide:false,autoWidth:false,deferRender:true,
   orderMulti:false,
   pageLength:25,lengthMenu:[[10,25,50,100],[10,25,50,100]],
   order:[[0,'asc']],columns:fields,dom:'Brtip',
   buttons:[
    {extend:'copy',text:'Copy',className:'btn btn-sm btn-outline-primary'},
    {extend:'csv',text:'CSV',className:'btn btn-sm btn-outline-primary'},
    {extend:'excel',text:'Excel',className:'btn btn-sm btn-outline-success'},
    {extend:'pdfHtml5',text:'PDF',className:'btn btn-sm btn-outline-danger'},
    {extend:'print',text:'Print',className:'btn btn-sm btn-outline-info'}
   ],
   ajax:{
    url:$root.attr('data-endpoint'),type:'POST',
    data:function(d){
     d._token=$('meta[name="csrf-token"]').attr('content');
     if(!party)d.account_type=$root.attr('data-account-type');
    },
    dataSrc:function(result){notify('');return result.data||[];},
    error:function(xhr){
     closeSkeleton();
     var message=xhr.status===403?'You do not have permission to view this report.':
      xhr.status===419?'Session expired. Please refresh the page.':
      'Unable to load this ledger report. Please retry.';
     notify(message);
     $root.find('.dataTables_processing').hide();

    }
   },
   createdRow:function(row,data){paint($(row),data);},
   footerCallback:function(){
    var currentTable=this.api();
    var totals=[0,0,0],start=party?3:1;
    currentTable.rows({page:'current',search:'applied'}).data().each(function(row){
     for(var j=0;j<3;j++)totals[j]+=cents(row[keys[start+j]]);
    });
    $('#financeReportTotalIn').text(money(totals[0]));
    $('#financeReportTotalOut').text(money(totals[1]));
    $('#financeReportTotalBalance').text(money(totals[2]))
      .toggleClass('fvr-negative',totals[2]<0);
   },
   initComplete:function(){
    closeSkeleton();
    // Only the shared column popover owns sorting; remove DataTables' native
    // heading click/keyboard sort handlers, but preserve API-driven sorting.
    $table.find('thead th').off('click.DT keypress.DT');
    var $buttons=$root.find('.dt-buttons').first();
    if($buttons.length)$buttons.appendTo('#financeReportExports');
    if($.financeVoucherComponents&&$.financeVoucherComponents.attachDataTablePager)
     $.financeVoucherComponents.attachDataTablePager('#financeReportTable');
   }
  });
  $('#financeReportLength').on('change',function(){api.page.len(Number($(this).val())||25).draw();});
  $search.on('input',function(){
   clearTimeout(searchTimer);
   var text=$(this).val();searchTimer=setTimeout(function(){api.search(text).draw();},220);
  });
  var filters={};
  if($.financeVoucherComponents&&$.financeVoucherComponents.createFilter){
   widget=$.financeVoucherComponents.createFilter({kind:'datatable',accent:'#1769e0'});
   var $popup=widget.$popup;
   function close(){widget.close();opened=-1;$table.find('.fvr-heading-filter').attr('aria-expanded','false');}
   function update(){
    $table.find('.fvr-heading-filter').each(function(){
     var n=Number($(this).attr('data-index'));
     $(this).toggleClass('bm-column-active',!!filters[n]);
    });
   }
   $table.find('thead th').off('click.DT keypress.DT').each(function(index){
    var $th=$(this),title=names[index];
    var $button=$('<button>',{type:'button','class':'fvr-heading-filter fvd-column-trigger',
      'aria-label':'Filter or sort '+title,'aria-haspopup':'dialog','aria-expanded':'false','data-index':index})
     .append($('<i>',{'class':'fas fa-caret-down','aria-hidden':'true'}));
    $th.append($button);
    $button.on('click',function(e){
     e.preventDefault();e.stopPropagation();
     if(opened===index){close();return;}
     close();opened=index;widget.setTitle(title);
     widget.search(filters[index]||'',function(value){
      filters[index]=value;api.column(index).search(value).draw();update();
     },'Search '+title);
     widget.position($button);$button.attr('aria-expanded','true');
    });
   });
   $popup.find('.fvd-sort-asc').on('click',function(){if(opened>=0)api.order([[opened,'asc']]).draw();close();});
   $popup.find('.fvd-sort-desc').on('click',function(){if(opened>=0)api.order([[opened,'desc']]).draw();close();});
   $popup.find('.fvd-clear').on('click',function(){
    if(opened>=0){filters[opened]='';api.column(opened).search('').draw();}
    update();close();
   });
   $(document).on('pointerdown.fvr',function(e){
    if(!$(e.target).closest($popup).length&&!$(e.target).closest('.fvr-heading-filter').length)close();
   }).on('keydown.fvr',function(e){if(e.key==='Escape')close();});
  }

 });
})(jQuery);
