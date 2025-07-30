@extends('admin.layouts.master')

@section('content')
<style>
    /* Custom Styles */
    .no-records-card {
        background-color: #f8d7da;
        border-radius: 10px;
        padding: 20px;
        text-align: center;
        margin-top: 20px;
    }
</style>
<div class="container mt-5">
    <div class="card shadow-lg border-0">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Check History Report</h4>
            <a href="{{ route('report.check') }}" class="btn btn-success btn-sm">Back to Reports</a>
        </div>
        <div class="card-body">
            <!-- Check Number Displayed at the Top -->
            <div class="row mb-4">
                <div class="col-12 text-center">
                    <h3><strong>Check Number:</strong> {{ $ledger->t_number }}</h3>
                </div>
            </div>

            <!-- Customer & Plot Information -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <h5><strong>Customer:</strong> {{ $ledger->customer_list->first_name }} {{ $ledger->customer_list->last_name }}</h5>
                    <h5><strong>Phone:</strong> {{ $ledger->customer_list->phone_number }}</h5>
                </div>
                <div class="col-md-6">
                    <h5><strong>Plot:</strong> {{ Setting::getPlotTypeShort($ledger->plot_list->type) }} - {{ $ledger->plot_list->name }}</h5>
                </div>
            </div>

            <!-- Check History Table or No Records Found Message -->
            <div class="table-responsive">
                @if(empty($checkHistory) || (is_array($checkHistory) && count($checkHistory) == 0) || ($checkHistory instanceof \Illuminate\Support\Collection && $checkHistory->isEmpty()))
                <div class="no-records-card">
                        <h5>No records found.</h5>
                    </div>
                @else
                    <table class="table table-striped table-bordered">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th>#</th>
                                <th>Passing Date</th>
                                <th>Status</th>
                                <th>Description Note</th>
                                <th>Bank Name</th>
                                <th>Account</th>
                                <th>Last Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($checkHistory as $index => $history)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ Setting::getShortDate($history['passing_date']) }}</td>
                                
                                <td>
                                    <span class="badge {{ collect(check_status())->firstWhere('id', $history['passing_status'])['badge'] }}" style="width: 80px">
                                        {{ collect(check_status())->firstWhere('id', $history['passing_status'])['name'] }}
                                    </span>
                                </td>
                                <td>{{ $history['description_note'] }}</td>
                                <td>{{ $history['bank_name'] }}</td>
                                
                                <!-- Fetch Head Account and Sub Account Names using Credit Account ID -->
                                <td class="d-flex align-items-center">
                                    <div class="d-flex flex-column">
                                        <span class="font-weight-bold">{{ getHeadAccountNameById($history['credit_account_id']) }}</span>
                                        <small class="text-muted">{{ getSubAccountNameById($history['credit_account_id']) }}</small>
                                    </div>
                                </td>
                                
                                
                                <td>{{ Setting::getShortDate($history['updated_at']) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <!-- Footer -->
        <div class="card-footer text-center bg-light">
            <button onclick="window.print()" class="btn btn-outline-primary">
                <i class="fas fa-print"></i> Print Report
            </button>
        </div>
    </div>
</div>
@endsection
