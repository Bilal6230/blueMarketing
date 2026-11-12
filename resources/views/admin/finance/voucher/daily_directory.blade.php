@extends('admin.layouts.master')
@section('title', $presetType ? $presetType.' Vouchers' : 'Voucher Directory')

@section('content')
<style>
    html.daily-voucher-directory-active,body.daily-voucher-directory-active{height:100%;overflow:hidden!important}
    body.daily-voucher-directory-active .main-footer{display:none!important}
    .main-content.page-content:has(> .daily-voucher-page){height:auto!important;min-height:0!important;overflow:visible!important;padding-bottom:0!important}
    .content-wrapper.daily-voucher-page{height:var(--dv-viewport-height,calc(100dvh - 110px))!important;max-height:var(--dv-viewport-height,calc(100dvh - 110px))!important;min-height:0!important;overflow:hidden!important;display:flex;flex-direction:column;padding:.3rem .65rem!important}
    .daily-voucher-page .content-header{flex:0 0 auto;padding:.1rem 0 .25rem}
    .daily-voucher-page .content-header h1{font-size:1.4rem;margin-bottom:.1rem!important}
    .daily-voucher-page .daily-voucher-card.mb-3{margin-bottom:.55rem!important}
    .daily-voucher-page .daily-voucher-card>.card-body{padding:.65rem .85rem}
    .daily-voucher-page .daily-voucher-filters label{margin-bottom:.2rem;font-size:.83rem}
    .daily-voucher-page .daily-voucher-filters .form-control{height:34px;padding:.25rem .55rem}
    .daily-voucher-page>.content{flex:1 1 auto;min-height:0;overflow:hidden;padding:0}
    .daily-voucher-page>.content>.container-fluid{height:100%;min-height:0;display:flex;flex-direction:column;padding:0}
    .daily-voucher-card{border:0;border-radius:14px;box-shadow:0 8px 24px rgba(16,36,64,.08);overflow:hidden}
    /* Applied only to the selected voucher directory, never to the global sidebar. */
    .daily-voucher-page{--dv-accent:#2463eb;--dv-pale:#eff5ff}
    .daily-voucher-page.dv-theme-credit{--dv-accent:#128044;--dv-pale:#e9f8ef}
    .daily-voucher-page.dv-theme-debit{--dv-accent:#bd3140;--dv-pale:#fff0f1}
    .daily-voucher-page .content-header h1{color:var(--dv-accent);font-weight:650}
    .daily-voucher-page .daily-voucher-card{border-top:3px solid var(--dv-accent)}
    .daily-voucher-page .daily-voucher-table thead tr:first-child th{background:var(--dv-pale);border-bottom:2px solid var(--dv-accent)}
    .daily-voucher-page .daily-voucher-actions .btn-primary,.daily-voucher-page .daily-voucher-actions .btn-success{background:var(--dv-accent);border-color:var(--dv-accent);color:#fff}
    .daily-voucher-page .daily-voucher-actions .btn:hover{filter:brightness(.93)}
    .daily-voucher-page .daily-voucher-table td:nth-child(3){color:var(--dv-accent)}
    .daily-voucher-page.dv-theme-credit,.daily-voucher-page.dv-theme-debit{background:var(--dv-pale)!important}
    .daily-voucher-page .daily-voucher-card{background:color-mix(in srgb,var(--dv-pale) 18%,white)}
    .daily-voucher-page #dv-type{background-color:var(--dv-pale);border:1px solid var(--dv-accent);color:var(--dv-accent);font-weight:600}
    .daily-voucher-page .dv-column-filters input:focus{border-color:var(--dv-accent);box-shadow:0 0 0 .15rem color-mix(in srgb,var(--dv-accent) 15%,transparent)}
    .daily-voucher-page .dv-page-btn{border:1px solid var(--dv-accent);background:#fff;color:var(--dv-accent);font-weight:500;border-radius:5px;min-width:31px;height:30px;padding:3px 7px}
    .daily-voucher-page .dv-page-btn.active{background:var(--dv-accent);color:#fff}
    .daily-voucher-page .dv-page-btn:disabled{opacity:.4;cursor:not-allowed}
    .daily-voucher-page .dv-page-ellipsis{padding:0 .2rem;color:#6f7c8a}
    .daily-voucher-page #dv-pages{display:flex;align-items:center;flex-wrap:wrap;gap:4px}
    .daily-voucher-page .dv-pagination-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
    .daily-voucher-page .dv-total-records{font-size:.81rem;color:#596476;font-weight:500}
    #dv-entry-loading{position:absolute;inset:0;z-index:2;display:flex;align-items:center;justify-content:center;gap:.75rem;background:#fff;color:#48556b}
    #dv-entry-loading.d-none{display:none!important}
    #dv-entry-loading .spinner-border{width:1.4rem;height:1.4rem;border-width:.18em}
    .daily-voucher-filters{display:grid;grid-template-columns:repeat(5,minmax(110px,1fr)) auto;gap:.6rem;align-items:end}
    .daily-voucher-filters.dv-preset{grid-template-columns:repeat(4,minmax(130px,1fr)) auto}
    .dv-common-print-heading{display:none}
    .dv-common-print-button{white-space:nowrap}
    @media print{
      body.dv-common-print-active *{visibility:hidden!important}
      body.dv-common-print-active #dv-detail-modal,
      body.dv-common-print-active #dv-detail-modal .modal-dialog,
      body.dv-common-print-active #dv-detail-modal .modal-content,
      body.dv-common-print-active #dv-detail-modal .modal-body,
      body.dv-common-print-active #dv-detail-modal .modal-body *{visibility:visible!important}
      body.dv-common-print-active #dv-detail-modal{position:absolute!important;inset:0 auto auto 0!important;display:block!important;width:100%!important;max-height:none!important;overflow:visible!important;background:white!important}
      body.dv-common-print-active #dv-detail-modal .modal-dialog{margin:0!important;max-width:none!important;width:100%!important;transform:none!important}
      body.dv-common-print-active #dv-detail-modal .modal-content{border:0!important;box-shadow:none!important}
      body.dv-common-print-active #dv-detail-modal .modal-header,
      body.dv-common-print-active #dv-detail-modal .modal-footer{display:none!important}
      body.dv-common-print-active .dv-common-print-heading{display:block!important;text-align:center;margin-bottom:18px;border-bottom:2px solid #333;padding-bottom:12px}
      body.dv-common-print-active #dv-detail-prints-wrap{display:none!important}
      body.dv-common-print-active #dv-detail-modal .modal-body{overflow:visible!important;max-height:none!important;padding:16px!important}
      body.dv-common-print-active #dv-detail-modal .modal-body dt,
      body.dv-common-print-active #dv-detail-modal .modal-body dd{break-inside:avoid}
      @page{size:A4 portrait;margin:15mm}
    }
    .daily-voucher-actions{display:flex;gap:.4rem;align-items:center;white-space:nowrap}
    .daily-voucher-shortcuts{display:flex;flex-wrap:wrap;gap:.5rem}
    .daily-voucher-table-card{flex:1 1 0;min-height:0;display:flex;flex-direction:column;margin-bottom:0!important}
    .dv-table-tools{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:.35rem .65rem;background:#fff}
    .daily-voucher-page .dv-pagination-footer{flex:0 0 auto;position:relative;z-index:4;min-height:40px;padding:.35rem .65rem;background:#fff}
    .daily-voucher-page .dv-table-tools{flex:0 0 auto}
    .dv-table-tools label{display:flex;align-items:center;gap:7px;margin:0;font-size:.88rem}
    .dv-table-tools select{width:72px}
    .dv-table-tools input{width:220px;max-width:45vw}
    .daily-voucher-table th.dv-sortable{cursor:pointer;user-select:none}
    .daily-voucher-table th.dv-sortable:hover{color:var(--dv-accent)}
    .dv-sort-indicator{margin-left:5px;color:var(--dv-accent);font-size:.75em}
    .dv-sortable:focus-visible{outline:2px solid var(--dv-accent);outline-offset:-2px}
    .daily-voucher-table-wrap{flex:1 1 0;min-height:0;overflow:auto}.daily-voucher-table{min-width:1100px;table-layout:fixed}
    /* Fixed column tracks prevent spare table width from enlarging Actions. Details absorbs the rest. */
    .daily-voucher-table{width:100%}
    .daily-voucher-table col.dv-col-actions{width:72px}
    .daily-voucher-table th:last-child,.daily-voucher-table td:last-child{width:72px;max-width:72px}
    .daily-voucher-table td:last-child{padding:.25rem .3rem;white-space:nowrap;text-align:center}
    .daily-voucher-table thead th{position:sticky;top:0;z-index:2;background:#edf4fc}.daily-voucher-table td{vertical-align:middle}
    .daily-voucher-table thead .dv-column-filters th{top:38px;background:#fff;z-index:3;padding:.3rem}
    .daily-voucher-table thead .dv-column-filters input{min-width:72px;font-size:.75rem;padding:.25rem .4rem;height:30px}
    .daily-voucher-muted{color:#70819a;font-size:.85rem}
    .daily-voucher-loading #dv-rows{pointer-events:none}
    .dv-skeleton-bar{height:12px;border-radius:5px;background:linear-gradient(90deg,#e7ecf1 22%,#f5f7fa 42%,#e7ecf1 67%);background-size:220% 100%;animation:dv-skeleton-pulse 1.3s ease-in-out infinite}
    .daily-voucher-page.dv-theme-credit .dv-skeleton-bar{background-image:linear-gradient(90deg,#daeee2 22%,#f3fbf6 42%,#daeee2 67%)}
    .daily-voucher-page.dv-theme-debit .dv-skeleton-bar{background-image:linear-gradient(90deg,#f2dce0 22%,#fff5f6 42%,#f2dce0 67%)}
    .daily-voucher-table tbody tr.dv-skeleton-row td{height:44px;vertical-align:middle;padding:.65rem .45rem}
    /* CCR/CDR: full-height card with its OWN scroll viewport, not a shrinking card. */
    .daily-voucher-page.dv-cash-only .daily-voucher-table-card{
        flex:1 1 0!important;min-height:0!important;max-height:100%!important;
        display:flex!important;flex-direction:column;overflow:hidden
    }
    .daily-voucher-page.dv-cash-only .dv-table-tools,
    .daily-voucher-page.dv-cash-only .dv-pagination-footer{flex:0 0 auto}
    .daily-voucher-page.dv-cash-only .daily-voucher-table-wrap{
        flex:1 1 0!important;min-height:0!important;max-height:none!important;
        overflow-y:auto!important;overflow-x:auto!important;overscroll-behavior:contain;
        scrollbar-gutter:stable
    }
    .daily-voucher-page.dv-cash-only .daily-voucher-table{
        border-collapse:separate;border-spacing:0;
        font-size:var(--bm-font-md);line-height:1.45
    }
    .daily-voucher-page.dv-cash-only .daily-voucher-table thead tr:first-child th{
        position:sticky;top:0;z-index:5;vertical-align:middle;
        font-size:var(--bm-font-sm);font-weight:700;letter-spacing:.01em;
        padding:.65rem .55rem;box-shadow:0 1px 0 var(--dv-accent)
    }
    .daily-voucher-page.dv-cash-only .daily-voucher-table thead .dv-column-filters th{
        position:sticky;top:40px;z-index:5;
        padding:.4rem .45rem;background:#fff;box-shadow:0 1px 0 #e0e8ec
    }
    .daily-voucher-page.dv-cash-only .daily-voucher-table thead .dv-column-filters input{
        height:31px;min-width:0;width:100%;border-radius:5px;
        border:1px solid #d4dee5;font-size:var(--bm-font-sm)
    }
    .daily-voucher-page.dv-cash-only .daily-voucher-table tbody td{
        height:51px;padding:.65rem .55rem;vertical-align:middle;
        font-size:var(--bm-font-md);color:#344354;background:#fff;
        border-bottom:1px solid #e5ebf0
    }
    .daily-voucher-page.dv-cash-only .daily-voucher-table tbody tr:nth-child(even) td{
        background:color-mix(in srgb,var(--dv-pale) 18%,white)
    }
    .daily-voucher-page.dv-cash-only .daily-voucher-table tbody tr:hover td{
        background:color-mix(in srgb,var(--dv-pale) 58%,white)
    }
    .daily-voucher-page.dv-cash-only .daily-voucher-table tbody td:nth-child(3){
        color:var(--dv-accent);font-weight:750
    }
    .daily-voucher-page.dv-cash-only .daily-voucher-table tbody td:nth-child(4),
    .daily-voucher-page.dv-cash-only .daily-voucher-table tbody td:nth-child(5){
        overflow-wrap:anywhere
    }
    .daily-voucher-page.dv-cash-only .daily-voucher-table tbody td:nth-child(7){
        font-weight:650;font-variant-numeric:tabular-nums;text-align:right
    }
    .daily-voucher-page.dv-cash-only .daily-voucher-table tbody td:last-child{
        padding:.45rem .35rem;white-space:nowrap
    }
    .daily-voucher-page.dv-cash-only .daily-voucher-table tbody td:last-child .btn{
        padding:.28rem .4rem;border-radius:4px
    }
    .daily-voucher-page.dv-cash-only .dv-pagination-footer{
        border-top:1px solid #dce6eb;padding:.5rem .75rem;background:#fff;
        box-shadow:0 -2px 8px rgba(31,57,79,.03)
    }

    @keyframes dv-skeleton-pulse{0%{background-position:100% 0}100%{background-position:-100% 0}}
    @media(prefers-reduced-motion:reduce){.dv-skeleton-bar{animation:none}}
    @media(max-width:1200px){.daily-voucher-filters{grid-template-columns:repeat(3,minmax(115px,1fr))}.daily-voucher-actions{grid-column:1/-1;justify-content:flex-end}}
    @media(max-width:900px){.daily-voucher-filters{grid-template-columns:repeat(2,minmax(130px,1fr))}.daily-voucher-actions{grid-column:1/-1;justify-content:flex-end}}
    @media(max-width:520px){.daily-voucher-filters{grid-template-columns:1fr}.daily-voucher-page .daily-voucher-actions{grid-column:1;justify-content:flex-end}.content-wrapper.daily-voucher-page{padding:.35rem!important}}

    /* Excel-like menu belongs to the viewport, never clipped by scrollable table. */
    .daily-voucher-page .daily-voucher-table{border-collapse:separate;border-spacing:0}
    .daily-voucher-page .daily-voucher-table th,
    .daily-voucher-page .daily-voucher-table td{border-right:1px solid #dce3eb!important;border-bottom:1px solid #dce3eb!important}
    .daily-voucher-page .daily-voucher-table th:first-child,
    .daily-voucher-page .daily-voucher-table td:first-child{border-left:1px solid #dce3eb!important}
    .daily-voucher-page .daily-voucher-table thead th{border-top:1px solid #dce3eb!important}
    .daily-voucher-page .daily-voucher-table tbody tr:nth-child(even) td{background:color-mix(in srgb,var(--dv-pale) 19%,white)}
    .daily-voucher-page .daily-voucher-table tbody tr:hover td{background:color-mix(in srgb,var(--dv-pale) 51%,white)}
    .daily-voucher-page .daily-voucher-table tbody td{height:42px;padding:.48rem .48rem}
    .daily-voucher-page .dv-filter-heading{vertical-align:middle;white-space:nowrap}
    .daily-voucher-page .dv-excel-trigger{float:right;min-width:25px;height:24px;border:1px solid transparent;border-radius:4px;color:#607086;background:transparent}
    .daily-voucher-page .dv-excel-trigger:hover,.daily-voucher-page .dv-excel-trigger[aria-expanded=true]{border-color:var(--dv-accent);background:#fff;color:var(--dv-accent)}
    .daily-voucher-page .dv-excel-trigger.dv-has-filter{color:var(--dv-accent);background:#fff;border-color:var(--dv-accent)}
    .dv-excel-popover{position:fixed;z-index:1100;width:288px;max-width:calc(100vw - 20px);background:white;border:1px solid #c9d3de;border-radius:8px;box-shadow:0 14px 38px rgba(13,34,56,.22);padding:10px;color:#273549;font-size:var(--bm-font-sm)}
    .dv-excel-popover.d-none{display:none!important}
    .dv-excel-title{font-weight:700;font-size:var(--bm-font-md);margin:0 0 7px}
    .dv-excel-sort{width:100%;display:block;border:0;background:white;text-align:left;padding:8px;border-radius:4px;color:#273549}
    .dv-excel-sort:hover,.dv-excel-sort:focus-visible{background:#ecf4fc}
    .dv-excel-divider{border-top:1px solid #e1e7ed;margin:8px 0}
    .dv-excel-values{height:190px;overflow:auto;border:1px solid #dde4ec;border-radius:5px;margin-top:7px;padding:5px}
    .dv-excel-choice{display:flex;align-items:center;gap:7px;margin:0;padding:5px 3px;cursor:pointer;overflow-wrap:anywhere}
    .dv-excel-choice:hover{background:#f1f6fc}
    .dv-excel-choice input{flex:0 0 auto;margin:0}
    .dv-excel-footer{display:flex;gap:6px;justify-content:flex-end;margin-top:10px}
    .dv-excel-footer .btn-primary{background:#2165b4;border-color:#2165b4}
    .dv-excel-date-label{display:block;font-size:var(--bm-font-sm);font-weight:600;margin:8px 0 4px}
    .dv-excel-popover.dv-search-mode .dv-excel-values,
    .dv-excel-popover.dv-search-mode .dv-excel-footer,
    .dv-excel-popover.dv-date-mode .dv-excel-values,
    .dv-excel-popover.dv-date-mode #dv-excel-search-area,
    .dv-excel-popover.dv-date-mode #dv-excel-apply,
    .dv-excel-popover.dv-date-mode #dv-excel-cancel{display:none!important}
    .dv-excel-popover.dv-search-mode #dv-excel-apply,
    .dv-excel-popover.dv-search-mode #dv-excel-cancel{display:none!important}
    .dv-excel-popover.dv-date-mode .dv-excel-footer{display:flex!important}
    .dv-excel-popover.dv-date-mode #dv-excel-clear{display:inline-block!important}
    .dv-excel-popover.dv-date-mode #dv-excel-dates{display:block!important}
    .dv-excel-popover.dv-search-mode #dv-excel-dates,
    .dv-excel-popover.dv-checkbox-mode #dv-excel-dates{display:none!important}
</style>
<div class="content-wrapper bm-ui bm-voucher-directory daily-voucher-page {{ in_array($presetType, ['CCR','CDR'], true) ? 'dv-cash-only' : '' }} {{ in_array($presetType, ['CR','CCR','BOR'], true) ? 'dv-theme-credit' : (in_array($presetType, ['DR','CDR','BOP','CP'], true) ? 'dv-theme-debit' : '') }}" id="dv-page">
    <section class="content-header"><div class="container-fluid d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h3 mb-1">
                @if($presetType)
                    {{ ['BOR'=>'Bank Receipt Voucher (BOR)','BOP'=>'Bank Payment Voucher (BOP)'][$presetType] ?? $presetType.' Vouchers' }}
                @else
                    Voucher Directory
                @endif
            </h1>
            <div class="daily-voucher-muted">Selected project records · Read-only history</div>
        </div>
        <div class="d-flex flex-wrap" style="gap:.5rem">
            @if(\App\Http\Controllers\Finance\VoucherController::canAccessFinanceApprovalsForUser(auth()->user()))
                <a class="btn btn-outline-primary" href="{{ route('finance.daily-vouchers.approvals.index') }}">Approvals</a>
            @endif
            <a class="btn btn-outline-secondary" href="{{ route('dashboard') }}">Dashboard</a>
        </div>
    </div></section>
    <section class="content"><div class="container-fluid">
        <x-finance.voucher-filter-card class="daily-voucher-card mb-3">
            <form id="daily-voucher-filters" class="bm-filter daily-voucher-filters {{ $presetType ? 'dv-preset' : '' }}">
                <div><label for="dv-from">From</label><input id="dv-from" name="from" type="date" class="form-control"></div>
                <div><label for="dv-to">To</label><input id="dv-to" name="to" type="date" class="form-control"></div>
                @if($presetType)
                <input id="dv-type" name="type" type="hidden" value="{{ $presetType }}">
                @else
                <div><label for="dv-type">Voucher Type</label>
                  <select id="dv-type" name="type" class="form-control">
                    <option value="">All voucher types</option>
                    @foreach(['CR','DR','CCR','CDR','BOR','BOP','CP','PPR','JV','CV','BR','BO','BW','BD','PR','PP'] as $type)
                      <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                  </select>
                </div>
                @endif
                <div><label for="dv-account">Account</label><select id="dv-account" name="account" class="form-control"><option value="">All accounts</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->head_name }}{{ $account->sub_name ? ' · '.$account->sub_name : '' }}</option>@endforeach</select></div>
                <div><label for="dv-search">Search</label><input id="dv-search" name="search" class="form-control" maxlength="100" placeholder="Number, reference, account or detail"></div>
                <div class="daily-voucher-actions">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-filter mr-1"></i> Apply</button>
                    <button class="btn btn-outline-secondary" type="button" id="dv-reset-filters">Reset</button>
                    <button class="btn btn-success" type="button" id="dv-create-voucher"><i class="fas fa-plus mr-1"></i> New voucher</button>
                </div>
            </form>
            <div class="d-none" aria-hidden="true">
                @foreach([
                    'CR' => ['Credit Voucher (CR)', route('finance.voucher.in', ['series'=>'CR','embed'=>1])],
                    'DR' => ['Debit Voucher (DR)', route('finance.voucher.out', ['series'=>'DR','embed'=>1])],
                    'CCR' => ['Cash Credit (CCR)', route('finance.voucher.in.method', ['method'=>'cash','series'=>'CCR','embed'=>1])],
                    'CDR' => ['Cash Debit (CDR)', route('finance.voucher.out.method', ['method'=>'cash','series'=>'CDR','embed'=>1])],
                    'BOR' => ['Bank Receipt Voucher (BOR)', route('finance.voucher.in', ['series'=>'BOR','embed'=>1])],
                    'BOP' => ['Bank Payment Voucher (BOP)', route('finance.voucher.out', ['series'=>'BOP','embed'=>1])],
                ] as $series => [$label, $url])
                    <button type="button" class="dv-create" data-url="{{$url}}" data-cheque-url="{{ in_array($series, ['BOR','BOP'], true) ? route($series === 'BOR' ? 'finance.voucher.in.method' : 'finance.voucher.out.method', ['method'=>'cheque','series'=>$series,'embed'=>1]) : '' }}" data-title="{{$label}}" data-series="{{$series}}"></button>
                @endforeach
            </div>
        </x-finance.voucher-filter-card>
        <x-finance.voucher-table-card class="daily-voucher-card daily-voucher-table-card">
            <div class="dv-table-tools bm-records-toolbar">
                <label for="dv-page-length">Show <select class="form-control form-control-sm" id="dv-page-length"><option value="10">10</option><option value="25" selected>25</option><option value="50">50</option><option value="100">100</option></select> entries</label>
                <label for="dv-table-search">Search: <input id="dv-table-search" class="form-control form-control-sm" type="search" placeholder="Search vouchers" autocomplete="off"></label>
            </div>
            <div class="daily-voucher-table-wrap" id="dv-table-wrap"><table class="table bm-table table-sm table-hover mb-0 daily-voucher-table">
                <colgroup><col style="width:48px"><col style="width:100px"><col style="width:125px"><col style="width:160px"><col style="width:180px"><col><col style="width:115px"><col style="width:90px">@if(\App\Services\Finance\VoucherApproverAccess::isSuperAdmin(auth()->user()))<col style="width:140px">@endif<col class="dv-col-actions"></colgroup>
                <thead>
                <tr>
                    <th>#</th>
                    @foreach(['date'=>'Date','number'=>'Voucher #','head'=>'Head','subhead'=>'Sub Head','detail'=>'Details','amount'=>'Amount','status'=>'Status'] as $field=>$label)
                    <th class="{{ $field==='amount' ? 'text-right' : '' }} dv-filter-heading" data-field="{{ $field }}">
                        <span>{{ $label }}</span>
                        <button type="button" class="dv-excel-trigger" data-column="{{ $field }}"
                            aria-label="Sort or filter {{ $label }}" aria-expanded="false"
                            aria-haspopup="dialog" title="Sort and filter {{ $label }}"><i class="fas fa-caret-down" aria-hidden="true"></i></button>
                    </th>
                    @endforeach
                    @if(\App\Services\Finance\VoucherApproverAccess::isSuperAdmin(auth()->user()))
                    <th class="dv-filter-heading"><span>Approved By</span>
                    <button type="button" class="dv-excel-trigger" data-column="approved_by"
                        aria-label="Sort or filter Approved By" aria-expanded="false"
                        aria-haspopup="dialog" title="Sort and filter Approved By"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
                    @endif
                    <th>Actions</th>
                </tr>
            </thead><tbody id="dv-rows"></tbody></table></div>
            <div class="card-footer dv-pagination-footer">
                <div id="dv-record-summary" class="dv-total-records" aria-live="polite">Loading records...</div>
                <nav id="dv-pages" aria-label="Voucher pagination"></nav>
            </div>
        </x-finance.voucher-table-card>
    </div></section>
</div>
<x-finance.voucher-column-filters />
<div class="modal fade" id="dv-detail-modal" tabindex="-1" role="dialog" aria-labelledby="dv-detail-title" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable" role="document"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="dv-detail-title">Voucher details</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body"><div class="dv-common-print-heading"><h3 class="mb-1">Voucher Details</h3><div id="dv-print-identity" class="small text-muted"></div><div class="small">Read-only voucher record · not a new accounting posting</div></div><dl class="row mb-0" id="dv-detail-fields"></dl><div id="dv-detail-lines-wrap" class="mt-3 d-none"><h6>Voucher lines</h6><div class="table-responsive"><table class="table table-sm table-bordered"><thead><tr><th>Account</th><th>Description</th><th class="text-right">Debit</th><th class="text-right">Credit</th></tr></thead><tbody id="dv-detail-lines"></tbody></table></div></div><div id="dv-detail-prints-wrap" class="mt-3 d-none"><h6>Plot receipt print views</h6><div id="dv-detail-prints" class="d-flex flex-wrap" style="gap:.5rem"></div></div><div id="dv-detail-audit-wrap" class="mt-3 d-none"><h6>Payment and audit information</h6><dl class="row mb-0" id="dv-detail-audit"></dl></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-primary dv-common-print-button" id="dv-common-print"><i class="fas fa-print mr-1"></i> Print Details (A4)</button><a id="dv-detail-print" class="btn btn-primary" target="_blank" rel="noopener"><i class="fas fa-print mr-1"></i> Open print view</a><span id="dv-no-print" class="small text-muted d-none">No posted print record is available.</span><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button></div></div></div></div>
<div class="modal fade" id="dv-entry-modal" tabindex="-1" role="dialog" aria-labelledby="dv-entry-title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="dv-entry-title">New voucher</h5>
                <div id="dv-bank-method-wrap" class="ml-auto mr-3 d-none">
                    <label class="small mb-0 mr-2" for="dv-bank-method">Payment</label>
                    <select id="dv-bank-method" class="form-control form-control-sm d-inline-block" style="width:auto">
                        <option value="online">Online</option><option value="cheque">Cheque</option>
                    </select>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body p-0 position-relative">
                <div id="dv-entry-loading" class="d-none" role="status" aria-live="polite"><span class="spinner-border text-primary" aria-hidden="true"></span><span>Loading voucher form...</span></div>
                <div id="dv-entry-choose" class="p-4 d-none">
                    <label for="dv-entry-type" class="font-weight-bold mb-2">Choose voucher type</label>
                    <select id="dv-entry-type" class="form-control" aria-label="Choose a voucher to create">
                        <option value="">Select voucher type</option>
                        <option value="CR">Credit Voucher (CR)</option>
                        <option value="DR">Debit Voucher (DR)</option>
                        <option value="CCR">Cash Credit (CCR)</option>
                        <option value="CDR">Cash Debit (CDR)</option>
                        <option value="BOR">Bank Receipt Voucher (BOR)</option>
                        <option value="BOP">Bank Payment Voucher (BOP)</option>
                        <option value="JV">Journal Voucher (JV)</option>
                        <option value="CV">Commission Voucher (CV)</option>
                        <option value="PR">Plot Receive Payment (PR)</option>
                        <option value="PP">Plot Purchase Voucher (PP)</option>
                        <option value="BD">Bank Deposit (BD)</option>
                        <option value="BW">Bank Withdrawal (BW)</option>
                    </select>
                </div>
                <div id="dv-entry-error" class="alert alert-danger m-3 d-none" role="alert">
                    <span id="dv-entry-error-text"></span>
                    <a id="dv-entry-fallback" class="alert-link ml-2" target="_blank" rel="noopener">Open voucher form</a>
                </div>
                <iframe id="dv-entry-frame" title="Voucher entry" style="display:block;width:100%;height:78vh;border:0;visibility:hidden" referrerpolicy="same-origin"></iframe>
            </div>
        </div>
    </div>
</div>
@push('script')
<script>
(() => {
    $('html, body').addClass('daily-voucher-directory-active');
    // Size the directory to the actual space below the navbar so its footer never gets clipped.
    function fitDirectoryToViewport(){
        const $page=$('#dv-page');
        const available=Math.max(280,$(window).height()-$page.offset().top-8);
        $page.css('--dv-viewport-height',available+'px');
    }
    fitDirectoryToViewport();
    $(window).on('resize.dailyVoucherDirectory',fitDirectoryToViewport);
    const form=$('#daily-voucher-filters')[0], body=$('#dv-rows')[0], wrap=$('#dv-table-wrap')[0];
    let start=0, length=25, filtered=0, total=0, timer, activeRequest=0, activeController=null;
    let sortColumn='date',sortDirection='desc';
    const tableSearch=$('#dv-table-search')[0];
    $('#dv-page-length').on('change',function(){length=Number($(this).val());start=0;load();});
    $('#dv-table-search').on('input',()=>{clearTimeout(timer);timer=setTimeout(()=>{start=0;load();},300);});

    // All static dropdown choices arrive with the page's first HTML response.
    // No facet-request-on-open and no dependence on the visible table page.
    const voucherColumns=$.blueVoucherColumnFilters.init({
        initialOptions:@json($initialFilterOptions),
        formSelector:'#daily-voucher-filters',
        quickSearchSelector:'#dv-table-search',
        triggerSelector:'.dv-excel-trigger',
        tableWrapSelector:'#dv-table-wrap',
        getStart:()=>start,getLength:()=>length,
        getSortColumn:()=>sortColumn,getSortDirection:()=>sortDirection,
        onSort:(column,direction)=>{sortColumn=column;sortDirection=direction;},
        onChange:()=>{start=0;load();}
    });
    const queryParams=()=>voucherColumns.queryParams();
    const updateSortArrows=()=>voucherColumns.updateSortArrows();
    const closeExcel=()=>voucherColumns.close();
    const scheduleLoad=()=>{clearTimeout(timer);timer=setTimeout(()=>{start=0;load();},300);};
    function showTableSkeleton(){
        $.blueTableSkeleton.show($('#dv-rows'),{rows:10,columns:rowColumnCount,tone:$('#dv-page').hasClass('dv-theme-debit')?'debit':'credit'});
        $('#dv-record-summary').text('Loading records...');
        $('#dv-pages').empty();
    }
    const pager=$('#dv-pages')[0];
    const recordSummary=$('#dv-record-summary')[0];
    function renderPagination(){
        $.blueVoucherPagination.render({
            container:'#dv-pages',summary:'#dv-record-summary',
            total:filtered,allTotal:total,start:start,length:length,
            buttonClass:'dv-page-btn',
            onPage:function(nextStart){
                start=nextStart;load();$('#dv-table-wrap').scrollTop(0);
            }
        });
    }
    const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
    const showApprovedBy = @json(\App\Services\Finance\VoucherApproverAccess::isSuperAdmin(auth()->user()));
    const rowColumnCount = showApprovedBy ? 10 : 9;
    function load(){
        const requestId=++activeRequest;
        if(activeController)activeController.abort();
        $(wrap).addClass('daily-voucher-loading');
        showTableSkeleton();
        const params=queryParams();
        activeController=$.ajax({
            url:@json(route('finance.daily-vouchers.data')),
            method:'GET',data:params,dataType:'json',
            headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}
        }).done(result=>{
            if(requestId!==activeRequest)return;
            filtered=Number(result.recordsFiltered||0);
            total=Number(result.recordsTotal||0);
            renderPagination();
            $.blueActionMenu.close();
            $('#dv-rows').html(result.data.length?result.data.map((row,index)=>`<tr><td>${start+index+1}</td><td>${esc(row.date)}</td><td class="font-weight-bold">${esc(row.number)}</td><td>${esc(row.head)||'—'}</td><td>${esc(row.subhead)||'—'}</td><td class="text-truncate" style="max-width:420px" title="${esc(row.detail)}">${esc(row.detail)||'—'}</td><td class="text-right">${Number(row.amount||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}</td><td><span class="badge badge-${row.status==='Pending'?'warning':row.status==='Approved'?'success':row.status==='Rejected'?'danger':'secondary'}" title="${esc(row.status==='Rejected' ? 'Rejection Reason: '+(row.rejection_reason||'Not recorded') : (row.approval_approver||''))}">${esc(row.status||'Posted')}</span>${row.last_edit_status==='rejected'? ` <span class="badge badge-danger" title="${esc('Edit rejected: '+(row.last_edit_reason||'Reason not recorded'))}">Edit Rejected</span>` : ''}</td>${showApprovedBy ? `<td class="small">${esc(row.approved_by)||'—'}</td>` : ''}<td class="blue-actions-cell"><button type="button" class="btn btn-sm btn-outline-secondary dv-details" data-row="${esc(JSON.stringify(row))}">Details</button> ${row.edit_url?`<button type="button" class="btn btn-sm btn-outline-primary dv-edit" data-url="${esc(row.edit_url)}" data-number="${esc(row.number)}" title="Edit voucher" aria-label="Edit voucher ${esc(row.number)}"><i class="fas fa-edit" aria-hidden="true"></i></button>`:''} ${row.revise_url?`<button type="button" class="btn btn-sm btn-outline-warning dv-edit" data-url="${esc(row.revise_url)}" data-number="${esc(row.number)}" title="Revise rejected cash voucher"><i class="fas fa-redo"></i> Revise</button>`:''} ${row.release_url?`<button type="button" class="btn btn-sm btn-outline-danger dv-bank-release" data-url="${esc(row.release_url)}" data-number="${esc(row.number)}" data-status="${esc(row.status)}" title="Audited allocation release / administrative void"><i class="fas fa-unlink" aria-hidden="true"></i> Release / Void</button>`:''} ${row.delete_url?`<button type="button" class="btn btn-sm btn-outline-danger dv-delete" title="Delete voucher" aria-label="Delete voucher ${esc(row.number)}" data-url="${esc(row.delete_url)}" data-id="${esc(row.delete_id||'')}" data-number="${esc(row.number)}"><i class="fas fa-trash-alt" aria-hidden="true"></i></button>`:''} ${row.url?`<a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="${esc(row.url)}" aria-label="Print ${esc(row.number)}"><i class="fas fa-print"></i></a>`:'<span class="small text-muted">—</span>'}</td></tr>`).join(''):'<tr><td colspan="${rowColumnCount}" class="text-center text-muted p-4">No vouchers match these filters.</td></tr>' );
            $.blueActionMenu.enhance($('#dv-rows > tr > td.blue-actions-cell'));
        }).fail((xhr,status)=>{
            if(requestId!==activeRequest||status==='abort')return;
            const message=xhr.responseJSON?.message||'Unable to load voucher history.';
            $('#dv-rows').html('<tr><td colspan="'+rowColumnCount+'" class="text-center text-danger p-4">'+esc(message)+'</td></tr>');
            $('#dv-pages').empty();
            $('#dv-record-summary').text('Unable to load records');
        }).always(()=>{
            if(requestId!==activeRequest)return;
            $('#dv-table-wrap').removeClass('daily-voucher-loading');
            $.blueTableSkeleton.clear($('#dv-rows'));
            activeController=null;
        });
    }
    $('#daily-voucher-filters').on('submit',event=>{event.preventDefault();start=0;load();});
    $('#dv-reset-filters').on('click',()=>{
        $('#daily-voucher-filters')[0].reset();
        $('#dv-from,#dv-to,#dv-account,#dv-search').val('');
        if($('#dv-type').is('select'))$('#dv-type').val('');
        $('#dv-table-search').val('');
        voucherColumns.reset();
        start=0;sortColumn='date';sortDirection='desc';updateSortArrows();load();
    });
    $('#dv-type').on('change',function(){
        if($(this).is('select')){start=0;updateDirectoryTheme($(this).val());load();}
    });
    $('#dv-search').on('input',scheduleLoad);

    // The modal contains the real voucher form. No overlay, spinner or polling.
    const entryFrame=$('#dv-entry-frame')[0];
    const entryError=$('#dv-entry-error')[0];
    const entryErrorText=$('#dv-entry-error-text')[0];
    const fallback=$('#dv-entry-fallback')[0];
    const entryLoading=$('#dv-entry-loading')[0];
    const dvPage=$('#dv-page')[0];
    const voucherTypeLabels={CR:'Credit Voucher (CR)',DR:'Debit Voucher (DR)',CCR:'Cash Credit (CCR)',CDR:'Cash Debit (CDR)',BOR:'Bank Receipt Voucher (BOR)',BOP:'Bank Payment Voucher (BOP)'};
    function updateDirectoryTheme(type){
        $(dvPage).toggleClass('dv-theme-credit',['CR','CCR','BOR'].includes(type));
        $(dvPage).toggleClass('dv-theme-debit',['DR','CDR','BOP','CP'].includes(type));
        const label=type ? (voucherTypeLabels[type]||type+' Vouchers') : 'Voucher Directory';
        $('#dv-page .content-header h1').text(label);
        $('title').text(label+' - '+@json(Setting::getValue('app_name')));
    }
    // The voucher type comes from the page URL; individual pages never switch series.
    let requestedEntryUrl='',entryMode='create';
    function revealEntry(){
        if(!requestedEntryUrl)return;
        $('#dv-entry-frame').css('visibility','visible');
        $(entryLoading).addClass('d-none');
    }
    function showEntryError(message,url=requestedEntryUrl){
        $(entryLoading).addClass('d-none');
        $('#dv-entry-frame').css('visibility','hidden');
        $('#dv-entry-error-text').text(message);
        $('#dv-entry-fallback').attr('href',url||'#');
        $(fallback).toggleClass('d-none',!url);
        $(entryError).removeClass('d-none');
    }
    function loadEntry(url,mode='create'){
        entryMode=mode;
        requestedEntryUrl=url;
        $(entryLoading).toggleClass('d-none',!url);
        $('#dv-entry-frame').css('visibility','hidden');
        $(entryError).addClass('d-none');
        $('#dv-entry-frame').attr('src',url||'about:blank');
        if(!url)showEntryError('No voucher entry form is available for this type.');
    }
    $('#dv-entry-frame').on('load',()=>{
        if(!requestedEntryUrl || entryFrame.src==='about:blank')return;
        try{
            const doc=$('#dv-entry-frame').contents();
            if(entryMode==='edit')return;
            if(doc.find('#voucherForm').length){
                // The iframe load event is a fallback for a missed child ready message.
                requestAnimationFrame(()=>requestAnimationFrame(revealEntry));
                return;
            }
            const message=$.trim(doc.find('.alert-danger,.exception-message,h1').first().text());
            showEntryError(message ? 'Voucher form unavailable: '+message.slice(0,180) : 'Voucher form unavailable. Check your create permission or the server response.');
        }catch(error){
            showEntryError('Voucher form is blocked inside the modal. Open it in a separate tab.');
        }
    });
    const entryChoose=$('#dv-entry-choose')[0];
    const entryType=$('#dv-entry-type')[0];
    const methodWrap=$('#dv-bank-method-wrap')[0];
    const seriesMap={CR:'CR',DR:'DR',CP:'DR',CCR:'CCR',CDR:'CDR',BOR:'BOR',BOP:'BOP'};
    const dedicatedRoutes={
        JV:@json(route('journal.voucher.create')),
        CV:@json(route('commision.voucher.create')),
        PR:@json(route('finance.voucher.plot-receipt.workspace')),
        PP:@json(route('finance.voucher.plot-purchase.workspace')),
        PPR:@json(route('finance.voucher.plot-receipt.workspace')),
        BD:@json(route('finance.voucher.transfer.workspace',['kind'=>'deposit'])),
        BW:@json(route('finance.voucher.transfer.workspace',['kind'=>'withdrawal']))
    };
    function chooseVoucher(){
        requestedEntryUrl='';
        $(entryLoading).addClass('d-none');
        $('#dv-entry-frame').attr('src','about:blank').css({display:'none',visibility:'hidden'});
        $(entryError).addClass('d-none');
        $(methodWrap).addClass('d-none');
        $('#dv-entry-type').val('');
        $(entryChoose).removeClass('d-none');
        $('#dv-entry-title').text('New voucher');
        $('#dv-entry-modal').modal('show');
    }
    function openVoucherType(selected){
        if(dedicatedRoutes[selected]){
            window.location.assign(dedicatedRoutes[selected]);
            return;
        }
        const series=seriesMap[selected];
        const button=series ? $(`.dv-create[data-series="${series}"]`)[0] : null;
        if(!button){chooseVoucher();return;}
        $(entryChoose).addClass('d-none');
        $('#dv-entry-frame').css('display','block');
        $('#dv-entry-title').text($(button).data('title'));
        // BOR/BOP select Online or Cheque inside their own form, then open the passed-source picker.
        $(methodWrap).addClass('d-none');
        $('#dv-bank-method').val('online');
        $(methodWrap).data('onlineUrl',$(button).attr('data-url'))
            .data('chequeUrl',$(button).attr('data-cheque-url'));
        loadEntry($(button).attr('data-url'));
        $('#dv-entry-modal').modal('show');
    }
    $('#dv-entry-type').on('change',function(){
        if($(this).val())openVoucherType($(this).val());
    });
    $('#dv-bank-method').on('change',function(){
        loadEntry($(this).val()==='cheque'?$('#dv-bank-method-wrap').data('chequeUrl'):$('#dv-bank-method-wrap').data('onlineUrl'));
    });
    $('#dv-create-voucher').on('click',()=>{
        const selected=$('#dv-type').val();
        if(!selected || (!seriesMap[selected] && !dedicatedRoutes[selected]))chooseVoucher();
        else openVoucherType(selected);
    });
    $('#dv-entry-modal').on('hidden.bs.modal',()=>{requestedEntryUrl='';$(entryLoading).addClass('d-none');$(entryError).addClass('d-none');$(entryChoose).addClass('d-none');$('#dv-entry-frame').css('visibility','hidden').attr('src','about:blank');});
    $(window).on('message',messageEvent=>{
        const event=messageEvent.originalEvent;
        if(event.origin!==window.location.origin || event.source!==entryFrame.contentWindow)return;
        if(event.data?.type==='daily-voucher-form-ready' && entryMode==='create'){
            requestAnimationFrame(()=>requestAnimationFrame(revealEntry));
            return;
        }
        if(event.data?.type==='daily-voucher-edit-ready' && entryMode==='edit'){
            requestAnimationFrame(()=>requestAnimationFrame(revealEntry));
            return;
        }
        if(event.data?.type==='daily-voucher-edit-saved' && entryMode==='edit'){
            if(window.jQuery)window.jQuery('#dv-entry-modal').modal('hide');
            start=0;load();
            return;
        }
        if(event.data?.type==='daily-voucher-edit-cancelled' && entryMode==='edit'){
            if(window.jQuery)window.jQuery('#dv-entry-modal').modal('hide');
            return;
        }
        if(event.data?.type!=='daily-voucher-saved')return;
        if(window.jQuery)window.jQuery('#dv-entry-modal').modal('hide');
        $('#dv-entry-frame')[0].src='about:blank';start=0;load();
    });
    $('#dv-rows').on('click','.dv-edit',function(){
        const button=this;
        const url=new URL(button.dataset.url,window.location.href);
        url.searchParams.set('embed','1');
        $(entryChoose).addClass('d-none');
        $(methodWrap).addClass('d-none');
        $('#dv-entry-frame').css('display','block');
        $('#dv-entry-title').text('Edit '+$(button).data('number'));
        loadEntry(url.toString(),'edit');
        if(window.jQuery)window.jQuery('#dv-entry-modal').modal('show');
    });
    $('#dv-rows').on('click','.dv-bank-release',function(){
        const $button=$(this),voucher=String($button.data('number')||'');
        const isRejected=String($button.data('status')||'')==='Rejected';
        const action=isRejected?'release its reserved allocation':'administratively void its active posting and release its allocation';
        const reason=window.prompt('Reason (3-500 characters) to '+action+' for '+voucher+':');
        if(reason===null)return;
        if(reason.trim().length<3||reason.trim().length>500){
            window.alert('Enter an audit reason between 3 and 500 characters.');return;
        }
        if(!window.confirm('Confirm '+action+' for '+voucher+'? This is one-time, audited and cannot be undone.'))return;
        $button.prop('disabled',true);
        $.ajax({
            url:String($button.data('url')),
            method:'POST',dataType:'json',
            headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},
            data:{_token:@json(csrf_token()),reason:reason.trim()}
        }).done(function(response){
            if(window.toastr&&$.isFunction(window.toastr.success))window.toastr.success(response.message||'Bank allocation released.');
            else window.alert(response.message||'Bank allocation released.');
            load();
        }).fail(function(xhr){
            const message=xhr.responseJSON?.message||'Allocation release failed. Nothing was changed.';
            if(window.toastr&&$.isFunction(window.toastr.error))window.toastr.error(message);
            else window.alert(message);
        }).always(function(){$button.prop('disabled',false);});
    });
    $('#dv-rows').on('click','.dv-delete',function(){
        const button=this;
        const reason=window.prompt('Reason for deactivating '+$(button).data('number')+' (required):');
        if(!reason || !reason.trim())return;
        if(reason.trim().length>500){window.alert('Reason cannot exceed 500 characters.');return;}
        if(!window.confirm('Deactivate '+button.dataset.number+'? No ledger record will be physically deleted.'))return;
        // JV/CV use their independent authorized reversal workflow.
        if(!$(button).data('id')){
            const $deletion=$('<form>',{method:'POST',action:$(button).data('url')}).hide();
            for(const [name,value] of [['_token',@json(csrf_token())],['_method','DELETE'],['reason',reason.trim()],['effective_date',new Date().toISOString().slice(0,10)],['request_key',String(Date.now())]]){
                $deletion.append($('<input>',{type:'hidden',name,value}));
            }
            $('body').append($deletion);
            $deletion.trigger('submit');return;
        }
        $(button).prop('disabled',true);
        $.ajax({
            url:$(button).data('url'),
            method:'POST',
            dataType:'json',
            headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},
            data:{
                _token:@json(csrf_token()),_method:'DELETE',
                id:$(button).data('id'),delete_reason:reason.trim()
            }
        }).done(()=>load())
          .fail(xhr=>window.alert(xhr.responseJSON?.message||'Unable to deactivate voucher.'))
          .always(()=>$(button).prop('disabled',false));
    });
    $('#dv-rows').on('click','.dv-details',function(){
        const row=JSON.parse($(this).attr('data-row'));
        $('#dv-detail-title').text(row.type+' · '+row.number);
        $('#dv-print-identity').text(row.type+' / '+row.number+' · '+(row.date||''));
        $('#dv-common-print').prop('disabled',false);
        const appendDefinition=($target,label,value)=>{
            $target.append($('<dt>',{'class':'col-sm-4',text:label}));
            $target.append($('<dd>',{'class':'col-sm-8',text:value===null||value===undefined||value===''?'—':String(value)}));
        };
        const $fields=$('#dv-detail-fields').empty();
        [['Date',row.date],['Voucher type',row.type],['Voucher number',row.number],['Reference',row.reference],['Account / customer',row.account],['Amount',Number(row.amount||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})],['Status',row.status],['Narration / detail',row.detail]].forEach(([label,value])=>appendDefinition($fields,label,value));
        if(row.attachment_url){
            $fields.append($('<dt>',{'class':'col-sm-4',text:'Attachment'}));
            $fields.append($('<dd>',{'class':'col-sm-8'}).append(
                $('<a>',{href:row.attachment_url,target:'_blank',rel:'noopener noreferrer',
                    text:row.attachment_name||'View attachment'})));
        }
        const $audit=$('#dv-detail-audit').empty();
        Object.entries(row.audit||{}).forEach(([label,value])=>{
            if(value===null||value===undefined||value==='')return;
            appendDefinition($audit,label,typeof value==='object'?JSON.stringify(value):value);
        });
        $('#dv-detail-audit-wrap').toggleClass('d-none',$audit.children().length===0);
        const $lines=$('#dv-detail-lines').empty();
        (row.lines||[]).forEach(line=>{
            const $tr=$('<tr>');
            [line.account,line.description,Number(line.debit||0).toLocaleString(),Number(line.credit||0).toLocaleString()].forEach((value,index)=>{
                $tr.append($('<td>',{text:value||'—','class':index>1?'text-right':''}));
            });
            $lines.append($tr);
        });
        $('#dv-detail-lines-wrap').toggleClass('d-none',!(row.lines||[]).length);
        const $prints=$('#dv-detail-prints').empty();
        (row.prints||[]).forEach(item=>{
            $prints.append($('<a>',{'class':'btn btn-sm btn-outline-primary',href:item.url,target:'_blank',rel:'noopener',text:item.label}));
        });
        $('#dv-detail-prints-wrap').toggleClass('d-none',!(row.prints||[]).length);
        $('#dv-detail-print').toggleClass('d-none',!row.url).attr('href',row.url||'#');
        $('#dv-no-print').toggleClass('d-none',!!row.url);
        $('#dv-detail-modal').modal('show');
    });
    $('#dv-common-print').on('click',()=>{
        if(!$('#dv-detail-modal').hasClass('show'))return;
        $('body').addClass('dv-common-print-active');
        window.print();
    });
    $(window).on('afterprint.dailyVoucherCommon',()=>{$('body').removeClass('dv-common-print-active');});
    $('#dv-detail-modal').on('hidden.bs.modal',()=>{$('body').removeClass('dv-common-print-active');});
    load();
})();
</script>
@endpush
@endsection
