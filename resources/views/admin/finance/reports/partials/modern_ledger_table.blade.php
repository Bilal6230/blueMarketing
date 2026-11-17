<div class="content-wrapper fvr-report bm-voucher-directory" id="financeReportRoot"
     data-mode="{{ $reportMode }}" data-endpoint="{{ $reportMode === 'party' ? route('fetch-data_by_party') : route('fetch_data_by_head') }}"
     data-account-type="{{ $account_type ?? 'Head' }}">
    <div class="fvr-heading d-flex justify-content-between align-items-center">
        <div><h1 class="fvr-title">{{ $title }}</h1><p class="fvr-subtitle mb-0">Project ledger balances · Live account summary</p></div>
        <nav aria-label="Breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a> <span class="text-muted mx-1">/</span> {{ $title }}</nav>
    </div>
    <section class="fvr-card bm-voucher-table-card">
        <div class="fvr-toolbar">
            <div class="fvr-buttons" id="financeReportExports"></div>
            <div class="fvr-controls">
                <div class="fvr-length">
                    <label for="financeReportLength">Show</label>
                    <select id="financeReportLength" class="form-control form-control-sm" aria-label="Rows per page">
                        <option value="10">10</option><option value="25" selected>25</option>
                        <option value="50">50</option><option value="100">100</option>
                    </select>
                    <span>entries</span>
                </div>
                <div class="fvr-search">
                    <label for="financeReportSearch">Search:</label>
                    <input type="search" id="financeReportSearch" class="form-control form-control-sm" placeholder="Search report..." autocomplete="off">
                </div>
            </div>
        </div>
        <div class="fvr-error alert alert-danger d-none" id="financeReportError" role="alert"></div>
        <div class="fvr-table-viewport">
            <table id="financeReportTable" class="table table-hover table-bordered w-100">
                <thead><tr>
                    @if($reportMode === 'party')
                        <th>Project</th><th>Head Account</th><th>Party Account</th>
                    @else
                        <th>{{ ($account_type ?? '') === 'account' ? 'Account Name' : 'Head Account' }}</th>
                    @endif
                    <th>Cash In</th><th>Cash Out</th><th>Balance</th>
                </tr></thead>
                <tbody></tbody>
                <tfoot><tr>
                    @if($reportMode === 'party')
                        <th colspan="3">Page total</th>
                    @else
                        <th>Page total</th>
                    @endif
                    <th id="financeReportTotalIn"></th><th id="financeReportTotalOut"></th><th id="financeReportTotalBalance"></th>
                </tr></tfoot>
            </table>
        </div>
    </section>
</div>
