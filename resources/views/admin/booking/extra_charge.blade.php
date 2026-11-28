@extends('admin.layouts.master')
@section('content')

    <div class="content-wrapper bm-ui bm-voucher-directory bm-booking-vouchers bm-modern-extra" style="--voucher-accent:#7145bd;--voucher-tint:#f2edff" data-extra-errors="{{ $errors->any() ? '1' : '0' }}">
        <div class="bm-extra-heading">
            <div><h4>Extra Charges</h4><small>Selected project records · Charges and collection history</small></div>
            <div class="bm-extra-actions">
                <button type="button" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#modal-charge-type">Add Charge Type</button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('dashboard') }}">Dashboard</a>
            </div>
        </div>
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <span class="{{ $class }}">
                            {{ $title }}
                        </span>

                    </div><!-- /.col -->
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active">{{ $title }}</li>
                        </ol>
                    </div><!-- /.col -->
                </div><!-- /.row -->
            </div>
            <!-- /.container-fluid -->
        </div>
        <!-- /.content-header -->

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card bm-card-frame-reset">
                            @can('finance extra-charge create')
                                <div class="card-header">
                                    <div>
                                        <form action="{{ route('booking.customer.deposit') }}" method="POST"
                                            enctype="multipart/form-data" id="voucherForm">
                                            @csrf
 @include('admin.finance.voucher.typed_posting_fields')

                                            <div class="row">

                                                <div class="col-sm-12">
                                                    <div class="row">
                                                        <div class="col-sm-3">
                                                            <div class="input-group">
                                                                <label class="fbox">Voucher No.</label>
                                                                <div class="input-group">
                                                                    <input type="text" value="extra_charge" name="action"
                                                                        hidden />
                                                                    <input type="text" class="form-control " name="voucher"
                                                                        value="{{ (int)getSelectedTown() > 0 ? 'EC-'.app(\App\Services\Finance\ProjectVoucherAllocator::class)->preview((int)getSelectedTown(),'EC') : 'Select a project first' }}"
                                                                        autocomplete="off" readonly>

                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-sm-3">
                                                            <div class="input-group">
                                                                <label class="fbox">Slip No.</label>
                                                                <div class="input-group">

                                                                    <input type="text"
                                                                        class="form-control @error('reference') is-invalid @enderror"
                                                                        name="reference" value="{{ old('reference') }}"
                                                                        autocomplete="off">
                                                                    @error('reference')
                                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-sm-6">
                                                            <div class="input-group">
                                                                <label class="fbox">Date</label>
                                                                <div class="input-group">
                                                                    <input type="text" name="date"
                                                                        class="date form-control" data-input>
                                                                    <!-- Add a hidden input to store the selected date in a format you want -->
                                                                    <input type="hidden" id="hiddenDate" name="hiddenDate">
                                                                    {{-- <input type="text" id="datepicker" class="form-control @error('date') is-invalid @enderror" name="date" value="{{ old('date') ?: date('d-m-yy') }}" autocomplete="off"> --}}
                                                                    @error('date')
                                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>

                                            <div class="row">
                                                <div class="col-sm-6">
                                                    <div class="row">
                                                        <div class="col-sm-6">
                                                            <div class="input-group">
                                                                <label class="fbox">Charge Type</label>
                                                                <div class="input-group">
                                                                    <select class="form-control fvc-tom-select" name="charge_type_id"
                                                                        id="charge_type">
                                                                        <option value="">Select an option</option>
                                                                        @foreach ($chargeTypes as $v)
                                                                            <option value="{{ $v->id }}">
                                                                                {{ $v->name }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                    @error('project_id')
                                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-sm-6">
                                                            <div class="input-group">
                                                                <label class="fbox">Customer</label>
                                                                <div class="input-group">
                                                                    <select class="form-control fvc-tom-select" name="customer_id"
                                                                        id="customer_id">
                                                                        <option value="">Select Customer</option>
                                                                        @foreach ($customers as $v)
                                                                            <option value="{{ $v->id }}">
                                                                                {{ $v->first_name }} {{ $v->last_name }}
                                                                                {{ $v->relate }} {{ $v->father_name }} -
                                                                                {{ $v->phone_number }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                    @error('customer_id')
                                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>
                                                        </div>

                                                    </div>
                                                </div>
                                                <div class="col-sm-6">
                                                    <div class="row">
                                                        <div class="col-sm-6">
                                                            <div class="input-group">
                                                                <label class="fbox">Plot No.</label>
                                                                <div class="input-group">
                                                                    <select class="form-control fvc-tom-select" name="plot_id"
                                                                        id="plot_id">
                                                                        <option value="">Select Plot</option>
                                                                    </select>
                                                                    @error('plot_id')
                                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="input-group">
                                                                <label class="fbox">Amount</label>
                                                                <div class="input-group">
                                                                    <input id="numberInput" oninput="formatAmount(this)"
                                                                        type="text"
                                                                        class="form-control @error('amount') is-invalid @enderror"
                                                                        placeholder="Amount" name="amount"
                                                                        value="{{ old('amount') }}">
                                                                    @error('amount')
                                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>
                                                        </div>


                                                    </div>
                                                </div>



                                            </div>
                                            <div class="row">
                                                <div class="col-sm-12">
                                                    <label for="extraAmountWords" class="fbox">Amount in Words</label>
                                                    <input id="extraAmountWords" type="text" class="form-control"
                                                        placeholder="Amount in words will appear automatically" disabled readonly
                                                        style="background:#f1f5f9;color:#20364d;font-size:15px;font-weight:600;opacity:1">
                                                </div>
                                            </div>

                                            <div class="col-sm-12">
                                                <div class="row">
                                                    <div class="col-sm-3">
                                                        <div class="input-group">
                                                            <label class="fbox">Payment Status</label>
                                                            <div class="input-group">
                                                                <input type="hidden" name="payment_type" value="0">
                                                                <input class="form-control" value="Charge now - payment can be received later" readonly>
                                                                @error('payment_type')
                                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                                @enderror
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-3">
                                                        <div class="input-group bank_group" style="display: none">
                                                            <label class="fbox">Number</label>
                                                            <div class="input-group">
                                                                <input id="t_number" type="text"
                                                                    class="form-control @error('t_number') is-invalid @enderror"
                                                                    placeholder="Transaction Number" name="t_number"
                                                                    value="{{ old('t_number') }}">
                                                                @error('amount')
                                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                                @enderror
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-3">
                                                        <div class="input-group bank_group" style="display: none">
                                                            <label class="fbox">Bank</label>
                                                            <div class="input-group">
                                                                <select class="form-control fvc-tom-select" name="bank_id"
                                                                    id="bank_id">
                                                                    <option value="">Bank</option>

                                                                    @foreach (getPakistanBanks() as $v)
                                                                        <option value="{{ $v['id'] }}">
                                                                            {{ $v['name'] }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>

                                                        </div>
                                                    </div>

                                                    <div class="col-sm-3">
                                                        <div class="input-group bank_group" style="display: none">
                                                            <label class="fbox">Passing Date</label>
                                                            <div class="input-group">
                                                                <input type="text" name="passing_date"
                                                                    class="date form-control" data-input>
                                                                <!-- Add a hidden input to store the selected date in a format you want -->
                                                                @error('passing_date')
                                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                                @enderror
                                                            </div>
                                                        </div>
                                                    </div>


                                                </div>
                                            </div>

                                            <div class="row">

                                                <div class="col-sm-12">
                                                    <div class="row">
                                                        <div class="col-sm-6">
                                                            <div class="input-group {{ $bg_voucher }}">
                                                                <div class="input-group">
                                                                    <textarea class="form-control @error('detail') is-invalid @enderror" placeholder="Detail" name="detail"
                                                                        style=" height: 150px;" maxlength="255">{{ old('detail') }}</textarea>
                                                                    @error('detail')
                                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-sm-6">
                                                            <div class="{{ $bg_voucher }}">
                                                                <div class="party-info">

                                                                    <div class="info-set">
                                                                        <strong class="info-label">Phone:</strong>
                                                                        <span id="phone" class="info-data"></span><br>
                                                                    </div>

                                                                    <div class="info-set">
                                                                        <strong class="info-label">Address:</strong>
                                                                        <span id="address" class="info-data"></span><br>
                                                                    </div>

                                                                    <div class="info-set">
                                                                        <strong class="info-label">CNIC:</strong>
                                                                        <span id="cnic" class="info-data"></span><br>
                                                                    </div>

                                                                    <div class="info-set">
                                                                        <strong class="info-label">Due Amount:</strong>
                                                                        <span id="due" class="info-data"></span>
                                                                    </div>

                                                                    <div class="info-set">
                                                                        <strong class="info-label">Balance:</strong>
                                                                        <span id="balance" class="info-data"></span>
                                                                    </div>
                                                                </div>


                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>






                                            </div>
                                            <div class="row">
                                                <div class="col-sm-12">


                                                    <div class="col-sm-3">
                                                        <div class="modal-footer justify-content-between">
                                                            <button type="button" class="btn btn-primary btn-lg btn-block bm-extra-save">Save</button>


                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endcan
                            <!-- /.card-header -->
                            @can('finance extra-charge view')
                                <x-finance.voucher-table-card class="mb-2">
                                <div class="table-responsive bm-extra-list-pending bm-voucher-initial-pending">
                                    <div class="bm-extra-list-skeleton bm-voucher-initial-skeleton" role="status" aria-label="Loading extra charges"><table class="table table-bordered w-100" aria-hidden="true"><tbody id="extraChargeInitialSkeleton">@include('components.finance.voucher-skeleton-rows', ['columns' => 14])</tbody></table></div>
                                    <table id="example1" class="table table-striped table-bordered " style="width:100%">
                                        <thead>
                                            <tr>
                                                <th style="width: 5px">#</th>
                                                <th style="width: 60px">Date</th>

                                                <th>Customer</th>
                                                <th style="width: 5px">Plot</th>
                                                <th style="width: 5px">Ref</th>
                                                <th>Detail</th>
                                                <th>Amount</th>
                                                <th>Received</th>
                                                <th>Pending</th>
                                                <th>Outstanding</th>
                                                <th>Cash Payment</th>
                                                <th>Type</th>
                                                <th>Approve</th>


                                                @canany(['delete slip', 'can approve', 'finance extra-charge edit', 'finance extra-charge delete'])
                                                    <th>Action</th>
                                                @endcanany
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($data as $i)
                                                <tr>
                                                    <td>{{ $loop->iteration }}</td>
                                                    <td>
                                                        {{ $i->date ?? '' }}
                                                    </td>

                                                    <td>
                                                        {{ $i->customer_list->first_name ?? '' }}
                                                        {{ $i->customer_list->last_name ?? '' }}
                                                        {{ $i->customer_list->relate ?? '' }}
                                                        {{ $i->customer_list->last_name ?? '' }}
                                                    </td>
                                                    <td>
                                                        {{ $i->plot_list->name ?? '' }}

                                                    </td>
                                                    <td>
                                                        @if ($i->display_voucher_number)
                                                            <strong>{{ $i->display_voucher_number }}</strong><br>
                                                        @endif
                                                        {{ $i->reference ?? '' }}

                                                    </td>
                                                    <td>
                                                        {{ $i->description }}
                                                    </td>
                                                    <td>
                                                        {{ Setting::roundformatAmount(in_array($i->transaction_type, ['EC','ECP'], true) ? $i->amount_in : $i->amount_out) }}
                                                    </td>
                                                    <td>
                                                        @if ($i->transaction_type === 'EC')
                                                            {{ Setting::roundformatAmount($i->received_amount) }}
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if ($i->transaction_type === 'EC')
                                                            {{ Setting::roundformatAmount($i->pending_amount) }}
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if ($i->transaction_type === 'EC')
                                                            {{ Setting::roundformatAmount($i->outstanding_amount) }}
                                                        @elseif ($i->transaction_type === 'ECP')
                                                            <small class="text-muted">Linked EC payment</small>
                                                        @else
                                                            Legacy - verify source
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if ($i->transaction_type === 'EC' && $i->available_amount > 0 && $ecCashReady)
                                                            @can('create plot')
                                                                <form method="POST" action="{{ route('booking.customer.deposit') }}">
                                                                    @csrf
 @include('admin.finance.voucher.typed_posting_fields')
                                                                    <input type="hidden" name="action" value="extra_charge_payment">
                                                                    <input type="hidden" name="customer_id" value="{{ $i->customer_id }}">
                                                                    <input type="hidden" name="plot_id" value="{{ $i->plot_id }}">
                                                                    <input type="hidden" name="extra_charge_id" value="{{ $i->id }}">
                                                                    <input type="hidden" name="payment_type" value="1">
                                                                    <input type="date" name="date" class="form-control form-control-sm" required>
                                                                    <input type="text" name="reference" class="form-control form-control-sm" placeholder="Payment slip reference" maxlength="100" required>
                                                                    <input type="number" name="amount" class="form-control form-control-sm" min="0.01" step="0.01" max="{{ $i->available_amount }}" required>
                                                                    <div class="small text-muted mt-1">Debit: Extra Charge Cash · Credit: Extra Charge Receivable (linked customer/plot). Project-specific accounts are resolved automatically.</div>
                                                                    <input type="hidden" name="detail" value="Extra Charge cash payment">
                                                                    <button type="submit" class="btn btn-sm btn-primary mt-1">Receive Cash</button>
                                                                </form>
                                                            @endcan
                                                        @elseif ($i->transaction_type === 'EC' && $i->available_amount > 0)
                                                            <small>Approved project cash account required</small>
                                                        @elseif ($i->transaction_type === 'EC')
                                                            <small>Paid</small>
                                                        @endif
                                                        @if ($i->transaction_type === 'EC' && $i->available_amount > 0)
                                                            @can('create plot')
                                                                <details class="mt-2">
                                                                    <summary>Receive Online / Cheque (pending)</summary>
                                                                    <form method="POST" action="{{ route('booking.customer.deposit') }}">
                                                                        @csrf
 @include('admin.finance.voucher.typed_posting_fields')
                                                                        <input type="hidden" name="action" value="extra_charge_payment">
                                                                        <input type="hidden" name="customer_id" value="{{ $i->customer_id }}">
                                                                        <input type="hidden" name="plot_id" value="{{ $i->plot_id }}">
                                                                        <input type="hidden" name="extra_charge_id" value="{{ $i->id }}">
                                                                        <select name="payment_type" required class="form-control form-control-sm">
                                                                            <option value="2">Online</option>
                                                                            <option value="3">Cheque</option>
                                                                        </select>
                                                                        <input type="date" name="date" required class="form-control form-control-sm">
                                                                        <input type="date" name="passing_date" required class="form-control form-control-sm">
                                                                        <select name="bank_id" required class="form-control form-control-sm fvc-tom-select">
                                                                            <option value="">Select bank</option>
                                                                            @foreach(($receiptBanks ?? collect()) as $receiptBank)
                                                                                <option value="{{ $receiptBank->id }}">
                                                                                    {{ $receiptBank->bank_name ?? $receiptBank->name ?? $receiptBank->title ?? ('Bank #' . $receiptBank->id) }}
                                                                                </option>
                                                                            @endforeach
                                                                        </select>
                                                                        <input type="text" name="t_number" required maxlength="100" placeholder="Transaction / cheque number" class="form-control form-control-sm">
                                                                        <div class="fvc-cheque-purpose d-none">
                                                                            <label class="small mb-1">Cheque purpose</label>
                                                                            <input type="text" name="cheque_purpose" maxlength="500"
                                                                                class="form-control form-control-sm" placeholder="Cheque purpose">
                                                                        </div>
                                                                        <input type="text" name="reference" required maxlength="100" placeholder="Unique slip reference" class="form-control form-control-sm">
                                                                        <input type="number" name="amount" min="0.01" step="0.01" max="{{ $i->available_amount }}" required class="form-control form-control-sm">
                                                                        <input type="hidden" name="detail" value="Extra Charge bank pending receipt">
                                                                        <button type="submit" class="btn btn-sm btn-secondary mt-1">Record Pending</button>
                                                                    </form>
                                                                </details>
                                                            @endcan
                                                        @endif
                                                    </td>

                                                    <td>
                                                        <span
                                                            class="badge {{ $i->transaction_type === 'EC' ? 'badge-warning' : getPaymentTypeDetails($i->payment_type)['badge'] }}"
                                                            style="width: 80%">
                                                            {{ $i->transaction_type === 'EC' ? 'Outstanding Charge' : getPaymentTypeDetails($i->payment_type)['name'] }}
                                                        </span>
                                                    </td>

                                                    <td>
                                                        {{ approveStatus($i->is_approve) }}
                                                    </td>


                                                    @canany(['update voucher', 'delete slip', 'can approve', 'finance extra-charge edit', 'finance extra-charge delete'])
                                                        <td>
                                                            <div class="btn-group">
                                                                @if($i->transaction_type==='EC')
                                                                    <button class="btn btn-sm btn-outline-info fvc-payment-history"
                                                                        type="button" data-charge-id="{{ $i->id }}"
                                                                        data-charge-number="{{ $i->display_voucher_number ?? $i->reference }}"
                                                                        data-blue-action-label="Payments"
                                                                        title="View ECP payments"><i class="fas fa-list"></i> Payments ({{ ($ecPaymentsByCharge[$i->id] ?? collect())->count() }})</button>
                                                                @endif
                                                                @canany(['update voucher','finance extra-charge edit'])
                                                                    <button class="btn btn-sm btn-primary btn-edit"
                                                                        data-id="{{ $i->id }}"><i
                                                                            class="fas fa-pencil-alt"></i></button>
                                                                @endcan
                                                                @can('can approve')
                                                                    <button class="btn btn-sm btn-secondary btn-view"
                                                                        data-blue-action-label="View / Approve"
                                                                        data-id="{{ $i->id }}"
                                                                        data-name="{{ $i->name }}"><i
                                                                            class="fas fa-eye"></i></button>
                                                                @endcan
                                                                @canany(['delete slip','finance extra-charge delete'])
                                                                    <button class="btn btn-sm btn-danger btn-delete"
                                                                        data-id="{{ $i->id }}"
                                                                        data-name="{{ $i->name }}"><i
                                                                            class="fas fa-trash"></i></button>
                                                                @endcan
                                                                @if (Auth::user()->hasRole('super-admin') || Auth::user()->can('direct-update'))
                                                                    @if ($i->status === 'Pending')
                                                                        <div class="d-flex admin_approval">
                                                                            <button
                                                                                class="btn btn-sm btn-outline-info btn-view-changes"
                                                                                data-old='@json($i->old_values)'
                                                                                data-new='@json($i->new_values)'
                                                                                data-submitted_by='{{ addslashes($i->submitted_by) }}'
                                                                                data-record_id='{{ $i->id }}'>
                                                                                <i class="fas fa-eye"></i> Approval Required
                                                                            </button>
                                                                        </div>
                                                                    @endif
                                                                @else
                                                                    @if ($i->status === 'Pending')
                                                                        <span class="badge bg-secondary px-3 py-2"
                                                                            style="cursor: pointer;" data-bs-toggle="tooltip"
                                                                            title="Needs Admin Approval">
                                                                            <i class="fas fa-lock me-1"></i>
                                                                        </span>
                                                                    @endif
                                                                @endif
                                                            </div>
                                                        </td>
                                                    @endcanany
                                                </tr>
                                            @endforeach


                                        </tbody>


                                    </table>
                                </div>
                                </x-finance.voucher-table-card>
                            @endcan

                            @if ($ecPendingPayments->isNotEmpty())
                                <div class="card-body">
                                    <h5>Extra Charge Online / Cheque Pending Clearance</h5>
                                    @foreach ($ecPendingPayments as $pending)
                                        <div class="border rounded p-2 mb-2">
                                            <strong>{{ $pending->display_voucher_number ?: $pending->reference }}</strong>
                                            @if ($pending->display_voucher_number)
                                                <small class="text-muted">({{ $pending->reference }})</small>
                                            @endif
                                            — Rs. {{ Setting::roundformatAmount($pending->amount_in) }}
                                            @can('can approve')
                                                <form method="POST" action="{{ route('booking.customer.deposit') }}" class="mt-2">
                                                    @csrf
 @include('admin.finance.voucher.typed_posting_fields')
                                                    <input type="hidden" name="action" value="extra_charge_clearance">
                                                    <input type="hidden" name="extra_charge_payment_id" value="{{ $pending->id }}">
                                                    <input type="hidden" name="customer_id" value="0">
                                                    <input type="hidden" name="plot_id" value="0">
                                                    <input type="hidden" name="amount" value="{{ $pending->amount_in }}">
                                                    <input type="hidden" name="reference" value="{{ $pending->reference }}">
                                                    <input type="hidden" name="payment_type" value="{{ $pending->payment_type }}">
                                                    <input type="hidden" name="detail" value="Extra Charge clearance decision">
                                                    <label class="small mb-1" for="ec-bank-account-{{ $pending->id }}">Project bank account</label>
                                                    <select id="ec-bank-account-{{ $pending->id }}" name="bank_account_id" class="form-control form-control-sm mb-2" data-bank-settlement-account required>
                                                        <option value="">Select the account receiving this payment</option>
                                                        @foreach(($bankSettlementAccounts ?? collect()) as $bankAccount)
                                                            <option value="{{ $bankAccount->id }}" data-account-mapping-id="{{ $bankAccount->account_mapping_id }}">{{ $bankAccount->bank_name }} — {{ $bankAccount->account_title }}</option>
                                                        @endforeach
                                                    </select>
                                                    <input type="date" name="bank_post_at" class="form-control form-control-sm">
                                                    <input type="text" name="note" maxlength="255" placeholder="Decision note" class="form-control form-control-sm">
                                                    <button type="submit" name="passing_status" value="1" class="btn btn-sm btn-success" @if (!$ecBankReady) disabled @endif>Clear</button>
                                                    <button type="submit" name="passing_status" value="2" class="btn btn-sm btn-danger">Reject</button>
                                                </form>
                                                @unless ($ecBankReady)
                                                    <small>Approve project bank account in finance config to enable clearance</small>
                                                @endunless
                                            @endcan
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <!-- /.card-body -->
                        </div>
                        <!-- /.card -->


                    </div>
                    <!-- /.col -->
                </div>
                <!-- /.row -->
            </div><!-- /.container-fluid -->
        </section>
        <!-- /.content -->
    </div>


@endsection

@section('modal')
<div class="modal fade fvc-modern-modal" id="fvcPaymentHistoryModal" tabindex="-1" aria-labelledby="fvcPaymentHistoryTitle" aria-hidden="true">
 <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h5 id="fvcPaymentHistoryTitle" class="modal-title">Extra Charge Payments</h5>
   <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span>&times;</span></button></div>
  <div class="modal-body">
   <div class="table-responsive">
    <table class="table table-bordered table-striped table-sm" id="fvcPaymentHistoryTable">
     <thead><tr><th>Voucher #</th><th>Date</th><th>Reference</th><th>Details</th><th>Amount</th><th>Payment Type</th><th>Actions</th></tr></thead>
     <tbody id="fvcPaymentRows">
      @foreach(($ecPaymentsByCharge ?? collect()) as $parentChargeId => $chargePayments)
       @foreach($chargePayments as $payment)
        @if((int)$parentChargeId>0)
        <tr data-charge-id="{{ (int)$parentChargeId }}">
         <td><strong>{{ $payment->display_voucher_number ?? ('ECP-'.$payment->id) }}</strong></td>
         <td>{{ $payment->date }}</td>
         <td>{{ $payment->reference }}</td>
         <td>{{ $payment->description }}</td>
         <td>{{ Setting::roundformatAmount($payment->amount_in) }}</td>
         <td>{{ [1=>'Cash',2=>'Online',3=>'Cheque'][(int)$payment->payment_type] ?? 'Other' }}</td>
         <td>
          @if((int)$payment->payment_type===1)
           @canany(['update voucher','finance extra-charge edit'])
            <button type="button" class="btn btn-sm btn-primary btn-edit" data-id="{{ $payment->id }}">Edit</button>
           @endcanany
           @canany(['delete slip','finance extra-charge delete'])
            <button type="button" class="btn btn-sm btn-danger btn-delete" data-id="{{ $payment->id }}">Delete</button>
           @endcanany
          @else
           <span class="text-muted">Managed through bank source</span>
          @endif
         </td>
        </tr>
        @endif
       @endforeach
      @endforeach
     </tbody>
    </table>
   </div>
   <div id="fvcPaymentHistoryEmpty" class="text-muted d-none">No ECP payments recorded for this charge.</div>
  </div>
  <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-dismiss="modal">Close</button></div>
 </div></div>
</div>
<div class="modal fade fvc-modern-modal" id="fvcExtraCashEditModal" tabindex="-1" aria-labelledby="fvcExtraCashEditTitle" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <div class="modal-header"><h5 id="fvcExtraCashEditTitle" class="modal-title">Edit Extra Charge</h5>
   <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button></div>
  <form id="fvcExtraCashEditForm"><div class="modal-body">
   <div class="alert alert-info small">The original EC/ECP voucher number and ledger links remain unchanged.</div>
   <div class="alert alert-secondary small">Account mappings are tied to this Extra Charge: EC uses dedicated Receivable and Income accounts; Cash ECP uses dedicated Cash and Receivable accounts. The exact stored Debit / Credit direction is shown below. Customer, plot and charge-type changes require a separately reviewed correction, not an ordinary amount edit.</div>
   <div id="fvcExtraCashIdentityFields">
    <div class="form-row">
     <div class="form-group col-md-6"><label for="fvcExtraCashCustomer">Customer</label>
      <select id="fvcExtraCashCustomer" class="form-control fvc-tom-select">
       <option value="">Choose customer</option>
       @foreach(($customers ?? collect()) as $customer)
        <option value="{{ $customer->id }}">{{ $customer->first_name }} {{ $customer->last_name }}</option>
       @endforeach
      </select></div>
     <div class="form-group col-md-6"><label for="fvcExtraCashPlot">Plot</label>
      <select id="fvcExtraCashPlot" class="form-control fvc-tom-select">
       <option value="">Choose booked plot</option>
       @foreach(($ecEditableBookings ?? collect()) as $booking)
        <option value="{{ $booking->plot_id }}" data-customer="{{ $booking->customer_id }}">
         {{ $booking->plot->name ?? ('Plot #'.$booking->plot_id) }}
        </option>
       @endforeach
      </select></div>
    </div>
    <div class="form-row">
     <div class="form-group col-md-6"><label for="fvcExtraCashChargeType">Charge Type</label>
      <select id="fvcExtraCashChargeType" class="form-control">
       <option value="">Existing charge type</option>
       @foreach(($chargeTypes ?? collect()) as $chargeType)
        <option value="{{ $chargeType->id }}">{{ $chargeType->name }}</option>
       @endforeach
      </select></div>
     <div class="form-group col-md-6"><label for="fvcExtraCashReference">Slip Reference</label>
      <input id="fvcExtraCashReference" type="text" class="form-control" maxlength="100"></div>
    </div>
    <div class="alert alert-warning small">Customer, Plot, Charge Type and Slip Reference changes are permitted only before any linked EC payments/reservations exist. The original voucher and both GL legs will be amended atomically.</div>
   </div>
   <div class="form-group"><label for="fvcExtraCashPostingAccounts">Linked Debit / Credit Accounts</label>
    <textarea id="fvcExtraCashPostingAccounts" class="form-control" rows="2" readonly></textarea>
    <small class="text-muted">Original voucher ledger account identities cannot be changed through a same-source amount correction.</small>
   </div>
   <div class="form-group"><label for="fvcExtraCashDate">Date</label><input type="date" class="form-control" id="fvcExtraCashDate" required></div>
   <div class="form-group"><label for="fvcExtraCashAmount">Amount</label><input type="number" min="0.01" step="0.01" class="form-control" id="fvcExtraCashAmount" required></div>
   <div class="form-group"><label for="fvcExtraCashDescription">Description</label><textarea class="form-control" id="fvcExtraCashDescription" rows="3" maxlength="1000"></textarea></div>
   <div id="fvcExtraCashEditError" class="alert alert-danger d-none" role="alert"></div>
  </div><div class="modal-footer">
   <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
   <button type="submit" id="fvcExtraCashEditSubmit" class="btn btn-primary">Update Voucher</button>
  </div></form>
 </div></div>
</div>
<div class="modal fade fvc-modern-modal" id="fvcExtraCashDeleteModal" tabindex="-1" aria-labelledby="fvcExtraCashDeleteTitle" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <div class="modal-header"><h5 id="fvcExtraCashDeleteTitle" class="modal-title">Delete Extra Charge</h5>
   <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button></div>
  <div class="modal-body">
   <p>Remove this cash EC/ECP from active balances? Original IDs and inactive ledger evidence will remain. Linked payments cannot be silently deleted.</p>
   <div id="fvcExtraCashDeleteError" class="alert alert-danger d-none" role="alert"></div>
  </div>
  <div class="modal-footer">
   <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
   <button type="button" id="fvcExtraCashDeleteSubmit" class="btn btn-danger">Delete Voucher</button>
  </div>
 </div></div>
</div>
    {{-- Modal Update --}}
    <div class="modal fade" id="modal-charge-type">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header {{ $class }}">
                    <h4 class="modal-title">Add Charge Type</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('charge-type.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
 @include('admin.finance.voucher.typed_posting_fields')
                        <div class="row">

                            <div class="col-sm-12">
                                <div class="row">
                                    <div class="col-12">
                                        <div class="input-group">
                                            <label class="fbox">Name</label>
                                            <div class="input-group">
                                                <input id="name" type="text" class="form-control "
                                                    name="name" autocomplete="off" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                        <div class="modal-footer justify-content-between">
                            <input type="hidden" name="id" id="id">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
                <!-- /.modal-content -->
            </div>
            <!-- /.modal-dialog -->
        </div>
    </div>
    {{-- Modal Update --}}
    <div class="modal fade" id="modal-edit">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header {{ $class }}">
                    <h4 class="modal-title">Edit Voucher</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('ledger.update') }}" method="POST" enctype="multipart/form-data">
                        @csrf
 @include('admin.finance.voucher.typed_posting_fields')
                        @method('PUT')
                        <div class="row">

                            <div class="col-sm-12">
                                <div class="row">
                                    <div class="col-sm-3">
                                        <div class="input-group">
                                            <label class="fbox">Voucher No.</label>
                                            <div class="input-group">
                                                <input type="text" value="1" name="action" hidden />
                                                <input id="voucher" type="text" class="form-control "
                                                    name="voucher" autocomplete="off" readonly>

                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-3">
                                        <div class="input-group">
                                            <label class="fbox">Reference</label>
                                            <div class="input-group">

                                                <input id="reference" type="text"
                                                    class="form-control @error('reference') is-invalid @enderror"
                                                    name="reference" value="{{ old('reference') }}" autocomplete="off">
                                                @error('reference')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-6">
                                        <div class="input-group">
                                            <label class="fbox">Date</label>
                                            <div class="input-group">
                                                <input type="text" id="date" name="date"
                                                    class="date_database form-control" data-input>
                                                <!-- Add a hidden input to store the selected date in a format you want -->
                                                {{-- <input type="hidden" id="hiddenDate" name="hiddenDate"> --}}
                                                {{-- <input type="text" id="datepicker" class="form-control @error('date') is-invalid @enderror" name="date" value="{{ old('date') ?: date('d-m-yy') }}" autocomplete="off"> --}}
                                                @error('date')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="row">
                            <div class="col-sm-6">
                                <div class="row">
                                    <div class="col-sm-6">
                                        <div class="input-group">
                                            <label class="fbox">Projects</label>
                                            <div class="input-group">
                                                <select class="form-control fvc-tom-select" name="projects_id"
                                                    id="e_projects_id">
                                                    <option value="">Select an option</option>

                                                    @foreach ($chargeTypes as $v)
                                                        <option value="{{ $v->id }}">{{ $v->name }}</option>
                                                    @endforeach
                                                </select>
                                                @error('projects_id')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="input-group">
                                            <label class="fbox">Accounts</label>
                                            <div class="input-group">
                                                <select class="form-control fvc-tom-select" name="accounts_id"
                                                    id="e_accounts_id">
                                                    <option value="">Select an option</option>

                                                    {{-- @foreach ($headaccounts as $v)
                                                        <option value="{{ $v->id }}">{{ $v->name }}</option>
                                                    @endforeach --}}
                                                </select>
                                                @error('accounts_id')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="row">
                                    <div class="col-sm-6">
                                        <div class="input-group">
                                            <label class="fbox">Party Account</label>
                                            <div class="input-group">
                                                <select class="form-control fvc-tom-select" name="subaccounts_id"
                                                    id="e_subaccounts_id">
                                                    <option value="">Select an option</option>
                                                </select>
                                                @error('subaccounts_id')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="input-group">
                                            <label class="fbox">Amount</label>
                                            <div class="input-group">
                                                <input id="amount" oninput="formatAmount(this)" type="text"
                                                    class="form-control @error('amount') is-invalid @enderror"
                                                    placeholder="Amount" name="amount" value="{{ old('amount') }}">
                                                @error('amount')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>


                                </div>
                            </div>

                        </div>
                        <div class="row">
                            <div class="col-sm-12">
                                <div class="input-group">
                                    <label class="fbox">Detail</label>
                                    <div class="input-group">
                                        <textarea id="detail" class="form-control @error('detail') is-invalid @enderror" placeholder="Detail"
                                            name="detail" style=" height: 150px;" maxlength="255">{{ old('detail') }}</textarea>
                                        @error('detail')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                </div>
                            </div>
                        </div>

                        <div class="modal-footer justify-content-between">
                            <input type="hidden" name="id" id="id">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Update</button>
                        </div>
                    </form>
                </div>
                <!-- /.modal-content -->
            </div>
            <!-- /.modal-dialog -->
        </div>
    </div>
    {{-- Modal delete --}}
    <div class="modal fade" id="modal-delete">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Delete Voucher</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('booking.customer.destroy') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        @method('DELETE')
                        <p class="modal-text">Are you sure you want to delete? <b id="delete-data"></b></p>
                        <input type="hidden" name="id" id="did">
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">No</button>
                    <button type="submit" class="btn btn-danger">Yes</button>
                </div>
                </form>
            </div>
            <!-- /.modal-content -->
        </div>
        <!-- /.modal-dialog -->
    </div>

    {{-- Approval is a project-scoped V2 or legacy finance write. --}}
    <div class="modal fade" id="modal-approve" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Approve Voucher</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="fvc-extra-approval-form" action="{{ route('booking.customer.approve') }}" method="POST" novalidate>
                    @csrf
                    <div class="modal-body">
                        @include('admin.finance.voucher.typed_posting_fields')
                        <p class="modal-text mb-0" id="msg">Are you sure you want to Approve?</p>
                        <input type="hidden" name="id" class="status_id">
                        <input type="hidden" name="amount" class="fvc-approval-amount" value="0.00">
                        <input type="hidden" name="is_approve" class="is_approve" value="1">
                        <div class="alert alert-danger d-none mt-2 fvc-approval-feedback" role="alert"></div>
                    </div>
                    <div class="modal-footer d-flex justify-content-end">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Modal view --}}
    {{-- Read-only, project-scoped voucher details. No ledger.update form in view mode. --}}
    <div class="modal fade" id="modal-view" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Extra Charge Details</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <dl id="fvc-extra-view-facts" class="row mb-0" aria-live="polite"></dl>
                    <div id="fvc-extra-view-note" class="alert alert-info d-none mt-2 mb-0" role="status"></div>
                </div>
                <div class="modal-footer d-flex justify-content-end">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-outline-danger btn-sm btn-rejected"
                        data-id="" data-dismiss="modal">Reject</button>
                    <button type="button" class="btn btn-primary btn-sm btn-approve"
                        data-id="" data-dismiss="modal">Approve</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="viewChangesModal" tabindex="-1" aria-labelledby="viewChangesModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="viewChangesModalLabel">
                        <i class="fas fa-exchange-alt me-2"></i> Pending Changes
                    </h5>
                    <!-- Header close button removed -->
                </div>
                <div class="modal-body">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Field</th>
                                <th>Old Value</th>
                                <th>New Value</th>
                            </tr>
                        </thead>
                        <tbody id="changesTableBody">
                            <!-- Dynamically filled by JS -->
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <!-- Footer close button remains -->
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        $(function(){
            if($.blueTableSkeleton)$.blueTableSkeleton.show('#extraChargeInitialSkeleton',{rows:10,columns:14});
        });
        // Some shared table layouts render both a toolbar pager and the
        // standard bottom pager. Preserve only the final bottom control.
        var keepExtraChargeBottomPager=function(){
            var $wrapper=$('#example1_wrapper');
            if(!$wrapper.length)return;
            var $pagers=$wrapper.find('.dataTables_paginate,.dt-paging');
            if($pagers.length>1)$pagers.slice(0,-1).remove();
        };
        $(document).on('init.dt draw.dt',function(event,settings){
            if(settings&&settings.nTable&&settings.nTable.id==='example1'){
                var $wrap=$('#example1').closest('.table-responsive');
                $wrap.removeClass('bm-extra-list-pending bm-voucher-initial-pending').find('.bm-extra-list-skeleton').remove();
                keepExtraChargeBottomPager();
            }
        });
        function submitForm() {
            $('#voucherForm').trigger('submit');
        }


        $(document).ready(function() {

            $(document).ready(function() {
                console.log("Document ready");
                $('#payment_type').change(function(e) {

                    e.preventDefault();

                    if ($(this).val() != '1') {

                        $('.bank_group').css('display', 'block');
                    } else {

                        $('.bank_group').css('display', 'none');
                    }
                });
            });

            function fetchCustomers(projectId) {
                $.ajax({
                    url: '/admin/get-customers-byplot', // URL to your route
                    type: 'POST', // Use POST method for sending data
                    data: {
                        _token: '{{ csrf_token() }}', // Add CSRF token
                        project_id: projectId // Pass project ID to server
                    },
                    success: function(data) {
                        // Populate customer dropdown with retrieved data
                        $('#customer_id').empty();
                        $('#customer_id').append('<option value="">Select customer</option>');
                        $.each(data, function(key, customer) {
                            $('#customer_id').append('<option value="' + customer.id +
                                '" data-phone="' + customer.mobile_number +
                                '" data-nic_number="' + customer.nic_number +
                                '" data-home_address="' + customer.home_address + '">' +
                                customer.first_name + ' ' + customer.last_name + ' ' +
                                customer.relate + ' ' + customer.father_name + ' - ' +
                                customer.phone_number + '</option>');
                        });
                    }
                });
            }


            // Event listener for project dropdown change
            $('#project_id').change(function() {
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

            $('#customer_id').change(function(e) {
                e.preventDefault();
                var customerId = $(this).val();

                if (customerId) {
                    fetchPlots(customerId);
                    // console.log($(this).data('phone'));
                    // $('#phone').text( 'asdas');
                } else {
                    // If no project is selected, empty the customer dropdown
                    $('#plot_id').empty();
                    $('#plot_id').append('<option value="">Select plots</option>');
                }

            });

            function fetchPlots(customerId) {
                $.ajax({
                    url: '/admin/get-plots-list', // URL to your route
                    type: 'POST', // Use POST method for sending data
                    data: {
                        _token: '{{ csrf_token() }}', // Add CSRF token
                        project_id: '{{ getSelectedTown() }}',
                        customer_id: customerId // Pass project ID to server
                    },
                    success: function(data) {
                        console.log(data);
                        // Populate customer dropdown with retrieved data
                        $('#plot_id').empty();
                        $('#plot_id').append('<option value="">Select Plots</option>');
                        $.each(data, function(key, plot) {
                            var plotType = (plot.type == 1) ? 'R- ' : 'C- ';
                            $('#plot_id').append('<option value="' + plot.plot_id + '">' +
                                plotType + ' ' + plot.name + '  </option>');
                        });
                    }
                });
            }



            $('#numberInput').on('input', function() {
                convertToWords();
            });

            var extraCashBase=@json(url('admin/finance/extra-charge/cash'));
            var extraCashFeedback=$.financeVoucherFeedback||{};
            var extraCashNotify=function(type,message){
                if($.isFunction(extraCashFeedback.toast))extraCashFeedback.toast(type,message);
                else if(window.toastr&&$.isFunction(window.toastr[type]))window.toastr[type](message);
            };
            var extraCashError=function($box,xhr){
                var text=(xhr.responseJSON&&xhr.responseJSON.message)
                    ||($.isFunction(extraCashFeedback.error)?extraCashFeedback.error(xhr,'Voucher change failed.'):'Voucher change failed.');
                $box.text(text).removeClass('d-none');
                extraCashNotify('error',text);
            };
            // Refresh table rows through jQuery GET without a full document
            // navigation; the server remains authoritative for EC outstanding.
            var refreshExtraCashTable=function(){
                $.get(window.location.href).done(function(html){
                    var $page=$('<div>').append($.parseHTML(html,document,false)),
                        $new=$page.find('#example1 tbody'),$table=$('#example1');
                    var $newPayments=$page.find('#fvcPaymentRows');
                    if($newPayments.length){
                        var currentCharge=$('#fvcPaymentRows tr:visible').first().attr('data-charge-id');
                        $('#fvcPaymentRows').html($newPayments.html());
                        if(currentCharge){
                            var $rows=$('#fvcPaymentRows tr');
                            $rows.hide().filter(function(){return $(this).attr('data-charge-id')===currentCharge;}).show();
                            $('#fvcPaymentHistoryEmpty').toggleClass('d-none',$rows.filter(':visible').length>0);
                        }
                    }
                    if(!$new.length)return;
                    var hasDataTable=$.fn.dataTable&&$.fn.dataTable.isDataTable('#example1');
                    if(hasDataTable)$table.DataTable().destroy();
                    $table.find('tbody').html($new.html());
                    if(hasDataTable)$table.DataTable();
                    keepExtraChargeBottomPager();
                    if($.blueActionMenu)$.blueActionMenu.enhance($table.find('td.blue-actions-cell'));
                });
            };
            // The ECP Edit/Delete dialogs stack above their payment-history
            // parent rather than replacing it or navigating away.
            $('#fvcExtraCashEditModal,#fvcExtraCashDeleteModal').on('show.bs.modal',function(){
                if($('#fvcPaymentHistoryModal').hasClass('show')){
                    $(this).css('z-index',1070);
                    setTimeout(function(){
                        $('.modal-backdrop').last().css('z-index',1060);
                    },0);
                }
            }).on('hidden.bs.modal',function(){
                if($('#fvcPaymentHistoryModal').hasClass('show')){
                    $('body').addClass('modal-open');
                }
            });
            $(document).on('click.fvcPaymentHistory','#example1 .fvc-payment-history',function(event){
                event.preventDefault();
                var id=String($(this).attr('data-charge-id')||'');
                $('#fvcPaymentHistoryTitle').text('ECP Payments — '+($(this).attr('data-charge-number')||'Extra Charge'));
                var $rows=$('#fvcPaymentRows tr');
                $rows.hide().filter(function(){return String($(this).attr('data-charge-id'))===id;}).show();
                $('#fvcPaymentHistoryEmpty').toggleClass('d-none',$rows.filter(':visible').length>0);
                $('#fvcPaymentHistoryModal').modal('show');
            });
            var ecEditablePlotOptions=$('#fvcExtraCashPlot option[data-customer]').map(function(){
                return {value:String($(this).val()),customer:String($(this).attr('data-customer')),
                    text:$(this).text().trim()};
            }).get();
            var ecRebuildPlotChoices=function(customer,selected){
                var $field=$('#fvcExtraCashPlot'),element=$field[0];
                var matches=$.grep(ecEditablePlotOptions,function(option){
                    return option.customer===String(customer||'');
                });
                if(element&&element.tomselect){
                    element.tomselect.clear(true);
                    element.tomselect.clearOptions();
                    $.each(matches,function(_,option){
                        element.tomselect.addOption({value:option.value,text:option.text});
                    });
                    element.tomselect.refreshOptions(false);
                    if(selected)element.tomselect.setValue(String(selected),true);
                }else{
                    $field.empty().append($('<option>').val('').text('Choose booked plot'));
                    $.each(matches,function(_,option){
                        $field.append($('<option>').val(option.value).text(option.text));
                    });
                    if(selected)$field.val(String(selected));
                }
            };
            $(document).on('click.fvcExtraCash','#example1 .btn-edit,#fvcPaymentHistoryModal .btn-edit',function(event){
                event.preventDefault();event.stopImmediatePropagation();
                var source=Number($(this).data('id')),$button=$(this);
                if(!source)return;
                $button.prop('disabled',true);
                $.ajax({url:extraCashBase+'/'+source,type:'GET',dataType:'json',
                    headers:{Accept:'application/json'}})
                .done(function(result){
                    var d=result.data||{};
                    if(!d.editable){
                        extraCashNotify('error','This voucher is linked to bank custody or a typed posting; use its source workflow.');
                        return;
                    }
                    $('#fvcExtraCashEditForm').data('source',source);
                    $('#fvcExtraCashEditTitle').text('Edit '+d.type+' Voucher');
                    $('#fvcExtraCashIdentityFields').toggle(d.type==='EC');
                    if($('#fvcExtraCashCustomer')[0]?.tomselect){
                        $('#fvcExtraCashCustomer')[0].tomselect.setValue(String(d.customer_id||''),true);
                    }else $('#fvcExtraCashCustomer').val(d.customer_id||'');
                    ecRebuildPlotChoices(d.customer_id,d.plot_id);
                    $('#fvcExtraCashChargeType').val(d.charge_type_id||'');
                    $('#fvcExtraCashReference').val(d.reference||'');
                    $('#fvcExtraCashDate').val(d.date);
                    $('#fvcExtraCashAmount').val(d.amount);
                    $('#fvcExtraCashDescription').val(d.description);
                    $('#fvcExtraCashPostingAccounts').val($.map(d.posting_accounts||[],function(line){
                        return (line.side||'Account')+': '+(line.account||'Unmapped');
                    }).join('\n'));
                    $('#fvcExtraCashEditError').addClass('d-none').text('');
                    $('#fvcExtraCashEditModal').modal('show');
                }).fail(function(xhr){extraCashError($('#fvcExtraCashEditError'),xhr);})
                .always(function(){$button.prop('disabled',false);});
            });
            $('#fvcExtraCashCustomer').on('change',function(){
                ecRebuildPlotChoices($(this).val(),'');
            });
            $('#fvcExtraCashEditForm').on('submit.fvcExtraCash',function(event){
                event.preventDefault();
                var source=Number($(this).data('source')),$button=$('#fvcExtraCashEditSubmit');
                if(!source||$button.prop('disabled'))return;
                if($('#fvcExtraCashIdentityFields').is(':visible')
                    &&(!$('#fvcExtraCashCustomer').val()
                        ||!$('#fvcExtraCashPlot').val()
                        ||!$.trim($('#fvcExtraCashReference').val()))){
                    extraCashNotify('error','Select a valid booked Customer / Plot and enter the slip reference.');
                    return;
                }
                $button.prop('disabled',true);
                $.ajax({url:extraCashBase+'/'+source,type:'PUT',dataType:'json',
                    headers:{Accept:'application/json','X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')},
                    data:{date:$('#fvcExtraCashDate').val(),amount:$('#fvcExtraCashAmount').val(),
                        description:$('#fvcExtraCashDescription').val(),
                        customer_id:$('#fvcExtraCashIdentityFields').is(':visible')?$('#fvcExtraCashCustomer').val():null,
                        plot_id:$('#fvcExtraCashIdentityFields').is(':visible')?$('#fvcExtraCashPlot').val():null,
                        charge_type_id:$('#fvcExtraCashIdentityFields').is(':visible')?$('#fvcExtraCashChargeType').val():null,
                        reference:$('#fvcExtraCashIdentityFields').is(':visible')?$('#fvcExtraCashReference').val():null}})
                .done(function(result){
                    $('#fvcExtraCashEditModal').modal('hide');
                    extraCashNotify('success',result.message||'Extra Charge updated.');
                    refreshExtraCashTable();
                }).fail(function(xhr){extraCashError($('#fvcExtraCashEditError'),xhr);})
                .always(function(){$button.prop('disabled',false);});
            });
            $(document).on('click.fvcExtraCash','#example1 .btn-delete,#fvcPaymentHistoryModal .btn-delete',function(event){
                event.preventDefault();event.stopImmediatePropagation();
                var source=Number($(this).data('id'));if(!source)return;
                $('#fvcExtraCashDeleteSubmit').data('source',source);
                $('#fvcExtraCashDeleteError').addClass('d-none').text('');
                $('#fvcExtraCashDeleteModal').modal('show');
            });
            $('#fvcExtraCashDeleteSubmit').on('click.fvcExtraCash',function(){
                var $button=$(this),source=Number($button.data('source'));
                if(!source||$button.prop('disabled'))return;
                $button.prop('disabled',true);
                $.ajax({url:extraCashBase+'/'+source,type:'DELETE',dataType:'json',
                    headers:{Accept:'application/json','X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')}})
                .done(function(result){
                    $('#fvcExtraCashDeleteModal').modal('hide');
                    extraCashNotify('success',result.message||'Extra Charge deleted.');
                    refreshExtraCashTable();
                }).fail(function(xhr){extraCashError($('#fvcExtraCashDeleteError'),xhr);})
                .always(function(){$button.prop('disabled',false);});
            });

            $(document).on('click.fvcExtraView','#example1 .btn-view',function(event){
                event.preventDefault();
                var id=$(this).data('id'),$modal=$('#modal-view');
                var $details=$modal.find('#fvc-extra-view-facts').empty();
                var $button=$(this).prop('disabled',true);
                $.ajax({
                    url:"{{ route('booking.voucher.show') }}",type:'POST',dataType:'json',
                    headers:{Accept:'application/json'},
                    data:{id:id,_token:"{{ csrf_token() }}"}
                }).done(function(response){
                    var data=response&&response.data?response.data:{};
                    var pairs=[
                        ['Voucher #',data.reference||data.id],
                        ['Date',data.date],['Customer',data.customer&&data.customer.name],
                        ['Plot',data.plot&&data.plot.name],
                        ['Project',data.project&&data.project.name],
                        ['Description',data.description],
                        ['Amount',data.transaction_type==='EC'?data.amount_in:data.amount_out],
                        ['Payment Type',data.payment_type_label],
                        ['Approval',data.approval_status_label],
                        ['Clearance',data.clearance_status_label],
                        ['Phone',data.customer&&data.customer.phone_number],
                        ['Size',data.plot&&data.plot.size]
                    ];
                    $.each(pairs,function(_,item){
                        $('<dt>',{'class':'col-sm-4 mb-2'}).text(item[0]).appendTo($details);
                        $('<dd>',{'class':'col-sm-8 mb-2'}).text(item[1]==null?'—':String(item[1]))
                            .appendTo($details);
                    });
                    $modal.find('.btn-approve,.btn-rejected')
                        .attr('data-id',data.id)
                        .attr('data-amount',data.transaction_type==='EC'?data.amount_in:data.amount_out);
                    var settled=String(data.clearance_status_label||'').toLowerCase()==='posted';
                    var approved=String(data.approval_status_label||'').toLowerCase()==='approved';
                    $modal.find('#fvc-extra-view-note')
                        .toggleClass('d-none',!(settled||approved))
                        .text(settled?'This charge is posted. Approval/rejection is locked; a separate reversal is required.':
                            approved?'This charge is already approved. Reapproval is not available.':'');
                    $modal.find('.btn-approve').prop('disabled',settled||approved)
                      .attr('title',settled?'Posted vouchers must use reversal':approved?'Already approved':'Approve this voucher');
                    $modal.find('.btn-rejected').prop('disabled',settled)
                      .attr('title',settled?'Posted vouchers must use reversal':'Reject unposted voucher');
                    $modal.modal('show');
                }).fail(function(xhr){
                    if($.financeVoucherFeedback)
                        $.financeVoucherFeedback.toast('error',
                            $.financeVoucherFeedback.error(xhr,'Extra Charge details could not load.'));
                }).always(function(){$button.prop('disabled',false);});
            });

            // V2 approvals require a complete two-leg allocation. Validate
            // before AJAX; never allow an empty TomSelect to fail silently.
            $('#fvc-extra-approval-form').on('submit.fvcExtraApproval',function(event){
                event.preventDefault();
                var $form=$(this),$body=$form.find('.modal-body'),
                    $message=$form.find('.fvc-approval-feedback'),
                    $button=$form.find('[type="submit"]');
                if($form.data('fvc-busy'))return;
                $message.addClass('d-none').text('');
                var $fields=$form.find('[name="posting_lines[0][account_id]"],[name="posting_lines[1][account_id]"]');
                var missing=$fields.filter(function(){return !$(this).val();}).first();
                if(missing.length){
                    var msg='Select valid Debit and Credit posting accounts before approval.';
                    $message.text(msg).removeClass('d-none');
                    if($.financeVoucherFeedback)$.financeVoucherFeedback.toast('error',msg);
                    if(missing[0].tomselect)missing[0].tomselect.focus();else missing.trigger('focus');
                    return;
                }
                $form.data('fvc-busy',true);$button.prop('disabled',true);
                if($.financeVoucherFeedback)$.financeVoucherFeedback.skeleton($body,true);
                $.ajax({
                    url:$form.attr('action'),type:'POST',data:$form.serialize(),
                    headers:{Accept:'application/json'},dataType:'json'
                }).done(function(data){
                    if(data && data.status==='success'){
                        if($.financeVoucherFeedback)$.financeVoucherFeedback.toast('success',data.message||'Approval saved.');
                        $('#modal-approve,#modal-view').modal('hide');
                        $(document).trigger('finance:extra-changed');
                    }else{
                        $message.text('Approval was not confirmed by the server.').removeClass('d-none');
                    }
                }).fail(function(xhr){
                    var msg=$.financeVoucherFeedback?
                        $.financeVoucherFeedback.error(xhr,'Approval could not be completed.'):
                        'Approval could not be completed.';
                    $message.text(msg).removeClass('d-none');
                    if($.financeVoucherFeedback)$.financeVoucherFeedback.toast('error',msg);
                }).always(function(){
                    $form.data('fvc-busy',false);$button.prop('disabled',false);
                    if($.financeVoucherFeedback)$.financeVoucherFeedback.skeleton($body,false);
                });
            });

            $(document).on("click", '.btn-approve', function(event) {
                event.preventDefault();
                let id = $(this).attr("data-id");
                $('#modal-view').modal('hide');
                $('#fvc-extra-approval-form .status_id').val(id);
                var amount=$(this).attr('data-amount')||'0.00';
                $('#fvc-extra-approval-form .fvc-approval-amount').val(amount);
                if(window.syncOriginalPostingForm)
                    window.syncOriginalPostingForm($('#fvc-extra-approval-form')[0]);
                $('#fvc-extra-approval-form .is_approve').val(1);
                $('#msg').text("Are you sure you want to Approve?");
                $('#modal-approve').modal({
                    backdrop: 'static',
                    keyboard: false,
                    show: true
                });
            });
            $(document).on("click", '#modal-view .btn-rejected', function(event) {
                event.preventDefault();
                let id = $(this).attr("data-id");
                $('#modal-view').modal('hide');
                $(".status_id").val(id);
                $('#fvc-extra-approval-form .is_approve').val(0);
                $('#msg').text("Are you sure you want to rejected?");
                $('#modal-approve').modal({
                    backdrop: 'static',
                    keyboard: false,
                    show: true
                });
            });





        });

        $(document).ready(function() {
            $(document).on('click', '.btn-view-changes', function() {
                const newValues = $(this).data('new') || '{}';
                const oldValues = $(this).data('old');
                const submittedBy = $(this).data('submitted_by') || 'Unknown User';
                const record_id = $(this).data('record_id') || $(this).data('id');
                const $tbody = $('#changesTableBody');
                const $modalFooter = $('#viewChangesModal .modal-footer');

                $tbody.empty();
                $modalFooter.find('.btn-approved, .btn-rejected')
                    .remove(); // Remove old buttons if any

                // 🛑 Check if this is a delete request (only is_active changed to 0)
                if (Object.keys(newValues).length === 1 && newValues.is_active == 0) {
                    $tbody.html(`
                            <tr>
                                <td colspan="3" class="text-center text-danger fw-bold">
                                    <i class="fas fa-trash-alt me-2"></i>
                                    User <span class="text-primary">${submittedBy}</span> has requested to <strong>delete</strong> the ledger.
                                    ${newValues.delete_reason ? `<br><strong>Reason:</strong> ${newValues.delete_reason}` : ''}
                                </td>
                            </tr>
                        `);
                } else {
                    // 📝 Show normal field changes
                    $.each(newValues, function(key, newVal) {
                        const oldVal = oldValues[key] ??
                            '<em class="text-muted">N/A</em>';
                        const safeNewVal = newVal ?? '<em class="text-muted">N/A</em>';
                        $tbody.append(`
                                <tr>
                                    <td><strong>${key}</strong></td>
                                    <td>${oldVal}</td>
                                    <td class="text-primary fw-semibold">${safeNewVal}</td>
                                </tr>
                            `);
                    });
                }

                const approveBtn = $(`
                            <button class="btn btn-outline-success btn-approved" data-id="${record_id}" data-table="customer_ledger">
                                <i class="fas fa-check"></i> Approve
                            </button>
                        `);
                const rejectBtn = $(`
                            <button class="btn btn-outline-danger btn-rejected" data-id="${record_id}" data-table="customer_ledger">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        `);

                // Append buttons before the Close button
                $modalFooter.prepend(approveBtn, rejectBtn);

                // Show Bootstrap modal
                const modal = new bootstrap.Modal($('#viewChangesModal')[0]);
                modal.show();
            });
            // Approve voucher
            $(document).on('click', '.btn-approved', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                const table = $(this).data('table');
                const container = $(this).closest(
                    '.admin_approval'); // full container to remove

                Swal.fire({
                    title: 'Approve Voucher?',
                    text: 'Are you sure you want to approve this voucher?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Approve',
                    cancelButtonText: 'Cancel',
                    customClass: {
                        confirmButton: 'btn btn-success me-2',
                        cancelButton: 'btn btn-secondary'
                    },
                    buttonsStyling: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: "{{ route('finance.voucher.approve', ['id' => 'ID_PLACEHOLDER']) }}"
                                .replace('ID_PLACEHOLDER', id),
                            type: 'POST',
                            data: {
                                _token: '{{ csrf_token() }}',
                                table: table
                            },
                            success: function() {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Approved!',
                                    text: 'Voucher approved successfully.',
                                    timer: 100,
                                    showConfirmButton: false
                                });
                                // 🗑 Remove container completely
                                refreshExtraCashTable();

                            },
                            error: function(xhr) {
                                const error = xhr.responseJSON?.message ||
                                    'Something went wrong.';
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: error
                                });
                            }
                        });
                    }
                });
            });
            // Reject voucher
            $(document).on('click', '.admin_approval .btn-rejected, #viewChangesModal .btn-rejected', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                const table = $(this).data('table');
                const container = $(this).closest('.admin_approval');

                Swal.fire({
                    title: 'Reject Voucher?',
                    text: 'Are you sure you want to reject this voucher?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Reject',
                    cancelButtonText: 'Cancel',
                    customClass: {
                        confirmButton: 'btn btn-danger me-2',
                        cancelButton: 'btn btn-secondary'
                    },
                    buttonsStyling: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: "{{ route('finance.voucher.reject', ['id' => 'ID_PLACEHOLDER']) }}"
                                .replace('ID_PLACEHOLDER', id),
                            type: 'POST',
                            data: {
                                _token: '{{ csrf_token() }}',
                                table: table,
                            },
                            success: function() {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Rejected!',
                                    text: 'Voucher rejected successfully.',
                                    timer: 100,
                                    showConfirmButton: false
                                });
                                // 🗑 Remove container completely
                                refreshExtraCashTable();
                            },
                            error: function(xhr) {
                                const error = xhr.responseJSON?.message ||
                                    'Something went wrong.';
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: error
                                });
                            }
                        });
                    }
                });
            });

        });


        function convertToWords() {
            // Get the value entered in the input field
            var numberInput = $('#numberInput').val();

            // Remove commas for thousands separator
            var numericValue = numberInput.replace(/,/g, '');

            // Convert the entered number to English wording
            var wordingAmount = numberToWords.toWords(numericValue);

            // Display the English wording amount in the container
            $('#extraAmountWords').val(wordingAmount);
        }

        function formatAmount(input) {
            // Remove non-numeric characters and leading zeros
            let value = input.value.replace(/[^0-9]/g, '').replace(/^0+/, '');

            // Add commas for thousands separator
            value = value.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

            // Update the input value
            input.value = value;
        }
    </script>
@endsection
