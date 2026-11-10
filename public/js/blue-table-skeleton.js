/* Shared jQuery-only skeleton for AJAX-loaded tables. */
(function($){'use strict';$.blueTableSkeleton={
show:function(target,opts){var $body=$(target);if(!$body.length)return;var p=$.extend({rows:10,columns:8,tone:'neutral',widths:[]},opts||{});var n=Math.min(30,Math.max(1,parseInt(p.rows,10)||10));var cols=Math.min(30,Math.max(1,parseInt(p.columns,10)||8));var tone=$.inArray(p.tone,['credit','debit'])!==-1?p.tone:'neutral';$body.closest('.table-responsive').attr('aria-busy','true');$body.empty();for(var i=0;i<n;i++){var $tr=$('<tr>').addClass('bm-skeleton-row bm-skeleton-'+tone).attr('aria-hidden','true');for(var j=0;j<cols;j++){var width=Number(p.widths[j])||[68,62,79,87,58,65,91,70][j%8];$('<td>').append($('<span>').addClass('bm-skeleton-bar').css('width',Math.min(95,Math.max(22,width-((i+j)%3)*10))+'%')).appendTo($tr)}$body.append($tr)}},
clear:function(target){$(target).closest('.table-responsive').removeAttr('aria-busy')}
}})(jQuery);
