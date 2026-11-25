@extends('admin.layouts.master')
@push('style')<link rel="stylesheet" href="{{ asset('css/modern-account-directory.css') }}">@endpush
@section('content')
    <div class="content-wrapper bm-modern-accounts" data-account-kind="head">
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
                                        <form action="{{ route('accounting.head_store') }}" method="POST"
                                            enctype="multipart/form-data">
                                            @csrf
                                            <div class="row">
                                                <div class="col-sm-6">
                                                    <div class="input-group">
                                                        <label class="fbox">Head Account Name</label>
                                                        <div class="input-group">
                                                            <input type="text"
                                                                class="form-control @error('name') is-invalid @enderror"
                                                                placeholder="Head Account Name" name="name"
                                                                value="{{ old('name') }}">
                                                            @error('name')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-sm-3">
                                                    <div class="input-group">
                                                        <label>Type</label>
                                                        <div class="input-group">
                                                            <select class="form-control" name="acct_type">
                                                                <option value="">Select Account Type</option>
                                                                @foreach ($accountTypes as $type)
                                                                    <option value="{{ $type->id }}"
                                                                        {{ old('acct_type') == $type->id ? 'selected' : '' }}>
                                                                        {{ $type->name }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                            @error('is_active')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-sm-3">
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
                                                <button type="submit" class="btn btn-primary">Save</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endcan
                            <!-- /.card-header -->
                            <div class="card-body table-responsive bm-account-table-wrap"><div class="bm-account-loading" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
                                <table id="bmAccountDirectory" class="table table-bordered bm-account-table" style="visibility:hidden">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Name</th>
                                            <th>Type</th>
                                            @canany(['update accounting','delete accounting'])
                                                <th>Action</th>
                                            @endcanany
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($data as $i)
                                            <tr>
                                                <td>{{ $loop->iteration }}</td>
                                                <td>
                                                    {{ $i->name }}
                                                </td>
                                                <td>
                                                    {{ getHead($i->acct_type) }}
                                                </td>
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
                    url: "{{ route('accounting.head_show') }}",
                    type: "POST",
                    dataType: "JSON",
                    data: {
                        id: id,
                        _token: "{{ csrf_token() }}"
                    },
                    success: function(data) {
                        
                        var data = data.data;
                        $("#name").val(data.name);
                        $("#id").val(data.id);
                        $('#modal-edit [name="is_active"]').val(String(data.is_active));
                        $('#head_status_change_reason').val('');
                        $("#acct_type").val(data
                            .acct_type); // ✅ This sets the correct option selected
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
                    <h4 class="modal-title">Edit Main Account</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('accounting.head_update') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="input-group">
                                    <label class="fbox">First Name</label>
                                    <div class="input-group">
                                        <input id="name" type="text"
                                            class="form-control @error('name') is-invalid @enderror"
                                            placeholder="First Name" name="name" value="{{ old('name') }}">
                                        @error('name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="input-group">
                                    <label>Type</label>
                                    <div class="input-group">
                                        <select class="form-control" id="acct_type" name="acct_type">
                                            <option value="">Select Account Type</option>
                                            @foreach ($accountTypes as $type)
                                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('is_active')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-4">
                                <div class="input-group mt-2">
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
                            <label for="head_status_change_reason">Status change reason</label>
                            <textarea id="head_status_change_reason" class="form-control @error('status_change_reason') is-invalid @enderror"
                                name="status_change_reason" rows="2" maxlength="500"
                                placeholder="Required only when changing Active/Inactive status"></textarea>
                            @error('status_change_reason')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="modal-footer justify-content-between mt-2">
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
                    <h4 class="modal-title">Delete Main Account</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('accounting.head_destroy') }}" method="POST" enctype="multipart/form-data">
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

