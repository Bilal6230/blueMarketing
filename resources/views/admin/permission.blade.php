@extends('admin.layouts.master')
@push('style')
<link rel="stylesheet" href="{{ asset('css/modern-account-directory.css') }}">
<link rel="stylesheet" href="{{ asset('css/modern-permission-directory.css') }}?v=1">
@endpush
@section('content')
    <div class="content-wrapper bm-modern-accounts bm-permission-directory" data-account-kind="permission">
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
                            
                            <div class="bm-modern-export-toolbar bm-export-placeholder" aria-label="Table export toolbar loading">
  <button type="button" class="bm-modern-export-btn" disabled>Copy</button>
  <button type="button" class="bm-modern-export-btn" disabled>CSV</button>
  <button type="button" class="bm-modern-export-btn" disabled>Excel</button>
  <button type="button" class="bm-modern-export-btn" disabled>PDF</button>
  <button type="button" class="bm-modern-export-btn" disabled>Print</button>
  <button type="button" class="bm-modern-export-btn bm-modern-col-toggle" disabled>Column Visibility</button>
@can('create permission')
                            <div class="bm-permission-toolbar-actions">
                                <div class="bm-toolbar-action-buttons">
                                    <div class="btn-group">
                                        <a href="#" class="btn btn-sm btn-success" data-toggle="modal" data-target="#modal-tambah" data-backdrop="static" data-keyboard="false"><i class="fas fa-plus"></i> Add</a>
                                        <a href="{{ route('permission.reload') }}" class="btn btn-sm btn-warning"><i class="fas fa-sync-alt"></i> Reload</a>
                                    </div>
                                </div>
                            </div>
                            @endcan
                            
</div>
    <div class="card-body table-responsive bm-account-table-wrap">
                                <table id="bmAccountDirectory" data-initial-skeleton="true" class="table table-bordered bm-account-table bm-pending-directory">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Name</th>
                                            <th>Guard</th>
                                            <th>Updated</th>
                                            @canany(['update permission', 'delete permission'])
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
                                                @canany(['update permission', 'delete permission'])<td><span class="bm-cell-skeleton"></span></td>@endcanany
                                            </tr>
                                        @endfor
                                        @foreach ($data as $i)
                                            <tr>
                                                <td>{{ $loop->iteration }}</td>
                                                <td>{{ $i->name }}</td>
                                                <td>{{ $i->guard_name }}</td>
                                                <td>{{ $i->updated_at }}</td>
                                                @canany(['update permission', 'delete permission'])
                                                    <td>
                                                        <div class="btn-group">
                                                            @can('update permission')
                                                                <button class="btn btn-sm btn-primary btn-edit" data-id="{{ $i->id }}"><i class="fas fa-pencil-alt"></i></button>
                                                            @endcan
                                                            @can('delete permission')
                                                                <button class="btn btn-sm btn-danger btn-delete" data-id="{{ $i->id }}" data-name="{{ $i->name }}"><i class="fas fa-trash"></i></button>
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
                $('#modal-loading').modal({backdrop: 'static', keyboard: false, show: true});
                $.ajax({
                    url: "{{ route('permission.show') }}",
                    type: "POST",
                    dataType: "JSON",
                    data: {
                        id: id,
                        _token: "{{ csrf_token() }}"
                    },
                    success: function(data) {
                        var data = data.data;
                        $("#name").val(data.name);
                        $("#guard_name").val(data.guard_name);
                        $("#id").val(data.id);
                        $('#modal-loading').modal('hide');
                        $('#modal-edit').modal({backdrop: 'static', keyboard: false, show: true});
                    },
                    error: function(xhr) {
                        $('#modal-loading').modal('hide');
                        if(window.toastr)toastr.error((xhr.responseJSON||{}).message||'Unable to load permission details.');
                    }
                });
            });

            $(document).on("click", '.btn-delete', function() {
                let id = $(this).attr("data-id");
                let name = $(this).attr("data-name");
                $("#did").val(id);
                $("#delete-data").text(name);
                $('#modal-delete').modal({backdrop: 'static', keyboard: false, show: true});
            });
        });
    </script>
    <script src="{{ asset('js/modern-account-directory.js') }}"></script>
@endsection

@section('modal')
    {{-- Modal Add --}}
    <div class="modal fade bm-permission-modal" id="modal-tambah">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add Permission</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('permission.store') }}" method="POST" enctype="multipart/form-data">
                <div class="modal-body">

                        @csrf
                        <div class="input-group">
                            <label>Name</label>
                            <div class="input-group">
                                <input type="text" class="form-control @error('name') is-invalid @enderror" placeholder="Name" name="name" value="{{ old('name') }}">
                                @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="input-group">
                            <label>Guard</label>
                            <div class="input-group">
                                <select class="form-control" name="guard_name">
                                    <option value="web">web</option>
                                    <option value="api">api</option>
                                </select>
                                @error('guard_name')
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
    <div class="modal fade bm-permission-modal" id="modal-edit">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Permission</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('permission.update') }}" method="POST" enctype="multipart/form-data">
                <div class="modal-body">

                        @csrf
                        @method("PUT")
                        <div class="input-group">
                            <label>Name</label>
                            <div class="input-group">
                                <input type="text" class="form-control @error('name') is-invalid @enderror" placeholder="Name" name="name" id="name" value="{{ old('name') }}">
                                @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="input-group">
                            <label>Guard</label>
                            <div class="input-group">
                                <select class="form-control" name="guard_name" id="guard_name">
                                    <option value="web">web</option>
                                    <option value="api">api</option>
                                </select>
                                @error('guard_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
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
    {{-- Modal delete --}}
    <div class="modal fade bm-permission-modal" id="modal-delete">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Delete Permission</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('permission.destroy') }}" method="POST" enctype="multipart/form-data">
                <div class="modal-body">

                        @csrf
                        @method('DELETE')
                        <p class="modal-text">Are you sure you want to delete this permission? <b id="delete-data"></b></p>
                        <input type="hidden" name="id" id="did">
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
                </form>
            </div>
            <!-- /.modal-content -->
        </div>
        <!-- /.modal-dialog -->
    </div>
@endsection