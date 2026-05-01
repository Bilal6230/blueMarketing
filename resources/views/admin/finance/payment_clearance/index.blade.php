@extends('admin.layouts.master')

@section('content')
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">{{ $title }}</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active">{{ $title }}</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-2">
                                <label for="date_from">Date From</label>
                                <input type="date" id="date_from" class="form-control">
                            </div>
                            <div class="col-md-2">
                                <label for="date_to">Date To</label>
                                <input type="date" id="date_to" class="form-control">
                            </div>
                            <div class="col-md-2">
                                <label for="payment_type">Payment Type</label>
                                <select id="payment_type" class="form-control">
                                    <option value="">All</option>
                                    <option value="2">Online</option>
                                    <option value="3">Check</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="status">Status</label>
                                <select id="status" class="form-control">
                                    <option value="">All</option>
                                    <option value="0">Pending</option>
                                    <option value="1">Pass</option>
                                    <option value="2">Return</option>
                                    <option value="3">Cheque Bounce</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="bank_id">Bank</label>
                                <select id="bank_id" class="form-control">
                                    <option value="">All</option>
                                    @foreach ($banks as $bank)
                                        <option value="{{ $bank['id'] }}">{{ $bank['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="voucher_number">Voucher Number</label>
                                <input type="text" id="voucher_number" class="form-control"
                                    placeholder="CR-123 / PPR-242">
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-2">
                                <label for="t_number">Transaction/Cheque No</label>
                                <input type="text" id="t_number" class="form-control" placeholder="Number">
                            </div>
                            <div class="col-md-2">
                                <label for="customer_id">Customer</label>
                                <select id="customer_id" class="form-control">
                                    <option value="">All</option>
                                    @foreach ($customers as $customer)
                                        <option value="{{ $customer->id }}">
                                            {{ trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="plot_id">Plot</label>
                                <select id="plot_id" class="form-control">
                                    <option value="">All</option>
                                    @foreach ($plots as $plot)
                                        <option value="{{ $plot->id }}">
                                            {{ ((int) $plot->type === 1 ? 'R-' : ((int) $plot->type === 2 ? 'C-' : '')) . $plot->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <button type="button" class="btn btn-primary mr-2" id="applyFilters">Apply Filters</button>
                                <button type="button" class="btn btn-secondary" id="resetFilters">Reset</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row" id="clearanceCards">
                    <div class="col-md-2 col-sm-6">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h4 id="card_today_total_amount">0.00</h4>
                                <p>Today Total Amount</p>
                                <small id="card_today_total_amount_count">0 record(s)</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h4 id="card_today_pending">0.00</h4>
                                <p>Today Pending</p>
                                <small id="card_today_pending_count">0 record(s)</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h4 id="card_today_passed">0.00</h4>
                                <p>Today Passed</p>
                                <small id="card_today_passed_count">0 record(s)</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <div class="small-box bg-secondary">
                            <div class="inner">
                                <h4 id="card_today_returned">0.00</h4>
                                <p>Today Returned</p>
                                <small id="card_today_returned_count">0 record(s)</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <div class="small-box bg-danger">
                            <div class="inner">
                                <h4 id="card_today_bounced">0.00</h4>
                                <p>Today Bounced</p>
                                <small id="card_today_bounced_count">0 record(s)</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <div class="small-box bg-primary">
                            <div class="inner">
                                <h4 id="card_all_pending_amount">0.00</h4>
                                <p>All Pending Amount</p>
                                <small id="card_all_pending_amount_count">0 record(s)</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body table-responsive">
                        <table id="paymentClearanceTable" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Voucher Number</th>
                                    <th>Source</th>
                                    <th>Customer</th>
                                    <th>Plot</th>
                                    <th>Payment Type</th>
                                    <th>Bank</th>
                                    <th>Transaction/Cheque No</th>
                                    <th>Amount</th>
                                    <th>Passing Date</th>
                                    <th>Status</th>
                                    <th>Cleared Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@section('js')
    <script>
        (function($) {
            const dataRoute = @json($dataRoute);
            let paymentClearanceTable = null;

            function money(value) {
                return Number(value || 0).toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            function badgeHtml(text, badgeClass) {
                return `<span class="badge ${badgeClass}">${text || ''}</span>`;
            }

            function actionHtml(row) {
                if (!row.ledger_exists || !row.view_url) {
                    return '<span class="text-muted">No linked voucher</span>';
                }

                return `
                    <div class="btn-group">
                        <a href="${row.view_url}" target="_blank" class="btn btn-sm btn-primary">View</a>
                        <a href="${row.print_url}" target="_blank" class="btn btn-sm btn-info">Print</a>
                    </div>
                `;
            }

            function filters() {
                return {
                    date_from: $('#date_from').val(),
                    date_to: $('#date_to').val(),
                    payment_type: $('#payment_type').val(),
                    status: $('#status').val(),
                    bank_id: $('#bank_id').val(),
                    voucher_number: $('#voucher_number').val(),
                    t_number: $('#t_number').val(),
                    customer_id: $('#customer_id').val(),
                    plot_id: $('#plot_id').val()
                };
            }

            function updateCard(key, item) {
                $(`#card_${key}`).text(money(item.amount));
                $(`#card_${key}_count`).text(`${item.count || 0} record(s)`);
            }

            function updateCards(cards) {
                updateCard('today_total_amount', cards.today_total_amount || {});
                updateCard('today_pending', cards.today_pending || {});
                updateCard('today_passed', cards.today_passed || {});
                updateCard('today_returned', cards.today_returned || {});
                updateCard('today_bounced', cards.today_bounced || {});
                updateCard('all_pending_amount', cards.all_pending_amount || {});
            }

            function renderTable(rows) {
                if ($.fn.DataTable.isDataTable('#paymentClearanceTable')) {
                    paymentClearanceTable.clear().destroy();
                }

                paymentClearanceTable = $('#paymentClearanceTable').DataTable({
                    data: rows,
                    pageLength: 25,
                    order: [
                        [0, 'desc']
                    ],
                    columns: [{
                            data: 'date',
                            defaultContent: ''
                        },
                        {
                            data: 'voucher_number',
                            defaultContent: ''
                        },
                        {
                            data: 'source',
                            defaultContent: ''
                        },
                        {
                            data: 'customer',
                            defaultContent: ''
                        },
                        {
                            data: 'plot',
                            defaultContent: ''
                        },
                        {
                            data: null,
                            render: function(data) {
                                return badgeHtml(data.payment_type, data.payment_type_badge);
                            }
                        },
                        {
                            data: 'bank',
                            defaultContent: ''
                        },
                        {
                            data: 't_number',
                            defaultContent: ''
                        },
                        {
                            data: 'amount',
                            render: function(data) {
                                return money(data);
                            }
                        },
                        {
                            data: 'passing_date',
                            defaultContent: ''
                        },
                        {
                            data: null,
                            render: function(data) {
                                return badgeHtml(data.status, data.status_badge);
                            }
                        },
                        {
                            data: 'cleared_date',
                            defaultContent: ''
                        },
                        {
                            data: null,
                            orderable: false,
                            searchable: false,
                            render: function(data) {
                                return actionHtml(data);
                            }
                        }
                    ]
                });
            }

            function loadTable() {
                $.ajax({
                    url: dataRoute,
                    type: 'GET',
                    dataType: 'json',
                    data: filters(),
                    success: function(response) {
                        updateCards(response.cards || {});
                        renderTable(response.data || []);
                    },
                    error: function() {
                        updateCards({});
                        renderTable([]);
                    }
                });
            }

            $('#applyFilters').on('click', loadTable);

            $('#resetFilters').on('click', function() {
                $('#date_from, #date_to, #voucher_number, #t_number').val('');
                $('#payment_type, #status, #bank_id, #customer_id, #plot_id').val('');
                loadTable();
            });

            loadTable();
        })(jQuery);
    </script>
@endsection
