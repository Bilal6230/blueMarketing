@extends('admin.layouts.master')
@section('content')
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
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
        <!-- /.content-header -->

        <!-- Main content -->
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
                                <i class="fas fa-smile"></i>
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
                                <i class="fas fa-unlock"></i>
                            </div>
                            <a href="{{ route('report.lead.index') }}" class="small-box-footer">More info <i
                                    class="fas fa-arrow-circle-right"></i></a>
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
                                <i class="fas fa-unlock"></i>
                            </div>
                            <a href="{{ route('finance.reports.show_ledger') }}" class="small-box-footer">More info <i
                                    class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <!-- ./col -->
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
                              <canvas id="donutChart" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
                            </div>
                            <!-- /.card-body -->
                        </div>
                      <!-- /.card -->
                    </div> --}}
                @canany(['lead search', 'lead details', 'read attendance'])
                    <div class="row">
                        @can('lead search')
                            <div class="col-md-6">
                                <div class="card card-info">
                                    <div class="card-header">
                                        <h3 class="card-title">Search Lead By Number</h3>
                                    </div>
                                    <!-- /.card-header -->
                                    <!-- form start -->
                                    <div class="card-body pt-5">
                                        <div class="form-group row">
                                            <div class="input-group input-group-sm">
                                                @csrf
                                                <input type="text" class="form-control" name="number" id="number"
                                                    maxlength="11" size="11">
                                                <span class="input-group-append">
                                                    <button type="submit" class="btn btn-info btn-flat"
                                                        id="search_number">Go!</button>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <div id="msg" class="message message-success col-lg-12 col-12">

                                            </div>

                                        </div>


                                    </div>

                                </div>
                                <!-- /.col -->
                            </div>
                        @endcan

                        @can('read attendance')
                            <div class="col-md-6">
                                <div class="card card-danger">
                                    <div class="card-header">
                                        <h3 class="card-title">Employee Attendance</h3>
                                    </div>
                                    <!-- /.card-header -->
                                    <!-- form start -->
                                    <div class="card-body">
                                        <div class="form-group">
                                            <div class=" input-group-sm">
                                                <form action="/admin/punch" method="POST" enctype="multipart/form-data">
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


                                                    <span class="input-group-append">
                                                        <button type="submit" class="btn btn-info btn-flat"
                                                            id="punch_button">Punch</button>
                                                    </span>

                                                </form>

                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <div id="msg" class="message message-success col-lg-12 col-12">

                                            </div>

                                        </div>


                                    </div>

                                </div>
                                <!-- /.col -->
                            </div>
                        @endcan


                    </div>
                @endcanany
                <div class="row">
                    <!-- Hand Cash Card -->
                    <div class="col-md-6">
                        <div class="card card-success">
                            <div class="card-header">
                                <h3 class="card-title">Hand Cash</h3>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-md-4">
                                        <h5>Hand Cash Total</h5>
                                        <p><strong>{{ number_format($total_blance, 2) }}</strong></p>
                                    </div>
                                    <div class="col-md-4">
                                        <h5>Bank Account Total</h5>
                                        <p><strong>{{ number_format($total_bank_account_data, 2) }}</strong></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card card-primary">
                            <div class="card-header">
                                <h3 class="card-title">Dasticash Records</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="d-flex justify-content-end mb-2">
                                            <button class="btn btn-sm btn-primary" data-toggle="modal"
                                                data-target="#addDasticashModal">
                                                <i class="fas fa-plus-circle"></i> Add Dasticash
                                            </button>
                                        </div>

                                        <!-- DataTable -->
                                        <table class="table table-bordered table-striped" id="dasticashTable">
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
                                                    <tr>
                                                        <td>{{ $key + 1 }}</td>
                                                        <td>{{ $item->name }}</td>
                                                        <td>{{ $item->amount }}</td>
                                                        <td>{{ $item->description }}</td>
                                                        <td>
                                                            <a href="javascript:void(0)"
                                                                class="btn btn-sm btn-info edit-btn"
                                                                data-id="{{ $item->id }}"
                                                                data-name="{{ $item->name }}"
                                                                data-amount="{{ $item->amount }}"
                                                                data-description="{{ $item->description }}">
                                                                Edit
                                                            </a>
                                                            <form action="{{ route('dasticash.destroy', $item->id) }}"
                                                                method="POST" style="display:inline-block">
                                                                @csrf @method('DELETE')
                                                                <button class="btn btn-sm btn-danger"
                                                                    onclick="return confirm('Delete this record?')">Delete</button>
                                                            </form>
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
                </div>




                @canany(['read attendance'])
                    <div class="col-md-12">
                        <h4>Punch-in Records for Today</h4>
                        <table class="table table-bordered">
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
                                        <td>{{ $punch->punch_out ? date('H:i:s', strtotime($punch->punch_out)) : 'N/A' }}</td>

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
                                                {{ $hours }}h {{ $minutes }}m {{ $seconds }}s
                                            @else
                                                N/A
                                            @endif
                                        </td>

                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
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
                                        <input type="text" name="name" id="edit-name" class="form-control"
                                            required>
                                    </div>
                                    <div class="form-group">
                                        <label>Amount</label>
                                        <input type="text" name="amount" id="edit-amount" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label>Description</label>
                                        <textarea name="description" id="edit-description" class="form-control" rows="3"></textarea>
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



            </div><!-- /.container-fluid -->
        </section>


        <!-- /.content -->
    </div>
@endsection
@section('js')
    <script>
        $(document).ready(function() {
            $('.edit-btn').click(function() {
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
        });
    </script>
@endsection
