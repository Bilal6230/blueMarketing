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
                            @can('create lead')
                            <div class="card-header">
                                <h3 class="card-title">
                                    <a href="#" class="btn btn-sm btn-success" data-toggle="modal" data-target="#modal-tambah" data-backdrop="static" data-keyboard="false"><i class="fas fa-plus"></i> Add</a>
                                    <select class="custom-select" id="filter">
                                        <option value="all">All Leads</option>
                                        <option value="schedule">Schedule Now</option>
                                        <option value="today">Add Today</option>

                                        </select>
                                </h3>
                            </div>
                            @endcan
                            <!-- /.card-header -->
                            <div class="card-body table-responsive">
                                <table id="example1" class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Name</th>
                                            <th>1st Mobile Number</th>
                                            <th>Status</th>
                                            <th>Project</th>
                                            <th>Assign To</th>
                                            <th>Business</th>
                                            <th>Schedule Date</th>
                                            @canany(['update lead'])
                                                <th>Action</th>
                                            @endcanany
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($data as $i)
                                            <tr>
                                                <td>{{ $loop->iteration }}</td>
                                                <td>
                                                    <a href="{{ route('lead.work', ['id' => $i->id,'status' => true]) }}">
                                                    {{ $i->first_name }} {{ $i->last_name }}
                                                    </a>
                                                </td>
                                                <td><i class="fa fa-phone"></i><a href="tel:{{ $i->phone_number }}"> {{ $i->phone_number }}</a> </td>
                                                <td><span class="badge {{ Setting::getColorClass($i->follow_status) }}">{{ Setting::getCallStatus($i->follow_status) }}</span> </td>
                                                <td><span class="btn btn-sm  {{ Setting::getProjectColorClass($i->project_id) }}">{{ $i->project_name }}</span> </td>
                                                <td>
                                                    @foreach ($i->users as $u )
                                                    <button class="btn btn-sm btn-primary " >{{ $u->name }}</button>
                                                    @endforeach
                                                </td>
                                                <td>{{ $i->business }}</td>
                                                <td>{{ Setting::getformatedDate($i->follow_up) }}</td>
                                                @canany(['update lead', 'delete lead'])
                                                    <td>
                                                        <div class="btn-group">
                                                            @can('update lead')
                                                                <button class="btn btn-sm btn-primary btn-edit" data-id="{{ $i->id }}"><i class="fas fa-pencil-alt"></i></button>
                                                            @endcan
                                                            @can('delete lead')
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

            $("#filter").change(function () {
                var filter = $("#filter").val();
                var pathname = window.location.pathname; // Returns path only (/path/example.html)
                var url    = window.location.href;      // Returns full URL (https://example.com/path/example.html)
                var origin   = window.location.origin;
                origin = origin+"/admin/crm/lead?filter="+filter
                window.location.replace(origin);

            });

            $(document).on("click", '.btn-edit', function() {
                let id = $(this).attr("data-id");
                $('#modal-loading').modal({backdrop: 'static', keyboard: false, show: true});
                $.ajax({
                    url: "{{ route('crm.lead.show') }}",
                    type: "POST",
                    dataType: "JSON",
                    data: {
                        id: id,
                        _token: "{{ csrf_token() }}"
                    },
                    success: function(data) {
                        console.log(data);
                        var data = data.data;
                        $("#assign_id").val(data.assign_id);
                        $("#first_name").val(data.first_name);
                        $("#last_name").val(data.last_name);
                        $("#gender").val(data.gender);
                        $("#type").val(data.type);
                        $("#nic_number").val(data.nic_number);
                        $("#phone_number").val(data.phone_number);
                        $("#mobile_number").val(data.mobile_number); // This line populates the 2nd phone number
                        $("#zone_id").val(data.zone_id);
                        $("#area_id").val(data.area_id);
                        $("#business").val(data.business);
                        $("#designation").val(data.designation);
                        $("#home_address").val(data.home_address);
                        $("#office_address").val(data.office_address);
                        $("#projects_id").val(data.project_id);

                        $("#id").val(data.id);
                        $("#old_phone").val(data.phone_number);

                        $('#modal-loading').modal('hide');
                        $('#modal-edit').modal({backdrop: 'static', keyboard: false, show: true});
                    },
                });
            });

            $(document).on("click", '.btn-delete', function() {
                let id = $(this).attr("data-id");
                let name = $(this).attr("data-name");
                $("#did").val(id);
                $("#delete-data").html(name);
                $('#modal-delete').modal({backdrop: 'static', keyboard: false, show: true});
            });
        });
    </script>
@endsection

@section('modal')
    {{-- Modal Add --}}
    <div class="modal fade" id="modal-tambah">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add Customer Lead</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('crm.lead.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @if ($power == "superadmin")
                            <div class="row">


                                <div class="col-sm-6">

                                    <div class="form-group">
                                        <label>Assign To</label>
                                        <div class="select2-purple">
                                        <select class="select2 select2-hidden-accessible form-control @error('assign_id') is-invalid @enderror" name="assign_id[]" multiple="" data-placeholder="Select a State" data-dropdown-css-class="select2-purple" style="width: 100%;" data-select2-id="16" tabindex="-1" aria-hidden="true">
                                            @foreach ($users as $u )
                                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                                            @endforeach
                                        </select>
                                            @error('assign_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>


                                </div>

                                <div class="col-sm-6">

                                    <div class="input-group">
                                        <label >Monitoring By</label>
                                        <div class="input-group">
                                            <select class="form-control" name="follow_id">
                                                @foreach ($users as $u )
                                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                                @endforeach

                                            </select>
                                            @error('follow_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>


                                </div>




                            </div>
                            <hr>
                        @else

                        <input type="text" id="assign_id" class="form-control @error('assign_id') is-invalid @enderror"  name="assign_id[]" value="{{ Auth::user()->id }}" hidden="true">
                        <input type="text" id="follow_id" class="form-control @error('follow_id') is-invalid @enderror"  name="follow_id" value="1" hidden="true">

                        @endif

                        <div class="row">
                            <div class="col-sm-3">

                                <div class="input-group">
                                    <label class="fbox">First Name</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control @error('first_name') is-invalid @enderror" placeholder="First Name" name="first_name" value="{{ old('first_name') }}">
                                        @error('first_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-3">

                                <div class="input-group">
                                    <label class="fbox">Last Name</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control @error('last_name') is-invalid @enderror" placeholder="Last Name" name="last_name" value="{{ old('last_name') }}">
                                        @error('last_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-2">

                                <div class="input-group">
                                    <label class="fbox">Relation</label>
                                    <div class="input-group">
                                        <select class="form-control" name="relate">
                                            <option value="S/O">S/O</option>
                                            <option value="D/O">D/O</option>
                                            <option value="W/O">W/O</option>

                                        </select>
                                        @error('relate')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4">

                                <div class="input-group">
                                    <label class="fbox">Father Name</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control @error('father_name') is-invalid @enderror" placeholder="Last Name" name="father_name" value="{{ old('father_name') }}">
                                        @error('father_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-6">

                                <div class="input-group">
                                    <label class="fbox">Gender</label>
                                    <div class="input-group">
                                        <select class="form-control" name="gender">
                                            <option value="1">Male</option>
                                            <option value="2">Female</option>
                                        </select>
                                        @error('gender')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-6">

                                <div class="input-group">
                                    <label class="fbox">Type</label>
                                    <div class="input-group">
                                        <select class="form-control @error('type') is-invalid @enderror" name="type">
                                            <option >Select One</option>
                                            <option value="1">Dealer</option>
                                            <option value="2">Sub Dealer</option>
                                            <option value="3">Customer</option>

                                        </select>
                                        @error('type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="row">
                            <div class="col-sm-6">

                                <div class="input-group">
                                    <label class="fbox">NIC Number</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control @error('nic_number') is-invalid @enderror" placeholder="NIC" name="nic_number" value="{{ old('nic_number') }}">
                                        @error('nic_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-3">


                                <div class="input-group">
                                    <label class="fbox">1st Phone Number</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                        </div>
                                        <input type="text" class="form-control @error('phone_number') is-invalid @enderror" placeholder="1st Phone Number" name="phone_number" value="{{ old('phone_number') }}">
                                        @error('phone_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-3">

                                <div class="input-group">
                                    <label class="fbox">2nd Phone Number</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                        </div>
                                        {{-- Removed 'readonly' attribute here --}}
                                        <input type="text" class="form-control @error('mobile_number') is-invalid @enderror" placeholder="2nd Phone Number" name="mobile_number" value="{{ old('mobile_number') }}">
                                        @error('mobile_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="row">
                            <div class="col-sm-3">

                                <div class="input-group">
                                    <label class="fbox">Sector</label>
                                    <div class="input-group">
                                        <select class="form-control" name="zone_id">
                                            @foreach (Setting::get_active_zone() as $v )
                                                <option value="{{ $v->id }}">{{ $v->zone_name }}</option>
                                            @endforeach

                                        </select>
                                        @error('sectors')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                            </div>

                            <div class="col-sm-3">

                                <div class="input-group">
                                    <label class="fbox">Business Area</label>
                                    <div class="input-group">
                                        <select class="form-control" name="area_id">
                                            @foreach (Setting::get_active_area() as $v )
                                                <option value="{{ $v->id }}">{{ $v->name }}</option>
                                            @endforeach

                                        </select>
                                        @error('area_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                            </div>



                            <div class="col-sm-3">

                                <div class="input-group">
                                    <label class="fbox">Business</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control @error('business') is-invalid @enderror" placeholder="Main Business" name="business" value="{{ old('business') }}">
                                        @error('business')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-3">

                                <div class="input-group">
                                    <label class="fbox">Designation</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control @error('designation') is-invalid @enderror"  name="designation" value="{{ old('designation') }}">
                                        @error('designation')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>





                        </div>


                        <div class="row">


                            <div class="col-sm-6">

                                <div class="input-group">
                                    <label class="fbox">Home Address</label>
                                    <div class="input-group ">
                                        <div class="form-group col-sm-12">
                                            <textarea class="form-control @error('home_address') is-invalid @enderror" rows="3" placeholder="Enter ..." spellcheck="false" name="home_address">{{ old('home_address') }}</textarea>
                                            @error('home_address')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-6">

                                <div class="input-group">
                                    <label class="fbox">Office Address</label>
                                    <div class="input-group ">
                                        <div class="form-group col-sm-12">
                                            <textarea class="form-control @error('office_address') is-invalid @enderror" rows="3" placeholder="Enter ..." spellcheck="false" name="office_address">{{ old('office_address') }}</textarea>
                                            @error('office_address')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

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
                                    <label>Project</label>
                                    <div class="input-group">
                                        <select class="form-control select2 @error('projects_id') is-invalid @enderror" name="projects_id" >
                                            <option value="">Select an option</option>

                                            @foreach ($projects as $v)
                                                <option value="{{ $v->id }}">{{ $v->project }}</option>
                                            @endforeach
                                        </select>
                                        @error('projects_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
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
    {{-- Modal Update --}}
    <div class="modal fade" id="modal-edit">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Customer Lead</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('crm.lead.update') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method("PUT")
                        @if ($power == "superadmin")
                            <div class="row">


                                <div class="col-sm-6">

                                    <div class="form-group">
                                        <label>Assign To</label>
                                        <div class="select2-purple">
                                        <select id="assign_id" class="select2 select2-hidden-accessible form-control @error('assign_id') is-invalid @enderror" name="assign_id[]" multiple="" data-placeholder="Select a State" data-dropdown-css-class="select2-purple" style="width: 100%;" data-select2-id="15" tabindex="-1" aria-hidden="true">
                                            @foreach ($users as $u )
                                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                                            @endforeach
                                        </select>
                                            @error('assign_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>


                                </div>

                                <div class="col-sm-6">

                                    <div class="input-group">
                                        <label >Monitoring By</label>
                                        <div class="input-group">
                                            <select class="form-control" name="follow_id" id="follow_id">
                                                @foreach ($users as $u )
                                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                                @endforeach

                                            </select>
                                            @error('follow_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>


                                </div>




                            </div>
                            <hr>
                        @else

                        <input type="text" id="assign_id" class="form-control @error('assign_id') is-invalid @enderror"  name="assign_id[]" value="{{ Auth::user()->id }}" hidden="true">
                        <input type="text" id="follow_id" class="form-control @error('follow_id') is-invalid @enderror"  name="follow_id" value="1" hidden="true">

                        @endif

                        <div class="row">
                            <div class="col-sm-3">

                                <div class="input-group">
                                    <label class="fbox">First Name</label>
                                    <div class="input-group">
                                        <input id="first_name" type="text" class="form-control @error('first_name') is-invalid @enderror" placeholder="First Name" name="first_name" value="{{ old('first_name') }}">
                                        @error('first_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-3">

                                <div class="input-group">
                                    <label class="fbox">Last Name</label>
                                    <div class="input-group">
                                        <input id="last_name" type="text" class="form-control @error('last_name') is-invalid @enderror" placeholder="Last Name" name="last_name" value="{{ old('last_name') }}">
                                        @error('last_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-2">

                                <div class="input-group">
                                    <label class="fbox">Relation</label>
                                    <div class="input-group">
                                        <select class="form-control" name="relate">
                                            <option value="S/O">S/O</option>
                                            <option value="D/O">D/O</option>
                                            <option value="W/O">W/O</option>

                                        </select>
                                        @error('relate')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-4">

                                <div class="input-group">
                                    <label class="fbox">Father Name</label>
                                    <div class="input-group">
                                        <input id="father_name" type="text" class="form-control @error('father_name') is-invalid @enderror" placeholder="Last Name" name="father_name" value="{{ old('father_name') }}">
                                        @error('father_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-6">

                                <div class="input-group">
                                    <label class="fbox">Gender</label>
                                    <div class="input-group">
                                        <select id="gender" class="form-control" name="gender">
                                            <option value="1">Male</option>
                                            <option value="2">Female</option>
                                        </select>
                                        @error('gender')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-6">

                                <div class="input-group">
                                    <label class="fbox">Type</label>
                                    <div class="input-group">
                                        <select id="type" class="form-control" name="type">
                                            <option >Select One</option>
                                            <option value="1">Dealer</option>
                                            <option value="2">Sub Dealer</option>
                                            <option value="3">Customer</option>

                                        </select>
                                        @error('type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="row">
                            <div class="col-sm-6">

                                <div class="input-group">
                                    <label class="fbox">NIC Number</label>
                                    <div class="input-group">
                                        <input id="nic_number" type="text" class="form-control @error('nic_number') is-invalid @enderror" placeholder="NIC" name="nic_number" value="{{ old('nic_number') }}">
                                        @error('nic_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-3">


                                <div class="input-group">
                                    <label class="fbox">1st Phone Number</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                        </div>
                                        {{-- Removed 'readonly' attribute here --}}
                                        <input id="phone_number" type="text" class="form-control @error('phone_number') is-invalid @enderror" placeholder="1st Phone Number" name="phone_number" value="{{ old('phone_number') }}">
                                        @error('phone_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-3">

                                <div class="input-group">
                                    <label class="fbox">2nd Phone Number</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                        </div>
                                        {{-- Removed 'readonly' attribute here to allow editing --}}
                                        <input id="mobile_number" type="text" class="form-control @error('mobile_number') is-invalid @enderror" placeholder="2nd Phone Number" name="mobile_number" value="{{ old('mobile_number') }}">
                                        @error('mobile_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="row">
                            <div class="col-sm-3">

                                <div class="input-group">
                                    <label class="fbox">Sector</label>
                                    <div class="input-group">
                                        <select id="zone_id" class="form-control" name="zone_id">
                                            @foreach (Setting::get_active_zone() as $v )
                                                <option value="{{ $v->id }}">{{ $v->zone_name }}</option>
                                            @endforeach

                                        </select>
                                        @error('sectors')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                            </div>

                            <div class="col-sm-3">

                                <div class="input-group">
                                    <label class="fbox">Business Area</label>
                                    <div class="input-group">
                                        <select id="area_id" class="form-control" name="area_id">
                                            @foreach (Setting::get_active_area() as $v )
                                                <option value="{{ $v->id }}">{{ $v->name }}</option>
                                            @endforeach

                                        </select>
                                        @error('area_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                            </div>



                            <div class="col-sm-3">

                                <div class="input-group">
                                    <label class="fbox">Business</label>
                                    <div class="input-group">
                                        <input id="business" type="text" class="form-control @error('business') is-invalid @enderror" placeholder="Main Business" name="business" value="{{ old('business') }}">
                                        @error('business')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-3">

                                <div class="input-group">
                                    <label class="fbox">Designation</label>
                                    <div class="input-group">
                                        <input id="designation" type="text" class="form-control @error('designation') is-invalid @enderror"  name="designation" value="{{ old('designation') }}">
                                        @error('designation')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>





                        </div>


                        <div class="row">


                            <div class="col-sm-6">

                                <div class="input-group">
                                    <label class="fbox">Home Address</label>
                                    <div class="input-group ">
                                        <div class="form-group col-sm-12">
                                            <textarea id="home_address" class="form-control @error('home_address') is-invalid @enderror" rows="3" placeholder="Enter ..." spellcheck="false" name="home_address">{{ old('home_address') }}</textarea>
                                            @error('home_address')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-6">

                                <div class="input-group">
                                    <label class="fbox">Office Address</label>
                                    <div class="input-group ">
                                        <div class="form-group col-sm-12">
                                            <textarea id="office_address" class="form-control @error('office_address') is-invalid @enderror" rows="3" placeholder="Enter ..." spellcheck="false" name="office_address">{{ old('office_address') }}</textarea>
                                            @error('office_address')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                    </div>
                                </div>
                            </div>



                        </div>

                        <div class="row">


                            <div class="col-sm-6">

                                <div class="input-group">
                                    <label>Status</label>
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

                            <div class="col-sm-6">

                                <div class="input-group">
                                    <label>Project</label>
                                    <div class="input-group">
                                        <select class="form-control " name="projects_id" id="projects_id" >

                                            @foreach ($projects as $v)
                                                <option value="{{ $v->id }}">{{ $v->project }}</option>
                                            @endforeach
                                        </select>
                                        @error('projects_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>


                        </div>


                </div>
                <div class="modal-footer justify-content-between">
                    <input type="hidden" name="id" id="id">
                    <input type="hidden" name="old_phone" id="old_phone">
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
                    <form action="{{ route('user.destroy') }}" method="POST" enctype="multipart/form-data">
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
