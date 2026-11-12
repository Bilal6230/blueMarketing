/* BlueMarketing global actions menu — first-party jQuery; no dependency on Bootstrap dropdowns.
 * Usage: $.blueActionMenu.enhance($cells);
 * Add class blue-actions-cell to a <td> containing the row's original buttons/anchors.
 * Originals remain in the row so tbody-delegated handlers and permission checks are preserved.
 */
(function($){
    'use strict';
    if(!$ || $.blueActionMenu)return;
    let $openToggle=null,$panel=null,$sourceCell=null,serial=0;
    const iconFor=($source,label)=>{
        const $icon=$source.find('i').first(),cls=$icon.attr('class');
        if(cls)return cls;
        if(/print/i.test(label))return 'fas fa-print';
        if(/detail|view/i.test(label))return 'fas fa-eye';
        if(/history/i.test(label))return 'fas fa-history';
        if(/revise/i.test(label))return 'fas fa-redo';
        if(/edit/i.test(label))return 'fas fa-edit';
        if(/delete|void|release|remove/i.test(label))return 'fas fa-unlink';
        if(/confirm|approve|clearing/i.test(label))return 'fas fa-check-circle';
        return 'fas fa-angle-right';
    };
    const labelFor=$source=>{
        const label=$source.attr('data-blue-action-label')||$.trim($source.text())||
            $source.attr('title')||$source.attr('aria-label');
        return $.trim(label.replace(/\s+/g,' '))||'Open';
    };
    const isDanger=($source,label)=>/delete|void|release|reject|bounce|return|cancel|remove/i.test(label)||
        $source.hasClass('btn-danger')||$source.hasClass('btn-outline-danger');
    const close=()=>{
        if(!$panel)return;
        $panel.removeClass('is-open');
        if($openToggle)$openToggle.attr('aria-expanded','false');
        $panel.remove();
        $panel=null;$openToggle=null;$sourceCell=null;
    };
    const place=()=>{
        if(!$panel||!$openToggle || !$openToggle.closest('body').length){close();return;}
        const $win=$(window),top=$openToggle.offset().top-$win.scrollTop(),
            left=$openToggle.offset().left-$win.scrollLeft(),
            h=$panel.outerHeight(),w=$panel.outerWidth(),
            viewHeight=$win.height(),viewWidth=$win.width();
        let menuTop=top+$openToggle.outerHeight()+6,up=false;
        if(menuTop+h>viewHeight-10 && top>h+12){
            menuTop=top-h-6;up=true;
        }else menuTop=Math.max(8,Math.min(menuTop,viewHeight-h-8));
        const menuLeft=Math.max(8,Math.min(left+$openToggle.outerWidth()-w,viewWidth-w-8));
        $panel.css({top:menuTop+'px',left:menuLeft+'px'}).toggleClass('is-up',up);
    };
    const open=$toggle=>{
        const $cell=$toggle.closest('.blue-actions-cell');
        if(!$cell.length)return;
        if($openToggle && $openToggle.is($toggle)){close();return;}
        close();
        const $items=$cell.find('> .blue-actions-source > button, > .blue-actions-source > a');
        if(!$items.length)return;
        const id='blue-actions-pop-'+(++serial);
        $panel=$('<div>',{'class':'blue-actions-panel',id,role:'menu','aria-label':'Voucher and transaction actions'});
        $panel.append($('<div>',{'class':'blue-actions-label',text:'Available actions'}));
        $items.each(function(){
            const source=this,$source=$(source),label=labelFor($source);
            if($source.is(':hidden') && !$source.closest('.blue-actions-source').length)return;
            if($source.hasClass('d-none')||$source.attr('hidden')!==undefined)return;
            const danger=isDanger($source,label);
            const $item=$('<button>',{
                type:'button',role:'menuitem','class':'blue-actions-item'+(danger?' is-danger':''),
                disabled:$source.prop('disabled')
            }).append($('<i>',{'class':iconFor($source,label),'aria-hidden':'true'}))
              .append($('<span>',{text:label}));
            $item.on('click.blueActionMenu',function(event){
                event.preventDefault();event.stopPropagation();
                if($source.prop('disabled'))return;
                close();
                // Trigger on the original in-table element; existing delegated
                // financial action handlers keep working without rebinding.
                $source.trigger('click');
            });
            $panel.append($item);
        });
        if(!$panel.find('.blue-actions-item').length){close();return;}
        $sourceCell=$cell;$openToggle=$toggle;
        $('body').append($panel);
        $toggle.attr({'aria-expanded':'true','aria-controls':id});
        place();
        // A paint boundary lets opacity/scale animate instead of appearing instantly.
        setTimeout(()=>{if($panel)$panel.addClass('is-open');},15);
    };
    const enhance=$cells=>{
        $($cells).each(function(){
            const $cell=$(this);
            if($cell.find('> .blue-actions-toggle').length)return;
            const $children=$cell.children('button,a');
            if(!$children.length)return;
            const $source=$('<div>',{'class':'blue-actions-source','aria-hidden':'true'});
            // append() moves rather than clones, keeping all bound handlers.
            $source.append($children);
            const $trigger=$('<button>',{
                type:'button','class':'blue-actions-toggle','aria-label':'Open row actions',
                'aria-haspopup':'menu','aria-expanded':'false',title:'Actions'
            });
            $cell.addClass('blue-actions-cell').append($source,$trigger);
        });
    };
    $.blueActionMenu={enhance,close};
    $(document).on('click.blueActionMenu','.blue-actions-toggle',function(event){
        event.preventDefault();event.stopPropagation();open($(this));
    });
    $(document).on('pointerdown.blueActionMenu',function(event){
        if(!$panel)return;
        if($(event.target).closest('.blue-actions-panel,.blue-actions-toggle').length)return;
        close();
    });
    $(document).on('keydown.blueActionMenu',function(event){
        if(!$panel)return;
        if(event.key==='Escape'){
            event.preventDefault();
            const $focus=$openToggle;
            close();
            if($focus)$focus.trigger('focus');
        }
    });
    $(window).on('resize.blueActionMenu scroll.blueActionMenu',close);
    $(document).on('scroll.blueActionMenu','.table-responsive,.dataTables_scrollBody',close);
    $(document).on('ajax:panel-updated.blueActionMenu',close);
})(window.jQuery);
