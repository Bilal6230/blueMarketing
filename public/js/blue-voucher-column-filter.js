/* Shared Excel-style voucher column filters: server-side query adapter and
 * client-side row adapter. All UI interaction uses jQuery. */
(function($){
 'use strict';
 if(!$||$.blueVoucherColumnFilters)return;
 $.blueVoucherColumnFilters={
    init:function(cfg){
    const initialOptions=cfg.initialOptions||{};
    const excelSelections={},excelExclusions={},liveColumnSearch={};
    const dateColumn={from:'',to:''};
    const accountRelations=initialOptions.account_relations||[];
    const $excel=$(cfg.popoverSelector||'#dv-excel-popover');
    let excelCurrent=null,excelOptions=[],excelDraft=new Set(),filterInputTimer=null;
    const searchOnly=cfg.searchOnly||['number','detail','amount'];
    const checkboxes=cfg.checkboxes||['head','subhead','status','approved_by'];
    const staticChoices={head:initialOptions.head||[],subhead:initialOptions.subhead||[],
        approved_by:initialOptions.approved_by||[],status:['Approved','Pending','Posted','Rejected','Inactive']};
    function modeFor(field){return field==='date'?'date':searchOnly.includes(field)?'search':'checkbox';}
    function activeFor(field){
        return Boolean(excelSelections[field]||excelExclusions[field]||liveColumnSearch[field]||
            (field==='date'&&(dateColumn.from||dateColumn.to)));
    }
    function updateSortArrows(){
        $(cfg.triggerSelector||'.dv-excel-trigger').each(function(){
            const $button=$(this),field=$button.data('column');
            $button.toggleClass('dv-has-filter',activeFor(field));
            $button.closest('th').attr('aria-sort',cfg.getSortColumn()===field?
                (cfg.getSortDirection()==='asc'?'ascending':'descending'):'none');
        });
    }
    function closeExcel(){
        $excel.addClass('d-none').removeClass('dv-search-mode dv-date-mode dv-checkbox-mode');
        $(cfg.triggerSelector||'.dv-excel-trigger').attr('aria-expanded','false');
        excelCurrent=null;
    }
    function availableChoices(field){
        if(field==='head'||field==='subhead'){
            const opposite=field==='head'?'subhead':'head';
            const hasInclude=Object.prototype.hasOwnProperty.call(excelSelections,opposite);
            const excludes=excelExclusions[opposite]||[];
            const selected=excelSelections[opposite]||[];
            const permitted=accountRelations.filter(pair=>
                (!hasInclude||selected.includes(pair[opposite]))&&!excludes.includes(pair[opposite]));
            const matched=new Set(permitted.map(pair=>pair[field]));
            const existing=excelSelections[field]||[];
            return [...new Set([...staticChoices[field].filter(v=>matched.has(v)),...existing])]
                .sort((x,y)=>String(x).localeCompare(String(y),undefined,{numeric:true}));
        }
        return cfg.choicesFor ? cfg.choicesFor(field, staticChoices[field]||[]) : (staticChoices[field]||[]);
    }
    function renderExcelValues(){
        const match=$.trim($('#dv-excel-search').val()).toLocaleLowerCase();
        const shown=excelOptions.filter(value=>String(value).toLocaleLowerCase().includes(match));
        const $values=$('#dv-excel-values').empty();
        $values.append($('<label>',{'class':'dv-excel-choice'})
            .append($('<input>',{type:'checkbox','class':'dv-check-all',
                checked:shown.length>0&&shown.every(v=>excelDraft.has(String(v)))}))
            .append($('<span>',{text:'(Select All Results)'})));
        shown.forEach(value=>{
            const str=String(value);
            $values.append($('<label>',{'class':'dv-excel-choice'})
                .append($('<input>',{type:'checkbox','class':'dv-excel-item',
                    value:str,checked:excelDraft.has(str)}))
                .append($('<span>',{text:str||'(Blank / Not Approved)'})));
        });
        if(!shown.length)$values.append($('<div>',{'class':'text-muted p-2',text:'No matching values'}));
    }
    function queryParams(){
        const query={};
        $.each($(cfg.formSelector).serializeArray(),(_,item)=>{query[item.name]=item.value;});
        query.start=cfg.getStart();query.length=cfg.getLength();query.sort_column=cfg.getSortColumn();query.sort_direction=cfg.getSortDirection();
        if($.trim($(cfg.quickSearchSelector).val()))query.search=$.trim($(cfg.quickSearchSelector).val());
        $.each(liveColumnSearch,(field,value)=>{if(value)query['column_'+field]=value;});
        if(dateColumn.from)query.column_date_from=dateColumn.from;
        if(dateColumn.to)query.column_date_to=dateColumn.to;
        $.each(excelSelections,(field,values)=>{
            $.each(values,(_,value)=>{(query['column_values['+field+']']??=[]).push(value);});
        });
        $.each(excelExclusions,(field,values)=>{
            $.each(values,(_,value)=>{(query['column_excluded['+field+']']??=[]).push(value);});
        });
        return query;
    }
    function applyLiveColumn(){
        clearTimeout(filterInputTimer);
        filterInputTimer=setTimeout(()=>{updateSortArrows();cfg.onChange();},300);
    }
    $(cfg.triggerSelector||'.dv-excel-trigger').on('click',function(event){
        event.stopPropagation();
        const $button=$(this),field=$button.data('column');
        if(excelCurrent===field){closeExcel();return;}
        closeExcel();excelCurrent=field;
        const mode=modeFor(field);
        $excel.addClass('dv-'+mode+'-mode').removeClass('d-none');
        $(cfg.triggerSelector||'.dv-excel-trigger').attr('aria-expanded','false');$button.attr('aria-expanded','true');
        $('#dv-excel-title').text($button.closest('th').find('span:first').text());
        $('#dv-excel-search').val(mode==='search'?(liveColumnSearch[field]||''):'');
        $('#dv-column-date-from').val(dateColumn.from);
        $('#dv-column-date-to').val(dateColumn.to);
        if(mode==='checkbox'){
            excelOptions=availableChoices(field).map(String);
            excelDraft=new Set(excelSelections[field]||[]);
            if(Object.prototype.hasOwnProperty.call(excelExclusions,field))
                excelDraft=new Set(excelOptions.filter(v=>!excelExclusions[field].includes(v)));
            else if(!Object.prototype.hasOwnProperty.call(excelSelections,field))
                excelOptions.forEach(v=>excelDraft.add(v));
            renderExcelValues();
        }
        const pos=$button.offset(),menuHeight=mode==='checkbox'?355:mode==='date'?250:180;
        $excel.css({left:Math.max(8,Math.min(pos.left-$(window).scrollLeft(),$(window).width()-300))+'px',
            top:Math.max(8,Math.min(pos.top-$(window).scrollTop()+$button.outerHeight(),
                $(window).height()-menuHeight))+'px'});
        if(mode==='search')$('#dv-excel-search').trigger('focus');
    });
    $excel.on('change','.dv-excel-item',function(){
        if($(this).is(':checked'))excelDraft.add($(this).val());
        else excelDraft.delete($(this).val());
        renderExcelValues();
    }).on('change','.dv-check-all',function(){
        const checked=$(this).is(':checked'),match=$.trim($('#dv-excel-search').val()).toLocaleLowerCase();
        excelOptions.filter(v=>String(v).toLocaleLowerCase().includes(match)).forEach(v=>{
            if(checked)excelDraft.add(String(v));else excelDraft.delete(String(v));
        });
        renderExcelValues();
    });
    $('#dv-excel-search').on('input',function(){
        if(!excelCurrent)return;
        if(modeFor(excelCurrent)==='search'){
            liveColumnSearch[excelCurrent]=$.trim($(this).val());
            applyLiveColumn();
        }else renderExcelValues();
    });
    $('#dv-column-date-from,#dv-column-date-to').on('change',function(){
        const from=$('#dv-column-date-from').val(),to=$('#dv-column-date-to').val();
        if(from&&to&&from>to){
            $(this).addClass('is-invalid');return;
        }
        $('#dv-column-date-from,#dv-column-date-to').removeClass('is-invalid');
        dateColumn.from=from;dateColumn.to=to;applyLiveColumn();
    });
    $('#dv-excel-apply').on('click',()=>{
        if(!excelCurrent||modeFor(excelCurrent)!=='checkbox')return;
        if(!excelDraft.size&&excelOptions.length){
            $('#dv-excel-values').prepend($('<p>',{'class':'text-danger mb-1',
                text:'Choose a value, or use Clear.'}));return;
        }
        const removed=excelOptions.filter(v=>!excelDraft.has(String(v)));
        if(!removed.length){
            delete excelSelections[excelCurrent];delete excelExclusions[excelCurrent];
        }else if(removed.length<excelDraft.size&&removed.length<=250){
            delete excelSelections[excelCurrent];excelExclusions[excelCurrent]=removed;
        }else{
            if(excelDraft.size>250){
                $('#dv-excel-values').prepend($('<p>',{'class':'text-danger mb-1',
                    text:'Choose fewer values or narrow account selections.'}));return;
            }
            delete excelExclusions[excelCurrent];excelSelections[excelCurrent]=[...excelDraft];
        }
        closeExcel();updateSortArrows();cfg.onChange();
    });
    $('#dv-excel-clear').on('click',()=>{
        if(!excelCurrent)return;
        if(excelCurrent==='date'){dateColumn.from='';dateColumn.to='';}
        else if(modeFor(excelCurrent)==='search'){delete liveColumnSearch[excelCurrent];}
        else{delete excelSelections[excelCurrent];delete excelExclusions[excelCurrent];}
        closeExcel();updateSortArrows();cfg.onChange();
    });
    $('#dv-excel-cancel').on('click',closeExcel);
    $excel.on('click','.dv-excel-sort',function(){
        if(!excelCurrent)return;
        cfg.onSort(excelCurrent,$(this).data('direction'));
        closeExcel();updateSortArrows();cfg.onChange();
    });
    $(document).on('pointerdown.dvExcel',function(event){
        if(!$(event.target).closest('#dv-excel-popover,.dv-excel-trigger').length)closeExcel();
    }).on('keydown.dvExcel',function(event){if(event.key==='Escape')closeExcel();});
    $(cfg.tableWrapSelector).on('scroll',()=>{if(excelCurrent)closeExcel();});
    updateSortArrows();

    
    function reset(){
        $.each(excelSelections,(field)=>{delete excelSelections[field];});
        $.each(excelExclusions,(field)=>{delete excelExclusions[field];});
        $.each(liveColumnSearch,(field)=>{delete liveColumnSearch[field];});
        dateColumn.from='';dateColumn.to='';
        closeExcel();updateSortArrows();
    }
    function filterRows(input, getValue){
        const values = input.filter(row=>{
            for(const [field,selected] of Object.entries(excelSelections)){
                if(!selected.includes(String(getValue(row,field)??'')))return false;
            }
            for(const [field,excluded] of Object.entries(excelExclusions)){
                if(excluded.includes(String(getValue(row,field)??'')))return false;
            }
            for(const [field,search] of Object.entries(liveColumnSearch)){
                if(search && !String(getValue(row,field)??'').toLocaleLowerCase().includes(search.toLocaleLowerCase()))return false;
            }
            const date=String(getValue(row,'date')??'').slice(0,10);
            return (!dateColumn.from||date>=dateColumn.from)&&(!dateColumn.to||date<=dateColumn.to);
        });
        const field=cfg.getSortColumn(),direction=cfg.getSortDirection()==='asc'?1:-1;
        return values.sort((a,b)=>{
            const left=String(getValue(a,field)??''),right=String(getValue(b,field)??'');
            return direction*left.localeCompare(right,undefined,{numeric:true,sensitivity:'base'});
        });
    }
    return {queryParams,reset,close:closeExcel,updateSortArrows,filterRows};

    }
 };
})(window.jQuery);
