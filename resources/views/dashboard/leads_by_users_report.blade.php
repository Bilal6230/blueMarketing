<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leads By Users Report</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Daterangepicker CSS -->
    <link href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" rel="stylesheet">

    <style>
        @media print {
            .no-print {
                display: none;
            }
            .page-break {
                page-break-before: always;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Leads By Users Report</h1>
            <a href="{{ route('admin') }}" class="btn btn-secondary no-print">Back to Dashboard</a>
            <button class="btn btn-primary no-print" onclick="window.print()">Print Report</button>
        </div>

        <!-- Filters Section -->
        <div class="card mb-4 shadow-sm filter-card">
            <div class="card-body">
                <div class="row">
                    <!-- Total Leads Section -->
                    <div class="col-md-12 mb-3">
                        <h5>Total Leads Across All Users:</h5>
                        <span class="badge bg-primary fs-4">{{ $leadsByUsers->sum('total_leads') }}</span>
                    </div>

                    <!-- Date Range Display -->
                    <div class="col-md-12 mb-3">
                        <strong>Selected Date Range: </strong>
                        <span>{{ $fromDate->format('d-m-Y') }} to {{ $toDate->format('d-m-Y') }}</span>
                    </div>

                    <!-- Date Range Picker Form -->
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
                </div>
            </div>
        </div>

        <!-- Custom CSS for Animation, Shadow, and Borderline Animation -->
        <style>
            .filter-card {
                background: #f8f9fa; /* Light background color */
                border-radius: 10px;  /* Rounded corners */
                transition: all 0.3s ease; /* Smooth transition for hover effect */
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); /* Initial shadow */
                position: relative; /* Needed for the animated border */
            }

            /* Hover effect - shadow and lifting */
            .filter-card:hover {
                box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2); /* Larger shadow on hover */
                transform: translateY(-5px); /* Slightly lift the card */
            }

            /* Borderline animation */
            .filter-card::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                border: 2px solid transparent; /* Invisible border initially */
                border-radius: 10px; /* Match rounded corners */
                transition: border-color 0.3s ease-in-out;
                pointer-events: none; /* Ensures the border does not interfere with interactions */
            }

            .filter-card:hover::before {
                animation: borderAnimation 1s forwards; /* Trigger the animation on hover */
            }

            @keyframes borderAnimation {
                0% {
                    border-top: 2px solid red;
                    border-right: 2px solid transparent;
                    border-bottom: 2px solid transparent;
                    border-left: 2px solid transparent;
                }
                25% {
                    border-top: 2px solid red;
                    border-right: 2px solid red;
                    border-bottom: 2px solid transparent;
                    border-left: 2px solid transparent;
                }
                50% {
                    border-top: 2px solid red;
                    border-right: 2px solid red;
                    border-bottom: 2px solid red;
                    border-left: 2px solid transparent;
                }
                75% {
                    border-top: 2px solid red;
                    border-right: 2px solid red;
                    border-bottom: 2px solid red;
                    border-left: 2px solid red;
                }
                100% {
                    border-top: 2px solid red;
                    border-right: 2px solid red;
                    border-bottom: 2px solid red;
                    border-left: 2px solid red;
                }
            }

            .filter-card h5, .filter-card strong {
                color: #495057; /* Dark text for readability */
            }

            .filter-card .badge {
                background-color: #007bff; /* Custom color for badge */
            }

            .filter-card form button {
                transition: background-color 0.3s ease;
            }

            .filter-card form button:hover {
                background-color: #28a745; /* Green color on hover for button */
            }

            /* For responsive layout */
            @media (max-width: 767px) {
                .filter-card {
                    margin-bottom: 20px; /* Space between cards on small screens */
                }
            }
        </style>




        <!-- Leads Table Section -->
        @forelse ($leadsByUsers as $group)
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4>User: {{ $group['user_name'] ?? 'Unassigned' }} (ID: {{ $group['user_id'] }})</h4>
                    <p>Total Leads: {{ $group['total_leads'] }}</p>
                </div>
                <div class="card-body">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Lead ID</th>
                                <th>Full Name</th>
                                <th>Phone Number</th>
                                <th>Project Name</th>
                                <th>Created At</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($group['leads'] as $index => $lead)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $lead->id }}</td>
                                    <td>{{ $lead->first_name }} {{ $lead->last_name }}</td>
                                    <td>{{ $lead->phone_number }}</td>
                                    <td>{{ $lead->project_name }}</td>
                                    <td>{{ $lead->created_at->format('d-m-Y') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center">No leads found for this user.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if (!$loop->last)
                <div class="page-break"></div>
            @endif
        @empty
            <p class="text-center">No leads found for any users.</p>
        @endforelse
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Moment.js -->
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>

    <!-- Daterangepicker JS -->
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize Daterangepicker with default options
            $('#date_range').daterangepicker({
                autoUpdateInput: true,   // Automatically update input field
                locale: {
                    format: 'YYYY-MM-DD',  // Date format for input
                },
                // Use default date ranges
                ranges: {
                    'Today': [moment(), moment()],
                    'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')],
                    'Last Month': [moment().subtract(1, 'months').startOf('month'), moment().subtract(1, 'months').endOf('month')],
                },
                startDate: "{{ request('from_date', now()->startOfMonth()->format('YYYY-MM-DD')) }}",
                endDate: "{{ request('to_date', now()->format('YYYY-MM-DD')) }}",
            });

            // Automatically update hidden inputs when the range is applied
            $('#date_range').on('apply.daterangepicker', function (ev, picker) {
                $(this).val(picker.startDate.format('YYYY-MM-DD') + ' to ' + picker.endDate.format('YYYY-MM-DD'));
            });
        });
    </script>
</body>
</html>
