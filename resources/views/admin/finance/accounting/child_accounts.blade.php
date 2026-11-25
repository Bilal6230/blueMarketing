@extends('admin.layouts.master')
@push('style')<link rel="stylesheet" href="{{ asset('css/modern-account-directory.css') }}">@endpush
@section('content')
    <div class="content-wrapper bm-modern-accounts" data-account-kind="subhead">
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
                            @can('create accounting')
                                <div class="card-header bm-account-create-source">
                                    {{-- <h3 class="card-title">
                                        <a href="#" class="btn btn-sm btn-success" data-toggle="modal" data-target="#modal-tambah" data-backdrop="static" data-keyboard="false"><i class="fas fa-plus"></i> Add</a>
                                    </h3> --}}
                                    <div>
                                        <form action="{{ route('accounting.subhead_store') }}" method="POST"
                                            enctype="multipart/form-data">
                                            @csrf
                                            <div class="row">
                                                <div class="col-sm-6">
                                                    <div class="input-group">
                                                        <label class="fbox">Party Account Name</label>
                                                        <div class="input-group">
                                                            <input type="text"
                                                                class="form-control @error('name') is-invalid @enderror"
                                                                placeholder="Subhead Account Name" name="name"
                                                                value="{{ old('name') }}">
                                                            @error('name')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-sm-6">
                                                    <div class="input-group">
                                                        <label class="fbox">Party Account Name (Urdu)</label>
                                                        <div class="input-group">
                                                            <input type="text"
                                                                class="form-control @error('urdu_name') is-invalid @enderror"
                                                                placeholder="Subhead Account Name" name="urdu_name"
                                                                value="{{ old('urdu_name') }}">
                                                            @error('urdu_name')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-sm-6">
                                                    <div class="input-group">
                                                        <label>Status</label>
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
                                                <div class="col-sm-6">
                                                    <div class="input-group">
                                                        <label class="fbox">NIC Number</label>
                                                        <div class="input-group">
                                                            <div class="input-group-prepend">
                                                                <span class="input-group-text"><i
                                                                        class="fas fa-id-card"></i></span>
                                                            </div>
                                                            <input type="text"
                                                                class="form-control @error('cnic') is-invalid @enderror"
                                                                placeholder="CNIC Number Here" name="cnic"
                                                                value="{{ old('cnic') }}">
                                                            @error('cnic')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-sm-6">
                                                    <div class="input-group">
                                                        <label class="fbox">Phone Number</label>
                                                        <div class="input-group">
                                                            <div class="input-group-prepend">
                                                                <span class="input-group-text"><i
                                                                        class="fas fa-phone"></i></span>
                                                            </div>
                                                            <input type="text"
                                                                class="form-control @error('phone') is-invalid @enderror"
                                                                placeholder="Phone Number" name="phone"
                                                                value="{{ old('phone') }}">
                                                            @error('phone')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer justify-content-between">
                                                <button type="submit" class="btn btn-primary">Save</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endcan
                            <!-- /.card-header -->
                            <div class="bm-modern-export-toolbar bm-export-placeholder" aria-label="Table export toolbar loading">
  <button type="button" class="bm-modern-export-btn" disabled>Copy</button>
  <button type="button" class="bm-modern-export-btn" disabled>CSV</button>
  <button type="button" class="bm-modern-export-btn" disabled>Excel</button>
  <button type="button" class="bm-modern-export-btn" disabled>PDF</button>
  <button type="button" class="bm-modern-export-btn" disabled>Print</button>
  <button type="button" class="bm-modern-export-btn bm-modern-col-toggle" disabled>Column Visibility</button>
</div>
    <div class="card-body table-responsive bm-account-table-wrap">
                                <table id="bmAccountDirectory" data-initial-skeleton="true" class="table table-bordered bm-account-table bm-pending-directory">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Name</th>
                                            <th>CNIC</th>
                                            <th>Phone</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            @canany(['update accounting','delete accounting'])
                                                <th>Action</th>
                                            @endcanany
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @for ($skeletonIndex = 0; $skeletonIndex < 6; $skeletonIndex++)
                                            <tr class="bm-cell-skeleton-row" aria-hidden="true">
                                                <td><span class="bm-cell-skeleton"></span></td>
                                                <td><span class="bm-cell-skeleton"></span></td>
                                                <td><span class="bm-cell-skeleton"></span></td>
                                                <td><span class="bm-cell-skeleton"></span></td>
                                                <td><span class="bm-cell-skeleton"></span></td>
                                                <td><span class="bm-cell-skeleton"></span></td>
                                                @canany(['update accounting','delete accounting'])<td><span class="bm-cell-skeleton"></span></td>@endcanany
                                            </tr>
                                        @endfor
                                        @foreach ($data as $i)
                                            <tr>
                                                <td>{{ $loop->iteration }}</td>
                                                <td>
                                                    {{ $i->name }} @if ($i->urdu_name)
                                                        / {{ $i->urdu_name }}
                                                    @endif
                                                </td>
                                                <td>
                                                    {{ $i->cnic }}
                                                </td>
                                                <td>
                                                    {{ $i->phone }}
                                                </td>
                                                <td><span class="bm-account-status {{ (int) $i->is_active === 1 ? '' : 'inactive' }}">{{ (int) $i->is_active === 1 ? 'Active' : 'Inactive' }}</span></td>
                                                <td>{{ optional($i->created_at)->format('Y-m-d H:i:s') }}</td>
                                                @canany(['update accounting','delete accounting'])
                                                    <td>
                                                        <div class="btn-group">
                                                            @can('update accounting')
                                                                <button class="btn btn-sm btn-primary btn-edit"
                                                                    data-id="{{ $i->id }}"><i
                                                                        class="fas fa-pencil-alt"></i></button>
                                                            @endcan
                                                            @can('delete accounting')
                                                                <button class="btn btn-sm btn-danger btn-delete"
                                                                    data-id="{{ $i->id }}"
                                                                    data-name="{{ $i->name }}"><i
                                                                        class="fas fa-trash"></i></button>
                                                            @endcan
                                                        </div>
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
                    url: "{{ route('accounting.subhead_show') }}",
                    type: "POST",
                    dataType: "JSON",
                    data: {
                        id: id,
                        _token: "{{ csrf_token() }}"
                    },
                    success: function(data) {
                        
                        var data = data.data;
                        $("#name").val(data.name);
                        $("#urdu_name").val(data.urdu_name);
                        $("#id").val(data.id);
                        $("#cnic").val(data.cnic);
                        $("#phone").val(data.phone);
                        $('#modal-edit [name="is_active"]').val(String(data.is_active));
                        $('#subhead_status_change_reason').val('');
                        $('#modal-loading').modal('hide');
                        $('#modal-edit').modal({
                            backdrop: 'static',
                            keyboard: false,
                            show: true
                        });
                    },
                });
            });

            $(document).on("click", '.btn-delete', function() {
                let id = $(this).attr("data-id");
                let name = $(this).attr("data-name");
                $("#did").val(id);
                $("#delete-data").text(name);
                $('#modal-delete').modal({
                    backdrop: 'static',
                    keyboard: false,
                    show: true
                });
            });
        });
    </script>
    <script src="{{ asset('js/modern-account-directory.js') }}"></script>
@endsection

@section('modal')
    {{-- Modal Add --}}
    {{-- <div class="modal fade" id="modal-tambah">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add User</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('accounting.head_store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="row">
                            <div class="col-sm-3">
                                <div class="input-group">
                                    <label class="fbox">First Name</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control @error('name') is-invalid @enderror" placeholder="First Name" name="name" value="{{ old('name') }}">
                                        @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                        </div>
                        <div class="row">


                            <div class="col-sm-12">

                                <div class="input-group">
                                    <label>Status</label>
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
    </div> --}}
    {{-- Modal Update --}}
    <div class="modal fade bm-account-finance-modal" id="modal-edit">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Party Account</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('accounting.subhead_update') }}" method="POST"
                        enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-sm-4">
                                <div class="input-group">
                                    <label class="fbox">Party Account Name</label>
                                    <div class="input-group">
                                        <input id="name" type="text"
                                            class="form-control @error('name') is-invalid @enderror"
                                            placeholder="Party Name" name="name" value="{{ old('name') }}">
                                        @error('name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="input-group">
                                    <label class="fbox">Party Account Name (Urdu)</label>
                                    <div class="input-group">
                                        <input type="text" id="urdu_name"
                                            class="form-control @error('urdu_name') is-invalid @enderror"
                                            placeholder="Subhead Account Name" name="urdu_name"
                                            value="{{ old('urdu_name') }}">
                                        @error('urdu_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="input-group">
                                    <label class="fbox">CNIC Number</label>
                                    <div class="input-group">
                                        <input id="cnic" type="text"
                                            class="form-control @error('cnic') is-invalid @enderror"
                                            placeholder="CNIC Number" name="cnic" value="{{ old('cnic') }}">
                                        @error('cnic')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="input-group">
                                    <label class="fbox">Phone Number</label>
                                    <div class="input-group">
                                        <input id="phone" type="text"
                                            class="form-control @error('phone') is-invalid @enderror"
                                            placeholder="Phone Number" name="phone" value="{{ old('phone') }}">
                                        @error('phone')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-12">
                                <div class="input-group">
                                    <label>Status</label>
                                    <div class="input-group">
                                        <select id="is_active" class="form-control" name="is_active">
                                            <option value="1">Active</option>
                                            <option value="0">Disable</option>
                                        </select>
                                        @error('is_active')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group mt-2">
                            <label for="subhead_status_change_reason">Status change reason</label>
                            <textarea id="subhead_status_change_reason" class="form-control @error('status_change_reason') is-invalid @enderror"
                                name="status_change_reason" rows="2" maxlength="500"
                                placeholder="Required only when changing Active/Inactive status"></textarea>
                            @error('status_change_reason')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="modal-footer justify-content-between">
                            <input type="hidden" name="id" id="id">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
                <!-- /.modal-content -->
            </div>
            <!-- /.modal-dialog -->
        </div>
    </div>
    {{-- Modal delete --}}
    <div class="modal fade bm-account-finance-modal" id="modal-delete">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Delete Party Account</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('accounting.subhead_destroy') }}" method="POST"
                        enctype="multipart/form-data">
                <div class="modal-body">
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

