/* BlueMarketing UI / globally reusable floating speed dial.
 * Existing actions remain in their original table cells; permissions and
 * delegated event handlers are never recreated or bypassed.
 * $.blueActionMenu.enhance($cells); $.blueActionMenu.close();
 */
(function($){
    'use strict';
    if(!$ || $.blueActionMenu)return;

    let active=null,sequence=0;
    const labels={
        details:['view','fas fa-eye'],view:['view','fas fa-eye'],
        history:['history','fas fa-history'],print:['print','fas fa-print'],
        revise:['revise','fas fa-redo'],edit:['edit','fas fa-edit'],
        approve:['approve','fas fa-check'],confirm:['approve','fas fa-check'],
        clearing:['approve','fas fa-check'],withdrawal:['approve','fas fa-check'],
        reject:['danger','fas fa-times'],release:['danger','fas fa-unlink'],
        void:['danger','fas fa-unlink'],delete:['danger','fas fa-trash'],
        returned:['danger','fas fa-undo'],bounced:['danger','fas fa-undo']
    };
    const readLabel=$source=>{
        let result=$source.attr('data-blue-action-label')||$.trim($source.text())||
            $source.attr('title')||$source.attr('aria-label')||'Action';
        if(!$.trim($source.text()) && !$source.attr('data-blue-action-label')){
            result=String(result).replace(/^(Edit|Print|Delete|View)\s+voucher\s+.*/i,'$1')
                .replace(/^Audited allocation release.*/i,'Release / Void');
        }
        return $.trim(String(result).replace(/\s+/g,' '))||'Action';
    };
    const kindFor=($source,label)=>{
        const term=String($source.attr('data-blue-action-type')||label).toLowerCase();
        const found=Object.keys(labels).find(key=>term.includes(key));
        if(found)return {tone:labels[found][0],icon:labels[found][1]};
        if($source.hasClass('btn-danger')||$source.hasClass('btn-outline-danger'))
            return {tone:'danger',icon:'fas fa-exclamation-triangle'};
        return {tone:'view',icon:'fas fa-bolt'};
    };
    const position=state=>{
        if(!state || !state.$toggle.closest('body').length){close();return;}
        const $w=$(window),offset=state.$toggle.offset();
        const top=offset.top-$w.scrollTop(),left=offset.left-$w.scrollLeft();
        const height=state.$rail.outerHeight(),width=state.$rail.outerWidth();
        const viewW=$w.width(),viewH=$w.height();
        // Floating vertical actions live beside the +, not inside a box.
        const onLeft=left>width+12 || left>viewW/2;
        let x=onLeft?left-width-10:left+state.$toggle.outerWidth()+10;
        x=Math.max(8,Math.min(viewW-width-8,x));
        let y=top+state.$toggle.outerHeight()/2-height/2;
        y=Math.max(8,Math.min(viewH-height-8,y));
        state.$rail.css({left:x+'px',top:y+'px'});
        state.$rail.toggleClass('blue-actions-right',!onLeft);
    };
    const close=()=>{
        if(!active)return;
        const old=active;
        active=null;
        old.$toggle.attr({'aria-expanded':'false','aria-label':'Open row actions'}).removeAttr('aria-controls');
        old.$rail.removeClass('is-open');
        // Allow items to collapse back before detaching the floating rail.
        setTimeout(()=>old.$rail.remove(),240);
    };
    const open=$toggle=>{
        if(active && active.$toggle.is($toggle)){close();return;}
        close();
        const $cell=$toggle.closest('.blue-actions-cell');
        if(!$cell.length)return;
        const $source=$cell.children('.blue-actions-source');
        const $actions=$source.children('button,a').filter(function(){
            const $item=$(this);
            return !$item.hasClass('d-none') && !$item.prop('hidden') &&
                $item.attr('aria-hidden')!=='true' && $item.css('display')!=='none';
        });
        if(!$actions.length)return;
        const id='blue-speed-dial-'+(++sequence);
        const $rail=$('<div>',{'class':'blue-actions-rail',id,role:'group','aria-label':'Row actions'});
        $actions.each(function(index){
            const $original=$(this),label=readLabel($original);
            const type=kindFor($original,label);
            const $item=$('<button>',{
                type:'button','class':'blue-actions-float blue-actions-'+type.tone,
                'aria-label':label,title:label,disabled:$original.prop('disabled')
            }).css('--action-index',index);
            $item.append($('<i>',{'class':type.icon,'aria-hidden':'true'}));
            $item.append($('<span>',{'class':'blue-actions-float-label',text:label}));
            $item.on('click.blueActionMenu',function(event){
                event.preventDefault();event.stopPropagation();
                if($original.prop('disabled'))return;
                close();
                // Execute original in-cell action so existing jQuery delegation,
                // HTTP methods, confirmation modals and security rules remain.
                $original.trigger('click');
            });
            $rail.append($item);
        });
        $('body').append($rail);
        active={$rail,$toggle};
        $toggle.attr({'aria-expanded':'true','aria-controls':id,'aria-label':'Close row actions'});
        position(active);
        setTimeout(()=>{if(active && active.$rail.is($rail))$rail.addClass('is-open');},20);
    };
    const enhance=cells=>{
        $(cells).each(function(){
            const $cell=$(this);
            if($cell.children('.blue-actions-toggle').length)return;
            const $buttons=$cell.children('button,a');
            if(!$buttons.length)return;
            const $source=$('<div>',{'class':'blue-actions-source','aria-hidden':'true'});
            $source.append($buttons);
            const $toggle=$('<button>',{
                type:'button','class':'blue-actions-toggle',
                'aria-label':'Open row actions','aria-expanded':'false',
                'aria-haspopup':'true',title:'Actions'
            });
            $cell.addClass('blue-actions-cell').append($source,$toggle);
        });
    };
    $.blueActionMenu={enhance,close};
    $(document).on('click.blueActionMenu','.blue-actions-toggle',function(event){
        event.preventDefault();event.stopPropagation();open($(this));
    });
    $(document).on('pointerdown.blueActionMenu',function(event){
        if(active && !$(event.target).closest('.blue-actions-rail,.blue-actions-toggle').length)close();
    });
    $(document).on('keydown.blueActionMenu',function(event){
        if(!active)return;
        if(event.key==='Escape'){
            event.preventDefault();
            const $focus=active.$toggle;
            close();$focus.trigger('focus');
        }
    });
    $(window).on('resize.blueActionMenu scroll.blueActionMenu',close);
    $(document).on('scroll.blueActionMenu','.table-responsive,.dataTables_scrollBody',close);
    $(document).on('ajax:panel-updated.blueActionMenu',close);
})(window.jQuery);
