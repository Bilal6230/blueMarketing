@extends('admin.layouts.master')
@section('content')

    <div class="content-wrapper">
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
                        <div class="card">
                            @can('add slip')
                                <div class="card-header">
                                    @if ($errors->has('reference') || $errors->has('msg'))
                                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            <strong>Unable to save received payment.</strong>
                                            <div>{{ $errors->first('reference') ?: $errors->first('msg') }}</div>
                                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                    @endif
                                    <div>
                                        <form action="{{ route('booking.customer.deposit') }}" method="POST"
                                            enctype="multipart/form-data" id="voucherForm">
                                            @csrf

                                            <div class="row">

                                                <div class="col-sm-12">
                                                    <div class="row">
                                                        <div class="col-sm-3">
                                                            <div class="input-group">
                                                                <label class="fbox">Voucher No.</label>
                                                                <div class="input-group">
                                                                    <input type="text" value="deposit" name="action"
                                                                        hidden />
                                                                    <input type="text" class="form-control " name="voucher"
                                                                        value="{{ old('voucher', $type . '-' . ($nextVoucherNumber ?? getVocuherNumber($type))) }}"
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
                                                        <div class="col-sm-12">
                                                            <div class="input-group">
                                                                <label class="fbox">Customer</label>
                                                                <div class="input-group">
                                                                    <select class="form-control select2" name="customer_id"
                                                                        id="customer_id">
                                                                        <option value="">Select Customer</option>

                                                                        {{-- @foreach ($headaccounts as $v)
                                                                            <option value="{{ $v->id }}">{{ $v->name }}</option>
                                                                        @endforeach --}}
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
                                                                    <select class="form-control select2" name="plot_id"
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
                                                    <div id="wordingAmount"></div>
                                                </div>
                                            </div>

                                            <div class="col-sm-12">
                                                <div class="row">
                                                    <div class="col-sm-3">
                                                        <div class="input-group">
                                                            <label class="fbox">Payment Type</label>
                                                            <div class="input-group">
                                                                <select class="form-control select2" name="payment_type"
                                                                    id="payment_type">
                                                                    <option value="1">Cash</option>
                                                                    <option value="2">Online</option>
                                                                    <option value="3">Check</option>
                                                                </select>
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
                                                                <select class="form-control select2" name="bank_id"
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
                                                            <button type="button" class="btn btn-primary btn-lg btn-block"
                                                                data-toggle="modal" data-target="#confirmModal">Save</button>


                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endcan
                            <!-- /.card-header -->
                            @can('read slip')
                                <div class="card-body">
                                    <form action="{{ route('payment_schedule.cash') }}" method="GET" class="mb-3">
                                        <div class="row">
                                            <div class="col-md-2">
                                                <label class="fbox">Customer</label>
                                                <select class="form-control select2" name="customer_id">
                                                    <option value="">All Customers</option>
                                                    @foreach ($customers as $customer)
                                                        <option value="{{ $customer['value'] }}"
                                                            {{ (string) ($filters['customer_id'] ?? '') === (string) $customer['value'] ? 'selected' : '' }}>
                                                            {{ $customer['label'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="fbox">Plot</label>
                                                <select class="form-control select2" name="plot_id">
                                                    <option value="">All Plots</option>
                                                    @foreach ($plots as $plot)
                                                        <option value="{{ $plot['value'] }}"
                                                            {{ (string) ($filters['plot_id'] ?? '') === (string) $plot['value'] ? 'selected' : '' }}>
                                                            {{ $plot['label'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="fbox">Reference</label>
                                                <input type="text" class="form-control" name="reference"
                                                    value="{{ $filters['reference'] ?? '' }}" placeholder="Slip reference">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="fbox">Date From</label>
                                                <input type="date" class="form-control" name="fdate"
                                                    value="{{ $filters['fdate'] ?? '' }}">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="fbox">Date To</label>
                                                <input type="date" class="form-control" name="tdate"
                                                    value="{{ $filters['tdate'] ?? '' }}">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="fbox">Status</label>
                                                <select class="form-control" name="status">
                                                    <option value="">All Statuses</option>
                                                    <option value="posted" {{ (string) ($filters['status'] ?? '') === 'posted' ? 'selected' : '' }}>Posted</option>
                                                    <option value="pending" {{ (string) ($filters['status'] ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                                                    <option value="1" {{ (string) ($filters['status'] ?? '') === '1' ? 'selected' : '' }}>Passed</option>
                                                    <option value="2" {{ (string) ($filters['status'] ?? '') === '2' ? 'selected' : '' }}>Returned</option>
                                                    <option value="3" {{ (string) ($filters['status'] ?? '') === '3' ? 'selected' : '' }}>Bounced</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="mt-3 d-flex gap-2">
                                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                                            <a href="{{ route('payment_schedule.cash') }}" class="btn btn-default">Reset</a>
                                        </div>
                                    </form>
                                </div>
                                <div class="card-body table-responsive">
                                    <table id="example1" class="table table-striped table-bordered " style="width:100%">
                                        <thead>
                                            <tr>
                                                <th style="width: 5px">#</th>
                                                <th style="width: 60px">Date</th>

                                                <th>Customer</th>
                                                <th style="width: 5px">Plot</th>
                                                <th style="width: 5px">Ref</th>
                                                <th style="width: 5px">Voucher</th>
                                                <th>Detail</th>
                                                <th>Amount</th>
                                                <th>Type</th>
                                                <th>Status</th>


                                                @canany(['read slip', 'delete slip', 'can approve'])
                                                    <th>Action</th>
                                                @endcanany
                                            </tr>
                                        </thead>
                                            <tbody>
                                                @foreach ($data as $i)
                                                    @php
                                                        $customerLedger = $i->customerLedger;
                                                        $ledger = $i->ledger;
                                                        $ledgerProjectHeadSubhead = optional($ledger)->projectHeadSubhead;
	                                                        $ledgerSubhead = optional($ledgerProjectHeadSubhead)->subheadAccounting;
	                                                        $ledgerPlot = optional($ledgerProjectHeadSubhead)->plot;
	                                                        $fallbackPlotId = optional($ledgerProjectHeadSubhead)->plot_id;
                                                        $customer = $i->customer ?: optional($customerLedger)->customer_list;
                                                        $plot = $i->plot ?: optional($customerLedger)->plot_list;
                                                        $customerName = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
                                                        if ($customerName === '') {
                                                            $customerName = $ledgerSubhead->name ?? '—';
                                                        }
                                                        $plotSource = $plot ?: $ledgerPlot;
                                                        $plotPrefix = ($plotSource->type ?? null) == 1 ? 'R-' : (($plotSource->type ?? null) == 2 ? 'C-' : '');
                                                        $plotName = $plotSource ? $plotPrefix . $plotSource->name : '—';
                                                        $referenceValue = $i->reference
                                                            ?? $i->ledger->reference
                                                            ?? (($i->ledger->type ?? null) && ($i->ledger->voucher_number ?? $i->ledger->voucher ?? null)
                                                                ? $i->ledger->type . '-' . ($i->ledger->voucher_number ?? $i->ledger->voucher)
                                                                : null)
                                                            ?? '—';
                                                        $plotName = $i->plot_list ? $plotPrefix . $i->plot_list->name : '—';
                                                        $voucherNumber = $i->ledger->voucher_number ?? $i->ledger->voucher ?? '—';
                                                        $plotName = $plotSource ? $plotPrefix . $plotSource->name : '—';
                                                        $voucherNumber = $i->ledger->voucher_number ?? $i->ledger->voucher ?? '—';
                                                        $referenceValue = $i->reference
                                                            ?? $i->ledger->reference
                                                            ?? $i->ledger->voucher_number
                                                            ?? $i->ledger->voucher
                                                            ?? 'â€”';
                                                        $voucherNumber = !empty($i->voucher_series) && !empty($i->voucher_number)
                                                            ? $i->voucher_series . '-' . $i->voucher_number
                                                            : ($i->ledger->voucher_number ?? $i->ledger->voucher ?? 'â€”');
	                                                        $plotName = ($plotSource && !empty($plotSource->name))
	                                                            ? Setting::getPlotTypeShort($plotSource->type) . '-' . $plotSource->name
	                                                            : ($plotLabels[$fallbackPlotId] ?? '—');
	                                                        $i->reference = $referenceValue;
                                                        $plotSource = $plot ?: $ledgerPlot;
                                                        $plotName = ($plotSource && !empty($plotSource->name))
                                                            ? Setting::getPlotTypeShort($plotSource->type) . '-' . $plotSource->name
                                                            : ($plotLabels[$fallbackPlotId] ?? '-');
                                                        $voucherNumber = !empty($i->voucher_series) && !empty($i->voucher_number)
                                                            ? $i->voucher_series . '-' . $i->voucher_number
                                                            : ((optional($ledger)->type && (optional($ledger)->voucher_number ?? optional($ledger)->voucher))
                                                                ? optional($ledger)->type . '-' . (optional($ledger)->voucher_number ?? optional($ledger)->voucher)
                                                                : '-');
                                                        $referenceValue = $i->slip_reference
                                                            ?? optional($customerLedger)->reference
                                                            ?? optional($ledger)->reference
                                                            ?? '-';
                                                        $i->reference = $referenceValue;
                                                    @endphp
                                                    <tr>
                                                        <td>{{ $loop->iteration }}</td>
                                                        <td>
                                                            {{ $i->receipt_date ?? $i->date ?? optional($customerLedger)->date ?? '-' }}
                                                        </td>

                                                        <td>
                                                            {{ $customerName ?: '-' }}
                                                        </td>
                                                        <td>
                                                            {{ $plotName }}

                                                        </td>
                                                        <td>
                                                            {{ $i->slip_reference ?? $i->reference ?? optional($customerLedger)->reference ?? '-' }}
                                                        </td>
                                                        <td>
                                                            {{ (!empty($i->voucher_series) && !empty($i->voucher_number)) ? $i->voucher_series . '-' . $i->voucher_number : ($voucherNumber ?: '-') }}

                                                        </td>
                                                        <td>
                                                            {{ $i->description ?: optional($customerLedger)->description ?: '-' }}
                                                        </td>
                                                        <td>
                                                            {{ Setting::roundformatAmount($i->amount ?? $i->amount_out ?? optional($customerLedger)->amount_out ?? 0) }}
                                                        </td>

                                                        <td>
                                                            <span
                                                                class="badge {{ getPaymentTypeDetails($i->payment_type)['badge'] }}"
                                                                style="width: 80%">
                                                                {{ getPaymentTypeDetails($i->payment_type)['name'] }}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge {{ $i->payment_status_badge_class ?? 'badge-warning' }}">
                                                                {{ $i->payment_status_label ?? 'Pending' }}
                                                            </span>
                                                        </td>


                                                    @canany(['read slip', 'update voucher', 'delete slip', 'can approve'])
                                                        <td>
                                                            <div class="btn-group">
                                                                @can('update voucher')
                                                                    <button class="btn btn-sm btn-primary btn-edit"
                                                                        data-id="{{ $customerLedger->id ?? $i->customer_ledger_id }}"
                                                                        data-action-template="{{ route('payment_schedule.cash.update', ['id' => '__id__']) }}"
                                                                        data-voucher="{{ $voucherNumber }}"
                                                                        data-reference="{{ $i->slip_reference ?? $i->reference ?? optional($customerLedger)->reference ?? '—' }}"
                                                                        data-date="{{ $i->receipt_date ?? $i->date ?? optional($customerLedger)->date }}"
                                                                        data-amount="{{ $i->amount ?? $i->amount_out ?? optional($customerLedger)->amount_out }}"
                                                                        data-detail="{{ $i->description ?? optional($customerLedger)->description }}"
                                                                        data-payment_type="{{ $i->payment_type }}"
                                                                        data-t_number="{{ $i->t_number }}"
                                                                        data-bank_id="{{ $i->bank_id }}"
                                                                        data-passing_date="{{ $i->passing_date }}"
                                                                        data-customer="{{ $customerName }}"
                                                                        data-plot="{{ $plotName }}">
                                                                        <i class="fas fa-pencil-alt"></i>
                                                                    </button>
                                                                @endcan

                                                                <a href="{{ route('booking.customer.print', ['id' => $customerLedger->id ?? $i->customer_ledger_id]) }}"
                                                                    class="btn btn-sm btn-info" target="_blank"
                                                                    title="Print">
                                                                    <i class="fas fa-print"></i>
                                                                </a>

                                                                @can('can approve')
                                                                    <button class="btn btn-sm btn-secondary btn-view"
                                                                        data-id="{{ $customerLedger->id ?? $i->customer_ledger_id }}"
                                                                        data-name="{{ $i->name }}"><i
                                                                            class="fas fa-eye"></i></button>
                                                                @endcan
	                                                                @can('delete slip')
	                                                                    <button class="btn btn-sm btn-danger btn-delete"
	                                                                        data-id="{{ $customerLedger->id ?? $i->customer_ledger_id }}"
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
	                                                                                data-record_id='{{ $customerLedger->id ?? $i->customer_ledger_id }}'>
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
                            @endcan

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
                    <form id="updateForm"
                        action="{{ route('payment_schedule.cash.update', ['id' => '__id__']) }}"
                        data-action-template="{{ route('payment_schedule.cash.update', ['id' => '__id__']) }}"
                        method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('POST')
                        <div class="row">
                            <div class="col-sm-4">
                                <div class="form-group">
                                    <label class="fbox">Voucher No.</label>
                                    <input id="edit_voucher" type="text" class="form-control" readonly>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="form-group">
                                    <label class="fbox">Customer</label>
                                    <input id="edit_customer" type="text" class="form-control" readonly>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="form-group">
                                    <label class="fbox">Plot</label>
                                    <input id="edit_plot" type="text" class="form-control" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-4">
                                <div class="form-group">
                                    <label class="fbox">Reference</label>
                                    <input id="edit_reference" type="text"
                                        class="form-control @error('reference') is-invalid @enderror"
                                        name="reference" value="{{ old('reference') }}" autocomplete="off">
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="form-group">
                                    <label class="fbox">Date</label>
                                    <input type="date" id="edit_date" name="date"
                                        class="form-control @error('date') is-invalid @enderror"
                                        value="{{ old('date') }}">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-4">
                                <div class="form-group">
                                    <label class="fbox">Amount</label>
                                    <input id="edit_amount" oninput="formatAmount(this)" type="text"
                                        class="form-control @error('amount_out') is-invalid @enderror"
                                        placeholder="Amount" name="amount_out" value="{{ old('amount_out') }}">
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="form-group">
                                    <label class="fbox">Payment Type</label>
                                    <select class="form-control" name="payment_type" id="edit_payment_type">
                                        <option value="1">Cash</option>
                                        <option value="2">Online</option>
                                        <option value="3">Check</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-4 edit-bank-group" style="display:none;">
                                <div class="form-group">
                                    <label class="fbox">Number</label>
                                    <input id="edit_t_number" type="text" class="form-control" name="t_number"
                                        value="{{ old('t_number') }}">
                                </div>
                            </div>
                        </div>
                        <div class="row edit-bank-group" style="display:none;">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label class="fbox">Bank</label>
                                    <select class="form-control" name="bank_id" id="edit_bank_id">
                                        <option value="">Bank</option>
                                        @foreach (getPakistanBanks() as $v)
                                            <option value="{{ $v['id'] }}">{{ $v['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label class="fbox">Passing Date</label>
                                    <input type="date" id="edit_passing_date" name="passing_date"
                                        class="form-control" value="{{ old('passing_date') }}">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-12">
                                <div class="form-group">
                                    <label class="fbox">Detail</label>
                                    <textarea id="edit_description" class="form-control @error('description') is-invalid @enderror"
                                        placeholder="Detail" name="description" style="height: 150px;" maxlength="255">{{ old('description') }}</textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer justify-content-between">
                            <input type="hidden" name="id" id="edit_id">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Update</button>
                        </div>
                    </form>
                </div>
            </div>
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
                    <form action="{{ route('booking.customer.destroy') }}" method="POST" enctype="multipart/form-data"
                        id="receivedPaymentDeleteForm">
                        @csrf
                        @method('DELETE')
                        <p class="modal-text" data-default-text="Are you sure you want to delete?">Are you sure you want to delete? <b id="delete-data"></b></p>
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

    {{-- Modal approve --}}
    <div class="modal fade" id="modal-approve">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Approve Voucher</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('booking.customer.approve') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('POST')
                        <p class="modal-text" id="msg">Are you sure you want to Approve? <b id="delete-data"></b>
                        </p>
                        <input type="hidden" name="id" class="status_id">
                        <input type="hidden" name="is_approve" class="is_approve" value="0">
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

    {{-- Modal view --}}
    <div class="modal fade" id="modal-view">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header {{ $class }}">
                    <h4 class="modal-title">View Slip</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-sm-6">
                            <table class="table table-sm table-bordered">
                                <tr>
                                    <th>Voucher</th>
                                    <td id="view_voucher_number">—</td>
                                </tr>
                                <tr>
                                    <th>Reference</th>
                                    <td id="view_reference">—</td>
                                </tr>
                                <tr>
                                    <th>Date</th>
                                    <td id="view_date">—</td>
                                </tr>
                                <tr>
                                    <th>Amount</th>
                                    <td id="view_amount">—</td>
                                </tr>
                                <tr>
                                    <th>Payment Type</th>
                                    <td id="view_payment_type">—</td>
                                </tr>
                                <tr>
                                    <th>Transaction No.</th>
                                    <td id="view_t_number">—</td>
                                </tr>
                                <tr>
                                    <th>Bank</th>
                                    <td id="view_bank">—</td>
                                </tr>
                                <tr>
                                    <th>Passing Date</th>
                                    <td id="view_passing_date">—</td>
                                </tr>
                                <tr>
                                    <th>Status</th>
                                    <td id="view_status">—</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-sm-6">
                            <table class="table table-sm table-bordered">
                                <tr>
                                    <th>Customer</th>
                                    <td id="view_customer">—</td>
                                </tr>
                                <tr>
                                    <th>Phone</th>
                                    <td id="view_phone">—</td>
                                </tr>
                                <tr>
                                    <th>CNIC</th>
                                    <td id="view_cnic">—</td>
                                </tr>
                                <tr>
                                    <th>Project</th>
                                    <td id="view_project">—</td>
                                </tr>
                                <tr>
                                    <th>Plot</th>
                                    <td id="view_plot">—</td>
                                </tr>
                                <tr>
                                    <th>Plot Size</th>
                                    <td id="view_plot_size">—</td>
                                </tr>
                                <tr>
                                    <th>Ledger Ref</th>
                                    <td id="view_ledger_reference">—</td>
                                </tr>
                                <tr>
                                    <th>Created At</th>
                                    <td id="view_created_at">—</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="form-group">
                                <label class="fbox">Address</label>
                                <div id="view_address" class="border rounded p-2 bg-light">—</div>
                            </div>
                            <div class="form-group">
                                <label class="fbox">Remarks</label>
                                <div id="view_description" class="border rounded p-2 bg-light">—</div>
                            </div>
                            <div class="form-group">
                                <label class="fbox">Ledger Detail</label>
                                <div id="view_ledger_detail" class="border rounded p-2 bg-light">—</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <a href="#" class="btn btn-info" id="view_print_link" target="_blank"
                        data-print-template="{{ route('booking.customer.print', ['id' => '__id__']) }}">
                        Print
                    </a>
                    <div>
                        <button type="button" class="btn btn-default btn-rejected" data-dismiss="modal"
                            data-id='' data-name=''>Rejected</button>
                        <button type="button" class="btn btn-primary btn-approve" data-dismiss="modal"
                            data-id='' data-name=''>Approve</button>
                    </div>
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        function submitForm() {
            document.getElementById('voucherForm').submit();
        }


        $(document).ready(function() {

            $(document).ready(function() {
                $('#payment_type').change(function(e) {
                    e.preventDefault();

                    if ($(this).val() != '1') {
                        $('.bank_group').css('display', 'block');
                    } else {
                        $('.bank_group').css('display', 'none');
                    }
                });

                function toggleEditBankGroup(paymentType) {
                    if (paymentType && paymentType !== '1') {
                        $('.edit-bank-group').show();
                    } else {
                        $('.edit-bank-group').hide();
                        $('#edit_t_number').val('');
                        $('#edit_bank_id').val('');
                        $('#edit_passing_date').val('');
                    }
                }

                $('#edit_payment_type').change(function() {
                    toggleEditBankGroup($(this).val());
                });

                $(document).on('click', '.btn-edit', function() {
                    const id = $(this).data('id');
                    const template = $('#updateForm').data('action-template');
                    const paymentType = String($(this).data('payment_type') || '1');

                    $('#updateForm').attr('action', template.replace('__id__', id));
                    $('#edit_id').val(id);
                    $('#edit_voucher').val($(this).data('voucher') || '—');
                    $('#edit_customer').val($(this).data('customer') || '—');
                    $('#edit_plot').val($(this).data('plot') || '—');
                    $('#edit_reference').val($(this).data('reference') || '');
                    $('#edit_date').val($(this).data('date') || '');
                    $('#edit_amount').val($(this).data('amount') || '');
                    $('#edit_description').val($(this).data('detail') || '');
                    $('#edit_payment_type').val(paymentType);
                    $('#edit_t_number').val($(this).data('t_number') || '');
                    $('#edit_bank_id').val($(this).data('bank_id') || '');
                    $('#edit_passing_date').val($(this).data('passing_date') || '');
                    toggleEditBankGroup(paymentType);
                    $('#modal-edit').modal('show');
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
                                    window.location.reload();

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
                $(document).on('click', '.btn-rejected', function(e) {
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
                                    window.location.reload();
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


            function fetchCustomers() {
                $.ajax({
                    url: '/admin/get-customers-byplot', // URL to your route
                    type: 'POST', // Use POST method for sending data
                    data: {
                        _token: '{{ csrf_token() }}' // Add CSRF token
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


            fetchCustomers();

            $('#customer_id').change(function(e) {
                e.preventDefault();
                var customerId = $(this).val();

                if (customerId) {
                    fetchPlots(customerId);
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
                        customer_id: customerId // Pass project ID to server
                    },
                    success: function(data) {
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

            $(document).on("click", '.btn-delete', function() {
                let id = $(this).attr("data-id");
                let name = $(this).attr("data-name");
                $("#did").val(id);
                receivedPaymentIsDeleting = false;
                setDeleteModalProcessing($('#modal-delete'), false);
                $('#modal-delete').modal({
                    backdrop: 'static',
                    keyboard: false,
                    show: true
                });
            });

            let receivedPaymentIsDeleting = false;

            function setDeleteModalProcessing($modal, isProcessing, message = null) {
                const $text = $modal.find('.modal-text');
                const defaultText = $text.data('default-text') || 'Are you sure you want to delete?';
                const $submitButton = $modal.find('button[type="submit"]');
                const $closeButtons = $modal.find('[data-dismiss="modal"], .close, .btn-default');

                if (!$submitButton.data('default-html')) {
                    $submitButton.data('default-html', $submitButton.html());
                }

                if (isProcessing) {
                    $submitButton
                        .prop('disabled', true)
                        .html('<span class="spinner-border spinner-border-sm mr-1"></span> Checking ledger...');
                    $closeButtons.prop('disabled', true);
                    $text.text(message || 'Checking voucher status in ledger. Please wait...');
                    return;
                }

                $submitButton
                    .prop('disabled', false)
                    .html($submitButton.data('default-html') || 'Yes');
                $closeButtons.prop('disabled', false);
                $text.html(defaultText + ' <b id="delete-data"></b>');
            }

            $('#receivedPaymentDeleteForm').on('submit', function(e) {
                e.preventDefault();

                if (receivedPaymentIsDeleting) {
                    return;
                }

                const $form = $(this);
                const $modal = $('#modal-delete');
                receivedPaymentIsDeleting = true;

                setDeleteModalProcessing($modal, true);

                $.ajax({
                    url: $form.attr('action'),
                    type: 'POST',
                    dataType: 'json',
                    headers: {
                        'Accept': 'application/json'
                    },
                    data: $form.serialize(),
                    success: function(response) {
                        $('#modal-delete').modal('hide');
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted',
                            text: response?.message || 'Voucher deleted successfully.'
                        }).then(() => {
                            window.location.reload();
                        });
                    },
                    error: function(xhr) {
                        const response = xhr?.responseJSON || {};
                        const isPostedBlock = response?.error_key === 'voucher_posted_to_ledger';

                        Swal.fire({
                            icon: isPostedBlock ? 'warning' : 'error',
                            title: isPostedBlock ? 'Cannot Delete' : 'Delete Failed',
                            text: isPostedBlock
                                ? 'Cannot delete this received-payment voucher because it has already been posted to the ledger.'
                                : (response?.message || 'Unable to delete voucher.')
                        });
                    },
                    complete: function() {
                        setDeleteModalProcessing($modal, false);
                        receivedPaymentIsDeleting = false;
                    }
                });
            });

            $(document).on("click", '.btn-view', function() {
                let id = $(this).attr("data-id");
                $('#modal-loading').modal({
                    backdrop: 'static',
                    keyboard: false,
                    show: true
                });
                $.ajax({
                    url: "{{ route('booking.voucher.show') }}",
                    type: "POST",
                    dataType: "JSON",
                    data: {
                        id: id,
                        _token: "{{ csrf_token() }}"
                    },
                    success: function(response) {
                        const data = response.data;
                        const printTemplate = $('#view_print_link').data('print-template');

                        $('#view_reference').text(data.reference || '—');
                        $('#view_voucher_number').text(data.ledger?.voucher_number || '—');
                        $('#view_date').text(data.date || '—');
                        $('#view_amount').text(data.amount_out || '—');
                        $('#view_payment_type').text(data.payment_type_label || '—');
                        $('#view_t_number').text(data.t_number || '—');
                        $('#view_bank').text(data.bank_name || data.bank_id || '—');
                        $('#view_passing_date').text(data.passing_date || '—');
                        $('#view_status').text(data.status_label || '—');
                        $('#view_customer').text(data.customer?.name || '—');
                        $('#view_phone').text(data.customer?.phone_number || data.customer?.mobile_number || '—');
                        $('#view_cnic').text(data.customer?.nic_number || '—');
                        $('#view_project').text(data.project?.name || '—');
                        $('#view_plot').text(data.plot?.name ? (((data.plot?.type == 1) ? 'R-' : ((data.plot?.type == 2) ? 'C-' : '')) + data.plot.name) : '—');
                        $('#view_plot_size').text(data.plot?.size ? `${data.plot.size}${data.plot?.unit ? ' ' + data.plot.unit : ''}` : '—');
                        $('#view_ledger_reference').text(data.ledger?.reference || '—');
                        $('#view_created_at').text(data.created_at || '—');
                        $('#view_address').text(data.customer?.home_address || '—');
                        $('#view_description').text(data.description || '—');
                        $('#view_ledger_detail').text(data.ledger?.detail || '—');
                        $('#view_print_link').attr('href', printTemplate.replace('__id__', data.id));
                        $('.btn-approve').attr('data-id', data.id);
                        $('.btn-rejected').attr('data-id', data.id);
                        $('#modal-loading').modal('hide');
                        $('#modal-view').modal({
                            backdrop: 'static',
                            keyboard: false,
                            show: true
                        });
                    },
                    error: function() {
                        $('#modal-loading').modal('hide');
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Unable to load voucher details right now.'
                        });
                    }
                });
            });

            $(document).on("click", '.btn-approve', function() {
                let id = $(this).attr("data-id");
                $(".status_id").val(id);
                $(".is_approve").val(1);
                $('#msg').text("Are you sure you want to Approve?");
                $('#modal-approve').modal({
                    backdrop: 'static',
                    keyboard: false,
                    show: true
                });
            });

            $(document).on("click", '.btn-rejected', function() {
                let id = $(this).attr("data-id");
                $(".status_id").val(id);
                $(".is_approve").val(2);
                $('#msg').text("Are you sure you want to rejected?");
                $('#modal-approve').modal({
                    backdrop: 'static',
                    keyboard: false,
                    show: true
                });
            });





        });

        function convertToWords() {
            // Get the value entered in the input field
            var numberInput = document.getElementById('numberInput').value;

            // Remove commas for thousands separator
            var numericValue = numberInput.replace(/,/g, '');

            // Convert the entered number to English wording
            var wordingAmount = numberToWords.toWords(numericValue);

            // Display the English wording amount in the container
            document.getElementById('wordingAmount').innerText = wordingAmount;
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


