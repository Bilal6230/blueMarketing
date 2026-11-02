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

/* Batch 02: compact dashboard-only composition matching the approved V2 reference. */
@media(min-width: 992px){
 .bm-dashboard-modern .content-header{padding:5px 0 1px}
 .bm-dashboard-modern .content-header h1{font-size:1.05rem}
 .bm-dashboard-modern .content{height:calc(100dvh - 126px);overflow:hidden}
 .bm-dashboard-modern .content>.container-fluid{height:100%;display:flex;flex-direction:column;gap:9px;overflow:hidden!important;padding-bottom:8px}
 .bm-dashboard-modern .bm-kpi-row{flex:0 0 auto;margin:0 -5px}
 .bm-dashboard-modern .bm-kpi-row>[class*="col-"]{padding:0 5px}
 .bm-dashboard-modern .bm-widget-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);grid-template-rows:repeat(2,minmax(0,1fr));gap:9px;flex:1;min-height:0}
 .bm-dashboard-modern .bm-widget-grid>.bm-widget{width:100%;max-width:none;min-height:0;padding:0!important;margin:0!important}
 .bm-dashboard-modern .bm-widget-grid .custom_card{height:100%!important;display:flex;flex-direction:column}
 .bm-dashboard-modern .bm-widget-grid .custom_card>.card-body{flex:1;min-height:0;display:flex;flex-direction:column;padding:12px!important;overflow:hidden}
 .bm-dashboard-modern .bm-widget-grid .table-responsive,.bm-dashboard-modern .bm-widget-grid .table-resposive{max-height:none;min-height:0;flex:1;overflow:auto}
 .bm-dashboard-modern .bm-widget-grid .financial-circle{width:min(100%,110px);height:auto;aspect-ratio:1;display:grid;place-items:center;border-radius:50%;background:conic-gradient(#22bf90 0 76%,#e9f0f7 76% 100%)!important;position:relative;animation:bm-ring-enter .7s ease-out both}
 .bm-dashboard-modern .bm-widget-grid .financial-circle .circle-content{width:79%;height:79%;border-radius:50%;background:white;display:flex;align-items:center;justify-content:center;flex-direction:column;position:relative;z-index:1;padding:3px}
 .bm-dashboard-modern .bm-widget-grid .financial-circle h4{font-size:clamp(.65rem,1vw,.9rem);margin:3px 0!important}
 .bm-dashboard-modern .bm-widget-grid .financial-circle h6{font-size:.65rem}
 .bm-dashboard-modern .bm-widget-grid .col-12.text-center.mb-4{margin-bottom:6px!important}
 .bm-dashboard-modern .bm-widget-grid .custom_card h4,.bm-dashboard-modern .bm-widget-grid .custom_card h5{font-size: .95rem}
 .bm-dashboard-modern .bm-widget-grid .form-group{margin-bottom:7px}
 .bm-dashboard-modern .small-box{min-height:88px;margin-bottom:0}
 .bm-dashboard-modern .small-box .inner{padding:9px 13px 5px}
 .bm-dashboard-modern .small-box h3{font-size:clamp(1.15rem,1.7vw,1.7rem);margin-bottom:2px}
 .bm-dashboard-modern .small-box p{font-size:.74rem;margin-bottom:3px}
 .bm-dashboard-modern .small-box-footer{padding:3px 8px;font-size:.66rem}
 .bm-dashboard-modern #dasticashTable td{font-size:.75rem}
 .bm-dashboard-modern .bm-widget-grid>.bm-widget.bm-attendance-records{grid-column:span 2}
 .bm-dashboard-modern .bm-widget-grid.bm-grid-five{grid-template-rows:repeat(3,minmax(0,1fr))}
 .bm-dashboard-modern .bm-widget-grid.bm-grid-five>.bm-widget.bm-attendance-records{grid-column:span 2}
}
@media(min-width:992px) and (max-height:700px){
 .bm-dashboard-modern .bm-widget-grid .financial-circle{width:80px}
 .bm-dashboard-modern .small-box{min-height:72px}
 .bm-dashboard-modern .small-box .inner{padding:6px 12px 2px}
 .bm-dashboard-modern .bm-widget-grid .custom_card>.card-body{padding:8px!important}
}
@media(max-width:991.98px){
 .bm-dashboard-modern .bm-widget-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,330px),1fr));gap:10px}
 .bm-dashboard-modern .bm-widget-grid>.bm-widget{padding:0;width:100%;max-width:none}
 .bm-dashboard-modern .content{height:auto!important;overflow:visible!important}
 .bm-dashboard-modern .content>.container-fluid{height:auto!important;overflow:visible!important}
}
@keyframes bm-ring-enter{from{transform:scale(.85);opacity:.3}to{transform:scale(1);opacity:1}}
@media(prefers-reduced-motion:reduce){.bm-dashboard-modern .bm-widget-grid .financial-circle{animation:none}}
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
                <!-- Small boxes (Stat box) -->
                <div class="row">
                    <div class="col-lg-3 col-6">
                        <!-- small box -->
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3>{{ $today_leads }}</h3>
                                <p>New Leads Add Today</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <a href="{{ route('dashboard.leads_by_users_report') }}" class="small-box-footer">More info <i
                                    class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <!-- ./col -->
                    <div class="col-lg-3 col-6">
                        <!-- small box -->
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3>{{ Setting::getTodayLeadWorkCount() }}</h3>
                                <p>Today Follow UP</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-comments"></i>
                            </div>
                            <a href="{{ route('crm.todayLeadWorkReport') }}" class="small-box-footer">More info <i
                                    class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <!-- ./col -->
                    <div class="col-lg-3 col-6">
                        <!-- small box -->
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h3>{{ $get_all_lead }}</h3>

                                <p>Pending Works</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <a href="{{ route('report.lead.index') }}" class="small-box-footer"
                                style="color: #fff !important;">More info <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <!-- small box -->
                        <div class="small-box bg-primary">
                            <div class="inner">
                                <h3>{{ $total_blance }}</h3>
                                <p>Total Balance</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-wallet"></i>
                            </div>
                            <a href="{{ route('finance.reports.show_ledger') }}" class="small-box-footer">More info <i
                                    class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <!-- ./col -->
                </div>
                @canany(['lead search', 'lead details', 'read attendance'])
                    <div class="row">
                        @can('lead search')
                            <div class="col-md-6 mb-4">
                                <div class="custom_card h-100">
                                    <div class="card-body">
                                        <div class="mb-3 d-flex align-items-center justify-content-between">
                                            <h5 class="text-lg font-semibold">Search Lead By Number</h5>
                                        </div>
                                        <div class="form-group">
                                            <div class="input-group input-group-sm">
                                                @csrf
                                                <input type="text" class="form-control" name="number" id="number" maxlength="11"
                                                    size="11">
                                                <span class="input-group-append">
                                                    <button type="submit" class="btn btn-info btn-flat" id="search_number"><i
                                                            class="fas fa-search mr-1"></i> Search</button>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="">
                                            <div id="msg" class="message  ">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endcan

                        @can('read attendance')
                            <div class="col-md-6 mb-4">
                                <div class="custom_card h-100">
                                    <div class="card-body">
                                        <form action="/admin/punch" method="POST" enctype="multipart/form-data">
                                            <div class="mb-3 d-flex align-items-center justify-content-between">
                                                <h5 class="text-lg font-semibold">Employee Attendance</h5>
                                                <button class="btn btn-sm custom_btn_outline primary" id="punch_button">
                                                    <i class="fas fa-plus mr-1"></i> Punch In
                                                </button>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    @csrf
                                                    <select class="form-control" name="user_id">
                                                        @foreach ($users_list as $u)
                                                            <option value="{{ $u->id }}"> {{ $u->name }}
                                                            </option>
                                                        @endforeach

                                                    </select>
                                                </div>

                                                @if ($display_date)
                                                    <div class="col-md-6">
                                                        <input type="datetime-local" class="form-control" name="punch_time"
                                                            id="date_time">
                                                    </div>
                                                @endif
                                            </div>
                                        </form>
                                        <div class="form-group row">
                                            <div id="msg" class="message  ">
                                            </div>
                                            <div id="soft-alert" class="alert" style="display:none;"></div>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endcan


                    </div>
                @endcanany
                {{-- Financial Summary --}}
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="custom_card h-100">
                            <div class="card-body">
                                <div class="row justify-content-center">
                                    <div class="col-12 text-center mb-4">
                                        <h4 class="text-lg">Financial Summary</h4>
                                    </div>

                                    <!-- Hand Cash Total -->
                                    <div class="col-md-4 mb-4 mb-md-0">
                                        <div class="financial-circle bg_success">
                                            <div class="circle-content">
                                                <h6>Hand Cash</h6>
                                                {{-- @dd($total_blance) --}}
                                                <!-- Hand Cash -->
                                                <h4 class="mt-2" id="handCash">
                                                    {{ number_format($total_blance, 2) }}
                                                </h4>
                                            </div>
                                        </div>
                                        <p class="text-muted mt-2 text-center">Hand Cash Balance</p>
                                    </div>
                                    <!-- Bank Account Total -->
                                    <div class="col-md-4">
                                        <div class="financial-circle {{ (-$total_bank_account_data) >= 0 ? 'bg_success' : 'bg_danger' }}">
                                            <div class="circle-content">
                                                <h6>Bank Account</h6>
                                                <h4 class="mt-2">{{ number_format(-$total_bank_account_data, 2) }}
                                                </h4>
                                            </div>
                                        </div>
                                        <p class="text-muted mt-2 text-center">Current Bank Balance</p>
                                    </div>
                                    <!-- Hand Cash Total -->
                                    <div class="col-md-4 mb-4 mb-md-0">
                                        <div class="financial-circle bg_success">
                                            <div class="circle-content">
                                                <h6>Total Cash</h6>
                                                <h4 class="mt-2" id="totalCash">{{ number_format($total_blance - $total_bank_account_data, 2) }}</h4>
                                            </div>
                                        </div>
                                        <p class="text-muted mt-2 text-center">Available Cash Balance</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="custom_card h-100">
                            <div class="card-body">
                                <div class="mb-3 d-flex align-items-center justify-content-between">
                                    <h5 class="text-lg font-semibold">Dasticash Summary</h5>
                                    <button class="btn btn-sm custom_btn_outline primary" data-toggle="modal"
                                        data-target="#addDasticashModal">
                                        <i class="fas fa-plus mr-1"></i> Add Dasticash
                                    </button>
                                </div>
                                <!-- DataTable -->
                                <div class="table-responsive">
                                    <table class="table table-hover" id="dasticashTable">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Amount</th>
                                                <th>Description</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($dasticashData as $key => $item)
                                                <tr id="row-{{ $item->id }}">
                                                    <td>{{ $key + 1 }}</td>
                                                    <td class="name-cell">{{ $item->name }}</td>
                                                    <td class="amount-cell">{{ $item->amount }}</td>
                                                    <td class="desc-cell">{{ $item->description }}</td>
                                                    <td>
                                                        <a href="javascript:void(0)" class="btn btn-sm btn-info edit-btn"
                                                            data-id="{{ $item->id }}" data-name="{{ $item->name }}"
                                                            data-amount="{{ $item->amount }}"
                                                            data-description="{{ $item->description }}">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button class="btn btn-sm btn-danger delete-btn"
                                                            data-id="{{ $item->id }}"
                                                            data-url="{{ route('dasticash.destroy', $item->id) }}"><i
                                                                class="fas fa-trash"></i></button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
                <!-- /.row (main row) -->

                {{-- <div class="row">
                    <div class="col-md-6">
                        <div class="card card-danger">
                            <div class="card-header">
                                <h3 class="card-title">Donut Chart</h3>

                                <div class="card-tools">
                                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <button type="button" class="btn btn-tool" data-card-widget="remove">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <canvas id="donutChart"
                                    style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
                            </div>
                            <!-- /.card-body -->
                        </div>
                        <!-- /.card -->
                    </div> --}}


                    @canany(['read attendance'])
                        <div class="col-md-12 mb-4">
                            <div class="custom_card h-100">
                                <div class="card-body">
                                    <div class="mb-3 d-flex align-items-center justify-content-between">
                                        <h5 class="text-lg font-semibold">Punch-in Records for Today</h5>

                                    </div>
                                    <div class="table-resposive">

                                        <table class="table table-hover ">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>User Name</th>
                                                    <th>Punch In</th>
                                                    <th>Punch Out</th>
                                                    <th>Total Hours</th>

                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($today_punches as $punch)
                                                    <tr>
                                                        <td>{{ date('Y-m-d', strtotime($punch->punch_in)) }}</td>
                                                        <td>{{ $punch->user->name }}</td>
                                                        <td>{{ date('H:i:s', strtotime($punch->punch_in)) }}</td>
                                                        <td>{{ $punch->punch_out ? date('H:i:s', strtotime($punch->punch_out)) : 'N/A' }}
                                                        </td>

                                                        <td>
                                                            @if ($punch->punch_out)
                                                                                                    <?php
                                                                $punchInTime = strtotime($punch->punch_in);
                                                                $punchOutTime = strtotime($punch->punch_out);
                                                                $totalSeconds = $punchOutTime - $punchInTime;
                                                                $hours = floor($totalSeconds / 3600);
                                                                $minutes = floor(($totalSeconds % 3600) / 60);
                                                                $seconds = $totalSeconds % 60;
                                                                                                                                                                    ?>
                                                                                                    {{ $hours }}h {{ $minutes }}m
                                                                                                    {{ $seconds }}s
                                                            @else
                                                                N/A
                                                            @endif
                                                        </td>

                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>

                                    </div>
                                </div>
                            </div>
                        </div>
                    @endcanany

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
 function arrangeBmDashboard(){
  var host=document.querySelector('.bm-dashboard-modern .content > .container-fluid');
  if(!host || host.querySelector('.bm-widget-grid'))return;
  var allRows=Array.from(host.children).filter(function(el){return el.classList.contains('row')});
  var kpi=allRows.find(function(row){return row.querySelectorAll('.small-box').length===4});
  if(!kpi)return;
  kpi.classList.add('bm-kpi-row');
  var grid=document.createElement('div');grid.className='bm-widget-grid';
  kpi.insertAdjacentElement('afterend',grid);
  var widgets=[
    {el:host.querySelector('#number'),label:'lead-search'},
    {el:host.querySelector('#punch_button'),label:'attendance-form'},
    {el:host.querySelector('#handCash'),label:'financial'},
    {el:host.querySelector('#dasticashTable'),label:'dasticash'},
    {el:Array.from(host.querySelectorAll('h5')).find(function(n){return n.textContent.trim()==='Punch-in Records for Today'}),label:'attendance-records'}
  ];
  widgets.forEach(function(item){
   if(!item.el)return;
   var col=item.el.closest('[class*="col-md-"],[class*="col-lg-"]');
   if(!col || !host.contains(col))return;
   col.classList.add('bm-widget','bm-'+item.label);
   grid.appendChild(col);
  });
  var count=grid.querySelectorAll(':scope > .bm-widget').length;
  if(count>4)grid.classList.add('bm-grid-five');
  /* Keep legacy rows outside the rearranged grid intact if they contain other controls. */
  allRows.forEach(function(row){if(row!==kpi && !row.querySelector('.custom_card') && !row.textContent.trim())row.style.display='none'});
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',arrangeBmDashboard);
 else arrangeBmDashboard();
})();
</script>


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
