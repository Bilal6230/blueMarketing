@extends('admin.layouts.master')

@section('content')
    @php
        $customerName = trim(($booking->customer->first_name ?? '') . ' ' . ($booking->customer->last_name ?? ''));
        $canUpdateBroker = auth()->user()?->can('update plot') ?? false;
        $canUpdatePricing = auth()->user()?->can('update booking price') ?? false;
        $pricingAllowed = (bool) ($pricingLifecycle['pricing_edit_allowed'] ?? false) && !$paymentAttributionRequired;
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

            <form id="booking-edit-form" method="POST" action="{{ route('booking.update', ['id' => $booking->id]) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="expected_updated_at" value="{{ $booking->updated_at?->format('Y-m-d H:i:s.u') }}">
                <input type="hidden" name="expected_broker_id" value="{{ $booking->broker_id }}">
                @if ($paymentSummary)<input type="hidden" name="expected_paid_to_date" value="{{ $paymentSummary['paid_to_date'] }}">@endif
                @foreach (['project_id', 'customer_id', 'plot_id', 'plot_type', 'plot_size', 'status'] as $lockedField)
                    <input type="hidden" name="{{ $lockedField }}" value="{{ $booking->{$lockedField} }}">
                @endforeach
                <input type="hidden" name="booking_date" value="{{ \Carbon\Carbon::parse($booking->booking_date)->format('Y-m-d H:i:s') }}">
                @foreach (['plot_rate', 'is_park', 'park_facing', 'is_corner', 'carner_price', 'dicount_value', 'total_price'] as $field)
                    <input type="hidden" name="expected_{{ $field }}" value="{{ $booking->{$field} }}">
                @endforeach
            <div class="row">
                <div class="col-md-7">

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
                            </div>
                        </div>
                </div>

                <div class="col-md-5" id="pricing">
                        <div class="card">
                            <div class="card-header"><h3 class="card-title">Pricing Form</h3></div>
                            <div class="card-body">
                                @if (!$canUpdatePricing)
                                    <div class="alert alert-secondary">You do not have permission to update booking pricing.</div>
                                @elseif ($paymentAttributionRequired)
                                    <div class="alert alert-warning">Some historical payments cannot be linked safely to this booking. Please review payment attribution before changing the sale price.</div>
                                @elseif (!$pricingAllowed)
                                    <div class="alert alert-warning">@foreach (($pricingLifecycle['pricing_block_reasons'] ?? []) as $reason)<div>{{ $blockMessages[$reason] ?? 'Accounting structure requires review.' }}</div>@endforeach</div>
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
                                <div class="form-group"><label>Current Sale Price</label><input class="form-control" value="{{ $booking->total_price }}" readonly></div>
                                <div class="form-group"><label>Paid To Date</label><input class="form-control" id="paid_to_date" value="{{ $paymentSummary['paid_to_date'] ?? 'Attribution review required' }}" readonly></div>
                                <div class="form-group"><label>Current Outstanding</label><input class="form-control" value="{{ $paymentSummary ? (bccomp((string) $booking->total_price, $paymentSummary['paid_to_date'], 2) === 1 ? bcsub((string) $booking->total_price, $paymentSummary['paid_to_date'], 2) : '0.00') : 'Attribution review required' }}" readonly></div>
                                <div class="form-group"><label>Estimated New Outstanding</label><input class="form-control" id="estimated_outstanding" readonly></div>
                                <div class="form-group"><label>Estimated Refund Due</label><input class="form-control" id="estimated_refund" readonly></div>
                                <small class="form-text text-muted">Existing accounting vouchers will remain unchanged. Previews are informational; the server recalculates on save.</small>
                            </div>
                        </div>
                </div>
            </div>
            @if ($canUpdateBroker || $canUpdatePricing)<button type="submit" class="btn btn-primary mb-3">Save Changes</button>@endif
            </form>
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
                const paid = moneyToCents(@json($paymentSummary['paid_to_date'] ?? '0.00'));
                const originalTotal = moneyToCents(@json((string) $booking->total_price));

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
                    const revised = base === null || park === null || corner === null || less === null ? null : base + park + corner - less;
                    document.getElementById('estimated_outstanding').value = revised === null || paid === null ? '' : format(revised > paid ? revised - paid : 0n);
                    document.getElementById('estimated_refund').value = revised === null || paid === null ? '' : format(paid > revised ? paid - revised : 0n);
                }
                [rate, parkToggle, parkCharge, cornerToggle, cornerCharge, discount].forEach(function (field) {
                    field.addEventListener(field.tagName === 'SELECT' ? 'change' : 'input', updatePreview);
                });
                updatePreview();
                const form = document.getElementById('booking-edit-form');
                const original = {
                    broker_id: @json((string) ($booking->broker_id ?? '')),
                    plot_rate: @json((string) $booking->plot_rate),
                    is_park: @json((string) $booking->is_park),
                    park_facing: @json((string) $booking->park_facing),
                    is_corner: @json((string) $booking->is_corner),
                    carner_price: @json((string) $booking->carner_price),
                    dicount_value: @json((string) $booking->dicount_value)
                };
                const labels = { broker_id: 'Broker', plot_rate: 'Plot Rate', is_park: 'Park Facing', park_facing: 'Park Charges', is_corner: 'Corner Plot', carner_price: 'Corner Charges', dicount_value: 'Discount' };
                const escape = (value) => String(value).replace(/[&<>"']/g, (character) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[character]));
                let confirmed = false;
                form.addEventListener('submit', function (event) {
                    if (confirmed) return;
                    event.preventDefault();
                    const changes = [];
                    Object.keys(original).forEach(function (key) {
                        const field = document.getElementById(key);
                        if (!field || field.disabled) return;
                        const oldValue = original[key], newValue = field.value;
                        const same = ['plot_rate', 'park_facing', 'carner_price', 'dicount_value'].includes(key)
                            ? moneyToCents(oldValue) === moneyToCents(newValue) : oldValue === newValue;
                        if (!same) {
                            const display = key === 'broker_id' ? (value) => field.querySelector('option[value="' + CSS.escape(value) + '"]')?.textContent || 'None'
                                : (['is_park', 'is_corner'].includes(key) ? (value) => value === '1' ? 'Yes' : 'No' : (value) => value);
                            changes.push(escape(labels[key]) + ': ' + escape(display(oldValue)) + ' → ' + escape(display(newValue)));
                        }
                    });
                    if (!changes.length) { Swal.fire('No changes to save.'); return; }
                    const revised = moneyToCents(grandTotal.value);
                    if (changes.some((line) => !line.startsWith('Broker:'))) {
                        changes.push('Sale Price: ' + escape(format(originalTotal)) + ' → ' + escape(grandTotal.value));
                        changes.push('Already Paid: ' + escape(format(paid)));
                        changes.push(revised !== null && paid > revised ? 'Estimated Refund Due: ' + escape(format(paid - revised)) : 'Estimated Outstanding: ' + escape(document.getElementById('estimated_outstanding').value));
                        changes.push('Payment Schedule: ' + (revised !== originalTotal ? 'Will be reset' : 'Will remain unchanged'));
                        changes.push('Existing Vouchers: Will remain unchanged');
                        if (revised !== null && paid > revised) changes.push('Accountant action will be required for the refund.');
                    }
                    Swal.fire({title: 'Confirm Booking Update', html: changes.join('<br>'), icon: 'warning', showCancelButton: true,
                        confirmButtonText: 'Confirm Update'}).then(function (result) {
                        if (result.isConfirmed) { confirmed = true; form.submit(); }
                    });
                });
            });
        </script>
    @elseif ($canUpdateBroker)
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const form = document.getElementById('booking-edit-form');
                const broker = document.getElementById('broker_id');
                const oldBroker = @json((string) ($booking->broker_id ?? ''));
                form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    if (broker.value === oldBroker) { Swal.fire('No changes to save.'); return; }
                    const before = broker.querySelector('option[value="' + CSS.escape(oldBroker) + '"]')?.textContent || 'None';
                    const after = broker.selectedOptions[0]?.textContent || 'None';
                    Swal.fire({title: 'Confirm Booking Update', text: 'Broker: ' + before + ' → ' + after,
                        icon: 'warning', showCancelButton: true, confirmButtonText: 'Confirm Update'})
                        .then(function (result) { if (result.isConfirmed) form.submit(); });
                });
            });
        </script>
    @endif
@endsection
