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
.bm-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;flex-shrink:0}
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
 ['value'=>$today_leads,'label'=>'New Leads Add Today','icon'=>'fa-user','href'=>route('dashboard.leads_by_users_report'),'tone'=>'indigo'],
 ['value'=>Setting::getTodayLeadWorkCount(),'label'=>'Today Follow UP','icon'=>'fa-comments','href'=>route('crm.todayLeadWorkReport'),'tone'=>'green'],
 ['value'=>$get_all_lead,'label'=>'Pending Works','icon'=>'fa-tasks','href'=>route('report.lead.index'),'tone'=>'amber'],
 ['value'=>$total_blance,'label'=>'Total Balance','icon'=>'fa-wallet','href'=>route('finance.reports.show_ledger'),'tone'=>'blue']
] as $kpi)
<a class="bm-kpi bm-{{ $kpi['tone'] }}" href="{{ $kpi['href'] }}">
 <div class="bm-kpi-top"><div class="bm-kpi-icon"><i class="fas {{ $kpi['icon'] }}"></i></div>
 <div class="bm-kpi-value bm-counter" data-number="{{ $kpi['value'] }}">{{ number_format((float)$kpi['value'],is_numeric($kpi['value']) && floor((float)$kpi['value'])!=(float)$kpi['value'] ? 2 : 0) }}</div></div>
 <div class="bm-kpi-bottom"><div class="bm-kpi-label">{{ $kpi['label'] }}</div><span class="bm-kpi-action">View report <i class="fas fa-arrow-right"></i></span></div>
</a>
@endforeach
</div>
<div class="bm-dashboard-grid">
 <section class="bm-panel bm-finance">
  <div class="bm-panel-heading"><h5>Financial Summary</h5><span>Ledger balances</span></div>
  <div class="bm-panel-body bm-finance-body">
   @foreach ([
    ['name'=>'Hand Cash','label'=>'Hand Cash Balance','value'=>$total_blance,'id'=>'handCash','accent'=>'#13bd8b'],
    ['name'=>'Bank Account','label'=>'Current Bank Balance','value'=>-$total_bank_account_data,'id'=>'','accent'=>'#456eea'],
    ['name'=>'Total Cash','label'=>'Available Cash Balance','value'=>$total_blance - $total_bank_account_data,'id'=>'totalCash','accent'=>'#9767e9']
   ] as $finance)
   <div class="bm-ring-item">
    <div class="bm-ring" style="--ring-color:{{ $finance['accent'] }}"><div class="bm-ring-center"><span>{{ $finance['name'] }}</span><strong class="bm-counter" @if($finance['id']) id="{{ $finance['id'] }}" @endif data-number="{{ $finance['value'] }}">{{ number_format($finance['value'],2) }}</strong></div></div>
    <div class="bm-ring-caption">{{ $finance['label'] }}</div>
   </div>
   @endforeach
  </div>
 </section>
 <section class="bm-panel bm-actions">
  <div class="bm-panel-heading"><h5>Lead Search & Attendance</h5><span>Quick actions</span></div>
  <div class="bm-panel-body bm-action-body">
   @can('lead search')
   <div class="bm-action-block"><h6>Search Lead By Number</h6><div class="bm-lead-search"><input name="number" id="number" type="text" maxlength="11" class="form-control" placeholder="Mobile number" aria-label="Search lead by mobile number"><button type="button" id="search_number" class="bm-search-icon" aria-label="Search lead"><i class="fas fa-search"></i></button></div><div id="msg" class="message"></div></div>
   @endcan
   @can('read attendance')
   <div class="bm-action-block"><form method="POST" action="/admin/punch" enctype="multipart/form-data">@csrf
    <div class="bm-action-title"><h6>Employee Attendance</h6></div>
    <div class="bm-attendance-fields"><select class="form-control" name="user_id">@foreach($users_list as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select>
    @if($display_date)<input type="datetime-local" class="form-control" name="punch_time" id="date_time">@endif
    <button type="submit" class="btn btn-primary btn-sm bm-punch-button" id="punch_button"><i class="fas fa-plus"></i> Punch In</button></div>
    <div id="soft-alert" class="alert" style="display:none"></div></form></div>
   @endcan
  </div>
 </section>
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
 <section class="bm-panel bm-punches">
  <div class="bm-panel-heading"><h5>Punch-in Records for Today</h5><span>Today · Employee records</span></div>
  <div class="bm-panel-body bm-table-scroll"><table class="table table-hover"><thead><tr><th>Date</th><th>User Name</th><th>Punch In</th><th>Punch Out</th><th>Total Hours</th></tr></thead><tbody>
  @foreach($today_punches as $punch)
  <tr><td>{{ date('Y-m-d',strtotime($punch->punch_in)) }}</td><td>{{ $punch->user->name }}</td><td>{{ date('H:i:s',strtotime($punch->punch_in)) }}</td><td>{{ $punch->punch_out ? date('H:i:s',strtotime($punch->punch_out)) : 'N/A' }}</td><td>
  @if($punch->punch_out){{ gmdate('H:i:s',max(0,strtotime($punch->punch_out)-strtotime($punch->punch_in))) }}@else N/A @endif
  </td></tr>
  @endforeach
  </tbody></table></div>
 </section>
 @endcan
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
(function () {
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  function runDashboardCounters() {
    document.querySelectorAll('.bm-dashboard-modern .small-box .inner h3, .bm-dashboard-modern .financial-circle h4').forEach(function (node) {
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
