@extends('admin.layouts.master')

@push('style')
<style>
/* Dashboard-only presentation upgrade; preserves all existing form, AJAX and routes. */
.bm-dashboard-modern { background: #f3f6fb; }
.bm-dashboard-modern .content-header { padding: 10px 0 5px; }
.bm-dashboard-modern .content-header h1 { font-weight: 750; font-size: 1.35rem; color: #193252; }
.bm-dashboard-modern .small-box { border-radius: 15px; overflow: hidden; min-height: 127px; margin-bottom: 12px; box-shadow: 0 5px 18px rgba(18,38,75,.10); transition: transform .2s,box-shadow .2s; }
.bm-dashboard-modern .small-box:hover { transform:translateY(-3px); box-shadow:0 10px 22px rgba(18,38,75,.15); }
.bm-dashboard-modern .small-box .inner { padding: 17px 19px 12px; }
.bm-dashboard-modern .small-box h3 { font-size: clamp(1.3rem,2vw,2rem); font-weight: 800; overflow-wrap:anywhere; }
.bm-dashboard-modern .small-box p { font-size: .88rem; font-weight: 600; }
.bm-dashboard-modern .small-box .icon { opacity:.35; }
.bm-dashboard-modern .small-box-footer { padding: 7px 12px; font-size: .78rem; }
.bm-dashboard-modern .custom_card { background: #fff; border: 1px solid #e3eaf3; border-radius: 15px; box-shadow: 0 5px 18px rgba(18,38,75,.06); overflow: hidden; }
.bm-dashboard-modern .custom_card .card-body { padding: 17px; }
.bm-dashboard-modern .custom_card h4,.bm-dashboard-modern .custom_card h5 { font-weight: 750; color: #193252; }
.bm-dashboard-modern .financial-circle { border-radius:50%; box-shadow: 0 6px 20px rgba(17,36,74,.12); margin-inline:auto; }
.bm-dashboard-modern .financial-circle h4 { font-size: clamp(.92rem,1.35vw,1.25rem); overflow-wrap:anywhere; }
.bm-dashboard-modern #dasticashTable th { white-space:nowrap; background:#f5f8fd; color:#52637a; }
.bm-dashboard-modern #dasticashTable td { vertical-align:middle; }
.bm-dashboard-modern .table-responsive,.bm-dashboard-modern .table-resposive { max-height: 220px; overflow:auto; scrollbar-width:thin; }
.bm-dashboard-modern .table-responsive thead th,.bm-dashboard-modern .table-resposive thead th { position:sticky; top:0; background:#f5f8fd; z-index:1; }
.bm-dashboard-modern .edit-btn,.bm-dashboard-modern .delete-btn { border-radius:7px; min-width:30px; }
@media (min-width: 992px) and (min-height: 720px) {
  .bm-dashboard-modern .content { height:calc(100vh - 133px); overflow:hidden; }
  .bm-dashboard-modern .content > .container-fluid { height:100%; overflow-y:auto; overscroll-behavior:contain; scrollbar-width:thin; }
}
@media (prefers-reduced-motion:reduce) {
  .bm-dashboard-modern .small-box { transition:none; }
}

/* Dashboard V3: direct Blade grid, no DOM relocation. */
.bm-dashboard-modern .content-header{padding:5px 0 2px}
.bm-dashboard-modern .content-header h1{font-size:1.05rem}
.bm-dashboard-modern .content>.container-fluid{display:flex;flex-direction:column;gap:10px}
.bm-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px;flex-shrink:0}
.bm-kpi{position:relative;overflow:hidden;display:block;background:#fff;color:#243852;border:1px solid #e5ebf3;border-radius:12px;padding:11px 13px;box-shadow:0 3px 12px #1b2f4b09;min-width:0;text-decoration:none!important}
.bm-kpi:hover{color:#223755;box-shadow:0 7px 20px #162c4d18;transform:translateY(-1px)}
.bm-kpi-icon{width:29px;height:29px;border-radius:8px;display:grid;place-items:center;margin-bottom:4px}
.bm-indigo .bm-kpi-icon{background:#e8edff;color:#5b6fe0}.bm-green .bm-kpi-icon{background:#dff8ef;color:#0eac82}.bm-amber .bm-kpi-icon{background:#fff0da;color:#c88f2e}.bm-blue .bm-kpi-icon{background:#e6f1ff;color:#307ad8}
.bm-kpi-value{font-size:clamp(16px,1.8vw,25px);font-weight:800;line-height:1.2;overflow-wrap:anywhere}
.bm-kpi-label{font-size:11px;color:#708199;margin-top:4px}.bm-kpi-action{font-size:10px;color:#376be1;margin-top:5px}
.bm-dashboard-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));grid-template-rows:repeat(2,minmax(0,1fr));gap:10px;min-height:0;flex:1}
.bm-panel{background:white;border:1px solid #e3eaf3;border-radius:13px;display:flex;flex-direction:column;min-width:0;min-height:0;overflow:hidden;box-shadow:0 3px 14px #14263c09}
.bm-panel-heading{padding:10px 13px;border-bottom:1px solid #eef1f6;display:flex;align-items:center;justify-content:space-between;gap:8px;flex:0 0 auto}
.bm-panel-heading h5{font-weight:750;font-size:13px;margin:0;color:#213956}.bm-panel-heading span{font-size:10px;color:#8391a7}
.bm-panel-body{min-height:0;flex:1;padding:10px 12px}
.bm-finance-body{display:flex;align-items:center;justify-content:space-around;gap:9px;overflow:auto}
.bm-ring-item{flex:1;min-width:0;text-align:center}
.bm-ring{width:clamp(79px,8.3vw,130px);aspect-ratio:1;max-width:100%;margin:0 auto 8px;padding:10px;border-radius:50%;background:conic-gradient(var(--ring-color) 0 76%,#e6edf6 76% 100%);display:grid;place-items:center}
.bm-ring-center{width:100%;height:100%;border-radius:50%;background:white;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:2px}
.bm-ring-center span{font-size:10px;color:#7a899e}.bm-ring-center strong{font-size:clamp(10px,1.05vw,15px);white-space:nowrap}
.bm-ring-caption{font-size:10px;color:#8994a9}
.bm-action-body{overflow-y:auto}.bm-action-block{margin-bottom:10px}.bm-action-block h6{font-size:12px;font-weight:700}.bm-action-title{display:flex;align-items:center;justify-content:space-between;gap:5px}.bm-attendance-fields{display:flex;gap:8px}.bm-attendance-fields>*{min-width:0;flex:1}
.bm-table-scroll{overflow:auto;scrollbar-width:thin;padding:0 11px 9px}.bm-table-scroll table{margin:0;font-size:11px}.bm-table-scroll thead th{position:sticky;top:0;background:#f5f8fc;z-index:2;border-top:0;white-space:nowrap}.bm-table-scroll td{vertical-align:middle}.bm-actions-cell{white-space:nowrap}
@media(min-width:992px){.bm-dashboard-modern .content{height:calc(100dvh - 127px)!important;overflow:hidden!important}.bm-dashboard-modern .content>.container-fluid{height:100%;overflow:hidden!important;padding-bottom:8px}.bm-kpi{padding:7px 11px}.bm-kpi-icon{margin-bottom:2px}.bm-panel-heading{padding:8px 11px}.bm-panel-body{padding:8px 11px}.bm-table-scroll{padding:0 10px 5px}}
@media(max-width:991.98px){.bm-dashboard-modern .content{height:auto!important;overflow:visible!important}.bm-dashboard-modern .content>.container-fluid{height:auto!important;overflow:visible!important}.bm-dashboard-grid{grid-template-rows:auto}.bm-panel{min-height:220px}.bm-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:600px){.bm-dashboard-grid{grid-template-columns:1fr}.bm-finance-body{gap:5px}.bm-ring{width:78px;padding:8px}.bm-ring-center strong{font-size:10px}.bm-attendance-fields{flex-direction:column}}

/* Batch 04 — tighter KPI layout, smaller rings, inline form controls */
.bm-kpi-top{display:flex;align-items:center;justify-content:space-between;gap:8px;min-height:29px}
.bm-kpi-bottom{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:7px;min-width:0}
.bm-kpi .bm-kpi-icon{margin:0;width:34px;height:34px;flex-shrink:0;font-size:16px}
.bm-kpi .bm-kpi-value{font-size:clamp(20px,2vw,28px);text-align:right}
.bm-kpi .bm-kpi-label{margin:0;font-size:clamp(11px,.83vw,13px);font-weight:700;color:#32445d;min-width:0}
.bm-kpi .bm-kpi-action{margin:0;flex-shrink:0;white-space:nowrap;font-size:clamp(10px,.78vw,12px);font-weight:700;color:#356ae2}
.bm-kpi .bm-kpi-action i{margin-left:4px}
.bm-ring{width:clamp(68px,6.4vw,100px);padding:8px;margin-bottom:5px}
.bm-ring-center span{font-size:9px}.bm-ring-center strong{font-size:clamp(9px,.9vw,13px)}
.bm-lead-search{position:relative;display:flex;align-items:center}
.bm-lead-search input{height:37px;padding-right:42px;border-radius:8px}
.bm-search-icon{position:absolute;right:5px;top:50%;transform:translateY(-50%);display:grid;place-items:center;width:30px;height:29px;border:0;border-radius:6px;background:transparent;color:#3475c9;font-size:15px;cursor:pointer}
.bm-search-icon:hover,.bm-search-icon:focus-visible{background:#e9f1ff;outline:2px solid #b5ceff;outline-offset:0}
.bm-attendance-fields{display:flex;align-items:stretch;gap:8px}
.bm-attendance-fields select{flex:1 1 38%;width:0}
.bm-attendance-fields input{flex:1 1 39%;width:0}
.bm-attendance-fields .bm-punch-button{flex:0 0 auto;width:auto;white-space:nowrap;padding:5px 12px;font-size:12px;font-weight:700}
@media(min-width:992px){.bm-kpi{padding:9px 12px}.bm-kpis{gap:9px}.bm-finance-body{align-items:center}.bm-panel-heading{padding:8px 11px}}
@media(max-width:1100px){.bm-kpi-bottom{flex-wrap:wrap;gap:3px}.bm-kpi .bm-kpi-action{font-size:10px}.bm-attendance-fields{flex-wrap:wrap}.bm-attendance-fields select,.bm-attendance-fields input{flex:1 1 35%;width:auto}}
@media(max-width:600px){.bm-kpi .bm-kpi-icon{width:29px;height:29px}.bm-kpi .bm-kpi-value{font-size:18px}.bm-attendance-fields{flex-direction:column}.bm-attendance-fields select,.bm-attendance-fields input{width:100%}}

/* Batch 05: compact upper panels without stretching their content. */
@media(min-width:992px){
 .bm-dashboard-grid{grid-template-rows:minmax(155px,0.74fr) minmax(0,1.26fr)}
 .bm-finance .bm-panel-body,.bm-actions .bm-panel-body{padding-top:5px;padding-bottom:5px}
 .bm-finance .bm-ring{width:clamp(70px,6.2vw,94px);margin-bottom:4px}
 .bm-actions .bm-action-block{margin-bottom:7px}
 .bm-actions .bm-action-block h6{margin-bottom:5px}
}
.bm-finance .bm-ring-center span{font-size:clamp(10px,.9vw,13px);font-weight:650}
.bm-finance .bm-ring-center strong{font-size:clamp(12px,1.05vw,16px);font-weight:800}
.bm-finance .bm-ring-caption{font-size:clamp(11px,.86vw,13px);font-weight:600;color:#53657e}
.bm-finance .bm-panel-heading h5{font-size:14px}
@media(max-width:991.98px){.bm-dashboard-grid{grid-template-rows:auto}.bm-finance,.bm-actions{min-height:175px}}

/* Batch 06 — fit the complete desktop dashboard below the navbar + breadcrumb.
   The previous 127px subtraction ignored the dashboard content header. */
@media (min-width: 992px) {
 .bm-dashboard-modern { height: calc(100dvh - 57px); min-height:0; overflow:hidden; display:flex; flex-direction:column; }
 .bm-dashboard-modern .content-header { flex:0 0 auto; }
 .bm-dashboard-modern .content { flex:1 1 auto; min-height:0; height:auto!important; overflow:hidden!important; }
 .bm-dashboard-modern .content > .container-fluid { box-sizing:border-box; height:100%; min-height:0; overflow:hidden!important; padding-bottom:9px; }
 .bm-dashboard-modern .bm-dashboard-grid { grid-template-rows:minmax(0,0.76fr) minmax(0,1.24fr); min-height:0; }
 .bm-dashboard-modern .bm-dasticash, .bm-dashboard-modern .bm-punches { min-height:0; }
 .bm-dashboard-modern .bm-dasticash .bm-table-scroll,
 .bm-dashboard-modern .bm-punches .bm-table-scroll { overflow-y:auto; overflow-x:auto; min-height:0; }
 .bm-dashboard-modern .bm-finance .bm-ring { width:clamp(90px,7.4vw,112px); max-width:min(100%,20vh); }
}
@media (min-width:992px) and (max-height:690px) {
 .bm-dashboard-modern .bm-finance .bm-ring { width:clamp(70px,6.3vw,95px); max-width:17vh; }
 .bm-dashboard-modern .bm-dashboard-grid { grid-template-rows:minmax(0,0.84fr) minmax(0,1.16fr); }
 .bm-dashboard-modern .bm-panel-heading{padding:6px 10px}
}
@media (min-width: 600px) and (max-width:991.98px) {
 .bm-dashboard-modern {height:auto;overflow:visible}
 .bm-dashboard-modern .bm-dashboard-grid {grid-template-columns:repeat(2,minmax(0,1fr));grid-template-rows:auto;align-items:stretch}
 .bm-dashboard-modern .bm-panel {min-height:220px}
 .bm-dashboard-modern .bm-finance .bm-ring{width:clamp(75px,9vw,110px)}
 .bm-dashboard-modern .bm-kpis {grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media (max-width:599.98px) {
 .bm-dashboard-modern {height:auto;overflow:visible}
 .bm-dashboard-modern .bm-dashboard-grid{grid-template-columns:minmax(0,1fr);grid-template-rows:auto}
 .bm-dashboard-modern .bm-panel{min-height:190px}
 .bm-dashboard-modern .bm-finance .bm-ring{width:clamp(64px,21vw,105px)}
 .bm-dashboard-modern .bm-finance .bm-ring-center strong{font-size:clamp(10px,2.7vw,14px)}
 .bm-dashboard-modern .bm-finance .bm-ring-center span{font-size:clamp(9px,2.3vw,12px)}
 .bm-dashboard-modern .bm-finance .bm-ring-caption{font-size:clamp(9px,2.5vw,12px)}
 .bm-dashboard-modern .bm-kpi-label{font-size:clamp(10px,2.6vw,13px)}
}

/* Batch 07 — contain AdminLTE wrapper, not just dashboard inner panels.
   AdminLTE's JavaScript min-height and the separate footer previously
   extended the whole document despite a viewport-bounded .content. */
@media (min-width: 992px) {
 body:has(.bm-dashboard-modern) { height:100dvh!important; overflow:hidden!important; }
 body:has(.bm-dashboard-modern) .wrapper {
   height:100dvh!important; min-height:0!important;
   display:flex; flex-direction:column; overflow:hidden!important;
 }
 body:has(.bm-dashboard-modern) .main-header {
   flex:0 0 auto; min-height:0;
 }
 body:has(.bm-dashboard-modern) .main-footer {
   flex:0 0 auto; min-height:0; margin-top:0!important;
 }
 body:has(.bm-dashboard-modern) .content-wrapper.bm-dashboard-modern {
   flex:1 1 0%; height:auto!important; min-height:0!important;
   margin-bottom:0!important; overflow:hidden!important;
   display:flex; flex-direction:column;
 }
 .bm-dashboard-modern .content-header { flex:0 0 auto; }
 .bm-dashboard-modern .content {
   flex:1 1 0%; height:auto!important; min-height:0!important;
   overflow:hidden!important;
 }
 .bm-dashboard-modern .content > .container-fluid {
   height:100%!important; min-height:0!important; overflow:hidden!important;
   display:flex; flex-direction:column;
 }
 .bm-dashboard-modern .bm-dashboard-grid {
   min-height:0!important; flex:1 1 0%;
   grid-template-rows:minmax(0,0.79fr) minmax(0,1.21fr);
 }
 .bm-dashboard-modern .bm-panel {min-height:0!important}
 .bm-dashboard-modern .bm-table-scroll {min-height:0; overflow:auto}
}
@media (max-width:991.98px) {
 /* Smaller devices must keep normal document scroll rather than clipping controls. */
 body:has(.bm-dashboard-modern),
 body:has(.bm-dashboard-modern) .wrapper {
   height:auto!important; min-height:100dvh!important; overflow:visible!important;
 }
}

/* Batch 08 — New Leads-only date preset; other KPIs retain original semantics. */
.bm-lead-period-filter{display:flex;align-items:center;gap:8px;justify-content:flex-end;flex-wrap:wrap;margin-bottom:-4px;font-size:11px;color:#566987}
.bm-lead-period-filter label{margin:0;font-weight:650}
.bm-lead-period-filter select{border:1px solid #d8e2f0;border-radius:7px;padding:3px 8px;background:#fff;color:#253b5a}
.bm-lead-period-filter span{font-size:10px;color:#75869b}
@media(max-width:600px){.bm-lead-period-filter{justify-content:flex-start;margin-bottom:0}}

/* AJAX period selector: custom inputs shown only when needed. */
.bm-lead-period-filter .bm-custom-period{display:inline-flex;align-items:center;gap:5px}
.bm-lead-period-filter .bm-custom-period[hidden],.bm-lead-period-filter [hidden]{display:none!important}
.bm-lead-period-filter input[type=date]{border:1px solid #d8e2f0;border-radius:6px;padding:3px;max-width:135px}
.bm-lead-period-filter [role=alert]{color:#bf3040;font-weight:600}
.bm-lead-period-filter[aria-busy=true]{opacity:.7}
@media(max-width:650px){.bm-lead-period-filter .bm-custom-period{flex-wrap:wrap}}

/* Batch 10: one two-level cash donut and live accessible legend. */
.bm-finance .bm-finance-dual{display:flex;flex-direction:row;align-items:center;justify-content:center;gap:clamp(13px,2.2vw,32px);overflow:auto;padding:10px 14px}
.bm-cash-donut{position:relative;flex:0 0 auto;width:clamp(120px,13vw,195px);aspect-ratio:1}
.bm-cash-outer{width:100%;height:100%;border-radius:50%;background:conic-gradient(#7353d8 0 100%);display:grid;place-items:center;padding:clamp(10px,1vw,15px);cursor:help}
.bm-cash-inner{width:100%;height:100%;border-radius:50%;background:conic-gradient(#0cae87 0 var(--hand-share),#198add var(--hand-share) calc(var(--hand-share) + var(--bank-share)),#e2e9f1 0 100%);display:grid;place-items:center;padding:clamp(12px,1.3vw,20px);cursor:help;border:3px solid white}
.bm-cash-core{background:white;width:100%;height:100%;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-direction:column;text-align:center;padding:3px}
.bm-cash-core span{font-size:clamp(10px,.8vw,13px);color:#64748b}.bm-cash-core strong{font-size:clamp(12px,1.15vw,19px);color:#173450;white-space:nowrap}
.bm-cash-legend{flex:1;min-width:150px;max-width:330px;display:flex;flex-direction:column;gap:9px}
.bm-cash-detail{display:grid;grid-template-columns:minmax(95px,1fr) auto auto;align-items:center;gap:8px;font-size:clamp(11px,.85vw,14px);cursor:help;border-radius:7px;padding:5px}
.bm-cash-detail:hover,.bm-cash-detail:focus-visible{background:#f0f5fb;outline-offset:1px}
.bm-cash-detail span{display:flex;align-items:center;gap:7px}.bm-cash-detail strong{white-space:nowrap;font-size:clamp(11px,.86vw,14px)}
.bm-cash-detail small{font-size:10px;color:#6e7f98;min-width:36px;text-align:right}
.bm-cash-total{border-top:1px solid #e1e6f0;padding-top:10px;font-weight:750;grid-template-columns:1fr auto}
.bm-dot{height:9px;width:9px;display:inline-block;border-radius:50%;flex-shrink:0}.bm-dot-hand{background:#0cae87}.bm-dot-bank{background:#198add}.bm-dot-total{background:#7353d8}
.bm-finance-tooltip{position:absolute;bottom:2px;left:50%;transform:translateX(-50%);background:#162b45;color:white;border-radius:6px;padding:7px 10px;white-space:nowrap;font-size:11px;z-index:7;pointer-events:none;box-shadow:0 6px 18px #0004}
.bm-finance-tooltip[hidden]{display:none}.bm-cash-warning{font-size:9px;color:#a06119;white-space:nowrap;grid-column:1/-1}
@media(max-width:1100px){.bm-cash-donut{width:clamp(105px,12vw,160px)}.bm-finance .bm-finance-dual{gap:9px;padding:7px}.bm-cash-legend{min-width:125px}.bm-cash-detail{gap:4px;grid-template-columns:minmax(80px,1fr) auto}.bm-cash-detail small{display:none}}
@media(max-width:650px){.bm-finance .bm-finance-dual{flex-wrap:wrap;overflow:visible}.bm-cash-donut{width:clamp(130px,37vw,180px)}.bm-cash-legend{width:100%;max-width:none}.bm-cash-detail small{display:block}}
@media(prefers-reduced-motion:reduce){.bm-cash-donut{animation:none}}

.bm-crm-open{align-self:flex-end;font-size:11px;font-weight:700;padding:3px 10px;margin-bottom:-5px}.bm-crm-kpis{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px;margin:8px 0 15px}.bm-crm-kpis>div{padding:12px;border:1px solid #dce6f1;border-radius:9px;background:#f8faff}.bm-crm-kpis strong{font-size:21px;display:block}.bm-crm-kpis span{font-size:11px}.bm-crm-table{max-height:48vh!important;overflow:auto}.bm-crm-table th{white-space:nowrap;position:sticky;top:0;background:#e9eff7}.bm-crm-table td{max-width:230px;overflow-wrap:anywhere}@media(max-width:600px){.bm-crm-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
.bm-module-panel{min-height:180px}.bm-module-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;overflow:auto}.bm-module-metric{display:block;padding:10px;border:1px solid #e3eaf3;border-radius:9px;background:#f8faff;color:#263d5b;text-decoration:none!important;min-width:0}.bm-module-metric strong{display:block;font-size:16px;overflow-wrap:anywhere}.bm-module-metric span{font-size:10px;color:#71829a}.bm-module-metric:hover{border-color:#9cb9ed;color:#1f4f9a}@media(min-width:992px){.bm-dashboard-modern .content>.container-fluid{overflow-y:auto!important}.bm-dashboard-modern .bm-dashboard-grid{grid-template-rows:auto!important;flex:none}.bm-dashboard-modern .bm-panel{min-height:180px}}@media(max-width:600px){.bm-module-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}}
.bm-finance-value{cursor:default;background:#f7faff}.bm-finance-value span{font-size:10px}.bm-finance-value strong{font-size:11px}.bm-recovery-chart{height:145px;display:flex;align-items:flex-end;gap:5px;padding:12px 10px 7px;overflow-x:auto;border-bottom:1px solid #edf1f6}.bm-recovery-bar-wrap{height:100%;min-width:43px;flex:1;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;text-decoration:none!important;color:#60708a}.bm-recovery-value{font-size:8px;margin-bottom:3px;color:#52637a}.bm-recovery-bar{display:block;width:min(25px,70%);min-height:2px;border-radius:4px 4px 0 0;background:linear-gradient(180deg,#43a5e3,#2386c7);transition:filter .15s}.bm-recovery-bar-wrap:hover .bm-recovery-bar{filter:brightness(.85)}.bm-recovery-bar-wrap small{font-size:8px;white-space:nowrap;margin-top:4px}@media(min-width:1100px){.bm-cash-legend{max-width:500px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:3px 8px}.bm-cash-detail{padding:3px}.bm-cash-total{grid-column:1/-1}.bm-finance .bm-finance-dual{justify-content:flex-start}.bm-finance-value strong{font-size:10px}}
.bm-actions .bm-lead-period-filter{justify-content:flex-start;margin:8px 0 4px}.bm-actions .bm-crm-open{align-self:flex-start;margin:3px 0}.bm-punch-inline{display:flex;align-items:center;gap:8px;margin:7px 0 10px}.bm-punch-inline select{max-width:280px}.bm-punch-inline input{max-width:260px}.bm-punch-inline .alert{margin:0;padding:4px 8px}@media(max-width:650px){.bm-punch-inline{flex-direction:column;align-items:stretch}.bm-punch-inline select,.bm-punch-inline input{max-width:none}}

/* Batch 11: one donut ring, lowest balance dark red, visible floating hover amount. */
.bm-finance .bm-single-donut{width:100%;height:100%;border:0;padding:clamp(15px,1.45vw,23px);border-radius:50%;background:conic-gradient(var(--hand-color) 0 var(--hand-share),var(--bank-color) var(--hand-share) calc(var(--hand-share) + var(--bank-share)),#e2e9f1 0 100%);cursor:pointer}
.bm-finance .bm-single-donut .bm-cash-core{width:100%;height:100%}
.bm-finance-tooltip{position:fixed!important;top:0;left:0;bottom:auto!important;transform:none!important;z-index:2147483000!important;pointer-events:none!important;max-width:min(300px,85vw);white-space:normal;line-height:1.45}
.bm-cash-detail{cursor:pointer}

/* Batch 12: slightly smaller, thinner single financial donut. */
.bm-finance .bm-cash-donut{width:clamp(108px,10.5vw,158px)}
.bm-finance .bm-single-donut{padding:clamp(11px,1vw,16px)}
@media(min-width:992px) and (max-height:690px){
 .bm-finance .bm-cash-donut{width:clamp(92px,9vw,132px)}
 .bm-finance .bm-single-donut{padding:11px}
}
@media(max-width:650px){
 .bm-finance .bm-cash-donut{width:clamp(112px,31vw,150px)}
 .bm-finance .bm-single-donut{padding:13px}
}

/* Tiny real-value financial slice: minimum 2-degree visual arc, actual % retained in legend. */
.bm-finance .bm-single-donut{background:conic-gradient(var(--hand-color) 0 var(--hand-display-share,var(--hand-share)),var(--bank-color) var(--hand-display-share,var(--hand-share)) 100%)}
.bm-finance .bm-tiny-hand-marker,.bm-finance .bm-tiny-bank-marker{position:absolute;width:13px;height:13px;top:3%;left:50%;transform:translate(-50%,-50%);border-radius:50%;border:2px solid white;background:#991b1b;box-shadow:0 1px 5px #0005;z-index:4;cursor:pointer;padding:0}
.bm-finance .bm-tiny-hand-marker[hidden],.bm-finance .bm-tiny-bank-marker[hidden]{display:none!important}
.bm-finance .bm-tiny-bank-marker{background:#991b1b}

/* Batch 14: financial widget is non-scrolling; ring thick, center blank. */
.bm-dashboard-modern .bm-finance .bm-finance-dual{overflow:hidden!important;min-height:0;align-items:center}
.bm-dashboard-modern .bm-finance .bm-cash-donut{flex-shrink:0;max-height:min(100%,175px);width:min(150px,32%,25vh);aspect-ratio:1}
.bm-dashboard-modern .bm-finance .bm-single-donut{padding:clamp(7px,.65vw,11px)!important}
.bm-finance .bm-single-donut .bm-cash-core{background:white;border-radius:50%}
.bm-total-cash-hidden{display:none!important}
@media(max-width:650px){.bm-dashboard-modern .bm-finance .bm-finance-dual{overflow:visible!important}.bm-dashboard-modern .bm-finance .bm-cash-donut{width:min(145px,42vw)}}

/* Batch 15: substantially thicken the ring inward; blank center retained. */
.bm-dashboard-modern .bm-finance .bm-single-donut{padding:clamp(22px,2vw,30px)!important}
.bm-dashboard-modern .bm-finance .bm-cash-core{min-width:0;min-height:0}
@media(max-width:650px){.bm-dashboard-modern .bm-finance .bm-single-donut{padding:clamp(18px,5vw,26px)!important}}
/* Dashboard now contains multiple module sections: keep one visible page scrollbar. */
@media(min-width:992px){html,body:has(.bm-dashboard-modern){height:auto!important;min-height:100%!important;overflow-y:auto!important}body:has(.bm-dashboard-modern) .wrapper{display:block!important;height:auto!important;min-height:100vh!important;overflow:visible!important}body:has(.bm-dashboard-modern) .content-wrapper.bm-dashboard-modern{display:block!important;height:auto!important;min-height:calc(100vh - 57px)!important;overflow:visible!important}body:has(.bm-dashboard-modern) .content-wrapper.bm-dashboard-modern .content{display:block!important;height:auto!important;overflow:visible!important}body:has(.bm-dashboard-modern) .content-wrapper.bm-dashboard-modern .content>.container-fluid{display:flex!important;height:auto!important;overflow:visible!important;padding-bottom:24px}}
html::-webkit-scrollbar,body::-webkit-scrollbar{width:11px}html::-webkit-scrollbar-thumb,body::-webkit-scrollbar-thumb{background:#aebbd0;border-radius:10px;border:2px solid #f3f6fb}html::-webkit-scrollbar-track,body::-webkit-scrollbar-track{background:#f3f6fb}
/* Final responsive contract: one browser scrollbar; horizontal overflow stays inside tables. */
.bm-dashboard-modern,.bm-dashboard-modern .content,.bm-dashboard-modern .content>.container-fluid{max-width:100%;overflow-x:hidden!important}
.bm-dashboard-modern .bm-panel,.bm-dashboard-modern .bm-module-metric{min-width:0}
.bm-dashboard-modern a:focus-visible,.bm-dashboard-modern button:focus-visible,.bm-dashboard-modern input:focus-visible,.bm-dashboard-modern select:focus-visible{outline:3px solid #74a7f7;outline-offset:2px}
@media(min-width:768px){.bm-dashboard-modern .bm-module-metrics:has(> .bm-module-metric:nth-child(5):last-child){grid-template-columns:repeat(5,minmax(0,1fr))}}
@media(min-width:992px){.bm-dashboard-modern .bm-recovery-panel{grid-column:1;grid-row:2/span 2}.bm-dashboard-modern .bm-sales-panel{grid-column:2;grid-row:2}.bm-dashboard-modern #employee-attendance-summary{grid-column:2;grid-row:3;min-height:180px}.bm-dashboard-modern .bm-sales-panel .bm-module-metrics,.bm-dashboard-modern #employee-attendance-summary .bm-module-metrics{align-content:start;grid-auto-rows:64px}.bm-dashboard-modern .bm-sales-panel .bm-module-metric,.bm-dashboard-modern #employee-attendance-summary .bm-module-metric{height:64px;min-height:64px;padding:8px}.bm-dashboard-modern .bm-recovery-panel .bm-recovery-chart{height:auto;min-height:200px}.bm-dashboard-modern .bm-store-labour-panel{grid-column:1;grid-row:4}.bm-dashboard-modern .bm-cheque-panel{grid-column:2;grid-row:4}.bm-dashboard-modern .bm-activity-panel{grid-column:1;grid-row:5}.bm-dashboard-modern .bm-task-panel{grid-column:2;grid-row:5}}
@media(max-width:767.98px){
 .bm-dashboard-modern .content-header .breadcrumb{float:none!important;margin-top:6px;padding:0}
 .bm-dashboard-modern .bm-kpis,.bm-dashboard-modern .bm-module-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}
 .bm-dashboard-modern .bm-panel-heading{align-items:flex-start;gap:8px;flex-wrap:wrap}
 .bm-dashboard-modern .bm-crm-open{align-self:stretch;margin:7px 0;text-align:center}
 .bm-dashboard-modern .bm-lead-period-filter{justify-content:flex-start;width:100%}
 .bm-dashboard-modern .bm-lead-period-filter select,.bm-dashboard-modern .bm-custom-period input{max-width:100%}
 .bm-dashboard-modern .modal-dialog{margin:.5rem}
}
@media(max-width:420px){
 .bm-dashboard-modern .bm-kpis,.bm-dashboard-modern .bm-module-metrics{grid-template-columns:1fr}
 .bm-dashboard-modern .bm-kpi,.bm-dashboard-modern .bm-module-metric{min-height:64px}
}

/* Dashboard polish: distinct surfaces, compact finance matrix and live lead search. */
.bm-dashboard-modern .bm-kpi:nth-child(1){background:linear-gradient(135deg,#ffffff 54%,#eef1ff);border-radius:18px 18px 18px 5px}
.bm-dashboard-modern .bm-kpi:nth-child(2){background:linear-gradient(135deg,#ffffff 54%,#e8fbf4);border-radius:18px 5px 18px 18px}
.bm-dashboard-modern .bm-kpi:nth-child(3){background:linear-gradient(135deg,#ffffff 54%,#eaf4ff);border-radius:22px 8px 22px 8px}
.bm-dashboard-modern .bm-kpi:nth-child(4){background:linear-gradient(135deg,#ffffff 54%,#fff4e3);border-radius:8px 22px 8px 22px}
.bm-dashboard-modern .bm-kpi:nth-child(5){background:linear-gradient(135deg,#ffffff 54%,#edf5ff);border-radius:5px 18px 18px 18px}
.bm-dashboard-modern .bm-panel{border:0;box-shadow:0 8px 24px rgba(28,55,90,.09)}
.bm-dashboard-modern .bm-panel:nth-of-type(4n+1){background:linear-gradient(145deg,#fff 70%,#f4f8ff)}
.bm-dashboard-modern .bm-panel:nth-of-type(4n+2){background:linear-gradient(145deg,#fff 70%,#f2fbf8)}
.bm-dashboard-modern .bm-panel:nth-of-type(4n+3){background:linear-gradient(145deg,#fff 70%,#fff8ef)}
.bm-dashboard-modern .bm-panel-heading{border-bottom-color:#e8eef6}
.bm-cash-legend{max-width:none!important;display:block!important}
.bm-cash-primary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:7px;margin-bottom:8px}
.bm-cash-primary .bm-cash-detail{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:6px;padding:6px 3px;border:0;border-bottom:1px solid #dfe6ef;background:transparent;border-radius:0}
.bm-cash-primary .bm-cash-total{border-top:0;grid-column:auto;grid-template-columns:minmax(0,1fr) auto}
.bm-cash-primary .bm-cash-detail strong{font-size:clamp(12px,1vw,16px);color:#173450}
.bm-cash-primary .bm-cash-detail small{display:none!important}
.bm-finance-metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px}
.bm-finance-metrics .bm-finance-value{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:6px;padding:6px 3px;border:0;border-bottom:1px solid #e3e9f1;border-radius:0;background:transparent}
.bm-finance-metrics .bm-finance-value span{font-size:11px;color:#657890;white-space:normal}
.bm-finance-metrics .bm-finance-value strong{font-size:clamp(12px,.92vw,15px);color:#183652}
.bm-cash-warning{display:block;margin-top:8px;font-size:11px!important;line-height:1.35;white-space:normal!important;font-weight:650}
.bm-actions .bm-panel-body{overflow:visible}
.bm-lead-search input{height:43px;border:1px solid #d6e1ef;border-radius:12px;padding-left:14px;box-shadow:0 3px 10px rgba(28,55,90,.04);transition:border-color .15s,box-shadow .15s}
.bm-lead-search input:focus{border-color:#4f8ff7;box-shadow:0 0 0 3px rgba(79,143,247,.14)}
.bm-lead-result{margin-top:10px;border:1px solid #dce7f4;border-radius:14px;background:linear-gradient(135deg,#fff,#f5f9ff);box-shadow:0 8px 18px rgba(31,65,110,.09);overflow:hidden}
.bm-lead-result-head{display:flex;align-items:center;gap:10px;padding:12px;background:linear-gradient(100deg,#163454,#2463a3);color:#fff}
.bm-lead-avatar{width:38px;height:38px;border-radius:12px;display:grid;place-items:center;background:#ffffff25;font-size:17px}
.bm-lead-result-head strong{display:block;font-size:14px}.bm-lead-result-head small{opacity:.78}
.bm-lead-result-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;padding:11px}
.bm-lead-result-grid div{padding:7px 9px;background:#fff;border:1px solid #e6edf6;border-radius:9px;min-width:0}
.bm-lead-result-grid span{display:block;font-size:9px;text-transform:uppercase;letter-spacing:.05em;color:#7a8ba1}.bm-lead-result-grid b{display:block;font-size:12px;color:#233b59;overflow-wrap:anywhere}
.bm-lead-result-actions{display:flex;gap:7px;padding:0 11px 11px}.bm-lead-result-actions .btn{border-radius:9px;font-size:11px;font-weight:700}
.bm-lead-state{margin-top:8px;padding:10px 12px;border-radius:10px;font-size:12px}.bm-lead-state.loading{background:#edf5ff;color:#2862a8}.bm-lead-state.error{background:#fff0f1;color:#b3263b}
@media(max-width:767px){.bm-cash-primary,.bm-finance-metrics{grid-template-columns:1fr}.bm-lead-result-grid{grid-template-columns:1fr}}
</style>
@endpush
@section('content')
    <div class="content-wrapper bm-dashboard-modern">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Dashboard</h1>
                    </div><!-- /.col -->
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active">{{ $title }}</li>
                        </ol>
                    </div><!-- /.col -->
                </div><!-- /.row -->
            </div><!-- /.container-fluid -->
        </div>
        <section class="content">
            <div class="container-fluid">
                
<div class="bm-kpis">
@foreach ([
 ['value'=>$today_leads,'label'=>'New Leads','icon'=>'fa-user','href'=>route('dashboard.leads_by_users_report'),'tone'=>'indigo','id'=>'bm-new-leads-count'],
 ['value'=>$completed_followups,'label'=>'Completed Follow-ups','icon'=>'fa-check-circle','href'=>route('crm.todayLeadWorkReport',['mode'=>'completed','date_range'=>$lead_period_from.' to '.$lead_period_to]),'tone'=>'green','id'=>'bm-completed-followups-count'],
 ['value'=>$today_followups,'label'=>'Today Follow-ups','icon'=>'fa-comments','href'=>route('crm.todayLeadWorkReport',['mode'=>'today','date_range'=>now()->toDateString().' to '.now()->toDateString()]),'tone'=>'blue','id'=>'bm-today-followups-count'],
 ['value'=>$pending_overdue_followups,'label'=>'Pending + Overdue','icon'=>'fa-tasks','href'=>route('crm.todayLeadWorkReport',['mode'=>'pending_overdue','date_range'=>$lead_period_from.' to '.$lead_period_to]),'tone'=>'amber','id'=>'bm-pending-overdue-count'],
 ['value'=>$approval_metrics['total'],'label'=>'Pending Approvals','icon'=>'fa-clipboard-check','href'=>route('approvals.finance.index'),'tone'=>'blue']
] as $kpi)
<a class="bm-kpi bm-{{ $kpi['tone'] }}" href="{{ $kpi['href'] }}">
 <div class="bm-kpi-top"><div class="bm-kpi-icon"><i class="fas {{ $kpi['icon'] }}"></i></div>
 <div class="bm-kpi-value bm-counter" @isset($kpi['id']) id="{{ $kpi['id'] }}" @endisset data-number="{{ $kpi['value'] }}">{{ number_format((float)$kpi['value'],is_numeric($kpi['value']) && floor((float)$kpi['value'])!=(float)$kpi['value'] ? 2 : 0) }}</div></div>
 <div class="bm-kpi-bottom"><div class="bm-kpi-label">{{ $kpi['label'] }}</div><span class="bm-kpi-action">View report <i class="fas fa-arrow-right"></i></span></div>
</a>
@endforeach
</div>
<div class="bm-dashboard-grid">
 <section class="bm-panel bm-finance">
  <div class="bm-panel-heading"><h5>Financial Summary</h5><span>Ledger balances</span></div>
  @php
    $bmHandCash = (float) $total_blance;
    $bmBankCash = (float) (-$total_bank_account_data);
    $bmTotalCash = $bmHandCash + $bmBankCash;
    $bmPositiveSum = max(0, $bmHandCash) + max(0, $bmBankCash);
    $bmHandPct = $bmPositiveSum > 0 ? max(0, $bmHandCash) / $bmPositiveSum * 100 : 0;
    $bmBankPct = $bmPositiveSum > 0 ? max(0, $bmBankCash) / $bmPositiveSum * 100 : 0;
  @endphp
  <div class="bm-panel-body bm-finance-dual" id="bm-financial-widget"
    data-hand="{{ $bmHandCash }}" data-bank="{{ $bmBankCash }}" data-total="{{ $bmTotalCash }}">
    <div class="bm-cash-donut" role="img" aria-label="Total Cash {{ number_format($bmTotalCash,2) }}, Hand Cash {{ number_format($bmHandCash,2) }}, Bank Account {{ number_format($bmBankCash,2) }}">
      <div class="bm-cash-inner bm-single-donut" style="--hand-share:{{ $bmHandPct }}%;--bank-share:{{ $bmBankPct }}%;--hand-color:{{ $bmHandCash <= $bmBankCash ? '#991b1b' : '#0cae87' }};--bank-color:{{ $bmBankCash < $bmHandCash ? '#991b1b' : '#198add' }}" data-type="split" tabindex="0" aria-label="Hand Cash and Bank Account distribution">
        <div class="bm-cash-core" aria-hidden="true"></div>
      </div>
      <strong id="totalCash" class="bm-total-cash-hidden">{{ number_format($bmTotalCash,2) }}</strong><div class="bm-finance-tooltip" id="bm-finance-tooltip" role="status" hidden></div>
    </div>
    <div class="bm-cash-legend">
     <div class="bm-cash-primary">
      <div class="bm-cash-detail" data-type="hand" tabindex="0"><span><i class="bm-dot bm-dot-hand" style="background:{{ $bmHandCash <= $bmBankCash ? '#991b1b' : '#0cae87' }}"></i>Hand Cash</span><strong id="handCash">{{ number_format($bmHandCash,2) }}</strong><small>{{ number_format($bmHandPct,2) }}%</small></div>
      <div class="bm-cash-detail" data-type="bank" tabindex="0"><span><i class="bm-dot bm-dot-bank" style="background:{{ $bmBankCash < $bmHandCash ? '#991b1b' : '#198add' }}"></i>Bank Account</span><strong id="bmBankCash">{{ number_format($bmBankCash,2) }}</strong><small>{{ number_format($bmBankPct,2) }}%</small></div>
      <div class="bm-cash-detail bm-cash-total" data-type="total" tabindex="0"><span><i class="bm-dot bm-dot-total"></i>Total Cash</span><strong id="bmCashLegendTotal">{{ number_format($bmTotalCash,2) }}</strong></div>
     </div>
     <div class="bm-finance-metrics">
      @foreach(['receivables'=>'Receivables','payables'=>'Payables','cash_receipts'=>"Today's Cash Receipts",'cash_payments'=>"Today's Cash Payments",'bank_receipts'=>"Today's Bank Receipts",'bank_payments'=>"Today's Bank Payments"] as $key=>$label)
       <div class="bm-cash-detail bm-finance-value"><span>{{ $label }}</span><strong>Rs {{ number_format($finance_metrics[$key],2) }}</strong></div>
      @endforeach
     </div>
      @if($bmHandCash < 0 || $bmBankCash < 0)<small class="bm-cash-warning">Negative balances shown separately; ring proportions use non-negative components only.</small>@endif
    </div>
  </div>
 </section>
 <section class="bm-panel bm-actions">
  <div class="bm-panel-heading"><h5>Lead Search</h5><span>Quick action</span></div>
  <div class="bm-panel-body bm-action-body">
   @can('lead search')
   <div class="bm-action-block" id="bm-live-lead-search" data-url="{{ route('lead.search') }}" data-edit-url="{{ url(config('adminPrefix') . 'admin/crm/lead') }}" data-current-user="{{ Auth::id() }}" data-super-admin="{{ Auth::user()->hasRole('superadmin') ? '1' : '0' }}"><h6>Search Lead By Number</h6><div class="bm-lead-search"><input name="number" id="bm-lead-number" type="text" inputmode="numeric" autocomplete="off" maxlength="11" class="form-control" placeholder="Type mobile number..." aria-label="Search lead by mobile number"><button type="button" id="bm-search-number" class="bm-search-icon" aria-label="Search lead"><i class="fas fa-search"></i></button></div><div id="bm-lead-result" aria-live="polite"></div></div>
   @endcan
   <button type="button" class="btn btn-sm btn-outline-primary bm-crm-open" id="bm-crm-open" data-url="{{ route('dashboard.crm_snapshot') }}"><i class="fas fa-chart-line"></i> CRM Details</button>
  </div>
 </section>
 @can('report cashbook')
 @can('stock module')
 <section class="bm-panel bm-module-panel bm-recovery-panel">
  <div class="bm-panel-heading"><h5>Monthly Recovery</h5><a href="{{ route('booking.plot.recovery_list') }}">Recovery report</a></div>
  @php($recoveryMax = max(1, collect($recovery_monthly)->max('value')))
  <div class="bm-panel-body bm-recovery-chart" aria-label="Month-wise project recovery bar chart">
   @foreach($recovery_monthly as $month)
    <a class="bm-recovery-bar-wrap" href="{{ route('booking.plot.recovery_list') }}" title="{{ $month['label'] }}: Rs {{ number_format($month['value'],2) }}">
     <span class="bm-recovery-value">{{ $month['value'] ? number_format($month['value']/1000,1).'k' : '0' }}</span><span class="bm-recovery-bar" style="height:{{ max(2,($month['value']/$recoveryMax)*100) }}%"></span><small>{{ $month['label'] }}</small>
    </a>
   @endforeach
  </div>
 </section>
 @endcan
 @can('view inventory')
 <section class="bm-panel bm-module-panel bm-sales-panel">
  <div class="bm-panel-heading"><h5>Sales & Plot Inventory</h5><a href="{{ route('booking.plot.inventory') }}">View inventory</a></div>
  <div class="bm-panel-body bm-module-metrics">
   @foreach(['total'=>'Total Plots','available'=>'Available','hold'=>'On Hold','sold'=>'Sold','cancelled'=>'Cancelled'] as $key=>$label)
    <a class="bm-module-metric" href="{{ route('booking.plot.inventory') }}"><strong>{{ number_format($plot_metrics[$key]) }}</strong><span>{{ $label }}</span></a>
   @endforeach
  </div>
 </section>
 @endcan
 <section class="bm-panel bm-module-panel bm-store-labour-panel">
  <div class="bm-panel-heading"><h5>Store Inventory & Labour Summary</h5><span><a href="{{ route('stocks.index') }}">Stock</a> · <a href="{{ route('labours.index') }}">Labour</a></span></div>
  <div class="bm-panel-body bm-module-metrics">
   @foreach(['quantity'=>'On-hand Quantity','value'=>'Stock Value (Rs)','inward'=>"Today's Inward",'outward'=>"Today's Outward"] as $key=>$label)
    <a class="bm-module-metric" href="{{ route('stocks.index') }}"><strong>{{ number_format($stock_metrics[$key],$key==='quantity'||$key==='inward'||$key==='outward'?3:($key==='value'?2:0)) }}</strong><span>{{ $label }}</span></a>
   @endforeach
   @can('labour module')
    @foreach(['active'=>'Active Workers','present'=>"Today's Present",'payable'=>'Payable (Rs)'] as $key=>$label)
     <a class="bm-module-metric" href="{{ route('labours.index') }}"><strong>{{ number_format($labour_metrics[$key],$key==='payable'?2:0) }}</strong><span>{{ $label }}</span></a>
    @endforeach
   @endcan
  </div>
 </section>
 @endcan
 @can('read attendance')
 <section class="bm-panel bm-module-panel" id="employee-attendance-summary">
  <div class="bm-panel-heading"><h5>Employee Attendance</h5><span>Today</span></div>
  <div class="bm-panel-body bm-module-metrics">
   @foreach(['total'=>'Total Employees','present'=>'Present','absent'=>'Absent','missing_punch'=>'Missing Punch-out','late_arrival'=>'Late Arrival'] as $key=>$label)
    <a class="bm-module-metric" href="#today-punch-records"><strong>{{ $attendance_metrics[$key] }}</strong><span>{{ $label }}</span></a>
   @endforeach
  </div>
 </section>
 @endcan
 <section class="bm-panel bm-module-panel bm-activity-panel">
  <div class="bm-panel-heading"><h5>Activity Notifications</h5><span>Action required</span></div>
  <div class="bm-panel-body bm-module-metrics">
   @can('read voucher')
   <a class="bm-module-metric" href="{{ route('approvals.finance.index') }}"><strong>{{ $approval_metrics['total'] }}</strong><span>Pending Approvals</span></a>
   @endcan
   <a class="bm-module-metric" href="{{ route('report.lead.index') }}"><strong>{{ $pending_overdue_followups }}</strong><span>Pending + Overdue CRM</span></a>
   <a class="bm-module-metric" href="{{ route('employee-tasks.index',['status'=>'overdue']) }}"><strong>{{ $task_metrics['metrics']['overdue'] }}</strong><span>Overdue Tasks</span></a>
   <a class="bm-module-metric" href="{{ route('employee-tasks.index',['status'=>'rework']) }}"><strong>{{ $task_metrics['metrics']['rework'] }}</strong><span>Tasks Requiring Rework</span></a>
   @can('read attendance')
   <a class="bm-module-metric" href="#today-punch-records"><strong>{{ $attendance_metrics['missing_punch'] }}</strong><span>Missing Punch-outs</span></a>
   @endcan
  </div>
 </section>
 @can('cheque report')
 <section class="bm-panel bm-module-panel bm-cheque-panel">
  <div class="bm-panel-heading"><h5>Cheque And Online Tracking</h5><a href="{{ route('report.check') }}">Cheque report</a></div>
  <div class="bm-panel-body bm-module-metrics">
   @foreach(['pending'=>'Pending','passed'=>'Passed','memo'=>'Memo','received_today'=>'Received Today','cleared_today'=>'Cleared Today','processed'=>'Processed'] as $key=>$label)
    <a class="bm-module-metric" href="{{ route('report.check') }}"><strong>{{ $payment_metrics['cheques'][$key] ?? 0 }}</strong><span>{{ $label }}</span></a>
   @endforeach
   <a class="bm-module-metric" href="{{ route('finance.voucher.in') }}"><strong>{{ $payment_metrics['online']['total'] ?? 0 }}</strong><span>Online Transactions</span></a>
  </div>
 </section>
 @endcan
 <section class="bm-panel bm-module-panel bm-task-panel">
  <div class="bm-panel-heading"><h5>Employee Tasks</h5><a href="{{ route('employee-tasks.index') }}">Task management</a></div>
  <div class="bm-panel-body bm-module-metrics">
   @foreach(['pending'=>'Pending','completed'=>'Completed','overdue'=>'Overdue','rework'=>'Rework','cancelled'=>'Cancelled'] as $key=>$label)
    <a class="bm-module-metric" href="{{ route('employee-tasks.index',['status'=>in_array($key,['total'])?'':$key]) }}"><strong>{{ $task_metrics['metrics'][$key] }}</strong><span>{{ $label }}</span></a>
   @endforeach
  </div>
 </section>
 @if(count($task_metrics['performance']))
 <section class="bm-panel bm-module-panel">
  <div class="bm-panel-heading"><h5>Employee Task Performance</h5><span>Top 10</span></div>
  <div class="bm-panel-body bm-table-scroll"><table class="table table-sm"><thead><tr><th>Employee</th><th>Assigned</th><th>Completed</th><th>Pending</th><th>In Progress</th><th>Overdue</th><th>On Time</th><th>Completion %</th></tr></thead><tbody>@foreach($task_metrics['performance'] as $row)<tr><td>{{ $row['employee'] }}</td><td>{{ $row['assigned'] }}</td><td>{{ $row['completed'] }}</td><td>{{ $row['pending'] }}</td><td>{{ $row['in_progress'] }}</td><td>{{ $row['overdue'] }}</td><td>{{ $row['on_time'] }}</td><td>{{ $row['completion_rate'] }}%</td></tr>@endforeach</tbody></table></div>
 </section>
 @endif
 <section class="bm-panel bm-dasticash">
  <div class="bm-panel-heading"><h5>Dasticash Summary</h5><button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addDasticashModal"><i class="fas fa-plus"></i> Add Dasticash</button></div>
  <div class="bm-panel-body bm-table-scroll"><table class="table table-hover" id="dasticashTable"><thead><tr><th>ID</th><th>Name</th><th>Amount</th><th>Description</th><th>Actions</th></tr></thead><tbody>
  @foreach($dasticashData as $key=>$item)
  <tr id="row-{{ $item->id }}"><td>{{ $key+1 }}</td><td class="name-cell">{{ $item->name }}</td><td class="amount-cell">{{ $item->amount }}</td><td class="desc-cell">{{ $item->description }}</td><td class="bm-actions-cell">
  <a href="javascript:void(0)" class="btn btn-sm btn-info edit-btn" data-id="{{ $item->id }}" data-name="{{ $item->name }}" data-amount="{{ $item->amount }}" data-description="{{ $item->description }}"><i class="fas fa-edit"></i></a>
  <button type="button" class="btn btn-sm btn-danger delete-btn" data-id="{{ $item->id }}" data-url="{{ route('dasticash.destroy',$item->id) }}"><i class="fas fa-trash"></i></button></td></tr>
  @endforeach
  </tbody></table></div>
 </section>
 @can('read attendance')
 <section class="bm-panel bm-punches" id="today-punch-records">
  <div class="bm-panel-heading"><h5>Punch-in Records for Today</h5><span>Today · Employee records</span></div>
  <div class="bm-panel-body bm-table-scroll">
  <form method="POST" action="/admin/punch" enctype="multipart/form-data" class="bm-punch-inline">@csrf
   <select class="form-control form-control-sm" name="user_id">@foreach($users_list as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select>
   @if($display_date)<input type="datetime-local" class="form-control form-control-sm" name="punch_time" id="date_time">@endif
   <button type="submit" class="btn btn-primary btn-sm" id="punch_button"><i class="fas fa-plus"></i> Punch In</button><div id="soft-alert" class="alert" style="display:none"></div>
  </form>
  <table class="table table-hover"><thead><tr><th>Date</th><th>User Name</th><th>Punch In</th><th>Punch Out</th><th>Total Hours</th></tr></thead><tbody>
  @foreach($today_punches as $punch)
  <tr><td>{{ date('Y-m-d',strtotime($punch->punch_in)) }}</td><td>{{ $punch->user->name }}</td><td>{{ date('H:i:s',strtotime($punch->punch_in)) }}</td><td>{{ $punch->punch_out ? date('H:i:s',strtotime($punch->punch_out)) : 'N/A' }}</td><td>
  @if($punch->punch_out){{ gmdate('H:i:s',max(0,strtotime($punch->punch_out)-strtotime($punch->punch_in))) }}@else N/A @endif
  </td></tr>
  @endforeach
  </tbody></table></div>
 </section>
 @endcan
</div>
<div class="modal fade" id="bmCrmModal" tabindex="-1" role="dialog" aria-labelledby="bmCrmTitle" aria-hidden="true">
 <div class="modal-dialog modal-xl" role="document"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="bmCrmTitle">CRM Details — selected period</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
  <div class="modal-body">
   <p class="text-muted">Completed means a saved follow-up activity. Pending/overdue includes only open CRM statuses and excludes Invalid Number, Not Interested and Sale Done.</p>
   <form method="GET" action="{{ route('home') }}" class="bm-lead-period-filter mb-3" id="bm-lead-period-form" aria-label="CRM date period">
    <label for="bm-lead-period">CRM period</label><select id="bm-lead-period" name="lead_period">@foreach (['today'=>'Today','yesterday'=>'Yesterday','this_week'=>'This Week','last_week'=>'Last Week','this_month'=>'This Month','last_month'=>'Last Month','custom'=>'Custom Date Range'] as $periodValue=>$periodLabel)<option value="{{ $periodValue }}" {{ $lead_period === $periodValue ? 'selected' : '' }}>{{ $periodLabel }}</option>@endforeach</select>
    <span id="bm-custom-period" class="bm-custom-period" {{ $lead_period === 'custom' ? '' : 'hidden' }}><input type="date" name="lead_from" aria-label="From date" value="{{ $lead_period_from }}"><input type="date" name="lead_to" aria-label="To date" value="{{ $lead_period_to }}"><button type="submit" class="btn btn-sm btn-primary">Apply</button></span><span id="bm-lead-period-bounds" aria-live="polite">{{ $lead_period_from }} to {{ $lead_period_to }}</span><span id="bm-lead-period-error" role="alert" hidden></span>
   </form>
   <div class="form-inline mb-2"><label for="bm-crm-project" class="mr-2">Project</label><select class="form-control form-control-sm mr-2" id="bm-crm-project">@foreach($crm_projects as $crmProject)<option value="{{ $crmProject->id }}" {{ $crmProject->id == getSelectedTown() ? 'selected' : '' }}>{{ $crmProject->project }}</option>@endforeach</select><label for="bm-crm-employee" class="mr-2">Employee</label><select class="form-control form-control-sm mr-2" id="bm-crm-employee"><option value="">All employees</option>@foreach($users_list as $employee)<option value="{{ $employee->id }}">{{ $employee->name }}</option>@endforeach</select><label for="bm-crm-status" class="mr-2">Status</label><select class="form-control form-control-sm mr-2" id="bm-crm-status"><option value="all">All</option><option value="new">New Leads</option><option value="completed">Completed</option><option value="today">Today</option><option value="pending_overdue">Pending + Overdue</option></select><button type="button" class="btn btn-sm btn-primary" id="bm-crm-apply">Apply Filter</button></div><div class="bm-crm-kpis" id="bm-crm-kpis" aria-live="polite"></div>
   <div id="bm-crm-error" role="alert" class="text-danger"></div><h6 class="mt-3">Employee-wise CRM Performance</h6><div class="table-responsive bm-crm-table"><table class="table table-sm table-striped"><thead><tr><th>Employee</th><th>Assigned Leads</th><th>New Leads</th><th>Completed Follow-ups</th><th>Due on End Date</th><th>Overdue on End Date</th></tr></thead><tbody id="bm-crm-employee-rows"></tbody></table></div>
   <div class="table-responsive bm-crm-table"><table class="table table-sm table-striped"><thead><tr><th>Lead ID</th><th>Customer</th><th>Mobile</th><th>Project</th><th>Assigned Employee</th><th>Created</th><th>Last Activity</th><th>Next Follow-up</th><th>Status</th><th>Remarks</th></tr></thead><tbody id="bm-crm-rows"></tbody></table></div>
   <div class="d-flex justify-content-between align-items-center mt-2"><small id="bm-crm-page-info" class="text-muted"></small><div><button type="button" class="btn btn-sm btn-outline-primary" id="bm-crm-prev">Previous</button><button type="button" class="btn btn-sm btn-outline-primary" id="bm-crm-next">Next</button></div></div>
  </div>
 </div></div>
</div>

<!-- Add Dasticash Modal -->
                    <div class="modal fade" id="addDasticashModal" tabindex="-1" role="dialog"
                        aria-labelledby="addDasticashModalLabel" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                            <form action="{{ route('dasticash.store') }}" method="POST">
                                @csrf
                                <div class="modal-content">
                                    <div class="modal-header bg-success text-white">
                                        <h5 class="modal-title" id="addDasticashModalLabel">Add Dasticash</h5>
                                        <button type="button" class="close text-white" data-dismiss="modal"
                                            aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label>Name</label>
                                            <input type="text" name="name" class="form-control" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Amount</label>
                                            <input type="text" name="amount" class="form-control">
                                        </div>
                                        <div class="form-group">
                                            <label>Description</label>
                                            <textarea name="description" class="form-control" rows="3"></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-success">Save</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <!-- Edit Dasticash Modal -->
                    <div class="modal fade" id="editDasticashModal" tabindex="-1" role="dialog"
                        aria-labelledby="editDasticashModalLabel" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                            <form id="editDasticashForm" method="POST">
                                @csrf
                                @method('PUT')
                                <div class="modal-content">
                                    <div class="modal-header bg-info text-white">
                                        <h5 class="modal-title" id="editDasticashModalLabel">Edit Dasticash</h5>
                                        <button type="button" class="close text-white" data-dismiss="modal"
                                            aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" id="edit-id">
                                        <div class="form-group">
                                            <label>Name</label>
                                            <input type="text" name="name" id="edit-name" class="form-control" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Amount</label>
                                            <input type="text" name="amount" id="edit-amount" class="form-control">
                                        </div>
                                        <div class="form-group">
                                            <label>Description</label>
                                            <textarea name="description" id="edit-description" class="form-control"
                                                rows="3"></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-info">Update</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>
        </section>
    </div>
@endsection
@section('js')

<script>
(function(){
 const root=$('#bm-live-lead-search')[0];
 if(!root)return;
 const input=$('#bm-lead-number')[0];
 const button=$('#bm-search-number')[0];
 const output=$('#bm-lead-result')[0];
 let timer=null,request=null,last='';
 const escape=value=>String(value??'').replace(/[&<>'"]/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
 function state(message,type){output.innerHTML='<div class="bm-lead-state '+type+'">'+escape(message)+'</div>';}
 function render(data){
   const assigned=Array.isArray(data.assignTo)&&data.assignTo.length?data.assignTo.map(user=>user.name).join(', '):'Unassigned';
   const allowed=root.dataset.superAdmin==='1'||(data.assignTo||[]).some(user=>String(user.id)===root.dataset.currentUser);
   const action=allowed?'<a class="btn btn-primary" href="'+escape(root.dataset.editUrl)+'"><i class="fas fa-edit"></i> Open Lead</a>':'';
   output.innerHTML='<article class="bm-lead-result">'+
    '<div class="bm-lead-result-head"><div class="bm-lead-avatar"><i class="fas fa-user"></i></div><div><strong>'+escape(data.name||'Unnamed Lead')+'</strong><small>'+escape(data.active||'Lead record')+'</small></div></div>'+
    '<div class="bm-lead-result-grid"><div><span>Project</span><b>'+escape(data.project||'No Project')+'</b></div><div><span>Follow-up Status</span><b>'+escape(data.follow_status||'Not set')+'</b></div><div><span>Assigned To</span><b>'+escape(assigned)+'</b></div><div><span>Last Updated</span><b>'+escape(data.updated_at||'—')+'</b></div></div>'+
    (action?'<div class="bm-lead-result-actions">'+action+'</div>':'')+'</article>';
 }
 async function search(){
   const number=input.value.replace(/\D/g,'').slice(0,11);input.value=number;
   if(number.length<11){last='';if(number.length)state('Enter complete 11-digit mobile number.','loading');else output.textContent='';return;}
   if(number===last)return;last=number;
   if(request)request.abort();state('Searching lead…','loading');button.disabled=true;
   try{
    const current=request=$.ajax({
     url:$(root).data('url'),method:'POST',data:{number,_token:'{{ csrf_token() }}'},
     dataType:'json',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}
    });
    const payload=await current;
    if(!payload.data)throw new Error('No lead record returned.');
    render(payload.data);
   }catch(error){
    if(error.statusText==='abort')return;
    if(error.status===404){state('No lead found against this mobile number.','error');return;}
    state(error.message||'Search could not be completed.','error');
   }finally{button.disabled=false;request=null;}
 }
 $(input).on('input',()=>{clearTimeout(timer);timer=setTimeout(search,350);});
 $(input).on('keydown',event=>{if(event.key==='Enter'){event.preventDefault();clearTimeout(timer);search();}});
 $(button).on('click',()=>{clearTimeout(timer);search();});
})();
</script>

<script>
(function(){
 const open=$('#bm-crm-open')[0];
 if(!open)return;
 const form=$('#bm-lead-period-form')[0];
 const rowHost=$('#bm-crm-rows')[0];
 const error=$('#bm-crm-error')[0];
 const summary=$('#bm-crm-kpis')[0];
 const labels=[['new_leads','New Leads'],['completed_followups','Completed Follow-ups'],['today_due','Today Follow-ups'],['pending_overdue_combined','Pending + Overdue'],['due_on_period_end','Due on Selected End Date'],['overdue_as_of_period_end','Overdue by Selected End Date']];
 const cell=(tr,value)=>{const td=document.createElement('td');td.textContent=value??'—';tr.appendChild(td);};
 let currentPage=1,lastPage=1;
 async function loadCrm(page=1){
   if(!window.jQuery){return;}
   window.jQuery('#bmCrmModal').modal('show');
   summary.textContent='';rowHost.textContent='';$('#bm-crm-employee-rows')[0].textContent='';error.textContent='Loading CRM data…';
   const bounds=$('#bm-lead-period-bounds')[0].textContent.trim().split(' to ');
   if(bounds.length!==2){error.textContent='Select a date range first.';return;}
   try{
     const query=new URLSearchParams({from:bounds[0],to:bounds[1],page:String(page),per_page:'20'});const employee=$('#bm-crm-employee')[0];if(employee && employee.value)query.set('employee_id',employee.value);const project=$('#bm-crm-project')[0];if(project && project.value)query.set('project_id',project.value);const status=$('#bm-crm-status')[0];if(status)query.set('status',status.value);
     const data=await $.ajax({url:$(open).data('url'),method:'GET',data:query.toString(),
       dataType:'json',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
     error.textContent='';
     labels.forEach(([key,label])=>{const box=document.createElement('div');const value=document.createElement('strong');value.textContent=Number(data[key]||0).toLocaleString('en-PK');const caption=document.createElement('span');caption.textContent=label;box.append(value,caption);summary.appendChild(box);});
     (data.rows||[]).forEach(row=>{const tr=document.createElement('tr');['id','customer','mobile','project','assigned','created_at','last_activity','next_follow_up','status','remarks'].forEach(k=>cell(tr,row[k]));rowHost.appendChild(tr);});
     const employeeRows=$('#bm-crm-employee-rows')[0];
     (data.employee_summary||[]).forEach(row=>{const tr=document.createElement('tr');['employee','assigned_leads','new_leads','completed_followups','due_on_period_end','overdue_as_of_period_end'].forEach(key=>cell(tr,row[key]));employeeRows.appendChild(tr);});
     currentPage=data.pagination?.page||1;lastPage=data.pagination?.last_page||1;$('#bm-crm-page-info')[0].textContent='Page '+currentPage+' of '+lastPage+' - '+(data.pagination?.total||0)+' records';$('#bm-crm-prev')[0].disabled=currentPage<=1;$('#bm-crm-next')[0].disabled=currentPage>=lastPage;
     if(!data.rows?.length)error.textContent='No matching leads for this period.';
   }catch(e){error.textContent=e.message;}
 }
 $(open).on('click',()=>{$('#bmCrmModal').modal('show');loadCrm(1);});
 $('#bm-crm-apply').on('click',()=>loadCrm(1));
 $('#bm-crm-prev').on('click',()=>{if(currentPage>1)loadCrm(currentPage-1);});
 $('#bm-crm-next').on('click',()=>{if(currentPage<lastPage)loadCrm(currentPage+1);});
})();
</script>


<script>
(function(){
 const host=$('#bm-financial-widget')[0];
 if(!host)return;
 const tooltip=$('#bm-finance-tooltip')[0];
 document.body.appendChild(tooltip); // Escape clipped dashboard panel for visible hover values.
 const number=(v)=>Number(v).toLocaleString('en-PK',{minimumFractionDigits:2,maximumFractionDigits:2});
 function current(){
   const hand=Number(host.dataset.hand)||0,bank=Number(host.dataset.bank)||0;
   const positive=Math.max(0,hand)+Math.max(0,bank);
   return {hand,bank,total:hand+bank,hp:positive?Math.max(0,hand)/positive*100:0,bp:positive?Math.max(0,bank)/positive*100:0};
 }
 function update(){const d=current();const inner=host.querySelector('.bm-cash-inner');
 inner.style.setProperty('--hand-share',d.hp+'%');inner.style.setProperty('--bank-share',d.bp+'%');
 const minAnglePercent=2/3.6;let handDisplay=d.hp;
 if(d.hp>0&&d.hp<minAnglePercent)handDisplay=minAnglePercent;
 if(d.bp>0&&d.bp<minAnglePercent)handDisplay=100-minAnglePercent;
 inner.style.setProperty('--hand-display-share',handDisplay+'%');


 inner.style.setProperty('--hand-color',d.hand<=d.bank?'#991b1b':'#0cae87');
 inner.style.setProperty('--bank-color',d.bank<d.hand?'#991b1b':'#198add');
 const dots=host.querySelectorAll('.bm-cash-detail .bm-dot');if(dots[0])dots[0].style.background=d.hand<=d.bank?'#991b1b':'#0cae87';if(dots[1])dots[1].style.background=d.bank<d.hand?'#991b1b':'#198add';
 [['handCash',d.hand],['bmBankCash',d.bank],['totalCash',d.total],['bmCashLegendTotal',d.total]].forEach(([id,n])=>{const el=document.getElementById(id);if(el)el.textContent=number(n)});
 const small=host.querySelectorAll('.bm-cash-detail small');if(small[0])small[0].textContent=d.hp.toFixed(2)+'%';if(small[1])small[1].textContent=d.bp.toFixed(2)+'%';
 }
 function show(type){const d=current(),name=type==='hand'?'Hand Cash':type==='bank'?'Bank Account':'Total Cash';
 const amount=type==='hand'?d.hand:type==='bank'?d.bank:d.total,percent=type==='hand'?d.hp:type==='bank'?d.bp:100;
 tooltip.textContent=name+': Rs. '+number(amount)+(type==='total'?'':' ('+percent.toFixed(2)+'% of positive balances)');
 tooltip.hidden=false;
 }
 function place(x,y){const margin=12;tooltip.style.left=Math.max(8,Math.min(x+14,window.innerWidth-260))+'px';tooltip.style.top=Math.max(margin,Math.min(y-53,window.innerHeight-70))+'px';}
 function ringSegment(event,el){
   const rect=el.getBoundingClientRect(),x=event.clientX-rect.left-rect.width/2,y=event.clientY-rect.top-rect.height/2;
   const dist=Math.hypot(x,y),innerRadius=Math.min(rect.width,rect.height)*0.32;
   if(dist<innerRadius)return 'total';
   const deg=(Math.atan2(y,x)*180/Math.PI+450)%360;
   return deg<current().hp*3.6?'hand':'bank';
 }
 host.querySelectorAll('[data-type]').forEach(el=>{
   el.addEventListener('mouseenter',event=>{show(el.dataset.type==='split'?ringSegment(event,el):el.dataset.type);place(event.clientX,event.clientY);});
   el.addEventListener('mousemove',event=>{show(el.dataset.type==='split'?ringSegment(event,el):el.dataset.type);place(event.clientX,event.clientY);});
   el.addEventListener('mouseleave',()=>tooltip.hidden=true);
   el.addEventListener('focus',()=>{show(el.dataset.type==='split'?'total':el.dataset.type);const rect=el.getBoundingClientRect();place(rect.left+rect.width/2,rect.top);});
   el.addEventListener('blur',()=>tooltip.hidden=true);
 });
 const observer=new MutationObserver(()=>{const h=parseFloat(($('#handCash')[0]?.textContent||'').replace(/,/g,''));if(Number.isFinite(h)&&Math.abs(h-Number(host.dataset.hand))>.001){host.dataset.hand=h;update();}});
 const hand=$('#handCash')[0];if(hand)observer.observe(hand,{childList:true,characterData:true,subtree:true});
 update();
})();
</script>


<script>
(function(){
  const form=$('#bm-lead-period-form')[0];
  if(!form)return;
  const preset=form.querySelector('#bm-lead-period');
  const custom=form.querySelector('#bm-custom-period');
  const label=form.querySelector('#bm-lead-period-bounds');
  const error=form.querySelector('#bm-lead-period-error');
  const values={new_leads:$('#bm-new-leads-count')[0],completed_followups:$('#bm-completed-followups-count')[0],today_followups:$('#bm-today-followups-count')[0],pending_overdue_followups:$('#bm-pending-overdue-count')[0]};
  let pending=null;
  function toggleCustom(){custom.hidden=preset.value!=='custom';}
  async function loadPeriod(){
    if(preset.value==='custom' && (!form.elements.lead_from.value||!form.elements.lead_to.value))return;
    if(pending)pending.abort();
    const current=pending=$.ajax({url:$(form).attr('action'),method:'GET',data:$(form).serialize(),dataType:'json',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
    form.setAttribute('aria-busy','true');
    error.hidden=true;
    try{
      const result=await current;
      if(!Number.isFinite(Number(result.new_leads)))throw new Error('Invalid response from server.');
      Object.entries(values).forEach(([key,node])=>{if(node&&Number.isFinite(Number(result[key]))){node.textContent=Number(result[key]).toLocaleString('en-US');node.dataset.number=result[key];}});
      label.textContent=result.from+' to '+result.to;
    }catch(e){
      if(e.statusText!=='abort'){error.textContent=e.status===422?'Invalid date range.':(e.message||'Unable to load dashboard data.');error.hidden=false;}
    }finally{if(pending===current){form.removeAttribute('aria-busy');pending=null;}}
  }
  $(preset).on('change',()=>{toggleCustom();if(preset.value!=='custom')loadPeriod();});
  $(form).on('submit',e=>{e.preventDefault();loadPeriod();});
  toggleCustom();
})();
</script>


<script>
(function () {
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  function runDashboardCounters() {
    $('.bm-dashboard-modern .small-box .inner h3, .bm-dashboard-modern .financial-circle h4').forEach(function (node) {
      var raw = node.textContent.trim();
      var clean = raw.replace(/,/g, '');
      if (!/^-?\d+(?:\.\d+)?$/.test(clean)) return;
      var target = Number(clean), decimals = (clean.split('.')[1] || '').length;
      var start = performance.now();
      function frame(now) {
        if (!node.isConnected) return;
        var progress = Math.min((now - start) / 1100, 1);
        var eased = 1 - Math.pow(1 - progress, 3);
        node.textContent = (target * eased).toLocaleString('en-US', {
          minimumFractionDigits: decimals, maximumFractionDigits: decimals
        });
        if (progress < 1) requestAnimationFrame(frame);
      }
      requestAnimationFrame(frame);
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', runDashboardCounters);
  else runDashboardCounters();
})();
</script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function () {
            let destroyUrl = "{{ url('admin/dasticash') }}"; // e.g., /admin/dasticash

            // ------------------------------
            // RESET FIELD ERRORS
            // ------------------------------
            function resetErrors(form) {
                $(form).find('.text-danger').remove();
                $(form).find('.is-invalid').removeClass('is-invalid');
            }

            // ------------------------------
            // BUTTON LOADER
            // ------------------------------
            function toggleLoader(button, loading = true, text = "Saving...") {
                if (loading) {
                    $(button).html(`<span class="spinner-border spinner-border-sm"></span> ${text}`);
                    $(button).attr("disabled", true);
                } else {
                    $(button).html($(button).data('original-text'));
                    $(button).attr("disabled", false);
                }
            }

            // ------------------------------
            // CSRF TOKEN
            // ------------------------------
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            // ===================================================================
            // 1️⃣ ADD DASTICASH (AJAX)
            // ===================================================================
            $("#addDasticashModal form").submit(function (e) {
                e.preventDefault();

                let form = this;
                let amount = $(form).find("input[name='amount']").val();
                let button = $(form).find("button[type='submit']");
                button.data('original-text', button.text());

                resetErrors(form);
                toggleLoader(button, true, "Saving...");

                $.ajax({
                    type: "POST",
                    url: form.action,
                    data: new FormData(form),
                    processData: false,
                    contentType: false,

                    success: function (res) {
                        toggleLoader(button, false);
                        $("#addDasticashModal").modal("hide");

                        Swal.fire({
                            icon: "success",
                            title: "Dasticash Added",
                            text: res.message,
                            timer: 1800,
                            showConfirmButton: false
                        });
                        let dastiAmount = parseFloat(res.data.amount) || 0;

                        let handCashEl = $("#handCash");
                        let totalCashEl = $("#totalCash");

                        let handCash = parseFloat(handCashEl.text().replace(/,/g, '')) || 0;
                        let totalCash = parseFloat(totalCashEl.text().replace(/,/g, '')) || 0;

                        let updatedHandCash = handCash - dastiAmount;
                        let updatedTotalCash = totalCash - dastiAmount;

                        handCashEl.text(updatedHandCash.toLocaleString(undefined, { minimumFractionDigits: 2 }));
                        totalCashEl.text(updatedTotalCash.toLocaleString(undefined, { minimumFractionDigits: 2 }));

                        // ------------------------------
                        // APPEND NEW ROW DYNAMICALLY
                        // ------------------------------
                        $("#dasticashTable tbody").append(`
                            <tr id="row-${res.data.id}">
                                <td>NEW</td>
                                <td class="name-cell">${res.data.name}</td>
                                <td class="amount-cell">${res.data.amount ?? ''}</td>
                                <td class="desc-cell">${res.data.description ?? ''}</td>
                                <td>
                                    <a href="javascript:void(0)"
                                       class="btn btn-sm btn-info edit-btn"
                                       data-id="${res.data.id}"
                                       data-name="${res.data.name}"
                                       data-amount="${res.data.amount ?? ''}"
                                       data-description="${res.data.description ?? ''}">
                                        <i class="fas fa-edit"></i>
                                    </a>

                                    <button class="btn btn-sm btn-danger delete-btn" 
                                            data-id="${res.data.id}" data-url="${destroyUrl}/${res.data.id}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `);

                        // Clear form
                        form.reset();
                    },

                    error: function (xhr) {
                        toggleLoader(button, false);

                        if (xhr.status === 422) {
                            $.each(xhr.responseJSON.errors, function (key, value) {
                                let input = $(form).find(`[name="${key}"]`);
                                input.addClass("is-invalid");
                                input.after(
                                    `<small class="text-danger">${value[0]}</small>`
                                );
                            });
                        }
                    }
                });
            });

            $('.edit-btn').click(function () {
                var id = $(this).data('id');
                var name = $(this).data('name');
                var amount = $(this).data('amount');
                var description = $(this).data('description');
                let baseUrl = "{{ url('/') }}"; // output: http://127.0.0.1:8000

                $('#edit-id').val(id);
                $('#edit-name').val(name);
                $('#edit-amount').val(amount);
                $('#edit-description').val(description);
                $('#editDasticashForm').attr('action', baseUrl + '/admin/dasticash/' + id);
                $('#editDasticashModal').modal('show');
            });

            // ===================================================================
            // 2️⃣ UPDATE DASTICASH (AJAX)
            // ===================================================================

            $("#editDasticashForm").submit(function (e) {
                e.preventDefault();

                let form = this;
                let button = $(form).find("button[type='submit']");
                button.data('original-text', button.text());

                resetErrors(form);
                toggleLoader(button, true, "Updating...");

                $.ajax({
                    type: "POST",
                    url: form.action,
                    data: new FormData(form),
                    processData: false,
                    contentType: false,

                    success: function (res) {
                        toggleLoader(button, false);
                        $("#editDasticashModal").modal("hide");

                        Swal.fire({
                            icon: "success",
                            title: "Updated Successfully",
                            text: res.message,
                            timer: 1800,
                            showConfirmButton: false
                        });

                        let row = $("#row-" + res.data.id);

                        row.find(".name-cell").text(res.data.name);
                        row.find(".amount-cell").text(res.data.amount ?? '');
                        row.find(".desc-cell").text(res.data.description ?? '');

                        // update dataset for edit button
                        let editBtn = row.find(".edit-btn");
                        editBtn.data("name", res.data.name);
                        editBtn.data("amount", res.data.amount);
                        editBtn.data("description", res.data.description);
                    },

                    error: function (xhr) {
                        toggleLoader(button, false);

                        if (xhr.status === 422) {
                            $.each(xhr.responseJSON.errors, function (key, value) {
                                let input = $(form).find(`[name="${key}"]`);
                                input.addClass("is-invalid");
                                input.after(
                                    `<small class="text-danger">${value[0]}</small>`
                                );
                            });
                        }
                    }
                });
            });

            // ===================================================================
            // 3️⃣ DELETE DYNAMIC (AJAX)
            // ===================================================================
            $(document).on("click", ".delete-btn", function () {
                let url = $(this).data("url");
                console.log(url);
                let id = $(this).data("id");
                let row = $("#row-" + id);

                Swal.fire({
                    title: "Are you sure?",
                    text: "This record will be removed.",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Yes, delete it!"
                }).then((result) => {
                    if (result.isConfirmed) {

                        $.ajax({
                            type: "DELETE",
                            url: url,
                            success: function (res) {
                                row.fadeOut(300, function () {
                                    $(this).remove();
                                });
                                dastiAmount = parseFloat(res.data.amount) || 0;
                                let id = res.data.id;
                                let handCashEl = $("#handCash");
                                let totalCashEl = $("#totalCash");

                                let handCash = parseFloat(handCashEl.text().replace(/,/g, '')) || 0;
                                let totalCash = parseFloat(totalCashEl.text().replace(/,/g, '')) || 0;

                                let updatedHandCash = handCash + dastiAmount;
                                let updatedTotalCash = totalCash + dastiAmount;

                                handCashEl.text(updatedHandCash.toLocaleString(undefined, { minimumFractionDigits: 2 }));
                                totalCashEl.text(updatedTotalCash.toLocaleString(undefined, { minimumFractionDigits: 2 }));
                                $("#row-" + id).remove();
                                Swal.fire({
                                    icon: "success",
                                    title: "Deleted",
                                    text: res.message,
                                    timer: 1500,
                                    showConfirmButton: false
                                });
                                window.location.reload();
                            }
                        });

                    }
                });
            });

        });
    </script>
@endsection
