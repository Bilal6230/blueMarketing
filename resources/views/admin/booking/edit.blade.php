@extends('admin.layouts.master')

@section('content')
    @php
        $customerName = trim(($booking->customer->first_name ?? '') . ' ' . ($booking->customer->last_name ?? ''));
        $canUpdateBroker = auth()->user()?->can('update plot') ?? false;
        $canUpdatePricing = auth()->user()?->can('update booking price') ?? false;
        $pricingAllowed = (bool) ($pricingLifecycle['pricing_edit_allowed'] ?? false);
        $pricingEnabled = $canUpdatePricing && $pricingAllowed;
        try {
            $currentPricing = app(\App\Services\Booking\BookingPriceCalculator::class)->calculate(
                (string) $booking->plot_size,
                (string) $booking->plot_rate,
                $booking->is_park,
                (string) $booking->park_facing,
                $booking->is_corner,
                (string) $booking->carner_price,
                (string) $booking->dicount_value
            );
            $basePreview = $currentPricing->baseAmount;
        } catch (\Throwable) {
            $basePreview = '';
        }
        $blockMessages = [
            'SCHEDULE_EXISTS' => 'Payment schedule already exists.',
            'PAYMENT_ACTIVITY_EXISTS' => 'Payment activity exists.',
            'TRANSFER_HISTORY_EXISTS' => 'File Transfer already exists.',
            'RESALE_HISTORY_EXISTS' => 'Resale history exists.',
            'VOUCHER_NOT_PENDING' => 'The original Sales Voucher is not pending.',
            'BOOKING_NOT_ACTIVE' => 'Booking is inactive.',
        ];
    @endphp

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid"><div class="row mb-2">
                <div class="col-sm-6"><h1>{{ $title }}</h1></div>
                <div class="col-sm-6"><ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('booking.plot.index') }}">Bookings</a></li>
                    <li class="breadcrumb-item active">{{ $title }}</li>
                </ol></div>
            </div></div>
        </div>

        <section class="content"><div class="container-fluid">
            @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
            @if ($errors->any())
                <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <div class="row">
                <div class="col-md-7">
                    <form method="POST" action="{{ route('booking.update', ['id' => $booking->id]) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="expected_updated_at" value="{{ $booking->updated_at?->format('Y-m-d H:i:s.u') }}">
                        <input type="hidden" name="expected_broker_id" value="{{ $booking->broker_id }}">
                        @foreach (['project_id', 'customer_id', 'plot_id', 'plot_type', 'plot_size', 'status', 'plot_rate', 'is_park', 'park_facing', 'is_corner', 'carner_price', 'dicount_value', 'total_price'] as $lockedField)
                            <input type="hidden" name="{{ $lockedField }}" value="{{ $booking->{$lockedField} }}">
                        @endforeach
                        <input type="hidden" name="booking_date" value="{{ \Carbon\Carbon::parse($booking->booking_date)->format('Y-m-d H:i:s') }}">

                        <div class="card">
                            <div class="card-header"><h3 class="card-title">Booking Form</h3></div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4"><div class="form-group"><label>Booking ID</label><input class="form-control" value="{{ $booking->id }}" disabled></div></div>
                                    <div class="col-md-4"><div class="form-group"><label>Booking Date</label><input class="form-control" value="{{ \Carbon\Carbon::parse($booking->booking_date)->format('d-m-Y') }}" disabled></div></div>
                                    <div class="col-md-4"><div class="form-group"><label>Status</label><select class="form-control" disabled><option>{{ ucfirst((string) $booking->status) }}</option></select></div></div>
                                    <div class="col-md-6"><div class="form-group"><label>Project</label><select class="form-control select2" disabled><option>{{ $booking->project->project ?? 'Unavailable' }}</option></select></div></div>
                                    <div class="col-md-6"><div class="form-group">
                                        <label>Customer</label><select class="form-control select2" disabled><option>{{ $customerName !== '' ? $customerName : 'Unavailable' }}</option></select>
                                        <small class="form-text text-muted">Customer changes must be completed through File Transfer.</small>
                                    </div></div>
                                    <div class="col-md-4"><div class="form-group"><label>Plot Type</label><select class="form-control select2" disabled><option>{{ (int) $booking->plot_type === 1 ? 'Residential' : ((int) $booking->plot_type === 2 ? 'Commercial' : 'Unknown') }}</option></select></div></div>
                                    <div class="col-md-4"><div class="form-group"><label>Plot</label><select class="form-control select2" disabled><option>{{ $booking->plot->name ?? 'Unavailable' }}</option></select></div></div>
                                    <div class="col-md-4"><div class="form-group"><label>Plot Size</label><input class="form-control" value="{{ $booking->plot_size }}{{ !empty($booking->plot->unit) ? ' ' . $booking->plot->unit : '' }}" readonly></div></div>
                                    <div class="col-md-6"><div class="form-group">
                                        <label for="broker_id">Broker</label>
                                        <select class="form-control select2 @error('broker_id') is-invalid @enderror" name="broker_id" id="broker_id" @disabled(!$canUpdateBroker)>
                                            <option value="">Select Broker</option>
                                            @foreach ($brokers as $broker)<option value="{{ $broker->id }}" @selected((string) old('broker_id', $booking->broker_id) === (string) $broker->id)>{{ $broker->name }}</option>@endforeach
                                        </select>
                                    </div></div>
                                </div>
                                @if ($canUpdateBroker)<button type="submit" class="btn btn-primary">Save Broker Change</button>@endif
                            </div>
                        </div>
                    </form>
                </div>

                <div class="col-md-5" id="pricing">
                    <form method="POST" action="{{ route('booking.pricing.update', ['id' => $booking->id]) }}">
                        @csrf
                        @method('PUT')
                        @foreach (['plot_rate', 'is_park', 'park_facing', 'is_corner', 'carner_price', 'dicount_value', 'total_price'] as $field)
                            <input type="hidden" name="expected_{{ $field }}" value="{{ $booking->{$field} }}">
                        @endforeach
                        <div class="card">
                            <div class="card-header"><h3 class="card-title">Pricing Form</h3></div>
                            <div class="card-body">
                                @if (!$canUpdatePricing)
                                    <div class="alert alert-secondary">You do not have permission to update booking pricing.</div>
                                @elseif (!$pricingAllowed)
                                    <div class="alert alert-warning">@foreach (($pricingLifecycle['pricing_block_reasons'] ?? []) as $reason)<div>{{ $blockMessages[$reason] ?? 'Accounting structure requires review.' }}</div>@endforeach</div>
                                @elseif (!($pricingLifecycle['amounts_consistent'] ?? true))
                                    <div class="alert alert-warning">The saved booking amount and original sales accounting are different. Saving this pricing change will synchronize the original pending sales accounting.</div>
                                @endif

                                <div class="row">
                                    <div class="col-md-3"><div class="form-group"><label for="plot_rate">Plot Rate</label><input class="form-control" id="plot_rate" name="plot_rate" value="{{ old('plot_rate', $booking->plot_rate) }}" @disabled(!$pricingEnabled)></div></div>
                                    <div class="col-md-9"><div class="form-group"><label for="sub_total_price">Sub Total / Base Amount</label><input class="form-control" id="sub_total_price" value="{{ $basePreview }}" readonly></div></div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3"><div class="form-group"><label for="is_park">Park Facing</label><select class="form-control" id="is_park" name="is_park" @disabled(!$pricingEnabled)><option value="1" @selected((int) old('is_park', $booking->is_park) === 1)>Yes</option><option value="0" @selected((int) old('is_park', $booking->is_park) === 0)>No</option></select></div></div>
                                    <div class="col-md-9"><div class="form-group"><label for="park_facing">Park Facing Charges</label><input class="form-control" id="park_facing" name="park_facing" value="{{ (int) old('is_park', $booking->is_park) === 1 ? old('park_facing', $booking->park_facing) : '0' }}" @disabled(!$pricingEnabled)></div></div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3"><div class="form-group"><label for="is_corner">Corner Plot</label><select class="form-control" id="is_corner" name="is_corner" @disabled(!$pricingEnabled)><option value="1" @selected((int) old('is_corner', $booking->is_corner) === 1)>Yes</option><option value="0" @selected((int) old('is_corner', $booking->is_corner) === 0)>No</option></select></div></div>
                                    <div class="col-md-9"><div class="form-group"><label for="carner_price">Corner Charges</label><input class="form-control" id="carner_price" name="carner_price" value="{{ (int) old('is_corner', $booking->is_corner) === 1 ? old('carner_price', $booking->carner_price) : '0' }}" @disabled(!$pricingEnabled)></div></div>
                                </div>
                                <div class="row"><div class="col-md-12"><hr></div></div>
                                <div class="row">
                                    <div class="col-md-3"><div class="form-group"><label for="dicount_value">Discount</label></div></div>
                                    <div class="col-md-9"><div class="form-group"><input class="form-control" id="dicount_value" name="dicount_value" value="{{ old('dicount_value', $booking->dicount_value) }}" @disabled(!$pricingEnabled)></div></div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3"><div class="form-group"><label for="grand_total">Grand Total</label></div></div>
                                    <div class="col-md-9"><div class="form-group"><input class="form-control" id="grand_total" value="{{ $booking->total_price }}" readonly><small class="form-text text-muted">Preview only. The server calculator is authoritative.</small></div></div>
                                </div>
                                <div class="form-group"><label for="pricing_reason">Reason for pricing change</label><textarea id="pricing_reason" name="reason" maxlength="500" class="form-control" rows="3" placeholder="Client-approved discount correction" @disabled(!$pricingEnabled)>{{ old('reason') }}</textarea></div>
                                @if ($pricingEnabled)<button type="submit" class="btn btn-primary">Save Pricing Change</button>@endif
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <a href="{{ route('booking.plot.index') }}" class="btn btn-secondary mb-3">Back to Bookings</a>
        </div></section>
    </div>
@endsection

@section('js')
    @if ($pricingEnabled)
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const plotSize = @json((string) $booking->plot_size);
                const rate = document.getElementById('plot_rate');
                const parkToggle = document.getElementById('is_park');
                const parkCharge = document.getElementById('park_facing');
                const cornerToggle = document.getElementById('is_corner');
                const cornerCharge = document.getElementById('carner_price');
                const discount = document.getElementById('dicount_value');
                const subTotal = document.getElementById('sub_total_price');
                const grandTotal = document.getElementById('grand_total');

                function decimal(value) {
                    const clean = String(value ?? '').replace(/,/g, '').trim();
                    if (!/^\d+(\.\d+)?$/.test(clean)) return null;
                    const parts = clean.split('.');
                    return { value: BigInt(parts.join('')), scale: (parts[1] || '').length };
                }
                function roundFraction(numerator, denominator) {
                    const whole = numerator / denominator;
                    return whole + (numerator % denominator * 2n >= denominator ? 1n : 0n);
                }
                function multiplyToCents(left, right) {
                    const a = decimal(left), b = decimal(right);
                    return !a || !b ? null : roundFraction(a.value * b.value * 100n, 10n ** BigInt(a.scale + b.scale));
                }
                function moneyToCents(value) {
                    const number = decimal(value);
                    return !number ? null : roundFraction(number.value * 100n, 10n ** BigInt(number.scale));
                }
                function format(cents) {
                    if (cents === null) return '';
                    const negative = cents < 0n, absolute = negative ? -cents : cents;
                    const whole = (absolute / 100n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                    return (negative ? '-' : '') + whole + '.' + (absolute % 100n).toString().padStart(2, '0');
                }
                function toggleCharge(toggle, charge) {
                    const enabled = toggle.value === '1';
                    charge.readOnly = !enabled;
                    charge.classList.toggle('bg-light', !enabled);
                    if (!enabled) charge.value = '0';
                }
                function updatePreview() {
                    toggleCharge(parkToggle, parkCharge);
                    toggleCharge(cornerToggle, cornerCharge);
                    const base = multiplyToCents(plotSize, rate.value);
                    const park = moneyToCents(parkCharge.value), corner = moneyToCents(cornerCharge.value), less = moneyToCents(discount.value);
                    subTotal.value = format(base);
                    grandTotal.value = base === null || park === null || corner === null || less === null ? '' : format(base + park + corner - less);
                }
                [rate, parkToggle, parkCharge, cornerToggle, cornerCharge, discount].forEach(function (field) {
                    field.addEventListener(field.tagName === 'SELECT' ? 'change' : 'input', updatePreview);
                });
                updatePreview();
            });
        </script>
    @endif
@endsection
