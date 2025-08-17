@extends('admin.layouts.master')
@section('content')
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">{{ $title }}</h1>
                    </div><!-- /.col -->
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active">{{ $title }}</li>
                        </ol>
                    </div><!-- /.col -->
                </div><!-- /.row -->
            </div>
            <!-- /.container-fluid -->
        </div>
        <!-- /.content-header -->

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            @can('create plot')
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <a href="#" class="btn btn-sm btn-success" data-toggle="modal"
                                            data-target="#modal-tambah" data-backdrop="static" data-keyboard="false"><i
                                                class="fas fa-plus"></i> Put on Hold</a>
                                    </h3>
                                </div>
                            @endcan
                            <!-- /.card-header -->
                            <div class="card-body table-responsive">
                                <table id="example1" class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Unit Type</th>
                                            <th>Unit Name</th>
                                            <th>Size</th>
                                            <th>Road</th>
                                            <th>Sold</th>
                                            <th>Status</th>
                                            <th>Reason</th>
                                            @canany(['update plot', 'delete plot'])
                                                <th>Action</th>
                                            @endcanany
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($data as $i)
                                            <tr>
                                                <td>{{ $loop->iteration }}</td>
                                                <td>{{ Setting::getPlotType($i->type) }}</td>
                                                <td>
                                                    <a href="{{ route('project.plot.history', $i->id) }}" target="_blank">
                                                        {{ $i->name }}
                                                    </a>
                                                </td>

                                                <td>{{ $i->size }} {{ Setting::getUnitTypes($i->unit) }}</td>
                                                <td>{{ Setting::getRoadSide($i->road_id) }} </td>
                                                <td>{{ Setting::sale_status($i->sold) }}</td>
                                                <td>{{ Setting::getStatus($i->is_active) }}</td>
                                                <td>{{ $i?->holdPlots?->reason ?? '' }}</td>
                                                @canany(['update plot', 'delete plot'])
                                                    <td>
                                                        @if ($i->sold == 0)
                                                            <div class="btn-group">
                                                                @can('delete plot')
                                                                    <button class="btn btn-sm btn-danger btn-delete"
                                                                        data-id="{{ $i->id }}"
                                                                        data-name="{{ $i->name }}"><i
                                                                            class="fas fa-trash"></i></button>
                                                                @endcan
                                                            </div>
                                                        @endif

                                                    </td>
                                                @endcanany
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <!-- /.card-body -->
                        </div>

                        <!-- /.card -->


                    </div>
                    <!-- /.col -->
                </div>
                <!-- /.row -->
            </div><!-- /.container-fluid -->
        </section>
        <!-- /.content -->
    </div>
@endsection

@section('js')
    <script>
        $(document).ready(function() {

            $(document).on("click", '.btn-delete', function() {
                let id = $(this).attr("data-id");
                let name = $(this).attr("data-name");
                $("#did").val(id);
                $("#delete-data").html(name);
                $('#modal-delete').modal({
                    backdrop: 'static',
                    keyboard: false,
                    show: true
                });
            });
        });
        $(document).ready(function() {
            function fetch_plot_list(plot_type) {
                $.ajax({
                    url: '/admin/get-plots', // URL to your route
                    type: 'POST', // Use POST method for sending data
                    data: {
                        _token: '{{ csrf_token() }}', // Add CSRF token
                        plot_type: plot_type, // Pass project ID to server
                        allplots: true, // Pass project ID to server
                        project_id: '{{ getSelectedTown() }}'
                    },
                    success: function(data) {
                        $('#plot_id').empty();
                        $('#plot_id').append('<option value="">Select Plot</option>');
                        $.each(data, function(key, plot) {
                            $('#plot_id').append('<option value="' + plot.id + '" data-size="' +
                                plot.size + '" >' + plot.name + '</option>');
                        });
                    }
                });
            }
            $(document).on("change", '#plot_type', function() {
                // Get selected project ID
                var plotType = $(this).val();
                // If a project is selected, fetch customers
                if (plotType) {
                    fetch_plot_list(plotType);
                } else {

                    $('#plot_id').empty();
                    $('#plot_id').append('<option value="">Select Plot</option>');
                }
            });
        })
    </script>
@endsection

@section('modal')
    {{-- Modal Add --}}
    <div class="modal fade" id="modal-tambah">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Hold Plot</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('project.plot.hold') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="input-group">
                                    <label class="fbox">Plot Type</label>
                                    <div class="input-group">
                                        <select class="form-control" name="type" id="plot_type">
                                            <option value="">Select Plot Type</option>
                                            <option value="1">Residenational</option>
                                            <option value="2">Commercial</option>
                                        </select>
                                        @error('type')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="input-group">
                                    <label class="fbox">Plots</label>
                                    <div class="input-group">
                                        <select class="form-control select2" name="plot_id" id="plot_id">
                                            <option value="">Select Plot</option>
                                        </select>
                                        @error('plot_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-sm-12">

                                <div class="form-group">
                                    <label class="fbox">Reason</label>
                                    <textarea class="form-control" rows="3" placeholder="Enter ..." spellcheck="false" name="reason"></textarea>
                                </div>
                            </div>

                        </div>

                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
                </form>
            </div>
            <!-- /.modal-content -->
        </div>
        <!-- /.modal-dialog -->
    </div>
    {{-- Modal delete --}}
    <div class="modal fade" id="modal-delete">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Clear Data</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('project.plot.unhold') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('DELETE')
                        <p class="modal-text">Are you sure you want to UnHold this Plot? <b id="delete-data"></b></p>
                        <input type="hidden" name="id" id="did">
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
                </form>
            </div>
            <!-- /.modal-content -->
        </div>
        <!-- /.modal-dialog -->
    </div>
@endsection
