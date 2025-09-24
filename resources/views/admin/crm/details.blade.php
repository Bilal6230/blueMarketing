@extends('admin.layouts.master')
@section('content')

    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <section class="content-header">
            <div class="container-fluid">

            </div><!-- /.container-fluid -->
        </section>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-12">

                        <div class="card card-widget widget-user">
                    <div class="widget-user-header text-white"  style="background: url('https://adminlte.io/themes/v3/dist/img/photo1.png') center center;">
                                <h3 class="widget-user-username text-right">{{ $data->users[0]->name }}</h3>
                                <h5 class="widget-user-desc text-right">{{ $data->users[0]->department }}</h5>
                            </div>
                            <div class="widget-user-image">
                        <img class="img-circle elevation-2" src="/storage/{{ $data->users[0]->avatar }}" alt="{{ $data->users[0]->name }}">
                            </div>
                            <div class="card-footer">
                                <div class="row">
                                    <div class="col-sm-3 border-right">
                                        <div class="description-block">
                                            <h5 class="description-header">Full Name</h5>
                                    <span class="description-text">{{ $data->first_name }}  {{ $data->last_name }}</span>
                                        </div>
                                    </div>

                                    <div class="col-sm-3 border-right">
                                        <div class="description-block">
                                            <h5 class="description-header">Phone Number</h5>
                                            <span class="description-text">{{ $data->phone_number }}</span>
                                        </div>

                                    </div>

                                    <div class="col-sm-3 border-right">
                                        <div class="description-block">
                                            <h5 class="description-header">Project</h5>
                                            <span class="description-text"> ..</span>
                                        </div>

                                    </div>

                                    <div class="col-sm-3">
                                        <div class="description-block">
                                            <h5 class="description-header">Register Date</h5>
                                            <span
                                                class="description-text">{{ Setting::getformatedDate($register_date) }}</span>
                                        </div>

                                    </div>


                                </div>

                                @if ($user->id == $data->users[0]->id)
                                    <div class="card-header">
                                        @can('create calllog')
                                            <div class="row">
                                                <div class="col-sm-6">
                                                    <a class="btn btn-block bg-success">
                                                        <i class="fas fa-comments"></i> Send Message
                                                    </a>
                                                </div>

                                                <div class="col-sm-6">
                                                    <a class="btn btn-block bg-info" data-toggle="modal"
                                                        data-target="#modal-tambah" data-backdrop="static"
                                                        data-keyboard="false">
                                                        <i class="fas fa-plus"></i> Add Call Log
                                                    </a>
                                                </div>




                                            </div>
                                        @endcan
                                        @can('send sms')
                                        @endcan
                                    </div>
                                @endif

                            </div>
                        </div>

                    </div>



                    <!-- /.col -->
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header p-2">
                                <ul class="nav nav-pills">
                                    <li class="nav-item"><a class="nav-link active" href="#timeline" data-toggle="tab">Time
                                            Line</a></li>
                                    <li class="nav-item"><a class="nav-link" href="#settings" data-toggle="tab">Proposal</a>
                                    </li>
                                </ul>
                            </div><!-- /.card-header -->
                            <div class="card-body">
                                <div class="tab-content">

                                    <!-- /.tab-pane -->
                                    <div class="tab-pane active" id="timeline">
                                        <!-- The timeline -->
                                        <div class="timeline timeline-inverse">

                                            <!-- timeline item -->

                                            @foreach ($data->comments as $c)
                                                <div>
                                                    <i class="fas {{ Setting::getLogtype($c->type) }}"></i>

                                                    <div class="timeline-item">
                                                        <span class="time">
                                                            <i class="far fa-clock"></i> {{ $c->user->name }}
                                                        </span>

                                                        <h3 class="timeline-header"
                                                            style="{{ isset($c->call_status) && $c->call_status !== null ? Setting::getColorCard($c->call_status) : '' }}">
                                                            <span
                                                                class="badge {{ isset($c->call_status) && $c->call_status !== null ? Setting::getColorClass($c->call_status) : '' }}">
                                                                {{ isset($c->call_status) && $c->call_status !== null ? Setting::getCallStatus($c->call_status) : 'N/A' }}
                                                            </span>
                                                            <span
                                                                class="badge">{{ Setting::getformatedDate($c->created_at) }}</span>

                                                            @if (isset($c->call_status) && in_array($c->call_status, [2, 3, 7]) && !empty($c->follow_up))
                                                                <span
                                                                    class="badge {{ isset($c->call_status) && $c->call_status !== null ? Setting::getColorClass($c->call_status) : '' }} follow_time">
                                                                    {{ $c->follow_up }}
                                                                </span>
                                                            @endif
                                                        </h3>

                                                        <div class="timeline-body">
                                                            <div class="row">
                                                                <div class="col-sm-2 col-2">
                                                                    <img class="avatar"
                                                                        src="/storage/{{ $c->user->avatar }}"
                                                                        alt="{{ $c->user->name }}"
                                                                        style="height:90px; width:90px; border:3px solid;">
                                                                </div>
                                                                <div class="col-sm-10 col-10">
                                                                    <div class="description-block ">
                                                                        {{-- Safely handle comment data --}}
                                                                        {{ is_array($c->comment) ? implode(', ', $c->comment) : $c->comment }}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="card-footer">
                                                            <div class="row">
                                                                <div class="col-sm-3 col-6">
                                                                    {{-- {{ Setting::getCallStatus($c->call_status) }} --}}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach




                                            <div>
                                                <i class="far fa-clock bg-gray"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- /.tab-pane -->

                                    <div class="tab-pane" id="settings">
                                        Comming Soon
                                    </div>
                                    <!-- /.tab-pane -->
                                </div>
                                <!-- /.tab-content -->
                            </div><!-- /.card-body -->
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
                        console.log(data);
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
                        $("#follow_id").val(data.follow_id);

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
    <div class="modal fade" id="modal-tambah">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Record Call Log</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('lead.work.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf



                        <div class="row">
                            <div class="col-sm-3">
                                <input type="text" name="lead_id" value="{{ $data->id }}" hidden="true">


                                <div class="input-group">
                                    <label class="fbox">Type</label>
                                    <div class="input-group">
                                        <select class="form-control" name="type">
                                            <option>Select</option>
                                            <option value="1">Call</option>
                                            <option value="2">Visit</option>

                                        </select>
                                        @error('type')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                            </div>

                            <div class="col-sm-3">


                                <div class="input-group">
                                    <label class="fbox">Call Status</label>
                                    <div class="input-group">
                                        <select class="form-control" name="call_status">
                                            <option>Select</option>
                                            <option value="1">Invalide Number</option>
                                            <option value="2">Interested</option>
                                            <option value="3">Schedule Later</option>
                                            <option value="4">Not Interested</option>
                                            <option value="7" class="bg-danger">Town Visit</option>
                                            <option value="5" class="bg-danger">Sale Done</option>

                                        </select>
                                        @error('call_status')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                            </div>

                            <div class="col-sm-3">

                                <div class="input-group">
                                    <label class="fbox">Schedule Time</label>
                                    <div class="input-group">
                                        <input type="datetime-local" id="follow_up" name="follow_up"
                                            class="form-control" value="{{ old('follow_up') }}">
                                        @error('follow_up')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-2">

                                <div class="input-group">
                                    <label class="fbox">Call Duration</label>
                                    <div class="input-group">
                                        <input type="text" autocomplete="false"
                                            class="form-control @error('call_duration') is-invalid @enderror"
                                            name="call_duration" value="{{ old('call_duration') }}">

                                        @error('call_duration')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-4" hidden>

                                <div class="input-group">
                                    <label class="fbox">Recording File</label>
                                    <div class="input-group">
                                        <input type="file"
                                            class="form-control @error('recording_path') is-invalid @enderror"
                                            placeholder="recording_path" name="recording_path"
                                            value="{{ old('recording_path') }}">

                                        @error('recording_path')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>








                        </div>


                        <div class="row">


                            <div class="col-sm-12">

                                <div class="input-group">
                                    <label class="fbox">Details</label>
                                    <div class="input-group ">
                                        <div class="form-group col-sm-12">
                                            <textarea class="form-control @error('comment') is-invalid @enderror" rows="3" placeholder="Enter ..."
                                                spellcheck="false" name="comment">{{ old('comment') }}</textarea>
                                            @error('comment')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

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
                    <h4 class="modal-title">Edit User</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('crm.lead.update') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <div class="input-group">
                            <label>First Name</label>
                            <div class="input-group">
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                    placeholder="Name" name="name" id="name" value="{{ old('name') }}">
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="input-group">
                            <label>Email</label>
                            <div class="input-group">
                                <input type="email" class="form-control @error('email') is-invalid @enderror"
                                    placeholder="Email" name="email" id="email" value="{{ old('email') }}">
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

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
                        <div class="input-group">
                            <label>Nic Number</label>
                            <div class="input-group">
                                <input type="number" class="form-control @error('nic_number') is-invalid @enderror"
                                    placeholder="NIC " name="nic_number" id="nic_number"
                                    value="{{ old('nic_number') }}">
                                @error('nic_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="input-group">
                            <label>Phone Number</label>
                            <div class="input-group">
                                <input type="number" class="form-control @error('phone_number') is-invalid @enderror"
                                    placeholder="Phone Number" name="phone_number" id="phone_number"
                                    value="{{ old('phone_number') }}">
                                @error('phone_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="input-group">
                            <label>Department</label>
                            <div class="input-group">
                                <input type="text" class="form-control @error('department') is-invalid @enderror"
                                    placeholder="Department" name="department" id="department"
                                    value="{{ old('department') }}">
                                @error('department')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="input-group">
                            <label>Address</label>
                            <div class="input-group">
                                <input type="text" class="form-control @error('address') is-invalid @enderror"
                                    placeholder="Address" name="address" id="address" value="{{ old('address') }}">
                                @error('address')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
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
