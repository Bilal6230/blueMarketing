@extends('admin.layouts.master')
@push('style')
<link rel="stylesheet" href="{{ asset('css/modern-account-directory.css') }}">
<link rel="stylesheet" href="{{ asset('css/modern-user-directory.css') }}?v=2">
@endpush
@section('content')
    <div class="content-wrapper bm-modern-accounts bm-user-directory" data-account-kind="user">
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
@canany(['create user', 'read user'])
                                <div class="bm-user-toolbar-actions">
                                    <div class="bm-toolbar-action-buttons">
                                        <a href="{{ route('employee-definitions.index') }}" class="btn btn-sm btn-outline-primary mr-1"><i class="fas fa-user-cog"></i> Employee Setup</a>
                                        @can('create user')
                                            <a href="#" class="btn btn-sm btn-success" data-toggle="modal"
                                                data-target="#modal-tambah" data-backdrop="static" data-keyboard="false"><i
                                                    class="fas fa-plus"></i> Add</a>
                                        @endcan
                                    </div>
                                </div>
                            @endcan
                            
</div>
    <div class="card-body table-responsive bm-account-table-wrap">
                                <table id="bmAccountDirectory" data-initial-skeleton="true" class="table table-bordered bm-account-table bm-pending-directory">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>avatar</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Phone Number</th>
                                            <th>NIC</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Updated</th>
                                            @canany(['update user', 'delete user'])
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
                                                <td><span class="bm-cell-skeleton"></span></td>
                                                <td><span class="bm-cell-skeleton"></span></td>
                                                <td><span class="bm-cell-skeleton"></span></td>
                                                @canany(['update user', 'delete user'])<td><span class="bm-cell-skeleton"></span></td>@endcanany
                                            </tr>
                                        @endfor
                                        @foreach ($data as $i)
                                            <tr>
                                                <td>{{ $loop->iteration }}</td>
                                                <td>@if($i->avatar)<img src="{{ asset('storage/'.$i->avatar) }}" class="bm-user-avatar" alt="" loading="lazy"><span class="bm-user-avatar-fallback" style="display:none" aria-hidden="true"><i class="fas fa-user"></i></span>@else<span class="bm-user-avatar-fallback" aria-hidden="true"><i class="fas fa-user"></i></span>@endif</td>
                                                <td>{{ $i->name }}</td>
                                                <td>{{ $i->email }}</td>
                                                <td>{{ $i->phone_number }}</td>
                                                <td>{{ $i->nic_number }}</td>
                                                <td>{{ $i->roles->pluck('name')->implode(', ') }}</td>
                                                <td>
                                                    <label class="switch">
                                                        <input type="checkbox" class="status-toggle" aria-label="Change status for {{ $i->name }}"
                                                            data-id="{{ $i->id }}"
                                                            {{ $i->status_id == 1 ? 'checked' : '' }}>
                                                        <span class="slider round"></span>
                                                    </label><span class="bm-user-status-text">{{ (int)$i->status_id === 1 ? 'Active' : 'Inactive' }}</span>
                                                </td>
                                                <td>{{ $i->updated_at }}</td>
                                                @canany(['update user', 'delete user'])
                                                    <td>
                                                        <div class="btn-group">
                                                            @can('update user')
                                                                <button class="btn btn-sm btn-primary btn-edit"
                                                                    data-id="{{ $i->id }}"><i
                                                                        class="fas fa-pencil-alt"></i></button>
                                                            @endcan
                                                            @can('delete user')
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
                    url: "{{ route('user.show') }}",
                    type: "POST",
                    dataType: "JSON",
                    data: {
                        id: id,
                        _token: "{{ csrf_token() }}"
                    },
                    success: function(data) {
                        var data = data.data;
                        $("#name").val(data.name);
                        $("#email").val(data.email);
                        $("#old_email").val(data.email);
                        $("#role").val(data.role);
                        $("#gender").val(data.gender);
                        $("#nic_number").val(data.nic_number);
                        $("#phone_number").val(data.phone_number);
                        $("#department").val(data.department);
                        $("#address").val(data.address);
                        $("#project_id").val(data.project_id == null ? '' : String(data.project_id)).trigger('change');
                        $("#status_id").change().val(data.status_id);

                        $("#id").val(data.id);
                        $('#modal-loading').modal('hide');
                        $('#modal-edit').modal({
                            backdrop: 'static',
                            keyboard: false,
                            show: true
                        });
                    },
                    error: function(xhr) {
                        $('#modal-loading').modal('hide');
                        toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Unable to load user details.');
                    }
                });
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
            $(document).on('change', '.status-toggle', function() {
                let toggle = $(this);
                let id = toggle.data('id');
                let status = toggle.is(':checked') ? 1 : 0;

                toggle.prop('disabled', true).closest('.switch').addClass('status-loading');

                $.ajax({
                    url: "{{ route('user.status.toggle') }}",
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        id: id,
                        status_id: status
                    },
                    success: function(response) {
                        toggle.closest('td').find('.bm-user-status-text').text(status ? 'Active' : 'Inactive');
                        toastr.success(response.message);
                    },
                    error: function() {
                        toastr.error('Failed to update status');
                        toggle.prop('checked', !status);
                    },
                    complete: function() {
                        toggle.prop('disabled', false).closest('.switch').removeClass(
                            'status-loading');
                    }
                });
            });
        });
    </script>
    <script>
    $(function(){
        $('.bm-user-avatar').each(function(){
            var $img=$(this);
            function fallback(){ $img.hide().next('.bm-user-avatar-fallback').css('display','inline-flex'); }
            $img.on('error',fallback);
            if(this.complete && this.naturalWidth===0)fallback();
        });
    });
    </script>
    <script src="{{ asset('js/modern-account-directory.js') }}"></script>
@endsection

@section('modal')
    {{-- Modal Add --}}
    <div class="modal fade bm-user-modal" id="modal-tambah">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add User</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('user.store') }}" method="POST" enctype="multipart/form-data">
                <div class="modal-body">

                        @csrf

                        <div class="row">
                            <!-- Name Field -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Name</label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror"
                                        placeholder="Name" name="name" value="{{ old('name') }}">
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Email Field -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" class="form-control @error('email') is-invalid @enderror"
                                        placeholder="Email" name="email" value="{{ old('email') }}">
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Gender Field -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Gender</label>
                                    <select class="form-control" name="gender">
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                    </select>
                                    @error('gender')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- NIC Number Field -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Nic Number</label>
                                    <input type="number" class="form-control @error('nic_number') is-invalid @enderror"
                                        placeholder="NIC" name="nic_number" value="{{ old('nic_number') }}">
                                    @error('nic_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Phone Number Field -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Phone Number</label>
                                    <input type="number" class="form-control @error('phone_number') is-invalid @enderror"
                                        placeholder="Phone Number" name="phone_number" value="{{ old('phone_number') }}">
                                    @error('phone_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Department Field -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Department</label>
                                    <input type="text" class="form-control @error('department') is-invalid @enderror"
                                        placeholder="Department" name="department" value="{{ old('department') }}">
                                    @error('department')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Address Field -->
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>Address</label>
                                    <input type="text" class="form-control @error('address') is-invalid @enderror"
                                        placeholder="Address" name="address" value="{{ old('address') }}">
                                    @error('address')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>


                        </div>

                        <div class="row">

                            <!-- Status Field -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Project</label>
                                    <select class="form-control" name="project_id">
                                        <option value="">Select an option</option>

                                        @foreach ($projects as $v)
                                            <option value="{{ $v->id }}">{{ $v->project }}</option>
                                        @endforeach
                                    </select>
                                    @error('project_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Status Field -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Status</label>
                                    <select class="form-control" name="status_id">
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                    @error('status_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>


                        <div class="input-group">
                            <label>Profile Image</label>
                            <div class="input-group">
                                <input type="file" class="form-control @error('avatar') is-invalid @enderror"
                                    placeholder="avatar" name="avatar" value="{{ old('avatar') }}">
                                @error('avatar')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="input-group">
                            <label>Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control @error('password') is-invalid @enderror"
                                    placeholder="Password" name="password" value="{{ old('password') }}">
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="input-group">
                            <label>Role</label>
                            <div class="input-group">
                                <select class="form-control" name="role">
                                    @foreach ($role as $i)
                                        <option value="{{ $i->name }}">{{ $i->name }}</option>
                                    @endforeach
                                </select>
                                @error('role')
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
    <div class="modal fade bm-user-modal" id="modal-edit">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit User</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('user.update') }}" method="POST" enctype="multipart/form-data">
                <div class="modal-body">

                        @csrf
                        @method('PUT')
                        <div class="row">
                            <!-- Name Field -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Name</label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror"
                                        placeholder="Name" name="name" id="name" value="{{ old('name') }}">
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Email Field -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" class="form-control @error('email') is-invalid @enderror"
                                        placeholder="Email" name="email" id="email" value="{{ old('email') }}">
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <label>Gender</label>
                                    <div class="input-group">
                                        <select class="form-control" name="gender" id="gender">
                                            <option value="male">Male</option>
                                            <option value="female">Female</option>
                                        </select>
                                        @error('gender')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                            </div>

                            <div class="col-md-6">
                                <div class="input-group">
                                    <label>Nic Number</label>
                                    <div class="input-group">
                                        <input type="number"
                                            class="form-control @error('nic_number') is-invalid @enderror"
                                            placeholder="NIC " name="nic_number" id="nic_number"
                                            value="{{ old('nic_number') }}">
                                        @error('nic_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <label>Phone Number</label>
                                    <div class="input-group">
                                        <input type="number"
                                            class="form-control @error('phone_number') is-invalid @enderror"
                                            placeholder="Phone Number" name="phone_number" id="phone_number"
                                            value="{{ old('phone_number') }}">
                                        @error('phone_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                            </div>

                            <div class="col-md-6">
                                <div class="input-group">
                                    <label>Department</label>
                                    <div class="input-group">
                                        <input type="text"
                                            class="form-control @error('department') is-invalid @enderror"
                                            placeholder="Department" name="department" id="department"
                                            value="{{ old('department') }}">
                                        @error('department')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="input-group">
                                    <label>Address</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control @error('address') is-invalid @enderror"
                                            placeholder="Address" name="address" id="address"
                                            value="{{ old('address') }}">
                                        @error('address')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">

                            <!-- Status Field -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Project</label>
                                    <select class="form-control" name="project_id" id="project_id">
                                        <option value="">Select an option</option>

                                        @foreach ($projects as $v)
                                            <option value="{{ $v->id }}">{{ $v->project }}</option>
                                        @endforeach
                                    </select>
                                    @error('project_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Status Field -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Status</label>
                                    <select class="form-control" name="status_id" id="status_id">
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                    @error('status_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>




                        <div class="input-group">
                            <label>Profile Image</label>
                            <div class="input-group">
                                <input type="file" class="form-control @error('avatar') is-invalid @enderror"
                                    placeholder="avatar" name="avatar" value="{{ old('avatar') }}">
                                @error('avatar')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="input-group">
                            <label>Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control @error('password') is-invalid @enderror"
                                    placeholder="Password" name="password" value="{{ old('password') }}">
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="input-group">
                            <label>Role</label>
                            <div class="input-group">
                                <select class="form-control" name="role" id="role">
                                    @foreach ($role as $i)
                                        <option value="{{ $i->name }}">{{ $i->name }}</option>
                                    @endforeach
                                </select>
                                @error('role')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <input type="hidden" name="id" id="id">
                    <input type="hidden" name="old_email" id="old_email">
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
    <div class="modal fade bm-user-modal" id="modal-delete">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Clear Data</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('user.destroy') }}" method="POST" enctype="multipart/form-data">
                <div class="modal-body">

                        @csrf
                        @method('DELETE')
                        <p class="modal-text">Are you sure you want to delete? <b id="delete-data"></b></p>
                        <input type="hidden" name="id" id="did">
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger">Save</button>
                </div>
                </form>
            </div>
            <!-- /.modal-content -->
        </div>
        <!-- /.modal-dialog -->
    </div>
@endsection
