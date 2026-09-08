@extends('admin.layouts.master')

@section('content')
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1>{{ $title }}</h1>
                    </div><!-- /.col -->
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active">{{ $title }}</li>
                        </ol>
                    </div><!-- /.col -->
                </div><!-- /.row -->
            </div><!-- /.container-fluid -->
        </div><!-- /.content-header -->

        <!-- Main content -->
        <form method="POST" action="{{ route('bookings.transfer') }}">
            <section class="content">
                <div class="container-fluid">
                    <div class="row">

                        <div class="col-md-7">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Booking Form </h3>
                                </div><!-- /.card-header -->
                                <div class="card-body">
                                    @csrf
                                    <input type="hidden" name="expected_updated_at" value="{{ $booking->updated_at?->format('Y-m-d H:i:s.u') }}">
                                    <input type="hidden" name="expected_project_id" value="{{ $booking->project_id }}">
                                    <input type="hidden" name="expected_customer_id" value="{{ $booking->customer_id }}">
                                    <input type="hidden" name="expected_plot_id" value="{{ $booking->plot_id }}">
                                    <input type="hidden" name="expected_plot_type" value="{{ $booking->plot_type }}">
                                    <input type="hidden" name="expected_plot_size" value="{{ $booking->plot_size }}">
                                    <input type="hidden" name="expected_plot_rate" value="{{ $booking->plot_rate }}">
                                    <input type="hidden" name="expected_is_park" value="{{ $booking->is_park }}">
                                    <input type="hidden" name="expected_park_facing" value="{{ $booking->park_facing }}">
                                    <input type="hidden" name="expected_is_corner" value="{{ $booking->is_corner }}">
                                    <input type="hidden" name="expected_carner_price" value="{{ $booking->carner_price }}">
                                    <input type="hidden" name="expected_dicount_value" value="{{ $booking->dicount_value }}">
                                    <input type="hidden" name="expected_total_price" value="{{ $booking->total_price }}">
                                    <input type="hidden" name="expected_booking_date" value="{{ $booking->booking_date }}">
                                    <input type="hidden" name="expected_status" value="{{ $booking->status }}">
                                    <input type="hidden" name="expected_broker_id" value="{{ $booking->broker_id }}">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <input type="text" class="form-control" id="booking_id" name="booking_id"
                                                    value="0000{{ $booking->id }}" disabled>
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-group">
                                                {{-- <label for="booking_date">Booking Date</label> --}}
                                                <input type="hidden" name="id" value="{{ $booking->id }}">
                                                <div class="input-group">
                                                    <input type="text" name="booking_date" class="date form-control"
                                                        data-input>
                                                    <!-- Add a hidden input to store the selected date in a format you want -->
                                                    <input type="hidden" id="hiddenDate" name="hiddenDate">
                                                    {{-- <input type="text" id="datepicker"
                                                        class="form-control @error('date') is-invalid @enderror" name="date"
                                                        value="{{ old('date') ?: date('d-m-yy') }}" autocomplete="off"> --}}
                                                    @error('booking_date')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-group">
                                                {{-- <label for="reference">Reference</label> --}}
                                                <input type="text" class="form-control" id="reference" name="reference"
                                                    placeholder="Referance">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                {{-- <label for="project">Project</label> --}}
                                                <div class="input-group">
                                                    <input type="hidden" name="project_id"
                                                        value="{{ $booking->project_id }}">
                                                    <select class="form-control select2 " name="project_id" id="project_id"
                                                        disabled>
                                                        <option value="">Select Project</option>

                                                        @foreach ($projects as $v)
                                                            <option value="{{ $v->id }}" {{ $booking->project_id == $v->id ? 'selected' : '' }}>{{ $v->project }}</option>
                                                        @endforeach
                                                    </select>
                                                    @error('project_id')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                {{-- <label for="customer">Customer</label> --}}
                                                <input type="hidden" name="old_customer_id"
                                                    value="{{ $booking->customer_id }}">
                                                <select class="form-control select2" name="customer_id" id="customer_id">
                                                    <option value="">Select Customer</option>
                                                    @foreach ($customers as $v)
                                                        <option value="{{ $v->id }}" {{ $booking->customer_id == $v->id ? 'selected' : '' }}>
                                                            {{ $v->first_name . ' ' . $v->last_name . ' ' . $v->relate . ' ' . $v->father_name . ' - ' . $v->phone_number }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('customer_id')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                {{-- <label for="plot_type">Plot Type</label> --}}
                                                <input type="hidden" name="plot_type" value="{{ $booking->plot_type }}">
                                                <select class="form-control select2" name="plot_type" id="plot_type"
                                                    disabled>
                                                    <option value="" {{ $booking->plot_type == '' ? 'selected' : '' }}>Select
                                                        Plot type</option>
                                                    <option value="1" {{ $booking->plot_type == '1' ? 'selected' : '' }}>
                                                        Residential</option>
                                                    <option value="2" {{ $booking->plot_type == '2' ? 'selected' : '' }}>
                                                        Commercial</option>

                                                </select>
                                                @error('plot_type')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror

                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <input type="hidden" name="plot_id" value="{{ $booking->plot_id }}">
                                                <select class="form-control select2" name="plot_id" id="plot_id" disabled>
                                                    <option value="">Select Plot</option>
                                                    @foreach ($plots as $v)
                                                        <option value="{{ $v->id }}" data-size='{{ $v->size }}' {{ $booking->plot_id == $v->id ? 'selected' : '' }}>{{ $v->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('plot_id')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <input type="text" class="form-control" id="plot_size" name="plot_size"
                                                    value="{{ $booking->plot_size }}" required
                                                    placeholder="Plot Size (Marla)" readonly>
                                            </div>
                                        </div>


                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <input type="hidden" name="broker_id" value="{{ $booking->broker_id }}">
                                                <select class="form-control select2" name="broker_id" id="broker_id"
                                                    disabled>
                                                    <option value="">Select Broker</option>

                                                    @foreach ($brokers as $v)
                                                        <option value="{{ $v->id }}" {{ $booking->broker_id == $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
                                                    @endforeach
                                                </select>
                                                @error('broker_id')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror

                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                {{-- <label for="status">Status</label> --}}
                                                <input type="hidden" name="status"
                                                    value="{{ $booking->status == 'active' ? '1' : '0' }}">
                                                <select class="form-control" id="status" name="status" required
                                                    placeholder="Status" disabled>
                                                    <option value="1" {{ $booking->status == '1' ? 'selected' : '' }}>Active
                                                    </option>
                                                    <option value="0" {{ $booking->status == '0' ? 'selected' : '' }}>Inactive
                                                    </option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <textarea class="form-control" id="details" name="details" rows="3"
                                                    placeholder="Details"></textarea>

                                            </div>
                                        </div>
                                    </div><!-- /.row -->
                                    <button type="submit" class="btn btn-primary">Submit</button>
                                </div><!-- /.card-body -->
                            </div><!-- /.card -->
                        </div><!-- /.col -->


                        <div class="col-md-5">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Booking Form </h3>
                                </div><!-- /.card-header -->
                                <div class="card-body">

                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <input type="text" oninput="formatAmount(this)" class="form-control"
                                                    id="plot_rate" name="plot_rate" value="{{ $booking->plot_rate ?? '' }}"
                                                    placeholder="Rate / Marla" {{ ($booking->plot_rate ?? 0) == 0 ? 'readonly' : '' }}>
                                            </div>
                                        </div>
                                        <div class="col-md-9">
                                            <div class="form-group">
                                                <input type="text" class="form-control" id="sub_total_price"
                                                    name="sub_total_price" @if($booking->sub_total_price > 0)
                                                    value="{{ $booking->sub_total_price ?? '' }}" @endif
                                                    placeholder="Total Price" readonly>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <select class="form-control" id="is_park" name="is_park" required
                                                    placeholder="Park Facing">
                                                    <option value="1" {{ $booking->is_park == '1' ? 'selected' : '' }}>Yes
                                                    </option>
                                                    <option value="0" {{ $booking->is_park == '0' ? 'selected' : '' }}>No
                                                    </option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-9">
                                            <div class="form-group">
                                                <input type="text" class="form-control" id="park_facing" name="park_facing"
                                                    @if ($booking->park_facing > 0)
                                                    value="{{ $booking->park_facing ?? '' }}" @endif
                                                    placeholder="Park Facing Charges " {{ $booking->is_park == '0' ? 'readonly' : '' }}>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <select class="form-control" id="is_corner" name="is_corner" required
                                                    placeholder="Corner Facing">
                                                    <option value="1" {{ $booking->is_corner == '1' ? 'selected' : '' }}>Yes
                                                    </option>
                                                    <option value="0" {{ $booking->is_corner == '0' ? 'selected' : '' }}>No
                                                    </option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-9">
                                            <div class="form-group">
                                                <input type="text" class="form-control" id="carner_price"
                                                    name="carner_price" @if($booking->carner_price > 0)
                                                    value="{{ $booking->carner_price ?? '' }}" @endif
                                                    placeholder="Carner Charges " {{ $booking->is_corner == '0' ? 'readonly' : '' }}>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <select class="form-control" id="party" name="party" required
                                                    placeholder="Corner Facing">
                                                    <option value="old">Old Party</option>
                                                    <option value="new">New Party</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-9">
                                            <div class="form-group">
                                                <input type="text" class="form-control" id="party_fee" name="party_fee"
                                                    placeholder="Fee pay by">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12">
                                            <hr>
                                        </div>

                                    </div>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="grand_total">Discount</label>
                                            </div>
                                        </div>
                                        <div class="col-md-9">
                                            <div class="form-group">
                                                <input type="text" class="form-control" id="discount_value"
                                                    name="discount_value" value="{{ $booking->discount_value ?? '' }}"
                                                    placeholder="Discount Amount ">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="grand_total">Grand Total</label>
                                            </div>
                                        </div>
                                        <div class="col-md-9">
                                            <div class="form-group">
                                                <input type="text" class="form-control" id="total_price" name="total_price"
                                                    value="{{ $booking->total_price ?? '' }}" placeholder="Grand Total"
                                                    readonly>
                                            </div>
                                        </div>
                                    </div>

                                </div><!-- /.card-body -->
                            </div><!-- /.card -->
                        </div><!-- /.col -->
                    </div><!-- /.row -->
                </div><!-- /.container-fluid -->
            </section><!-- /.content -->
        </form>
    </div><!-- /.content-wrapper -->
@endsection

@section('js')
    <script>
        $(document).ready(function () {
            // Function to fetch customers based on selected project
            function fetchCustomers(projectId) {
                $.ajax({
                    url: '/admin/get-customers', // URL to your route
                    type: 'POST', // Use POST method for sending data
                    data: {
                        _token: '{{ csrf_token() }}', // Add CSRF token
                        project_id: projectId // Pass project ID to server
                    },
                    success: function (data) {
                        // Populate customer dropdown with retrieved data
                        $('#customer_id').empty();
                        $('#customer_id').append('<option value="">Select customer</option>');
                        $.each(data, function (key, customer) {
                            $('#customer_id').append('<option value="' + customer.id + '">' + customer.first_name + ' ' + customer.last_name + ' ' + customer.relate + ' ' + customer.father_name + ' - ' + customer.phone_number + '</option>');
                        });
                    }
                });
            }

            function fetch_plot_list(plot_type) {
                $.ajax({
                    url: '/admin/get-plots', // URL to your route
                    type: 'POST', // Use POST method for sending data
                    data: {
                        _token: '{{ csrf_token() }}', // Add CSRF token
                        plot_type: plot_type, // Pass project ID to server
                        project_id: '{{ getSelectedTown() }}'
                    },
                    success: function (data) {
                        $('#plot_id').empty();
                        $('#plot_id').append('<option value="">Select Plot</option>');
                        $.each(data, function (key, plot) {
                            $('#plot_id').append('<option value="' + plot.id + '" data-size="' + plot.size + '" >' + plot.name + '</option>');
                        });
                    }
                });
            }

            $('#plot_type').change(function () {
                // Get selected project ID
                var plotType = $(this).val();

                // If a project is selected, fetch customers
                if (plotType) {
                    fetch_plot_list(plotType);
                } else {

                    $('#plot_id').empty();
                    $('#plot_id').append('<option value="">Select Plot</option>');
                }
            });

            // Event listener for project dropdown change
            $('#project_id').change(function () {
                // Get selected project ID
                var projectId = $(this).val();

                // If a project is selected, fetch customers
                if (projectId) {
                    fetchCustomers(projectId);
                } else {
                    // If no project is selected, empty the customer dropdown
                    $('#customer_id').empty();
                    $('#customer_id').append('<option value="">Select customer</option>');
                }
            });



            $('#plot_id').change(function (e) {
                e.preventDefault();
                // on change plot id get plot size from selected plot data-size and append it to plot size field
                var selectedPlotSize = $(this).find(':selected').data('size'); // Get the data-size attribute of the selected plot
                $('#plot_size').val(selectedPlotSize); // Set the value of the plot size input field to the selected plot's size
                if (selectedPlotSize > 0) {
                    $('#plot_rate').prop('readonly', false); // Make plot_rate input field writable
                } else {
                    $('#plot_rate').prop('readonly', true); // Make plot_rate input field readonly
                }


            });

            // Event listener for plot size change
            $('#plot_size').change(function () {

                // Remove any non-numeric characters from the input value
                var sanitizedValue = $(this).val().replace(/[^0-9.]/g, '');
                // Update the input value with the sanitized value
                $(this).val(sanitizedValue);

                // Check if plot size is empty
                if ($(this).val() === '') {
                    // If plot size is empty, disable plot rate and total price fields
                    $('#plot_rate').prop('readonly', true);
                } else {
                    // If plot size is not empty, enable plot rate and total price fields
                    $('#plot_rate').val('');
                    $('#plot_rate').prop('readonly', false);
                }
            });

            // Event listener for #is_park change
            $('#is_park').change(function () {
                if ($(this).val() == 1) {
                    $('#park_facing').prop('readonly', false);
                } else {
                    $('#park_facing').val('');
                    $('#park_facing').prop('readonly', true);
                }
            });

            $('#is_corner').change(function () {
                if ($(this).val() == 1) {
                    $('#carner_price').prop('readonly', false);
                } else {
                    $('#carner_price').val('');
                    $('#carner_price').prop('readonly', true);
                }
            });

            // Event listener for plot size and plot rate change
            $('#plot_size, #plot_rate').on('input', function () {
                plotTotalPrice();
            });
            plotTotalPrice();
            function plotTotalPrice() {
                var numberInput = $('#plot_rate').val();
                console.log(numberInput);


                // Remove commas for thousands separator
                var numericValue = numberInput.replace(/,/g, '');
                var plotSize = parseFloat($('#plot_size').val());
                var plotRate = parseFloat(numericValue);

                // Check if both plot size and plot rate are not empty
                if (plotSize && plotRate) {
                    // Calculate total price by multiplying plot size and plot rate
                    var totalPrice = plotSize * plotRate;

                    // Format totalPrice with commas for thousands separator
                    var formattedPrice = totalPrice.toLocaleString();

                    // Set the value of #total_price
                    $('#sub_total_price').val(formattedPrice);
                    updateGrandTotal();
                }
            }
            function updateGrandTotal() {
                var subTotalPrice = parseFloat($('#sub_total_price').val().replace(/,/g, '')) || 0;
                var parkFacing = parseFloat($('#park_facing').val().replace(/,/g, '')) || 0;
                var carnerPrice = parseFloat($('#carner_price').val().replace(/,/g, '')) || 0;
                var discount_value = parseFloat($('#discount_value').val().replace(/,/g, '')) || 0;

                var grandTotal = (subTotalPrice + parkFacing + carnerPrice) - discount_value;
                var formattedGrandTotal = grandTotal.toLocaleString();
                $('#total_price').val(formattedGrandTotal);
            }
            // Event listener for sub total price, park facing, and carner price change
            $('#sub_total_price, #park_facing, #carner_price , #discount_value').on('input', function () {
                totalPrice();
            });
            totalPrice();
            function totalPrice() {
                var subTotalPrice = parseFloat($('#sub_total_price').val().replace(/,/g, '')) || 0; // Remove commas for thousands separator

                // Retrieve park facing and ensure it defaults to 0 if empty
                var parkFacing = parseFloat($('#park_facing').val().replace(/,/g, '')) || 0; // Remove commas for thousands separator

                // Retrieve carner price and ensure it defaults to 0 if empty
                var carnerPrice = parseFloat($('#carner_price').val().replace(/,/g, '')) || 0; // Remove commas for thousands separator

                var discount_value = parseFloat($('#discount_value').val().replace(/,/g, '')) || 0; // Remove commas for thousands separator

                // Check if sub total price, park facing, and carner price are valid numbers
                if (!isNaN(parkFacing) && !isNaN(carnerPrice)) {
                    // Calculate total price by adding sub total price, park facing, and carner price
                    var totalPrice = (subTotalPrice + parkFacing + carnerPrice) - discount_value;

                    // Format totalPrice with commas for thousands separator
                    var formattedPrice = totalPrice.toLocaleString();

                    // Set the value of #total_price
                    $('#total_price').val(formattedPrice);
                }
            }




        });
       

    </script>
<script>
function formatAmount(input) {
  if (!input) return;

  // Take only the part before the decimal point
  let raw = String(input.value ?? '');
  raw = raw.split('.')[0];                 // "100000.00" -> "100000"
  raw = raw.replace(/[^0-9]/g, '');        // remove commas/anything non-digit
  raw = raw.replace(/^0+(?=\d)/, '');      // remove leading zeros but keep single 0

  // Add commas
  raw = raw.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

  input.value = raw;
}

// Format on page load
document.addEventListener('DOMContentLoaded', function () {
  const ids = ['plot_rate', 'total_price', 'carner_price', 'discount_value', 'party_fee'];

  ids.forEach((id) => {
    const el = document.getElementById(id);
    if (el && el.value) formatAmount(el);
  });
});
</script>



@endsection
