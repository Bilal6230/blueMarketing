@extends('admin.layouts.master')
@section('content')

    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <span class="{{$class}}">
                            {{ $title }}
                        </span>
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modal-charge-type">Add Charge Type</button>
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
                                    <div>
                                        <form action="{{ route('booking.customer.deposit') }}" method="POST" enctype="multipart/form-data" id="voucherForm">
                                            @csrf

                                            <div class="row">

                                                <div class="col-sm-12">
                                                    <div class="row">
                                                        <div class="col-sm-3">
                                                            <div class="input-group">
                                                                <label class="fbox">Voucher No.</label>
                                                                <div class="input-group">
                                                                    <input type="text" value="extra_charge" name="action" hidden />
                                                                    <input type="text"  class="form-control " name="voucher" value="{{$type}}-{{get_new_booking_voucher($type)}}" autocomplete="off" readonly>

                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-sm-3">
                                                            <div class="input-group">
                                                                <label class="fbox">Slip No.</label>
                                                                <div class="input-group">

                                                                    <input type="text" class="form-control @error('reference') is-invalid @enderror" name="reference" value="{{ old('reference') }}" autocomplete="off">
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
                                                                    <input type="text" name="date" class="date form-control" data-input>
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
                                                                    <select class="form-control select2" name="charge_type_id" id="charge_type">
                                                                        <option value="">Select an option</option>
                                                                        @foreach ($chargeTypes as $v)
                                                                            <option value="{{ $v->id }}">{{ $v->name }}</option>
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
                                                                    <select class="form-control select2" name="customer_id" id="customer_id">
                                                                        <option value="">Select Customer</option>
                                                                        @foreach ($customers as $v)
                                                                            <option value="{{ $v->id }}">{{ $v->first_name }} {{ $v->last_name }} {{ $v->relate }} {{ $v->father_name }} - {{ $v->phone_number }}</option>
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
                                                                    <select class="form-control select2" name="plot_id" id="plot_id">
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
                                                                    <input id="numberInput" oninput="formatAmount(this)" type="text" class="form-control @error('amount') is-invalid @enderror" placeholder="Amount" name="amount" value="{{ old('amount') }}"  >
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
                                                                <select class="form-control select2" name="payment_type" id="payment_type">
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
                                                        <div class="input-group bank_group"  style="display: none"  >
                                                            <label class="fbox">Number</label>
                                                            <div class="input-group">
                                                                <input id="t_number" type="text" class="form-control @error('t_number') is-invalid @enderror" placeholder="Transaction Number" name="t_number" value="{{ old('t_number') }}"  >
                                                                @error('amount')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                                @enderror
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-3" >
                                                        <div class="input-group bank_group" style="display: none">
                                                            <label class="fbox">Bank</label>
                                                            <div class="input-group">
                                                                <select class="form-control select2" name="bank_id" id="bank_id">
                                                                    <option value="">Bank</option>

                                                                    @foreach (getPakistanBanks() as $v)
                                                                        <option value="{{ $v['id'] }}">{{ $v['name'] }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>

                                                        </div>
                                                    </div>

                                                    <div class="col-sm-3"  >
                                                        <div class="input-group bank_group" style="display: none">
                                                            <label class="fbox">Passing Date</label>
                                                            <div class="input-group">
                                                                <input type="text" name="passing_date" class="date form-control" data-input>
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
                                                            <div class="input-group {{$bg_voucher}}">
                                                                <div class="input-group">
                                                                    <textarea class="form-control @error('detail') is-invalid @enderror" placeholder="Detail" name="detail" style=" height: 150px;" maxlength="255" >{{ old('detail') }}</textarea>
                                                                    @error('detail')
                                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-sm-6">
                                                            <div class="{{$bg_voucher}}">
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
                                                            <button type="button" class="btn btn-primary btn-lg btn-block" data-toggle="modal" data-target="#confirmModal">Save</button>


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
                                <div class="card-body table-responsive">
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
                                                <th>Type</th>
                                                <th>Approve</th>


                                                @canany(['delete slip','can approve'])
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
                                                        {{ $i->customer_list->first_name ?? '' }} {{ $i->customer_list->last_name ?? '' }}  {{ $i->customer_list->relate ?? '' }} {{ $i->customer_list->last_name ?? '' }}
                                                    </td>
                                                    <td>
                                                        {{ $i->plot_list->name ?? '' }}

                                                    </td>
                                                    <td>
                                                        {{ $i->reference ?? '' }}

                                                    </td>
                                                    <td>
                                                        {{ $i->description }}
                                                    </td>
                                                    <td>
                                                        {{ Setting::roundformatAmount($i->amount_out)  }}
                                                    </td>

                                                    <td>
                                                        <span class="badge {{ getPaymentTypeDetails($i->payment_type)['badge'] }}" style="width: 80%">
                                                            {{ getPaymentTypeDetails($i->payment_type)['name'] }}
                                                        </span>
                                                    </td>

                                                    <td>
                                                        {{ approveStatus($i->is_approve) }}
                                                    </td>


                                                    @canany(['update voucher','delete slip','can approve'])
                                                        <td>
                                                            <div class="btn-group">
                                                                @can('update voucher')
                                                                    <button class="btn btn-sm btn-primary btn-edit" data-id="{{ $i->id }}"><i class="fas fa-pencil-alt"></i></button>
                                                                @endcan
                                                                @can('can approve')
                                                                    <button class="btn btn-sm btn-secondary btn-view" data-id="{{ $i->id }}" data-name="{{ $i->name }}"><i class="fas fa-eye"></i></button>
                                                                @endcan
                                                                @can('delete slip')
                                                                    <button class="btn btn-sm btn-danger btn-delete" data-id="{{ $i->id }}" data-name="{{ $i->name }}"><i class="fas fa-trash"></i></button>
                                                                @endcan

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
    <div class="modal fade" id="modal-charge-type">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header {{$class}}">
                    <h4 class="modal-title">Add Charge Type</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('charge-type.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="row">

                            <div class="col-sm-12">
                                <div class="row">
                                    <div class="col-12">
                                        <div class="input-group">
                                            <label class="fbox">Name</label>
                                            <div class="input-group">
                                                <input id="name" type="text"  class="form-control " name="name" autocomplete="off" required>
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
                <div class="modal-header {{$class}}">
                    <h4 class="modal-title">Edit Voucher</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('ledger.update') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method("PUT")
                        <div class="row">

                            <div class="col-sm-12">
                                <div class="row">
                                    <div class="col-sm-3">
                                        <div class="input-group">
                                            <label class="fbox">Voucher No.</label>
                                            <div class="input-group">
                                                <input type="text" value="1" name="action" hidden />
                                                <input id="voucher" type="text"  class="form-control " name="voucher" autocomplete="off" readonly >

                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-3">
                                        <div class="input-group">
                                            <label class="fbox">Reference</label>
                                            <div class="input-group">

                                                <input id="reference" type="text" class="form-control @error('reference') is-invalid @enderror" name="reference" value="{{ old('reference') }}" autocomplete="off">
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
                                                <input type="text" id="date" name="date" class="date_database form-control" data-input>
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
                                                <select class="form-control select2" name="projects_id" id="e_projects_id">
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
                                                <select class="form-control select2" name="accounts_id" id="e_accounts_id">
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
                                                <select class="form-control select2" name="subaccounts_id" id="e_subaccounts_id">
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
                                                <input id="amount" oninput="formatAmount(this)" type="text" class="form-control @error('amount') is-invalid @enderror" placeholder="Amount" name="amount" value="{{ old('amount') }}"  >
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
                                        <textarea id="detail" class="form-control @error('detail') is-invalid @enderror" placeholder="Detail" name="detail" style=" height: 150px;" maxlength="255" >{{ old('detail') }}</textarea>
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
                        <p class="modal-text" id="msg">Are you sure you want to Approve? <b id="delete-data"></b></p>
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
                <div class="modal-header {{$class}}">
                    <h4 class="modal-title">View Slip</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('ledger.update') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method("PUT")
                        <div class="row">

                            <div class="col-sm-6">
                                <div class="row">
                                    <div class="col-sm-6">
                                        <span>Slip</span>
                                    </div>
                                    <div class="col-sm-6">
                                        <span>Slip Number </span>
                                    </div>

                                </div>
                                <div class="row">
                                    <div class="col-sm-6">
                                        <span>Customer</span>
                                    </div>
                                    <div class="col-sm-6">
                                        <span>Ali </span>
                                    </div>

                                </div>
                                <div class="row">
                                    <div class="col-sm-6">
                                        <span>Plot</span>
                                    </div>
                                    <div class="col-sm-6">
                                        <span>Ali </span>
                                    </div>

                                </div>
                                <div class="row">
                                    <div class="col-sm-6">
                                        <span>Plot</span>
                                    </div>
                                    <div class="col-sm-6">
                                        <span>Ali </span>
                                    </div>

                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="row">
                                    <div class="col-sm-6">
                                        <span>Date</span>
                                    </div>
                                    <div class="col-sm-6">
                                        <span>Date Here</span>
                                    </div>

                                </div>
                                <div class="row">
                                    <div class="col-sm-6">
                                        <span>Phone</span>
                                    </div>
                                    <div class="col-sm-6">
                                        <span>Date Here</span>
                                    </div>

                                </div>
                                <div class="row">
                                    <div class="col-sm-6">
                                        <span>Size</span>
                                    </div>
                                    <div class="col-sm-6">
                                        <span>Date Here</span>
                                    </div>

                                </div>
                            </div>

                        </div>


                        <div class="modal-footer justify-content-between">
                            <button type="button" class="btn btn-default btn-rejected" data-dismiss="modal" data-id='' data-name=''>Rejected</button>
                            <button type="button" class="btn btn-primary btn-approve" data-dismiss="modal" data-id='' data-name='' >Approve</button>

                        </div>
                    </form>
                </div>
                <!-- /.modal-content -->
            </div>
            <!-- /.modal-dialog -->
        </div>
    </div>
@endsection

@section('js')
    <script>

            function submitForm() {
                document.getElementById('voucherForm').submit();
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
                            $('#customer_id').append('<option value="' + customer.id + '" data-phone="'+ customer.mobile_number +'" data-nic_number="'+ customer.nic_number +'" data-home_address="'+ customer.home_address +'">' + customer.first_name + ' ' + customer.last_name + ' '+ customer.relate +' '+ customer.father_name + ' - ' + customer.phone_number + '</option>');
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

            $('#customer_id').change(function (e) {
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
                            $('#plot_id').append('<option value="' + plot.plot_id + '">' + plotType + ' ' + plot.name +  '  </option>');
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
                $('#modal-delete').modal({backdrop: 'static', keyboard: false, show: true});
            });

            $(document).on("click", '.btn-view', function() {
                debugger;
                let id = $(this).attr("data-id");
                $('#modal-loading').modal({backdrop: 'static', keyboard: false, show: true});
                $.ajax({
                    url: "{{ route('booking.voucher.show') }}",
                    type: "POST",
                    dataType: "JSON",
                    data: {
                        id: id,
                        _token: "{{ csrf_token() }}"
                    },
                    success: function(data) {
                        debugger;
                        var data = data.data;
                        $("#id").val(data.id);
                        $("#reference").val(data.reference);
                        $('.btn-approve').attr('data-id', data.id);
                        $('.btn-rejected').attr('data-id', data.id);



                        $("#description").val(data.description);

                        $('#modal-loading').modal('hide');
                        $('#modal-view').modal({backdrop: 'static', keyboard: false, show: true});
                    },
                });
            });

            $(document).on("click", '.btn-approve', function() {
                let id = $(this).attr("data-id");
                $(".status_id").val(id);
                $(".is_approve").val(1);
                $('#msg').text("Are you sure you want to Approve?");
                $('#modal-approve').modal({backdrop: 'static', keyboard: false, show: true});
            });
            $(document).on("click", '.btn-rejected', function() {
                let id = $(this).attr("data-id");
                $(".status_id").val(id);
                $(".is_approve").val(2);
                $('#msg').text("Are you sure you want to rejected?");
                $('#modal-approve').modal({backdrop: 'static', keyboard: false, show: true});
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
