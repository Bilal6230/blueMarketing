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

    body {
      background: var(--pv-bg);
      color: var(--pv-text);
      font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, "Apple Color Emoji", "Segoe UI Emoji";
    }

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
  </style>
  <div class="pv-shell">
    <!-- Sidebar (visual only) -->
    <aside class="pv-sidebar">
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
            UI only • Bootstrap • In-memory demo
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
                        <select class="form-select" id="entryParty" required></select>
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
                        Demo mode: saves in memory, updates stock report
                        instantly.
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
                          <th class="text-end">Actions</th>
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
                        <select class="form-select" id="billParty" required></select>
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
                          <th class="text-end">Actions</th>
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
                          <th>Name</th>
                          <th>Mobile</th>
                          <th>Address</th>
                          <th>Head</th>
                          <th>Sub Head</th>
                          <th>Type</th>
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
  </div>

  <!-- Modals (edit item/party) -->
  <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
      <div class="modal-content pv-card" style="border-radius: 22px">
        <div class="pv-cardhead">
          <div>
            <h2 id="editModalTitle">Edit</h2>
            <div class="pv-small pv-muted" id="editModalSub">
              Update fields (demo only).
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
  {{-- In-memory attendance records stored as JSON in DOM for demo. Initially empty (seeded below by JS). --}}
  <script type="application/json" id="initial-attendance">[]</script>
@endsection

@section('js')
  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


  <script>
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
      storeParty: "{{ route('stocks.parties.store') }}",
      storeEntry: "{{ route('stocks.entries.store') }}",
      storeBill: "{{ route('stocks.bills.store') }}",
      updateItem: "{{ route('stocks.items.update', ['item' => 0]) }}",
      deleteItem: "{{ route('stocks.items.delete', ['item' => 0]) }}",
      updateParty: "{{ route('stocks.parties.update', ['party' => 0]) }}",
      deleteParty: "{{ route('stocks.parties.delete', ['party' => 0]) }}",
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

    const csrfToken = document
      .querySelector('meta[name="csrf-token"]')
      ?.getAttribute("content");

    async function apiGet(url, params = {}) {
      const qs = new URLSearchParams(
        Object.entries(params).filter(([, v]) => v !== null && v !== "")
      ).toString();
      const res = await fetch(qs ? `${url}?${qs}` : url, {
        headers: { Accept: "application/json" },
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw { status: res.status, data };
      return data;
    }

    async function apiPost(url, payload = {}) {
      const res = await fetch(url, {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrfToken || "",
        },
        body: JSON.stringify(payload),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw { status: res.status, data };
      return data;
    }

    async function apiSend(url, method, payload = null) {
      const opts = {
        method,
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrfToken || "",
        },
      };
      if (payload) {
        opts.body = JSON.stringify(payload);
      }
      const res = await fetch(url, opts);
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw { status: res.status, data };
      return data;
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
    const tabBtns = Array.from(document.querySelectorAll(".pv-tabbtn"));
    const views = {
      entry: document.getElementById("view-entry"),
      billing: document.getElementById("view-billing"),
      stock: document.getElementById("view-stock"),
      items: document.getElementById("view-items"),
      parties: document.getElementById("view-parties"),
    };
    function setView(name) {
      tabBtns.forEach((b) =>
        b.classList.toggle("active", b.dataset.view === name)
      );
      Object.entries(views).forEach(([k, el]) =>
        el.classList.toggle("d-none", k !== name)
      );
    }

    /* ===========================
  SELECTS POPULATION
  =========================== */
    function fillPartySelect(sel, withAll = false) {
      sel.innerHTML = "";
      if (withAll)
        sel.insertAdjacentHTML("beforeend", `<option value="">All</option>`);
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
      document.querySelectorAll(".line-item").forEach((sel) => {
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
    const entryTitle = document.getElementById("entryTitle");
    const entryPartyLabel = document.getElementById("entryPartyLabel");
    const entryReasonLabel = document.getElementById("entryReasonLabel");
    const btnPurchaseMode = document.getElementById("btnPurchaseMode");
    const btnStockOutMode = document.getElementById("btnStockOutMode");

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
      btnPurchaseMode.classList.toggle("btn-outline-primary", !isPurchase);
      btnPurchaseMode.classList.toggle("btn-primary", isPurchase);
      btnStockOutMode.classList.toggle("btn-outline-secondary", isPurchase);
      btnStockOutMode.classList.toggle("btn-secondary", !isPurchase);
    }
    btnPurchaseMode.addEventListener("click", () => setEntryMode("purchase"));
    btnStockOutMode.addEventListener("click", () => setEntryMode("stockout"));

    /* ===========================
  ENTRY LINES
  =========================== */
    const entryLinesWrap = document.getElementById("entryLines");
    const entryTotalEl = document.getElementById("entryTotal");

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

      const temp = document.createElement("div");
      temp.innerHTML = html.trim();
      const row = temp.firstElementChild;

      const itemSel = row.querySelector(".line-item");
      fillItemSelect(itemSel, false);

      // default first item
      if (state.items.length) itemSel.value = state.items[0].id;

      const unitEl = row.querySelector(".line-unit");
      const qtyEl = row.querySelector(".line-qty");
      const rateEl = row.querySelector(".line-rate");
      const amtEl = row.querySelector(".line-amount");

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

      itemSel.addEventListener("change", syncFromItem);
      qtyEl.addEventListener("input", () => {
        qtyEl.value = qtyEl.value.replace(/[^0-9]/g, "");
        recalc();
      });
      rateEl.addEventListener("input", () => {
        pvFormatAmountNoDecimal(rateEl);
        recalc();
      });
      row.querySelector("button").addEventListener("click", () => {
        row.remove();
        recalcEntryTotal();
      });

      // initial sync
      syncFromItem();

      return row;
    }

    function recalcEntryTotal() {
      let total = 0;
      entryLinesWrap.querySelectorAll(".pv-line").forEach((row) => {
        const qty = pvParseIntAmount(row.querySelector(".line-qty").value);
        const rate = pvParseIntAmount(row.querySelector(".line-rate").value);
        total += qty * rate;
      });
      entryTotalEl.textContent = fmtPKR(total);
    }

    document.getElementById("btnAddLine").addEventListener("click", () => {
      entryLinesWrap.appendChild(
        makeLineRow("entry", entryLinesWrap.children.length + 1)
      );
    });

    /* ===========================
  ENTRY SUBMIT
  =========================== */
    const entryForm = document.getElementById("entryForm");
    const entrySlipNo = document.getElementById("entrySlipNo");
    const entryDate = document.getElementById("entryDate");
    const entryParty = document.getElementById("entryParty");
    const entryReason = document.getElementById("entryReason");
    const entryVehicle = document.getElementById("entryVehicle");
    const entryDriver = document.getElementById("entryDriver");


    function collectLines(wrap) {
      const lines = [];
      wrap.querySelectorAll(".pv-line").forEach((row) => {
        const itemId = Number(row.querySelector(".line-item").value);
        const qtyRaw = String(row.querySelector(".line-qty").value || "").replace(
          /[^0-9]/g,
          ""
        );
        const rate = pvParseIntAmount(row.querySelector(".line-rate").value);
        if (!itemId || !qtyRaw || Number(qtyRaw) <= 0) return;
        lines.push({ item_id: itemId, qty: qtyRaw, rate });
      });
      return lines;
    }

    function entryFlow(mode) {
      return mode === "purchase" ? "IN" : "OUT";
    }

    entryForm.addEventListener("submit", async (e) => {
      e.preventDefault();

      if (!entryParty.value) {
        Swal.fire({
          icon: "error",
          title: "Missing Party",
          text: "Please select Shop/Party or Site/Customer.",
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

      document.getElementById("entrySubmitBtn").disabled = true;
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
        document.getElementById("entrySubmitBtn").disabled = false;
      }
    });


    /* ===========================
  BILLING LINES + SUBMIT
  =========================== */
    const billForm = document.getElementById("billForm");
    const billType = document.getElementById("billType");
    const billDate = document.getElementById("billDate");
    const billParty = document.getElementById("billParty");
    const billNo = document.getElementById("billNo");
    const billNoChip = document.getElementById("billNoChip");
    const billEntryNo = document.getElementById("billEntryNo");
    const billLinesWrap = document.getElementById("billLines");
    const billTotalEl = document.getElementById("billTotal");

    function makeBillLineRow(idx) {
      const row = makeLineRow("bill", idx);
      // tweak labels a bit
      row
        .querySelector(".line-amount")
        .closest(".d-flex")
        .querySelector(".pv-muted").textContent = "Line Amount";
      return row;
    }

    function recalcBillTotal() {
      let total = 0;
      billLinesWrap.querySelectorAll(".pv-line").forEach((row) => {
        const qty = pvParseIntAmount(row.querySelector(".line-qty").value);
        const rate = pvParseIntAmount(row.querySelector(".line-rate").value);
        total += qty * rate;
      });
      billTotalEl.textContent = fmtPKR(total);
    }

    billLinesWrap.addEventListener("input", (e) => {
      if (
        e.target.classList.contains("line-qty") ||
        e.target.classList.contains("line-rate")
      ) {
        recalcBillTotal();
      }
    });

    document
      .getElementById("btnAddBillLine")
      .addEventListener("click", () => {
        billLinesWrap.appendChild(
          makeBillLineRow(billLinesWrap.children.length + 1)
        );
        recalcBillTotal();
      });

    billForm.addEventListener("submit", async (e) => {
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

      billForm.querySelector('button[type="submit"]').disabled = true;
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
        billForm.querySelector('button[type="submit"]').disabled = false;
      }
    });


    /* ===========================
  ITEMS + PARTIES CRUD (demo)
  =========================== */
    const editModalEl = document.getElementById("editModal");
    const editModal = new bootstrap.Modal(editModalEl);

    function openEditModal(title, fields, onSave) {
      document.getElementById("editModalTitle").textContent = title;

      const form = document.getElementById("editForm");
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
          form.querySelector(`[name="${f.name}"]`).value = f.value ?? "";
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

      form.querySelectorAll(".pv-money").forEach((el) => {
        pvFormatAmountNoDecimal(el);
        el.addEventListener("input", () => pvFormatAmountNoDecimal(el));
      });

      if (onSave) {
        form.querySelector("#editSaveBtn").addEventListener("click", () => {
          const data = Object.fromEntries(new FormData(form).entries());
          onSave(data);
        });
      }

      editModal.show();
    }

    /* Items */
    document.getElementById("itemForm").addEventListener("submit", async (e) => {
      e.preventDefault();
      const name = document.getElementById("itemName").value.trim();
      const unit = document.getElementById("itemUnit").value.trim();
      const rate = pvParseIntAmount(
        document.getElementById("itemRate").value
      );

      if (!name || !unit) return;

      try {
        await apiPost(endpoints.storeItem, {
          name,
          unit,
          default_rate: rate,
        });
        viewToast("success", "Added", "Item created.");

        document.getElementById("itemName").value = "";
        document.getElementById("itemUnit").value = "";
        document.getElementById("itemRate").value = "";

        await loadMeta();
        await loadItemsTable();
      } catch (err) {
        handleApiError(err, "Unable to create item.");
      }
    });


    /* Parties */
    document.getElementById("partyForm").addEventListener("submit", async (e) => {
      e.preventDefault();
      const type = document.getElementById("partyType").value;
      const name = document.getElementById("partyName").value.trim();
      if (!name) return;

      try {
        await apiPost(endpoints.storeParty, {
          type,
          name,
          mobile: document.getElementById("partyMobile").value.trim(),
          address: document.getElementById("partyAddress").value.trim(),
          head: document.getElementById("accounts_id").value.trim(),
          subhead: document.getElementById("partySubHead").value.trim(),
        });

        viewToast("success", "Added", "Party created.");

        document.getElementById("partyName").value = "";
        document.getElementById("partyMobile").value = "";
        document.getElementById("partyAddress").value = "";
        document.getElementById("accounts_id").value = "";
        document.getElementById("subaccounts_id").value = "";

        await loadMeta();
        await loadPartiesTable();
      } catch (err) {
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
      fillItemSelect(document.getElementById("entryFilterItem"), true);
      fillItemSelect(document.getElementById("stockItemFilter"), true);
      fillPartySelect(document.getElementById("billFilterParty"), true);
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
        item_id: document.getElementById("entryFilterItem").value || null,
        flow: document.getElementById("entryFilterFlow").value || null,
        q: document.getElementById("entrySearch").value || null,
        per_page: 50,
        page: 1,
      });
      state.entries = res?.data || { rows: [], total: 0, kpi: {} };
      renderEntryTable();
    }

    async function loadBills() {
      const res = await apiGet(endpoints.bills, {
        type: document.getElementById("billFilterType").value || null,
        party_id: document.getElementById("billFilterParty").value || null,
        q: document.getElementById("billSearch").value || null,
        per_page: 50,
        page: 1,
      });
      state.bills = res?.data || { rows: [], total: 0, kpi: {} };
      renderBillTable();
    }

    async function loadReport() {
      const res = await apiGet(endpoints.report, {
        from: document.getElementById("stockFrom").value || null,
        to: document.getElementById("stockTo").value || null,
        item_id: document.getElementById("stockItemFilter").value || null,
      });
      state.report = res?.data || { rows: [], kpi: {} };
      renderStock();
    }

    async function loadItemsTable() {
      const res = await apiGet(endpoints.items, {
        q: document.getElementById("itemSearch").value || null,
      });
      state.itemsTable = res?.data || { rows: [], total: 0 };
      renderItems();
    }

    async function loadPartiesTable() {
      const res = await apiGet(endpoints.parties, {
        q: document.getElementById("partySearch").value || null,
        type: document.getElementById("partyFilterType").value || null,
      });
      state.partiesTable = res?.data || { rows: [], total: 0 };
      renderParties();
    }

    /* ===========================
  RENDERING
  =========================== */
    function renderEntryTable() {
      const tbody = document.getElementById("entryTbody");
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
              <td class="text-end pv-actions">
                <button class="btn btn-outline-secondary btn-sm" data-act="print">
                  <i class="bi bi-printer"></i>
                </button>
              </td>
            </tr>
          `
        );
      });

      document.getElementById("entryCountChip").textContent = `${state.entries.total || 0
        } total`;

      const kpi = state.entries.kpi || {};
      document.getElementById("kpiInQty").textContent = fmtInt(
        kpi.in_qty || 0
      );
      document.getElementById("kpiOutQty").textContent = fmtInt(
        kpi.out_qty || 0
      );
      document.getElementById("kpiLines").textContent = fmtInt(
        kpi.line_count || 0
      );

      tbody.querySelectorAll("button[data-act]").forEach((btn) => {
        btn.addEventListener("click", () => {
          viewToast("info", "Print", "Print action not available.");
        });
      });
    }

    function renderBillTable() {
      const tbody = document.getElementById("billTbody");
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
              <td class="text-end pv-actions">
                <button class="btn btn-outline-secondary btn-sm" data-act="print">
                  <i class="bi bi-printer"></i>
                </button>
              </td>
            </tr>
          `
        );
      });

      document.getElementById("billCountChip").textContent = `${state.bills.total || 0
        } total`;

      const kpi = state.bills.kpi || {};
      document.getElementById("kpiPurchaseBills").textContent = fmtInt(
        kpi.purchase_count || 0
      );
      document.getElementById("kpiSaleBills").textContent = fmtInt(
        kpi.sale_count || 0
      );
      document.getElementById("kpiBillTotal").textContent = fmtPKR(
        kpi.total_amount || 0
      );

      tbody.querySelectorAll("button[data-act]").forEach((btn) => {
        btn.addEventListener("click", () => {
          viewToast("info", "Print", "Print action not available.");
        });
      });
    }

    function renderStock() {
      const from = document.getElementById("stockFrom").value;
      const to = document.getElementById("stockTo").value;
      const rows = state.report.rows || [];
      const tbody = document.getElementById("stockTbody");
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
      document.getElementById("kpiStockItems").textContent = fmtInt(
        kpi.items || 0
      );
      document.getElementById("kpiStockIn").textContent = fmtInt(
        kpi.in_qty || 0
      );
      document.getElementById("kpiStockOut").textContent = fmtInt(
        kpi.out_qty || 0
      );
      document.getElementById("kpiStockAvail").textContent = fmtInt(
        kpi.available || 0
      );

      const chip = document.getElementById("stockRangeChip");
      chip.textContent = `Range: ${from || "—"} → ${to || "—"}`;
    }

    function renderItems() {
      const tbody = document.getElementById("itemTbody");
      tbody.innerHTML = "";
      (state.itemsTable.rows || []).forEach((it, idx) => {
        tbody.insertAdjacentHTML(
          "beforeend",
          `
            <tr>
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

      document.getElementById(
        "itemCountChip"
      ).textContent = `${state.itemsTable.total || 0} total`;

      tbody.querySelectorAll("button[data-act]").forEach((btn) => {
        btn.addEventListener("click", async () => {
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
              title: "Delete item?",
              text: "This will remove related entries/bills that reference this item.",
              showCancelButton: true,
              confirmButtonText: "Delete",
            });
            if (!ok.isConfirmed) return;
            try {
              await apiSend(withId(endpoints.deleteItem, it.id), "DELETE");
              viewToast("success", "Deleted", "Item deleted.");
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
      const tbody = document.getElementById("partyTbody");
      tbody.innerHTML = "";
      (state.partiesTable.rows || []).forEach((p, idx) => {
        tbody.insertAdjacentHTML(
          "beforeend",
          `
            <tr>
              <td>${idx + 1}</td>
              <td>${p.name}</td>
              <td>${p.mobile || "—"}</td>
              <td class="text-truncate" style="max-width:160px;">${p.address || "—"
          }</td>
              <td>${p.head || "—"}</td>
              <td>${p.subhead || "—"}</td>
              <td><span class="pv-pill">${p.type}</span></td>
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

      document.getElementById(
        "partyCountChip"
      ).textContent = `${state.partiesTable.total || 0} total`;

      tbody.querySelectorAll("button[data-act]").forEach((btn) => {
        btn.addEventListener("click", async () => {
          const row = btn.closest("tr");
          const idx = Array.from(row.parentNode.children).indexOf(row);
          const p = state.partiesTable.rows[idx];
          if (!p) return;

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
                { label: "Head", name: "head", value: p.head || "" },
                { label: "Sub Head", name: "subhead", value: p.subhead || "" },
              ],
              async (data) => {
                try {
                  await apiSend(withId(endpoints.updateParty, p.id), "PUT", {
                    type: data.type || "",
                    name: data.name || "",
                    mobile: data.mobile || "",
                    address: data.address || "",
                    head: data.head || "",
                    subhead: data.subhead || "",
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
              title: "Delete party?",
              text: "This will remove related entries/bills that reference this party.",
              showCancelButton: true,
              confirmButtonText: "Delete",
            });
            if (!ok.isConfirmed) return;
            try {
              await apiSend(withId(endpoints.deleteParty, p.id), "DELETE");
              viewToast("success", "Deleted", "Party deleted.");
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
      document.getElementById("stockFrom").value = daysAgoISO(30);
      document.getElementById("stockTo").value = todayISO();

      entryLinesWrap.innerHTML = "";
      entryLinesWrap.appendChild(makeLineRow("entry", 1));
      recalcEntryTotal();

      billLinesWrap.innerHTML = "";
      billLinesWrap.appendChild(makeBillLineRow(1));
      recalcBillTotal();

      document
        .getElementById("entryFilterItem")
        .addEventListener("change", () =>
          loadEntries().catch((err) => handleApiError(err, "Unable to load entries."))
        );
      document
        .getElementById("entryFilterFlow")
        .addEventListener("change", () =>
          loadEntries().catch((err) => handleApiError(err, "Unable to load entries."))
        );
      document
        .getElementById("entrySearch")
        .addEventListener(
          "input",
          debounce(
            () =>
              loadEntries().catch((err) =>
                handleApiError(err, "Unable to load entries.")
              ),
            300
          )
        );

      document
        .getElementById("billFilterType")
        .addEventListener("change", () =>
          loadBills().catch((err) => handleApiError(err, "Unable to load bills."))
        );
      document
        .getElementById("billFilterParty")
        .addEventListener("change", () =>
          loadBills().catch((err) => handleApiError(err, "Unable to load bills."))
        );
      document
        .getElementById("billSearch")
        .addEventListener(
          "input",
          debounce(
            () =>
              loadBills().catch((err) => handleApiError(err, "Unable to load bills.")),
            300
          )
        );

      document
        .getElementById("itemSearch")
        .addEventListener(
          "input",
          debounce(
            () =>
              loadItemsTable().catch((err) =>
                handleApiError(err, "Unable to load items.")
              ),
            300
          )
        );
      document
        .getElementById("partySearch")
        .addEventListener(
          "input",
          debounce(
            () =>
              loadPartiesTable().catch((err) =>
                handleApiError(err, "Unable to load parties.")
              ),
            300
          )
        );
      document
        .getElementById("partyFilterType")
        .addEventListener("change", () =>
          loadPartiesTable().catch((err) => handleApiError(err, "Unable to load parties."))
        );

      document
        .getElementById("btnApplyStock")
        .addEventListener("click", () =>
          loadReport().catch((err) => handleApiError(err, "Unable to load report."))
        );

      billType.addEventListener("change", () =>
        loadNextBill().catch((err) => handleApiError(err, "Unable to load bill no."))
      );

      document.querySelectorAll(".pv-money").forEach((el) => {
        pvFormatAmountNoDecimal(el);
        el.addEventListener("input", () => pvFormatAmountNoDecimal(el));
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
          loadPartiesTable(),
        ]);
      } catch (err) {
        handleApiError(err, "Failed to load data.");
      }
    }

    document.addEventListener("DOMContentLoaded", () => {
      initUI();

      // tab switching
      tabBtns.forEach((btn) =>
        btn.addEventListener("click", () => setView(btn.dataset.view))
      );
    });
  </script>
@endsection