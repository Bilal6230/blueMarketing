@extends('admin.layouts.master')
<style>
    .modal-content {
        border-radius: 10px;
        box-shadow: 0px 10px 30px rgba(0, 0, 0, 0.2);
        animation: fadeIn 0.3s ease-in-out;
    }

    .modal-header {
        border-bottom: none;
    }

    .modal-footer button {
        border-radius: 25px;
        padding: 8px 20px;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: scale(0.95);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }
    input[name="plot_number"]:focus {
        border-color: #007bff;
        box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    
</style>

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
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Booking Form </h3>
                            </div><!-- /.card-header -->

                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <!-- Display customer details here -->
                                        <p><strong>Name:</strong> {{ $data->customer->first_name }} {{ $data->customer->last_name }} {{ $data->customer->relate }}  {{ $data->customer->father_name }}</p>
                                        <p><strong>Phone:</strong> {{ $data->customer->phone_number }}</p>
                                        <p><strong>CNIC:</strong> {{ $data->customer->cnic }}</p>
                                        <p><strong>Plot Number:</strong> 
                                            <input type="text" name="plot_id" id="plot_id" value="{{ $data->plot->id }}" hidden > 
                                            <input type="text" id="plot_number" name="plot_number" class="form-control d-inline-block w-auto" value="{{ $data->plot->name }}" readonly ondblclick="this.readOnly = false;" 
                                            style="width: 80px !important;" maxlength="5" placeholder="Enter New Plot Number" onfocus="this.style.borderColor = '#007bff';" onblur="this.style.borderColor = '';" >

                                            <button id="save_plot_number" class="btn btn-primary btn-sm ms-2">Update </button>
                                        </p>
                                        <p><strong>Sale Rate:</strong> {{ $data->plot_rate }}</p>
                                        <!-- Add more customer details here -->
                                    </div>
                                    <div class="col-md-6">
                                        <!-- Display customer details here -->
                                        <p><strong>CNIC:</strong> {{ $data->customer->nic_number }}</p>
                                        <p><strong>Mobile:</strong> {{ $data->customer->mobile_number }}</p>
                                        <p><strong>Address:</strong> {{ $data->customer->office_address }}</p>
                                        <p><strong>Plot Size:</strong> 
                                            <input type="text" id="plot_size_update" name="plot_size_update" class="form-control d-inline-block w-auto" value="{{ $data->plot_size }}" readonly ondblclick="this.readOnly = false;" 
                                            style="width: 80px !important;" maxlength="5" placeholder="Enter New Plot size" onfocus="this.style.borderColor = '#007bff';" onblur="this.style.borderColor = '';" >

                                        </p>
                                        <p><strong>Total Amount:</strong> {{ $data->total_price }}</p>
                                        <!-- Add more customer details here -->
                                    </div>

                                    

                                </div>
                                
                                
                                <dive >
                                    @csrf
                                    <input type="text" name='booking_id' value="{{ $data->id }}" hidden required>
                                    <input type="text" name='old_price_total' value="{{ $data->total_price }}" hidden id="old_price_total">
                                    <input type="text" name='plot_size' value="{{ $data->plot_size }}" hidden id="plot_size">
                                    <div class="row">
                                        <div class="col-md-12">

                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="new_rate">New Rate</label>
                                                <input type="number" class="form-control" id="new_rate" name="new_rate" placeholder="Enter New Rate Per Marla" required>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="discount_price">Discount</label>
                                                <input type="number" class="form-control" id="discount_price" name="discount_price" placeholder="Discount in plot" required>
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="total_new_value">Total Amount</label>
                                                <input type="number" class="form-control" id="total_new_value" name="total_new_value" placeholder="Total Price After Discount" required>
                                            </div>
                                        </div>
                                        
                                        

                                    </div>
                                    <hr>
                                    
                                    <button type="submit" class="btn btn-danger" id="submit_data" >Update</button>
                                </dive>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </section>
        

        <!-- Confirmation Modal -->
        <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="confirmModalLabel">Confirm Action</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-center">Are you sure you want to update the payment details?</p>
                    </div>
                    <div class="modal-footer d-flex justify-content-center">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" id="confirmSubmit">Yes, Update</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- AJAX Response Modal -->
        <div class="modal fade" id="ajaxResponseModal" tabindex="-1" aria-labelledby="ajaxResponseModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="ajaxResponseModalLabel">Response</h5>
                        <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Response content will be dynamically inserted here -->
                        <p class="text-center">Processing your request...</p>
                    </div>
                    <div class="modal-footer d-flex justify-content-center">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notification Modal -->
        <div class="modal fade" id="notificationModal" tabindex="-1" aria-labelledby="notificationModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="notificationModalLabel">Notification</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p id="notificationMessage" class="text-center">Processing...</p>
                    </div>
                    <div class="modal-footer d-flex justify-content-center">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>


        
    </div><!-- /.content-wrapper -->
@endsection

@section('js')

    <script>
        $(document).ready(function () {
            function calculateTotal() {
                let plotSize = parseFloat($('#plot_size').val()) || 0;
                let newRate = parseFloat($('#new_rate').val()) || 0;
                let discountPrice = parseFloat($('#discount_price').val()) || 0;

                let totalNewValue = (plotSize * newRate) - discountPrice;
                $('#total_new_value').val(totalNewValue > 0 ? totalNewValue : 0);
            }

            // Set #total_new_value to readonly
            $('#total_new_value').prop('readonly', true);

            $('#new_rate, #discount_price').on('input', calculateTotal);

            // Trigger the modal on submit button click
            $('#submit_data').on('click', function (event) {
                event.preventDefault(); // Prevent default form submission

                // Update modal with new details
                let plotNumber = "{{ $data->plot->name }}";
                let newRate = $('#new_rate').val();
                let discountPrice = $('#discount_price').val();
                let totalNewValue = $('#total_new_value').val();

                let confirmationDetails = `
                    <p><strong>Plot Number:</strong> ${plotNumber}</p>
                    <p><strong>New Rate:</strong> ${newRate}</p>
                    <p><strong>Discount:</strong> ${discountPrice}</p>
                    <p><strong>Total Amount:</strong> ${totalNewValue}</p>
                `;
                $('#confirmModal .modal-body').html(`
                    <p class="text-center alert alert-success">Are you sure you want to update the following details?</p>
                    ${confirmationDetails}
                `);

                $('#confirmModal').modal('show'); // Show the confirmation modal
            });

            // Handle confirmation button click
            $('#confirmSubmit').on('click', function () {
                $('#confirmModal').modal('hide'); // Close the confirmation modal

                let formData = {
                    _token: $('input[name="_token"]').val(),
                    booking_id: $('input[name="booking_id"]').val(),
                    old_price_total: $('input[name="old_price_total"]').val(),
                    plot_size: $('input[name="plot_size"]').val(),
                    new_rate: $('#new_rate').val(),
                    discount_price: $('#discount_price').val(),
                    total_new_value: $('#total_new_value').val(),
                };

                // AJAX request
                $.ajax({
                    url: "{{ route('payment_price_update.store') }}",
                    method: "POST",
                    data: formData,
                    beforeSend: function () {
                        $('#ajaxResponseModal .modal-body').html('<p class="text-center">Processing...</p>');
                        $('#ajaxResponseModal').modal('show');
                    },
                    success: function (response) {
                        if (response.status === "success") {
                            let successMessage = `
                                <div class="alert alert-success" role="alert">
                                    ${response.message}
                                </div>
                                <p><strong>Plot Number:</strong> {{ $data->plot->name }}  </p>
                                <p><strong>Updated Rate:</strong> ${response.data.plot_rate}</p>
                                <p><strong>Discount:</strong> ${response.data.dicount_value}</p>
                                <p><strong>Total Amount:</strong> ${response.data.total_price}</p>
                            `;
                            $('#ajaxResponseModal .modal-body').html(successMessage);

                            // Redirect after 5 seconds
                            setTimeout(function () {
                                window.location.href = "{{ route('booking.plot.index') }}";
                            }, 5000);
                        }
                    },
                    error: function (xhr) {
                        let errors = xhr.responseJSON?.message || "An unexpected error occurred.";
                        let errorMessage = `
                            <div class="alert alert-danger" role="alert">
                                ${errors}
                            </div>
                        `;
                        $('#ajaxResponseModal .modal-body').html(errorMessage);
                    },
                });
            });

            $('#save_plot_number').on('click', function () {
            // Get the value from the input box
            const plotNumber = $('#plot_number').val();
            const plot_id = $('#plot_id').val();
            const plot_size_update = $('#plot_size_update').val();

            // Perform an AJAX request to send the data to the Laravel application
            $.ajax({
                url: "{{ route('update_plot_number.store') }}", // Laravel route to handle the request
                type: 'POST',
                data: {
                    plot_id: plot_id,
                    plot_number: plotNumber,
                    plot_size_update: plot_size_update,
                    _token: '{{ csrf_token() }}' // Include CSRF token for security
                },
                beforeSend: function () {
                    // Show the notification modal with a "Processing" message
                    $('#notificationMessage').text('Processing your request...');
                    $('#notificationModal').modal('show');
                },
                success: function (response) {
                    // Update the notification modal with a success message
                    $('#notificationMessage').html(`
                        <div class="alert alert-success" role="alert">
                            Plot number and size updated successfully!
                        </div>
                    `);

                    // Optional: Automatically close the modal after a delay
                    setTimeout(function () {
                        $('#notificationModal').modal('hide');
                    }, 3000);
                },
                error: function (xhr, status, error) {
                    // Update the notification modal with an error message
                    $('#notificationMessage').html(`
                        <div class="alert alert-danger" role="alert">
                            Failed to update the plot number and size. Please try again.
                        </div>
                    `);
                }
            });
        });

        });



    </script>

@endsection


