@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h4>Plot Booking History - {{ $plot->name }}</h4>
        </div>
        <div class="card-body">
            @if(count($plotHistory) > 0)
                <table class="table table-striped">
                    <thead class="thead-dark">
                        <tr>
                            <th>Booking ID</th>
                            <th>Customer</th>
                            <th>Plot Size</th>
                            <th>Rate</th>
                            <th>Total Price</th>
                            <th>Broker</th>
                            <th>Booking Date</th>
                            <th>Deleted At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($plotHistory as $history)
                            <tr>
                                <td>{{ $history['booking_id'] }}</td>
                                <td>{{ $history['name'] }} ({{ $history['cnic'] }})</td>
                                <td>{{ $history['plot_size'] }}</td>
                                <td>{{ number_format($history['plot_rate']) }}</td>
                                <td>{{ number_format($history['total_price']) }}</td>
                                <td>{{ $history['broker_id'] ?? 'N/A' }}</td>
                                <td>{{ \Carbon\Carbon::parse($history['booking_date'])->format('d M Y') }}</td>
                                <td>{{ \Carbon\Carbon::parse($history['deleted_at'])->format('d M Y, h:i A') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-center">No booking history available for this plot.</p>
            @endif
        </div>
    </div>
</div>
@endsection
