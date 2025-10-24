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
                                    <a href="{{ route('booking.plot.sale') }}" class="btn btn-sm btn-success" ><i class="fas fa-plus"></i> New Booking</a>
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
                                                {{-- <td>{{ Setting::getPlotTypeShort($i->plot_type) }} - <a href="#"> {{ $i->plot->name }}</a></td> --}}

                                                <td>
                                                    {{ Setting::getPlotTypeShort($i->plot_type) }} -
                                                    <form action="{{ route('booking.customer.report.display') }}" method="POST" style="display: inline;">
                                                        @csrf
                                                        <input type="hidden" name="plot_id" value="{{ $i->plot->id }}">
                                                        <input type="hidden" name="customer_id" value="{{ $i->customer->id }}">
                                                        <input type="hidden" name="action" value="booking_file">

                                                        <button type="submit" class="btn btn-card" style="padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; background: linear-gradient(135deg, #6c5ce7, #00b894); color: white; font-size: 14px; text-align: center; cursor: pointer; transition: transform 0.2s ease;">
                                                            {{ $i->plot->name }}
                                                        </button>
                                                    </form>
                                                </td>

                                                <td>{{ $i->customer->first_name.' '.$i->customer->last_name.' '.$i->customer->relate.' '.$i->customer->father_name }}</td>
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
                                                                data-id="{{ $i->id }}"
                                                                title="Payment not received"
                                                                data-toggle="modal"
                                                                data-target="#actionBookingModal">
                                                                <i class="fas fa-times fa-xs"></i>
                                                            </button>
                                                            @endif
                                                            @endcan

                                                            @can('delete booking')
                                                            <!-- Delete Button -->
                                                            <button class="btn btn-xs ml-1 btn-danger btn-delete-booking"
                                                                    data-id="{{ $i->id }}"
                                                                    data-plot="{{ $i->plot->name }}"
                                                                    data-toggle="modal"
                                                                    data-target="#deleteBookingModal">
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


@section('js')
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
            debugger;
            let bookingId = $(this).data("id");

            $("#cancel_booking_id").val(bookingId);
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
        $("#actionBookingForm").submit(function (e) {
            e.preventDefault(); // Prevent default form submission

            //let enteredPlotName = $("#confirm_plot_number").val().trim(); // Trim spaces

            //if (enteredPlotName !== plotName) {
            //    $("#deleteError").show(); // Show error message
            //    return false;
            //}

            let bookingId = $("#cancel_booking_id").val();
            let reason = $("#reason").val();
            let actionType = $('input[name="action_type"]:checked').val();

            let actionUrl = "{{ route('booking.cancel', ':id') }}".replace(':id', bookingId);

            $.ajax({
                url: actionUrl,
                type: "POST",
                data: {
                    _token: "{{ csrf_token() }}", // CSRF token for security
                    booking_id: bookingId,
                    reason: reason,
                    action_type: actionType
                },
                success: function (response) {
                    $("#actionBookingModal").modal("hide"); // Close modal
                    location.reload(); // Reload page after deletion
                },
                error: function (xhr) {
                    alert("Error deleting booking. Please try again!"); // Show error message
                }
            });
        });
    });
</script>
@endsection


@section('modal')
<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteBookingModal" tabindex="-1" role="dialog" aria-labelledby="deleteBookingModalLabel" aria-hidden="true">
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
<div class="modal fade" id="actionBookingModal" tabindex="-1" role="dialog" aria-labelledby="actionBookingModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="actionBookingModalLabel">Plot Cancellation</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
           <form id="actionBookingForm" method="POST">
                @csrf

                <div class="modal-body">
                    {{-- <p>Please enter the <strong>Plot Number</strong> to confirm Action:</p> --}}

                    <input type="hidden" name="booking_id" id="cancel_booking_id">

                    {{-- <input type="text" class="form-control mb-3" id="confirm_plot_number" name="confirm_plot_number" placeholder="Enter Plot Number">

                    <small class="text-danger" id="deleteError" style="display: none;">Incorrect plot number!</small>

                    <hr> --}}

                    <label><strong>Select Action:</strong></label>
                    <div class="row">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="action_type" id="payment_not_received" value="payment_not_received" checked>
                            <label class="form-check-label mr-2" for="payment_not_received">Payment not received</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="action_type" id="action_purchase" value="plot_purchase">
                            <label class="form-check-label mr-2" for="action_purchase">Plot Purchase</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="action_type" id="re_sale" value="re_sale">
                            <label class="form-check-label mr-2" for="re_sale">ReSale</label>
                        </div>
                    </div>
                    <label for="reason">Reason</label>
                    <textarea name="reason" class="form-control" id="reason" cols="60" ></textarea>
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


