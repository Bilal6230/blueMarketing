{{-- Shared CR/DR/BD/BW Excel-style column filter popover. One instance per voucher page. --}}
<div id="dv-excel-popover" class="dv-excel-popover d-none" role="dialog" aria-label="Column sorting and filtering">
    <div class="dv-excel-title" id="dv-excel-title"></div>
    <button type="button" class="dv-excel-sort" data-direction="asc"><i class="fas fa-sort-amount-up mr-2" aria-hidden="true"></i>Sort A to Z / Small to Large</button>
    <button type="button" class="dv-excel-sort" data-direction="desc"><i class="fas fa-sort-amount-down mr-2" aria-hidden="true"></i>Sort Z to A / Large to Small</button>
    <div class="dv-excel-divider"></div>
    <div id="dv-excel-dates" class="d-none">
        <label class="dv-excel-date-label" for="dv-column-date-from">From Date</label>
        <input type="date" id="dv-column-date-from" class="form-control form-control-sm">
        <label class="dv-excel-date-label mt-2" for="dv-column-date-to">To Date</label>
        <input type="date" id="dv-column-date-to" class="form-control form-control-sm">
    </div>
    <div id="dv-excel-search-area">
        <label for="dv-excel-search" class="sr-only">Search column</label>
        <input id="dv-excel-search" type="search" class="form-control form-control-sm" placeholder="Search values…" autocomplete="off">
    </div>
    <div class="dv-excel-values" id="dv-excel-values" role="group" aria-label="Filter choices"></div>
    <div class="dv-excel-footer">
        <button type="button" class="btn btn-sm btn-light" id="dv-excel-clear">Clear</button>
        <button type="button" class="btn btn-sm btn-light" id="dv-excel-cancel">Cancel</button>
        <button type="button" class="btn btn-sm btn-primary" id="dv-excel-apply">Apply</button>
    </div>
</div>
