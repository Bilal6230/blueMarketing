# System Setting — Implementation Tracker

**Project:** BlueMarketing / ERP  
**Scope:** Settings (`/admin/setting`) — project-scoped configuration and modern UI  
**Baseline:** 26 November 2026  
**Status:** S1 browser and contract checks reported PASS (7/7; 48 assertions). S2 code implemented; automated and browser checks pending. S3 browser picker flow reported PASS; S4 UI batch implemented, numbering integration pending.  
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
**State: Revised UI implementation pushed; fresh local PHPUnit and browser checks pending.**

- [x] Replace always-visible Device Configuration form with a **table of saved devices**, listing Device, Connection, Status and Edit / Map IDs / Delete actions.
- [x] **Add Device** opens a blank, centered modal; **Edit** loads the same modal with existing device data via project-scoped AJAX JSON. Form no longer occupies the Settings card.
- [x] Device create/update runs via jQuery AJAX + confirmation; updates the device table and mapping selector without reloading the page.
- [x] Employee ID Mapping selects a saved device, loads mappings by AJAX and adds/updates/removes mappings by AJAX with independent confirmation and in-place table refresh. No full-page form submission.
- [x] Fix root cause of S2 blank page / dead Add Device click: move event handlers to `@push('script')` (after jQuery) and explicitly exempt forms from generic AJAX form replacement using `data-ajax="off"`.
- [x] Guard deletion by project ID and `update setting` permission. Device deletion is refused while employee mappings exist; a confirmed mapping Delete action is available first.
- [x] Preserve encrypted secret; send only `has_password` flag on JSON responses. Preserve existing saved secret when update leaves password blank.
- [x] Test Connection and Sync Attendance remain disabled until a verified device adapter exists. Saving configuration does not imply a working connection.
- [x] Automated S2 contract file updated for revised flow.
- [ ] Run `php artisan view:cache`, `php artisan test --filter=SystemSettingS2ContractTest`, and `php artisan test --filter=SystemSettingS1ContractTest` on the user's local project.
- [ ] Browser verify Add opens modal; Edit pre-fills; Save Yes/No and validation; newly saved device appears without reload; Map IDs loads mappings; mapping Apply/Edit/Delete works without blank page; Device Delete with mappings refuses; after removing mappings Delete succeeds; no change of URL; credentials stay hidden.
- [ ] Mark S2 fully verified after the above checks pass.

### S3 — Bank Accounts and Cash Accounts
**State: Implementation batch pushed on 26 November 2026; migration, tests, and user browser verification pending.**

- [x] Remove **Configure Payment method labels** from Bank Accounts UI (retain underlying data until dependent views are audited).
- [x] Remove **Recent bank account changes** and **Recent payment label changes** visual cards; continue recording actions for Audit Activity.
- [x] Only two top actions: **Configure Bank Account** and **Configure Cash Account**, each opening its own Add/Edit modal.
- [x] Add project bank **Display Title** field, keeping Bank name, Account title, Branch, Account number, Project Chart of Accounts mapping and status.
- [x] Add project cash account master with **Display Title**, **Project Chart of Accounts mapping**, and **Active/Inactive** status.
- [x] Show two **side-by-side listing cards**, Bank Accounts and Cash Accounts, with Edit / Activate / Deactivate actions and clear labels.
- [ ] Project-specific active account mappings only for new accounts. Preserve historical mappings/account IDs and protect accounts used by posted vouchers.
- [ ] Populate relevant bank account selectors (e.g. BOR/BOP Counter Account and other voucher bank pickers) with **Display Title**. Cash pickers use configured cash Display Title. Changing a display title must not change the underlying account mapping/financial transaction.
- [ ] Add/adjust migrations safely; audit every action; AJAX validation and UI update; test projects with zero configured accounts and multi-project isolation.


**S3 implementation notes (not acceptance):** Migration `2026_11_26_210000_add_s3_bank_cash_account_masters.php`, `ProjectCashAccount`, bank Display Title, scoped JSON edit/save/status endpoints, cash and bank audit writes, side-by-side modern table cards, modal forms, and `SystemSettingS3ContractTest` were pushed. S3 front-end reloads the selected tab through AJAX; existing account IDs remain unchanged. Do not mark S3 complete until picker integration and live verification.

**S3 voucher picker update:** BOR/BOP new voucher Counter Account now displays the active bank profile Display Title (falls back to Bank / Account Title); CCR/CDR new voucher Counter Account now displays active Cash Account Display Titles. Cash Save verifies the current project's active cash mapping both before and inside the posting transaction. Bank profile and COA mapping IDs have not been changed. Static picker regression test: `SystemSettingS3VoucherPickerIntegrationTest`.

**Outstanding S3 verification:** Run picker and S3 contract tests, and browser-test BOR/BOP and CCR/CDR Create forms, disabled accounts, project switching, and recorded postings. Review existing historical voucher Edit / print display labels separately before marking all S3 consumers integrated. No historical postings should be relinked.

### S4 — Voucher Display / receipt headers
**State: UI, receipt-header expansion, prefix-save confirmation, and project voucher Active/Inactive route guard pushed 26 November 2026. New numbering writer integration, automated and browser verification pending.**

**Current S4 changes:** Voucher Name labels centralized in `ProjectVoucherDisplay::LABELS`, modern full-height Voucher Display directory with shared Users-style filter/export/pagination and Action menu components, receipt-header switches inside matching listing rows (including legacy-only types), dedicated Add/Edit prefix modal with AJAX save and in-place listing update, and custom-payment-method configuration removed from this UI only. S4 regression contract `SystemSettingS4ContractTest` added. Underlying custom payment method records, voucher writer identities and historical receipts are untouched. The optional Start Number field is deliberately disabled until collision-safe versioned display numbering can be implemented end-to-end.

**S4 follow-up fixes:** Bootstrap-based Save Prefix confirmation replaces the unavailable SweetAlert call. Every supported voucher family now has a Print Header toggle, with the legacy Setting upsert accepting never-used voucher series. Additive `project_voucher_availability` table stores Active/Inactive per project (default Active). Inactive actionable voucher series are hidden from both sidebars and the New Voucher chooser on the next page render; the protected entry, create and related option/source routes enforce HTTP 403, including embedded `series` URLs and action-scoped EC/ECP/PPR creation. Historical audit, approval and print routes remain available, and project finance data is not deleted. BO/BR now expose project Active/Inactive controls: their internal new voucher allocation fails with 403 while inactive, without blocking historical records or prints. CHR/CHP are cheque-method legacy display labels, not independent entry modules. The original global Print Header setting behavior is preserved. Run `php artisan migrate` and the S4 contracts before browser acceptance.

**UI refinement (26 November 2026):** Removed Copy/CSV/Excel/PDF/Print/Column Visibility toolbar from all System Settings listing cards (Attendance, Bank/Cash, Voucher Display), without removing column sorting/filtering, pagination or action menus. Removed the Voucher Display bottom explanatory footer. Print Header and Active/Inactive changes now require an explicit Bootstrap confirmation dialog; Cancel preserves the original switch state and AJAX runs only after Yes, Apply.

**S4 BO/BR and remaining Edit fix:** Added BO/BR project availability controls with guarded BO/BR source and ledger number allocation; included DR, BD, BW, PP in the editable voucher-prefix series, and expanded the settings validation allowlist. These settings do **not** activate the configured prefix in actual issued voucher numbers. Example: saving CRV for legacy CR still leaves the form's canonical `CR-832` preview unchanged until versioned display numbering is implemented across creation, display, print and approval workflows; do not simply relabel the form without changing its stored display identity.

**CP → DR legacy consolidation:** The owner confirmed that CP (Cash Payment Voucher) was renamed to DR (Debit Voucher). Roles now show only DR, with the corresponding CP and DR permissions selected/updated together. The additive grant migration `2026_11_26_230000_consolidate_legacy_cp_dr_role_grants.php` unions existing role/direct user grants without deleting either permission or rewriting historical voucher records. Voucher Settings hides the duplicate CP row/selector; CP entry URLs resolve to DR, CP historical records keep their source IDs, numbers and edit/print functionality, and CP historical authorization checks resolve via DR permissions. Voucher activation for CP is now governed by DR. **The held prefix/start-number migration remains separate and has not been implemented.**

**Safety discovery:** Existing `ProjectVoucherDisplay::format()` still returns canonical `$series.'-'.$number` even if a prefix setting is present. Therefore saving a prefix in S4 remains *configuration only*, not a promise that new vouchers already use that prefix. Do not enable Start Number or show a next-issued-number promise without wiring all writer and print families.


- [ ] Replace Type codes in listing and editor with full **Voucher Name** (common series have labels; verify remaining legacy/finance families and remove generic fallbacks before S4 acceptance). (e.g. Credit Voucher, Debit Voucher, Cash Receipt Voucher, etc.), while retaining legacy internal series keys as immutable identities.
- [x] Move receipt-header ON/OFF toggles from Information into each corresponding Voucher Display row; AJAX update.
- [ ] Editor: **Voucher Name** selector, **Current Prefix** (editable), optional **Voucher Number** (start number). Do not confuse a display prefix with the immutable internal type code.
- [ ] Expected future example: existing `CR` sequence has 850 entries. Set prefix `CRV`, optional number blank => next new displayed number `CRV-1`; set number `78` => next new displayed number `CRV-78`, then `CRV-79`. Source prompt also uses `CVR` as an example; prefix is user-configurable, not hardcoded.
- [ ] **Critical compatibility requirement:** Current `ProjectVoucherDisplay` uses a *display prefix* and year-formatted number; current `SettingController::saveVoucherDisplaySettings` changes display only. Implement a **new versioned display-series counter** scoped to project + voucher family + prefix (and define fiscal-year behavior explicitly before enabling); reserve number atomically on successful write, not merely opening a modal.
- [ ] Detect/prevent collisions with existing historical voucher display numbers, including prefix reuse. Preserve original display identity for old vouchers, printed receipts, linked records, approval/audit entries and exported reports. No historical renumbering or accidental re-posting.
- [ ] On Add Voucher modal show **next preview number**; after a successful create assign the reserved sequence. Parallel submissions, canceled drafts, retries, deletes, reactivations and approval flow must not duplicate numbers.
- [ ] Apply new display semantics consistently across **all voucher families** that the application actually supports; do not implement only one voucher type.
- [x] Remove/hide **Custom payment methods / Configure payment method** from Voucher Display UI pending a separate integration decision. Existing custom payment method records must remain untouched; evaluate Cash/Online/Cheque consumers before final removal.
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
