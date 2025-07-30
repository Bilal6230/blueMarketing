<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Today's Lead Work Report</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- Daterangepicker CSS -->
    <link href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" rel="stylesheet">
    

</head>
<style>
    .card:hover {
        transform: translateY(-5px); /* Slight lift effect */
        transition: transform 0.3s ease, box-shadow 0.3s ease; /* Smooth transition */
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); /* Enhanced shadow */
    }

    .card-body .btn {
        transition: background-color 0.2s ease;
    }

    .card-body .btn:hover {
        background-color: #0056b3; /* Darker blue for the button on hover */
    }

    #date_range {
        border: 2px solid #007bff; /* Add a blue border */
        border-radius: 5px; /* Rounded corners */
        padding: 10px; /* Add padding */
        font-size: 16px; /* Increase font size */
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); /* Add a subtle shadow */
        transition: box-shadow 0.3s ease, border-color 0.3s ease; /* Smooth transitions */
    }

    #date_range:focus {
        border-color: #0056b3; /* Darker blue on focus */
        box-shadow: 0 6px 8px rgba(0, 0, 0, 0.2); /* Enhanced shadow on focus */
        outline: none; /* Remove default outline */
    }

    .daterangepicker {
        border: 2px solid #007bff !important; /* Customize picker border */
        border-radius: 10px !important; /* Rounded corners for picker */
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2) !important; /* Subtle shadow for picker */
    }

    .daterangepicker .calendar-table th, .daterangepicker .calendar-table td {
        font-size: 14px; /* Adjust font size in calendar */
    }

    .daterangepicker .applyBtn, .daterangepicker .cancelBtn {
        border-radius: 5px !important; /* Rounded buttons */
        font-size: 14px; /* Adjust button font size */
    }
</style>
<body>
<div class="container mt-5">
    <h1 class="text-center mb-4">Today's Lead Work Report</h1>

    <div class="d-flex justify-content-between mb-4">
        <a href="{{ route('admin') }}" class="btn btn-primary">Go to Admin Page</a>
    
        @if(request()->has('user_id'))
            <a href="{{ url()->current() }}" class="btn btn-danger">Reset Filter</a>
        @endif
    </div>

    <!-- Display all users and their work counts -->
    <div class="mb-4">
        <h3 class="mb-4 text-center">All Users</h3>
        <div class="row">
            @foreach($allUsers as $userId => $user)
                <div class="col-md-4 mb-3">
                    <div class="card shadow-sm hover-shadow-lg border-light rounded">
                        <div class="card-body text-center">
                            <h5 class="card-title mb-2 text-success">
                                <i class="fas fa-user-circle"></i> {{ $user->name }}
                            </h5>
                            <p class="card-text text-muted">Total Work Count: {{ $user->works_count }}</p>
                            <a href="{{ url()->current() . '?user_id=' . $user->id }}" class="btn btn-success btn-sm mt-2">View Work</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="col-md-12">
        <form method="GET" class="row align-items-center">
            <div class="col-md-8 mb-3">
                <label for="date_range" class="form-label">Select Date Range</label>
                <input type="text" id="date_range" name="date_range" class="form-control" value="{{ request('date_range') }}">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-success w-100">Reload Report</button>
            </div>
        </form>
    </div>

    

    @if($todayWorkReport->isEmpty())
        <div class="alert alert-info text-center">
            No work records found for today.
        </div>
    @else
        @foreach($todayWorkReport as $userId => $userWork)
            <div class="card mb-4">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h4>
                        <a href="{{ url()->current() . '?user_id=' . $userId }}" class="text-white text-decoration-none">
                            User Name: {{ $userWork->first()->first()->user->name ?? 'N/A' }} 
                        </a>
                    </h4>
                    <h5>
                        <a href="{{ url()->current() . '?user_id=' . $userId }}" class="text-white text-decoration-none">
                            User ID: {{ $userId }}
                        </a>
                    </h5>
                </div>
                <table class="table table-bordered">
                    <thead class="table-success">
                        <tr>
                            <th style="width: 20%;">Name</th>
                            <th style="width: 5%;">Mobile</th>
                            <th style="width: 35%;">Comment</th>
                            <th style="width: 10%;">Recall</th>
                            <th style="width: 15%;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($userWork as $leadId => $workEntries)
                            @php
                                $lead = $workEntries->first()->leads;
                            @endphp
                            @foreach($workEntries as $work)
                                <tr>
                                    <td>
                                        <a href="/admin/crm/lead/work?id={{ $lead->id }}&status=1&user={{ $userId }}" class="text-decoration-none text-success fw-bold transition-colors hover:text-success">
                                            {{ $lead->first_name ?? 'N/A' }} {{ $lead->last_name ?? '' }}
                                        </a>
                                    </td>
                                    <td>{{ $lead->phone_number ?? 'N/A' }}</td>
                                    
                                    <td>{{ $work->comment }}</td>
                                    <td>{{ Setting::timeRemaining($work->follow_up) }} </td>
                                    <td
                                        @if($work->call_status == 2)
                                            class="bg-primary text-white" 
                                        @elseif($work->call_status == 3)
                                            class="bg-danger text-white"  
                                        @elseif($work->call_status == 7)
                                            class="bg-info text-white"  
                                        @elseif($work->call_status == 4)
                                            class="bg-secondary text-white"  
                                        @endif
                                    >
                                        {{ Setting::getCallStatus($work->call_status) }} 
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>

                
            </div>
        @endforeach
    @endif
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

 <!-- jQuery -->
 <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

 <!-- Moment.js -->
 <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>

<!-- Daterangepicker JS -->
<script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>

<!-- Initialize Daterangepicker -->
<script>
    $(document).ready(function () {
        $('#date_range').daterangepicker({
            autoUpdateInput: true, // Automatically update input field
            locale: {
                format: 'YYYY-MM-DD', // Date format for input
                applyLabel: 'Apply', // Apply button label
                cancelLabel: 'Cancel', // Cancel button label
                customRangeLabel: 'Custom Range', // Custom range label
            },
            ranges: {
                'Today': [moment(), moment()],
                'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                'This Month': [moment().startOf('month'), moment().endOf('month')],
                'Last Month': [moment().subtract(1, 'months').startOf('month'), moment().subtract(1, 'months').endOf('month')],
            },
            // Set the maximum date to today (disable future dates)
            maxDate: moment(),
            startDate: "{{ request('from_date', now()->startOfMonth()->format('YYYY-MM-DD')) }}",
            endDate: "{{ request('to_date', now()->format('YYYY-MM-DD')) }}"
        });

        // Automatically update hidden inputs when the range is applied
        $('#date_range').on('apply.daterangepicker', function (ev, picker) {
            $(this).val(picker.startDate.format('YYYY-MM-DD') + ' to ' + picker.endDate.format('YYYY-MM-DD'));
        });

        // Clear input when the Cancel button is clicked
        $('#date_range').on('cancel.daterangepicker', function (ev, picker) {
            $(this).val('');
        });
    });
</script>

</body>
</html>
