# System Setting — Implementation Tracker

**Project:** BlueMarketing / Waqas ERP  
**Scope:** Settings (`/admin/setting`) — project-scoped configuration and modern UI  
**Baseline:** 26 November 2026  
**Status:** S1 browser and contract checks reported PASS (7/7; 48 assertions). S2 code implemented; automated and browser checks pending. S3–S4 pending.  
**Tracker rule:** Check `[x]` only after code + automated tests + user browser verification pass. Do not mark existing partial code as complete.

## Overall behavior (applies to every batch)

- One shared design system: compact modern tables/cards, 14px readable table/form content, sticky table header where needed, clear borders and alternating rows.
- Settings card extends down to the footer with **only its card body scrolling**; no outside page scroll and no clipped footer. Responsive on smaller displays.
- After initial Settings page load, **all tab switches, create/edit/save/deactivate, and row updates are AJAX/jQuery**; do not reload the document or navigate to query parameters for device creation or editing.
- Confirmation, validation and failure messages use existing SweetAlert/toast pattern. Cancel or failed writes restore prior state. No raw inline error page; per-field messages as appropriate.
- Preserve project isolation, permissions, accounting audit trail and existing voucher/ledger identity. No destructive historical data rewriting.
- Keep user-facing change history under **Audit Activity**. Existing underlying audit records must remain stored.

## Implementation batches

### S1 — Settings layout, Information & Contact
**State: Verified by user — S1 browser PASS, SystemSettingS1ContractTest 7/7 (48 assertions), 26 November 2026.**  
**Implementation commits:** `f954258a`, `0bace770`, `f6a9dd33`, `b6a80847`, `60aab9f8`, `6cea76f0`, `faabc3da`.

- [x] Main Settings card fits available viewport up to the footer; tab content owns vertical scrolling, page remains fixed.
- [x] Merge **Contact** into **Information & Contact** and remove separate Contact tab.
- [x] Render Information + Contact fields **directly in tab card**; no configuration modal for these sections.
- [x] AJAX FormData save with Yes/No Bootstrap confirmation modal, JSON response, validation and toast feedback. Application Logo, Favicon and Loading Image use compact 3-column direct drag/drop uploaders with previews, file validation and secure file storage (up to 4MB per JPG/PNG/WEBP/GIF).
- [x] Move receipt-header toggles into **Voucher Display** table; each ON/OFF change saves via confirmation + AJAX and reverts on cancel/failure.
- [x] Keep existing global application logo/favicon/loading-image settings in Information, without changing project branding in ERP.
- [x] `php artisan view:cache` and `php artisan test --filter=SystemSettingS1ContractTest` PASS (7/7, 48 assertions). ProjectLogoBrandingContractTest is independent and not part of the reported run.
- [x] User reported S1 browser testing PASS after drag/drop uploader update. Refer to the browser screenshot and user confirmation; detailed subcase logs were not provided.
- [x] S1 accepted. Follow-up visual tweaks, if requested, remain separate.

### S2 — Attendance Machine
**State: Implementation pushed — automated tests and browser acceptance pending.**

- [x] Unify Add/Edit: **Add Device** clears the same inline editor; selecting an existing device loads it using a scoped JSON endpoint without reloading.
- [x] Device Save / Update uses jQuery AJAX + confirmation popup; successful saves update the selected-device dropdown, editor state and mappings in place.
- [x] Employee ID mapping uses jQuery AJAX + confirmation popup; successful changes repaint the mapping table in place. Existing mapping row Edit pre-fills the editor.
- [x] Device details endpoint checks project ownership, role and `update setting` permission; password/secret is never included in JSON and the Eloquent password cast remains encrypted.
- [x] Separate project-wise Device Configuration and Employee ID Mapping cards; compact table with alternating rows, validation and inline errors.
- [x] Test Connection and Sync Attendance remain disabled until the vendor protocol and event adapter have been verified. Saving does not claim a successful connection.
- [x] Generic Settings modalizer excludes S2 AJAX forms.
- [ ] Local `php artisan view:cache` and `php artisan test --filter=SystemSettingS2ContractTest` PASS.
- [ ] Browser check: Add Device without full reload; update selected device; cancel confirm, validation errors and network failure; employee ID mappings add/update without reload; project isolation; disabled sync and connection; no plaintext secret in Network responses.
- [ ] After local test and browser checks pass, mark S2 fully verified.

### S3 — Bank Accounts and Cash Accounts
**State: [ ] Pending**

- [ ] Remove **Configure Payment method labels** from Bank Accounts UI (retain underlying data until dependent views are audited).
- [ ] Remove **Recent bank account changes** and **Recent payment label changes** visual cards; continue recording actions for Audit Activity.
- [ ] Only two top actions: **Configure Bank Account** and **Configure Cash Account**, each opening its own Add/Edit modal.
- [ ] Add project bank **Display Title** field, keeping Bank name, Account title, Branch, Account number, Project Chart of Accounts mapping and status.
- [ ] Add project cash account master with **Display Title**, **Project Chart of Accounts mapping**, and **Active/Inactive** status.
- [ ] Show two **side-by-side listing cards**, Bank Accounts and Cash Accounts, with Edit / Activate / Deactivate actions and clear labels.
- [ ] Project-specific active account mappings only for new accounts. Preserve historical mappings/account IDs and protect accounts used by posted vouchers.
- [ ] Populate relevant bank account selectors (e.g. BOR/BOP Counter Account and other voucher bank pickers) with **Display Title**. Cash pickers use configured cash Display Title. Changing a display title must not change the underlying account mapping/financial transaction.
- [ ] Add/adjust migrations safely; audit every action; AJAX validation and UI update; test projects with zero configured accounts and multi-project isolation.

### S4 — Voucher Display / receipt headers
**State: [ ] Pending**

- [ ] Replace Type codes in listing and editor with full **Voucher Name** (e.g. Credit Voucher, Debit Voucher, Cash Receipt Voucher, etc.), while retaining legacy internal series keys as immutable identities.
- [ ] Move receipt-header ON/OFF toggles from Information into each corresponding Voucher Display row; AJAX update.
- [ ] Editor: **Voucher Name** selector, **Current Prefix** (editable), optional **Voucher Number** (start number). Do not confuse a display prefix with the immutable internal type code.
- [ ] Expected future example: existing `CR` sequence has 850 entries. Set prefix `CRV`, optional number blank => next new displayed number `CRV-1`; set number `78` => next new displayed number `CRV-78`, then `CRV-79`. Source prompt also uses `CVR` as an example; prefix is user-configurable, not hardcoded.
- [ ] **Critical compatibility requirement:** Current `ProjectVoucherDisplay` uses a *display prefix* and year-formatted number; current `SettingController::saveVoucherDisplaySettings` changes display only. Implement a **new versioned display-series counter** scoped to project + voucher family + prefix (and define fiscal-year behavior explicitly before enabling); reserve number atomically on successful write, not merely opening a modal.
- [ ] Detect/prevent collisions with existing historical voucher display numbers, including prefix reuse. Preserve original display identity for old vouchers, printed receipts, linked records, approval/audit entries and exported reports. No historical renumbering or accidental re-posting.
- [ ] On Add Voucher modal show **next preview number**; after a successful create assign the reserved sequence. Parallel submissions, canceled drafts, retries, deletes, reactivations and approval flow must not duplicate numbers.
- [ ] Apply new display semantics consistently across **all voucher families** that the application actually supports; do not implement only one voucher type.
- [ ] Remove/hide **Custom payment methods / Configure payment method** from Voucher Display UI pending a separate integration decision. Existing custom payment method records must remain untouched; evaluate Cash/Online/Cheque consumers before final removal.
- [ ] Test old/new numbering, start-number override, duplicate prefix, project isolation, receipts, linked vouchers, finance audits and all supported approval workflows.

## Priorities and release gates

1. **Batch S1** first (layout + Information + Contact + receipt-header relocation).
2. **Batch S2** next (Attendance AJAX).
3. **Batch S3** next (Bank/Cash masters + picker integrations).
4. **Batch S4** last (voucher numbering is highest financial-integrity risk).

Each batch ends with: code merged to `upgrade/laravel-framework`, PHP syntax + `view:cache` + relevant PHPUnit tests passing, specified browser checks passing, then its checklist items switched to `[x]` with commit reference and date.

## Existing system findings (not counted as completed requirements)

- Current Settings wraps Information/Contact in auto-created configuration modals; attendance Add Device is a regular GET link that reloads the page.
- Existing project bank account configuration, mapping guard and some audit tables already exist. **Cash account master and Bank Display Title are not yet implemented under this requested flow.**
- Existing receipt-header toggles live under the legacy Information form.
- Current voucher prefix configuration changes **display prefix only**; it does **not** implement new prefix-specific numbering starting at 1/78.
- Existing custom payment methods are currently labeled as pending full voucher integration; therefore hide/remove from UI only after a dependency check.
- Audit Activity is the desired centralized *view*. Removing local audit-history cards does not remove underlying history.

**Do not infer a completed batch from its current basic/partial functionality.**
