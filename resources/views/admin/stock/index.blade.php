@extends('admin.layouts.master')
@section('content')
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root {
      --pv-primary: #2b6cff;
      --pv-bg: #f5f7fb;
      --pv-card: #ffffff;
      --pv-border: #e7ecf5;
      --pv-text: #0b1220;
      --pv-muted: #64748b;
      --pv-shadow: 0 18px 52px -34px rgba(2, 6, 23, .30);
      --pv-radius: 18px;
      --pv-radius-lg: 22px;
      --pv-sidebar: #0d1b2a;
      --pv-sidebar2: #0b1522;
      --pv-success: #22c55e;
      --pv-warn: #f59e0b;
      --pv-danger: #ef4444;
    }

    .stock-module-wrapper { background: var(--pv-bg); color: var(--pv-text); min-height: calc(100vh - 140px); overflow-x: hidden; }

    /* Keep the ERP's actual navigation shell; this page owns only its compact toolbar. */
    .pv-shell { display: block; min-height: auto; }
    .pv-sidebar { display: none !important; }
    .pv-main { padding: 20px clamp(12px, 2vw, 30px) 34px; max-width: 1900px; margin: 0 auto; }
    .pv-topbar { border-radius: 16px; border-color: #e2eaf5; background: linear-gradient(115deg,#fff 0%,#f5f9ff 100%); }
    .pv-tabbtn { border-radius: 11px; font-weight: 650; }
    .pv-tabbtn.active { background: #eaf2ff; color: #175bd6; border-color: #bcd3ff; box-shadow: 0 5px 14px #2b6cff1a; }
    .pv-main .form-control, .pv-main .form-select { border-radius: 10px; min-height: 40px; }
    .pv-main .btn { border-radius: 10px; font-weight: 600; }
    .pv-main .table thead th { background: #f0f5fc; color: #35506f; font-size: .76rem; letter-spacing: .045em; }
    @media (max-width: 767.98px) { .pv-main { padding: 12px 10px 24px; } .pv-topbar { align-items: flex-start; flex-direction: column; } .pv-tabs { justify-content: flex-start; } }

    /* Shared voucher-style table-first layout; stock colors unchanged. */
    #view-entry>.row,#view-billing>.row,#view-items>.row,#view-parties>.row{display:block}
    #view-entry .pv-scroll,#view-billing .pv-scroll,#view-items .pv-scroll,#view-parties .pv-scroll{max-height:calc(100dvh - 315px);min-height:280px;overflow:auto}
    #view-entry>.row>.col-12>.pv-card,#view-billing>.row>.col-12>.pv-card{border-top:3px solid var(--pv-primary)}
    .stock-column-filters th{padding:.3rem!important;min-width:84px;vertical-align:top}
    .stock-column-filters .form-control,.stock-column-filters .form-select{min-height:32px!important;height:32px!important;font-size:.76rem!important;padding:.15rem .3rem!important;min-width:72px;border-radius:7px!important}
    #view-entry thead,#view-billing thead,#view-items thead,#view-parties thead,#view-stock thead{position:sticky;top:0;z-index:2}
    .stock-create-modal .modal-content{border-top:3px solid var(--pv-primary);border-radius:16px}
    .stock-create-modal .modal-body{padding:1rem 1.25rem}
    @media(max-width:767.98px){.stock-create-modal .modal-dialog{margin:.5rem}}


    /* CR voucher-style column triggers and Excel-like filtering popover. */
    .stock-module-wrapper .stock-excel-heading{white-space:nowrap;position:sticky;top:0}
    .stock-module-wrapper .stock-excel-heading>span{vertical-align:middle}
    .stock-module-wrapper .stock-excel-trigger{float:right;min-width:22px;height:22px;border:1px solid transparent;border-radius:4px;background:transparent;color:#586a82;padding:0 3px}
    .stock-module-wrapper .stock-excel-trigger:hover,.stock-module-wrapper .stock-excel-trigger[aria-expanded=true],.stock-module-wrapper .stock-excel-trigger.stock-has-filter{background:white;border-color:#2b6cff;color:#175bd6}
    .stock-excel-popover{position:fixed;z-index:2000;width:288px;max-width:calc(100vw - 20px);background:white;border:1px solid #c9d3de;border-radius:8px;box-shadow:0 14px 38px rgba(13,34,56,.22);padding:10px;color:#273549;font-size:12px}
    .stock-excel-popover.d-none{display:none!important}
    .stock-excel-sort{width:100%;border:0;background:white;text-align:left;padding:8px;border-radius:4px;color:#273549;display:block}
    .stock-excel-sort:hover,.stock-excel-sort:focus-visible{background:#ecf4fc}
    .stock-excel-values{height:190px;overflow:auto;border:1px solid #dde4ec;border-radius:5px;padding:5px}
    .stock-excel-choice{display:flex;align-items:center;gap:7px;margin:0;padding:5px 3px;cursor:pointer}
    .stock-excel-choice:hover{background:#f1f6fc}
    .stock-excel-choice input{flex:0 0 auto;margin:0}
    
    /* ERP voucher viewport: card body scrolls, browser document does not. */
    html.stock-page-active,body.stock-page-active{height:100%;overflow:hidden!important}
    body.stock-page-active .main-footer{display:none!important}
    .main-content.page-content:has(> .stock-module-wrapper){min-height:0!important;height:auto!important;overflow:hidden!important;padding-bottom:0!important}
    .stock-module-wrapper{--stock-page-top:124px;height:calc(100dvh - var(--stock-page-top) - 4px)!important;max-height:calc(100dvh - var(--stock-page-top) - 4px)!important;min-height:0!important;overflow:hidden!important}
    .stock-module-wrapper .pv-shell{height:100%;min-height:0}
    .stock-module-wrapper .pv-main{height:100%;display:flex!important;flex-direction:column;padding:8px 12px!important;max-width:none!important;min-height:0;overflow:hidden}
    .stock-module-wrapper .pv-topbar{flex:0 0 auto;padding:10px 12px!important;margin-bottom:8px!important}
    .stock-module-wrapper #pvViews{display:flex;flex:1 1 0;min-height:0;overflow:hidden}
    .stock-module-wrapper .pv-view{display:flex;flex:1 1 0;min-width:0;min-height:0}
    .stock-module-wrapper .pv-view.d-none{display:none!important}
    .stock-module-wrapper .pv-view>.row{display:flex!important;flex-direction:column!important;flex-wrap:nowrap!important;flex:1 1 0;min-height:0;min-width:0;width:100%;margin:0!important;gap:10px}
    .stock-module-wrapper .pv-view>.row>.col-12{padding:0!important;width:100%;flex:1 1 0;min-height:0;min-width:0;display:flex;flex-direction:column}
    .stock-module-wrapper .pv-view>.row>.col-12>.pv-card{flex:1 1 0;min-height:0;display:flex;flex-direction:column;border-top:3px solid var(--pv-primary)}
    .stock-module-wrapper .pv-view>.row>.col-12>.pv-card>.p-3{display:flex;flex-direction:column;flex:1 1 0;min-height:0;overflow:hidden;padding:.6rem .8rem!important}
    .stock-module-wrapper .pv-cardhead{flex:0 0 auto}
    .stock-module-wrapper .pv-view .pv-scroll{flex:1 1 0;min-height:0;max-height:none!important;overflow:auto}
    .stock-module-wrapper .pv-view .pv-scroll table{margin-bottom:0}
    .stock-module-wrapper .stock-kpi-row{flex:0 0 auto;width:100%;margin:0!important;display:flex;flex-wrap:nowrap}
    .stock-module-wrapper .stock-kpi-row>[class*=col-]{flex:1 1 0;min-width:0;padding:0 4px}
    .stock-module-wrapper .stock-kpi-row .pv-kpi{height:58px;padding:7px 10px}
    .stock-module-wrapper #view-stock .pv-cardhead{align-items:center}
    .stock-module-wrapper .stock-date-controls{display:flex;gap:9px;align-items:end}
    .stock-module-wrapper .stock-date-controls label{font-size:11px;color:var(--pv-muted);margin:0;font-weight:600}
    .stock-module-wrapper .stock-date-controls .form-control{min-height:32px;height:32px;padding:3px 7px;font-size:12px}
    .stock-module-wrapper #view-items .stock-items-side-by-side{display:grid;grid-template-columns:minmax(280px,36%) minmax(0,1fr);gap:12px;flex:1 1 0;min-height:0}
    .stock-module-wrapper #view-items .stock-import-history,.stock-module-wrapper #view-items .stock-items-register{display:flex;flex-direction:column;min-height:0;overflow:hidden}
    .stock-module-wrapper #view-items .stock-items-side-by-side .pv-scroll{min-height:0;overflow:auto;flex:1 1 0}
    .stock-module-wrapper #view-items .stock-import-history>.mb-3{display:flex;flex-direction:column;min-height:0;flex:1 1 0;margin:0!important}
    .stock-module-wrapper #view-items .stock-import-history>.mb-3>.table-responsive{flex:1 1 0;min-height:0;overflow:auto}
    .stock-module-wrapper #view-items .border.rounded-3.bg-light{flex:0 0 auto;padding:.6rem!important;margin-bottom:.5rem!important}
    @media(max-width:850px){.stock-module-wrapper #view-items .stock-items-side-by-side{grid-template-columns:1fr;grid-template-rows:minmax(125px,35%) minmax(0,1fr)}.stock-module-wrapper .stock-date-controls{flex-wrap:wrap}.stock-module-wrapper .stock-kpi-row .pv-kpi{height:auto;min-height:55px}}
    

    /* Unified compact finance-style stock modals; visible close buttons in both Bootstrap 4/5 shells. */
    .stock-create-modal .modal-dialog{max-width:min(950px,calc(100vw - 32px));margin:1.25rem auto}
    .stock-create-modal .modal-content,
    #editModal .modal-content,#stockDocumentEditModal .modal-content,#stockDocumentDeleteModal .modal-content{
      border:1px solid #dce6f5;border-top:3px solid var(--pv-primary);border-radius:14px!important;overflow:hidden;
    }
    .stock-create-modal .modal-header,#editModal .pv-cardhead,
    #stockDocumentEditModal .modal-header,#stockDocumentDeleteModal .modal-header{
      padding:10px 16px!important;min-height:52px;display:flex;align-items:center;justify-content:space-between;gap:12px;
      background:#fff;border-bottom:1px solid #e7ecf5;
    }
    .stock-create-modal .modal-header h5,#stockDocumentEditModal .modal-header h5,#stockDocumentDeleteModal .modal-header h5{
      font-size:16px;font-weight:650;margin:0;color:#21314e
    }
    .stock-modal-close{appearance:none;display:inline-flex;align-items:center;justify-content:center;flex:0 0 34px;
      width:34px;height:34px;border:1px solid #d6e0ed;background:#f8fbff;color:#405470;
      border-radius:9px;cursor:pointer;font-size:17px;opacity:1;line-height:1;transition:background .15s}
    .stock-modal-close:hover{background:#ffe9ea;border-color:#efb8bd;color:#ba2634}
    .stock-modal-close:focus-visible{outline:2px solid var(--pv-primary);outline-offset:2px}
    .stock-create-modal .modal-body{padding:12px 16px!important;max-height:calc(100dvh - 145px);overflow-y:auto}
    .stock-create-modal .modal-body hr{margin:9px 0!important}
    .stock-create-modal .modal-body .row{--bs-gutter-x:12px;--bs-gutter-y:7px}
    .stock-create-modal .form-label,.stock-create-modal label{font-size:11px!important;font-weight:600;margin-bottom:3px!important}
    .stock-create-modal .form-control,.stock-create-modal .form-select,.stock-create-modal .ts-control{
      min-height:34px!important;height:34px;font-size:12px!important;padding:5px 9px!important;border-radius:8px!important;
      border:1px solid #dce5f2!important;box-shadow:none!important;
    }
    .stock-create-modal textarea.form-control{height:48px!important;min-height:48px!important;resize:vertical}
    .stock-create-modal .stock-entry-mode{gap:12px!important;align-items:center}
    .stock-create-modal .stock-entry-mode button{min-width:104px;border-radius:8px;padding:5px 12px;font-size:12px;height:34px}
    .stock-create-modal .pv-line{padding:8px 10px!important;margin:0!important;border-radius:10px!important}
    .stock-create-modal #entryLines,.stock-create-modal #billLines{gap:6px!important}
    .stock-create-modal .pv-line .row{align-items:end}
    .stock-create-modal .pv-line .d-flex.mt-2{margin-top:4px!important}
    .stock-create-modal .pv-line .d-flex.mt-2 .pv-small{font-size:10px}
    .stock-create-modal .btn-lg{padding:7px 16px!important;font-size:12px!important;min-height:36px}
    .stock-create-modal .d-grid.mt-3,.stock-create-modal .d-grid.gap-2.mt-3{margin-top:10px!important}
    .stock-create-modal .pv-line.mt-3{margin-top:8px!important}
    .stock-create-modal .pv-line button.btn-outline-danger{padding:5px 8px;font-size:12px;min-height:34px}
    .ts-dropdown.stock-modal-dropdown,.ts-dropdown{z-index:2100!important}
    .stock-create-modal .ts-wrapper{width:100%;min-width:0;font-size:12px}
    .stock-create-modal .ts-wrapper.single .ts-control{display:flex;align-items:center;padding-right:26px!important}
    .stock-create-modal .select2-container{width:100%!important;min-width:0}
    .stock-create-modal .select2-container .select2-selection--single{height:34px!important;min-height:34px!important;border:1px solid #dce5f2;border-radius:8px}
    .stock-create-modal .select2-selection__rendered{font-size:12px;line-height:32px!important;padding-left:9px!important}
    .stock-create-modal .select2-selection__arrow{height:32px!important}
    .stock-module-wrapper .stock-date-controls{display:flex;gap:22px!important;align-items:center!important}
    .stock-module-wrapper .stock-date-controls label{display:flex;align-items:center;gap:9px!important;font-size:12px!important;white-space:nowrap}
    .stock-module-wrapper .stock-date-controls .form-control{width:185px!important;min-width:185px;height:36px!important}
    .stock-module-wrapper #view-items .stock-items-side-by-side{grid-template-columns:minmax(0,1fr) minmax(0,1fr)!important;align-items:stretch;min-height:0;height:100%}
    .stock-module-wrapper #view-items .stock-import-history,.stock-module-wrapper #view-items .stock-items-register{
      height:100%;min-height:0;overflow:hidden;border:1px solid #dfe7f3;border-radius:10px;background:#fff;padding:8px;display:flex;flex-direction:column
    }
    .stock-module-wrapper #view-items .stock-items-side-by-side .table-responsive{flex:1 1 0;min-height:0;max-height:none!important;overflow:auto}
    .stock-module-wrapper #view-items .stock-import-history>.mb-3{height:100%;flex:1 1 0}
    @media(max-width:850px){
      .stock-module-wrapper #view-items .stock-items-side-by-side{grid-template-columns:1fr!important;grid-template-rows:minmax(130px,1fr) minmax(130px,1fr)}
      .stock-module-wrapper .stock-date-controls .form-control{width:140px!important;min-width:140px}
    }


    /* Unified finance modal finishing styles. */
    .stock-create-modal .stock-modal-actions,#editModal .stock-modal-actions{display:flex!important;justify-content:flex-end;align-items:center;gap:8px;flex-wrap:wrap}
    .stock-create-modal .stock-modal-actions .pv-small{width:100%;text-align:right;margin:0!important}
    .stock-create-modal .stock-compact-line{padding:7px 9px!important}
    .stock-create-modal .stock-compact-line .row{--bs-gutter-x:8px!important;--bs-gutter-y:5px!important}
    .stock-compact-line .stock-line-field{display:flex;align-items:stretch;border:1px solid #dce5f2;border-radius:8px;background:#fff;min-width:0;height:34px}
    .stock-compact-line .stock-line-caption{display:flex;align-items:center;justify-content:center;flex:0 0 auto;padding:0 8px;background:#edf2f8;color:#718198;border-right:1px solid #dce5f2;border-radius:7px 0 0 7px;font-size:10px;font-weight:700;min-width:36px}
    .stock-compact-line .stock-line-field>.form-control,.stock-compact-line .stock-line-field>.form-select{flex:1 1 auto;min-width:0;width:100%;border:0!important;border-radius:0 7px 7px 0!important;box-shadow:none!important;height:32px!important;min-height:32px!important}
    .stock-compact-line .stock-line-field>.form-control[readonly]{background:#f5f7fa;color:#64748b}
    .stock-compact-line .stock-line-field>.ts-wrapper{flex:1 1 0;min-width:0;width:100%;height:32px}
    .stock-compact-line .stock-line-field .ts-control{border:0!important;height:32px!important;min-height:32px!important;border-radius:0 7px 7px 0!important;padding:4px 8px!important}
    .stock-compact-line .stock-line-summary{display:flex;align-items:center;justify-content:space-between;padding:3px 3px 0;margin-top:3px;line-height:1.2}
    .stock-compact-line .stock-line-summary .line-amount{font-size:11px;font-weight:700}
    .stock-compact-line .stock-line-remove,#stockDocumentEditModal .stock-doc-remove{height:26px!important;width:26px!important;min-height:26px!important;min-width:26px!important;padding:0!important;border:1px solid #f4c5ca!important;border-radius:8px!important;background:#fff4f5!important;color:#d14252!important;display:inline-flex!important;align-items:center;justify-content:center;font-size:11px!important;box-shadow:none!important}
    .stock-compact-line .stock-line-remove:hover,#stockDocumentEditModal .stock-doc-remove:hover{background:#d14252!important;color:#fff!important;border-color:#d14252!important}
    #stockDocumentEditModal .modal-footer,#stockDocumentDeleteModal .modal-footer{display:flex!important;justify-content:flex-end!important}
    @media(max-width:767.98px){.stock-compact-line .stock-line-caption{min-width:45px}}


    /* Never let global modal backdrop styles tint Tom Select option menus. */
    .modal.stock-create-modal .ts-dropdown,.modal#editModal .ts-dropdown,.modal#stockDocumentEditModal .ts-dropdown,
    .modal.stock-create-modal .ts-dropdown-content,.modal#editModal .ts-dropdown-content{
      background:#ffffff!important;color:#26364d!important;opacity:1!important;
      border:1px solid #d7e2f0!important;box-shadow:0 12px 24px rgba(14,38,70,.20)!important;
    }
    .modal.stock-create-modal .ts-dropdown,.modal#editModal .ts-dropdown,.modal#stockDocumentEditModal .ts-dropdown{
      z-index:1068!important;position:absolute!important;
    }
    .stock-create-modal .ts-dropdown .option,.stock-create-modal .ts-dropdown .active{
      background:#fff!important;color:#26364d!important;opacity:1!important;
    }
    .stock-create-modal .ts-dropdown .option.active,.stock-create-modal .ts-dropdown .option:hover{
      background:#eaf2ff!important;color:#175bd6!important
    }
    .stock-create-modal .ts-wrapper .ts-control input{color:#26364d!important}
    .modal.stock-create-modal,.modal#editModal,.modal#stockDocumentEditModal{isolation:isolate}

    /* Stock Tom Select menus are body-level overlays anchored to the input rect. */
    body>.ts-dropdown.stock-modal-dropdown{
      position:fixed!important;background:#fff!important;color:#26364d!important;
      opacity:1!important;border:1px solid #d7e2f0!important;border-radius:8px!important;
      box-shadow:0 12px 24px rgba(14,38,70,.20)!important;z-index:2100!important;
      overflow:auto!important;
    }
    body>.ts-dropdown.stock-modal-dropdown .option{background:#fff!important;color:#26364d!important}
    body>.ts-dropdown.stock-modal-dropdown .option.active,
    body>.ts-dropdown.stock-modal-dropdown .option:hover{background:#eaf2ff!important;color:#175bd6!important}


    /* CR voucher-style bordered directory tables and compact action menu. */
    .stock-module-wrapper .pv-view .pv-scroll > table.table,
    .stock-module-wrapper .stock-items-side-by-side table.table{
      border-collapse:separate!important;border-spacing:0!important;width:100%;
      border-top:1px solid #d5e0ec!important;border-left:1px solid #d5e0ec!important
    }
    .stock-module-wrapper .pv-view .pv-scroll > table.table > thead > tr > th,
    .stock-module-wrapper .pv-view .pv-scroll > table.table > tbody > tr > td,
    .stock-module-wrapper .stock-items-side-by-side table.table > thead > tr > th,
    .stock-module-wrapper .stock-items-side-by-side table.table > tbody > tr > td{
      border-right:1px solid #d5e0ec!important;border-bottom:1px solid #d5e0ec!important;
      border-left:0!important;border-top:0!important;vertical-align:middle;padding:8px 10px
    }
    .stock-module-wrapper .pv-view .pv-scroll > table.table > tbody > tr:nth-child(even),
    .stock-module-wrapper .stock-items-side-by-side table.table > tbody > tr:nth-child(even){background:#f8fbff}
    .stock-module-wrapper .pv-view .pv-scroll > table.table > tbody > tr:hover,
    .stock-module-wrapper .stock-items-side-by-side table.table > tbody > tr:hover{background:#eef5ff}
    /* Grid lines must remain visible despite Bootstrap's table cell shadows. */
    .stock-module-wrapper .pv-view table.table > :not(caption) > * > * {
      border-color:#cbd8e6!important;
      border-style:solid!important;
      border-width:0 1px 1px 0!important;
      --bs-table-border-color:#cbd8e6;
      --bs-table-bg-state:transparent;
      --bs-table-bg-type:transparent;
    }
    .stock-module-wrapper .pv-view table.table > :not(caption) > * > :first-child {
      border-left:1px solid #cbd8e6!important;
    }
    .stock-module-wrapper .pv-view table.table > thead > tr:first-child > th {
      border-top:1px solid #cbd8e6!important;
      border-bottom:1px solid #a9c0d5!important;
    }
    .stock-module-wrapper .pv-view table.table > tbody > tr > td {
      background:transparent;box-shadow:none!important;
    }
    .stock-module-wrapper .pv-view table.table > tbody > tr:nth-child(even) > td {background:#f8fbff!important;}
    .stock-module-wrapper .pv-view table.table > tbody > tr:hover > td {background:#eef5ff!important;}
    .stock-module-wrapper .pv-view .blue-actions-cell {
      width:76px!important;min-width:76px!important;max-width:76px!important;text-align:center!important;white-space:nowrap;
    }
    /* Voucher-consistent AJAX table skeleton within the scrolling card. */
    .stock-module-wrapper .pv-scroll,.stock-module-wrapper .stock-import-history .table-responsive{position:relative}
    .stock-skeleton-overlay{position:absolute;inset:0;z-index:7;background:rgba(255,255,255,.95);
      display:flex;flex-direction:column;gap:12px;padding:12px;pointer-events:none;overflow:hidden}
    .stock-skeleton-row{display:flex;gap:12px;width:100%;height:28px;flex:0 0 auto}
    .stock-skeleton-bar{height:100%;border-radius:6px;flex:1;min-width:0;
      background:linear-gradient(100deg,#e9eef6 15%,#f6f9ff 40%,#e9eef6 70%);
      background-size:220% 100%;animation:stock-skeleton-shimmer 1.25s linear infinite}
    .stock-skeleton-row .stock-skeleton-bar:first-child{flex:.38}
    .stock-skeleton-row .stock-skeleton-bar:last-child{flex:.55}
    @keyframes stock-skeleton-shimmer{from{background-position:220% 0}to{background-position:-220% 0}}
    @media(prefers-reduced-motion:reduce){.stock-skeleton-bar{animation:none}}

    /* App shell (labour module-ish) */
    .pv-shell {
      display: flex;
      min-height: 100vh;
    }

    .pv-sidebar {
      width: 72px;
      background: linear-gradient(180deg, var(--pv-sidebar), var(--pv-sidebar2));
      color: #cbd5e1;
      position: sticky;
      top: 0;
      height: 100vh;
      border-right: 1px solid rgba(255, 255, 255, .06);
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 10px 8px;
      gap: 10px;
      z-index: 10;
    }

    .pv-brand {
      width: 46px;
      height: 46px;
      border-radius: 14px;
      background: rgba(255, 255, 255, .06);
      display: grid;
      place-items: center;
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .08);
      margin: 4px 0 8px;
    }

    .pv-navicon {
      width: 46px;
      height: 46px;
      border-radius: 14px;
      display: grid;
      place-items: center;
      cursor: pointer;
      transition: transform .08s ease, background .15s ease, color .15s ease;
      color: #cbd5e1;
      position: relative;
    }

    .pv-navicon:hover {
      background: rgba(255, 255, 255, .06);
      transform: translateY(-1px);
    }

    .pv-navicon.active {
      background: rgba(43, 108, 255, .18);
      color: #e7f0ff;
      box-shadow: inset 0 0 0 1px rgba(43, 108, 255, .35);
    }

    .pv-navicon.active::before {
      content: "";
      position: absolute;
      left: -6px;
      width: 4px;
      height: 26px;
      border-radius: 999px;
      background: var(--pv-primary);
    }

    .pv-main {
      flex: 1;
      padding: 18px 18px 26px;
    }

    .pv-topbar {
      background: var(--pv-card);
      border: 1px solid var(--pv-border);
      border-radius: var(--pv-radius-lg);
      box-shadow: var(--pv-shadow);
      padding: 12px 14px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      margin-bottom: 14px;
    }

    .pv-topbar .pv-title {
      display: flex;
      align-items: center;
      gap: 10px;
      min-width: 260px;
    }

    .pv-topbar .pv-title h1 {
      font-size: 16px;
      margin: 0;
      letter-spacing: .2px;
    }

    .pv-chip {
      font-size: 12px;
      color: var(--pv-muted);
      background: #f1f5ff;
      border: 1px solid #dfe8ff;
      padding: 4px 10px;
      border-radius: 999px;
      white-space: nowrap;
    }

    .pv-tabs {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      justify-content: flex-end;
    }

    .pv-tabbtn {
      border: 1px solid var(--pv-border);
      background: #fff;
      color: #223;
      padding: 8px 12px;
      border-radius: 999px;
      font-weight: 600;
      font-size: 13px;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: background .15s ease, transform .08s ease;
    }

    .pv-tabbtn:hover {
      background: #f7f9ff;
      transform: translateY(-1px);
    }

    .pv-tabbtn.active {
      background: var(--pv-primary);
      border-color: var(--pv-primary);
      color: #fff;
      box-shadow: 0 12px 26px -18px rgba(43, 108, 255, .55);
    }

    .pv-card {
      background: var(--pv-card);
      border: 1px solid var(--pv-border);
      border-radius: var(--pv-radius-lg);
      box-shadow: var(--pv-shadow);
      overflow: hidden;
    }

    .pv-card .pv-cardhead {
      padding: 14px 16px;
      border-bottom: 1px solid var(--pv-border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
    }

    .pv-card .pv-cardhead h2 {
      margin: 0;
      font-size: 14px;
      font-weight: 800;
      letter-spacing: .2px;
    }

    .pv-muted {
      color: var(--pv-muted);
    }

    .pv-small {
      font-size: 12px;
    }

    .pv-scroll {
      max-height: 520px;
      overflow: auto;
    }

    .pv-kpi {
      background: #fbfcff;
      border: 1px solid var(--pv-border);
      border-radius: 16px;
      padding: 12px 12px;
      display: flex;
      gap: 12px;
      align-items: center;
    }

    .pv-kpi i {
      font-size: 18px;
      width: 36px;
      height: 36px;
      border-radius: 14px;
      display: grid;
      place-items: center;
      background: #eef3ff;
      color: var(--pv-primary);
    }

    .pv-kpi .n {
      font-weight: 900;
      font-size: 16px;
    }

    .pv-kpi .l {
      font-size: 12px;
      color: var(--pv-muted);
    }

    .table thead th {
      font-size: 11px;
      letter-spacing: .7px;
      text-transform: uppercase;
      color: var(--pv-muted);
      background: #f7f9ff;
      border-bottom: 1px solid var(--pv-border);
      white-space: nowrap;
    }

    .table td {
      vertical-align: middle;
    }

    .pv-actions .btn {
      border-radius: 12px;
      padding: 7px 10px;
      font-weight: 700;
      font-size: 12px;
    }

    .pv-pill {
      font-size: 11px;
      padding: 4px 10px;
      border-radius: 999px;
      border: 1px solid var(--pv-border);
      background: #fff;
      color: var(--pv-muted);
      white-space: nowrap;
    }

    .pv-pill.ok {
      border-color: #bfead0;
      background: #effaf3;
      color: #0f5132;
    }

    .pv-pill.warn {
      border-color: #ffe2b0;
      background: #fff7e8;
      color: #8a5a00;
    }

    .pv-pill.bad {
      border-color: #ffc2c2;
      background: #fff0f0;
      color: #842029;
    }

    .form-control,
    .form-select {
      border-radius: 14px;
      border-color: var(--pv-border);
      background: #fff;
      padding: 10px 12px;
    }

    .form-control:focus,
    .form-select:focus {
      box-shadow: 0 0 0 .2rem rgba(43, 108, 255, .12);
      border-color: rgba(43, 108, 255, .55);
    }

    .pv-line {
      border: 1px solid var(--pv-border);
      border-radius: 16px;
      padding: 10px;
      background: #fff;
    }

    .pv-line .row>* {
      margin-bottom: 8px;
    }

    .pv-subseg {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .pv-subseg .btn {
      border-radius: 999px;
      font-weight: 800;
      font-size: 12px;
      padding: 8px 12px;
    }

    /* Responsive */
    @media (max-width: 992px) {
      .pv-sidebar {
        display: none;
      }

      .pv-main {
        padding: 12px;
      }

      .pv-topbar {
        border-radius: 18px;
      }

      .pv-scroll {
        max-height: 420px;
      }
    }
    /* Override legacy mock shell styles: use the real sticky ERP sidebar and navbar. */
    .pv-shell { display: block; min-height: auto; }
    .pv-sidebar { display: none !important; }
    .pv-main { display: block; width: 100%; padding: 20px clamp(12px, 2vw, 30px) 34px; max-width: 1900px; margin: 0 auto; }
    .pv-topbar { border-radius: 16px; border-color: #e2eaf5; background: linear-gradient(115deg,#fff,#f5f9ff); }
    .pv-tabbtn { border-radius: 11px; font-weight: 650; }
    .pv-tabbtn.active { background: #eaf2ff; color: #175bd6; border-color: #bcd3ff; box-shadow: 0 5px 14px #2b6cff1a; }
    .pv-main .form-control, .pv-main .form-select { border-radius: 10px; min-height: 40px; }
    .pv-main .btn { border-radius: 10px; font-weight: 600; }
    .pv-main .table thead th { background: #f0f5fc; color: #35506f; font-size: .76rem; letter-spacing: .045em; }
    @media (max-width: 767.98px) { .pv-main { padding: 12px 10px 24px; } .pv-topbar { align-items: flex-start; flex-direction: column; } .pv-tabs { justify-content: flex-start; } }
  </style>
  <div class="content-wrapper stock-module-wrapper"><div class="pv-shell">
    <!-- Sidebar (visual only) -->
    <aside class="pv-sidebar" aria-hidden="true">
      <div class="pv-brand" title="ERP">
        <i class="bi bi-boxes text-white"></i>
      </div>

      <div class="pv-navicon" title="Dashboard">
        <i class="bi bi-house"></i>
      </div>
      <div class="pv-navicon" title="Labour">
        <i class="bi bi-people"></i>
      </div>
      <div class="pv-navicon active" title="Stock">
        <i class="bi bi-box-seam"></i>
      </div>
      <div class="pv-navicon" title="Reports">
        <i class="bi bi-graph-up"></i>
      </div>

      <div class="mt-auto pv-navicon" title="Settings">
        <i class="bi bi-gear"></i>
      </div>
    </aside>

    <main class="pv-main">
      <!-- Top bar -->
      <div class="pv-topbar">
        <div class="pv-title">
          <div class="pv-chip">
            <i class="bi bi-box-seam me-1"></i> Stock Management Module
          </div>
          <div class="pv-small pv-muted d-none d-md-block">
            Project-scoped stock, billing and material records
          </div>
        </div>

        <div class="pv-tabs">
          <button class="pv-tabbtn active" data-view="entry">
            <i class="bi bi-arrow-left-right"></i> Entry
          </button>
          <button class="pv-tabbtn" data-view="billing">
            <i class="bi bi-receipt"></i> Billing
          </button>
          <button class="pv-tabbtn" data-view="stock">
            <i class="bi bi-clipboard-data"></i> Stock
          </button>
          <button class="pv-tabbtn" data-view="items">
            <i class="bi bi-box2"></i> Items
          </button>
          <button class="pv-tabbtn" data-view="parties">
            <i class="bi bi-person-lines-fill"></i> Parties
          </button>
        </div>
      </div>

      @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
      @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
      @if ($canReconcileLegacyStock && (count($legacyStockCandidates['entries']) + count($legacyStockCandidates['bills']) + count($legacyStockCandidates['movements']) > 0 || $legacyStockAudits->isNotEmpty()))
        <div class="card border-warning mb-3" data-ajax-panel="stock-reconciliation-panel">
          <div class="card-header bg-light"><strong>Legacy stock project reconciliation</strong></div>
          <div class="card-body">
            <p class="text-muted">Assign only records whose party belongs to this project. Each assignment requires a reason and is audited. Linked lines and source movements are scoped together; quantities, rates and amounts are not edited. Review up to 50 candidates per section.</p>
            @if (count($legacyStockCandidates['entries']))
              <h6>Unassigned entries</h6><div class="table-responsive mb-3"><table class="table table-sm table-bordered"><thead><tr><th>Slip</th><th>Date</th><th>Flow</th><th>Party</th><th>Record state</th><th>Assignment reason</th></tr></thead><tbody>
              @foreach ($legacyStockCandidates['entries'] as $candidate)<tr><td>{{ $candidate->slip_no }}</td><td>{{ $candidate->entry_date }}</td><td>{{ $candidate->flow }}</td><td>{{ $candidate->party_name }}</td><td>{{ $candidate->deleted_at ? 'Archived' : 'Active' }}</td><td><form method="POST" action="{{ route('stocks.legacy.reconcile') }}" class="d-flex" onsubmit="return confirm('Assign this entry and its linked history to the selected project? Its archive state will stay unchanged.')">@csrf<input type="hidden" name="kind" value="entry"><input type="hidden" name="record_id" value="{{ $candidate->id }}"><input class="form-control form-control-sm mr-2" name="reason" minlength="8" maxlength="500" placeholder="Why does this record belong to this project?" required><button class="btn btn-sm btn-warning">Assign</button></form></td></tr>@endforeach
              </tbody></table></div>
            @endif
            @if (count($legacyStockCandidates['bills']))
              <h6>Unassigned bills</h6><div class="table-responsive mb-3"><table class="table table-sm table-bordered"><thead><tr><th>Bill</th><th>Date</th><th>Type</th><th>Entry ID</th><th>Party</th><th>Record state</th><th>Assignment reason</th></tr></thead><tbody>
              @foreach ($legacyStockCandidates['bills'] as $candidate)<tr><td>{{ $candidate->bill_no }}</td><td>{{ $candidate->bill_date }}</td><td>{{ $candidate->type }}</td><td>{{ $candidate->entry_id ?? '—' }}</td><td>{{ $candidate->party_name }}</td><td>{{ $candidate->deleted_at ? 'Archived' : 'Active' }}</td><td><form method="POST" action="{{ route('stocks.legacy.reconcile') }}" class="d-flex" onsubmit="return confirm('Assign this bill and its linked history to the selected project? Its archive state will stay unchanged.')">@csrf<input type="hidden" name="kind" value="bill"><input type="hidden" name="record_id" value="{{ $candidate->id }}"><input class="form-control form-control-sm mr-2" name="reason" minlength="8" maxlength="500" placeholder="Reason" required><button class="btn btn-sm btn-warning">Assign</button></form></td></tr>@endforeach
              </tbody></table></div>
            @endif
            @if (count($legacyStockCandidates['movements']))
              <h6>Unassigned standalone movements</h6><div class="table-responsive"><table class="table table-sm table-bordered"><thead><tr><th>ID</th><th>Date</th><th>Direction</th><th>Item ID</th><th>Quantity</th><th>Source</th><th>Party</th><th>Assignment reason</th></tr></thead><tbody>
              @foreach ($legacyStockCandidates['movements'] as $candidate)<tr><td>{{ $candidate->id }}</td><td>{{ $candidate->movement_date }}</td><td>{{ $candidate->direction }}</td><td>{{ $candidate->item_id }}</td><td>{{ $candidate->qty }}</td><td>{{ $candidate->source_type }} #{{ $candidate->source_id }}</td><td>{{ $candidate->party_name }}</td><td><form method="POST" action="{{ route('stocks.legacy.reconcile') }}" class="d-flex" onsubmit="return confirm('Assign this standalone movement to the selected project?')">@csrf<input type="hidden" name="kind" value="movement"><input type="hidden" name="record_id" value="{{ $candidate->id }}"><input class="form-control form-control-sm mr-2" name="reason" minlength="8" maxlength="500" placeholder="Reason" required><button class="btn btn-sm btn-warning">Assign</button></form></td></tr>@endforeach
              </tbody></table></div>
            @endif
            @if ($legacyStockAudits->isNotEmpty())
              <h6 class="mt-3">Recent reconciliation audit</h6><div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>When</th><th>Record</th><th>ID</th><th>Reason</th><th>By</th></tr></thead><tbody>
              @foreach ($legacyStockAudits as $audit)<tr><td>{{ $audit->created_at }}</td><td>{{ str_replace('_', ' ', $audit->record_type) }}</td><td>{{ $audit->record_id }}</td><td>{{ $audit->reason }}</td><td>{{ $audit->actor_name ?? 'Unavailable user' }}</td></tr>@endforeach
              </tbody></table></div>
            @endif
          </div>
        </div>
      @endif

<div id="stockServerFilters" class="d-none" aria-hidden="true">
<select id="entryFilterFlow"><option value="">All</option><option value="IN">IN</option><option value="OUT">OUT</option></select>
<select id="entryFilterItem"></select><input id="entrySearch" type="search">
<select id="billFilterType"><option value="">All</option><option value="purchase">Purchase</option><option value="sale">Sale</option></select>
<select id="billFilterParty"></select><input id="billSearch" type="search">
<select id="stockItemFilter"></select><input id="itemSearch" type="search">
<input id="partySearch" type="search">
<select id="partyFilterType"><option value="">All</option><option value="Supplier">Supplier</option><option value="Consumer">Consumer</option><option value="Site">Site</option></select></div>
      <!-- Views container -->
      <div id="pvViews">
        <!-- ===================== ENTRY VIEW (Purchase + Stock Out) ===================== -->
        <section id="view-entry" class="pv-view">
          <div class="row g-3">
<div class="row g-2 stock-kpi-row">
                <div class="col-md-4">
                  <div class="pv-kpi">
                    <i class="bi bi-box-arrow-in-down"></i>
                    <div>
                      <div class="n" id="kpiInQty">0</div>
                      <div class="l">Total IN Qty</div>
                    </div>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="pv-kpi">
                    <i class="bi bi-box-arrow-up"></i>
                    <div>
                      <div class="n" id="kpiOutQty">0</div>
                      <div class="l">Total OUT Qty</div>
                    </div>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="pv-kpi">
                    <i class="bi bi-clipboard2-check"></i>
                    <div>
                      <div class="n" id="kpiLines">0</div>
                      <div class="l">Total Lines</div>
                    </div>
                  </div>
                </div>
              </div>
            <!-- Right: Entry List -->
            <div class="col-12">
              <div class="pv-card">
                <div class="pv-cardhead">
                  <div>
                    <h2>Entries</h2>
                    <div class="pv-small pv-muted">
                      Excel list style: Sr, Date, In/Out, Name, Details, Item,
                      Qty, Rate, Billing Status.
                    </div>
                  </div>
                  <div class="d-flex gap-2 align-items-center flex-wrap">
                    <span class="pv-chip" id="entryCountChip">0 total</span><button class="btn btn-sm btn-primary" type="button" id="stockNewEntry" data-stock-modal="#stockEntryCreateModal"><i class="bi bi-plus-lg"></i> New Entry</button>
                  </div>
                </div>

                <div class="p-3">
                  <div class="table-responsive pv-scroll">
                    <table class="table table-hover align-middle mb-0">
                      <thead>
                        <tr>
                          <th class="stock-excel-heading"><span>Sr</span><button type="button" class="stock-excel-trigger" data-stock-kind="entry" data-stock-column="0" data-stock-label="Sr" aria-label="Sort and filter Sr" aria-haspopup="dialog" aria-expanded="false" title="Sort and filter Sr"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
                          <th class="stock-excel-heading"><span>Date</span><button type="button" class="stock-excel-trigger" data-stock-kind="entry" data-stock-column="1" data-stock-label="Date" aria-label="Sort and filter Date" aria-haspopup="dialog" aria-expanded="false" title="Sort and filter Date"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
                          <th class="stock-excel-heading"><span>In/Out</span><button type="button" class="stock-excel-trigger" data-stock-kind="entry" data-stock-column="2" data-stock-label="In/Out" aria-label="Sort and filter In/Out" aria-haspopup="dialog" aria-expanded="false" title="Sort and filter In/Out"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
                          <th class="stock-excel-heading"><span>Name</span><button type="button" class="stock-excel-trigger" data-stock-kind="entry" data-stock-column="3" data-stock-label="Name" aria-label="Sort and filter Name" aria-haspopup="dialog" aria-expanded="false" title="Sort and filter Name"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
                          <th class="stock-excel-heading"><span>Details</span><button type="button" class="stock-excel-trigger" data-stock-kind="entry" data-stock-column="4" data-stock-label="Details" aria-label="Sort and filter Details" aria-haspopup="dialog" aria-expanded="false" title="Sort and filter Details"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
                          <th class="stock-excel-heading"><span>Item</span><button type="button" class="stock-excel-trigger" data-stock-kind="entry" data-stock-column="5" data-stock-label="Item" aria-label="Sort and filter Item" aria-haspopup="dialog" aria-expanded="false" title="Sort and filter Item"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
                          <th class="stock-excel-heading"><span>Qty</span><button type="button" class="stock-excel-trigger" data-stock-kind="entry" data-stock-column="6" data-stock-label="Qty" aria-label="Sort and filter Qty" aria-haspopup="dialog" aria-expanded="false" title="Sort and filter Qty"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
                          <th class="stock-excel-heading"><span>Rate</span><button type="button" class="stock-excel-trigger" data-stock-kind="entry" data-stock-column="7" data-stock-label="Rate" aria-label="Sort and filter Rate" aria-haspopup="dialog" aria-expanded="false" title="Sort and filter Rate"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
                          <th class="stock-excel-heading"><span>Amount</span><button type="button" class="stock-excel-trigger" data-stock-kind="entry" data-stock-column="8" data-stock-label="Amount" aria-label="Sort and filter Amount" aria-haspopup="dialog" aria-expanded="false" title="Sort and filter Amount"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
                          <th class="stock-excel-heading"><span>Billing</span><button type="button" class="stock-excel-trigger" data-stock-kind="entry" data-stock-column="9" data-stock-label="Billing" aria-label="Sort and filter Billing" aria-haspopup="dialog" aria-expanded="false" title="Sort and filter Billing"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th><th>Actions</th>
                        </tr>

                      </thead>
                      <tbody id="entryTbody"></tbody>
                    </table>
                  </div>
                </div>
              </div>

              <!-- KPIs -->
              
            </div>
          </div>
        </section>

        <!-- ===================== BILLING VIEW ===================== -->
        <section id="view-billing" class="pv-view d-none">
          <div class="row g-3">
<div class="row g-2 stock-kpi-row">
                <div class="col-md-4">
                  <div class="pv-kpi">
                    <i class="bi bi-cart-plus"></i>
                    <div>
                      <div class="n" id="kpiPurchaseBills">0</div>
                      <div class="l">Purchase Bills</div>
                    </div>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="pv-kpi">
                    <i class="bi bi-cart-check"></i>
                    <div>
                      <div class="n" id="kpiSaleBills">0</div>
                      <div class="l">Sale Bills</div>
                    </div>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="pv-kpi">
                    <i class="bi bi-cash-coin"></i>
                    <div>
                      <div class="n" id="kpiBillTotal">PKR 0</div>
                      <div class="l">Bills Total</div>
                    </div>
                  </div>
                </div>
              </div>
            <div class="col-12">
              <div class="pv-card">
                <div class="pv-cardhead">
                  <div>
                    <h2>Bills</h2>
                    <div class="pv-small pv-muted">
                      Excel list: Sr, Date, Name, Details, Amount,
                      Print/Edit/Delete.
                    </div>
                  </div>
                  <span class="pv-chip" id="billCountChip">0 total</span><button class="btn btn-sm btn-primary" type="button" id="stockNewBill" data-stock-modal="#stockBillCreateModal"><i class="bi bi-plus-lg"></i> New Bill</button>
                </div>

                <div class="p-3">
                  <div class="table-responsive pv-scroll">
                    <table class="table table-hover align-middle mb-0">
                      <thead>
                        <tr>
                          <th class="stock-excel-heading"><span>Sr</span><button type="button" class="stock-excel-trigger" data-stock-kind="bill" data-stock-column="0" data-stock-label="Sr" aria-label="Sort and filter Sr" aria-haspopup="dialog" aria-expanded="false" title="Sort and filter Sr"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
                          <th class="stock-excel-heading"><span>Date</span><button type="button" class="stock-excel-trigger" data-stock-kind="bill" data-stock-column="1" data-stock-label="Date" aria-label="Sort and filter Date" aria-haspopup="dialog" aria-expanded="false" title="Sort and filter Date"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
                          <th class="stock-excel-heading"><span>Type</span><button type="button" class="stock-excel-trigger" data-stock-kind="bill" data-stock-column="2" data-stock-label="Type" aria-label="Sort and filter Type" aria-haspopup="dialog" aria-expanded="false" title="Sort and filter Type"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
                          <th class="stock-excel-heading"><span>Name</span><button type="button" class="stock-excel-trigger" data-stock-kind="bill" data-stock-column="3" data-stock-label="Name" aria-label="Sort and filter Name" aria-haspopup="dialog" aria-expanded="false" title="Sort and filter Name"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
                          <th class="stock-excel-heading"><span>Details</span><button type="button" class="stock-excel-trigger" data-stock-kind="bill" data-stock-column="4" data-stock-label="Details" aria-label="Sort and filter Details" aria-haspopup="dialog" aria-expanded="false" title="Sort and filter Details"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
                          <th class="stock-excel-heading"><span>Amount</span><button type="button" class="stock-excel-trigger" data-stock-kind="bill" data-stock-column="5" data-stock-label="Amount" aria-label="Sort and filter Amount" aria-haspopup="dialog" aria-expanded="false" title="Sort and filter Amount"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th><th>Actions</th>
                        </tr>

                      </thead>
                      <tbody id="billTbody"></tbody>
                    </table>
                  </div>
                </div>
              </div>

              
            </div>
          </div>
        </section>

        <!-- ===================== STOCK VIEW ===================== -->
        <section id="view-stock" class="pv-view d-none">
          <div class="row g-3">
<div class="row g-2 stock-kpi-row">
                    <div class="col-md-3">
                      <div class="pv-kpi">
                        <i class="bi bi-box2"></i>
                        <div>
                          <div class="n" id="kpiStockItems">0</div>
                          <div class="l">Items in report</div>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-3">
                      <div class="pv-kpi">
                        <i class="bi bi-arrow-down-circle"></i>
                        <div>
                          <div class="n" id="kpiStockIn">0</div>
                          <div class="l">Total Purchase Qty</div>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-3">
                      <div class="pv-kpi">
                        <i class="bi bi-arrow-up-circle"></i>
                        <div>
                          <div class="n" id="kpiStockOut">0</div>
                          <div class="l">Total Sale/Out Qty</div>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-3">
                      <div class="pv-kpi">
                        <i class="bi bi-clipboard2-data"></i>
                        <div>
                          <div class="n" id="kpiStockAvail">0</div>
                          <div class="l">Total Available</div>
                        </div>
                      </div>
                    </div>
                  </div>
            <div class="col-12">
              <div class="pv-card">
                <div class="pv-cardhead">
                  <div>
                    <h2>Stock (Date Range)</h2>
                    <div class="pv-small pv-muted">
                      Excel “Stock” sheet: Item, Purchase Qty, Sale Qty,
                      Available Stock.
                    </div>
                  </div>
                  <div class="stock-date-controls"><label for="stockFrom"><span>From</span><input type="date" class="form-control form-control-sm" id="stockFrom" aria-label="Stock report start date"></label><label for="stockTo"><span>To</span><input type="date" class="form-control form-control-sm" id="stockTo" aria-label="Stock report end date"></label><span class="d-none" id="stockRangeChip">Range: —</span></div>
                </div>

                <div class="p-3">
                  

                  

                  <div class="table-responsive mt-3">
                    <table class="table table-hover align-middle mb-0">
                      <thead>
                        <tr>
                          <th class="stock-excel-heading"><span>Item</span><button type="button" class="stock-excel-trigger" data-stock-kind="stock" data-stock-column="0" data-stock-label="Item" aria-label="Sort and filter Item" aria-haspopup="dialog" aria-expanded="false" title="Sort and filter Item"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
                          <th class="stock-excel-heading"><span>Pur Qty</span><button type="button" class="stock-excel-trigger" data-stock-kind="stock" data-stock-column="1" data-stock-label="Pur Qty" aria-label="Sort and filter Pur Qty" aria-haspopup="dialog" aria-expanded="false" title="Sort and filter Pur Qty"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
                          <th class="stock-excel-heading"><span>Sale Qty</span><button type="button" class="stock-excel-trigger" data-stock-kind="stock" data-stock-column="2" data-stock-label="Sale Qty" aria-label="Sort and filter Sale Qty" aria-haspopup="dialog" aria-expanded="false" title="Sort and filter Sale Qty"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
                          <th class="stock-excel-heading"><span>Available Stock</span><button type="button" class="stock-excel-trigger" data-stock-kind="stock" data-stock-column="3" data-stock-label="Available Stock" aria-label="Sort and filter Available Stock" aria-haspopup="dialog" aria-expanded="false" title="Sort and filter Available Stock"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
                        </tr>

                      </thead>
                      <tbody id="stockTbody"></tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- ===================== ITEMS VIEW ===================== -->
        <section id="view-items" class="pv-view d-none">
          <div class="row g-3">
            <div class="col-12">
              <div class="pv-card">
                <div class="pv-cardhead">
                  <div>
                    <h2>Items</h2>
                    <div class="pv-small pv-muted">
                      Used across Entry + Billing + Stock.
                    </div>
                  </div>
                  <span class="pv-chip" id="itemCountChip">0 total</span><button type="button" class="btn btn-primary btn-sm" id="stockNewItem" data-stock-modal="#stockItemCreateModal"><i class="bi bi-plus-lg"></i> New Item</button>
                </div>
                <div class="p-3">
                  <div class="border rounded-3 bg-light p-3 mb-3">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                      <div>
                        <strong>Excel import / export</strong>
                        <div class="pv-small pv-muted">Excel (.xlsx) aur CSV import/export available hain. Existing items sirf stable Item ID se match honge; stock balances aur transaction history change nahi hogi.</div>
                      </div>
                      <div class="d-flex gap-2">
                        <a class="btn btn-outline-primary btn-sm" href="{{ route('stocks.items.export', ['format' => 'xlsx']) }}"><i class="bi bi-download me-1"></i>Export all Excel</a>
                        <a class="btn btn-outline-primary btn-sm disabled" id="exportSelectedItems" href="{{ route('stocks.items.export', ['format' => 'xlsx']) }}" aria-disabled="true"><i class="bi bi-check2-square me-1"></i>Export selected Excel</a>
                      </div>
                    </div>
                    <form id="itemImportForm" class="row g-2 align-items-end mt-2">
                      <div class="col-md-8">
                        <label class="form-label pv-small" for="itemImportFile">Import Excel or CSV</label>
                        <input class="form-control form-control-sm" id="itemImportFile" name="file" type="file" accept=".xlsx,.csv,.txt,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required>
                      </div>
                      <div class="col-md-4 d-grid">
                        <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-upload me-1"></i>Preview Import</button>
                      </div>
                    </form>
                    <div id="itemImportPreview" class="mt-3" hidden></div>
                  </div>
                  <div class="stock-items-side-by-side"><div class="stock-import-history"><div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2"><strong>Recent import history</strong><span class="pv-small pv-muted">Latest 25 uploads</span></div>
                    <div class="table-responsive pv-scroll"><table class="table table-sm table-bordered align-middle mb-0"><thead><tr><th>Batch</th><th>Date</th><th>By</th><th>Status</th><th>Added / Updated / Errors</th><th></th></tr>
</thead><tbody id="itemImportHistoryBody"><tr><td colspan="6" class="text-muted text-center">Loading history...</td></tr></tbody></table></div>
                  </div></div><div class="stock-items-register"><div class="table-responsive pv-scroll">
                    <table class="table table-hover align-middle mb-0">
                      <thead>
                        <tr>
                          <th><input type="checkbox" id="selectAllStockItems" aria-label="Select all listed items"></th>
                          <th class="stock-excel-heading"><span>Sr</span><button type="button" class="stock-excel-trigger" data-stock-kind="item" data-stock-column="1" data-stock-label="Sr" aria-label="Sort and filter Sr" aria-haspopup="dialog" aria-expanded="false" title="Sort and filter Sr"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
                          <th class="stock-excel-heading"><span>Item</span><button type="button" class="stock-excel-trigger" data-stock-kind="item" data-stock-column="2" data-stock-label="Item" aria-label="Sort and filter Item" aria-haspopup="dialog" aria-expanded="false" title="Sort and filter Item"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
                          <th class="stock-excel-heading"><span>Unit</span><button type="button" class="stock-excel-trigger" data-stock-kind="item" data-stock-column="3" data-stock-label="Unit" aria-label="Sort and filter Unit" aria-haspopup="dialog" aria-expanded="false" title="Sort and filter Unit"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
                          <th class="stock-excel-heading"><span>Default Rate</span><button type="button" class="stock-excel-trigger" data-stock-kind="item" data-stock-column="4" data-stock-label="Default Rate" aria-label="Sort and filter Default Rate" aria-haspopup="dialog" aria-expanded="false" title="Sort and filter Default Rate"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
                          <th class="text-end">Actions</th>
                        </tr>
                      </thead>
                      <tbody id="itemTbody"></tbody>
                    </table>
                  </div></div></div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- ===================== PARTIES VIEW ===================== -->
        <section id="view-parties" class="pv-view d-none">
          <div class="row g-3">
            <div class="col-12">
              <div class="pv-card">
                <div class="pv-cardhead">
                  <div>
                    <h2>Parties</h2>
                    <div class="pv-small pv-muted">
                      Used for Purchase/Sale/Stock Out.
                    </div>
                  </div>
                  <span class="pv-chip" id="partyCountChip">0 total</span><button type="button" class="btn btn-primary btn-sm" id="stockNewParty" data-stock-modal="#stockPartyCreateModal"><i class="bi bi-plus-lg"></i> New Party</button>
                </div>
                <div class="p-3">
                  <div class="table-responsive pv-scroll">
                    <table class="table table-hover align-middle mb-0">
                      <thead>
                        <tr>
                          <th class="stock-excel-heading"><span>Sr</span><button type="button" class="stock-excel-trigger" data-stock-kind="party" data-stock-column="0" data-stock-label="Sr" aria-label="Sort and filter Sr" aria-haspopup="dialog" aria-expanded="false" title="Sort and filter Sr"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
                          <th class="stock-excel-heading"><span>Vendor Code</span><button type="button" class="stock-excel-trigger" data-stock-kind="party" data-stock-column="1" data-stock-label="Vendor Code" aria-label="Sort and filter Vendor Code" aria-haspopup="dialog" aria-expanded="false" title="Sort and filter Vendor Code"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
                          <th class="stock-excel-heading"><span>Name</span><button type="button" class="stock-excel-trigger" data-stock-kind="party" data-stock-column="2" data-stock-label="Name" aria-label="Sort and filter Name" aria-haspopup="dialog" aria-expanded="false" title="Sort and filter Name"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
                          <th class="stock-excel-heading"><span>Mobile</span><button type="button" class="stock-excel-trigger" data-stock-kind="party" data-stock-column="3" data-stock-label="Mobile" aria-label="Sort and filter Mobile" aria-haspopup="dialog" aria-expanded="false" title="Sort and filter Mobile"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
                          <th class="stock-excel-heading"><span>Address</span><button type="button" class="stock-excel-trigger" data-stock-kind="party" data-stock-column="4" data-stock-label="Address" aria-label="Sort and filter Address" aria-haspopup="dialog" aria-expanded="false" title="Sort and filter Address"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
                          <th class="stock-excel-heading"><span>Head</span><button type="button" class="stock-excel-trigger" data-stock-kind="party" data-stock-column="5" data-stock-label="Head" aria-label="Sort and filter Head" aria-haspopup="dialog" aria-expanded="false" title="Sort and filter Head"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
                          <th class="stock-excel-heading"><span>Sub Head</span><button type="button" class="stock-excel-trigger" data-stock-kind="party" data-stock-column="6" data-stock-label="Sub Head" aria-label="Sort and filter Sub Head" aria-haspopup="dialog" aria-expanded="false" title="Sort and filter Sub Head"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
                          <th class="stock-excel-heading"><span>Type</span><button type="button" class="stock-excel-trigger" data-stock-kind="party" data-stock-column="7" data-stock-label="Type" aria-label="Sort and filter Type" aria-haspopup="dialog" aria-expanded="false" title="Sort and filter Type"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
                          <th class="stock-excel-heading"><span>Status</span><button type="button" class="stock-excel-trigger" data-stock-kind="party" data-stock-column="8" data-stock-label="Status" aria-label="Sort and filter Status" aria-haspopup="dialog" aria-expanded="false" title="Sort and filter Status"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
                          <th class="text-end">Actions</th>
                        </tr>

                      </thead>
                      <tbody id="partyTbody"></tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>
      <!-- /pvViews -->
    </main>
  </div></div>

  <div class="modal fade stock-create-modal" id="stockEntryCreateModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content pv-card"><div class="modal-header"><h5 class="modal-title"><span id="entryTitle">Purchase Entry</span></h5><button class="stock-modal-close" type="button" data-bs-dismiss="modal" aria-label="Close"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div><div class="modal-body"><div class="stock-entry-mode d-flex gap-3 mb-2"><button type="button" class="btn btn-outline-primary" id="btnPurchaseMode">Purchase</button><button type="button" class="btn btn-outline-secondary" id="btnStockOutMode">Stock Out</button></div><form id="entryForm" novalidate>
                    <div class="row g-2">
                      <div class="col-md-6">
                        <label class="form-label pv-small">Slip No (auto)</label>
                        <input type="text" class="form-control" id="entrySlipNo" readonly />
                      </div>
                      <div class="col-md-6">
                        <label class="form-label pv-small">Date</label>
                        <input type="date" class="form-control" id="entryDate" required />
                      </div>

                      <div class="col-md-6">
                        <label class="form-label pv-small">Vehicle No</label>
                        <input type="text" class="form-control" id="entryVehicle" placeholder="e.g., LEE-123" />
                      </div>
                      <div class="col-md-6">
                        <label class="form-label pv-small">Driver</label>
                        <input type="text" class="form-control" id="entryDriver" placeholder="e.g., Ali" />
                      </div>

                      <div class="col-12">
                        <label class="form-label pv-small" id="entryPartyLabel">Shop / Party</label>
                        <select class="form-select" id="entryParty" required><option value="">Select a Shop/Party or Site/Customer</option></select>
                      </div>

                      <div class="col-12">
                        <label class="form-label pv-small" id="entryReasonLabel">Purchase Reason Details</label>
                        <textarea class="form-control" id="entryReason" rows="2"
                          placeholder="Write details..."></textarea>
                      </div>
                    </div>

                    <hr class="my-3" />

                    <div class="d-flex align-items-center justify-content-between mb-2">
                      <div class="fw-bold">Items / Material</div>
                      <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddLine">
                        <i class="bi bi-plus-lg"></i> Add Row
                      </button>
                    </div>

                    <div id="entryLines" class="vstack gap-2"></div>

                    <div class="pv-line mt-3">
                      <div class="d-flex align-items-center justify-content-between">
                        <div class="pv-small pv-muted">Total Amount</div>
                        <div class="fw-bold" id="entryTotal">PKR 0</div>
                      </div>
                    </div>

                    <div class="stock-modal-actions mt-2">
                      <button type="submit" class="btn btn-primary btn-lg" id="entrySubmitBtn">
                        <i class="bi bi-check2-circle me-1"></i> Save Entry
                      </button>
                      <div class="pv-small pv-muted mt-2">
                        Saves to this project's stock records. Entries refresh here without a page reload.
                      </div>
                    </div>
                  </form></div></div></div></div>
<div class="modal fade stock-create-modal" id="stockBillCreateModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content pv-card"><div class="modal-header"><h5 class="modal-title">Purchase / Sale Bill <span class="pv-chip" id="billNoChip">Bill no… auto</span></h5><button class="stock-modal-close" type="button" data-bs-dismiss="modal" aria-label="Close"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div><div class="modal-body"><form id="billForm" novalidate>
                    <div class="row g-2">
                      <div class="col-md-6">
                        <label class="form-label pv-small">Selection</label>
                        <select class="form-select" id="billType" required>
                          <option value="purchase">Purchase Bill</option>
                          <option value="sale">Sale Bill</option>
                        </select>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label pv-small">Date</label>
                        <input type="date" class="form-control" id="billDate" required />
                      </div>

                      <div class="col-12">
                        <label class="form-label pv-small" id="billPartyLabel">Supplier / Site Name</label>
                        <select class="form-select" id="billParty" required><option value="">Select a Supplier or Site</option></select>
                      </div>

                      <div class="col-md-6">
                        <label class="form-label pv-small">Entry No (optional)</label>
                        <input class="form-control" id="billEntryNo" placeholder="Link to entry..." />
                      </div>
                      <div class="col-md-6">
                        <label class="form-label pv-small">Bill No (auto)</label>
                        <input class="form-control" id="billNo" readonly />
                      </div>
                    </div>

                    <hr class="my-3" />

                    <div class="d-flex align-items-center justify-content-between mb-2">
                      <div class="fw-bold">Bill Items</div>
                      <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddBillLine">
                        <i class="bi bi-plus-lg"></i> Add Row
                      </button>
                    </div>

                    <div id="billLines" class="vstack gap-2"></div>

                    <div class="pv-line mt-3">
                      <div class="d-flex align-items-center justify-content-between">
                        <div class="pv-small pv-muted">Total</div>
                        <div class="fw-bold" id="billTotal">PKR 0</div>
                      </div>
                    </div>

                    <div class="stock-modal-actions mt-2">
                      <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-check2-circle me-1"></i> Save Bill
                      </button>
                    </div>
                  </form></div></div></div></div>
<div class="modal fade stock-create-modal" id="stockItemCreateModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content pv-card"><div class="modal-header"><h5 class="modal-title">New Item / Material</h5><button class="stock-modal-close" type="button" data-bs-dismiss="modal" aria-label="Close"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div><div class="modal-body"><form id="itemForm" novalidate>
                    <div class="row g-2">
                      <div class="col-12">
                        <label class="form-label pv-small">Item Name</label>
                        <input class="form-control" id="itemName" placeholder="e.g., Cement" required />
                      </div>
                      <div class="col-md-6">
                        <label class="form-label pv-small">Unit</label>
                        <input class="form-control" id="itemUnit" placeholder="e.g., bag, kg, pcs" required />
                      </div>
                      <div class="col-md-6">
                        <label class="form-label pv-small">Default Rate (PKR)</label>
                        <input class="form-control pv-money" id="itemRate" placeholder="e.g., 1,250"
                          inputmode="numeric" />
                      </div>
                    </div>

                    <div class="stock-modal-actions mt-2">
                      <button class="btn btn-primary btn-lg" type="submit">
                        <i class="bi bi-plus-circle me-1"></i> Add Item
                      </button>
                    </div>
                  </form></div></div></div></div>
<div class="modal fade stock-create-modal" id="stockPartyCreateModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content pv-card"><div class="modal-header"><h5 class="modal-title">New Stock Party</h5><button class="stock-modal-close" type="button" data-bs-dismiss="modal" aria-label="Close"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div><div class="modal-body"><form id="partyForm" novalidate>
                    <div class="row g-2">
                      <div class="col-md-6">
                        <label class="form-label pv-small">Supplier / Consumer</label>
                        <select class="form-select" id="partyType" required>
                          <option value="Supplier">Supplier</option>
                          <option value="Consumer">Consumer</option>
                          <option value="Site">Site</option>
                        </select>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label pv-small">Mobile</label>
                        <input class="form-control" id="partyMobile" placeholder="03xx...." />
                      </div>
                      <div class="col-12">
                        <label class="form-label pv-small">Name</label>
                        <input class="form-control" id="partyName" placeholder="e.g., ABC Supplier" required />
                      </div>
                      <div class="col-12">
                        <label class="form-label pv-small">Address</label>
                        <input class="form-control" id="partyAddress" placeholder="Address..." />
                      </div>
                      <div class="col-md-6">
                        <label>Head Accounts</label>
                        <select class="form-control select2" name="accounts_id" id="accounts_id">
                          <option value="">Select Head</option>
                          @foreach (collect($partyAccountOptions ?? [])->unique('head_id') as $head)
                            <option value="{{ $head['head_id'] }}">
                              {{ $head['head_name'] }}
                            </option>
                          @endforeach
                        </select>
                      </div>
                      <div class="col-md-6">
                        <label>Sub Head Accounts</label>
                        <select class="form-control select2" name="subaccounts_id" id="subaccounts_id">
                          <option value="">Select Sub Head</option>
                        </select>
                      </div>
                    </div>

                    <div class="stock-modal-actions mt-2">
                      <button class="btn btn-primary btn-lg" type="submit">
                        <i class="bi bi-person-plus me-1"></i> Add Party
                      </button>
                    </div>
                  </form></div></div></div></div>
<div id="stockExcelPopover" class="stock-excel-popover d-none" role="dialog" aria-label="Stock column sort and filter">
  <strong id="stockExcelTitle" class="d-block mb-2"></strong>
  <button type="button" class="stock-excel-sort" data-direction="asc"><i class="fas fa-sort-amount-up me-2"></i>Sort A to Z / Small to Large</button>
  <button type="button" class="stock-excel-sort" data-direction="desc"><i class="fas fa-sort-amount-down me-2"></i>Sort Z to A / Large to Small</button>
  <hr class="my-2">
  <div id="stockExcelDateFields" class="d-none"><label class="small fw-semibold" for="stockExcelFrom">From Date</label><input type="date" id="stockExcelFrom" class="form-control form-control-sm"><label class="small fw-semibold mt-2" for="stockExcelTo">To Date</label><input type="date" id="stockExcelTo" class="form-control form-control-sm"></div>
  <div id="stockExcelChoicesWrap"><input type="search" id="stockExcelSearch" class="form-control form-control-sm mb-2" placeholder="Search values..." autocomplete="off"><div id="stockExcelValues" class="stock-excel-values" role="group" aria-label="Filter choices"></div></div>
  <div class="d-flex gap-2 justify-content-end mt-2"><button type="button" class="btn btn-sm btn-light" id="stockExcelClear">Clear</button><button type="button" class="btn btn-sm btn-light" id="stockExcelCancel">Cancel</button></div>
</div>
<!-- Modals (edit item/party) -->
  <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
      <div class="modal-content pv-card" style="border-radius: 22px">
        <div class="pv-cardhead">
          <div>
            <h2 id="editModalTitle">Edit</h2>
            <div class="pv-small pv-muted" id="editModalSub">
              Edit this project's record. Existing linked history is preserved.
            </div>
          </div>
          <button type="button" class="btn btn-light" data-bs-dismiss="modal" aria-label="Close"
            style="border-radius: 14px">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
        <div class="p-3">
          <form id="editForm"></form>
        </div>
      </div>
    </div>
  </div>
  <div class="modal fade" id="stockDocumentEditModal" tabindex="-1" aria-labelledby="stockDocumentEditTitle" aria-hidden="true">
   <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content pv-card">
     <div class="modal-header"><h5 class="modal-title" id="stockDocumentEditTitle">Edit Stock Voucher</h5>
       <button class="stock-modal-close" type="button" data-bs-dismiss="modal" aria-label="Close"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>
     <form id="stockDocumentEditForm">
      <div class="modal-body">
       <div class="alert alert-info small">The original stock document, inventory movements and ledger IDs remain linked.</div>
       <div class="row g-2">
        <div class="col-md-4"><label for="stockDocumentDate" class="form-label">Date</label>
         <input type="date" class="form-control" id="stockDocumentDate" required></div>
        <div class="col-md-8"><label for="stockDocumentNarration" class="form-label">Description / Narration</label>
         <input type="text" class="form-control" id="stockDocumentNarration" maxlength="1000"></div>
       </div>
       <div id="stockDocumentEditRows" class="mt-3"></div>
       <button type="button" class="btn btn-sm btn-outline-primary" id="stockDocumentAddRow">Add Item</button>
       <div id="stockDocumentEditError" class="alert alert-danger d-none mt-2" role="alert"></div>
      </div>
      <div class="modal-footer">
       <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
       <button type="submit" id="stockDocumentSave" class="btn btn-primary">Update Stock Voucher</button>
      </div>
     </form>
    </div>
   </div>
  </div>
  <div class="modal fade" id="stockDocumentDeleteModal" tabindex="-1" aria-labelledby="stockDocumentDeleteTitle" aria-hidden="true">
   <div class="modal-dialog modal-dialog-centered"><div class="modal-content pv-card">
    <div class="modal-header"><h5 id="stockDocumentDeleteTitle" class="modal-title">Delete Stock Voucher</h5>
     <button type="button" class="stock-modal-close" data-bs-dismiss="modal" aria-label="Close"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>
    <div class="modal-body">
     <p>Archive this stock voucher from active inventory and balances? The original source and movement IDs remain preserved.</p>
     <div id="stockDocumentDeleteError" class="alert alert-danger d-none" role="alert"></div>
    </div>
    <div class="modal-footer">
     <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
     <button type="button" id="stockDocumentDeleteConfirm" class="btn btn-danger">Delete Voucher</button>
    </div>
   </div></div>
  </div>
  {{-- Attendance and stock data are loaded from the project-scoped APIs below. --}}
  <script type="application/json" id="initial-attendance">[]</script>
@endsection

@section('js')
  <!-- Searchable dropdowns shared across Stock create/edit modals. -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css">
  <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


  <script>
    const partyAccountOptions = @json($partyAccountOptions ?? []);
    const endpoints = {
      meta: "{{ route('stocks.meta') }}",
      nextSlip: "{{ route('stocks.nextSlip') }}",
      nextBill: "{{ route('stocks.nextBill') }}",
      items: "{{ route('stocks.items.index') }}",
      parties: "{{ route('stocks.parties.index') }}",
      entries: "{{ route('stocks.entries.index') }}",
      bills: "{{ route('stocks.bills.index') }}",
      report: "{{ route('stocks.report') }}",
      storeItem: "{{ route('stocks.items.store') }}",
      exportItems: "{{ route('stocks.items.export') }}",
      storeParty: "{{ route('stocks.parties.store') }}",
      storeEntry: "{{ route('stocks.entries.store') }}",
      storeBill: "{{ route('stocks.bills.store') }}",
      updateItem: "{{ route('stocks.items.update', ['item' => 0]) }}",
      deleteItem: "{{ route('stocks.items.delete', ['item' => 0]) }}",
      updateParty: "{{ route('stocks.parties.update', ['party' => 0]) }}",
      partyStatus: "{{ route('stocks.parties.status', ['party' => 0]) }}",
      deleteParty: "{{ route('stocks.parties.delete', ['party' => 0]) }}",
      stageItemImport: "{{ route('stocks.items.import.stage') }}",
      applyItemImport: "{{ route('stocks.items.import.apply', ['batch' => 0]) }}",
      itemImportErrors: "{{ route('stocks.items.import.errors', ['batch' => 0]) }}",
      itemImportPreview: "{{ route('stocks.items.import.preview', ['batch' => 0]) }}",
      itemImportHistory: "{{ route('stocks.items.import.history') }}",
    };

    const state = {
      items: [],
      parties: [],
      entries: { rows: [], total: 0, kpi: { in_qty: 0, out_qty: 0, lines: 0 } },
      bills: {
        rows: [],
        total: 0,
        kpi: { purchase_count: 0, sale_count: 0, total_amount: 0 },
      },
      report: { rows: [], kpi: { items: 0, in_qty: 0, out_qty: 0, available: 0 } },
      itemsTable: { rows: [], total: 0 },
      partiesTable: { rows: [], total: 0 },
    };

    const csrfToken = $('meta[name="csrf-token"]').attr('content');

    async function apiGet(url, params = {}) {
      try {
        return await $.ajax({
          url, method: 'GET', dataType: 'json',
          data: Object.fromEntries(Object.entries(params).filter(([,v])=>v!==null&&v!=='')),
          headers: {Accept:'application/json'}
        });
      } catch(xhr) {
        throw {status:xhr.status, data:xhr.responseJSON || {}};
      }
    }

    async function apiPost(url, payload = {}) {
      return apiSend(url, 'POST', payload);
    }

    async function apiSend(url, method, payload = null) {
      try {
        return await $.ajax({
          url, method, dataType:'json',
          data: payload === null ? undefined : JSON.stringify(payload),
          processData:false,
          contentType:'application/json',
          headers: {Accept:'application/json','X-CSRF-TOKEN':csrfToken || ''}
        });
      } catch(xhr) {
        throw {status:xhr.status, data:xhr.responseJSON || {}};
      }
    }

    const importHtml = (value) => String(value ?? "").replace(/[&<>"']/g, (char) => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;"
    }[char]));

    $('#itemImportForm').on("submit", async (event) => {
      event.preventDefault();
      const form = event.currentTarget;
      const file = $('#itemImportFile')[0]?.files?.[0];
      if (!file) return;
      const button = $(form).find('button[type="submit"]')[0];
      const preview = $('#itemImportPreview')[0];
      button.disabled = true;
      button.textContent = "Uploading and validating...";
      preview.hidden = false;
      $(preview).html('<div class="placeholder-glow"><span class="placeholder col-12"></span><span class="placeholder col-7"></span></div>');
      try {
        const body = new FormData();
        body.append("file", file);
        let result;
        try {
          result = await $.ajax({
            url:endpoints.stageItemImport, method:'POST',
            data:body, dataType:'json', contentType:false, processData:false,
            headers:{Accept:'application/json','X-CSRF-TOKEN':csrfToken || ''}
          });
        } catch(xhr) {
          throw {status:xhr.status,data:xhr.responseJSON || {}};
        }
        renderItemImportPreview(result.data);
        await loadItemImportHistory();
      } catch (error) {
        const message = error?.data?.message || error?.data?.errors?.file?.[0] || "Could not stage this CSV import.";
        $(preview).html(`<div class="alert alert-danger mb-0">${importHtml(message)}</div>`);
      } finally {
        button.disabled = false;
        $(button).html('<i class="bi bi-upload me-1"></i>Preview Import');
      }
    });

    function renderItemImportPreview(data) {
      const preview = $('#itemImportPreview')[0];
      const counts = data?.counts || {};
      const rows = data?.rows || [];
      const failed = Number(counts.failed || 0);
      const rowsHtml = rows.map((row) => {
        const errors = (row.errors || []).join("; ");
        return `<tr><td>${Number(row.row_number) || ""}</td><td>${importHtml(row.item?.item_id || "New")}</td><td>${importHtml(row.item?.name)}</td><td>${importHtml(row.action)}</td><td class="text-danger">${importHtml(errors)}</td></tr>`;
      }).join("");
      const applyUrl = endpoints.applyItemImport.replace(/0(?=\D*$)/, encodeURIComponent(data.batch_id));
      const errorsUrl = endpoints.itemImportErrors.replace(/0(?=\D*$)/, encodeURIComponent(data.batch_id));
      preview.hidden = false;
      $(preview).html(`
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
          <div><strong>Import preview</strong><div class="pv-small pv-muted">${Number(counts.total || 0)} rows · ${Number(counts.added || 0)} new · ${Number(counts.updated || 0)} updates · ${Number(counts.skipped || 0)} skipped · ${failed} errors</div></div>
          <div class="d-flex gap-2">${failed ? `<a class="btn btn-outline-danger btn-sm" href="${errorsUrl}">Download errors</a>` : ""}<button class="btn btn-success btn-sm" id="applyItemImport" ${failed || data.status !== "staged" ? "disabled" : ""}>Confirm and apply</button></div>
        </div>
        <div class="table-responsive" style="max-height:280px"><table class="table table-sm table-bordered align-middle mb-0"><thead><tr><th>Row</th><th>Item ID</th><th>Name</th><th>Action</th><th>Validation</th></tr></thead><tbody>${rowsHtml}</tbody></table></div>`);
      $('#applyItemImport').on("click", async (event) => {
        const button = event.currentTarget;
        const confirmation = await Swal.fire({ icon: "warning", title: "Apply item import?", text: "Only item names, units and default rates will change. Stock balances and transaction history will remain unchanged.", showCancelButton: true, confirmButtonText: "Apply import" });
        if (!confirmation.isConfirmed) return;
        button.disabled = true;
        button.textContent = "Applying...";
        try {
          const applied = await apiPost(applyUrl, { confirmed: true });
          viewToast("success", "Import applied", `${applied.data?.added || 0} added, ${applied.data?.updated || 0} updated.`);
          $(preview).html('<div class="alert alert-success mb-0">Import applied. Item master refreshed; stock history was not changed.</div>');
          await loadMeta();
          await loadItemsTable();
          await loadItemImportHistory();
        } catch (error) {
          handleApiError(error, "Unable to apply stock item import.");
          button.disabled = false;
          button.textContent = "Confirm and apply";
        }
      });
    }
    let stockSubheadRequest = null;
    function stockSetSubheads(items,placeholder){
      const $select=$('#subaccounts_id'),el=$select[0],selected='';
      if(!el)return;
      if(el.tomselect){
        el.tomselect.clear(true);
        el.tomselect.clearOptions();
        el.tomselect.addOption({value:'',text:placeholder||'Select Sub Head'});
        $.each(items,function(_,item){el.tomselect.addOption({value:String(item.value),text:String(item.name)});});
        el.tomselect.refreshOptions(false);
      }else{
        $select.empty().append($('<option>').val('').text(placeholder||'Select Sub Head'));
        $.each(items,function(_,item){$select.append($('<option>').val(item.value).text(item.name));});
        $select.trigger('change');
      }
    }
    $(document).on('change', '#accounts_id', function(){
      const accountID=$(this).val();
      if(stockSubheadRequest && stockSubheadRequest.readyState!==4)stockSubheadRequest.abort();
      stockSetSubheads([],accountID?'Loading subheads...':'Select Sub Head');
      if(!accountID)return;
      stockSubheadRequest=$.ajax({
        url: @json(route('get_account')),
        method:'POST',dataType:'json',
        headers:{Accept:'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
        data:{projectID:{{ getSelectedTown() }},accountID:accountID,action:'get_child',active_only:true}
      }).done(function(response){
        // Laravel endpoints may return a plain list or a {data:[...]} resource.
        const list=$.isArray(response)?response:($.isArray(response?.data)?response.data:($.isArray(response?.results)?response.results:[]));
        const rows=$.map(list,function(item){
          const nested=item.subhead_accounting||item.subheadAccounting||item.subhead||{};
          // get_child returns ProjectHeadSubhead rows: id is a mapping id,
          // while StockParty::storeParty expects the subhead_accounting_id.
          if(item.plot_id!=null || item.customer_id!=null)return null;
          const name=nested.name||item.subhead_name;
          const value=item.subhead_accounting_id??nested.id;
          return name&&value!=null?{value:String(value),name:String(name)}:null;
        });
        stockSetSubheads(rows,rows.length?'Select Sub Head':'No active subheads found');
      }).fail(function(xhr,status){
        if(status==='abort')return;
        stockSetSubheads([],'Unable to load subheads');
        handleApiError({data:xhr.responseJSON||{}},'Unable to load Sub Head Accounts.');
      });
    });
    function withId(templateUrl, id) {
      return templateUrl.replace(/\/0$/, `/${id}`);
    }

    function firstValidationError(errors) {
      if (!errors) return null;
      const keys = Object.keys(errors);
      if (!keys.length) return null;
      return errors[keys[0]]?.[0] || null;
    }

    function handleApiError(err, fallbackMessage) {
      const data = err?.data || {};
      if (data?.key === "validation_failed") {
        const msg = firstValidationError(data.errors) || "Validation failed.";
        Swal.fire({ icon: "error", title: "Validation", text: msg });
        return;
      }
      if (data?.key === "insufficient_stock") {
        Swal.fire({ icon: "error", title: "Stock", text: data.message || "Insufficient stock." });
        return;
      }
      Swal.fire({
        icon: "error",
        title: "Error",
        text: data?.message || fallbackMessage || "Something went wrong.",
      });
    }

    function debounce(fn, delay) {
      let t;
      return (...args) => {
        clearTimeout(t);
        t = setTimeout(() => fn(...args), delay);
      };
    }

    /* ===========================
  UTIL
  =========================== */
    const fmtInt = (n) =>
      new Intl.NumberFormat("en-US", { maximumFractionDigits: 0 }).format(
        Number(n || 0)
      );
    const fmtPKR = (n) => "PKR " + fmtInt(n);

    function pvFormatAmountNoDecimal(input) {
      if (!input) return;
      let raw = String(input.value ?? "");
      raw = raw.split(".")[0];
      raw = raw.replace(/[^0-9]/g, "");
      raw = raw.replace(/^0+(?=\d)/, "");
      raw = raw.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
      input.value = raw;
    }

    function pvParseIntAmount(v) {
      v = String(v ?? "")
        .split(".")[0]
        .replace(/[^0-9]/g, "");
      return v ? parseInt(v, 10) || 0 : 0;
    }

    function todayISO() {
      const d = new Date();
      const pad = (x) => String(x).padStart(2, "0");
      return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(
        d.getDate()
      )}`;
    }
    function daysAgoISO(days) {
      const d = new Date();
      d.setDate(d.getDate() - days);
      const pad = (x) => String(x).padStart(2, "0");
      return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(
        d.getDate()
      )}`;
    }

    function findItem(id) {
      return state.items.find((x) => x.id === Number(id));
    }
    function findParty(id) {
      return state.parties.find((x) => x.id === Number(id));
    }

    function viewToast(icon, title, text) {
      return Swal.fire({
        icon,
        title,
        text,
        timer: 1400,
        showConfirmButton: false,
        toast: true,
        position: "top-end",
      });
    }

    /* ===========================
  VIEW SWITCH
  =========================== */
    const tabBtns = $(".pv-tabbtn").toArray();
    const views = {
      entry: $('#view-entry')[0],
      billing: $('#view-billing')[0],
      stock: $('#view-stock')[0],
      items: $('#view-items')[0],
      parties: $('#view-parties')[0],
    };
    function setView(name) {
      tabBtns.forEach((b) =>
        $(b).toggleClass("active", b.dataset.view === name)
      );
      Object.entries(views).forEach(([k, el]) =>
        $(el).toggleClass("d-none", k !== name)
      );
    }

    /* ===========================
  SELECTS POPULATION
  =========================== */
    function fillPartySelect(sel, withAll = false) {
      const placeholder = withAll ? "All" : (sel.id === "billParty" ? "Select a Supplier or Site" : "Select a Shop/Party or Site/Customer");
      $(sel).html(`<option value="">${placeholder}</option>`);
      if (withAll)
        sel.options[0].textContent = "All";
      state.parties.forEach((p) => {
        $(sel).append(
          `<option value="${p.id}">${p.name} • ${p.type}</option>`
        );
      });
    }
    function fillItemSelect(sel, withAll = false) {
      $(sel).html("");
      if (withAll)
        $(sel).append( `<option value="">All</option>`);
      state.items.forEach((i) => {
        $(sel).append(
          `<option value="${i.id}">${i.name} (${i.unit})</option>`
        );
      });
      if (!state.items.length) {
        $(sel).append( `<option value="">No items</option>`);
      }
    }

    function refreshLineItemSelects() {
      $('.line-item').each((_,sel) => {
        const current = sel.value;
        fillItemSelect(sel, false);
        if (current) sel.value = current;
        sel.dispatchEvent(new Event("change"));
      });
    }

    /* ===========================
  ENTRY MODE (Purchase / Stock Out)
  =========================== */
    let entryMode = "purchase"; // purchase | stockout
    const entryTitle = $('#entryTitle')[0];
    const entryPartyLabel = $('#entryPartyLabel')[0];
    const entryReasonLabel = $('#entryReasonLabel')[0];
    const btnPurchaseMode = $('#btnPurchaseMode')[0];
    const btnStockOutMode = $('#btnStockOutMode')[0];

    function setEntryMode(mode) {
      entryMode = mode;
      const isPurchase = mode === "purchase";
      entryTitle.textContent = isPurchase
        ? "Purchase Entry"
        : "Stock Out Entry";
      entryPartyLabel.textContent = isPurchase
        ? "Shop / Party"
        : "Site & Customer";
      entryReasonLabel.textContent = isPurchase
        ? "Purchase Reason Details"
        : "Using Reason Details";
      $(btnPurchaseMode).toggleClass("btn-outline-primary", !isPurchase);
      $(btnPurchaseMode).toggleClass("btn-primary", isPurchase);
      $(btnStockOutMode).toggleClass("btn-outline-secondary", isPurchase);
      $(btnStockOutMode).toggleClass("btn-secondary", !isPurchase);
    }
    $(btnPurchaseMode).on("click", () => setEntryMode("purchase"));
    $(btnStockOutMode).on("click", () => setEntryMode("stockout"));

    /* ===========================
  ENTRY LINES
  =========================== */
    const entryLinesWrap = $('#entryLines')[0];
    const entryTotalEl = $('#entryTotal')[0];

    function makeLineRow(prefix, idx) {
      const lineId = `${prefix}_line_${idx}_${Math.random()
        .toString(16)
        .slice(2)}`;
      const html = `
        <div class="pv-line stock-compact-line" data-line="${lineId}">
          <div class="row g-2 align-items-center">
            <div class="col-md-5"><div class="stock-line-field"><span class="stock-line-caption">Item</span><select class="form-select line-item" aria-label="Item" required></select></div></div>
            <div class="col-md-2"><div class="stock-line-field"><span class="stock-line-caption">Unit</span><input class="form-control line-unit" aria-label="Unit" readonly value="—"></div></div>
            <div class="col-md-2"><div class="stock-line-field"><span class="stock-line-caption">Qty</span><input class="form-control line-qty" aria-label="Quantity" inputmode="numeric" placeholder="0" required></div></div>
            <div class="col-md-2"><div class="stock-line-field"><span class="stock-line-caption">Rate</span><input class="form-control line-rate pv-money" aria-label="Rate" inputmode="numeric" placeholder="0"></div></div>
            <div class="col-md-1 d-flex align-items-center justify-content-center"><button type="button" class="stock-line-remove" title="Remove row" aria-label="Remove item row"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>
          </div>
          <div class="stock-line-summary"><span class="pv-small pv-muted">Line Amount</span><strong class="line-amount">PKR 0</strong></div>
        </div>
      `;
      const temp = $('<div>')[0];
      $(temp).html(html.trim());
      const row = temp.firstElementChild;

      const itemSel = $(row).find(".line-item")[0];
      fillItemSelect(itemSel, false);

      // default first item
      if (state.items.length) itemSel.value = state.items[0].id;

      const unitEl = $(row).find(".line-unit")[0];
      const qtyEl = $(row).find(".line-qty")[0];
      const rateEl = $(row).find(".line-rate")[0];
      const amtEl = $(row).find(".line-amount")[0];

      function syncFromItem() {
        const it = findItem(itemSel.value);
        unitEl.value = it ? it.unit : "—";
        if (it && (!rateEl.value || pvParseIntAmount(rateEl.value) === 0)) {
          rateEl.value = fmtInt(it.default_rate || 0);
          pvFormatAmountNoDecimal(rateEl);
        }
        recalc();
      }

      function recalc() {
        const qty = pvParseIntAmount(qtyEl.value);
        const rate = pvParseIntAmount(rateEl.value);
        const amt = qty * rate;
        amtEl.textContent = fmtPKR(amt);
        if (prefix === "entry") recalcEntryTotal();
        if (prefix === "bill") recalcBillTotal();
      }

      $(itemSel).on("change", syncFromItem);
      $(qtyEl).on("input", () => {
        qtyEl.value = qtyEl.value.replace(/[^0-9]/g, "");
        recalc();
      });
      $(rateEl).on("input", () => {
        pvFormatAmountNoDecimal(rateEl);
        recalc();
      });
      $(row).find("button").on("click", () => {
        row.remove();
        recalcEntryTotal();
      });

      // initial sync
      syncFromItem();

      return row;
    }

    function recalcEntryTotal() {
      let total = 0;
      $(entryLinesWrap).find(".pv-line").toArray().forEach((row) => {
        const qty = pvParseIntAmount($(row).find(".line-qty")[0].value);
        const rate = pvParseIntAmount($(row).find(".line-rate")[0].value);
        total += qty * rate;
      });
      entryTotalEl.textContent = fmtPKR(total);
    }

    $('#btnAddLine').on("click", () => {
      $(entryLinesWrap).append(
        makeLineRow("entry", entryLinesWrap.children.length + 1)
      );
    });

    /* ===========================
  ENTRY SUBMIT
  =========================== */
    const entryForm = $('#entryForm')[0];
    const entrySlipNo = $('#entrySlipNo')[0];
    const entryDate = $('#entryDate')[0];
    const entryParty = $('#entryParty')[0];
    const entryReason = $('#entryReason')[0];
    const entryVehicle = $('#entryVehicle')[0];
    const entryDriver = $('#entryDriver')[0];


    function collectLines(wrap) {
      const lines = [];
      $(wrap).find(".pv-line").toArray().forEach((row) => {
        const itemId = Number($(row).find(".line-item")[0].value);
        const qtyRaw = String($(row).find(".line-qty")[0].value || "").replace(
          /[^0-9]/g,
          ""
        );
        const rate = pvParseIntAmount($(row).find(".line-rate")[0].value);
        if (!itemId || !qtyRaw || Number(qtyRaw) <= 0) return;
        lines.push({ item_id: itemId, qty: qtyRaw, rate });
      });
      return lines;
    }

    function entryFlow(mode) {
      return mode === "purchase" ? "IN" : "OUT";
    }

    $(entryForm).on("submit", async (e) => {
      e.preventDefault();

      if (!entryParty.value) {
        Swal.fire({
          icon: "error",
          title: state.parties.length ? "Select a party" : "No parties configured",
          text: state.parties.length ? "Choose a Shop/Party or Site/Customer from the list." : "Open Stock → Parties and add an active party for this project first.",
        });
        return;
      }

      const lines = collectLines(entryLinesWrap);
      if (!lines.length) {
        Swal.fire({
          icon: "error",
          title: "No items",
          text: "Add at least one item with Qty > 0.",
        });
        return;
      }

      $('#entrySubmitBtn')[0].disabled = true;
      try {
        const res = await apiPost(endpoints.storeEntry, {
          date: entryDate.value,
          party_id: Number(entryParty.value),
          vehicle: entryVehicle.value || "",
          driver: entryDriver.value || "",
          reason: entryReason.value || "",
          flow: entryFlow(entryMode),
          lines,
        });

        viewToast("success", "Saved", `Entry saved (${res?.data?.slip_no || ""}).`);
        stockModal('#stockEntryCreateModal','hide');

        entryParty.value = "";
        entryReason.value = "";
        entryVehicle.value = "";
        entryDriver.value = "";
        $(entryLinesWrap).html("");
        $(entryLinesWrap).append(makeLineRow("entry", 1));
        recalcEntryTotal();

        await loadNextSlip();
        await Promise.all([loadEntries(), loadReport()]);
      } catch (err) {
        handleApiError(err, "Unable to save entry.");
      } finally {
        $('#entrySubmitBtn')[0].disabled = false;
      }
    });


    /* ===========================
  BILLING LINES + SUBMIT
  =========================== */
    const billForm = $('#billForm')[0];
    const billType = $('#billType')[0];
    const billDate = $('#billDate')[0];
    const billParty = $('#billParty')[0];
    const billNo = $('#billNo')[0];
    const billNoChip = $('#billNoChip')[0];
    const billEntryNo = $('#billEntryNo')[0];
    const billLinesWrap = $('#billLines')[0];
    const billTotalEl = $('#billTotal')[0];

    function makeBillLineRow(idx) {
      const row = makeLineRow("bill", idx);
      // tweak labels a bit
      $(row).find('.stock-line-summary .pv-muted').text('Line Amount');
      return row;
    }

    function recalcBillTotal() {
      let total = 0;
      $(billLinesWrap).find(".pv-line").toArray().forEach((row) => {
        const qty = pvParseIntAmount($(row).find(".line-qty")[0].value);
        const rate = pvParseIntAmount($(row).find(".line-rate")[0].value);
        total += qty * rate;
      });
      billTotalEl.textContent = fmtPKR(total);
    }

    $(billLinesWrap).on("input", (e) => {
      if (
        $(e.target).hasClass("line-qty") ||
        $(e.target).hasClass("line-rate")
      ) {
        recalcBillTotal();
      }
    });

    $('#btnAddBillLine').on("click", () => {
        $(billLinesWrap).append(
          makeBillLineRow(billLinesWrap.children.length + 1)
        );
        recalcBillTotal();
      });

    $(billForm).on("submit", async (e) => {
      e.preventDefault();

      if (!billParty.value) {
        Swal.fire({
          icon: "error",
          title: "Missing Party",
          text: "Please select Supplier / Site Name.",
        });
        return;
      }

      const lines = collectLines(billLinesWrap);
      if (!lines.length) {
        Swal.fire({
          icon: "error",
          title: "No items",
          text: "Add at least one bill item with Qty > 0.",
        });
        return;
      }

      $(billForm).find('button[type="submit"]')[0].disabled = true;
      try {
        const res = await apiPost(endpoints.storeBill, {
          type: billType.value,
          date: billDate.value,
          party_id: Number(billParty.value),
          entry_no: billEntryNo.value || "",
          lines,
        });

        viewToast(
          "success",
          "Saved",
          `${billType.value.toUpperCase()} bill saved (${res?.data?.bill_no || ""}).`
        );
        stockModal('#stockBillCreateModal','hide');

        billEntryNo.value = "";
        $(billLinesWrap).html("");
        $(billLinesWrap).append(makeBillLineRow(1));
        recalcBillTotal();

        await loadNextBill();
        await Promise.all([loadBills(), loadReport()]);
      } catch (err) {
        handleApiError(err, "Unable to save bill.");
      } finally {
        $(billForm).find('button[type="submit"]')[0].disabled = false;
      }
    });


    /* ===========================
  PROJECT-SCOPED ITEMS + PARTIES MANAGEMENT
  =========================== */
    const editModalEl = $('#editModal')[0];
    const editModal = {
      show: function(){stockModal('#editModal','show');},
      hide: function(){stockModal('#editModal','hide');}
    };

    function openEditModal(title, fields, onSave) {
      $('#editModalTitle')[0].textContent = title;

      const form = $('#editForm')[0];
      $(form).html("");

      fields.forEach((f) => {
        $(form).append(
          `
            <div class="mb-2">
              <label class="form-label pv-small">${f.label}</label>
              ${f.type === "select"
            ? `<select class="form-select" name="${f.name}">${(f.options || [])
              .map((o) => `<option value="${o.value}">${o.label}</option>`)
              .join("")}</select>`
            : `<input class="form-control ${f.money ? "pv-money" : ""}" name="${f.name
            }" value="${f.value ?? ""}">`
          }
            </div>
          `
        );
        if (f.type === "select") {
          $(form).find(`[name="${f.name}"]`)[0].value = f.value ?? "";
        }
      });

      $(form).append(
        `
          <div class="stock-modal-actions mt-2">
            ${onSave
          ? `<button type="button" class="btn btn-primary btn-lg" id="editSaveBtn">
              <i class="bi bi-check2-circle me-1"></i> Save
            </button>`
          : ""
        }
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
              ${onSave ? "Cancel" : "Close"}
            </button>
          </div>
        `
      );

      stockEnhanceDropdowns($('#editModal'));
      $(form).find(".pv-money").toArray().forEach((el) => {
        pvFormatAmountNoDecimal(el);
        $(el).on("input", () => pvFormatAmountNoDecimal(el));
      });

      if (onSave) {
        $(form).find("#editSaveBtn").on("click", () => {
          const data = Object.fromEntries(new FormData(form).entries());
          onSave(data);
        });
      }

      editModal.show();
    }

    /* Items */
    $('#itemForm').on("submit", async (e) => {
      e.preventDefault();
      const name = $('#itemName')[0].value.trim();
      const unit = $('#itemUnit')[0].value.trim();
      const rate = pvParseIntAmount(
        $('#itemRate')[0].value
      );

      if (!name || !unit) return;

      try {
        await apiPost(endpoints.storeItem, {
          name,
          unit,
          default_rate: rate,
        });
        viewToast("success", "Added", "Item created.");
        stockModal('#stockItemCreateModal','hide');

        $('#itemName')[0].value = "";
        $('#itemUnit')[0].value = "";
        $('#itemRate')[0].value = "";

        await loadMeta();
        await loadItemsTable();
      } catch (err) {
        handleApiError(err, "Unable to create item.");
      }
    });


    /* Parties */
    $('#partyForm').on("submit", async (e) => {
      e.preventDefault();
      const type = $('#partyType')[0].value;
      const name = $('#partyName')[0].value.trim();
      if (!name) return;

      try {
        await apiPost(endpoints.storeParty, {
          type,
          name,
          mobile: $('#partyMobile')[0].value.trim(),
          address: $('#partyAddress')[0].value.trim(),
          head: $('#accounts_id')[0].value.trim(),
          subhead: $('#subaccounts_id')[0].value.trim(),
        });

        viewToast("success", "Added", "Party created.");
        stockModal('#stockPartyCreateModal','hide');

        $('#partyName')[0].value = "";
        $('#partyMobile')[0].value = "";
        $('#partyAddress')[0].value = "";
        $('#accounts_id')[0].value = "";
        $('#subaccounts_id')[0].value = "";

        await loadMeta();
        await loadPartiesTable();
      } catch (err) {
        console.error(err);
        handleApiError(err, "Unable to create party.");
      }
    });


    /* ===========================
  DATA LOADERS
  =========================== */
    async function loadMeta() {
      const res = await apiGet(endpoints.meta);
      state.items = res?.data?.items || [];
      state.parties = res?.data?.parties || [];

      fillPartySelect(entryParty, false);
      fillPartySelect(billParty, false);
      fillItemSelect($('#entryFilterItem')[0], true);
      fillItemSelect($('#stockItemFilter')[0], true);
      fillPartySelect($('#billFilterParty')[0], true);
      refreshLineItemSelects();
    }

    async function loadNextSlip() {
      const res = await apiGet(endpoints.nextSlip);
      entrySlipNo.value = res?.data?.slip_no || "";
    }

    async function loadNextBill() {
      const res = await apiGet(endpoints.nextBill, { type: billType.value });
      billNo.value = res?.data?.bill_no || "";
      billNoChip.textContent = billNo.value || "Bill no… auto";
    }

    // A skeleton belongs to the specific table scroll viewport, never to the page.
    // Request counters prevent concurrent filter refreshes from hiding it prematurely.
    const stockSkeletonCounts={};
    const stockSkeletonHosts={entry:'#entryTbody',bill:'#billTbody',stock:'#stockTbody',
      item:'#itemTbody',party:'#partyTbody',history:'#itemImportHistoryBody'};
    function stockSkeletonStart(kind){
      const $body=$(stockSkeletonHosts[kind]),$viewport=$body.closest('.table-responsive');
      if(!$viewport.length)return;
      stockSkeletonCounts[kind]=(stockSkeletonCounts[kind]||0)+1;
      if($viewport.children('.stock-skeleton-overlay').length)return;
      const $overlay=$('<div class="stock-skeleton-overlay" aria-label="Loading records" role="status">');
      for(let row=0;row<7;row++){
        const $row=$('<div class="stock-skeleton-row">');
        for(let column=0;column<5;column++)$row.append('<span class="stock-skeleton-bar"></span>');
        $overlay.append($row);
      }
      $viewport.append($overlay);
    }
    function stockSkeletonEnd(kind){
      stockSkeletonCounts[kind]=Math.max(0,(stockSkeletonCounts[kind]||1)-1);
      if(stockSkeletonCounts[kind]===0){
        $(stockSkeletonHosts[kind]).closest('.table-responsive').children('.stock-skeleton-overlay').remove();
      }
    }
    async function loadEntries() {
      stockSkeletonStart('entry');
      try {
      const res = await apiGet(endpoints.entries, {
        item_id: $('#entryFilterItem')[0].value || null,
        flow: $('#entryFilterFlow')[0].value || null,
        q: $('#entrySearch')[0].value || null,
        per_page: 50,
        page: 1,
      });
      state.entries = res?.data || { rows: [], total: 0, kpi: {} };
      renderEntryTable();      } finally { stockSkeletonEnd('entry'); }
    }

    async function loadBills() {
      stockSkeletonStart('bill');
      try {
      const res = await apiGet(endpoints.bills, {
        type: $('#billFilterType')[0].value || null,
        party_id: $('#billFilterParty')[0].value || null,
        q: $('#billSearch')[0].value || null,
        per_page: 50,
        page: 1,
      });
      state.bills = res?.data || { rows: [], total: 0, kpi: {} };
      renderBillTable();      } finally { stockSkeletonEnd('bill'); }
    }

    async function loadReport() {
      stockSkeletonStart('stock');
      try {
      const res = await apiGet(endpoints.report, {
        from: $('#stockFrom')[0].value || null,
        to: $('#stockTo')[0].value || null,
        item_id: $('#stockItemFilter')[0].value || null,
      });
      state.report = res?.data || { rows: [], kpi: {} };
      renderStock();      } finally { stockSkeletonEnd('stock'); }
    }

    async function loadItemsTable() {
      stockSkeletonStart('item');
      try {
      const res = await apiGet(endpoints.items, {
        q: $('#itemSearch')[0].value || null,
      });
      state.itemsTable = res?.data || { rows: [], total: 0 };
      renderItems();      } finally { stockSkeletonEnd('item'); }
    }

    async function loadItemImportHistory() {
      stockSkeletonStart('history');
      try {
      const tbody = $('#itemImportHistoryBody')[0];
      try {
        const result = await apiGet(endpoints.itemImportHistory);
        const rows = result?.data?.rows || [];
        $(tbody).html(rows.length ? rows.map((row) => `
          <tr><td>#${Number(row.id)}</td><td>${importHtml(row.created_at || "")}</td><td>${importHtml(row.created_by || "-")}</td><td><span class="pv-pill ${row.status === "applied" ? "ok" : "warn"}">${importHtml(row.status)}</span></td><td>${Number(row.added)} / ${Number(row.updated)} / ${Number(row.failed)}</td><td><button type="button" class="btn btn-outline-primary btn-sm" data-import-preview="${Number(row.id)}">Preview</button></td></tr>`).join("") : '<tr><td colspan="6" class="text-muted text-center">No import batches yet.</td></tr>');
        $(tbody).find("[data-import-preview]").toArray().forEach((button) => $(button).on("click", async () => {
          try {
            const url = endpoints.itemImportPreview.replace(/0(?=\D*$)/, encodeURIComponent(button.dataset.importPreview));
            const detail = await apiGet(url);
            renderItemImportPreview(detail.data);
            $('#itemImportPreview')[0].scrollIntoView({ behavior: "smooth", block: "nearest" });
          } catch (error) { handleApiError(error, "Unable to load this import preview."); }
        }));
      } catch (error) {
        $(tbody).html(`<tr><td colspan="6" class="text-danger text-center">${importHtml(error?.data?.message || "Import history is unavailable until its migration is installed.")}</td></tr>`);
      }      } finally { stockSkeletonEnd('history'); }
    }

    async function loadPartiesTable() {
      stockSkeletonStart('party');
      try {
      const res = await apiGet(endpoints.parties, {
        q: $('#partySearch')[0].value || null,
        type: $('#partyFilterType')[0].value || null,
      });
      state.partiesTable = res?.data || { rows: [], total: 0 };
      renderParties();      } finally { stockSkeletonEnd('party'); }
    }

    /* ===========================
  RENDERING
  =========================== */
    const stockColumnFilters={};
    const stockTableBodies={entry:'#entryTbody',bill:'#billTbody',stock:'#stockTbody',item:'#itemTbody',party:'#partyTbody'};
    const stockTableKey=(kind,column)=>kind+':'+column;
    const stockCellText=($row,column)=>$.trim($row.children('td').eq(column).text());
    function stockFilterRows(kind){
      const $body=$(stockTableBodies[kind]);
      $body.children('tr').each(function(){
        const $row=$(this);let visible=true;
        $.each(stockColumnFilters,function(key,condition){
          if(!key.startsWith(kind+':'))return;
          const column=Number(key.slice(kind.length+1)),value=stockCellText($row,column);
          if(condition.search){if(value.toLowerCase().indexOf(condition.search)<0)visible=false;
          }else if(condition.from || condition.to){
            const actualDate=value.slice(0,10);
            if(condition.from && actualDate<condition.from)visible=false;
            if(condition.to && actualDate>condition.to)visible=false;
          }else if(condition.values && condition.values.length && $.inArray(value,condition.values)<0){
            visible=false;
          }
        });
        $row.toggle(visible);
      });
      $('[data-stock-kind="'+kind+'"].stock-excel-trigger').each(function(){
        $(this).toggleClass('stock-has-filter',!!stockColumnFilters[stockTableKey(kind,$(this).attr('data-stock-column'))]);
      });
    }
    $(function(){
      const $popover=$('#stockExcelPopover');
      let activeKind='',activeColumn=0,activeTrigger=null,options=[];
      function stockClosePopover(){
        $popover.addClass('d-none');
        if(activeTrigger)$(activeTrigger).attr('aria-expanded','false');
        activeTrigger=null;
      }
      function stockRenderOptions(){
        const term=$.trim($('#stockExcelSearch').val()).toLowerCase(),previous=stockColumnFilters[stockTableKey(activeKind,activeColumn)];
        const selected=previous&&previous.values?previous.values:null;
        const $list=$('#stockExcelValues').empty();
        const available=$.grep(options,function(value){return value.toLowerCase().indexOf(term)>=0;});
        const $all=$('<label class="stock-excel-choice fw-semibold">').append(
          $('<input type="checkbox" class="stock-excel-all">').prop('checked',!selected || selected.length===options.length),
          $('<span>').text('(Select All Results)'));
        $list.append($all);
        $.each(available,function(_,value){
          const $choice=$('<label class="stock-excel-choice">').append(
            $('<input type="checkbox" class="stock-excel-choice-input">').val(value).prop('checked',!selected || $.inArray(value,selected)>=0),
            $('<span>').text(value||'(Blank)'));
          $list.append($choice);
        });
      }
      $(document).on('click','.stock-excel-trigger',function(event){
        event.preventDefault();event.stopPropagation();
        if(activeTrigger===this && !$popover.hasClass('d-none')){stockClosePopover();return;}
        stockClosePopover();
        activeTrigger=this;activeKind=$(this).attr('data-stock-kind');activeColumn=Number($(this).attr('data-stock-column'));
        const key=stockTableKey(activeKind,activeColumn),previous=stockColumnFilters[key]||{};
        const isDate=(activeColumn===1 && $.inArray(activeKind,['entry','bill'])>=0);
        const $body=$(stockTableBodies[activeKind]);
        options=[];
        $body.children('tr').each(function(){const value=stockCellText($(this),activeColumn);if($.inArray(value,options)<0)options.push(value);});
        options.sort(function(a,b){return a.localeCompare(b,undefined,{numeric:true,sensitivity:'base'});});
        $('#stockExcelTitle').text($(this).attr('data-stock-label')||'Filter');
        $('#stockExcelSearch').val('');
        $('#stockExcelDateFields').toggleClass('d-none',!isDate);
        $('#stockExcelChoicesWrap').toggleClass('d-none',isDate);
        $('#stockExcelFrom').val(previous.from||'');
        $('#stockExcelTo').val(previous.to||'');
        if(!isDate)stockRenderOptions();
        $(this).attr('aria-expanded','true');
        $popover.removeClass('d-none');
        const bounds=$(this).offset(),left=Math.min($(window).width()-300,Math.max(8,bounds.left-$(window).scrollLeft()-245));
        $popover.css({left:Math.max(8,left)+'px',top:Math.min($(window).height()-Math.min(400,$popover.outerHeight())-8,Math.max(8,bounds.top-$(window).scrollTop()+25))+'px'});
      });
      $('#stockExcelSearch').on('input',function(){stockRenderOptions();stockApplyLiveFilter();});
      $('#stockExcelValues').on('change','.stock-excel-all',function(){
        $('#stockExcelSearch').val('');
        $('#stockExcelValues .stock-excel-choice-input').prop('checked',$(this).is(':checked'));
      });
      $('#stockExcelValues').on('change','.stock-excel-choice-input',function(){
        $('#stockExcelSearch').val('');
        $('#stockExcelValues .stock-excel-all').prop('checked',$('#stockExcelValues .stock-excel-choice-input:not(:checked)').length===0);
      });
      $('.stock-excel-sort').on('click',function(){
        const direction=$(this).attr('data-direction')==='asc'?1:-1;
        const $tbody=$(stockTableBodies[activeKind]);
        const sorted=$tbody.children('tr').get().sort(function(a,b){
          const aa=stockCellText($(a),activeColumn),bb=stockCellText($(b),activeColumn);
          return direction*aa.localeCompare(bb,undefined,{numeric:true,sensitivity:'base'});
        });
        $tbody.append(sorted);stockFilterRows(activeKind);stockClosePopover();
      });
      function stockApplyLiveFilter(){
        if(!activeKind)return;
        const key=stockTableKey(activeKind,activeColumn);
        const isDate=(activeColumn===1&&$.inArray(activeKind,['entry','bill'])>=0);
        if(isDate){
          const from=$('#stockExcelFrom').val(),to=$('#stockExcelTo').val();
          if(from&&to&&from>to)return;
          if(from||to)stockColumnFilters[key]={from:from,to:to};else delete stockColumnFilters[key];
        }else{
          const term=$.trim($('#stockExcelSearch').val()).toLowerCase();
          const selected=$('#stockExcelValues .stock-excel-choice-input:checked').map(function(){return $(this).val();}).get();
          // Search applies instantly, even when this column is empty of choices.
          if(term){
            stockColumnFilters[key]={search:term};
          }else if(selected.length===0 && options.length){
            stockColumnFilters[key]={values:['__stock_no_matching_values__']};
          }else if(selected.length===options.length){
            delete stockColumnFilters[key];
          }else stockColumnFilters[key]={values:selected};
        }
        stockFilterRows(activeKind);
      }
      $('#stockExcelFrom,#stockExcelTo').on('input change',stockApplyLiveFilter);
      $('#stockExcelValues').on('change','.stock-excel-all,.stock-excel-choice-input',stockApplyLiveFilter);
      $('#stockExcelClear').on('click',function(){
        delete stockColumnFilters[stockTableKey(activeKind,activeColumn)];
        stockFilterRows(activeKind);stockClosePopover();
      });
      $('#stockExcelCancel').on('click',stockClosePopover);
      $(document).on('mousedown.stockExcel',function(event){if(!$(event.target).closest('#stockExcelPopover,.stock-excel-trigger').length)stockClosePopover();});
      $(document).on('keydown.stockExcel',function(event){if(event.key==='Escape')stockClosePopover();});
      $('.pv-tabbtn').on('click',stockClosePopover);
    });

    function stockActionsCell(sourceButtons){
      return '<td class="blue-actions-cell">'+sourceButtons+'</td>';
    }
    function stockEnhanceRowActions(body){
      if($.blueActionMenu && $.blueActionMenu.enhance){
        $.blueActionMenu.enhance($(body).children('tr').children('td.blue-actions-cell'));
      }
    }
    function renderEntryTable() {
      const tbody = $('#entryTbody')[0];
      $(tbody).html("");
      (state.entries.rows || []).forEach((en, idx) => {
        const billingPill = en.billed_at
          ? `<span class="pv-pill ok">Billed</span>`
          : `<span class="pv-pill warn">Unbilled</span>`;
        const flowPill =
          en.flow === "IN"
            ? `<span class="pv-pill ok">IN</span>`
            : `<span class="pv-pill bad">OUT</span>`;

        $(tbody).append(
          `
            <tr>
              <td>${idx + 1}</td>
              <td>${en.entry_date || "—"}</td>
              <td>${flowPill}</td>
              <td>${en.party_name || "—"}</td>
              <td class="text-truncate" style="max-width:220px;">${en.reason || "—"
          }</td>
              <td>${en.item_name || "—"}</td>
              <td>${fmtInt(en.qty)}</td>
              <td>${fmtPKR(en.rate || 0)}</td>
              <td>${fmtPKR(en.amount || 0)}</td>
              <td>${billingPill}</td>
              ${stockActionsCell(`<button type="button" class="stock-doc-edit" data-kind="entry" data-id="${Number(en.id)}">Edit</button><button type="button" class="stock-doc-delete" data-kind="entry" data-id="${Number(en.id)}">Delete</button>`)}
            </tr>
          `
        );
      });

      $('#entryCountChip')[0].textContent = `${state.entries.total || 0
        } total`;

      stockEnhanceRowActions(tbody);
      stockFilterRows("entry");
      const kpi = state.entries.kpi || {};
      $('#kpiInQty')[0].textContent = fmtInt(
        kpi.in_qty || 0
      );
      $('#kpiOutQty')[0].textContent = fmtInt(
        kpi.out_qty || 0
      );
      $('#kpiLines')[0].textContent = fmtInt(
        kpi.line_count || 0
      );

    }

    function renderBillTable() {
      const tbody = $('#billTbody')[0];
      $(tbody).html("");
      (state.bills.rows || []).forEach((b, idx) => {
        $(tbody).append(
          `
            <tr>
              <td>${idx + 1}</td>
              <td>${b.date || "—"}</td>
              <td><span class="pv-pill ${b.type === "purchase" ? "ok" : "warn"
          }">${(b.type || "").toUpperCase()}</span></td>
              <td>${b.party_name || "—"}</td>
              <td class="text-truncate" style="max-width:260px;">${b.bill_no || ""}${b.entry_no ? " • Entry: " + b.entry_no : ""
          }</td>
              <td>${fmtPKR(b.total || 0)}</td>
              ${stockActionsCell(`<button type="button" class="stock-doc-edit" data-kind="bill" data-id="${Number(b.id)}">Edit</button><button type="button" class="stock-doc-delete" data-kind="bill" data-id="${Number(b.id)}">Delete</button>`)}
            </tr>
          `
        );
      });

      $('#billCountChip')[0].textContent = `${state.bills.total || 0
        } total`;

      stockEnhanceRowActions(tbody);
      stockFilterRows("bill");
      const kpi = state.bills.kpi || {};
      $('#kpiPurchaseBills')[0].textContent = fmtInt(
        kpi.purchase_count || 0
      );
      $('#kpiSaleBills')[0].textContent = fmtInt(
        kpi.sale_count || 0
      );
      $('#kpiBillTotal')[0].textContent = fmtPKR(
        kpi.total_amount || 0
      );

    }

    function renderStock() {
      const from = $('#stockFrom')[0].value;
      const to = $('#stockTo')[0].value;
      const rows = state.report.rows || [];
      const tbody = $('#stockTbody')[0];
      $(tbody).html("");

      rows.forEach((r) => {
        $(tbody).append(
          `
            <tr>
              <td>${r.item_name || "—"}</td>
              <td>${fmtInt(r.purchase_qty || 0)}</td>
              <td>${fmtInt(r.sale_qty || 0)}</td>
              <td><span class="pv-pill ${Number(r.available) < 0 ? "bad" : "ok"}">${fmtInt(
            r.available || 0
          )}</span></td>
            </tr>
          `
        );
      });

      stockFilterRows("stock");
      const kpi = state.report.kpi || {};
      $('#kpiStockItems')[0].textContent = fmtInt(
        kpi.items || 0
      );
      $('#kpiStockIn')[0].textContent = fmtInt(
        kpi.in_qty || 0
      );
      $('#kpiStockOut')[0].textContent = fmtInt(
        kpi.out_qty || 0
      );
      $('#kpiStockAvail')[0].textContent = fmtInt(
        kpi.available || 0
      );

      const chip = $('#stockRangeChip')[0];
      chip.textContent = `Range: ${from || "—"} → ${to || "—"}`;
    }

    function renderItems() {
      const tbody = $('#itemTbody')[0];
      $(tbody).html("");
      (state.itemsTable.rows || []).forEach((it, idx) => {
        $(tbody).append(
          `
            <tr>
              <td><input type="checkbox" class="stock-item-select" value="${Number(it.id)}" aria-label="Select ${importHtml(it.name)}"></td>
              <td>${idx + 1}</td>
              <td>${it.name}</td>
              <td>${it.unit}</td>
              <td>${fmtPKR(it.default_rate || 0)}</td>
              ${stockActionsCell(`<button type="button" data-act="edit">Edit</button><button type="button" data-act="del">Delete</button>`)}
            </tr>
          `
        );
      });

      $('#itemCountChip')[0].textContent = `${state.itemsTable.total || 0} total`;

      const exportSelected = $('#exportSelectedItems')[0];
      const selectAll = $('#selectAllStockItems')[0];
      if (selectAll) selectAll.checked = false;
      const syncSelectedExport = () => {
        const ids = Array.from($(tbody).find(".stock-item-select:checked").toArray(), (input) => input.value);
        const url = new URL(endpoints.exportItems, window.location.origin);
        url.searchParams.set("format", "xlsx");
        ids.forEach((id) => url.searchParams.append("item_ids[]", id));
        exportSelected.href = url.toString();
        $(exportSelected).toggleClass("disabled", ids.length === 0);
        exportSelected.setAttribute("aria-disabled", ids.length === 0 ? "true" : "false");
      };
      if (selectAll) $(selectAll).off('change.stockExport').on('change.stockExport', () => {
        $(tbody).find('.stock-item-select').prop('checked',$(selectAll).prop('checked'));
        syncSelectedExport();
      });
      $(tbody).find(".stock-item-select").toArray().forEach((input) => $(input).on("change", syncSelectedExport));
      if (exportSelected) $(exportSelected).on('click', (event) => {
        if (!$(tbody).find(".stock-item-select:checked")[0]) event.preventDefault();
      });
      syncSelectedExport();

      $(tbody).find("button[data-act]").toArray().forEach((btn) => {
        $(btn).on("click", async () => {
          const row = btn.closest("tr");
          const idx = Array.from(row.parentNode.children).indexOf(row);
          const it = state.itemsTable.rows[idx];
          if (!it) return;

          if (btn.dataset.act === "edit") {
            openEditModal(
              "Edit Item",
              [
                { label: "Item Name", name: "name", value: it.name },
                { label: "Unit", name: "unit", value: it.unit },
                {
                  label: "Default Rate (PKR)",
                  name: "default_rate",
                  value: fmtInt(it.default_rate || 0),
                  money: true,
                },
              ],
              async (data) => {
                try {
                  await apiSend(withId(endpoints.updateItem, it.id), "PUT", {
                    name: data.name || "",
                    unit: data.unit || "",
                    default_rate: pvParseIntAmount(data.default_rate),
                  });
                  viewToast("success", "Updated", "Item updated.");
                  editModal.hide();
                  await loadMeta();
                  await loadItemsTable();
                  await Promise.all([loadEntries(), loadBills(), loadReport()]);
                } catch (err) {
                  handleApiError(err, "Unable to update item.");
                }
              }
            );
            return;
          }

          if (btn.dataset.act === "del") {
            const ok = await Swal.fire({
              icon: "warning",
              title: "Archive item from this project?",
              text: "It will be hidden from new entries. Existing stock and billing history will remain preserved.",
              showCancelButton: true,
              confirmButtonText: "Archive item",
            });
            if (!ok.isConfirmed) return;
            try {
              await apiSend(withId(endpoints.deleteItem, it.id), "DELETE");
              viewToast("success", "Archived", "Item archived for this project; history was preserved.");
              await loadMeta();
              await loadItemsTable();
              await Promise.all([loadEntries(), loadBills(), loadReport()]);
            } catch (err) {
              handleApiError(err, "Unable to delete item.");
            }
          }
        });
      });
      stockEnhanceRowActions(tbody);
      stockFilterRows("item");
    }

    function renderParties() {
      const tbody = $('#partyTbody')[0];
      $(tbody).html("");
      (state.partiesTable.rows || []).forEach((p, idx) => {
        $(tbody).append(
          `
            <tr>
              <td>${idx + 1}</td>
              <td>${p.vendor_code || "â€”"}</td>
              <td>${p.name}</td>
              <td>${p.mobile || "—"}</td>
              <td class="text-truncate" style="max-width:160px;">${p.address || "—"
          }</td>
              <td>${p.head || "—"}</td>
              <td>${p.subhead || "—"}</td>
              <td><span class="pv-pill">${p.type}</span></td>
              <td><span class="pv-pill ${p.is_active ? "ok" : "bad"}">${p.is_active ? "Active" : "Inactive"}</span></td>
              ${stockActionsCell(`<button type="button" data-act="edit">Edit</button><button type="button" data-act="del">Delete</button><button type="button" data-act="status">${p.is_active ? "Deactivate" : "Activate"}</button>`)}
            </tr>
          `
        );
      });

      $('#partyCountChip')[0].textContent = `${state.partiesTable.total || 0} total`;

      $(tbody).find("button[data-act]").toArray().forEach((btn) => {
        $(btn).on("click", async () => {
          const row = btn.closest("tr");
          const idx = Array.from(row.parentNode.children).indexOf(row);
          const p = state.partiesTable.rows[idx];
          if (!p) return;

          if (btn.dataset.act === "status") {
            const result = await Swal.fire({
              icon: "question",
              title: p.is_active ? "Deactivate party?" : "Activate party?",
              input: "textarea",
              inputLabel: "Reason (required)",
              inputAttributes: { maxlength: 500 },
              showCancelButton: true,
              confirmButtonText: p.is_active ? "Deactivate" : "Activate",
              inputValidator: (value) => !value?.trim() ? "Enter a reason to continue." : undefined,
            });
            if (!result.isConfirmed) return;
            try {
              await apiSend(withId(endpoints.partyStatus, p.id), "PATCH", {
                is_active: p.is_active ? 0 : 1,
                reason: result.value.trim(),
              });
              viewToast("success", "Updated", "Party status updated.");
              await loadMeta();
              await loadPartiesTable();
            } catch (err) {
              handleApiError(err, "Unable to update party status.");
            }
            return;
          }

          if (btn.dataset.act === "edit") {
            openEditModal(
              "Edit Party",
              [
                {
                  label: "Type",
                  name: "type",
                  type: "select",
                  value: p.type,
                  options: [
                    { value: "Supplier", label: "Supplier" },
                    { value: "Consumer", label: "Consumer" },
                    { value: "Site", label: "Site" },
                  ],
                },
                { label: "Name", name: "name", value: p.name },
                { label: "Mobile", name: "mobile", value: p.mobile || "" },
                { label: "Address", name: "address", value: p.address || "" },
                {
                  label: "Project Account",
                  name: "account_id",
                  type: "select",
                  value: p.account_id || "",
                  options: [
                    { value: "", label: "Keep existing / unassigned" },
                    ...partyAccountOptions.map((account) => ({ value: account.id, label: account.label }))
                  ],
                },
              ],
              async (data) => {
                try {
                  await apiSend(withId(endpoints.updateParty, p.id), "PUT", {
                    type: data.type || "",
                    name: data.name || "",
                    mobile: data.mobile || "",
                    address: data.address || "",
                    account_id: data.account_id || "",
                  });
                  viewToast("success", "Updated", "Party updated.");
                  editModal.hide();
                  await loadMeta();
                  await loadPartiesTable();
                  await Promise.all([loadEntries(), loadBills(), loadReport()]);
                } catch (err) {
                  handleApiError(err, "Unable to update party.");
                }
              }
            );
            return;
          }

          if (btn.dataset.act === "del") {
            const ok = await Swal.fire({
              icon: "warning",
              title: "Archive party?",
              text: "Parties with transaction history cannot be archived. Parties without history are soft-archived; records are never hard-deleted.",
              showCancelButton: true,
              confirmButtonText: "Archive party",
            });
            if (!ok.isConfirmed) return;
            try {
              await apiSend(withId(endpoints.deleteParty, p.id), "DELETE");
              viewToast("success", "Archived", "Party archived; existing history was preserved.");
              await loadMeta();
              await loadPartiesTable();
              await Promise.all([loadEntries(), loadBills(), loadReport()]);
            } catch (err) {
              handleApiError(err, "Unable to delete party.");
            }
          }
        });
      });
      stockEnhanceRowActions(tbody);
      stockFilterRows("party");
    }

    /* ===========================
  INIT
  =========================== */
    // Tom Select uses document coordinates for non-body dropdown parents. Stock modals
    // are transformed/scrollable; anchor the menu to the actual input viewport rect.
    function stockPositionTomDropdown(instance){
      if(!instance || !instance.dropdown || !instance.wrapper)return;
      const rect=instance.wrapper.getBoundingClientRect(),menu=instance.dropdown;
      const viewportHeight=window.innerHeight||$(window).height();
      const availableBelow=Math.max(0,viewportHeight-rect.bottom-8);
      const availableAbove=Math.max(0,rect.top-8);
      const above=availableBelow<180 && availableAbove>availableBelow;
      const maxHeight=Math.min(280,Math.max(90,above?availableAbove:availableBelow));
      menu.classList.add('stock-modal-dropdown');
      menu.style.setProperty('position','fixed','important');
      menu.style.setProperty('left',Math.max(8,rect.left)+'px','important');
      menu.style.setProperty('width',Math.max(120,rect.width)+'px','important');
      menu.style.setProperty('right','auto','important');
      menu.style.setProperty('bottom','auto','important');
      menu.style.setProperty('top',(above?Math.max(8,rect.top-maxHeight):rect.bottom)+'px','important');
      menu.style.setProperty('max-height',maxHeight+'px','important');
      menu.style.setProperty('z-index','2100','important');
      menu.style.setProperty('opacity','1','important');
      menu.style.setProperty('background','#fff','important');
      $(menu).find('.ts-dropdown-content').css('max-height',(maxHeight-8)+'px');
    }
    $(document).on('scroll.stockTom', '.stock-create-modal .modal-body, #editModal .modal-body, #stockDocumentEditModal .modal-body',function(){
      $('.stock-create-modal select, #editModal select, #stockDocumentEditModal select').each(function(){
        if(this.tomselect && this.tomselect.isOpen)stockPositionTomDropdown(this.tomselect);
      });
    });
    function stockEnhanceDropdowns(context){
      const $context=$(context);
      $context.find('select').each(function(){
        const el=this,$el=$(el);
        if(el.tomselect){
          el.tomselect.sync();return;
        }
        if(window.TomSelect){
          if($el.hasClass('select2-hidden-accessible') && $.fn.select2){
            try{$el.select2('destroy');}catch(ignore){}
          }
          new window.TomSelect(el,{
            create:false,allowEmptyOption:!!$el.find('option[value=""]').length,
            dropdownParent:'body',maxOptions:250,searchField:['text'],
            onDropdownOpen:function(){
              stockPositionTomDropdown(this);
            },
            onDropdownClose:function(){
              this.dropdown.classList.remove('stock-modal-dropdown');
            },
            placeholder:$el.find('option[value=""]').first().text()||'Select an option',
            onInitialize:function(){$(this.wrapper).addClass('stock-tom-select');}
          });
          return;
        }
        if($.fn.select2 && !$el.hasClass('select2-hidden-accessible')){
          $el.select2({width:'100%',dropdownParent:$el.closest('.modal'),minimumResultsForSearch:0});
        }
      });
    }
    $(document).on('shown.bs.modal','.stock-create-modal,#editModal,#stockDocumentEditModal',function(){
      stockEnhanceDropdowns(this);
    });
    $(document).on('click','#btnAddLine,#btnAddBillLine,#stockDocumentAddRow',function(){
      var $modal=$(this).closest('.modal');
      setTimeout(function(){stockEnhanceDropdowns($modal);},0);
    });
    function stockModal(selector, action){
      var $modal=$(selector);
      if(!$modal.length)return;
      if(window.bootstrap && window.bootstrap.Modal){
        try{
          var klass=window.bootstrap.Modal;
          var existing=$.isFunction(klass.getInstance)?klass.getInstance($modal[0]):null;
          var instance=existing||($.isFunction(klass.getOrCreateInstance)?klass.getOrCreateInstance($modal[0]):new klass($modal[0]));
          if(action==='hide')instance.hide();else instance.show();
          return;
        }catch(error){
          if(window.console && console.warn)console.warn('Stock modal fallback:',error);
        }
      }
      if($.fn.modal){
        try{$modal.modal(action==='hide'?'hide':'show');return;}catch(error){}
      }
      // If the Bootstrap CDN is unavailable, keep the forms usable with jQuery.
      {
        if(action==='hide'){
          $modal.hide().removeClass('show').attr('aria-hidden','true');
          $('.stock-modal-fallback-backdrop').remove();$('body').removeClass('modal-open').css('overflow','');
        }else{
          $modal.css({display:'block',position:'fixed',inset:0,zIndex:1060,overflowY:'auto'}).addClass('show').attr('aria-hidden','false');
          $('<div class="modal-backdrop fade show stock-modal-fallback-backdrop">').css('z-index',1050).appendTo('body');
          $('body').addClass('modal-open').css('overflow','hidden');
        }
      }
    }
    $(document).on('click','[data-stock-modal]',function(event){
      event.preventDefault();
      if($(this).is('#stockNewEntry'))setEntryMode('purchase');
      stockModal($(this).attr('data-stock-modal'),'show');
      var $modal=$($(this).attr('data-stock-modal'));
      setTimeout(function(){stockEnhanceDropdowns($modal);},0);
    });
    // Bootstrap owns dismiss clicks and their animated fade/backdrop lifecycle.
    // Do not attach a second manual hide() listener to X/Cancel buttons.
    // The ERP shell may stop Bootstrap's delegated data-api dismiss click.
    // Capture X/Cancel on the actual button and ask the *same* active Bootstrap
    // instance to hide. No jQuery double-hide and no forced display:none.
    document.querySelectorAll('.stock-create-modal [data-bs-dismiss="modal"], #editModal [data-bs-dismiss="modal"], #stockDocumentEditModal [data-bs-dismiss="modal"], #stockDocumentDeleteModal [data-bs-dismiss="modal"]').forEach(function(button){
      if(button.dataset.stockCloseBound==='1')return;
      button.dataset.stockCloseBound='1';
      button.addEventListener('click',function(event){
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
        const modal=button.closest('.modal');
        if(!modal)return;
        stockModal('#'+modal.id,'hide');
      },true);
    });
    // Dynamically rendered Edit form footer needs a delegated fallback.
    $(document).on('click.stockModalClose','#editForm [data-bs-dismiss="modal"]',function(event){
      event.preventDefault();event.stopPropagation();
      stockModal('#editModal','hide');
    });

    $(document).on('keydown.stockCreateModal',function(event){
      if(event.key==='Escape' && $('.stock-modal-fallback-backdrop').length){
        stockModal('#'+$('.stock-create-modal.show').attr('id'),'hide');
      }
    });
    async function initUI() {
      entryDate.value = todayISO();
      billDate.value = todayISO();
      $('#stockFrom')[0].value = daysAgoISO(30);
      $('#stockTo')[0].value = todayISO();

      $(entryLinesWrap).html("");
      $(entryLinesWrap).append(makeLineRow("entry", 1));
      recalcEntryTotal();

      $(billLinesWrap).html("");
      $(billLinesWrap).append(makeBillLineRow(1));
      recalcBillTotal();

      $('#entryFilterItem').on("change", () =>
          loadEntries().catch((err) => handleApiError(err, "Unable to load entries."))
        );
      $('#entryFilterFlow').on("change", () =>
          loadEntries().catch((err) => handleApiError(err, "Unable to load entries."))
        );
      $('#entrySearch').on(
          "input",
          debounce(
            () =>
              loadEntries().catch((err) =>
                handleApiError(err, "Unable to load entries.")
              ),
            300
          )
        );

      $('#billFilterType').on("change", () =>
          loadBills().catch((err) => handleApiError(err, "Unable to load bills."))
        );
      $('#billFilterParty').on("change", () =>
          loadBills().catch((err) => handleApiError(err, "Unable to load bills."))
        );
      $('#billSearch').on(
          "input",
          debounce(
            () =>
              loadBills().catch((err) => handleApiError(err, "Unable to load bills.")),
            300
          )
        );

      $('#itemSearch').on(
          "input",
          debounce(
            () =>
              loadItemsTable().catch((err) =>
                handleApiError(err, "Unable to load items.")
              ),
            300
          )
        );
      $('#partySearch').on(
          "input",
          debounce(
            () =>
              loadPartiesTable().catch((err) =>
                handleApiError(err, "Unable to load parties.")
              ),
            300
          )
        );
      $('#partyFilterType').on("change", () =>
          loadPartiesTable().catch((err) => handleApiError(err, "Unable to load parties."))
        );

      $('#stockFrom,#stockTo').on('change',function(){
        loadReport().catch((err)=>handleApiError(err,'Unable to refresh stock report.'));
      });

      $(billType).on("change", () =>
        loadNextBill().catch((err) => handleApiError(err, "Unable to load bill no."))
      );

      $('.pv-money').each((_,el) => {
        pvFormatAmountNoDecimal(el);
        $(el).on("input", () => pvFormatAmountNoDecimal(el));
      });

      setEntryMode("purchase");

      try {
        await loadMeta();
        await Promise.all([
          loadNextSlip(),
          loadNextBill(),
          loadEntries(),
          loadBills(),
          loadReport(),
          loadItemsTable(),
          loadItemImportHistory(),
          loadPartiesTable(),
        ]);
      } catch (err) {
        handleApiError(err, "Failed to load data.");
      }
    }

    $(document).on('finance:stock-document-saved',function(){
      $.when(loadEntries(),loadBills(),loadReport())
        .fail(function(err){handleApiError(err,'Could not refresh stock registers.');});
    });
    $(function () {
      $('html,body').addClass('stock-page-active');
      function syncStockViewport(){
        var offset=Math.max(0,Math.ceil($('.stock-module-wrapper').offset().top-$(window).scrollTop()));
        $('.stock-module-wrapper').css('--stock-page-top',offset+'px');
      }
      syncStockViewport();
      $(window).on('resize.stockViewport',syncStockViewport);
      initUI();
      $(tabBtns).on('click',function(){setView($(this).data('view'));});
    });
  </script>
  <script>
  $(function(){
      'use strict';
      var stockDocBase=@json(url('admin/stocks/documents')),
          stockMetaUrl=@json(route('stocks.meta')),
          stockDocItems=[],stockDocFeedback=$.financeVoucherFeedback||{};
      var stockDocNotify=function(type,message){
          if($.isFunction(stockDocFeedback.toast))stockDocFeedback.toast(type,message);
          else if(window.toastr&&$.isFunction(window.toastr[type]))window.toastr[type](message);
      };
      var stockDocError=function($box,xhr,fallback){
          var text=(xhr.responseJSON&&xhr.responseJSON.message)
              ||($.isFunction(stockDocFeedback.error)
                  ? stockDocFeedback.error(xhr,fallback):fallback);
          $box.text(text).removeClass('d-none');stockDocNotify('error',text);
      };
      // Bootstrap 5 does not guarantee a jQuery .modal() plugin.
      // Keep all authored event/AJAX handlers in jQuery and use the native
      // Bootstrap modal API, as the existing stock Edit modal already does.
      var stockDocModal=function(selector,action){
          var element=$(selector)[0];
          if(!element||!window.bootstrap||!window.bootstrap.Modal){
              stockDocNotify('error','Stock modal component is unavailable.');
              return;
          }
          var instance=bootstrap.Modal.getOrCreateInstance(element);
          if(action==='show')instance.show();
          else instance.hide();
      };
      var stockDocHide=function(selector){stockDocModal(selector,'hide');};
      var stockDocAddLine=function(line){
          line=line||{};
          var $wrap=$('<div class="row g-2 align-items-end mb-2 stock-doc-line">')
              .attr('data-detail-id',line.detail_id||'');
          var $item=$('<select class="form-control stock-doc-item">');
          $('<option>').val('').text('Choose item').appendTo($item);
          $.each(stockDocItems,function(_,item){
              $('<option>').val(item.id).text(item.name+' / '+(item.unit||''))
                  .appendTo($item);
          });
          $item.val(line.item_id?String(line.item_id):'');
          $('<div class="col-md-5">').append(
              $('<label class="form-label small">Item</label>'),$item).appendTo($wrap);
          $('<div class="col-md-3">').append(
              $('<label class="form-label small">Qty</label>'),
              $('<input type="number" class="form-control stock-doc-qty" min="0.001" step="0.001" required>')
                  .val(line.qty||'')).appendTo($wrap);
          $('<div class="col-md-3">').append(
              $('<label class="form-label small">Rate (minor units)</label>'),
              $('<input type="number" class="form-control stock-doc-rate" min="0" step="1" required>')
                  .val(line.rate==null?'':line.rate)).appendTo($wrap);
          $('<div class="col-md-1">').append(
              $('<button class="btn btn-sm btn-outline-danger stock-doc-remove" type="button">Remove</button>'))
              .appendTo($wrap);
          $('#stockDocumentEditRows').append($wrap);
      };
      $('#stockDocumentEditRows').on('click','.stock-doc-remove',function(){
          if($('#stockDocumentEditRows .stock-doc-line').length<2){
              stockDocNotify('error','Stock voucher requires at least one item.');return;
          }
          $(this).closest('.stock-doc-line').remove();
      });
      $('#stockDocumentAddRow').on('click',function(){stockDocAddLine();});
      $(document).on('click','.stock-doc-edit',function(){
          var kind=$(this).attr('data-kind'),id=Number($(this).attr('data-id')),
              $button=$(this);
          if(!id||$.inArray(kind,['entry','bill'])<0)return;
          $button.prop('disabled',true);
          $.when(
              $.ajax({url:stockMetaUrl,dataType:'json',headers:{Accept:'application/json'}}),
              $.ajax({url:stockDocBase+'/'+kind+'/'+id,dataType:'json',
                  headers:{Accept:'application/json'}})
          ).done(function(metaResponse,documentResponse){
              stockDocItems=(metaResponse[0].data||{}).items||[];
              var data=documentResponse[0].data||{};
              if(!data.editable){
                  stockDocNotify('error','This stock voucher is linked to a bill or typed posting and needs its owning workflow.');
                  return;
              }
              $('#stockDocumentEditForm').data('kind',kind).data('source',id);
              $('#stockDocumentEditTitle').text('Edit '+(kind==='entry'?'Stock Entry':'Stock Bill')+' '+(data.number||''));
              $('#stockDocumentDate').val(data.date);
              $('#stockDocumentNarration').val(data.narration||'');
              $('#stockDocumentEditRows').empty();
              $.each(data.lines||[],function(_,line){stockDocAddLine(line);});
              $('#stockDocumentEditError').addClass('d-none').text('');
              stockDocModal('#stockDocumentEditModal','show');
          }).fail(function(xhr){
              stockDocError($('#stockDocumentEditError'),xhr,'Could not load stock voucher.');
          }).always(function(){$button.prop('disabled',false);});
      });
      $('#stockDocumentEditForm').on('submit',function(event){
          event.preventDefault();
          var kind=$(this).data('kind'),source=Number($(this).data('source')),
              $button=$('#stockDocumentSave'),lines=[];
          if(!source||$button.prop('disabled'))return;
          $('#stockDocumentEditRows .stock-doc-line').each(function(){
              var $line=$(this);
              lines.push({
                  detail_id:$line.attr('data-detail-id')||'',
                  item_id:$line.find('.stock-doc-item').val(),
                  qty:$line.find('.stock-doc-qty').val(),
                  rate:$line.find('.stock-doc-rate').val()
              });
          });
          $button.prop('disabled',true);
          $.ajax({
              url:stockDocBase+'/'+kind+'/'+source,type:'PUT',dataType:'json',
              headers:{Accept:'application/json',
                  'X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')},
              data:{date:$('#stockDocumentDate').val(),
                  narration:$('#stockDocumentNarration').val(),lines:lines}
          }).done(function(result){
              stockDocHide('#stockDocumentEditModal');
              stockDocNotify('success',result.message||'Stock voucher updated.');
              $(document).trigger('finance:stock-document-saved');
          }).fail(function(xhr){
              stockDocError($('#stockDocumentEditError'),xhr,'Stock voucher could not be updated.');
          }).always(function(){$button.prop('disabled',false);});
      });
      $(document).on('click','.stock-doc-delete',function(){
          var kind=$(this).attr('data-kind'),source=Number($(this).attr('data-id'));
          if(!source||$.inArray(kind,['entry','bill'])<0)return;
          $('#stockDocumentDeleteConfirm').data('kind',kind).data('source',source);
          $('#stockDocumentDeleteError').addClass('d-none').text('');
          stockDocModal('#stockDocumentDeleteModal','show');
      });
      $('#stockDocumentDeleteConfirm').on('click',function(){
          var $button=$(this),kind=$button.data('kind'),source=Number($button.data('source'));
          if(!source||$button.prop('disabled'))return;
          $button.prop('disabled',true);
          $.ajax({url:stockDocBase+'/'+kind+'/'+source,type:'DELETE',dataType:'json',
              headers:{Accept:'application/json','X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')}})
          .done(function(result){
              stockDocHide('#stockDocumentDeleteModal');
              stockDocNotify('success',result.message||'Stock voucher deleted.');
              $(document).trigger('finance:stock-document-saved');
          }).fail(function(xhr){
              stockDocError($('#stockDocumentDeleteError'),xhr,'Stock voucher could not be deleted.');
          }).always(function(){$button.prop('disabled',false);});
      });
  });
  </script>
@endsection
