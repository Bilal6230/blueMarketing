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
                                    <a href="#" class="btn btn-sm btn-success" data-toggle="modal" data-target="#modal-tambah" data-backdrop="static" data-keyboard="false"><i class="fas fa-plus"></i> Add</a>
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
                                            @canany(['update plot', 'delete plot'])
                                                    <td>
                                                        @if ($i->sold == 0)
                                                            <div class="btn-group">
                                                                @can('update plot')
                                                                    <button class="btn btn-sm btn-primary btn-edit" data-id="{{ $i->id }}"><i class="fas fa-pencil-alt"></i></button>
                                                                @endcan
                                                                @can('delete plot')
                                                                    <button class="btn btn-sm btn-danger btn-delete" data-id="{{ $i->id }}" data-name="{{ $i->name }}"><i class="fas fa-trash"></i></button>
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
            $(document).on("click", '.btn-edit', function() {
                let id = $(this).attr("data-id");
                $('#modal-loading').modal({backdrop: 'static', keyboard: false, show: true});
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
                                            @foreach (Setting::get_active_project( getSelectedTown() ) as $v )
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
                                            <option value="1">Residenational</option>
                                            <option value="2">Commercial</option>
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
                                        <input type="text" class="form-control @error('name') is-invalid @enderror" placeholder="Unit Name" name="name" value="{{ old('name') }}" >
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
                                        <input type="text" class="form-control @error('size') is-invalid @enderror" placeholder="Unit Size" name="size" value="{{ old('size') }}">
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
                                            <option value="1">Square Feet</option>
                                            <option value="2">Marla</option>
                                            <option value="3">Kanal</option>

                                        </select>
                                        @error('unit')
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
                                            @foreach (Setting::get_active_roads() as $v )
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
                                            @foreach (Setting::get_active_facing() as $v )
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
    <div class="modal fade" id="modal-edit">
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
                        @method("PUT")
                        <div class="row">
                            <div class="col-sm-6">

                                <div class="input-group">
                                    <label class="fbox">Project</label>
                                    <div class="input-group">
                                        <select class="form-control" name="project_id" id="project_id" >
                                            @foreach (Setting::get_active_project() as $v )
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
                                            <option value="1">Residenational</option>
                                            <option value="2">Commercial</option>
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
                                        <input readonly id="name" type="text" class="form-control @error('name') is-invalid @enderror" placeholder="Unit Name" name="name" value="{{ old('name') }}">
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
                                        <input id="size" type="text" class="form-control @error('size') is-invalid @enderror" placeholder="Unit Size" name="size" value="{{ old('size') }}">
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
                                            <option value="1">Square Feet</option>
                                            <option value="2">Marla</option>
                                            <option value="3">Kanal</option>

                                        </select>
                                        @error('unit')
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
                                            @foreach (Setting::get_active_roads() as $v )
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
                                            @foreach (Setting::get_active_facing() as $v )
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
