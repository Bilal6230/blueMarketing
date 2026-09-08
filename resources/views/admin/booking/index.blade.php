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
        </div>
        <!-- /.content-header -->

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <!-- Total Sale Amount Card -->
                    <div class="col-md-4">
                        <div class="card" style="background-color: #fd7e14; color: #fff; border-radius: 10px;">
                            <div class="card-body">
                                <h5 class="card-title">Total Sale Amount</h5>
                                <p class="card-text">
                                    <strong>{{ Setting::formatAmount($totalSaleAmount) }}</strong>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Plots List Card -->
                    <div class="col-12">
                        <div class="card">
                            @can('create plot')
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <a href="{{ route('booking.plot.sale') }}" class="btn btn-sm btn-success"><i
                                                class="fas fa-plus"></i> New Booking</a>
                                    </h3>
                                </div>
                            @endcan
                            <!-- /.card-header -->
                            <div class="card-body table-responsive">
                                <table id="example1" class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Date</th>
                                            <th>Broker</th>
                                            <th>Plot</th>
                                            <th>Customer</th>
                                            <th>Phone</th>
                                            <th>Sale Amount</th>
                                            @canany(['update plot', 'delete plot'])
                                                <th>Action</th>
                                            @endcanany
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($data as $i)
                                            <tr>
                                                <td>{{ $loop->iteration }}</td>
                                                <td>{{ Setting::getShortDate($i->booking_date) }}</td>
                                                <td>{{ $i->broker->name ?? "" }}</td>
                                                {{-- <td>{{ Setting::getPlotTypeShort($i->plot_type) }} - <a href="#"> {{
                                                        $i->plot->name }}</a></td> --}}

                                                <td>
                                                    {{ Setting::getPlotTypeShort($i->plot_type) }} -
                                                    <form action="{{ route('booking.customer.report.display') }}" method="POST"
                                                        style="display: inline;">
                                                        @csrf
                                                        <input type="hidden" name="plot_id" value="{{ $i->plot->id }}">
                                                        <input type="hidden" name="customer_id" value="{{ $i->customer->id }}">
                                                        <input type="hidden" name="action" value="booking_file">

                                                        <button type="submit" class="btn btn-card"
                                                            style="padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; background: linear-gradient(135deg, #6c5ce7, #00b894); color: white; font-size: 14px; text-align: center; cursor: pointer; transition: transform 0.2s ease;">
                                                            {{ $i->plot->name }}
                                                        </button>
                                                    </form>
                                                </td>

                                                <td>{{ $i->customer->first_name . ' ' . $i->customer->last_name . ' ' . $i->customer->relate . ' ' . $i->customer->father_name }}
                                                </td>
                                                <td>{{ $i->customer->phone_number }}</td>
                                                <td>
                                                    @canany(['update plot number', 'update booking price'])
                                                        <a href="{{ route('booking.price.update', ['id' => $i->id]) }}"
                                                            class="btn btn-link">
                                                            {{ Setting::formatAmount($i->total_price) }}
                                                        </a>
                                                    @else
                                                        {{ Setting::formatAmount($i->total_price) }}
                                                    @endcanany
                                                </td>
                                                @canany(['update plot', 'delete plot', 'delete booking'])
                                                    <td>
                                                        <div class="btn-group">
                                                            @can('update plot')
                                                                <a href="{{ route('booking.edit', ['id' => $i->id]) }}"
                                                                    class="btn btn-xs ml-1 btn-outline-primary"
                                                                    title="Edit Booking" aria-label="Edit Booking">
                                                                    <i class="fas fa-pen fa-xs"></i>
                                                                </a>
                                                                @if (Setting::is_schedule($i->id) < 2)
                                                                    <a href="{{ route('booking.schedule.form', ['id' => $i->id]) }}"
                                                                        class="btn btn-xs ml-1 btn-primary btn-schedule"
                                                                        title="Schedule a Plot">
                                                                        <i class="fas fa-plus fa-xs"></i>
                                                                    </a>
                                                                @else
                                                                    <a href="{{ route('booking.schedule.form', ['id' => $i->id]) }}"
                                                                        class="btn btn-xs ml-1 btn-success btn-schedule"
                                                                        title="Edit Schedule">
                                                                        <i class="fas fa-edit fa-xs"></i>
                                                                    </a>
                                                                    <a href="{{ route('booking.plot.file-transfer', ['id' => $i->id]) }}"
                                                                        class="btn btn-xs ml-1 btn-warning btn-file-transfer"
                                                                        title="File Transfer">
                                                                        <i class="fas fa-exchange-alt fa-xs"></i>
                                                                    </a>
                                                                    <button
                                                                        class="btn btn-xs ml-1 btn-danger btn-schedule  btn-cancel-booking"
                                                                        data-id="{{ $i->id }}" data-total="{{ $i->total_price }}"
                                                                        title="Payment not received" data-toggle="modal"
                                                                        data-target="#actionBookingModal">
                                                                        <i class="fas fa-times fa-xs"></i>
                                                                    </button>
                                                                @endif
                                                            @endcan

                                                            @can('delete booking')
                                                                <!-- Delete Button -->
                                                                <button class="btn btn-xs ml-1 btn-danger btn-delete-booking"
                                                                    data-id="{{ $i->id }}" data-plot="{{ $i->plot->name }}"
                                                                    data-toggle="modal" data-target="#deleteBookingModal">
                                                                    <i class="fas fa-trash fa-xs"></i>
                                                                </button>
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
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@section('modal')
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteBookingModal" tabindex="-1" role="dialog" aria-labelledby="deleteBookingModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteBookingModalLabel">Confirm Booking Deletion</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="deleteBookingForm" method="POST">
                    @csrf
                    @method('DELETE')
                    <div class="modal-body">
                        <p>Please enter the **Plot Number** to confirm deletion:</p>
                        <input type="hidden" name="booking_id" id="booking_id">
                        <input type="text" class="form-control" id="confirm_plot_number" placeholder="Enter Plot Number">
                        <small class="text-danger" id="deleteError" style="display: none;">Incorrect plot number!</small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Confirm Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade" id="actionBookingModal" tabindex="-1" role="dialog" aria-labelledby="actionBookingModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title" id="actionBookingModalLabel">Plot Cancellation</h5>

                    <!-- ✅ FIX: add data-dismiss="modal" -->
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <form id="actionBookingForm" method="POST">
                    @csrf

                    <div class="modal-body">
                        <input type="hidden" name="booking_id" id="cancel_booking_id">

                        <!-- Existing Actions -->
                        <div class="mb-3">
                            <label class="d-block"><strong>Select Action:</strong></label>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="action_type"
                                            id="payment_not_received" value="payment_not_received" checked>
                                        <label class="form-check-label" for="payment_not_received">Payment not
                                            received</label>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="action_type" id="action_purchase"
                                            value="plot_purchase">
                                        <label class="form-check-label" for="action_purchase">Plot Purchase</label>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="action_type" id="re_sale"
                                            value="re_sale">
                                        <label class="form-check-label" for="re_sale">ReSale</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <!-- Cancellation Accounting Inputs -->
                        <div class="mb-3">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="cancel_effect"><strong>Cancellation Effect</strong></label>
                                    <select class="form-control" name="cancel_effect" id="cancel_effect">
                                        <option value="none" selected>None of them</option>
                                        <option value="charge_customer">Charge Customer (Deduction)</option>
                                        <option value="give_profit">Give Customer Profit</option>
                                    </select>
                                    <small class="text-muted">
                                        Select whether you are charging customer or giving profit.
                                    </small>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="sale_amount"><strong>Sale Amount</strong></label>
                                    <input type="text" class="form-control" id="sale_amount" name="sale_amount" value=""
                                        readonly placeholder="Auto">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="deduction_amount"><strong>Deduction Amount</strong></label>
                                    <input type="text" class="form-control" id="deduction_amount" name="deduction_amount"
                                        value="" placeholder="Enter deduction"
                                        oninput="pvFormatAmountNoDecimal(this); pvRecalcCancellationPreview();">
                                    <small class="text-muted">Will be subtracted from Sale Amount.</small>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="net_amount"><strong>Net Amount</strong></label>
                                    <input type="text" class="form-control" id="net_amount" name="net_amount" value=""
                                        readonly placeholder="Auto">
                                    <small class="text-muted" id="net_amount_hint">
                                        Preview: sale - deduction
                                    </small>
                                </div>
                            </div>

                            <div class="alert alert-light border mb-0" id="cancel_preview_box">
                                <div class="d-flex flex-wrap justify-content-between align-items-center">
                                    <div class="mb-2 mb-md-0">
                                        <strong>Preview:</strong>
                                        <span id="pv_preview_text" class="ml-1">—</span>
                                    </div>
                                    <div>
                                        <span class="badge badge-secondary" id="pv_preview_badge">Pending</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="mb-2">
                            <label for="reason"><strong>Reason</strong></label>
                            <textarea name="reason" class="form-control" id="reason" cols="60" rows="3"
                                placeholder="Write reason for cancellation..."></textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Update</button>
                    </div>
                </form>

            </div>
        </div>
    </div>
@endsection

@section('js')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>

        $(document).ready(function () {

            let plotName = ""; // Store correct plot name for validation

            // Open modal and set values
            $(".btn-delete-booking").click(function () {
                debugger;
                let bookingId = $(this).data("id");
                plotName = $(this).data("plot"); // Store correct plot name

                $("#booking_id").val(bookingId); // Set hidden booking_id
                $("#confirm_plot_number").val(""); // Clear input field
                $("#deleteError").hide(); // Hide error message
            });
            $(".btn-cancel-booking").click(function () {
                let bookingId = $(this).data("id");
                $("#cancel_booking_id").val(bookingId);
                let total = $(this).data("total");
                document.getElementById('sale_amount').value = total;
                pvFormatAmountNoDecimal(document.getElementById('sale_amount'));
                pvRecalcCancellationPreview();

            });

            // Handle form submission for deletion
            $("#deleteBookingForm").submit(function (e) {
                e.preventDefault(); // Prevent default form submission

                let enteredPlotName = $("#confirm_plot_number").val().trim(); // Trim spaces

                if (enteredPlotName !== plotName) {
                    $("#deleteError").show(); // Show error message
                    return false;
                }

                let bookingId = $("#booking_id").val();
                let deleteUrl = "{{ route('booking.destroy', ':id') }}".replace(':id', bookingId);

                $.ajax({
                    url: deleteUrl,
                    type: "DELETE", // Use DELETE method
                    data: {
                        _token: "{{ csrf_token() }}", // CSRF token for security
                        booking_id: bookingId
                    },
                    success: function (response) {
                        $("#deleteBookingModal").modal("hide"); // Close modal
                        location.reload(); // Reload page after deletion
                    },
                    error: function (xhr) {
                        alert("Error deleting booking. Please try again!"); // Show error message
                    }
                });
            });
            $("#actionBookingForm").on("submit", function (e) {
                e.preventDefault();

                const $form = $(this);
                const bookingId = $("#cancel_booking_id").val();

                if (!bookingId) {
                    Swal.fire({ icon: "error", title: "Missing booking", text: "Booking ID not found." });
                    return;
                }

                // Build URL
                const actionUrl = "{{ route('booking.cancel', ':id') }}".replace(":id", bookingId);

                // ✅ payload (don’t forget these)
                const payload = {
                    _token: "{{ csrf_token() }}",
                    booking_id: bookingId,

                    // existing fields
                    action_type: $('input[name="action_type"]:checked').val(),
                    reason: $("#reason").val(),

                    // cancellation accounting fields
                    cancel_effect: $("#cancel_effect").val(), // charge_customer | give_profit
                    sale_amount: $("#sale_amount").val(),     // formatted
                    deduction_amount: $("#deduction_amount").val(), // formatted
                    net_amount: $("#net_amount").val(),       // formatted
                };

                // Basic front validation (optional)
                if (!payload.action_type) {
                    Swal.fire({ icon: "warning", title: "Action required", text: "Please select an action type." });
                    return;
                }

                const $submitBtn = $form.find('button[type="submit"]');
                $submitBtn.prop("disabled", true).text("Updating...");

                $.ajax({
                    url: actionUrl,
                    type: "POST",
                    data: payload,
                    dataType: "json",
                    success: function (response) {
                        $("#actionBookingModal").modal("hide");

                        Swal.fire({
                            icon: "success",
                            title: "Updated",
                            text: response?.message || "Plot cancellation updated successfully.",
                            confirmButtonText: "OK",
                        }).then(() => {
                            // reload only after user closes swal
                            location.reload();
                        });
                    },
                    error: function (xhr) {
                        let msg = "Something went wrong. Please try again.";

                        // Laravel validation errors (422)
                        if (xhr.status === 422 && xhr.responseJSON) {
                            const errors = xhr.responseJSON.errors || {};
                            const firstKey = Object.keys(errors)[0];
                            if (firstKey && errors[firstKey]?.length) {
                                msg = errors[firstKey][0];
                            } else if (xhr.responseJSON.message) {
                                msg = xhr.responseJSON.message;
                            }
                        } else if (xhr.responseJSON?.message) {
                            msg = xhr.responseJSON.message;
                        }

                        Swal.fire({
                            icon: "error",
                            title: "Failed",
                            text: msg,
                        });
                    },
                    complete: function () {
                        $submitBtn.prop("disabled", false).text("Update");
                    }
                });
            });

        });

        function pvFormatAmountNoDecimal(input) {
            if (!input) return;

            let raw = String(input.value ?? '');
            raw = raw.split('.')[0];
            raw = raw.replace(/[^0-9]/g, '');
            raw = raw.replace(/^0+(?=\d)/, '');
            raw = raw.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

            input.value = raw;
        }

        function pvParseIntAmount(v) {
            v = String(v ?? '').split('.')[0].replace(/[^0-9]/g, '');
            return v ? (parseInt(v, 10) || 0) : 0;
        }
        function pvRecalcCancellationPreview() {
            const sale = pvParseIntAmount(document.getElementById('sale_amount')?.value);
            const amount = pvParseIntAmount(document.getElementById('deduction_amount')?.value); // same input used for both
            const effect = document.getElementById('cancel_effect')?.value || 'charge_customer';

            if (!sale) {
                const txt = document.getElementById('pv_preview_text');
                const badge = document.getElementById('pv_preview_badge');
                if (txt) txt.textContent = 'Sale amount not loaded yet.';
                if (badge) {
                    badge.className = 'badge badge-secondary';
                    badge.textContent = 'Pending';
                }
                const netEl = document.getElementById('net_amount');
                if (netEl) netEl.value = '';
                return;
            }

            // ✅ core fix: profit adds, charge subtracts
            let netRaw = (effect === 'give_profit') ? (sale + amount) : (sale - amount);

            // optional safety: don’t go negative in charge mode
            if (effect !== 'give_profit') netRaw = Math.max(0, netRaw);

            const netFormatted = netRaw.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');

            const netEl = document.getElementById('net_amount');
            if (netEl) netEl.value = netFormatted;

            const amtFormatted = amount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            const saleFormatted = sale.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');

            const txt = document.getElementById('pv_preview_text');
            const badge = document.getElementById('pv_preview_badge');

            if (effect === 'charge_customer') {
                if (txt) txt.textContent = `Charge mode → Sale: ${saleFormatted}, Deduction: ${amtFormatted} → Net: ${netFormatted}`;
                if (badge) {
                    badge.className = 'badge badge-warning';
                    badge.textContent = 'Charge Customer';
                }
            } else {
                if (txt) txt.textContent = `Profit mode → Sale: ${saleFormatted}, Profit: ${amtFormatted} → Net: ${netFormatted}`;
                if (badge) {
                    badge.className = 'badge badge-success';
                    badge.textContent = 'Give Profit';
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const effectEl = document.getElementById('cancel_effect');
            if (effectEl) effectEl.addEventListener('change', pvRecalcCancellationPreview);

            $('#actionBookingModal').on('shown.bs.modal', function () {
                ['sale_amount', 'deduction_amount', 'net_amount'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el && el.value) pvFormatAmountNoDecimal(el);
                });
                pvRecalcCancellationPreview();
            });
        });
        function pvFormatAmountNoDecimal(input) {
            if (!input) return;

            let raw = String(input.value ?? '');
            raw = raw.split('.')[0];
            raw = raw.replace(/[^0-9]/g, '');
            raw = raw.replace(/^0+(?=\d)/, '');
            raw = raw.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

            input.value = raw;
        }
    </script>
@endsection
