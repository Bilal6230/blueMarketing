@extends('admin.layouts.master')
@push('style')
<link rel="stylesheet" href="{{ asset('css/modern-account-directory.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/modern-user-directory.css') }}?v=2">
<style>
.plot-editor .modal-dialog{max-width:950px;width:calc(100% - 34px);margin:24px auto}
.plot-editor .modal-content{border:0;border-radius:12px;overflow:hidden;box-shadow:0 20px 65px #132b4a35;display:flex;max-height:calc(100vh - 48px)}
.plot-editor .modal-header{background:#f5f9fe;border-bottom:1px solid #d1e0ed;padding:15px 20px;flex-shrink:0}
.plot-editor .modal-title{color:#173f6e;font-size:19px;font-weight:750}
.plot-editor .modal-body{overflow:auto;min-height:0;padding:18px 20px;overscroll-behavior:contain}
.plot-editor .modal-footer{position:sticky;bottom:0;background:#fafcff;border-top:1px solid #ccdae8;z-index:2;padding:12px 20px}
.plot-editor .modal-body form>.row{margin-bottom:3px}
.plot-editor .modal-body .row>.col-sm-3,.plot-editor .modal-body .row>.col-sm-6,.plot-editor .modal-body .row>.col-sm-12{margin-bottom:13px}
.plot-editor .modal-body .input-group{display:flex;flex-direction:column;align-items:stretch;width:100%;min-width:0}
.plot-editor .modal-body .input-group>.input-group{width:100%;display:flex;flex-direction:column}
.plot-editor .modal-body .input-group .fbox,.plot-editor .modal-body .form-group .fbox{display:block;width:100%;margin-bottom:6px;font-size:14px;font-weight:650;color:#29496b}
.plot-editor .modal-body .form-control{width:100%;min-width:0;height:40px;border:1px solid #b6c9dd;border-radius:6px;font-size:14px;padding:7px 11px}
.plot-editor .modal-body textarea.form-control{height:auto;min-height:87px}
.plot-editor .plot-section-heading{font-size:13px;font-weight:750;color:#215a98;margin:5px 0 12px}
.plot-editor select.tomselected,.plot-editor select.ts-hidden-accessible{display:none!important}
.plot-editor .ts-wrapper{width:100%;border:0!important;background:transparent!important;padding:0!important;box-shadow:none!important}
.plot-editor .ts-control{min-height:40px;border-color:#b6c9dd!important;border-radius:6px;font-size:14px}
@media(max-width:700px){.plot-editor .modal-dialog{width:calc(100% - 16px);margin:8px auto}.plot-editor .modal-content{max-height:calc(100vh - 16px)}.plot-editor .modal-body{padding:14px}}

.bm-plot-directory .bm-account-table tbody td{height:53px!important}
.bm-plot-directory .bm-account-table thead th{position:sticky;top:0;z-index:3}
.bm-plot-directory .bm-modern-export-toolbar .bm-plot-toolbar-actions{margin-left:auto!important;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.bm-plot-directory .bm-modern-export-toolbar .bm-plot-toolbar-actions .btn{font-size:12px!important;border-radius:6px;padding:7px 12px;font-weight:650}
.bm-plot-directory .bm-account-table td:last-child{text-align:center;min-width:85px}
.bm-plot-directory .bm-account-table .btn-edit,.bm-plot-directory .bm-account-table .btn-delete{font-size:12px}
.bm-plot-directory .bm-account-table td:nth-child(4) a{font-weight:650}
.bm-plot-directory .bm-modern-export-toolbar .bm-modern-column-menu{order:0!important;margin-left:3px!important}
.bm-plot-directory .bm-modern-export-toolbar .bm-plot-toolbar-actions{order:20!important;margin-left:auto!important}
.bm-plot-directory .bm-account-table{min-width:1100px}
</style>
@endpush

@section('content')
    <div class="content-wrapper bm-modern-accounts bm-plot-directory" data-account-kind="plot">
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
                        <div class="card bm-account-card">
                            <div class="bm-modern-export-toolbar bm-export-placeholder" aria-label="Table tools loading">
                                <button type="button" class="bm-modern-export-btn" disabled>Copy</button>
                                <button type="button" class="bm-modern-export-btn" disabled>CSV</button>
                                <button type="button" class="bm-modern-export-btn" disabled>Excel</button>
                                <button type="button" class="bm-modern-export-btn" disabled>PDF</button>
                                <button type="button" class="bm-modern-export-btn" disabled>Print</button>
                                <button type="button" class="bm-modern-export-btn bm-modern-col-toggle" disabled>Column Visibility</button>
                                <div class="bm-plot-toolbar-actions">
                                    @can('create plot')
                                    <a href="#" class="btn btn-sm btn-success btn-add-plot" data-toggle="modal" data-target="#modal-tambah" data-backdrop="static" data-keyboard="false"><i class="fas fa-plus"></i> Add</a>
                                    @endcan
                                </div>
                            </div>
                            <!-- /.card-header -->
                            <div class="card-body table-responsive bm-account-table-wrap">
                                <table id="bmAccountDirectory" data-initial-skeleton="true" class="table table-bordered bm-account-table bm-pending-directory">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            
                                            <th>Unit Type</th>
                                            <th>Unit Name</th>
                                            <th>Size</th>
                                            <th>Amount</th>
                                            <th>Road</th>
                                            <th>Sold</th>
                                            <th>Status</th>
                                            @canany(['update plot', 'delete plot'])
                                                <th>Action</th>
                                            @endcanany
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @for($skeletonIndex = 0; $skeletonIndex < 6; $skeletonIndex++)
                                        <tr class="bm-cell-skeleton-row" aria-hidden="true">
                                            @for($column = 0; $column < 9; $column++)<td><span class="bm-cell-skeleton"></span></td>@endfor
                                            @canany(['update plot','delete plot'])<td><span class="bm-cell-skeleton"></span></td>@endcanany
                                        </tr>
                                        @endfor

                                        @foreach ($data as $i)
                                            <tr>
                                                <td>{{ $loop->iteration }}</td>
                                                <td>{{ ['1'=>'Residential','2'=>'Commercial','3'=>'Others'][(string)$i->type] ?? 'Others' }}</td>
                                                <td>
                                                    <a href="{{ route('project.plot.history', $i->id) }}" target="_blank">
                                                        {{ $i->name }}
                                                    </a>
                                                </td>

                                                <td>{{ $i->size }} {{ Setting::getUnitTypes($i->unit) }}</td>
                                                <td>{{ number_format($i->amount ?? 0, 2) }}</td>
                                                <td>{{ Setting::getRoadSide($i->road_id) }} </td>
                                                <td>{{ Setting::sale_status($i->sold) }}</td>
                                                <td>{{ Setting::getStatus($i->is_active) }}</td>
                                                @canany(['update plot', 'delete plot'])
                                                    <td>
                                                        @if ($i->sold == 0)
                                                            <div class="btn-group">
                                                                @can('update plot')
                                                                    <button class="btn btn-sm btn-primary btn-edit"
                                                                        data-id="{{ $i->id }}"><i
                                                                            class="fas fa-pencil-alt"></i></button>
                                                                @endcan
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
                            <div class="bm-account-footer bm-account-footer-placeholder" aria-label="Table pagination loading">
                                <div class="bm-account-footer-left">
                                    <small class="text-muted">Loading records...</small>
                                    <select disabled aria-label="Rows per page"><option selected>25</option></select>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" disabled>Reset filters</button>
                                </div>
                                <div class="bm-account-pages"><button type="button" class="dv-page-btn" disabled>1</button></div>
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
    <script src="{{ asset('js/modern-account-directory.js') }}?v=plots1"></script>

    @if ($errors->any())
        <script>
            $(document).ready(function() {
                $('#modal-tambah').modal('show');
            });
        </script>
    @endif

    <script>
        $(document).ready(function() {
            $(document).on("click", '.btn-edit', function() {
                let id = $(this).attr("data-id");
                $('#modal-loading').modal({
                    backdrop: 'static',
                    keyboard: false,
                    show: true
                });
                $.ajax({
                    url: "{{ route('project.plot.show') }}",
                    type: "POST",
                    dataType: "JSON",
                    data: {
                        id: id,
                        _token: "{{ csrf_token() }}"
                    },
                    success: function(data) {
                        console.log(data);
                        var data = data.data;
                        $("#edit_amount").val(data.amount ?? '0.00');
                        $("#project_id").val(data.project_id);
                        $("#old_project").val(data.project_id);
                        $("#type").val(data.type);
                        $("#is_active").val(data.is_active);
                        $("#name").val(data.name);
                        $("#size").val(data.size);
                        $("#unit").val(data.unit);
                        $("#is_corner").val(data.is_corner);
                        $("#road_id").val(data.road_id);
                        $("#facing_id").val(data.facing_id);
                        

                        $("#id").val(data.id);
                        $('#modal-loading').modal('hide');
                        $('#modal-edit').modal({
                            backdrop: 'static',
                            keyboard: false,
                            show: true
                        });
                    },
                });
            });

            $(document).on("click", '.btn-add-plot', function() {
                $("#amount").val('0.00');
                
            });

            

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
    </script>
@endsection

@section('modal')
    {{-- Modal Add --}}
    <div class="modal fade plot-editor" id="modal-tambah">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add Plot</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('project.plot.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="row">
                            <div class="col-sm-6">

                                <div class="input-group">
                                    <label class="fbox">Project</label>
                                    <div class="input-group">
                                        <select class="form-control" name="project_id">
                                            @foreach (Setting::get_active_project(getSelectedTown()) as $v)
                                                <option value="{{ $v->id }}">{{ $v->project }}</option>
                                            @endforeach
                                        </select>
                                        @error('project_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="input-group">
                                    <label class="fbox">Plot Type</label>
                                    <div class="input-group">
                                        <select class="form-control" name="type">
                                            
                                                <option value="1">Residential</option><option value="2">Commercial</option><option value="3">Others</option>
                                            
                                        </select>
                                        @error('type')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-sm-3">
                                <div class="input-group">
                                    <label class="fbox">Unit Name</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control @error('name') is-invalid @enderror"
                                            placeholder="Unit Name" name="name" value="{{ old('name') }}">
                                        @error('name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-3">

                                <div class="input-group">
                                    <label class="fbox">Unit Size</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control @error('size') is-invalid @enderror"
                                            placeholder="Unit Size" name="size" value="{{ old('size') }}">
                                        
                                        @error('size')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-3">

                                <div class="input-group">
                                    <label class="fbox">Unit Value</label>
                                    <div class="input-group">
                                        <select class="form-control" name="unit">
                                            
                                                <option value="1">Square Feet</option><option value="2">Marla</option><option value="3">Kanal</option>
                                            

                                        </select>
                                        
                                        @error('unit')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="input-group">
                                    <label class="fbox">Plot Amount</label>
                                    <div class="input-group">
                                        <input type="text" id="amount"
                                            class="form-control @error('amount') is-invalid @enderror"
                                            placeholder="Plot Amount" name="amount"
                                            value="{{ old('amount', '0.00') }}">
                                        @error('amount')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="input-group">
                                    <label class="fbox">Corner </label>
                                    <div class="input-group">
                                        <select class="form-control" name="is_corner">
                                            <option value="1">Yes</option>
                                            <option value="0" selected>No</option>
                                        </select>
                                        @error('is_corner')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-sm-6">
                                <div class="input-group">
                                    <label class="fbox">Road Size</label>
                                    <div class="input-group">
                                        <select class="form-control" name="road_id">
                                            @foreach (Setting::get_active_roads() as $v)
                                                <option value="{{ $v->id }}">{{ $v->name }}</option>
                                            @endforeach

                                        </select>
                                        @error('road_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-6">

                                <div class="input-group">
                                    <label class="fbox">Front Facing</label>
                                    <div class="input-group">
                                        <select class="form-control" name="facing_id">
                                            @foreach (Setting::get_active_facing() as $v)
                                                <option value="{{ $v->id }}">{{ $v->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('facing_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="row">
                            <div class="col-sm-12">

                                <div class="form-group">
                                    <label class="fbox">Description / Notes</label>
                                    <textarea class="form-control" rows="3" placeholder="Enter ..." spellcheck="false" name="description"></textarea>
                                </div>
                            </div>

                        </div>


                        <div class="input-group">
                            <label class="fbox">Status</label>
                            <div class="input-group">
                                <select class="form-control" name="is_active">
                                    <option value="1">Active</option>
                                    <option value="0">Disable</option>
                                </select>
                                @error('is_active')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
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
    {{-- Modal Update --}}
    <div class="modal fade plot-editor" id="modal-edit">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Plot</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('project.plot.update') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-sm-6">

                                <div class="input-group">
                                    <label class="fbox">Project</label>
                                    <div class="input-group">
                                        <select class="form-control" name="project_id" id="project_id">
                                            @foreach (Setting::get_active_project() as $v)
                                                <option value="{{ $v->id }}">{{ $v->project }}</option>
                                            @endforeach
                                        </select>
                                        @error('project_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="input-group">
                                    <label class="fbox">Plot Type</label>
                                    <div class="input-group">
                                        <select class="form-control" name="type" id="type">
                                            
                                                <option value="1">Residential</option><option value="2">Commercial</option><option value="3">Others</option>
                                            
                                        </select>
                                        @error('type')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-sm-3">
                                <div class="input-group">
                                    <label class="fbox">Unit Name</label>
                                    <div class="input-group">
                                        <input readonly id="name" type="text"
                                            class="form-control @error('name') is-invalid @enderror"
                                            placeholder="Unit Name" name="name" value="{{ old('name') }}">
                                        @error('name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-3">

                                <div class="input-group">
                                    <label class="fbox">Unit Size</label>
                                    <div class="input-group">
                                        <input id="size" type="text"
                                            class="form-control @error('size') is-invalid @enderror"
                                            placeholder="Unit Size" name="size" value="{{ old('size') }}">
                                        
                                        @error('size')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-3">

                                <div class="input-group">
                                    <label class="fbox">Unit Value</label>
                                    <div class="input-group">
                                        <select class="form-control" name="unit" id="unit">
                                            
                                                <option value="1">Square Feet</option><option value="2">Marla</option><option value="3">Kanal</option>
                                            

                                        </select>
                                        
                                        @error('unit')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="input-group">
                                    <label class="fbox">Plot Amount</label>
                                    <div class="input-group">
                                        <input id="edit_amount" type="text"
                                            class="form-control @error('amount') is-invalid @enderror"
                                            placeholder="Plot Amount" name="amount"
                                            value="{{ old('amount', '0.00') }}">
                                        @error('amount')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="input-group">
                                    <label class="fbox">Corner </label>
                                    <div class="input-group">
                                        <select class="form-control" name="is_corner" id="is_corner">
                                            <option value="1">Yes</option>
                                            <option value="0" selected>No</option>
                                        </select>
                                        @error('is_corner')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-sm-6">
                                <div class="input-group">
                                    <label class="fbox">Road Size</label>
                                    <div class="input-group">
                                        <select class="form-control" name="road_id" id="road_id">
                                            @foreach (Setting::get_active_roads() as $v)
                                                <option value="{{ $v->id }}">{{ $v->name }}</option>
                                            @endforeach

                                        </select>
                                        @error('road_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-6">

                                <div class="input-group">
                                    <label class="fbox">Front Facing</label>
                                    <div class="input-group">
                                        <select class="form-control" name="facing_id" id="facing_id">
                                            @foreach (Setting::get_active_facing() as $v)
                                                <option value="{{ $v->id }}">{{ $v->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('facing_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="row">
                            <div class="col-sm-12">

                                <div class="form-group">
                                    <label class="fbox">Textarea</label>
                                    <textarea class="form-control" rows="3" placeholder="Enter ..." spellcheck="false" name="description"></textarea>
                                </div>
                            </div>

                        </div>


                        <div class="input-group">
                            <label class="fbox">Status</label>
                            <div class="input-group">
                                <select class="form-control" name="is_active" id="is_active">
                                    <option value="1">Active</option>
                                    <option value="0">Disable</option>
                                </select>
                                @error('is_active')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>




                </div>
                <div class="modal-footer justify-content-between">
                    <input type="hidden" name="id" id="id">
                    <input type="hidden" name="old_project" id="old_project">
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
                    <form action="{{ route('project.plot.destroy') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('DELETE')
                        <p class="modal-text">Are you sure you want to delete? <b id="delete-data"></b></p>
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
