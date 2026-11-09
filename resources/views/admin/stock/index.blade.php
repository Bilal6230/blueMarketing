@extends('admin.layouts.master')
@section('content')
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root {
      --pv-primary: #2b6cff;
      --pv-bg: #f5f7fb;
      --pv-card: #ffffff;
      --pv-border: #e7ecf5;
      --pv-text: #0b1220;
      --pv-muted: #64748b;
      --pv-shadow: 0 18px 52px -34px rgba(2, 6, 23, .30);
      --pv-radius: 18px;
      --pv-radius-lg: 22px;
      --pv-sidebar: #0d1b2a;
      --pv-sidebar2: #0b1522;
      --pv-success: #22c55e;
      --pv-warn: #f59e0b;
      --pv-danger: #ef4444;
    }

    .stock-module-wrapper { background: var(--pv-bg); color: var(--pv-text); min-height: calc(100vh - 140px); overflow-x: hidden; }

    /* Keep the ERP's actual navigation shell; this page owns only its compact toolbar. */
    .pv-shell { display: block; min-height: auto; }
    .pv-sidebar { display: none !important; }
    .pv-main { padding: 20px clamp(12px, 2vw, 30px) 34px; max-width: 1900px; margin: 0 auto; }
    .pv-topbar { border-radius: 16px; border-color: #e2eaf5; background: linear-gradient(115deg,#fff 0%,#f5f9ff 100%); }
    .pv-tabbtn { border-radius: 11px; font-weight: 650; }
    .pv-tabbtn.active { background: #eaf2ff; color: #175bd6; border-color: #bcd3ff; box-shadow: 0 5px 14px #2b6cff1a; }
    .pv-main .form-control, .pv-main .form-select { border-radius: 10px; min-height: 40px; }
    .pv-main .btn { border-radius: 10px; font-weight: 600; }
    .pv-main .table thead th { background: #f0f5fc; color: #35506f; font-size: .76rem; letter-spacing: .045em; }
    @media (max-width: 767.98px) { .pv-main { padding: 12px 10px 24px; } .pv-topbar { align-items: flex-start; flex-direction: column; } .pv-tabs { justify-content: flex-start; } }

    /* App shell (labour module-ish) */
    .pv-shell {
      display: flex;
      min-height: 100vh;
    }

    .pv-sidebar {
      width: 72px;
      background: linear-gradient(180deg, var(--pv-sidebar), var(--pv-sidebar2));
      color: #cbd5e1;
      position: sticky;
      top: 0;
      height: 100vh;
      border-right: 1px solid rgba(255, 255, 255, .06);
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 10px 8px;
      gap: 10px;
      z-index: 10;
    }

    .pv-brand {
      width: 46px;
      height: 46px;
      border-radius: 14px;
      background: rgba(255, 255, 255, .06);
      display: grid;
      place-items: center;
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .08);
      margin: 4px 0 8px;
    }

    .pv-navicon {
      width: 46px;
      height: 46px;
      border-radius: 14px;
      display: grid;
      place-items: center;
      cursor: pointer;
      transition: transform .08s ease, background .15s ease, color .15s ease;
      color: #cbd5e1;
      position: relative;
    }

    .pv-navicon:hover {
      background: rgba(255, 255, 255, .06);
      transform: translateY(-1px);
    }

    .pv-navicon.active {
      background: rgba(43, 108, 255, .18);
      color: #e7f0ff;
      box-shadow: inset 0 0 0 1px rgba(43, 108, 255, .35);
    }

    .pv-navicon.active::before {
      content: "";
      position: absolute;
      left: -6px;
      width: 4px;
      height: 26px;
      border-radius: 999px;
      background: var(--pv-primary);
    }

    .pv-main {
      flex: 1;
      padding: 18px 18px 26px;
    }

    .pv-topbar {
      background: var(--pv-card);
      border: 1px solid var(--pv-border);
      border-radius: var(--pv-radius-lg);
      box-shadow: var(--pv-shadow);
      padding: 12px 14px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      margin-bottom: 14px;
    }

    .pv-topbar .pv-title {
      display: flex;
      align-items: center;
      gap: 10px;
      min-width: 260px;
    }

    .pv-topbar .pv-title h1 {
      font-size: 16px;
      margin: 0;
      letter-spacing: .2px;
    }

    .pv-chip {
      font-size: 12px;
      color: var(--pv-muted);
      background: #f1f5ff;
      border: 1px solid #dfe8ff;
      padding: 4px 10px;
      border-radius: 999px;
      white-space: nowrap;
    }

    .pv-tabs {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      justify-content: flex-end;
    }

    .pv-tabbtn {
      border: 1px solid var(--pv-border);
      background: #fff;
      color: #223;
      padding: 8px 12px;
      border-radius: 999px;
      font-weight: 600;
      font-size: 13px;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: background .15s ease, transform .08s ease;
    }

    .pv-tabbtn:hover {
      background: #f7f9ff;
      transform: translateY(-1px);
    }

    .pv-tabbtn.active {
      background: var(--pv-primary);
      border-color: var(--pv-primary);
      color: #fff;
      box-shadow: 0 12px 26px -18px rgba(43, 108, 255, .55);
    }

    .pv-card {
      background: var(--pv-card);
      border: 1px solid var(--pv-border);
      border-radius: var(--pv-radius-lg);
      box-shadow: var(--pv-shadow);
      overflow: hidden;
    }

    .pv-card .pv-cardhead {
      padding: 14px 16px;
      border-bottom: 1px solid var(--pv-border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
    }

    .pv-card .pv-cardhead h2 {
      margin: 0;
      font-size: 14px;
      font-weight: 800;
      letter-spacing: .2px;
    }

    .pv-muted {
      color: var(--pv-muted);
    }

    .pv-small {
      font-size: 12px;
    }

    .pv-scroll {
      max-height: 520px;
      overflow: auto;
    }

    .pv-kpi {
      background: #fbfcff;
      border: 1px solid var(--pv-border);
      border-radius: 16px;
      padding: 12px 12px;
      display: flex;
      gap: 12px;
      align-items: center;
    }

    .pv-kpi i {
      font-size: 18px;
      width: 36px;
      height: 36px;
      border-radius: 14px;
      display: grid;
      place-items: center;
      background: #eef3ff;
      color: var(--pv-primary);
    }

    .pv-kpi .n {
      font-weight: 900;
      font-size: 16px;
    }

    .pv-kpi .l {
      font-size: 12px;
      color: var(--pv-muted);
    }

    .table thead th {
      font-size: 11px;
      letter-spacing: .7px;
      text-transform: uppercase;
      color: var(--pv-muted);
      background: #f7f9ff;
      border-bottom: 1px solid var(--pv-border);
      white-space: nowrap;
    }

    .table td {
      vertical-align: middle;
    }

    .pv-actions .btn {
      border-radius: 12px;
      padding: 7px 10px;
      font-weight: 700;
      font-size: 12px;
    }

    .pv-pill {
      font-size: 11px;
      padding: 4px 10px;
      border-radius: 999px;
      border: 1px solid var(--pv-border);
      background: #fff;
      color: var(--pv-muted);
      white-space: nowrap;
    }

    .pv-pill.ok {
      border-color: #bfead0;
      background: #effaf3;
      color: #0f5132;
    }

    .pv-pill.warn {
      border-color: #ffe2b0;
      background: #fff7e8;
      color: #8a5a00;
    }

    .pv-pill.bad {
      border-color: #ffc2c2;
      background: #fff0f0;
      color: #842029;
    }

    .form-control,
    .form-select {
      border-radius: 14px;
      border-color: var(--pv-border);
      background: #fff;
      padding: 10px 12px;
    }

    .form-control:focus,
    .form-select:focus {
      box-shadow: 0 0 0 .2rem rgba(43, 108, 255, .12);
      border-color: rgba(43, 108, 255, .55);
    }

    .pv-line {
      border: 1px solid var(--pv-border);
      border-radius: 16px;
      padding: 10px;
      background: #fff;
    }

    .pv-line .row>* {
      margin-bottom: 8px;
    }

    .pv-subseg {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .pv-subseg .btn {
      border-radius: 999px;
      font-weight: 800;
      font-size: 12px;
      padding: 8px 12px;
    }

    /* Responsive */
    @media (max-width: 992px) {
      .pv-sidebar {
        display: none;
      }

      .pv-main {
        padding: 12px;
      }

      .pv-topbar {
        border-radius: 18px;
      }

      .pv-scroll {
        max-height: 420px;
      }
    }
    /* Override legacy mock shell styles: use the real sticky ERP sidebar and navbar. */
    .pv-shell { display: block; min-height: auto; }
    .pv-sidebar { display: none !important; }
    .pv-main { display: block; width: 100%; padding: 20px clamp(12px, 2vw, 30px) 34px; max-width: 1900px; margin: 0 auto; }
    .pv-topbar { border-radius: 16px; border-color: #e2eaf5; background: linear-gradient(115deg,#fff,#f5f9ff); }
    .pv-tabbtn { border-radius: 11px; font-weight: 650; }
    .pv-tabbtn.active { background: #eaf2ff; color: #175bd6; border-color: #bcd3ff; box-shadow: 0 5px 14px #2b6cff1a; }
    .pv-main .form-control, .pv-main .form-select { border-radius: 10px; min-height: 40px; }
    .pv-main .btn { border-radius: 10px; font-weight: 600; }
    .pv-main .table thead th { background: #f0f5fc; color: #35506f; font-size: .76rem; letter-spacing: .045em; }
    @media (max-width: 767.98px) { .pv-main { padding: 12px 10px 24px; } .pv-topbar { align-items: flex-start; flex-direction: column; } .pv-tabs { justify-content: flex-start; } }
  </style>
  <div class="content-wrapper stock-module-wrapper"><div class="pv-shell">
    <!-- Sidebar (visual only) -->
    <aside class="pv-sidebar" aria-hidden="true">
      <div class="pv-brand" title="ERP">
        <i class="bi bi-boxes text-white"></i>
      </div>

      <div class="pv-navicon" title="Dashboard">
        <i class="bi bi-house"></i>
      </div>
      <div class="pv-navicon" title="Labour">
        <i class="bi bi-people"></i>
      </div>
      <div class="pv-navicon active" title="Stock">
        <i class="bi bi-box-seam"></i>
      </div>
      <div class="pv-navicon" title="Reports">
        <i class="bi bi-graph-up"></i>
      </div>

      <div class="mt-auto pv-navicon" title="Settings">
        <i class="bi bi-gear"></i>
      </div>
    </aside>

    <main class="pv-main">
      <!-- Top bar -->
      <div class="pv-topbar">
        <div class="pv-title">
          <div class="pv-chip">
            <i class="bi bi-box-seam me-1"></i> Stock Management Module
          </div>
          <div class="pv-small pv-muted d-none d-md-block">
            Project-scoped stock, billing and material records
          </div>
        </div>

        <div class="pv-tabs">
          <button class="pv-tabbtn active" data-view="entry">
            <i class="bi bi-arrow-left-right"></i> Entry
          </button>
          <button class="pv-tabbtn" data-view="billing">
            <i class="bi bi-receipt"></i> Billing
          </button>
          <button class="pv-tabbtn" data-view="stock">
            <i class="bi bi-clipboard-data"></i> Stock
          </button>
          <button class="pv-tabbtn" data-view="items">
            <i class="bi bi-box2"></i> Items
          </button>
          <button class="pv-tabbtn" data-view="parties">
            <i class="bi bi-person-lines-fill"></i> Parties
          </button>
        </div>
      </div>

      @if(($legacyStockStatus['unassigned_entries'] ?? null) !== null && ($legacyStockStatus['unassigned_entries'] + $legacyStockStatus['unassigned_bills'] + $legacyStockStatus['unassigned_movements'] + $legacyStockStatus['legacy_balance_rows']) > 0)
        <div class="alert alert-warning mb-3" role="status">
          <strong>Legacy stock needs project reconciliation.</strong>
          {{ $legacyStockStatus['unassigned_entries'] }} entries, {{ $legacyStockStatus['unassigned_bills'] }} bills, and {{ $legacyStockStatus['unassigned_movements'] }} movements could not be safely assigned automatically.
          {{ $legacyStockStatus['legacy_balance_rows'] }} old global balance rows are preserved separately and are not used as a project's stock balance.
        </div>
      @endif

      @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
      @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
      @if ($canReconcileLegacyStock && (count($legacyStockCandidates['entries']) + count($legacyStockCandidates['bills']) + count($legacyStockCandidates['movements']) > 0 || $legacyStockAudits->isNotEmpty()))
        <div class="card border-warning mb-3" data-ajax-panel="stock-reconciliation-panel">
          <div class="card-header bg-light"><strong>Legacy stock project reconciliation</strong></div>
          <div class="card-body">
            <p class="text-muted">Assign only records whose party belongs to this project. Each assignment requires a reason and is audited. Linked lines and source movements are scoped together; quantities, rates and amounts are not edited. Review up to 50 candidates per section.</p>
            @if (count($legacyStockCandidates['entries']))
              <h6>Unassigned entries</h6><div class="table-responsive mb-3"><table class="table table-sm table-bordered"><thead><tr><th>Slip</th><th>Date</th><th>Flow</th><th>Party</th><th>Record state</th><th>Assignment reason</th></tr></thead><tbody>
              @foreach ($legacyStockCandidates['entries'] as $candidate)<tr><td>{{ $candidate->slip_no }}</td><td>{{ $candidate->entry_date }}</td><td>{{ $candidate->flow }}</td><td>{{ $candidate->party_name }}</td><td>{{ $candidate->deleted_at ? 'Archived' : 'Active' }}</td><td><form method="POST" action="{{ route('stocks.legacy.reconcile') }}" class="d-flex" onsubmit="return confirm('Assign this entry and its linked history to the selected project? Its archive state will stay unchanged.')">@csrf<input type="hidden" name="kind" value="entry"><input type="hidden" name="record_id" value="{{ $candidate->id }}"><input class="form-control form-control-sm mr-2" name="reason" minlength="8" maxlength="500" placeholder="Why does this record belong to this project?" required><button class="btn btn-sm btn-warning">Assign</button></form></td></tr>@endforeach
              </tbody></table></div>
            @endif
            @if (count($legacyStockCandidates['bills']))
              <h6>Unassigned bills</h6><div class="table-responsive mb-3"><table class="table table-sm table-bordered"><thead><tr><th>Bill</th><th>Date</th><th>Type</th><th>Entry ID</th><th>Party</th><th>Record state</th><th>Assignment reason</th></tr></thead><tbody>
              @foreach ($legacyStockCandidates['bills'] as $candidate)<tr><td>{{ $candidate->bill_no }}</td><td>{{ $candidate->bill_date }}</td><td>{{ $candidate->type }}</td><td>{{ $candidate->entry_id ?? '—' }}</td><td>{{ $candidate->party_name }}</td><td>{{ $candidate->deleted_at ? 'Archived' : 'Active' }}</td><td><form method="POST" action="{{ route('stocks.legacy.reconcile') }}" class="d-flex" onsubmit="return confirm('Assign this bill and its linked history to the selected project? Its archive state will stay unchanged.')">@csrf<input type="hidden" name="kind" value="bill"><input type="hidden" name="record_id" value="{{ $candidate->id }}"><input class="form-control form-control-sm mr-2" name="reason" minlength="8" maxlength="500" placeholder="Reason" required><button class="btn btn-sm btn-warning">Assign</button></form></td></tr>@endforeach
              </tbody></table></div>
            @endif
            @if (count($legacyStockCandidates['movements']))
              <h6>Unassigned standalone movements</h6><div class="table-responsive"><table class="table table-sm table-bordered"><thead><tr><th>ID</th><th>Date</th><th>Direction</th><th>Item ID</th><th>Quantity</th><th>Source</th><th>Party</th><th>Assignment reason</th></tr></thead><tbody>
              @foreach ($legacyStockCandidates['movements'] as $candidate)<tr><td>{{ $candidate->id }}</td><td>{{ $candidate->movement_date }}</td><td>{{ $candidate->direction }}</td><td>{{ $candidate->item_id }}</td><td>{{ $candidate->qty }}</td><td>{{ $candidate->source_type }} #{{ $candidate->source_id }}</td><td>{{ $candidate->party_name }}</td><td><form method="POST" action="{{ route('stocks.legacy.reconcile') }}" class="d-flex" onsubmit="return confirm('Assign this standalone movement to the selected project?')">@csrf<input type="hidden" name="kind" value="movement"><input type="hidden" name="record_id" value="{{ $candidate->id }}"><input class="form-control form-control-sm mr-2" name="reason" minlength="8" maxlength="500" placeholder="Reason" required><button class="btn btn-sm btn-warning">Assign</button></form></td></tr>@endforeach
              </tbody></table></div>
            @endif
            @if ($legacyStockAudits->isNotEmpty())
              <h6 class="mt-3">Recent reconciliation audit</h6><div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>When</th><th>Record</th><th>ID</th><th>Reason</th><th>By</th></tr></thead><tbody>
              @foreach ($legacyStockAudits as $audit)<tr><td>{{ $audit->created_at }}</td><td>{{ str_replace('_', ' ', $audit->record_type) }}</td><td>{{ $audit->record_id }}</td><td>{{ $audit->reason }}</td><td>{{ $audit->actor_name ?? 'Unavailable user' }}</td></tr>@endforeach
              </tbody></table></div>
            @endif
          </div>
        </div>
      @endif

      <!-- Views container -->
      <div id="pvViews">
        <!-- ===================== ENTRY VIEW (Purchase + Stock Out) ===================== -->
        <section id="view-entry" class="pv-view">
          <div class="row g-3">
            <!-- Left: Entry Form -->
            <div class="col-lg-5">
              <div class="pv-card">
                <div class="pv-cardhead">
                  <div>
                    <h2 id="entryTitle">Purchase Entry</h2>
                    <div class="pv-small pv-muted">
                      Based on your Excel “Entry” sheet: slip, vehicle,
                      party/site, reason, items.
                    </div>
                  </div>
                  <div class="pv-subseg">
                    <button class="btn btn-outline-primary" id="btnPurchaseMode">
                      <i class="bi bi-plus-circle"></i> Purchase
                    </button>
                    <button class="btn btn-outline-secondary" id="btnStockOutMode">
                      <i class="bi bi-dash-circle"></i> Stock Out
                    </button>
                  </div>
                </div>

                <div class="p-3">
                  <form id="entryForm" novalidate>
                    <div class="row g-2">
                      <div class="col-md-6">
                        <label class="form-label pv-small">Slip No (auto)</label>
                        <input type="text" class="form-control" id="entrySlipNo" readonly />
                      </div>
                      <div class="col-md-6">
                        <label class="form-label pv-small">Date</label>
                        <input type="date" class="form-control" id="entryDate" required />
                      </div>

                      <div class="col-md-6">
                        <label class="form-label pv-small">Vehicle No</label>
                        <input type="text" class="form-control" id="entryVehicle" placeholder="e.g., LEE-123" />
                      </div>
                      <div class="col-md-6">
                        <label class="form-label pv-small">Driver</label>
                        <input type="text" class="form-control" id="entryDriver" placeholder="e.g., Ali" />
                      </div>

                      <div class="col-12">
                        <label class="form-label pv-small" id="entryPartyLabel">Shop / Party</label>
                        <select class="form-select" id="entryParty" required><option value="">Select a Shop/Party or Site/Customer</option></select>
                      </div>

                      <div class="col-12">
                        <label class="form-label pv-small" id="entryReasonLabel">Purchase Reason Details</label>
                        <textarea class="form-control" id="entryReason" rows="2"
                          placeholder="Write details..."></textarea>
                      </div>
                    </div>

                    <hr class="my-3" />

                    <div class="d-flex align-items-center justify-content-between mb-2">
                      <div class="fw-bold">Items / Material</div>
                      <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddLine">
                        <i class="bi bi-plus-lg"></i> Add Row
                      </button>
                    </div>

                    <div id="entryLines" class="vstack gap-2"></div>

                    <div class="pv-line mt-3">
                      <div class="d-flex align-items-center justify-content-between">
                        <div class="pv-small pv-muted">Total Amount</div>
                        <div class="fw-bold" id="entryTotal">PKR 0</div>
                      </div>
                    </div>

                    <div class="d-grid mt-3">
                      <button type="submit" class="btn btn-primary btn-lg" id="entrySubmitBtn">
                        <i class="bi bi-check2-circle me-1"></i> Save Entry
                      </button>
                      <div class="pv-small pv-muted mt-2">
                        Saves to this project's stock records. Entries refresh here without a page reload.
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>

            <!-- Right: Entry List -->
            <div class="col-lg-7">
              <div class="pv-card">
                <div class="pv-cardhead">
                  <div>
                    <h2>Entries</h2>
                    <div class="pv-small pv-muted">
                      Excel list style: Sr, Date, In/Out, Name, Details, Item,
                      Qty, Rate, Billing Status.
                    </div>
                  </div>
                  <div class="d-flex gap-2 align-items-center flex-wrap">
                    <span class="pv-chip" id="entryCountChip">0 total</span>
                  </div>
                </div>

                <div class="p-3">
                  <div class="row g-2 mb-3">
                    <div class="col-md-4">
                      <label class="form-label pv-small">Filter: Item</label>
                      <select class="form-select" id="entryFilterItem"></select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label pv-small">Filter: In/Out</label>
                      <select class="form-select" id="entryFilterFlow">
                        <option value="">All</option>
                        <option value="IN">IN (Purchase)</option>
                        <option value="OUT">OUT (Stock Out)</option>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label pv-small">Search</label>
                      <input class="form-control" id="entrySearch" placeholder="Name / details / slip..." />
                    </div>
                  </div>

                  <div class="table-responsive pv-scroll">
                    <table class="table table-hover align-middle mb-0">
                      <thead>
                        <tr>
                          <th>Sr</th>
                          <th>Date</th>
                          <th>In/Out</th>
                          <th>Name</th>
                          <th>Details</th>
                          <th>Item</th>
                          <th>Qty</th>
                          <th>Rate</th>
                          <th>Amount</th>
                          <th>Billing</th>
                        </tr>
                      </thead>
                      <tbody id="entryTbody"></tbody>
                    </table>
                  </div>
                </div>
              </div>

              <!-- KPIs -->
              <div class="row g-3 mt-3">
                <div class="col-md-4">
                  <div class="pv-kpi">
                    <i class="bi bi-box-arrow-in-down"></i>
                    <div>
                      <div class="n" id="kpiInQty">0</div>
                      <div class="l">Total IN Qty</div>
                    </div>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="pv-kpi">
                    <i class="bi bi-box-arrow-up"></i>
                    <div>
                      <div class="n" id="kpiOutQty">0</div>
                      <div class="l">Total OUT Qty</div>
                    </div>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="pv-kpi">
                    <i class="bi bi-clipboard2-check"></i>
                    <div>
                      <div class="n" id="kpiLines">0</div>
                      <div class="l">Total Lines</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- ===================== BILLING VIEW ===================== -->
        <section id="view-billing" class="pv-view d-none">
          <div class="row g-3">
            <div class="col-lg-5">
              <div class="pv-card">
                <div class="pv-cardhead">
                  <div>
                    <h2>Purchase / Sale Bill</h2>
                    <div class="pv-small pv-muted">
                      Excel “Billing” sheet: selection, supplier/site, bill
                      no, item lines, total.
                    </div>
                  </div>
                  <span class="pv-chip" id="billNoChip">Bill no… auto</span>
                </div>

                <div class="p-3">
                  <form id="billForm" novalidate>
                    <div class="row g-2">
                      <div class="col-md-6">
                        <label class="form-label pv-small">Selection</label>
                        <select class="form-select" id="billType" required>
                          <option value="purchase">Purchase Bill</option>
                          <option value="sale">Sale Bill</option>
                        </select>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label pv-small">Date</label>
                        <input type="date" class="form-control" id="billDate" required />
                      </div>

                      <div class="col-12">
                        <label class="form-label pv-small" id="billPartyLabel">Supplier / Site Name</label>
                        <select class="form-select" id="billParty" required><option value="">Select a Supplier or Site</option></select>
                      </div>

                      <div class="col-md-6">
                        <label class="form-label pv-small">Entry No (optional)</label>
                        <input class="form-control" id="billEntryNo" placeholder="Link to entry..." />
                      </div>
                      <div class="col-md-6">
                        <label class="form-label pv-small">Bill No (auto)</label>
                        <input class="form-control" id="billNo" readonly />
                      </div>
                    </div>

                    <hr class="my-3" />

                    <div class="d-flex align-items-center justify-content-between mb-2">
                      <div class="fw-bold">Bill Items</div>
                      <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddBillLine">
                        <i class="bi bi-plus-lg"></i> Add Row
                      </button>
                    </div>

                    <div id="billLines" class="vstack gap-2"></div>

                    <div class="pv-line mt-3">
                      <div class="d-flex align-items-center justify-content-between">
                        <div class="pv-small pv-muted">Total</div>
                        <div class="fw-bold" id="billTotal">PKR 0</div>
                      </div>
                    </div>

                    <div class="d-grid gap-2 mt-3">
                      <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-check2-circle me-1"></i> Save Bill
                      </button>
                    </div>
                  </form>
                </div>
              </div>
            </div>

            <div class="col-lg-7">
              <div class="pv-card">
                <div class="pv-cardhead">
                  <div>
                    <h2>Bills</h2>
                    <div class="pv-small pv-muted">
                      Excel list: Sr, Date, Name, Details, Amount,
                      Print/Edit/Delete.
                    </div>
                  </div>
                  <span class="pv-chip" id="billCountChip">0 total</span>
                </div>

                <div class="p-3">
                  <div class="row g-2 mb-3">
                    <div class="col-md-4">
                      <label class="form-label pv-small">Filter: Type</label>
                      <select class="form-select" id="billFilterType">
                        <option value="">All</option>
                        <option value="purchase">Purchase</option>
                        <option value="sale">Sale</option>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label pv-small">Filter: Party</label>
                      <select class="form-select" id="billFilterParty"></select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label pv-small">Search</label>
                      <input class="form-control" id="billSearch" placeholder="Bill no / name..." />
                    </div>
                  </div>

                  <div class="table-responsive pv-scroll">
                    <table class="table table-hover align-middle mb-0">
                      <thead>
                        <tr>
                          <th>Sr</th>
                          <th>Date</th>
                          <th>Type</th>
                          <th>Name</th>
                          <th>Details</th>
                          <th>Amount</th>
                        </tr>
                      </thead>
                      <tbody id="billTbody"></tbody>
                    </table>
                  </div>
                </div>
              </div>

              <div class="row g-3 mt-3">
                <div class="col-md-4">
                  <div class="pv-kpi">
                    <i class="bi bi-cart-plus"></i>
                    <div>
                      <div class="n" id="kpiPurchaseBills">0</div>
                      <div class="l">Purchase Bills</div>
                    </div>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="pv-kpi">
                    <i class="bi bi-cart-check"></i>
                    <div>
                      <div class="n" id="kpiSaleBills">0</div>
                      <div class="l">Sale Bills</div>
                    </div>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="pv-kpi">
                    <i class="bi bi-cash-coin"></i>
                    <div>
                      <div class="n" id="kpiBillTotal">PKR 0</div>
                      <div class="l">Bills Total</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- ===================== STOCK VIEW ===================== -->
        <section id="view-stock" class="pv-view d-none">
          <div class="row g-3">
            <div class="col-12">
              <div class="pv-card">
                <div class="pv-cardhead">
                  <div>
                    <h2>Stock (Date Range)</h2>
                    <div class="pv-small pv-muted">
                      Excel “Stock” sheet: Item, Purchase Qty, Sale Qty,
                      Available Stock.
                    </div>
                  </div>
                  <span class="pv-chip" id="stockRangeChip">Range: —</span>
                </div>

                <div class="p-3">
                  <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                      <label class="form-label pv-small">From</label>
                      <input type="date" class="form-control" id="stockFrom" />
                    </div>
                    <div class="col-md-3">
                      <label class="form-label pv-small">To</label>
                      <input type="date" class="form-control" id="stockTo" />
                    </div>
                    <div class="col-md-3">
                      <label class="form-label pv-small">Item</label>
                      <select class="form-select" id="stockItemFilter"></select>
                    </div>
                    <div class="col-md-3 d-grid">
                      <button class="btn btn-primary" id="btnApplyStock">
                        <i class="bi bi-funnel"></i> Apply Filters
                      </button>
                    </div>
                  </div>

                  <div class="row g-3 mt-2">
                    <div class="col-md-3">
                      <div class="pv-kpi">
                        <i class="bi bi-box2"></i>
                        <div>
                          <div class="n" id="kpiStockItems">0</div>
                          <div class="l">Items in report</div>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-3">
                      <div class="pv-kpi">
                        <i class="bi bi-arrow-down-circle"></i>
                        <div>
                          <div class="n" id="kpiStockIn">0</div>
                          <div class="l">Total Purchase Qty</div>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-3">
                      <div class="pv-kpi">
                        <i class="bi bi-arrow-up-circle"></i>
                        <div>
                          <div class="n" id="kpiStockOut">0</div>
                          <div class="l">Total Sale/Out Qty</div>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-3">
                      <div class="pv-kpi">
                        <i class="bi bi-clipboard2-data"></i>
                        <div>
                          <div class="n" id="kpiStockAvail">0</div>
                          <div class="l">Total Available</div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="table-responsive mt-3">
                    <table class="table table-hover align-middle mb-0">
                      <thead>
                        <tr>
                          <th>Item</th>
                          <th>Pur Qty</th>
                          <th>Sale Qty</th>
                          <th>Available Stock</th>
                        </tr>
                      </thead>
                      <tbody id="stockTbody"></tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- ===================== ITEMS VIEW ===================== -->
        <section id="view-items" class="pv-view d-none">
          <div class="row g-3">
            <div class="col-lg-5">
              <div class="pv-card">
                <div class="pv-cardhead">
                  <div>
                    <h2>Add Item / Material</h2>
                    <div class="pv-small pv-muted">
                      Simple master list: Item name + Unit + default rate.
                    </div>
                  </div>
                </div>
                <div class="p-3">
                  <form id="itemForm" novalidate>
                    <div class="row g-2">
                      <div class="col-12">
                        <label class="form-label pv-small">Item Name</label>
                        <input class="form-control" id="itemName" placeholder="e.g., Cement" required />
                      </div>
                      <div class="col-md-6">
                        <label class="form-label pv-small">Unit</label>
                        <input class="form-control" id="itemUnit" placeholder="e.g., bag, kg, pcs" required />
                      </div>
                      <div class="col-md-6">
                        <label class="form-label pv-small">Default Rate (PKR)</label>
                        <input class="form-control pv-money" id="itemRate" placeholder="e.g., 1,250"
                          inputmode="numeric" />
                      </div>
                    </div>

                    <div class="d-grid mt-3">
                      <button class="btn btn-primary btn-lg" type="submit">
                        <i class="bi bi-plus-circle me-1"></i> Add Item
                      </button>
                    </div>
                  </form>
                </div>
              </div>
            </div>

            <div class="col-lg-7">
              <div class="pv-card">
                <div class="pv-cardhead">
                  <div>
                    <h2>Items</h2>
                    <div class="pv-small pv-muted">
                      Used across Entry + Billing + Stock.
                    </div>
                  </div>
                  <span class="pv-chip" id="itemCountChip">0 total</span>
                </div>
                <div class="p-3">
                  <div class="border rounded-3 bg-light p-3 mb-3">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                      <div>
                        <strong>Excel import / export</strong>
                        <div class="pv-small pv-muted">Excel (.xlsx) aur CSV import/export available hain. Existing items sirf stable Item ID se match honge; stock balances aur transaction history change nahi hogi.</div>
                      </div>
                      <div class="d-flex gap-2">
                        <a class="btn btn-outline-primary btn-sm" href="{{ route('stocks.items.export', ['format' => 'xlsx']) }}"><i class="bi bi-download me-1"></i>Export all Excel</a>
                        <a class="btn btn-outline-primary btn-sm disabled" id="exportSelectedItems" href="{{ route('stocks.items.export', ['format' => 'xlsx']) }}" aria-disabled="true"><i class="bi bi-check2-square me-1"></i>Export selected Excel</a>
                      </div>
                    </div>
                    <form id="itemImportForm" class="row g-2 align-items-end mt-2">
                      <div class="col-md-8">
                        <label class="form-label pv-small" for="itemImportFile">Import Excel or CSV</label>
                        <input class="form-control form-control-sm" id="itemImportFile" name="file" type="file" accept=".xlsx,.csv,.txt,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required>
                      </div>
                      <div class="col-md-4 d-grid">
                        <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-upload me-1"></i>Preview Import</button>
                      </div>
                    </form>
                    <div id="itemImportPreview" class="mt-3" hidden></div>
                  </div>
                  <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2"><strong>Recent import history</strong><span class="pv-small pv-muted">Latest 25 uploads</span></div>
                    <div class="table-responsive pv-scroll"><table class="table table-sm table-bordered align-middle mb-0"><thead><tr><th>Batch</th><th>Date</th><th>By</th><th>Status</th><th>Added / Updated / Errors</th><th></th></tr></thead><tbody id="itemImportHistoryBody"><tr><td colspan="6" class="text-muted text-center">Loading history...</td></tr></tbody></table></div>
                  </div>
                  <div class="row g-2 mb-3">
                    <div class="col-md-8">
                      <input class="form-control" id="itemSearch" placeholder="Search items..." />
                    </div>
                    <div class="col-md-4 d-grid">
                      <div class="d-none"></div>
                    </div>
                  </div>

                  <div class="table-responsive pv-scroll">
                    <table class="table table-hover align-middle mb-0">
                      <thead>
                        <tr>
                          <th><input type="checkbox" id="selectAllStockItems" aria-label="Select all listed items"></th>
                          <th>Sr</th>
                          <th>Item</th>
                          <th>Unit</th>
                          <th>Default Rate</th>
                          <th class="text-end">Actions</th>
                        </tr>
                      </thead>
                      <tbody id="itemTbody"></tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- ===================== PARTIES VIEW ===================== -->
        <section id="view-parties" class="pv-view d-none">
          <div class="row g-3">
            <div class="col-lg-5">
              <div class="pv-card">
                <div class="pv-cardhead">
                  <div>
                    <h2>Add Party</h2>
                    <div class="pv-small pv-muted">
                      Excel “Add Party Name”: selection, supplier/consumer,
                      name, mobile, address, head/subhead.
                    </div>
                  </div>
                </div>
                <div class="p-3">
                  <form id="partyForm" novalidate>
                    <div class="row g-2">
                      <div class="col-md-6">
                        <label class="form-label pv-small">Supplier / Consumer</label>
                        <select class="form-select" id="partyType" required>
                          <option value="Supplier">Supplier</option>
                          <option value="Consumer">Consumer</option>
                          <option value="Site">Site</option>
                        </select>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label pv-small">Mobile</label>
                        <input class="form-control" id="partyMobile" placeholder="03xx...." />
                      </div>
                      <div class="col-12">
                        <label class="form-label pv-small">Name</label>
                        <input class="form-control" id="partyName" placeholder="e.g., ABC Supplier" required />
                      </div>
                      <div class="col-12">
                        <label class="form-label pv-small">Address</label>
                        <input class="form-control" id="partyAddress" placeholder="Address..." />
                      </div>
                      <div class="col-md-6">
                        <label>Head Accounts</label>
                        <select class="form-control select2" name="accounts_id" id="accounts_id">
                          <option value="">Select Head</option>
                          @foreach ($headaccounts as $head)
                            <option value="{{ $head->head_accounting_id }}">
                              {{ $head->headAccounting->name }}
                            </option>
                          @endforeach
                        </select>
                      </div>
                      <div class="col-md-6">
                        <label>Sub Head Accounts</label>
                        <select class="form-control select2" name="subaccounts_id" id="subaccounts_id">
                          <option value="">Select Sub Head</option>
                        </select>
                      </div>
                    </div>

                    <div class="d-grid mt-3">
                      <button class="btn btn-primary btn-lg" type="submit">
                        <i class="bi bi-person-plus me-1"></i> Add Party
                      </button>
                    </div>
                  </form>
                </div>
              </div>
            </div>

            <div class="col-lg-7">
              <div class="pv-card">
                <div class="pv-cardhead">
                  <div>
                    <h2>Parties</h2>
                    <div class="pv-small pv-muted">
                      Used for Purchase/Sale/Stock Out.
                    </div>
                  </div>
                  <span class="pv-chip" id="partyCountChip">0 total</span>
                </div>
                <div class="p-3">
                  <div class="row g-2 mb-3">
                    <div class="col-md-6">
                      <input class="form-control" id="partySearch" placeholder="Search name/mobile..." />
                    </div>
                    <div class="col-md-3">
                      <select class="form-select" id="partyFilterType">
                        <option value="">All types</option>
                        <option value="Supplier">Supplier</option>
                        <option value="Consumer">Consumer</option>
                        <option value="Site">Site</option>
                      </select>
                    </div>
                    <div class="col-md-3 d-grid">
                      <div class="d-none"></div>
                    </div>
                  </div>

                  <div class="table-responsive pv-scroll">
                    <table class="table table-hover align-middle mb-0">
                      <thead>
                        <tr>
                          <th>Sr</th>
                          <th>Vendor Code</th>
                          <th>Name</th>
                          <th>Mobile</th>
                          <th>Address</th>
                          <th>Head</th>
                          <th>Sub Head</th>
                          <th>Type</th>
                          <th>Status</th>
                          <th class="text-end">Actions</th>
                        </tr>
                      </thead>
                      <tbody id="partyTbody"></tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>
      <!-- /pvViews -->
    </main>
  </div></div>

  <!-- Modals (edit item/party) -->
  <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
      <div class="modal-content pv-card" style="border-radius: 22px">
        <div class="pv-cardhead">
          <div>
            <h2 id="editModalTitle">Edit</h2>
            <div class="pv-small pv-muted" id="editModalSub">
              Edit this project's record. Existing linked history is preserved.
            </div>
          </div>
          <button type="button" class="btn btn-light" data-bs-dismiss="modal" aria-label="Close"
            style="border-radius: 14px">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
        <div class="p-3">
          <form id="editForm"></form>
        </div>
      </div>
    </div>
  </div>
  {{-- Attendance and stock data are loaded from the project-scoped APIs below. --}}
  <script type="application/json" id="initial-attendance">[]</script>
@endsection

@section('js')
  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


  <script>
    const partyAccountOptions = @json($partyAccountOptions ?? []);
    const endpoints = {
      meta: "{{ route('stocks.meta') }}",
      nextSlip: "{{ route('stocks.nextSlip') }}",
      nextBill: "{{ route('stocks.nextBill') }}",
      items: "{{ route('stocks.items.index') }}",
      parties: "{{ route('stocks.parties.index') }}",
      entries: "{{ route('stocks.entries.index') }}",
      bills: "{{ route('stocks.bills.index') }}",
      report: "{{ route('stocks.report') }}",
      storeItem: "{{ route('stocks.items.store') }}",
      exportItems: "{{ route('stocks.items.export') }}",
      storeParty: "{{ route('stocks.parties.store') }}",
      storeEntry: "{{ route('stocks.entries.store') }}",
      storeBill: "{{ route('stocks.bills.store') }}",
      updateItem: "{{ route('stocks.items.update', ['item' => 0]) }}",
      deleteItem: "{{ route('stocks.items.delete', ['item' => 0]) }}",
      updateParty: "{{ route('stocks.parties.update', ['party' => 0]) }}",
      partyStatus: "{{ route('stocks.parties.status', ['party' => 0]) }}",
      deleteParty: "{{ route('stocks.parties.delete', ['party' => 0]) }}",
      stageItemImport: "{{ route('stocks.items.import.stage') }}",
      applyItemImport: "{{ route('stocks.items.import.apply', ['batch' => 0]) }}",
      itemImportErrors: "{{ route('stocks.items.import.errors', ['batch' => 0]) }}",
      itemImportPreview: "{{ route('stocks.items.import.preview', ['batch' => 0]) }}",
      itemImportHistory: "{{ route('stocks.items.import.history') }}",
    };

    const state = {
      items: [],
      parties: [],
      entries: { rows: [], total: 0, kpi: { in_qty: 0, out_qty: 0, lines: 0 } },
      bills: {
        rows: [],
        total: 0,
        kpi: { purchase_count: 0, sale_count: 0, total_amount: 0 },
      },
      report: { rows: [], kpi: { items: 0, in_qty: 0, out_qty: 0, available: 0 } },
      itemsTable: { rows: [], total: 0 },
      partiesTable: { rows: [], total: 0 },
    };

    const csrfToken = $('meta[name="csrf-token"]').attr('content');

    async function apiGet(url, params = {}) {
      try {
        return await $.ajax({
          url, method: 'GET', dataType: 'json',
          data: Object.fromEntries(Object.entries(params).filter(([,v])=>v!==null&&v!=='')),
          headers: {Accept:'application/json'}
        });
      } catch(xhr) {
        throw {status:xhr.status, data:xhr.responseJSON || {}};
      }
    }

    async function apiPost(url, payload = {}) {
      return apiSend(url, 'POST', payload);
    }

    async function apiSend(url, method, payload = null) {
      try {
        return await $.ajax({
          url, method, dataType:'json',
          data: payload === null ? undefined : JSON.stringify(payload),
          processData:false,
          contentType:'application/json',
          headers: {Accept:'application/json','X-CSRF-TOKEN':csrfToken || ''}
        });
      } catch(xhr) {
        throw {status:xhr.status, data:xhr.responseJSON || {}};
      }
    }

    const importHtml = (value) => String(value ?? "").replace(/[&<>"']/g, (char) => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;"
    }[char]));

    $('#itemImportForm').on("submit", async (event) => {
      event.preventDefault();
      const form = event.currentTarget;
      const file = $('#itemImportFile')[0]?.files?.[0];
      if (!file) return;
      const button = $(form).find('button[type="submit"]')[0];
      const preview = $('#itemImportPreview')[0];
      button.disabled = true;
      button.textContent = "Uploading and validating...";
      preview.hidden = false;
      preview.innerHTML = '<div class="placeholder-glow"><span class="placeholder col-12"></span><span class="placeholder col-7"></span></div>';
      try {
        const body = new FormData();
        body.append("file", file);
        let result;
        try {
          result = await $.ajax({
            url:endpoints.stageItemImport, method:'POST',
            data:body, dataType:'json', contentType:false, processData:false,
            headers:{Accept:'application/json','X-CSRF-TOKEN':csrfToken || ''}
          });
        } catch(xhr) {
          throw {status:xhr.status,data:xhr.responseJSON || {}};
        }
        renderItemImportPreview(result.data);
        await loadItemImportHistory();
      } catch (error) {
        const message = error?.data?.message || error?.data?.errors?.file?.[0] || "Could not stage this CSV import.";
        preview.innerHTML = `<div class="alert alert-danger mb-0">${importHtml(message)}</div>`;
      } finally {
        button.disabled = false;
        button.innerHTML = '<i class="bi bi-upload me-1"></i>Preview Import';
      }
    });

    function renderItemImportPreview(data) {
      const preview = $('#itemImportPreview')[0];
      const counts = data?.counts || {};
      const rows = data?.rows || [];
      const failed = Number(counts.failed || 0);
      const rowsHtml = rows.map((row) => {
        const errors = (row.errors || []).join("; ");
        return `<tr><td>${Number(row.row_number) || ""}</td><td>${importHtml(row.item?.item_id || "New")}</td><td>${importHtml(row.item?.name)}</td><td>${importHtml(row.action)}</td><td class="text-danger">${importHtml(errors)}</td></tr>`;
      }).join("");
      const applyUrl = endpoints.applyItemImport.replace(/0(?=\D*$)/, encodeURIComponent(data.batch_id));
      const errorsUrl = endpoints.itemImportErrors.replace(/0(?=\D*$)/, encodeURIComponent(data.batch_id));
      preview.hidden = false;
      preview.innerHTML = `
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
          <div><strong>Import preview</strong><div class="pv-small pv-muted">${Number(counts.total || 0)} rows · ${Number(counts.added || 0)} new · ${Number(counts.updated || 0)} updates · ${Number(counts.skipped || 0)} skipped · ${failed} errors</div></div>
          <div class="d-flex gap-2">${failed ? `<a class="btn btn-outline-danger btn-sm" href="${errorsUrl}">Download errors</a>` : ""}<button class="btn btn-success btn-sm" id="applyItemImport" ${failed || data.status !== "staged" ? "disabled" : ""}>Confirm and apply</button></div>
        </div>
        <div class="table-responsive" style="max-height:280px"><table class="table table-sm table-bordered align-middle mb-0"><thead><tr><th>Row</th><th>Item ID</th><th>Name</th><th>Action</th><th>Validation</th></tr></thead><tbody>${rowsHtml}</tbody></table></div>`;
      $('#applyItemImport').on("click", async (event) => {
        const button = event.currentTarget;
        const confirmation = await Swal.fire({ icon: "warning", title: "Apply item import?", text: "Only item names, units and default rates will change. Stock balances and transaction history will remain unchanged.", showCancelButton: true, confirmButtonText: "Apply import" });
        if (!confirmation.isConfirmed) return;
        button.disabled = true;
        button.textContent = "Applying...";
        try {
          const applied = await apiPost(applyUrl, { confirmed: true });
          viewToast("success", "Import applied", `${applied.data?.added || 0} added, ${applied.data?.updated || 0} updated.`);
          preview.innerHTML = '<div class="alert alert-success mb-0">Import applied. Item master refreshed; stock history was not changed.</div>';
          await loadMeta();
          await loadItemsTable();
          await loadItemImportHistory();
        } catch (error) {
          handleApiError(error, "Unable to apply stock item import.");
          button.disabled = false;
          button.textContent = "Confirm and apply";
        }
      });
    }
    $(document).on('change', '#accounts_id', function () {
      const accountID = $(this).val();
      const subAccountSelect = $('#subaccounts_id');

      if (accountID) {
        $.ajax({
          url: '{{ route('get_account') }}',
          type: 'POST',
          dataType: 'json',
          data: {
            projectID: {{ getSelectedTown() }}, // Replace with the dynamic project ID if applicable
            accountID: accountID,
            action: 'get_child',
            active_only: true,
            _token: '{{ csrf_token() }}' // CSRF token for Laravel
          },
          success: function (response) {
            // Clear and populate the sub-account dropdown
            subAccountSelect.empty().append(
              '<option value="">Select Sub-Account</option>');

            if (response.length > 0) {
              $.each(response, function (index, item) {
                if (item.subhead_accounting) {
                  subAccountSelect.append('<option value="' + item
                    .id + '">' + item.subhead_accounting
                      .name + '</option>');
                }
              });
            } else {
              alert('No sub-accounts found for the selected account.');
            }
          },
          error: function (xhr, status, error) {
            alert('Error: ' + error);
          }
        });
      } else {
        // Reset the sub-account dropdown if no account is selected
        subAccountSelect.empty().append('<option value="">Select Sub-Account</option>');
      }
    });
    function withId(templateUrl, id) {
      return templateUrl.replace(/\/0$/, `/${id}`);
    }

    function firstValidationError(errors) {
      if (!errors) return null;
      const keys = Object.keys(errors);
      if (!keys.length) return null;
      return errors[keys[0]]?.[0] || null;
    }

    function handleApiError(err, fallbackMessage) {
      const data = err?.data || {};
      if (data?.key === "validation_failed") {
        const msg = firstValidationError(data.errors) || "Validation failed.";
        Swal.fire({ icon: "error", title: "Validation", text: msg });
        return;
      }
      if (data?.key === "insufficient_stock") {
        Swal.fire({ icon: "error", title: "Stock", text: data.message || "Insufficient stock." });
        return;
      }
      Swal.fire({
        icon: "error",
        title: "Error",
        text: data?.message || fallbackMessage || "Something went wrong.",
      });
    }

    function debounce(fn, delay) {
      let t;
      return (...args) => {
        clearTimeout(t);
        t = setTimeout(() => fn(...args), delay);
      };
    }

    /* ===========================
  UTIL
  =========================== */
    const fmtInt = (n) =>
      new Intl.NumberFormat("en-US", { maximumFractionDigits: 0 }).format(
        Number(n || 0)
      );
    const fmtPKR = (n) => "PKR " + fmtInt(n);

    function pvFormatAmountNoDecimal(input) {
      if (!input) return;
      let raw = String(input.value ?? "");
      raw = raw.split(".")[0];
      raw = raw.replace(/[^0-9]/g, "");
      raw = raw.replace(/^0+(?=\d)/, "");
      raw = raw.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
      input.value = raw;
    }

    function pvParseIntAmount(v) {
      v = String(v ?? "")
        .split(".")[0]
        .replace(/[^0-9]/g, "");
      return v ? parseInt(v, 10) || 0 : 0;
    }

    function todayISO() {
      const d = new Date();
      const pad = (x) => String(x).padStart(2, "0");
      return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(
        d.getDate()
      )}`;
    }
    function daysAgoISO(days) {
      const d = new Date();
      d.setDate(d.getDate() - days);
      const pad = (x) => String(x).padStart(2, "0");
      return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(
        d.getDate()
      )}`;
    }

    function findItem(id) {
      return state.items.find((x) => x.id === Number(id));
    }
    function findParty(id) {
      return state.parties.find((x) => x.id === Number(id));
    }

    function viewToast(icon, title, text) {
      return Swal.fire({
        icon,
        title,
        text,
        timer: 1400,
        showConfirmButton: false,
        toast: true,
        position: "top-end",
      });
    }

    /* ===========================
  VIEW SWITCH
  =========================== */
    const tabBtns = $(".pv-tabbtn").toArray();
    const views = {
      entry: $('#view-entry')[0],
      billing: $('#view-billing')[0],
      stock: $('#view-stock')[0],
      items: $('#view-items')[0],
      parties: $('#view-parties')[0],
    };
    function setView(name) {
      tabBtns.forEach((b) =>
        $(b).toggleClass("active", b.dataset.view === name)
      );
      Object.entries(views).forEach(([k, el]) =>
        $(el).toggleClass("d-none", k !== name)
      );
    }

    /* ===========================
  SELECTS POPULATION
  =========================== */
    function fillPartySelect(sel, withAll = false) {
      const placeholder = withAll ? "All" : (sel.id === "billParty" ? "Select a Supplier or Site" : "Select a Shop/Party or Site/Customer");
      sel.innerHTML = `<option value="">${placeholder}</option>`;
      if (withAll)
        sel.options[0].textContent = "All";
      state.parties.forEach((p) => {
        sel.insertAdjacentHTML(
          "beforeend",
          `<option value="${p.id}">${p.name} • ${p.type}</option>`
        );
      });
    }
    function fillItemSelect(sel, withAll = false) {
      sel.innerHTML = "";
      if (withAll)
        sel.insertAdjacentHTML("beforeend", `<option value="">All</option>`);
      state.items.forEach((i) => {
        sel.insertAdjacentHTML(
          "beforeend",
          `<option value="${i.id}">${i.name} (${i.unit})</option>`
        );
      });
      if (!state.items.length) {
        sel.insertAdjacentHTML("beforeend", `<option value="">No items</option>`);
      }
    }

    function refreshLineItemSelects() {
      $('.line-item').each((_,sel) => {
        const current = sel.value;
        fillItemSelect(sel, false);
        if (current) sel.value = current;
        sel.dispatchEvent(new Event("change"));
      });
    }

    /* ===========================
  ENTRY MODE (Purchase / Stock Out)
  =========================== */
    let entryMode = "purchase"; // purchase | stockout
    const entryTitle = $('#entryTitle')[0];
    const entryPartyLabel = $('#entryPartyLabel')[0];
    const entryReasonLabel = $('#entryReasonLabel')[0];
    const btnPurchaseMode = $('#btnPurchaseMode')[0];
    const btnStockOutMode = $('#btnStockOutMode')[0];

    function setEntryMode(mode) {
      entryMode = mode;
      const isPurchase = mode === "purchase";
      entryTitle.textContent = isPurchase
        ? "Purchase Entry"
        : "Stock Out Entry";
      entryPartyLabel.textContent = isPurchase
        ? "Shop / Party"
        : "Site & Customer";
      entryReasonLabel.textContent = isPurchase
        ? "Purchase Reason Details"
        : "Using Reason Details";
      $(btnPurchaseMode).toggleClass("btn-outline-primary", !isPurchase);
      $(btnPurchaseMode).toggleClass("btn-primary", isPurchase);
      $(btnStockOutMode).toggleClass("btn-outline-secondary", isPurchase);
      $(btnStockOutMode).toggleClass("btn-secondary", !isPurchase);
    }
    $(btnPurchaseMode).on("click", () => setEntryMode("purchase"));
    $(btnStockOutMode).on("click", () => setEntryMode("stockout"));

    /* ===========================
  ENTRY LINES
  =========================== */
    const entryLinesWrap = $('#entryLines')[0];
    const entryTotalEl = $('#entryTotal')[0];

    function makeLineRow(prefix, idx) {
      const lineId = `${prefix}_line_${idx}_${Math.random()
        .toString(16)
        .slice(2)}`;
      const html = `
          <div class="pv-line" data-line="${lineId}">
            <div class="row g-2 align-items-end">
              <div class="col-md-5">
                <label class="form-label pv-small">Item</label>
                <select class="form-select line-item" required></select>
              </div>
              <div class="col-md-2">
                <label class="form-label pv-small">Unit</label>
                <input class="form-control line-unit" readonly value="—">
              </div>
              <div class="col-md-2">
                <label class="form-label pv-small">Qty</label>
                <input class="form-control line-qty" inputmode="numeric" placeholder="0" required>
              </div>
              <div class="col-md-2">
                <label class="form-label pv-small">Rate</label>
                <input class="form-control line-rate pv-money" inputmode="numeric" placeholder="0">
              </div>
              <div class="col-md-1 d-grid">
                <button type="button" class="btn btn-outline-danger" title="Remove">
                  <i class="bi bi-x-lg"></i>
                </button>
              </div>
            </div>
            <div class="d-flex align-items-center justify-content-between mt-2">
              <div class="pv-small pv-muted">Line Amount</div>
              <div class="fw-bold line-amount">PKR 0</div>
            </div>
          </div>
        `;

      const temp = $('<div>')[0];
      temp.innerHTML = html.trim();
      const row = temp.firstElementChild;

      const itemSel = $(row).find(".line-item")[0];
      fillItemSelect(itemSel, false);

      // default first item
      if (state.items.length) itemSel.value = state.items[0].id;

      const unitEl = $(row).find(".line-unit")[0];
      const qtyEl = $(row).find(".line-qty")[0];
      const rateEl = $(row).find(".line-rate")[0];
      const amtEl = $(row).find(".line-amount")[0];

      function syncFromItem() {
        const it = findItem(itemSel.value);
        unitEl.value = it ? it.unit : "—";
        if (it && (!rateEl.value || pvParseIntAmount(rateEl.value) === 0)) {
          rateEl.value = fmtInt(it.default_rate || 0);
          pvFormatAmountNoDecimal(rateEl);
        }
        recalc();
      }

      function recalc() {
        const qty = pvParseIntAmount(qtyEl.value);
        const rate = pvParseIntAmount(rateEl.value);
        const amt = qty * rate;
        amtEl.textContent = fmtPKR(amt);
        if (prefix === "entry") recalcEntryTotal();
        if (prefix === "bill") recalcBillTotal();
      }

      $(itemSel).on("change", syncFromItem);
      $(qtyEl).on("input", () => {
        qtyEl.value = qtyEl.value.replace(/[^0-9]/g, "");
        recalc();
      });
      $(rateEl).on("input", () => {
        pvFormatAmountNoDecimal(rateEl);
        recalc();
      });
      $(row).find("button").on("click", () => {
        row.remove();
        recalcEntryTotal();
      });

      // initial sync
      syncFromItem();

      return row;
    }

    function recalcEntryTotal() {
      let total = 0;
      $(entryLinesWrap).find(".pv-line").toArray().forEach((row) => {
        const qty = pvParseIntAmount($(row).find(".line-qty")[0].value);
        const rate = pvParseIntAmount($(row).find(".line-rate")[0].value);
        total += qty * rate;
      });
      entryTotalEl.textContent = fmtPKR(total);
    }

    $('#btnAddLine').on("click", () => {
      entryLinesWrap.appendChild(
        makeLineRow("entry", entryLinesWrap.children.length + 1)
      );
    });

    /* ===========================
  ENTRY SUBMIT
  =========================== */
    const entryForm = $('#entryForm')[0];
    const entrySlipNo = $('#entrySlipNo')[0];
    const entryDate = $('#entryDate')[0];
    const entryParty = $('#entryParty')[0];
    const entryReason = $('#entryReason')[0];
    const entryVehicle = $('#entryVehicle')[0];
    const entryDriver = $('#entryDriver')[0];


    function collectLines(wrap) {
      const lines = [];
      $(wrap).find(".pv-line").toArray().forEach((row) => {
        const itemId = Number($(row).find(".line-item")[0].value);
        const qtyRaw = String($(row).find(".line-qty")[0].value || "").replace(
          /[^0-9]/g,
          ""
        );
        const rate = pvParseIntAmount($(row).find(".line-rate")[0].value);
        if (!itemId || !qtyRaw || Number(qtyRaw) <= 0) return;
        lines.push({ item_id: itemId, qty: qtyRaw, rate });
      });
      return lines;
    }

    function entryFlow(mode) {
      return mode === "purchase" ? "IN" : "OUT";
    }

    $(entryForm).on("submit", async (e) => {
      e.preventDefault();

      if (!entryParty.value) {
        Swal.fire({
          icon: "error",
          title: state.parties.length ? "Select a party" : "No parties configured",
          text: state.parties.length ? "Choose a Shop/Party or Site/Customer from the list." : "Open Stock → Parties and add an active party for this project first.",
        });
        return;
      }

      const lines = collectLines(entryLinesWrap);
      if (!lines.length) {
        Swal.fire({
          icon: "error",
          title: "No items",
          text: "Add at least one item with Qty > 0.",
        });
        return;
      }

      $('#entrySubmitBtn')[0].disabled = true;
      try {
        const res = await apiPost(endpoints.storeEntry, {
          date: entryDate.value,
          party_id: Number(entryParty.value),
          vehicle: entryVehicle.value || "",
          driver: entryDriver.value || "",
          reason: entryReason.value || "",
          flow: entryFlow(entryMode),
          lines,
        });

        viewToast("success", "Saved", `Entry saved (${res?.data?.slip_no || ""}).`);

        entryParty.value = "";
        entryReason.value = "";
        entryVehicle.value = "";
        entryDriver.value = "";
        entryLinesWrap.innerHTML = "";
        entryLinesWrap.appendChild(makeLineRow("entry", 1));
        recalcEntryTotal();

        await loadNextSlip();
        await Promise.all([loadEntries(), loadReport()]);
      } catch (err) {
        handleApiError(err, "Unable to save entry.");
      } finally {
        $('#entrySubmitBtn')[0].disabled = false;
      }
    });


    /* ===========================
  BILLING LINES + SUBMIT
  =========================== */
    const billForm = $('#billForm')[0];
    const billType = $('#billType')[0];
    const billDate = $('#billDate')[0];
    const billParty = $('#billParty')[0];
    const billNo = $('#billNo')[0];
    const billNoChip = $('#billNoChip')[0];
    const billEntryNo = $('#billEntryNo')[0];
    const billLinesWrap = $('#billLines')[0];
    const billTotalEl = $('#billTotal')[0];

    function makeBillLineRow(idx) {
      const row = makeLineRow("bill", idx);
      // tweak labels a bit
      $(row).find('.line-amount').closest('.d-flex').find('.pv-muted').text('Line Amount');
      return row;
    }

    function recalcBillTotal() {
      let total = 0;
      $(billLinesWrap).find(".pv-line").toArray().forEach((row) => {
        const qty = pvParseIntAmount($(row).find(".line-qty")[0].value);
        const rate = pvParseIntAmount($(row).find(".line-rate")[0].value);
        total += qty * rate;
      });
      billTotalEl.textContent = fmtPKR(total);
    }

    $(billLinesWrap).on("input", (e) => {
      if (
        e.$(target).hasClass("line-qty") ||
        e.$(target).hasClass("line-rate")
      ) {
        recalcBillTotal();
      }
    });

    $('#btnAddBillLine').on("click", () => {
        billLinesWrap.appendChild(
          makeBillLineRow(billLinesWrap.children.length + 1)
        );
        recalcBillTotal();
      });

    $(billForm).on("submit", async (e) => {
      e.preventDefault();

      if (!billParty.value) {
        Swal.fire({
          icon: "error",
          title: "Missing Party",
          text: "Please select Supplier / Site Name.",
        });
        return;
      }

      const lines = collectLines(billLinesWrap);
      if (!lines.length) {
        Swal.fire({
          icon: "error",
          title: "No items",
          text: "Add at least one bill item with Qty > 0.",
        });
        return;
      }

      $(billForm).find('button[type="submit"]')[0].disabled = true;
      try {
        const res = await apiPost(endpoints.storeBill, {
          type: billType.value,
          date: billDate.value,
          party_id: Number(billParty.value),
          entry_no: billEntryNo.value || "",
          lines,
        });

        viewToast(
          "success",
          "Saved",
          `${billType.value.toUpperCase()} bill saved (${res?.data?.bill_no || ""}).`
        );

        billEntryNo.value = "";
        billLinesWrap.innerHTML = "";
        billLinesWrap.appendChild(makeBillLineRow(1));
        recalcBillTotal();

        await loadNextBill();
        await Promise.all([loadBills(), loadReport()]);
      } catch (err) {
        handleApiError(err, "Unable to save bill.");
      } finally {
        $(billForm).find('button[type="submit"]')[0].disabled = false;
      }
    });


    /* ===========================
  PROJECT-SCOPED ITEMS + PARTIES MANAGEMENT
  =========================== */
    const editModalEl = $('#editModal')[0];
    const editModal = new bootstrap.Modal(editModalEl);

    function openEditModal(title, fields, onSave) {
      $('#editModalTitle')[0].textContent = title;

      const form = $('#editForm')[0];
      form.innerHTML = "";

      fields.forEach((f) => {
        form.insertAdjacentHTML(
          "beforeend",
          `
            <div class="mb-2">
              <label class="form-label pv-small">${f.label}</label>
              ${f.type === "select"
            ? `<select class="form-select" name="${f.name}">${(f.options || [])
              .map((o) => `<option value="${o.value}">${o.label}</option>`)
              .join("")}</select>`
            : `<input class="form-control ${f.money ? "pv-money" : ""}" name="${f.name
            }" value="${f.value ?? ""}">`
          }
            </div>
          `
        );
        if (f.type === "select") {
          $(form).find(`[name="${f.name}"]`)[0].value = f.value ?? "";
        }
      });

      form.insertAdjacentHTML(
        "beforeend",
        `
          <div class="d-grid gap-2 mt-3">
            ${onSave
          ? `<button type="button" class="btn btn-primary btn-lg" id="editSaveBtn">
              <i class="bi bi-check2-circle me-1"></i> Save
            </button>`
          : ""
        }
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
              ${onSave ? "Cancel" : "Close"}
            </button>
          </div>
        `
      );

      $(form).find(".pv-money").toArray().forEach((el) => {
        pvFormatAmountNoDecimal(el);
        $(el).on("input", () => pvFormatAmountNoDecimal(el));
      });

      if (onSave) {
        $(form).find("#editSaveBtn").on("click", () => {
          const data = Object.fromEntries(new FormData(form).entries());
          onSave(data);
        });
      }

      editModal.show();
    }

    /* Items */
    $('#itemForm').on("submit", async (e) => {
      e.preventDefault();
      const name = $('#itemName')[0].value.trim();
      const unit = $('#itemUnit')[0].value.trim();
      const rate = pvParseIntAmount(
        $('#itemRate')[0].value
      );

      if (!name || !unit) return;

      try {
        await apiPost(endpoints.storeItem, {
          name,
          unit,
          default_rate: rate,
        });
        viewToast("success", "Added", "Item created.");

        $('#itemName')[0].value = "";
        $('#itemUnit')[0].value = "";
        $('#itemRate')[0].value = "";

        await loadMeta();
        await loadItemsTable();
      } catch (err) {
        handleApiError(err, "Unable to create item.");
      }
    });


    /* Parties */
    $('#partyForm').on("submit", async (e) => {
      e.preventDefault();
      const type = $('#partyType')[0].value;
      const name = $('#partyName')[0].value.trim();
      if (!name) return;

      try {
        await apiPost(endpoints.storeParty, {
          type,
          name,
          mobile: $('#partyMobile')[0].value.trim(),
          address: $('#partyAddress')[0].value.trim(),
          head: $('#accounts_id')[0].value.trim(),
          subhead: $('#subaccounts_id')[0].value.trim(),
        });

        viewToast("success", "Added", "Party created.");

        $('#partyName')[0].value = "";
        $('#partyMobile')[0].value = "";
        $('#partyAddress')[0].value = "";
        $('#accounts_id')[0].value = "";
        $('#subaccounts_id')[0].value = "";

        await loadMeta();
        await loadPartiesTable();
      } catch (err) {
        console.error(err);
        handleApiError(err, "Unable to create party.");
      }
    });


    /* ===========================
  DATA LOADERS
  =========================== */
    async function loadMeta() {
      const res = await apiGet(endpoints.meta);
      state.items = res?.data?.items || [];
      state.parties = res?.data?.parties || [];

      fillPartySelect(entryParty, false);
      fillPartySelect(billParty, false);
      fillItemSelect($('#entryFilterItem')[0], true);
      fillItemSelect($('#stockItemFilter')[0], true);
      fillPartySelect($('#billFilterParty')[0], true);
      refreshLineItemSelects();
    }

    async function loadNextSlip() {
      const res = await apiGet(endpoints.nextSlip);
      entrySlipNo.value = res?.data?.slip_no || "";
    }

    async function loadNextBill() {
      const res = await apiGet(endpoints.nextBill, { type: billType.value });
      billNo.value = res?.data?.bill_no || "";
      billNoChip.textContent = billNo.value || "Bill no… auto";
    }

    async function loadEntries() {
      const res = await apiGet(endpoints.entries, {
        item_id: $('#entryFilterItem')[0].value || null,
        flow: $('#entryFilterFlow')[0].value || null,
        q: $('#entrySearch')[0].value || null,
        per_page: 50,
        page: 1,
      });
      state.entries = res?.data || { rows: [], total: 0, kpi: {} };
      renderEntryTable();
    }

    async function loadBills() {
      const res = await apiGet(endpoints.bills, {
        type: $('#billFilterType')[0].value || null,
        party_id: $('#billFilterParty')[0].value || null,
        q: $('#billSearch')[0].value || null,
        per_page: 50,
        page: 1,
      });
      state.bills = res?.data || { rows: [], total: 0, kpi: {} };
      renderBillTable();
    }

    async function loadReport() {
      const res = await apiGet(endpoints.report, {
        from: $('#stockFrom')[0].value || null,
        to: $('#stockTo')[0].value || null,
        item_id: $('#stockItemFilter')[0].value || null,
      });
      state.report = res?.data || { rows: [], kpi: {} };
      renderStock();
    }

    async function loadItemsTable() {
      const res = await apiGet(endpoints.items, {
        q: $('#itemSearch')[0].value || null,
      });
      state.itemsTable = res?.data || { rows: [], total: 0 };
      renderItems();
    }

    async function loadItemImportHistory() {
      const tbody = $('#itemImportHistoryBody')[0];
      try {
        const result = await apiGet(endpoints.itemImportHistory);
        const rows = result?.data?.rows || [];
        tbody.innerHTML = rows.length ? rows.map((row) => `
          <tr><td>#${Number(row.id)}</td><td>${importHtml(row.created_at || "")}</td><td>${importHtml(row.created_by || "-")}</td><td><span class="pv-pill ${row.status === "applied" ? "ok" : "warn"}">${importHtml(row.status)}</span></td><td>${Number(row.added)} / ${Number(row.updated)} / ${Number(row.failed)}</td><td><button type="button" class="btn btn-outline-primary btn-sm" data-import-preview="${Number(row.id)}">Preview</button></td></tr>`).join("") : '<tr><td colspan="6" class="text-muted text-center">No import batches yet.</td></tr>';
        $(tbody).find("[data-import-preview]").toArray().forEach((button) => $(button).on("click", async () => {
          try {
            const url = endpoints.itemImportPreview.replace(/0(?=\D*$)/, encodeURIComponent(button.dataset.importPreview));
            const detail = await apiGet(url);
            renderItemImportPreview(detail.data);
            $('#itemImportPreview')[0].scrollIntoView({ behavior: "smooth", block: "nearest" });
          } catch (error) { handleApiError(error, "Unable to load this import preview."); }
        }));
      } catch (error) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-danger text-center">${importHtml(error?.data?.message || "Import history is unavailable until its migration is installed.")}</td></tr>`;
      }
    }

    async function loadPartiesTable() {
      const res = await apiGet(endpoints.parties, {
        q: $('#partySearch')[0].value || null,
        type: $('#partyFilterType')[0].value || null,
      });
      state.partiesTable = res?.data || { rows: [], total: 0 };
      renderParties();
    }

    /* ===========================
  RENDERING
  =========================== */
    function renderEntryTable() {
      const tbody = $('#entryTbody')[0];
      tbody.innerHTML = "";
      (state.entries.rows || []).forEach((en, idx) => {
        const billingPill = en.billed_at
          ? `<span class="pv-pill ok">Billed</span>`
          : `<span class="pv-pill warn">Unbilled</span>`;
        const flowPill =
          en.flow === "IN"
            ? `<span class="pv-pill ok">IN</span>`
            : `<span class="pv-pill bad">OUT</span>`;

        tbody.insertAdjacentHTML(
          "beforeend",
          `
            <tr>
              <td>${idx + 1}</td>
              <td>${en.entry_date || "—"}</td>
              <td>${flowPill}</td>
              <td>${en.party_name || "—"}</td>
              <td class="text-truncate" style="max-width:220px;">${en.reason || "—"
          }</td>
              <td>${en.item_name || "—"}</td>
              <td>${fmtInt(en.qty)}</td>
              <td>${fmtPKR(en.rate || 0)}</td>
              <td>${fmtPKR(en.amount || 0)}</td>
              <td>${billingPill}</td>
            </tr>
          `
        );
      });

      $('#entryCountChip')[0].textContent = `${state.entries.total || 0
        } total`;

      const kpi = state.entries.kpi || {};
      $('#kpiInQty')[0].textContent = fmtInt(
        kpi.in_qty || 0
      );
      $('#kpiOutQty')[0].textContent = fmtInt(
        kpi.out_qty || 0
      );
      $('#kpiLines')[0].textContent = fmtInt(
        kpi.line_count || 0
      );

    }

    function renderBillTable() {
      const tbody = $('#billTbody')[0];
      tbody.innerHTML = "";
      (state.bills.rows || []).forEach((b, idx) => {
        tbody.insertAdjacentHTML(
          "beforeend",
          `
            <tr>
              <td>${idx + 1}</td>
              <td>${b.date || "—"}</td>
              <td><span class="pv-pill ${b.type === "purchase" ? "ok" : "warn"
          }">${(b.type || "").toUpperCase()}</span></td>
              <td>${b.party_name || "—"}</td>
              <td class="text-truncate" style="max-width:260px;">${b.bill_no || ""}${b.entry_no ? " • Entry: " + b.entry_no : ""
          }</td>
              <td>${fmtPKR(b.total || 0)}</td>
            </tr>
          `
        );
      });

      $('#billCountChip')[0].textContent = `${state.bills.total || 0
        } total`;

      const kpi = state.bills.kpi || {};
      $('#kpiPurchaseBills')[0].textContent = fmtInt(
        kpi.purchase_count || 0
      );
      $('#kpiSaleBills')[0].textContent = fmtInt(
        kpi.sale_count || 0
      );
      $('#kpiBillTotal')[0].textContent = fmtPKR(
        kpi.total_amount || 0
      );

    }

    function renderStock() {
      const from = $('#stockFrom')[0].value;
      const to = $('#stockTo')[0].value;
      const rows = state.report.rows || [];
      const tbody = $('#stockTbody')[0];
      tbody.innerHTML = "";

      rows.forEach((r) => {
        tbody.insertAdjacentHTML(
          "beforeend",
          `
            <tr>
              <td>${r.item_name || "—"}</td>
              <td>${fmtInt(r.purchase_qty || 0)}</td>
              <td>${fmtInt(r.sale_qty || 0)}</td>
              <td><span class="pv-pill ${Number(r.available) < 0 ? "bad" : "ok"}">${fmtInt(
            r.available || 0
          )}</span></td>
            </tr>
          `
        );
      });

      const kpi = state.report.kpi || {};
      $('#kpiStockItems')[0].textContent = fmtInt(
        kpi.items || 0
      );
      $('#kpiStockIn')[0].textContent = fmtInt(
        kpi.in_qty || 0
      );
      $('#kpiStockOut')[0].textContent = fmtInt(
        kpi.out_qty || 0
      );
      $('#kpiStockAvail')[0].textContent = fmtInt(
        kpi.available || 0
      );

      const chip = $('#stockRangeChip')[0];
      chip.textContent = `Range: ${from || "—"} → ${to || "—"}`;
    }

    function renderItems() {
      const tbody = $('#itemTbody')[0];
      tbody.innerHTML = "";
      (state.itemsTable.rows || []).forEach((it, idx) => {
        tbody.insertAdjacentHTML(
          "beforeend",
          `
            <tr>
              <td><input type="checkbox" class="stock-item-select" value="${Number(it.id)}" aria-label="Select ${importHtml(it.name)}"></td>
              <td>${idx + 1}</td>
              <td>${it.name}</td>
              <td>${it.unit}</td>
              <td>${fmtPKR(it.default_rate || 0)}</td>
              <td class="text-end pv-actions">
                <button class="btn btn-outline-secondary btn-sm" data-act="edit">
                  <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-outline-danger btn-sm" data-act="del">
                  <i class="bi bi-trash3"></i>
                </button>
              </td>
            </tr>
          `
        );
      });

      $('#itemCountChip')[0].textContent = `${state.itemsTable.total || 0} total`;

      const exportSelected = $('#exportSelectedItems')[0];
      const selectAll = $('#selectAllStockItems')[0];
      if (selectAll) selectAll.checked = false;
      const syncSelectedExport = () => {
        const ids = Array.from($(tbody).find(".stock-item-select:checked").toArray(), (input) => input.value);
        const url = new URL(endpoints.exportItems, window.location.origin);
        url.searchParams.set("format", "xlsx");
        ids.forEach((id) => url.searchParams.append("item_ids[]", id));
        exportSelected.href = url.toString();
        $(exportSelected).toggleClass("disabled", ids.length === 0);
        exportSelected.setAttribute("aria-disabled", ids.length === 0 ? "true" : "false");
      };
      if (selectAll) $(selectAll).off('change.stockExport').on('change.stockExport', () => {
        $(tbody).find('.stock-item-select').prop('checked',$(selectAll).prop('checked'));
        syncSelectedExport();
      });
      $(tbody).find(".stock-item-select").toArray().forEach((input) => $(input).on("change", syncSelectedExport));
      if (exportSelected) $(exportSelected).on('click', (event) => {
        if (!$(tbody).find(".stock-item-select:checked")[0]) event.preventDefault();
      });
      syncSelectedExport();

      $(tbody).find("button[data-act]").toArray().forEach((btn) => {
        $(btn).on("click", async () => {
          const row = btn.closest("tr");
          const idx = Array.from(row.parentNode.children).indexOf(row);
          const it = state.itemsTable.rows[idx];
          if (!it) return;

          if (btn.dataset.act === "edit") {
            openEditModal(
              "Edit Item",
              [
                { label: "Item Name", name: "name", value: it.name },
                { label: "Unit", name: "unit", value: it.unit },
                {
                  label: "Default Rate (PKR)",
                  name: "default_rate",
                  value: fmtInt(it.default_rate || 0),
                  money: true,
                },
              ],
              async (data) => {
                try {
                  await apiSend(withId(endpoints.updateItem, it.id), "PUT", {
                    name: data.name || "",
                    unit: data.unit || "",
                    default_rate: pvParseIntAmount(data.default_rate),
                  });
                  viewToast("success", "Updated", "Item updated.");
                  editModal.hide();
                  await loadMeta();
                  await loadItemsTable();
                  await Promise.all([loadEntries(), loadBills(), loadReport()]);
                } catch (err) {
                  handleApiError(err, "Unable to update item.");
                }
              }
            );
            return;
          }

          if (btn.dataset.act === "del") {
            const ok = await Swal.fire({
              icon: "warning",
              title: "Archive item from this project?",
              text: "It will be hidden from new entries. Existing stock and billing history will remain preserved.",
              showCancelButton: true,
              confirmButtonText: "Archive item",
            });
            if (!ok.isConfirmed) return;
            try {
              await apiSend(withId(endpoints.deleteItem, it.id), "DELETE");
              viewToast("success", "Archived", "Item archived for this project; history was preserved.");
              await loadMeta();
              await loadItemsTable();
              await Promise.all([loadEntries(), loadBills(), loadReport()]);
            } catch (err) {
              handleApiError(err, "Unable to delete item.");
            }
          }
        });
      });
    }

    function renderParties() {
      const tbody = $('#partyTbody')[0];
      tbody.innerHTML = "";
      (state.partiesTable.rows || []).forEach((p, idx) => {
        tbody.insertAdjacentHTML(
          "beforeend",
          `
            <tr>
              <td>${idx + 1}</td>
              <td>${p.vendor_code || "â€”"}</td>
              <td>${p.name}</td>
              <td>${p.mobile || "—"}</td>
              <td class="text-truncate" style="max-width:160px;">${p.address || "—"
          }</td>
              <td>${p.head || "—"}</td>
              <td>${p.subhead || "—"}</td>
              <td><span class="pv-pill">${p.type}</span></td>
              <td><span class="pv-pill ${p.is_active ? "ok" : "bad"}">${p.is_active ? "Active" : "Inactive"}</span></td>
              <td class="text-end pv-actions">
                <button class="btn btn-outline-secondary btn-sm" data-act="edit">
                  <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-outline-danger btn-sm" data-act="del">
                  <i class="bi bi-trash3"></i>
                </button>
                <button class="btn btn-outline-primary btn-sm" data-act="status" title="Change active status">
                  ${p.is_active ? "Deactivate" : "Activate"}
                </button>
              </td>
            </tr>
          `
        );
      });

      $('#partyCountChip')[0].textContent = `${state.partiesTable.total || 0} total`;

      $(tbody).find("button[data-act]").toArray().forEach((btn) => {
        $(btn).on("click", async () => {
          const row = btn.closest("tr");
          const idx = Array.from(row.parentNode.children).indexOf(row);
          const p = state.partiesTable.rows[idx];
          if (!p) return;

          if (btn.dataset.act === "status") {
            const result = await Swal.fire({
              icon: "question",
              title: p.is_active ? "Deactivate party?" : "Activate party?",
              input: "textarea",
              inputLabel: "Reason (required)",
              inputAttributes: { maxlength: 500 },
              showCancelButton: true,
              confirmButtonText: p.is_active ? "Deactivate" : "Activate",
              inputValidator: (value) => !value?.trim() ? "Enter a reason to continue." : undefined,
            });
            if (!result.isConfirmed) return;
            try {
              await apiSend(withId(endpoints.partyStatus, p.id), "PATCH", {
                is_active: p.is_active ? 0 : 1,
                reason: result.value.trim(),
              });
              viewToast("success", "Updated", "Party status updated.");
              await loadMeta();
              await loadPartiesTable();
            } catch (err) {
              handleApiError(err, "Unable to update party status.");
            }
            return;
          }

          if (btn.dataset.act === "edit") {
            openEditModal(
              "Edit Party",
              [
                {
                  label: "Type",
                  name: "type",
                  type: "select",
                  value: p.type,
                  options: [
                    { value: "Supplier", label: "Supplier" },
                    { value: "Consumer", label: "Consumer" },
                    { value: "Site", label: "Site" },
                  ],
                },
                { label: "Name", name: "name", value: p.name },
                { label: "Mobile", name: "mobile", value: p.mobile || "" },
                { label: "Address", name: "address", value: p.address || "" },
                {
                  label: "Project Account",
                  name: "account_id",
                  type: "select",
                  value: p.account_id || "",
                  options: [
                    { value: "", label: "Keep existing / unassigned" },
                    ...partyAccountOptions.map((account) => ({ value: account.id, label: account.label }))
                  ],
                },
              ],
              async (data) => {
                try {
                  await apiSend(withId(endpoints.updateParty, p.id), "PUT", {
                    type: data.type || "",
                    name: data.name || "",
                    mobile: data.mobile || "",
                    address: data.address || "",
                    account_id: data.account_id || "",
                  });
                  viewToast("success", "Updated", "Party updated.");
                  editModal.hide();
                  await loadMeta();
                  await loadPartiesTable();
                  await Promise.all([loadEntries(), loadBills(), loadReport()]);
                } catch (err) {
                  handleApiError(err, "Unable to update party.");
                }
              }
            );
            return;
          }

          if (btn.dataset.act === "del") {
            const ok = await Swal.fire({
              icon: "warning",
              title: "Archive party?",
              text: "Parties with transaction history cannot be archived. Parties without history are soft-archived; records are never hard-deleted.",
              showCancelButton: true,
              confirmButtonText: "Archive party",
            });
            if (!ok.isConfirmed) return;
            try {
              await apiSend(withId(endpoints.deleteParty, p.id), "DELETE");
              viewToast("success", "Archived", "Party archived; existing history was preserved.");
              await loadMeta();
              await loadPartiesTable();
              await Promise.all([loadEntries(), loadBills(), loadReport()]);
            } catch (err) {
              handleApiError(err, "Unable to delete party.");
            }
          }
        });
      });
    }

    /* ===========================
  INIT
  =========================== */
    async function initUI() {
      entryDate.value = todayISO();
      billDate.value = todayISO();
      $('#stockFrom')[0].value = daysAgoISO(30);
      $('#stockTo')[0].value = todayISO();

      entryLinesWrap.innerHTML = "";
      entryLinesWrap.appendChild(makeLineRow("entry", 1));
      recalcEntryTotal();

      billLinesWrap.innerHTML = "";
      billLinesWrap.appendChild(makeBillLineRow(1));
      recalcBillTotal();

      $('#entryFilterItem').on("change", () =>
          loadEntries().catch((err) => handleApiError(err, "Unable to load entries."))
        );
      $('#entryFilterFlow').on("change", () =>
          loadEntries().catch((err) => handleApiError(err, "Unable to load entries."))
        );
      $('#entrySearch').on(
          "input",
          debounce(
            () =>
              loadEntries().catch((err) =>
                handleApiError(err, "Unable to load entries.")
              ),
            300
          )
        );

      $('#billFilterType').on("change", () =>
          loadBills().catch((err) => handleApiError(err, "Unable to load bills."))
        );
      $('#billFilterParty').on("change", () =>
          loadBills().catch((err) => handleApiError(err, "Unable to load bills."))
        );
      $('#billSearch').on(
          "input",
          debounce(
            () =>
              loadBills().catch((err) => handleApiError(err, "Unable to load bills.")),
            300
          )
        );

      $('#itemSearch').on(
          "input",
          debounce(
            () =>
              loadItemsTable().catch((err) =>
                handleApiError(err, "Unable to load items.")
              ),
            300
          )
        );
      $('#partySearch').on(
          "input",
          debounce(
            () =>
              loadPartiesTable().catch((err) =>
                handleApiError(err, "Unable to load parties.")
              ),
            300
          )
        );
      $('#partyFilterType').on("change", () =>
          loadPartiesTable().catch((err) => handleApiError(err, "Unable to load parties."))
        );

      $('#btnApplyStock').on("click", () =>
          loadReport().catch((err) => handleApiError(err, "Unable to load report."))
        );

      $(billType).on("change", () =>
        loadNextBill().catch((err) => handleApiError(err, "Unable to load bill no."))
      );

      $('.pv-money').each((_,el) => {
        pvFormatAmountNoDecimal(el);
        $(el).on("input", () => pvFormatAmountNoDecimal(el));
      });

      setEntryMode("purchase");

      try {
        await loadMeta();
        await Promise.all([
          loadNextSlip(),
          loadNextBill(),
          loadEntries(),
          loadBills(),
          loadReport(),
          loadItemsTable(),
          loadItemImportHistory(),
          loadPartiesTable(),
        ]);
      } catch (err) {
        handleApiError(err, "Failed to load data.");
      }
    }

    $(function () {
      initUI();
      $(tabBtns).on('click',function(){setView($(this).data('view'));});
    });
  </script>
@endsection
