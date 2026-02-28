@extends('admin.layouts.master')
@section('content')
    <div class="content-wrapper pt-4">
        <section class="content">
            <div class="container-fluid">
                <div class="custom_card h-100">
                    <div class="card-body">
                        <div class="mb-3 d-flex align-items-center justify-content-between">
                            <h3>{{ $title }}</h3>
                            <button class="btn btn-sm custom_btn primary" data-toggle="modal" data-target="#addStockModal">
                                <i class="fas fa-plus mr-1"></i> Add Stock
                            </button>
                        </div>
                        @if ($stocks->isNotEmpty())
                            <table class="table table-hover" id="stockTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Quantity</th>
                                        <th>Total Used</th>
                                        <th>Total Remaining</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($stocks as $stock)
                                        <tr>
                                            <td>{{ $stock->id }}</td>
                                            <td>{{ $stock->stock_name ?? 'N/A' }}</td>
                                            <td>
                                                <span
                                                    class="badge {{ $stock->type === 'purchase' ? 'badge-success' : 'badge-danger' }}"
                                                    style="min-width: 60px;">
                                                    {{ ucfirst($stock->type) }}
                                                </span>
                                            </td>
                                            <td>{{ $stock->quantity }}</td>
                                            <td>{{ $stock->total_used }}</td>
                                            <td>{{ $stock->total_remaining }}</td>
                                            <td>{{ $stock->created_at->format('d M, Y') }}</td>
                                            <td>
                                                <button class="btn btn-info btn-sm editBtn" data-id="{{ $stock->id }}"
                                                    data-project="{{ $stock->project_id }}" data-type="{{ $stock->type }}"
                                                    data-quantity="{{ $stock->quantity }}" data-used="{{ $stock->total_used }}"
                                                    data-remaining="{{ $stock->total_remaining }}" data-toggle="modal"
                                                    data-target="#editStockModal">
                                                    <i class="fas fa-edit"></i>
                                                </button>

                                                <form id="delete-form-{{ $stock->id }}"
                                                    action="{{ route('stocks.destroy', $stock) }}" method="POST"
                                                    style="display:inline-block">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button" class="btn btn-danger btn-sm"
                                                        onclick="confirmDelete({{ $stock->id }})">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="notfound bg-white p-3">
                                <div class="d-flex flex-wrap justify-content-center align-items-center">
                                    <div class="image-notfound mr-3">
                                        <img src="{{ asset('dist/images/not-found.png') }}" class="img-fluid">
                                    </div>
                                    <div class="text-notfound text-center">
                                        <h4 class="mb-0 f-20 text-dark">{{ __('Sorry! No data found.') }}</h4>
                                        <p class="mb-0 f-16 text-gray-100 mt-2">
                                            {{ __('The requested data does not exist for this feature overview.') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addStockModal" tabindex="-1" aria-labelledby="addStockModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg"> {{-- Larger modal --}}
            <form action="{{ route('stocks.store') }}" method="POST" class="modal-content shadow-lg border-0 rounded-3">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addStockModalLabel">
                        <i class="fas fa-plus-circle mr-2"></i> Add Stock
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-dismiss="modal" aria-label="Close"> <span
                            aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    @include('admin.stocks.form', ['idPrefix' => 'add'])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Close
                    </button>
                    <button type="submit" class="btn btn-success px-4">
                        <i class="fas fa-save mr-1"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editStockModal" tabindex="-1" aria-labelledby="editStockModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg"> {{-- Larger modal --}}
            <form method="POST" id="editStockForm" class="modal-content shadow-lg border-0 rounded-3">
                @csrf @method('PUT')
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="editStockModalLabel">
                        <i class="fas fa-edit mr-2"></i> Edit Stock
                    </h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"> <span
                            aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    @include('admin.stocks.form', ['idPrefix' => 'edit'])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Close
                    </button>
                    <button type="submit" class="btn btn-success px-4">
                        <i class="fas fa-check mr-1"></i> Update
                    </button>
                </div>
            </form>
        </div>
    </div>

@endsection

@section('js')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function () {
            $("#stockTable").DataTable({
                "responsive": true,
                "lengthChange": true,
                "autoWidth": false,
                "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
            })

            function calculateRemaining(prefix) {
                let quantity = parseInt($('#' + prefix + '_quantity').val()) || 0;
                let used = parseInt($('#' + prefix + '_total_used').val()) || 0;
                $('#' + prefix + '_total_remaining').val(quantity - used);
            }

            $('#add_quantity, #add_total_used').on('input', function () {
                calculateRemaining('add');
            });

            $('#edit_quantity, #edit_total_used').on('input', function () {
                calculateRemaining('edit');
            });

            $(document).on('click', '.editBtn', function () {
                let id = $(this).data('id');
                let updateUrl = "{{ route('stocks.update', ':id') }}".replace(':id', id);
                $('#editStockForm').attr('action', updateUrl);

                $('#edit_quantity').val($(this).data('quantity'));
                $('#edit_total_used').val($(this).data('used'));
                $('#edit_total_remaining').val($(this).data('remaining'));
                $('#edit_type').val($(this).data('type'));

                calculateRemaining('edit'); // recalc when opening
            });

        });
    </script>
    @if ($errors->any())
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                Swal.fire({
                    toast: true,
                    position: 'top-end', // top right
                    icon: 'error',
                    title: `{!! implode('<br>', $errors->all()) !!}`,
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            });
        </script>
    @endif

    @if (session('success'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: '{{ session('success') }}',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            });
        </script>
    @endif
    <script>
        function confirmDelete(stockId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "This stock will be permanently deleted!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete-form-' + stockId).submit();
                }
            });
        }
    </script>
    <script>
        /* ==========================================================
          Paysavo Stock Module — AJAX Wiring (No in-memory demo)
          - Uses your existing routes
          - Gracefully handles missing list/report endpoints
        ========================================================== */

        const PV = {
            routes: {
                index: @json(route('stocks.index')),
                meta: @json(route('stocks.meta')),
                nextSlip: @json(route('stocks.nextSlip')),
                nextBill: @json(route('stocks.nextBill')),

                storeItem: @json(route('stocks.items.store')),
                storeParty: @json(route('stocks.parties.store')),
                storeEntry: @json(route('stocks.entries.store')),
                storeBill: @json(route('stocks.bills.store')),

                listEntries: @json(route('stocks.entries.index')),
                listBills: @json(route('stocks.bills.index')),
                listItems: @json(route('stocks.items.index')),
                listParties: @json(route('stocks.parties.index')),
                report: @json(route('stocks.report')),
            },
            state: {
                items: [],
                parties: [],
                view: 'entry',
                entryMode: 'purchase', // purchase | stockout
            }
        };

        function csrfToken() {
            const el = document.querySelector('meta[name="csrf-token"]');
            return el ? el.getAttribute('content') : '';
        }

        async function apiFetch(url, { method = 'GET', body = null } = {}) {
            const headers = {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            };
            if (method !== 'GET') {
                headers['Content-Type'] = 'application/json';
                headers['X-CSRF-TOKEN'] = csrfToken();
            }

            const res = await fetch(url, {
                method,
                headers,
                body: body ? JSON.stringify(body) : null,
                credentials: 'same-origin',
            });

            // Try JSON, else fallback
            const ct = res.headers.get('content-type') || '';
            const isJson = ct.includes('application/json');
            const payload = isJson ? await res.json().catch(() => null) : await res.text().catch(() => null);

            if (!res.ok) {
                const msg =
                    (payload && payload.message) ||
                    (payload && payload.error) ||
                    (typeof payload === 'string' ? payload : null) ||
                    'Request failed.';
                const err = new Error(msg);
                err.status = res.status;
                err.payload = payload;
                throw err;
            }

            return payload;
        }

        /* ===========================
          UTIL
        =========================== */
        const fmtInt = (n) =>
            new Intl.NumberFormat("en-US", { maximumFractionDigits: 0 }).format(Number(n || 0));
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
            v = String(v ?? "").split(".")[0].replace(/[^0-9]/g, "");
            return v ? parseInt(v, 10) || 0 : 0;
        }
        function viewToast(icon, title, text) {
            return Swal.fire({
                icon,
                title,
                text,
                timer: 1600,
                showConfirmButton: false,
                toast: true,
                position: "top-end",
            });
        }
        function todayISO() {
            const d = new Date();
            const pad = (x) => String(x).padStart(2, "0");
            return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
        }
        function daysAgoISO(days) {
            const d = new Date();
            d.setDate(d.getDate() - days);
            const pad = (x) => String(x).padStart(2, "0");
            return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
        }

        function findItem(id) {
            return PV.state.items.find((x) => x.id === Number(id));
        }
        function findParty(id) {
            return PV.state.parties.find((x) => x.id === Number(id));
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
            PV.state.view = name;
            tabBtns.forEach((b) => b.classList.toggle("active", b.dataset.view === name));
            Object.entries(views).forEach(([k, el]) => el.classList.toggle("d-none", k !== name));

            // refresh view data (tables/report)
            renderAll().catch(() => { });
        }

        /* ===========================
          SELECT POPULATION
        =========================== */
        function fillPartySelect(sel, withAll = false) {
            sel.innerHTML = "";
            if (withAll) sel.insertAdjacentHTML("beforeend", `<option value="">All</option>`);
            PV.state.parties.forEach((p) => {
                sel.insertAdjacentHTML("beforeend", `<option value="${p.id}">${p.name} • ${p.type}</option>`);
            });
        }
        function fillItemSelect(sel, withAll = false) {
            sel.innerHTML = "";
            if (withAll) sel.insertAdjacentHTML("beforeend", `<option value="">All</option>`);
            PV.state.items.forEach((i) => {
                sel.insertAdjacentHTML("beforeend", `<option value="${i.id}">${i.name} (${i.unit})</option>`);
            });
        }

        /* ===========================
          ENTRY MODE
        =========================== */
        let entryMode = "purchase"; // purchase | stockout
        const entryTitle = document.getElementById("entryTitle");
        const entryPartyLabel = document.getElementById("entryPartyLabel");
        const entryReasonLabel = document.getElementById("entryReasonLabel");
        const btnPurchaseMode = document.getElementById("btnPurchaseMode");
        const btnStockOutMode = document.getElementById("btnStockOutMode");

        function setEntryMode(mode) {
            entryMode = mode;
            PV.state.entryMode = mode;

            const isPurchase = mode === "purchase";
            entryTitle.textContent = isPurchase ? "Purchase Entry" : "Stock Out Entry";
            entryPartyLabel.textContent = isPurchase ? "Shop / Party" : "Site & Customer";
            entryReasonLabel.textContent = isPurchase ? "Purchase Reason Details" : "Using Reason Details";

            btnPurchaseMode.classList.toggle("btn-outline-primary", !isPurchase);
            btnPurchaseMode.classList.toggle("btn-primary", isPurchase);
            btnStockOutMode.classList.toggle("btn-outline-secondary", isPurchase);
            btnStockOutMode.classList.toggle("btn-secondary", !isPurchase);
        }
        btnPurchaseMode.addEventListener("click", () => setEntryMode("purchase"));
        btnStockOutMode.addEventListener("click", () => setEntryMode("stockout"));

        /* ===========================
          LINE ROW BUILDER (shared)
        =========================== */
        const entryLinesWrap = document.getElementById("entryLines");
        const entryTotalEl = document.getElementById("entryTotal");
        const billLinesWrap = document.getElementById("billLines");
        const billTotalEl = document.getElementById("billTotal");

        function makeLineRow(prefix) {
            const html = `
        <div class="pv-line">
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
      `.trim();

            const temp = document.createElement("div");
            temp.innerHTML = html;
            const row = temp.firstElementChild;

            const itemSel = row.querySelector(".line-item");
            fillItemSelect(itemSel, false);
            if (PV.state.items.length) itemSel.value = PV.state.items[0].id;

            const unitEl = row.querySelector(".line-unit");
            const qtyEl = row.querySelector(".line-qty");
            const rateEl = row.querySelector(".line-rate");
            const amtEl = row.querySelector(".line-amount");

            function syncFromItem() {
                const it = findItem(itemSel.value);
                unitEl.value = it ? it.unit : "—";
                // default rate if empty
                if (it && (!rateEl.value || pvParseIntAmount(rateEl.value) === 0)) {
                    rateEl.value = fmtInt(it.default_rate ?? it.rate ?? 0);
                    pvFormatAmountNoDecimal(rateEl);
                }
                recalc();
            }

            function recalc() {
                const qty = pvParseIntAmount(qtyEl.value);
                const rate = pvParseIntAmount(rateEl.value);
                const amt = qty * rate;
                amtEl.textContent = fmtPKR(amt);

                if (prefix === 'entry') recalcEntryTotal();
                if (prefix === 'bill') recalcBillTotal();
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
                if (prefix === 'entry') recalcEntryTotal();
                if (prefix === 'bill') recalcBillTotal();
            });

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
        function recalcBillTotal() {
            let total = 0;
            billLinesWrap.querySelectorAll(".pv-line").forEach((row) => {
                const qty = pvParseIntAmount(row.querySelector(".line-qty").value);
                const rate = pvParseIntAmount(row.querySelector(".line-rate").value);
                total += qty * rate;
            });
            billTotalEl.textContent = fmtPKR(total);
        }

        document.getElementById("btnAddLine").addEventListener("click", () => {
            entryLinesWrap.appendChild(makeLineRow('entry'));
        });

        document.getElementById("btnAddBillLine").addEventListener("click", () => {
            billLinesWrap.appendChild(makeLineRow('bill'));
        });

        /* ===========================
          META + COUNTERS
        =========================== */
        async function loadMeta() {
            const res = await apiFetch(PV.routes.meta);
            const data = res.data || res;

            PV.state.items = (data.items || []).map(i => ({
                id: i.id,
                name: i.name,
                unit: i.unit,
                default_rate: i.default_rate ?? 0,
            }));

            PV.state.parties = (data.parties || []).map(p => ({
                id: p.id,
                type: p.type,
                name: p.name,
                mobile: p.mobile,
                address: p.address,
                head: p.head,
                subhead: p.subhead,
            }));

            // refresh dropdowns
            fillPartySelect(document.getElementById("entryParty"), false);
            fillPartySelect(document.getElementById("billParty"), false);

            fillItemSelect(document.getElementById("entryFilterItem"), true);
            fillItemSelect(document.getElementById("stockItemFilter"), true);
            fillPartySelect(document.getElementById("billFilterParty"), true);

            // refresh existing line item dropdowns too
            entryLinesWrap.querySelectorAll('.line-item').forEach(sel => fillItemSelect(sel, false));
            billLinesWrap.querySelectorAll('.line-item').forEach(sel => fillItemSelect(sel, false));
        }

        async function refreshSlipNo() {
            const res = await apiFetch(PV.routes.nextSlip);
            document.getElementById("entrySlipNo").value = res.slip_no ?? res.data?.slip_no ?? '';
        }

        async function refreshBillNo() {
            const type = document.getElementById('billType').value || 'purchase';
            const url = PV.routes.nextBill + '?type=' + encodeURIComponent(type);
            const res = await apiFetch(url);
            const billNo = res.bill_no ?? res.data?.bill_no ?? '';
            document.getElementById("billNo").value = billNo;
            document.getElementById("billNoChip").textContent = billNo || 'Bill no… auto';
        }

        /* ===========================
          COLLECT LINES
        =========================== */
        function collectLines(wrap) {
            const lines = [];
            wrap.querySelectorAll(".pv-line").forEach((row) => {
                const itemId = Number(row.querySelector(".line-item").value);
                const qty = pvParseIntAmount(row.querySelector(".line-qty").value);
                const rate = pvParseIntAmount(row.querySelector(".line-rate").value);
                if (!itemId || qty <= 0) return;
                lines.push({ item_id: itemId, qty, rate });
            });
            return lines;
        }

        /* ===========================
          SAVE ENTRY
          (NOTE: uses controller keys date/flow/vehicle/driver/reason)
        =========================== */
        document.getElementById("entryForm").addEventListener("submit", async (e) => {
            e.preventDefault();

            const partyId = document.getElementById("entryParty").value;
            if (!partyId) {
                Swal.fire({ icon: "error", title: "Missing Party", text: "Please select Shop/Party or Site/Customer." });
                return;
            }

            const lines = collectLines(entryLinesWrap);
            if (!lines.length) {
                Swal.fire({ icon: "error", title: "No items", text: "Add at least one item with Qty > 0." });
                return;
            }

            // flow derived from entryMode
            const flow = (entryMode === 'purchase') ? 'IN' : 'OUT';

            try {
                await apiFetch(PV.routes.storeEntry, {
                    method: 'POST',
                    body: {
                        date: document.getElementById("entryDate").value,
                        flow,
                        party_id: Number(partyId),
                        reason: document.getElementById("entryReason").value || null,
                        vehicle: document.getElementById("entryVehicle").value || null,
                        driver: document.getElementById("entryDriver").value || null,
                        lines
                    }
                });

                viewToast("success", "Saved", `${flow} entry saved.`);
                // reset
                document.getElementById("entryReason").value = "";
                document.getElementById("entryVehicle").value = "";
                document.getElementById("entryDriver").value = "";
                entryLinesWrap.innerHTML = "";
                entryLinesWrap.appendChild(makeLineRow('entry'));
                recalcEntryTotal();
                await refreshSlipNo();
                await renderAll();
            } catch (err) {
                const key = err?.payload?.error_key;
                if (key === 'STOCK_NEGATIVE_BLOCKED') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Stock Not Available',
                        text: err?.payload?.message || err.message,
                    });
                    return;
                }
                Swal.fire({ icon: "error", title: "Failed", text: err.message || "Could not save entry." });
            }
        });

        /* ===========================
          SAVE BILL
        =========================== */
        document.getElementById("billForm").addEventListener("submit", async (e) => {
            e.preventDefault();

            const type = document.getElementById("billType").value || 'purchase';
            const partyId = document.getElementById("billParty").value;

            if (!partyId) {
                Swal.fire({ icon: "error", title: "Missing Party", text: "Please select Supplier / Site Name." });
                return;
            }

            const lines = collectLines(billLinesWrap);
            if (!lines.length) {
                Swal.fire({ icon: "error", title: "No items", text: "Add at least one bill item with Qty > 0." });
                return;
            }

            try {
                await apiFetch(PV.routes.storeBill, {
                    method: 'POST',
                    body: {
                        type,
                        date: document.getElementById("billDate").value,
                        party_id: Number(partyId),
                        entry_no: document.getElementById("billEntryNo").value || null,
                        lines
                    }
                });

                viewToast("success", "Saved", `${type.toUpperCase()} bill saved.`);
                // reset
                document.getElementById("billEntryNo").value = "";
                billLinesWrap.innerHTML = "";
                billLinesWrap.appendChild(makeLineRow('bill'));
                recalcBillTotal();
                await refreshBillNo();
                await renderAll();
            } catch (err) {
                const key = err?.payload?.error_key;
                if (key === 'STOCK_NEGATIVE_BLOCKED') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Stock Not Available',
                        text: err?.payload?.message || err.message,
                    });
                    return;
                }
                Swal.fire({ icon: "error", title: "Failed", text: err.message || "Could not save bill." });
            }
        });

        /* ===========================
          SAVE ITEM
          (your form uses ids: itemName/itemUnit/itemRate)
        =========================== */
        document.getElementById("itemForm").addEventListener("submit", async (e) => {
            e.preventDefault();

            const name = document.getElementById("itemName").value.trim();
            const unit = document.getElementById("itemUnit").value.trim();
            const default_rate = pvParseIntAmount(document.getElementById("itemRate").value);

            if (!name || !unit) return;

            try {
                await apiFetch(PV.routes.storeItem, {
                    method: 'POST',
                    body: { name, unit, default_rate }
                });

                viewToast("success", "Added", "Item added.");
                document.getElementById("itemName").value = "";
                document.getElementById("itemUnit").value = "";
                document.getElementById("itemRate").value = "";
                await loadMeta();
                await renderItems(); // try refresh table
            } catch (err) {
                Swal.fire({ icon: "error", title: "Failed", text: err.message || "Could not add item." });
            }
        });

        /* ===========================
          SAVE PARTY
        =========================== */
        document.getElementById("partyForm").addEventListener("submit", async (e) => {
            e.preventDefault();

            const type = document.getElementById("partyType").value;
            const name = document.getElementById("partyName").value.trim();
            if (!name) return;

            try {
                await apiFetch(PV.routes.storeParty, {
                    method: 'POST',
                    body: {
                        type,
                        name,
                        mobile: document.getElementById("partyMobile").value.trim() || null,
                        address: document.getElementById("partyAddress").value.trim() || null,
                        head: document.getElementById("partyHead").value.trim() || null,
                        subhead: document.getElementById("partySubHead").value.trim() || null,
                    }
                });

                viewToast("success", "Added", "Party added.");
                document.getElementById("partyName").value = "";
                document.getElementById("partyMobile").value = "";
                document.getElementById("partyAddress").value = "";
                document.getElementById("partyHead").value = "";
                document.getElementById("partySubHead").value = "";
                await loadMeta();
                await renderParties(); // try refresh table
            } catch (err) {
                Swal.fire({ icon: "error", title: "Failed", text: err.message || "Could not add party." });
            }
        });

        /* ===========================
          LIST RENDERERS (AJAX)
          - If endpoint not built yet -> show placeholder row
        =========================== */
        function showNotImplemented(tbodyId, message) {
            const tbody = document.getElementById(tbodyId);
            if (!tbody) return;
            tbody.innerHTML = `
        <tr>
          <td colspan="20">
            <div class="pv-pill warn">${message}</div>
          </td>
        </tr>
      `;
        }

        async function renderEntryTable() {
            const tbody = document.getElementById("entryTbody");
            if (!tbody) return;

            const itemId = document.getElementById("entryFilterItem").value || '';
            const flow = document.getElementById("entryFilterFlow").value || '';
            const q = document.getElementById("entrySearch").value || '';

            try {
                const url = PV.routes.listEntries + `?item_id=${encodeURIComponent(itemId)}&flow=${encodeURIComponent(flow)}&q=${encodeURIComponent(q)}`;
                const res = await apiFetch(url);

                const rows = res.data?.rows ?? res.rows ?? res.data ?? [];
                const total = res.data?.total ?? res.total ?? rows.length;

                tbody.innerHTML = "";
                if (!rows.length) {
                    tbody.innerHTML = `<tr><td colspan="20"><span class="pv-pill">No entries found</span></td></tr>`;
                } else {
                    rows.forEach((r, idx) => {
                        const flowPill = r.flow === 'IN'
                            ? `<span class="pv-pill ok">IN</span>`
                            : `<span class="pv-pill bad">OUT</span>`;

                        const billedPill = r.billed_at
                            ? `<span class="pv-pill ok">Billed</span>`
                            : `<span class="pv-pill warn">Unbilled</span>`;

                        tbody.insertAdjacentHTML("beforeend", `
              <tr>
                <td>${idx + 1}</td>
                <td>${r.entry_date ?? r.date ?? '—'}</td>
                <td>${flowPill}</td>
                <td>${r.party_name ?? '—'}</td>
                <td class="text-truncate" style="max-width:220px;">${r.reason ?? '—'}</td>
                <td>${r.item_name ?? '—'}</td>
                <td>${fmtInt(r.qty ?? 0)}</td>
                <td>${fmtPKR(r.rate ?? 0)}</td>
                <td>${fmtPKR(r.amount ?? ((r.qty || 0) * (r.rate || 0)))}</td>
                <td>${billedPill}</td>
                <td class="text-end pv-actions">
                  <button class="btn btn-outline-secondary btn-sm" data-act="print" data-id="${r.id}">
                    <i class="bi bi-printer"></i>
                  </button>
                </td>
              </tr>
            `);
                    });
                }

                document.getElementById("entryCountChip").textContent = `${total} total`;

                // KPIs (if backend provides, use them; else compute naive from rows)
                const kpi = res.data?.kpi ?? res.kpi ?? null;
                if (kpi) {
                    document.getElementById("kpiInQty").textContent = fmtInt(kpi.in_qty ?? 0);
                    document.getElementById("kpiOutQty").textContent = fmtInt(kpi.out_qty ?? 0);
                    document.getElementById("kpiLines").textContent = fmtInt(kpi.lines ?? 0);
                } else {
                    document.getElementById("kpiInQty").textContent = '—';
                    document.getElementById("kpiOutQty").textContent = '—';
                    document.getElementById("kpiLines").textContent = '—';
                }

            } catch (err) {
                if (err.status === 404 || err.status === 500) {
                    showNotImplemented("entryTbody", "Entries list endpoint not ready yet (implement StockController@entries).");
                    document.getElementById("entryCountChip").textContent = `—`;
                    document.getElementById("kpiInQty").textContent = '—';
                    document.getElementById("kpiOutQty").textContent = '—';
                    document.getElementById("kpiLines").textContent = '—';
                    return;
                }
                showNotImplemented("entryTbody", err.message || "Failed to load entries.");
            }
        }

        async function renderBillTable() {
            const tbody = document.getElementById("billTbody");
            if (!tbody) return;

            const type = document.getElementById("billFilterType").value || '';
            const partyId = document.getElementById("billFilterParty").value || '';
            const q = document.getElementById("billSearch").value || '';

            try {
                const url = PV.routes.listBills + `?type=${encodeURIComponent(type)}&party_id=${encodeURIComponent(partyId)}&q=${encodeURIComponent(q)}`;
                const res = await apiFetch(url);
                const rows = res.data?.rows ?? res.rows ?? res.data ?? [];
                const total = res.data?.total ?? res.total ?? rows.length;

                tbody.innerHTML = "";
                if (!rows.length) {
                    tbody.innerHTML = `<tr><td colspan="20"><span class="pv-pill">No bills found</span></td></tr>`;
                } else {
                    rows.forEach((r, idx) => {
                        const pill = r.type === 'purchase' ? 'ok' : 'warn';
                        tbody.insertAdjacentHTML("beforeend", `
              <tr>
                <td>${idx + 1}</td>
                <td>${r.bill_date ?? r.date ?? '—'}</td>
                <td><span class="pv-pill ${pill}">${String(r.type || '').toUpperCase()}</span></td>
                <td>${r.party_name ?? '—'}</td>
                <td class="text-truncate" style="max-width:260px;">${r.bill_no ?? '—'}${r.entry_no ? ' • Entry: ' + r.entry_no : ''}</td>
                <td>${fmtPKR(r.total ?? 0)}</td>
                <td class="text-end pv-actions">
                  <button class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-printer"></i>
                  </button>
                </td>
              </tr>
            `);
                    });
                }

                document.getElementById("billCountChip").textContent = `${total} total`;

                // KPIs (backend optional)
                const kpi = res.data?.kpi ?? res.kpi ?? null;
                document.getElementById("kpiPurchaseBills").textContent = kpi ? fmtInt(kpi.purchase_count ?? 0) : '—';
                document.getElementById("kpiSaleBills").textContent = kpi ? fmtInt(kpi.sale_count ?? 0) : '—';
                document.getElementById("kpiBillTotal").textContent = kpi ? fmtPKR(kpi.total_amount ?? 0) : '—';

            } catch (err) {
                if (err.status === 404 || err.status === 500) {
                    showNotImplemented("billTbody", "Bills list endpoint not ready yet (implement StockController@bills).");
                    document.getElementById("billCountChip").textContent = `—`;
                    document.getElementById("kpiPurchaseBills").textContent = '—';
                    document.getElementById("kpiSaleBills").textContent = '—';
                    document.getElementById("kpiBillTotal").textContent = '—';
                    return;
                }
                showNotImplemented("billTbody", err.message || "Failed to load bills.");
            }
        }

        async function renderStock() {
            const tbody = document.getElementById("stockTbody");
            if (!tbody) return;

            const from = document.getElementById("stockFrom").value || '';
            const to = document.getElementById("stockTo").value || '';
            const itemId = document.getElementById("stockItemFilter").value || '';

            try {
                const url = PV.routes.report + `?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}&item_id=${encodeURIComponent(itemId)}`;
                const res = await apiFetch(url);
                const rows = res.data?.rows ?? res.rows ?? res.data ?? [];

                tbody.innerHTML = "";
                if (!rows.length) {
                    tbody.innerHTML = `<tr><td colspan="10"><span class="pv-pill">No stock rows</span></td></tr>`;
                } else {
                    rows.forEach(r => {
                        const avail = Number(r.available ?? r.avail ?? 0);
                        tbody.insertAdjacentHTML("beforeend", `
              <tr>
                <td>${r.item_name ?? r.item ?? '—'}</td>
                <td>${fmtInt(r.purchase_qty ?? r.in_qty ?? r.inQty ?? 0)}</td>
                <td>${fmtInt(r.sale_qty ?? r.out_qty ?? r.outQty ?? 0)}</td>
                <td><span class="pv-pill ${avail < 0 ? "bad" : "ok"}">${fmtInt(avail)}</span></td>
              </tr>
            `);
                    });
                }

                document.getElementById("stockRangeChip").textContent = `Range: ${from || "—"} → ${to || "—"}`;

                const kpi = res.data?.kpi ?? res.kpi ?? null;
                document.getElementById("kpiStockItems").textContent = kpi ? fmtInt(kpi.items ?? 0) : '—';
                document.getElementById("kpiStockIn").textContent = kpi ? fmtInt(kpi.in_qty ?? 0) : '—';
                document.getElementById("kpiStockOut").textContent = kpi ? fmtInt(kpi.out_qty ?? 0) : '—';
                document.getElementById("kpiStockAvail").textContent = kpi ? fmtInt(kpi.available ?? 0) : '—';

            } catch (err) {
                if (err.status === 404 || err.status === 500) {
                    showNotImplemented("stockTbody", "Stock report endpoint not ready yet (implement StockController@report).");
                    document.getElementById("kpiStockItems").textContent = '—';
                    document.getElementById("kpiStockIn").textContent = '—';
                    document.getElementById("kpiStockOut").textContent = '—';
                    document.getElementById("kpiStockAvail").textContent = '—';
                    return;
                }
                showNotImplemented("stockTbody", err.message || "Failed to load stock report.");
            }
        }

        async function renderItems() {
            const tbody = document.getElementById("itemTbody");
            if (!tbody) return;

            const q = document.getElementById("itemSearch").value || '';

            try {
                const url = PV.routes.listItems + `?q=${encodeURIComponent(q)}`;
                const res = await apiFetch(url);
                const rows = res.data?.rows ?? res.rows ?? res.data ?? [];

                tbody.innerHTML = "";
                if (!rows.length) {
                    tbody.innerHTML = `<tr><td colspan="10"><span class="pv-pill">No items</span></td></tr>`;
                } else {
                    rows.forEach((it, idx) => {
                        tbody.insertAdjacentHTML("beforeend", `
              <tr>
                <td>${idx + 1}</td>
                <td>${it.name}</td>
                <td>${it.unit}</td>
                <td>${fmtPKR(it.default_rate ?? 0)}</td>
                <td class="text-end pv-actions">
                  <button class="btn btn-outline-secondary btn-sm" disabled><i class="bi bi-pencil"></i></button>
                  <button class="btn btn-outline-danger btn-sm" disabled><i class="bi bi-trash3"></i></button>
                </td>
              </tr>
            `);
                    });
                }
                document.getElementById("itemCountChip").textContent = `${rows.length} total`;
            } catch (err) {
                showNotImplemented("itemTbody", "Items list endpoint not ready yet (implement StockController@items).");
                document.getElementById("itemCountChip").textContent = `—`;
            }
        }

        async function renderParties() {
            const tbody = document.getElementById("partyTbody");
            if (!tbody) return;

            const q = document.getElementById("partySearch").value || '';
            const t = document.getElementById("partyFilterType").value || '';

            try {
                const url = PV.routes.listParties + `?q=${encodeURIComponent(q)}&type=${encodeURIComponent(t)}`;
                const res = await apiFetch(url);
                const rows = res.data?.rows ?? res.rows ?? res.data ?? [];

                tbody.innerHTML = "";
                if (!rows.length) {
                    tbody.innerHTML = `<tr><td colspan="20"><span class="pv-pill">No parties</span></td></tr>`;
                } else {
                    rows.forEach((p, idx) => {
                        tbody.insertAdjacentHTML("beforeend", `
              <tr>
                <td>${idx + 1}</td>
                <td>${p.name}</td>
                <td>${p.mobile ?? '—'}</td>
                <td class="text-truncate" style="max-width:160px;">${p.address ?? '—'}</td>
                <td>${p.head ?? '—'}</td>
                <td>${p.subhead ?? '—'}</td>
                <td><span class="pv-pill">${p.type}</span></td>
                <td class="text-end pv-actions">
                  <button class="btn btn-outline-secondary btn-sm" disabled><i class="bi bi-pencil"></i></button>
                  <button class="btn btn-outline-danger btn-sm" disabled><i class="bi bi-trash3"></i></button>
                </td>
              </tr>
            `);
                    });
                }
                document.getElementById("partyCountChip").textContent = `${rows.length} total`;
            } catch (err) {
                showNotImplemented("partyTbody", "Parties list endpoint not ready yet (implement StockController@parties).");
                document.getElementById("partyCountChip").textContent = `—`;
            }
        }

        async function renderAll() {
            // refresh selects
            fillPartySelect(document.getElementById("entryParty"), false);
            fillPartySelect(document.getElementById("billParty"), false);

            fillItemSelect(document.getElementById("entryFilterItem"), true);
            fillItemSelect(document.getElementById("stockItemFilter"), true);
            fillPartySelect(document.getElementById("billFilterParty"), true);

            await renderEntryTable();
            await renderBillTable();
            await renderStock();
            await renderItems();
            await renderParties();
        }

        /* ===========================
          INIT
        =========================== */
        async function initUI() {
            // default dates
            document.getElementById("entryDate").value = todayISO();
            document.getElementById("billDate").value = todayISO();
            document.getElementById("stockFrom").value = daysAgoISO(30);
            document.getElementById("stockTo").value = todayISO();

            // wire filters
            document.getElementById("entryFilterItem").addEventListener("change", renderEntryTable);
            document.getElementById("entryFilterFlow").addEventListener("change", renderEntryTable);
            document.getElementById("entrySearch").addEventListener("input", renderEntryTable);

            document.getElementById("billFilterType").addEventListener("change", renderBillTable);
            document.getElementById("billFilterParty").addEventListener("change", renderBillTable);
            document.getElementById("billSearch").addEventListener("input", renderBillTable);

            document.getElementById("itemSearch").addEventListener("input", renderItems);
            document.getElementById("partySearch").addEventListener("input", renderParties);
            document.getElementById("partyFilterType").addEventListener("change", renderParties);

            document.getElementById("btnApplyStock").addEventListener("click", renderStock);

            // Bill type -> new bill no
            document.getElementById("billType").addEventListener("change", refreshBillNo);

            // Initial meta
            await loadMeta();

            // Initial rows
            entryLinesWrap.innerHTML = "";
            entryLinesWrap.appendChild(makeLineRow('entry'));
            recalcEntryTotal();

            billLinesWrap.innerHTML = "";
            billLinesWrap.appendChild(makeLineRow('bill'));
            recalcBillTotal();

            // Counters
            await refreshSlipNo();
            await refreshBillNo();

            // Mode default
            setEntryMode("purchase");

            // render
            await renderAll();
        }

        document.addEventListener("DOMContentLoaded", async () => {
            try {
                await initUI();
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Init failed', text: e.message || 'Could not initialise module.' });
            }

            // tab switching
            tabBtns.forEach((btn) => btn.addEventListener("click", () => setView(btn.dataset.view)));
        });

        // Print/JV demo buttons keep
        document.getElementById("btnPrintBill").addEventListener("click", () => viewToast("info", "Print", "Demo print action triggered."));
        document.getElementById("btnGenerateJV").addEventListener("click", () => viewToast("info", "JV", "Generate JV is UI-only for now."));
    </script>

@endsection