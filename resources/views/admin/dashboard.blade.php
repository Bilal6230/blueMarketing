@extends('admin.layouts.master')
@section('content')
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Dashboard</h1>
                    </div><!-- /.col -->
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active">{{ $title }}</li>
                        </ol>
                    </div><!-- /.col -->
                </div><!-- /.row -->
            </div><!-- /.container-fluid -->
        </div>
        <section class="content">
            <div class="container-fluid">
                <!-- Small boxes (Stat box) -->
                <div class="row">
                    <div class="col-lg-3 col-6">
                        <!-- small box -->
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3>{{ $today_leads }}</h3>
                                <p>New Leads Add Today</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <a href="{{ route('dashboard.leads_by_users_report') }}" class="small-box-footer">More info <i
                                    class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <!-- ./col -->
                    <div class="col-lg-3 col-6">
                        <!-- small box -->
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3>{{ Setting::getTodayLeadWorkCount() }}</h3>
                                <p>Today Follow UP</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-comments"></i>
                            </div>
                            <a href="{{ route('crm.todayLeadWorkReport') }}" class="small-box-footer">More info <i
                                    class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <!-- ./col -->
                    <div class="col-lg-3 col-6">
                        <!-- small box -->
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h3>{{ $get_all_lead }}</h3>

                                <p>Pending Works</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <a href="{{ route('report.lead.index') }}" class="small-box-footer"
                                style="color: #fff !important;">More info <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <!-- small box -->
                        <div class="small-box bg-primary">
                            <div class="inner">
                                <h3>{{ $total_blance }}</h3>
                                <p>Total Balance</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-wallet"></i>
                            </div>
                            <a href="{{ route('finance.reports.show_ledger') }}" class="small-box-footer">More info <i
                                    class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <!-- ./col -->
                </div>
                @canany(['lead search', 'lead details', 'read attendance'])
                    <div class="row">
                        @can('lead search')
                            <div class="col-md-6 mb-4">
                                <div class="custom_card h-100">
                                    <div class="card-body">
                                        <div class="mb-3 d-flex align-items-center justify-content-between">
                                            <h5 class="text-lg font-semibold">Search Lead By Number</h5>
                                        </div>
                                        <div class="form-group">
                                            <div class="input-group input-group-sm">
                                                @csrf
                                                <input type="text" class="form-control" name="number" id="number" maxlength="11"
                                                    size="11">
                                                <span class="input-group-append">
                                                    <button type="submit" class="btn btn-info btn-flat" id="search_number"><i
                                                            class="fas fa-search mr-1"></i> Search</button>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="">
                                            <div id="msg" class="message  ">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endcan

                        @can('read attendance')
                            <div class="col-md-6 mb-4">
                                <div class="custom_card h-100">
                                    <div class="card-body">
                                        <form action="/admin/punch" method="POST" enctype="multipart/form-data">
                                            <div class="mb-3 d-flex align-items-center justify-content-between">
                                                <h5 class="text-lg font-semibold">Employee Attendance</h5>
                                                <button class="btn btn-sm custom_btn_outline primary" id="punch_button">
                                                    <i class="fas fa-plus mr-1"></i> Punch In
                                                </button>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    @csrf
                                                    <select class="form-control" name="user_id">
                                                        @foreach ($users_list as $u)
                                                            <option value="{{ $u->id }}"> {{ $u->name }}
                                                            </option>
                                                        @endforeach

                                                    </select>
                                                </div>

                                                @if ($display_date)
                                                    <div class="col-md-6">
                                                        <input type="datetime-local" class="form-control" name="punch_time"
                                                            id="date_time">
                                                    </div>
                                                @endif
                                            </div>
                                        </form>
                                        <div class="form-group row">
                                            <div id="msg" class="message  ">
                                            </div>
                                            <div id="soft-alert" class="alert" style="display:none;"></div>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endcan


                    </div>
                @endcanany
                {{-- Financial Summary --}}
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="custom_card h-100">
                            <div class="card-body">
                                <div class="row justify-content-center">
                                    <div class="col-12 text-center mb-4">
                                        <h4 class="text-lg">Financial Summary</h4>
                                    </div>

                                    <!-- Hand Cash Total -->
                                    <div class="col-md-4 mb-4 mb-md-0">
                                        <div class="financial-circle bg_success">
                                            <div class="circle-content">
                                                <h6>Hand Cash</h6>
                                                {{-- @dd($total_blance) --}}
                                                <!-- Hand Cash -->
                                                <h4 class="mt-2" id="handCash">
                                                    {{ number_format($total_blance, 2) }}
                                                </h4>
                                            </div>
                                        </div>
                                        <p class="text-muted mt-2 text-center">Hand Cash Balance</p>
                                    </div>
                                    <!-- Bank Account Total -->
                                    <div class="col-md-4">
                                        <div class="financial-circle {{ (-$total_bank_account_data) >= 0 ? 'bg_success' : 'bg_danger' }}">
                                            <div class="circle-content">
                                                <h6>Bank Account</h6>
                                                <h4 class="mt-2">{{ number_format(-$total_bank_account_data, 2) }}
                                                </h4>
                                            </div>
                                        </div>
                                        <p class="text-muted mt-2 text-center">Current Bank Balance</p>
                                    </div>
                                    <!-- Hand Cash Total -->
                                    <div class="col-md-4 mb-4 mb-md-0">
                                        <div class="financial-circle bg_success">
                                            <div class="circle-content">
                                                <h6>Total Cash</h6>
                                                <h4 class="mt-2" id="totalCash">{{ number_format($total_blance - $total_bank_account_data, 2) }}</h4>
                                            </div>
                                        </div>
                                        <p class="text-muted mt-2 text-center">Available Cash Balance</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="custom_card h-100">
                            <div class="card-body">
                                <div class="mb-3 d-flex align-items-center justify-content-between">
                                    <h5 class="text-lg font-semibold">Dasticash Summary</h5>
                                    <button class="btn btn-sm custom_btn_outline primary" data-toggle="modal"
                                        data-target="#addDasticashModal">
                                        <i class="fas fa-plus mr-1"></i> Add Dasticash
                                    </button>
                                </div>
                                <!-- DataTable -->
                                <div class="table-responsive">
                                    <table class="table table-hover" id="dasticashTable">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Amount</th>
                                                <th>Description</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($dasticashData as $key => $item)
                                                <tr id="row-{{ $item->id }}">
                                                    <td>{{ $key + 1 }}</td>
                                                    <td class="name-cell">{{ $item->name }}</td>
                                                    <td class="amount-cell">{{ $item->amount }}</td>
                                                    <td class="desc-cell">{{ $item->description }}</td>
                                                    <td>
                                                        <a href="javascript:void(0)" class="btn btn-sm btn-info edit-btn"
                                                            data-id="{{ $item->id }}" data-name="{{ $item->name }}"
                                                            data-amount="{{ $item->amount }}"
                                                            data-description="{{ $item->description }}">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button class="btn btn-sm btn-danger delete-btn"
                                                            data-id="{{ $item->id }}"
                                                            data-url="{{ route('dasticash.destroy', $item->id) }}"><i
                                                                class="fas fa-trash"></i></button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
                <!-- /.row (main row) -->

                {{-- <div class="row">
                    <div class="col-md-6">
                        <div class="card card-danger">
                            <div class="card-header">
                                <h3 class="card-title">Donut Chart</h3>

                                <div class="card-tools">
                                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <button type="button" class="btn btn-tool" data-card-widget="remove">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <canvas id="donutChart"
                                    style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
                            </div>
                            <!-- /.card-body -->
                        </div>
                        <!-- /.card -->
                    </div> --}}


                    @canany(['read attendance'])
                        <div class="col-md-12 mb-4">
                            <div class="custom_card h-100">
                                <div class="card-body">
                                    <div class="mb-3 d-flex align-items-center justify-content-between">
                                        <h5 class="text-lg font-semibold">Punch-in Records for Today</h5>

                                    </div>
                                    <div class="table-resposive">

                                        <table class="table table-hover ">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>User Name</th>
                                                    <th>Punch In</th>
                                                    <th>Punch Out</th>
                                                    <th>Total Hours</th>

                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($today_punches as $punch)
                                                    <tr>
                                                        <td>{{ date('Y-m-d', strtotime($punch->punch_in)) }}</td>
                                                        <td>{{ $punch->user->name }}</td>
                                                        <td>{{ date('H:i:s', strtotime($punch->punch_in)) }}</td>
                                                        <td>{{ $punch->punch_out ? date('H:i:s', strtotime($punch->punch_out)) : 'N/A' }}
                                                        </td>

                                                        <td>
                                                            @if ($punch->punch_out)
                                                                                                    <?php
                                                                $punchInTime = strtotime($punch->punch_in);
                                                                $punchOutTime = strtotime($punch->punch_out);
                                                                $totalSeconds = $punchOutTime - $punchInTime;
                                                                $hours = floor($totalSeconds / 3600);
                                                                $minutes = floor(($totalSeconds % 3600) / 60);
                                                                $seconds = $totalSeconds % 60;
                                                                                                                                                                    ?>
                                                                                                    {{ $hours }}h {{ $minutes }}m
                                                                                                    {{ $seconds }}s
                                                            @else
                                                                N/A
                                                            @endif
                                                        </td>

                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>

                                    </div>
                                </div>
                            </div>
                        </div>
                    @endcanany

                    <!-- Add Dasticash Modal -->
                    <div class="modal fade" id="addDasticashModal" tabindex="-1" role="dialog"
                        aria-labelledby="addDasticashModalLabel" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                            <form action="{{ route('dasticash.store') }}" method="POST">
                                @csrf
                                <div class="modal-content">
                                    <div class="modal-header bg-success text-white">
                                        <h5 class="modal-title" id="addDasticashModalLabel">Add Dasticash</h5>
                                        <button type="button" class="close text-white" data-dismiss="modal"
                                            aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label>Name</label>
                                            <input type="text" name="name" class="form-control" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Amount</label>
                                            <input type="text" name="amount" class="form-control">
                                        </div>
                                        <div class="form-group">
                                            <label>Description</label>
                                            <textarea name="description" class="form-control" rows="3"></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-success">Save</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <!-- Edit Dasticash Modal -->
                    <div class="modal fade" id="editDasticashModal" tabindex="-1" role="dialog"
                        aria-labelledby="editDasticashModalLabel" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                            <form id="editDasticashForm" method="POST">
                                @csrf
                                @method('PUT')
                                <div class="modal-content">
                                    <div class="modal-header bg-info text-white">
                                        <h5 class="modal-title" id="editDasticashModalLabel">Edit Dasticash</h5>
                                        <button type="button" class="close text-white" data-dismiss="modal"
                                            aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" id="edit-id">
                                        <div class="form-group">
                                            <label>Name</label>
                                            <input type="text" name="name" id="edit-name" class="form-control" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Amount</label>
                                            <input type="text" name="amount" id="edit-amount" class="form-control">
                                        </div>
                                        <div class="form-group">
                                            <label>Description</label>
                                            <textarea name="description" id="edit-description" class="form-control"
                                                rows="3"></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-info">Update</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>
        </section>
    </div>
@endsection
@section('js')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function () {
            let destroyUrl = "{{ url('admin/dasticash') }}"; // e.g., /admin/dasticash

            // ------------------------------
            // RESET FIELD ERRORS
            // ------------------------------
            function resetErrors(form) {
                $(form).find('.text-danger').remove();
                $(form).find('.is-invalid').removeClass('is-invalid');
            }

            // ------------------------------
            // BUTTON LOADER
            // ------------------------------
            function toggleLoader(button, loading = true, text = "Saving...") {
                if (loading) {
                    $(button).html(`<span class="spinner-border spinner-border-sm"></span> ${text}`);
                    $(button).attr("disabled", true);
                } else {
                    $(button).html($(button).data('original-text'));
                    $(button).attr("disabled", false);
                }
            }

            // ------------------------------
            // CSRF TOKEN
            // ------------------------------
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            $("#search_number").on("click", function (e) {
                e.preventDefault();

                const button = $(this);
                const originalHtml = button.html();
                const rawNumber = $("#number").val();
                const number = String(rawNumber || '').replace(/\D+/g, '');
                const messageBox = $("#msg").first();

                messageBox.removeClass("text-danger text-success").html("");

                if (!number || number.length < 10) {
                    messageBox.addClass("text-danger").html("Please enter a valid phone number.");
                    return;
                }

                button.prop("disabled", true).html(`<span class="spinner-border spinner-border-sm"></span> Searching...`);

                $.ajax({
                    type: "POST",
                    url: "{{ route('lead.search') }}",
                    data: {
                        number: number,
                        _token: "{{ csrf_token() }}"
                    },
                    success: function (res) {
                        const lead = res.data || {};
                        const assignedUsers = Array.isArray(lead.assignTo) && lead.assignTo.length
                            ? lead.assignTo.map(user => user.name).join(", ")
                            : "Unassigned";

                        messageBox.addClass("text-success").html(`
                            <div><strong>${lead.name ?? ""}</strong></div>
                            <div>Project: ${lead.project ?? "No Project"}</div>
                            <div>Assigned To: ${assignedUsers}</div>
                            <div>Status: ${lead.follow_status ?? ""}</div>
                        `);
                    },
                    error: function (xhr) {
                        const errorMessage = xhr.responseJSON?.error || "No Record Found in selected project.";
                        messageBox.addClass("text-danger").html(errorMessage);
                    },
                    complete: function () {
                        button.prop("disabled", false).html(originalHtml);
                    }
                });
            });

            // ===================================================================
            // 1️⃣ ADD DASTICASH (AJAX)
            // ===================================================================
            $("#addDasticashModal form").submit(function (e) {
                e.preventDefault();

                let form = this;
                let amount = $(form).find("input[name='amount']").val();
                let button = $(form).find("button[type='submit']");
                button.data('original-text', button.text());

                resetErrors(form);
                toggleLoader(button, true, "Saving...");

                $.ajax({
                    type: "POST",
                    url: form.action,
                    data: new FormData(form),
                    processData: false,
                    contentType: false,

                    success: function (res) {
                        toggleLoader(button, false);
                        $("#addDasticashModal").modal("hide");

                        Swal.fire({
                            icon: "success",
                            title: "Dasticash Added",
                            text: res.message,
                            timer: 1800,
                            showConfirmButton: false
                        });
                        let dastiAmount = parseFloat(res.data.amount) || 0;

                        let handCashEl = $("#handCash");
                        let totalCashEl = $("#totalCash");

                        let handCash = parseFloat(handCashEl.text().replace(/,/g, '')) || 0;
                        let totalCash = parseFloat(totalCashEl.text().replace(/,/g, '')) || 0;

                        let updatedHandCash = handCash - dastiAmount;
                        let updatedTotalCash = totalCash - dastiAmount;

                        handCashEl.text(updatedHandCash.toLocaleString(undefined, { minimumFractionDigits: 2 }));
                        totalCashEl.text(updatedTotalCash.toLocaleString(undefined, { minimumFractionDigits: 2 }));

                        // ------------------------------
                        // APPEND NEW ROW DYNAMICALLY
                        // ------------------------------
                        $("#dasticashTable tbody").append(`
                            <tr id="row-${res.data.id}">
                                <td>NEW</td>
                                <td class="name-cell">${res.data.name}</td>
                                <td class="amount-cell">${res.data.amount ?? ''}</td>
                                <td class="desc-cell">${res.data.description ?? ''}</td>
                                <td>
                                    <a href="javascript:void(0)"
                                       class="btn btn-sm btn-info edit-btn"
                                       data-id="${res.data.id}"
                                       data-name="${res.data.name}"
                                       data-amount="${res.data.amount ?? ''}"
                                       data-description="${res.data.description ?? ''}">
                                        <i class="fas fa-edit"></i>
                                    </a>

                                    <button class="btn btn-sm btn-danger delete-btn" 
                                            data-id="${res.data.id}" data-url="${destroyUrl}/${res.data.id}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `);

                        // Clear form
                        form.reset();
                    },

                    error: function (xhr) {
                        toggleLoader(button, false);

                        if (xhr.status === 422) {
                            $.each(xhr.responseJSON.errors, function (key, value) {
                                let input = $(form).find(`[name="${key}"]`);
                                input.addClass("is-invalid");
                                input.after(
                                    `<small class="text-danger">${value[0]}</small>`
                                );
                            });
                        }
                    }
                });
            });

            $('.edit-btn').click(function () {
                var id = $(this).data('id');
                var name = $(this).data('name');
                var amount = $(this).data('amount');
                var description = $(this).data('description');
                let baseUrl = "{{ url('/') }}"; // output: http://127.0.0.1:8000

                $('#edit-id').val(id);
                $('#edit-name').val(name);
                $('#edit-amount').val(amount);
                $('#edit-description').val(description);
                $('#editDasticashForm').attr('action', baseUrl + '/admin/dasticash/' + id);
                $('#editDasticashModal').modal('show');
            });

            // ===================================================================
            // 2️⃣ UPDATE DASTICASH (AJAX)
            // ===================================================================

            $("#editDasticashForm").submit(function (e) {
                e.preventDefault();

                let form = this;
                let button = $(form).find("button[type='submit']");
                button.data('original-text', button.text());

                resetErrors(form);
                toggleLoader(button, true, "Updating...");

                $.ajax({
                    type: "POST",
                    url: form.action,
                    data: new FormData(form),
                    processData: false,
                    contentType: false,

                    success: function (res) {
                        toggleLoader(button, false);
                        $("#editDasticashModal").modal("hide");

                        Swal.fire({
                            icon: "success",
                            title: "Updated Successfully",
                            text: res.message,
                            timer: 1800,
                            showConfirmButton: false
                        });

                        let row = $("#row-" + res.data.id);

                        row.find(".name-cell").text(res.data.name);
                        row.find(".amount-cell").text(res.data.amount ?? '');
                        row.find(".desc-cell").text(res.data.description ?? '');

                        // update dataset for edit button
                        let editBtn = row.find(".edit-btn");
                        editBtn.data("name", res.data.name);
                        editBtn.data("amount", res.data.amount);
                        editBtn.data("description", res.data.description);
                    },

                    error: function (xhr) {
                        toggleLoader(button, false);

                        if (xhr.status === 422) {
                            $.each(xhr.responseJSON.errors, function (key, value) {
                                let input = $(form).find(`[name="${key}"]`);
                                input.addClass("is-invalid");
                                input.after(
                                    `<small class="text-danger">${value[0]}</small>`
                                );
                            });
                        }
                    }
                });
            });

            // ===================================================================
            // 3️⃣ DELETE DYNAMIC (AJAX)
            // ===================================================================
            $(document).on("click", ".delete-btn", function () {
                let url = $(this).data("url");
                console.log(url);
                let id = $(this).data("id");
                let row = $("#row-" + id);

                Swal.fire({
                    title: "Are you sure?",
                    text: "This record will be removed.",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Yes, delete it!"
                }).then((result) => {
                    if (result.isConfirmed) {

                        $.ajax({
                            type: "DELETE",
                            url: url,
                            success: function (res) {
                                row.fadeOut(300, function () {
                                    $(this).remove();
                                });
                                dastiAmount = parseFloat(res.data.amount) || 0;
                                let id = res.data.id;
                                let handCashEl = $("#handCash");
                                let totalCashEl = $("#totalCash");

                                let handCash = parseFloat(handCashEl.text().replace(/,/g, '')) || 0;
                                let totalCash = parseFloat(totalCashEl.text().replace(/,/g, '')) || 0;

                                let updatedHandCash = handCash + dastiAmount;
                                let updatedTotalCash = totalCash + dastiAmount;

                                handCashEl.text(updatedHandCash.toLocaleString(undefined, { minimumFractionDigits: 2 }));
                                totalCashEl.text(updatedTotalCash.toLocaleString(undefined, { minimumFractionDigits: 2 }));
                                $("#row-" + id).remove();
                                Swal.fire({
                                    icon: "success",
                                    title: "Deleted",
                                    text: res.message,
                                    timer: 1500,
                                    showConfirmButton: false
                                });
                                window.location.reload();
                            }
                        });

                    }
                });
            });

        });
    </script>
@endsection
