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
                                        <a href="#" class="btn btn-sm btn-success" data-toggle="modal"
                                            data-target="#modal-tambah" data-backdrop="static" data-keyboard="false"><i
                                                class="fas fa-plus"></i> Add</a>
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
                                                    <a href="{{ route('lead.work', ['id' => $i->id, 'status' => true]) }}">
                                                        {{ $i->first_name }} {{ $i->last_name }}
                                                    </a>
                                                </td>
                                                <td><i class="fa fa-phone"></i><a href="tel:{{ $i->phone_number }}">
                                                        {{ $i->phone_number }}</a> </td>
                                                <td><span
                                                        class="badge {{ Setting::getColorClass($i->follow_status) }}">{{ Setting::getCallStatus($i->follow_status) }}</span>
                                                </td>
                                                <td><span
                                                        class="btn btn-sm  {{ Setting::getProjectColorClass($i->project_id) }}">{{ $i->project_name }}</span>
                                                </td>
                                                <td>
                                                    @foreach ($i->users as $u)
                                                        <button
                                                            class="btn btn-sm btn-primary ">{{ $u->name }}</button>
                                                    @endforeach
                                                </td>
                                                <td>{{ $i->business }}</td>
                                                <td>{{ Setting::getformatedDate($i->follow_up) }}</td>
                                                @canany(['update lead', 'delete lead'])
                                                    <td>
                                                        <div class="btn-group">
                                                            @can('update lead')
                                                                <button class="btn btn-sm btn-primary btn-edit"
                                                                    data-id="{{ $i->id }}"><i
                                                                        class="fas fa-pencil-alt"></i></button>
                                                            @endcan
                                                            @can('delete lead')
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
            const createForm = $('#lead-create-form');
            const editForm = $('#lead-edit-form');
            const deleteForm = $('#lead-delete-form');
            const oldInput = @json(old());
            const hasErrors = @json($errors->any());
            const modalAdd = $('#modal-tambah');
            const modalEdit = $('#modal-edit');
            let activeEditButton = null;

            $('.lead-form .invalid-feedback').addClass('server-error');

            const initSelect2 = () => {
                if (typeof $.fn.select2 !== 'function') {
                    return;
                }
                modalAdd.find('.select2').select2({
                    dropdownParent: modalAdd,
                    width: '100%'
                });
                modalEdit.find('.select2').select2({
                    dropdownParent: modalEdit,
                    width: '100%'
                });
            };
            initSelect2();

            const clearLeadErrors = ($form) => {
                if (!$form.length) {
                    return;
                }
                $form.find('.is-invalid').removeClass('is-invalid');
                $form.find('.client-error').remove();
                $form.find('.server-error').addClass('d-none');
            };

            const setButtonLoading = ($button, text) => {
                if (!$button || !$button.length) {
                    return;
                }
                if ($button.data('original-html') === undefined) {
                    $button.data('original-html', $button.html());
                }
                $button.prop('disabled', true).html(
                    `<span class="spinner-border spinner-border-sm mr-1" role="status" aria-hidden="true"></span>${text}`
                );
            };

            const resetButtonLoading = ($button) => {
                if (!$button || !$button.length) {
                    return;
                }
                const originalHtml = $button.data('original-html');
                if (originalHtml !== undefined) {
                    $button.html(originalHtml);
                }
                $button.prop('disabled', false);
            };

            const applyOldValues = ($form, oldValues) => {
                if (!$form.length || !oldValues) {
                    return;
                }

                const setVal = (name) => {
                    if (oldValues[name] !== undefined) {
                        $form.find(`[name="${name}"]`).val(oldValues[name]).trigger('change');
                    }
                };

                if (oldValues['assign_id']) {
                    $form.find('[name="assign_id[]"]').val(oldValues['assign_id']).trigger('change.select2').trigger('change');
                }

                setVal('relate');
                setVal('follow_id');
                setVal('gender');
                setVal('type');
                setVal('zone_id');
                setVal('area_id');
                setVal('is_active');

                if (oldValues['id']) {
                    $form.find('[name="id"]').val(oldValues['id']);
                }
            };

            if (hasErrors) {
                const isEditContext = oldInput.form_context === 'edit' || !!oldInput.id;
                const $targetForm = isEditContext ? editForm : createForm;
                applyOldValues($targetForm, oldInput);
                setTimeout(() => {
                    $(isEditContext ? '#modal-edit' : '#modal-tambah').modal({
                        backdrop: 'static',
                        keyboard: false,
                        show: true
                    });
                }, 150);
            }

            $('#modal-tambah').on('hidden.bs.modal', function() {
                clearLeadErrors(createForm);
            });

            $('#modal-edit').on('hidden.bs.modal', function() {
                clearLeadErrors(editForm);
            });

            createForm.on('submit', function(e) {
                const $submitButton = createForm.find('button[type="submit"]').last();
                if ($submitButton.prop('disabled')) {
                    e.preventDefault();
                    return false;
                }
                setButtonLoading($submitButton, 'Saving...');
            });

            editForm.on('submit', function(e) {
                const $submitButton = editForm.find('button[type="submit"]').last();
                if ($submitButton.prop('disabled')) {
                    e.preventDefault();
                    return false;
                }
                setButtonLoading($submitButton, 'Updating...');
            });

            deleteForm.on('submit', function(e) {
                const $submitButton = deleteForm.find('button[type="submit"]').last();
                if ($submitButton.prop('disabled')) {
                    e.preventDefault();
                    return false;
                }
                setButtonLoading($submitButton, 'Deleting...');
            });

            $("#filter").change(function() {
                var filter = $("#filter").val();
                var origin = window.location.origin + "/admin/crm/lead?filter=" + filter
                window.location.replace(origin);

            });

            $(document).on("click", '.btn-edit', function() {
                let id = $(this).attr("data-id");
                activeEditButton = $(this);
                setButtonLoading(activeEditButton, 'Loading...');
                clearLeadErrors(editForm);
                if (editForm.length) {
                    editForm[0].reset();
                    editForm.find('select.select2').val(null).trigger('change');
                }
                $('#modal-loading').modal({
                    backdrop: 'static',
                    keyboard: false,
                    show: true
                });
                $.ajax({
                    url: "{{ route('crm.lead.show') }}",
                    type: "POST",
                    dataType: "JSON",
                    data: {
                        id: id,
                        _token: "{{ csrf_token() }}"
                    },
                    success: function(data) {
                        var payload = data.data || {};
                        const assignField = editForm.find('[name="assign_id[]"]');
                        const assignedUsers = data.assigned_user_ids || ((payload.users || []).map(u => String(u.id)));
                        if (assignField.find('option').length) {
                            assignField.val(assignedUsers).trigger('change.select2').trigger('change');
                        }
                        editForm.find('[name="first_name"]').val(payload.first_name || '');
                        editForm.find('[name="last_name"]').val(payload.last_name || '');
                        editForm.find('[name="gender"]').val(payload.gender != null ? String(payload.gender) : '').trigger('change');
                        editForm.find('[name="type"]').val(payload.type != null ? String(payload.type) : '').trigger('change');
                        editForm.find('[name="relate"]').val(payload.relate || '').trigger('change');
                        editForm.find('[name="father_name"]').val(payload.father_name || '');
                        editForm.find('[name="nic_number"]').val(payload.nic_number || '');
                        editForm.find('[name="phone_number"]').val(payload.phone_number || '');
                        editForm.find('[name="mobile_number"]').val(payload.mobile_number || '');
                        editForm.find('[name="zone_id"]').val(payload.zone_id != null ? String(payload.zone_id) : '').trigger('change');
                        editForm.find('[name="area_id"]').val(payload.area_id != null ? String(payload.area_id) : '').trigger('change');
                        editForm.find('[name="business"]').val(payload.business || '');
                        editForm.find('[name="designation"]').val(payload.designation || '');
                        editForm.find('[name="home_address"]').val(payload.home_address || '');
                        editForm.find('[name="office_address"]').val(payload.office_address || '');
                        editForm.find('[name="follow_id"]').val(payload.follow_id != null ? String(payload.follow_id) : '').trigger('change');
                        editForm.find('[name="is_active"]').val(payload.is_active != null ? String(payload.is_active) : '').trigger('change');
                        editForm.find('[name="id"]').val(payload.id || '');

                        $('#modal-loading').modal('hide');
                        $('#modal-edit').modal({
                            backdrop: 'static',
                            keyboard: false,
                            show: true
                        });
                    },
                    error: function() {
                        $('#modal-loading').modal('hide');
                    },
                    complete: function() {
                        resetButtonLoading(activeEditButton);
                        activeEditButton = null;
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
        });
    </script>
@endsection

@section('modal')
    {{-- Modal Add --}}
    <div class="modal fade" id="modal-tambah">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="lead-create-form" class="lead-form" action="{{ route('crm.lead.store') }}"
                    method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="form_context" value="create">
                    <div class="modal-header">
                        <h4 class="modal-title">Add Customer Lead</h4>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        @if ($power == 'superadmin')
                            <div class="row">


                                <div class="col-sm-6">

                                    <div class="form-group">
                                        <label>Assign To</label>
                                        <div class="select2-purple">
                                            <select
                                                class="select2 select2-hidden-accessible form-control @error('assign_id') is-invalid @enderror"
                                                name="assign_id[]" multiple="" data-placeholder="Select a State"
                                                data-dropdown-css-class="select2-purple" style="width: 100%;"
                                                data-select2-id="16" tabindex="-1" aria-hidden="true">
                                                @foreach ($users as $u)
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
                                        <label>Monitoring By</label>
                                        <div class="input-group">
                                            <select class="form-control" name="follow_id">
                                                @foreach ($users as $u)
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
                            <input type="text" id="create_assign_id"
                                class="form-control @error('assign_id') is-invalid @enderror" name="assign_id[]"
                                value="{{ Auth::user()->id }}" hidden="true">
                            <input type="text" id="create_follow_id"
                                class="form-control @error('follow_id') is-invalid @enderror" name="follow_id"
                                value="1" hidden="true">
                        @endif

                        <div class="row">
                            <div class="col-sm-3">

                                <div class="input-group">
                                    <label class="fbox">First Name</label>
                                    <div class="input-group">
                                        <input type="text"
                                            class="form-control @error('first_name') is-invalid @enderror"
                                            placeholder="First Name" name="first_name" value="{{ old('first_name') }}">
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
                                        <input type="text"
                                            class="form-control @error('last_name') is-invalid @enderror"
                                            placeholder="Last Name" name="last_name" value="{{ old('last_name') }}">
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
                                        <input type="text"
                                            class="form-control @error('father_name') is-invalid @enderror"
                                            placeholder="Last Name" name="father_name" value="{{ old('father_name') }}">
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
                                            <option>Select One</option>
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
                                        <input type="text"
                                            class="form-control @error('nic_number') is-invalid @enderror"
                                            placeholder="NIC" name="nic_number" value="{{ old('nic_number') }}">
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
                                        <input type="text"
                                            class="form-control @error('phone_number') is-invalid @enderror"
                                            placeholder="1st Phone Number" name="phone_number"
                                            value="{{ old('phone_number') }}">
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
                                        <input type="text"
                                            class="form-control @error('mobile_number') is-invalid @enderror"
                                            placeholder="2nd Phone Number" name="mobile_number"
                                            value="{{ old('mobile_number') }}">
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
                                            @foreach (Setting::get_active_zone() as $v)
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
                                            @foreach (Setting::get_active_area() as $v)
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
                                        <input type="text"
                                            class="form-control @error('business') is-invalid @enderror"
                                            placeholder="Main Business" name="business" value="{{ old('business') }}">
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
                                        <input type="text"
                                            class="form-control @error('designation') is-invalid @enderror"
                                            name="designation" value="{{ old('designation') }}">
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
                                            <textarea class="form-control @error('home_address') is-invalid @enderror" rows="3" placeholder="Enter ..."
                                                spellcheck="false" name="home_address">{{ old('home_address') }}</textarea>
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
                                            <textarea class="form-control @error('office_address') is-invalid @enderror" rows="3" placeholder="Enter ..."
                                                spellcheck="false" name="office_address">{{ old('office_address') }}</textarea>
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
                        </div>



                    </div>
                    <div class="modal-footer justify-content-between">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Create Lead</button>
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
                <form id="lead-edit-form" class="lead-form" action="{{ route('crm.lead.update') }}"
                    method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="form_context" value="edit">
                    <input type="hidden" name="id" id="edit_lead_id">
                    <div class="modal-header">
                        <h4 class="modal-title">Edit Customer Lead</h4>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        @if ($power == 'superadmin')
                            <div class="row">


                                <div class="col-sm-6">

                                    <div class="form-group">
                                        <label>Assign To</label>
                                        <div class="select2-purple">
                                            <select id="edit_assign_id"
                                                class="select2 select2-hidden-accessible form-control @error('assign_id') is-invalid @enderror"
                                                name="assign_id[]" multiple="" data-placeholder="Select a State"
                                                data-dropdown-css-class="select2-purple" style="width: 100%;"
                                                data-select2-id="15" tabindex="-1" aria-hidden="true">
                                                @foreach ($users as $u)
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
                                        <label>Monitoring By</label>
                                        <div class="input-group">
                                            <select class="form-control" name="follow_id" id="edit_follow_id">
                                                @foreach ($users as $u)
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
                            <input type="text" id="edit_assign_id_hidden"
                                class="form-control @error('assign_id') is-invalid @enderror" name="assign_id[]"
                                value="{{ Auth::user()->id }}" hidden="true">
                            <input type="text" id="edit_follow_id_hidden"
                                class="form-control @error('follow_id') is-invalid @enderror" name="follow_id"
                                value="1" hidden="true">
                        @endif

                        <div class="row">
                            <div class="col-sm-3">

                                <div class="input-group">
                                    <label class="fbox">First Name</label>
                                    <div class="input-group">
                                        <input id="first_name" type="text"
                                            class="form-control @error('first_name') is-invalid @enderror"
                                            placeholder="First Name" name="first_name" value="{{ old('first_name') }}">
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
                                        <input id="last_name" type="text"
                                            class="form-control @error('last_name') is-invalid @enderror"
                                            placeholder="Last Name" name="last_name" value="{{ old('last_name') }}">
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
                                        <input id="father_name" type="text"
                                            class="form-control @error('father_name') is-invalid @enderror"
                                            placeholder="Last Name" name="father_name" value="{{ old('father_name') }}">
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
                                            <option>Select One</option>
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
                                        <input id="nic_number" type="text"
                                            class="form-control @error('nic_number') is-invalid @enderror"
                                            placeholder="NIC" name="nic_number" value="{{ old('nic_number') }}">
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
                                        <input id="phone_number" type="text"
                                            class="form-control @error('phone_number') is-invalid @enderror"
                                            placeholder="1st Phone Number" name="phone_number"
                                            value="{{ old('phone_number') }}">
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
                                        <input id="mobile_number" type="text"
                                            class="form-control @error('mobile_number') is-invalid @enderror"
                                            placeholder="2nd Phone Number" name="mobile_number"
                                            value="{{ old('mobile_number') }}">
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
                                            @foreach (Setting::get_active_zone() as $v)
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
                                            @foreach (Setting::get_active_area() as $v)
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
                                        <input id="business" type="text"
                                            class="form-control @error('business') is-invalid @enderror"
                                            placeholder="Main Business" name="business" value="{{ old('business') }}">
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
                                        <input id="designation" type="text"
                                            class="form-control @error('designation') is-invalid @enderror"
                                            name="designation" value="{{ old('designation') }}">
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
                                            <textarea id="home_address" class="form-control @error('home_address') is-invalid @enderror" rows="3"
                                                placeholder="Enter ..." spellcheck="false" name="home_address">{{ old('home_address') }}</textarea>
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
                                            <textarea id="office_address" class="form-control @error('office_address') is-invalid @enderror" rows="3"
                                                placeholder="Enter ..." spellcheck="false" name="office_address">{{ old('office_address') }}</textarea>
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
                        </div>


                    </div>
                    <div class="modal-footer justify-content-between">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Update Lead</button>
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
                <form id="lead-delete-form" action="{{ route('crm.lead.destroy') }}" method="POST"
                    enctype="multipart/form-data">
                    @csrf
                    @method('DELETE')
                    <div class="modal-header">
                        <h4 class="modal-title">Delete Lead</h4>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p class="modal-text">Are you sure you want to delete this lead? <b id="delete-data"></b></p>
                        <input type="hidden" name="id" id="did">
                    </div>
                    <div class="modal-footer justify-content-between">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger">Delete Lead</button>
                    </div>
                </form>
            </div>
            <!-- /.modal-content -->
        </div>
        <!-- /.modal-dialog -->
    </div>
@endsection
