@extends('admin.layouts.master')

@push('style')
    <style>
        .booking-edit-page .section-card { border-top: 3px solid #3c8dbc; }
        .booking-edit-page .section-card .card-title { font-weight: 600; }
        .booking-edit-page .form-control:disabled {
            background-color: #f4f6f9;
            color: #495057;
            opacity: 1;
        }
        .booking-edit-page .field-note { color: #6c757d; font-size: .8rem; margin-top: .3rem; }
        .booking-edit-page .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding: .55rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        .booking-edit-page .summary-row:last-child { border-bottom: 0; }
        .booking-edit-page .recorded-total {
            background: #eaf4ff;
            border-radius: .35rem;
            margin: .75rem -.75rem -.75rem;
            padding: 1rem .75rem;
        }
        .booking-edit-page .amount { font-variant-numeric: tabular-nums; white-space: nowrap; }
        @media (min-width: 992px) { .booking-edit-page .summary-sticky { position: sticky; top: 1rem; } }
    </style>
@endpush

@section('content')
    @php
        $money = static fn ($value) => number_format((float) $value, 2);
        $normalizedSize = str_replace(',', '', trim((string) $booking->plot_size));
        $hasNumericSize = $normalizedSize !== '' && is_numeric($normalizedSize);
        $baseAmount = $hasNumericSize ? (float) $normalizedSize * (float) $booking->plot_rate : null;
        $calculatedTotal = $baseAmount === null ? null : $baseAmount
            + (float) $booking->park_facing
            + (float) $booking->carner_price
            - (float) $booking->dicount_value;
        $recordedTotal = (float) $booking->total_price;
        $totalDiffers = $calculatedTotal !== null && abs($calculatedTotal - $recordedTotal) >= 0.01;
        $customerName = trim(($booking->customer->first_name ?? '') . ' ' . ($booking->customer->last_name ?? ''));
    @endphp

    <div class="content-wrapper booking-edit-page">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2 align-items-center">
                    <div class="col-sm-7">
                        <h1 class="m-0">Edit Booking #{{ $booking->id }}</h1>
                        <p class="text-muted mb-0">Review the recorded booking and update its broker assignment.</p>
                    </div>
                    <div class="col-sm-5 mt-2 mt-sm-0">
                        <ol class="breadcrumb float-sm-right mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('booking.plot.index') }}">Bookings</a></li>
                            <li class="breadcrumb-item active">#{{ $booking->id }}</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content pb-4">
            <div class="container-fluid">
                <div class="alert alert-info d-flex align-items-start" role="status">
                    <i class="fas fa-info-circle mt-1 mr-2"></i>
                    <div><strong>Broker-only editing.</strong> Customer, property, pricing, and status details remain locked.</div>
                </div>

                @if (session('success'))
                    <div class="alert alert-success" role="alert">{{ session('success') }}</div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif

                <form method="POST" action="{{ route('booking.update', ['id' => $booking->id]) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="expected_updated_at" value="{{ $booking->updated_at?->format('Y-m-d H:i:s.u') }}">
                    <input type="hidden" name="expected_broker_id" value="{{ $booking->broker_id }}">
                    @foreach (['project_id', 'customer_id', 'plot_id', 'plot_type', 'plot_size', 'status', 'plot_rate', 'is_park', 'park_facing', 'is_corner', 'carner_price', 'dicount_value', 'total_price'] as $lockedField)
                        <input type="hidden" name="{{ $lockedField }}" value="{{ $booking->{$lockedField} }}">
                    @endforeach
                    <input type="hidden" name="booking_date" value="{{ \Carbon\Carbon::parse($booking->booking_date)->format('Y-m-d H:i:s') }}">

                <div class="row">
                    <div class="col-lg-8">
                        <div class="card section-card">
                            <div class="card-header"><h3 class="card-title">Booking Information</h3></div>
                            <div class="card-body"><div class="row">
                                <div class="form-group col-md-4">
                                    <label>Booking ID</label>
                                    <input class="form-control" value="#{{ $booking->id }}" disabled>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Booking Date</label>
                                    <input class="form-control" value="{{ \Carbon\Carbon::parse($booking->booking_date)->format('d M Y') }}" disabled>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Status</label>
                                    <select class="form-control" disabled>
                                        <option selected>{{ ucfirst((string) $booking->status) }}</option>
                                    </select>
                                    <div class="field-note">Current stored status is shown exactly as recorded.</div>
                                </div>
                                <div class="form-group col-md-6 mb-md-0">
                                    <label>Broker</label>
                                    <select class="form-control @error('broker_id') is-invalid @enderror" name="broker_id">
                                        <option value="">No Broker</option>
                                        @foreach ($brokers as $broker)
                                            <option value="{{ $broker->id }}" @selected((string) old('broker_id', $booking->broker_id) === (string) $broker->id)>{{ $broker->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="field-note">This is the only editable Booking field in this phase.</div>
                                </div>
                            </div></div>
                        </div>

                        <div class="card section-card">
                            <div class="card-header"><h3 class="card-title">Customer &amp; Ownership</h3></div>
                            <div class="card-body"><div class="row">
                                <div class="form-group col-md-6 mb-md-0">
                                    <label>Project</label>
                                    <input class="form-control" value="{{ $booking->project->project ?? 'Unavailable' }}" disabled>
                                </div>
                                <div class="form-group col-md-6 mb-0">
                                    <label>Customer</label>
                                    <input class="form-control" value="{{ $customerName !== '' ? $customerName : 'Unavailable' }}" disabled>
                                    <div class="field-note"><i class="fas fa-exchange-alt mr-1"></i>Customer changes are handled through File Transfer.</div>
                                </div>
                            </div></div>
                        </div>

                        <div class="card section-card">
                            <div class="card-header"><h3 class="card-title">Property Details</h3></div>
                            <div class="card-body"><div class="row">
                                <div class="form-group col-md-4">
                                    <label>Plot Type</label>
                                    <input class="form-control" value="{{ (int) $booking->plot_type === 1 ? 'Residential' : ((int) $booking->plot_type === 2 ? 'Commercial' : 'Unknown (' . $booking->plot_type . ')') }}" disabled>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Plot</label>
                                    <input class="form-control" value="{{ $booking->plot->name ?? 'Unavailable' }}" disabled>
                                </div>
                                <div class="form-group col-md-4 mb-md-0">
                                    <label>Plot Size</label>
                                    <input class="form-control" value="{{ $booking->plot_size }}{{ !empty($booking->plot->unit) ? ' ' . $booking->plot->unit : '' }}" disabled>
                                </div>
                            </div></div>
                        </div>

                        <div class="card section-card">
                            <div class="card-header"><h3 class="card-title">Pricing Details</h3></div>
                            <div class="card-body"><div class="row">
                                <div class="form-group col-md-6">
                                    <label>Plot Rate</label>
                                    <div class="input-group"><div class="input-group-prepend"><span class="input-group-text">Rs.</span></div><input class="form-control amount" value="{{ $money($booking->plot_rate) }}" disabled></div>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Discount</label>
                                    <div class="input-group"><div class="input-group-prepend"><span class="input-group-text">Rs.</span></div><input class="form-control amount" value="{{ $money($booking->dicount_value) }}" disabled></div>
                                </div>
                            </div></div>
                        </div>

                        <div class="card section-card mb-lg-0">
                            <div class="card-header"><h3 class="card-title">Additional Charges</h3></div>
                            <div class="card-body"><div class="row">
                                <div class="form-group col-sm-6 col-md-3">
                                    <label>Park Facing</label>
                                    <input class="form-control" value="{{ (int) $booking->is_park === 1 ? 'Yes' : 'No' }}" disabled>
                                </div>
                                <div class="form-group col-sm-6 col-md-3">
                                    <label>Park Charge</label>
                                    <input class="form-control amount" value="Rs. {{ $money($booking->park_facing) }}" disabled>
                                </div>
                                <div class="form-group col-sm-6 col-md-3">
                                    <label>Corner Plot</label>
                                    <input class="form-control" value="{{ (int) $booking->is_corner === 1 ? 'Yes' : 'No' }}" disabled>
                                </div>
                                <div class="form-group col-sm-6 col-md-3 mb-0">
                                    <label>Corner Charge</label>
                                    <input class="form-control amount" value="Rs. {{ $money($booking->carner_price) }}" disabled>
                                </div>
                            </div></div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card card-primary card-outline summary-sticky">
                            <div class="card-header"><h3 class="card-title"><i class="fas fa-calculator mr-2"></i>Current Financial Summary</h3></div>
                            <div class="card-body">
                                <div class="summary-row"><span>Base calculated value</span><strong class="amount">{{ $baseAmount === null ? 'Not available' : 'Rs. ' . $money($baseAmount) }}</strong></div>
                                <div class="summary-row"><span>Park charge</span><strong class="amount">Rs. {{ $money($booking->park_facing) }}</strong></div>
                                <div class="summary-row"><span>Corner charge</span><strong class="amount">Rs. {{ $money($booking->carner_price) }}</strong></div>
                                <div class="summary-row"><span>Discount</span><strong class="amount text-danger">- Rs. {{ $money($booking->dicount_value) }}</strong></div>
                                @if ($calculatedTotal !== null)
                                    <div class="summary-row"><span>Calculated from components</span><strong class="amount">Rs. {{ $money($calculatedTotal) }}</strong></div>
                                @endif
                                <div class="recorded-total">
                                    <div class="text-muted small text-uppercase font-weight-bold">Current recorded sale amount</div>
                                    <div class="h3 mb-0 text-primary amount">Rs. {{ $money($booking->total_price) }}</div>
                                </div>
                                @if ($totalDiffers)
                                    <div class="alert alert-warning mt-3 mb-0 py-2" role="alert">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>Recorded total differs from calculated components.
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mt-4">
                    <a href="{{ route('booking.plot.index') }}" class="btn btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back to Bookings</a>
                    <button type="submit" class="btn btn-primary mt-2 mt-sm-0"><i class="fas fa-save mr-1"></i> Save Broker Change</button>
                </div>
                </form>
            </div>
        </section>
    </div>
@endsection
