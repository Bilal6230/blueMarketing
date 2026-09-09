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
        $pricingAllowed = (bool) ($pricingLifecycle['pricing_edit_allowed'] ?? false);
        $canUpdateBroker = auth()->user()?->can('update plot') ?? false;
        $canUpdatePricing = auth()->user()?->can('update booking price') ?? false;
        $pricingEnabled = $canUpdatePricing && $pricingAllowed;
        $blockMessages = [
            'SCHEDULE_EXISTS' => 'Pricing cannot be changed because a payment schedule already exists.',
            'PAYMENT_ACTIVITY_EXISTS' => 'Pricing cannot be changed because payment or recovery activity exists.',
            'TRANSFER_HISTORY_EXISTS' => 'Pricing cannot be changed after File Transfer.',
            'RESALE_HISTORY_EXISTS' => 'Pricing cannot be changed because resale history exists.',
            'VOUCHER_NOT_PENDING' => 'Pricing cannot be changed because the original Sales Voucher is no longer pending.',
            'BOOKING_NOT_ACTIVE' => 'Pricing cannot be changed because this booking is not active.',
        ];
    @endphp

    <div class="content-wrapper booking-edit-page">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2 align-items-center">
                    <div class="col-sm-7">
                        <h1 class="m-0">Edit Booking #{{ $booking->id }}</h1>
                        <p class="text-muted mb-0">Review the recorded booking, broker assignment, and pricing eligibility.</p>
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
                    <div>Customer, property, booking date, and status remain locked. Broker and eligible pricing changes use separate actions.</div>
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
                                    <select class="form-control @error('broker_id') is-invalid @enderror" name="broker_id" @disabled(!$canUpdateBroker)>
                                        <option value="">No Broker</option>
                                        @foreach ($brokers as $broker)
                                            <option value="{{ $broker->id }}" @selected((string) old('broker_id', $booking->broker_id) === (string) $broker->id)>{{ $broker->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="field-note">Broker changes are saved independently from pricing.</div>
                                </div>
                                @if ($canUpdateBroker)<div class="col-12 mt-3"><button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save Broker Change</button></div>@endif
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
                </div>
                </form>

                <div id="pricing" class="card section-card mt-4">
                    <div class="card-header"><h3 class="card-title">Pricing &amp; Charges</h3></div>
                    <div class="card-body">
                        @if (!$canUpdatePricing)
                            <div class="alert alert-secondary">You do not have permission to update booking pricing.</div>
                        @elseif (!$pricingAllowed)
                            <div class="alert alert-warning">
                                @foreach (($pricingLifecycle['pricing_block_reasons'] ?? []) as $reason)
                                    <div>{{ $blockMessages[$reason] ?? 'Pricing cannot be changed because the original booking accounting structure requires review.' }}</div>
                                @endforeach
                            </div>
                        @elseif (!($pricingLifecycle['amounts_consistent'] ?? true))
                            <div class="alert alert-warning">The saved booking amount and original sales accounting are currently different. Saving this pricing record will synchronize the original pending sales accounting to the authoritative booking amount.</div>
                        @endif

                        <form method="POST" action="{{ route('booking.pricing.update', ['id' => $booking->id]) }}">
                            @csrf
                            @method('PUT')
                            @foreach (['plot_rate', 'is_park', 'park_facing', 'is_corner', 'carner_price', 'dicount_value', 'total_price'] as $field)
                                <input type="hidden" name="expected_{{ $field }}" value="{{ $booking->{$field} }}">
                            @endforeach
                            <div class="row">
                                <div class="form-group col-md-4"><label>Plot Rate</label><input name="plot_rate" class="form-control" value="{{ old('plot_rate', $booking->plot_rate) }}" @disabled(!$pricingEnabled)></div>
                                <div class="form-group col-md-4"><label>Park Facing</label><select id="pricing_is_park" name="is_park" class="form-control" @disabled(!$pricingEnabled)><option value="0" @selected((int) old('is_park', $booking->is_park) === 0)>No</option><option value="1" @selected((int) old('is_park', $booking->is_park) === 1)>Yes</option></select></div>
                                <div class="form-group col-md-4"><label>Park Charge</label><input id="pricing_park_charge" name="park_facing" class="form-control" value="{{ old('park_facing', $booking->park_facing) }}" @disabled(!$pricingEnabled)></div>
                                <div class="form-group col-md-4"><label>Corner Plot</label><select id="pricing_is_corner" name="is_corner" class="form-control" @disabled(!$pricingEnabled)><option value="0" @selected((int) old('is_corner', $booking->is_corner) === 0)>No</option><option value="1" @selected((int) old('is_corner', $booking->is_corner) === 1)>Yes</option></select></div>
                                <div class="form-group col-md-4"><label>Corner Charge</label><input id="pricing_corner_charge" name="carner_price" class="form-control" value="{{ old('carner_price', $booking->carner_price) }}" @disabled(!$pricingEnabled)></div>
                                <div class="form-group col-md-4"><label>Discount</label><input name="dicount_value" class="form-control" value="{{ old('dicount_value', $booking->dicount_value) }}" @disabled(!$pricingEnabled)></div>
                                <div class="form-group col-md-8"><label>Reason for pricing change</label><textarea name="reason" maxlength="500" class="form-control" placeholder="Client-approved discount correction, rate correction, or park/corner charge correction" @disabled(!$pricingEnabled)>{{ old('reason') }}</textarea></div>
                                <div class="form-group col-md-4"><label>Server-calculated total</label><input class="form-control" value="Rs. {{ $money($booking->total_price) }}" disabled><div class="field-note">The server calculator is authoritative.</div></div>
                            </div>
                            @if ($pricingEnabled)<button type="submit" class="btn btn-danger"><i class="fas fa-calculator mr-1"></i> Save Pricing Change</button>@endif
                        </form>
                    </div>
                </div>
                @if ($pricingEnabled)
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            function bindCharge(toggleId, chargeId) {
                                const toggle = document.getElementById(toggleId);
                                const charge = document.getElementById(chargeId);
                                const sync = function () {
                                    const enabled = toggle.value === '1';
                                    charge.readOnly = !enabled;
                                    charge.classList.toggle('bg-light', !enabled);
                                    if (!enabled) charge.value = '0';
                                };
                                toggle.addEventListener('change', sync);
                                sync();
                            }
                            bindCharge('pricing_is_park', 'pricing_park_charge');
                            bindCharge('pricing_is_corner', 'pricing_corner_charge');
                        });
                    </script>
                @endif
            </div>
        </section>
    </div>
@endsection
