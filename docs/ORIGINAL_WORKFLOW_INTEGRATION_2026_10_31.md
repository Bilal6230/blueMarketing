# Original workflow integration — local continuation, 2026-10-31

## Delivered functional batch

This changes original application controllers/routes, not just parallel pilot endpoints. All new typed writes still require isolated local/testing connection, explicit pilot/write flags, the registered finance Gate, authorized project context and existing route permissions. Production/V1 deployment is not enabled.

| Original path | Delivered behavior | Evidence boundary |
|---|---|---|
| JournalVoucherController::store | Original browser JV creates header/details and typed posting atomically; server number allocation; exact account/head ownership recheck under lock; request replay returns the same source; changed metadata/lines refuse replay; no legacy ledger leg insertion | Actual original HTTP route, replay/conflict/Gate/missing-key/rollback tests |
| JournalVoucherController::destroy | Original reverse route preserves source/details and reverses original typed balances; shared Gate/project checks now also protect isolated edits/reversals | Actual original HTTP reversal and per-account zero closing balance |
| CommissionVoucherController | Original create preserves reference/description; update passes changed metadata to guarded draft workflow; original approval route atomically approves and posts, with retry protection and rollback; edit view exposes approval of saved draft | HTTP create/approve/replay/failure; existing draft workflow regression |
| CV number and UI source identity | Form next number comes from that project's CV sources, including archived numbers, rather than JV IDs. Numeric CV prefix stored once; formatted once. Listing Edit/Print uses CV routes, not unrelated JV with equal ID. CV print says Commission, not Journal | Number/duplicate-draft/print-render/listing-action regression |
| BookingController::approve | Five series CR/CP/PPR/EC/ECP approval invokes typed posting using actual customer_ledger PK and explicitly approved legs, with atomic rollback and replay. Legacy-linked source refuses double posting | Actual original HTTP route for all five series; rollback/wrong-project/legacy-link tests |
| Legacy BookingController::approve | Validates IDs and 0/1 approval; scopes source lookup to selected project; refuses revoking a ledger-linked posted source | Actual original route negative tests; this is not full legacy project-membership certification |
| StockController create/approve | In isolated V2 mode, original inventory creation commits source/movement/balance while finance remains pending. Separate original approval route requires project evidence and stock-finance Gate before exact source posting. Existing V1 reference prevents duplicate V2 posting. Posting failure rolls back approval attribution | Original entry create → separate approval → typed posting; exact entry/bill approval/replay; legacy conflict and rollback tests |
| MobileStockController | Original v1/mobile stock source creation also leaves finance pending; dedicated approval route uses the same approval/posting application service. Original auth/project resolution and can-approve permission retained | Original mobile HTTP IN create → approval → typed posting |
| Active account/master protection | Four posting writers lock active project account models; stock create locks active party/items to serialize against archival. Web stock closure metadata no longer falls back to undefined variable data | Archived account/item/party rejection; full regression |

Both original voucher list reversal forms now submit request identity, business date and reason and label the operation Reverse. These controls do not enable reversal of an unposted CV draft; that remains refused by the posted-source lifecycle service. Separate API draft cancellation already exists; original browser cancellation integration remains follow-up work.

Stock snapshot accepts SQL DATE and Eloquent/SQLite's midnight representation as the same business date. Historical MySQL Y-m-d fingerprints remain unchanged. Non-midnight or invalid source dates still fail the writer's calendar validation.

CV legacy JV numeric collisions cause refusal, never automatic source ownership attribution. Customer source FK-linked legacy postings and stock source reference-linked legacy postings also cause refusal rather than duplication or removal.

## Required remaining B3 work — no whole-milestone completion claim

1. Original labour payment/attendance JV source creation, approvals and allocations still need typed workflow integration. Do not infer that the existing V2 labour posting engine means the original controller was migrated.
2. Original CR/CP/PPR/EC/ECP **creation and clearance** paths (LedgerController, Finance/VoucherController and BookingController::deposit) remain legacy. This batch integrates approval of a real source, not those original source-creation branches.
3. Original finance/customer/account/stock/labour reports and exports need end-to-end typed read/coexistence integration. A corrected CV print and existing separate V2 report API are not full original-report migration.
4. Original browser CV cancellation, complete bill-create/OUT/mobile/business UI scenarios, and operator-facing stock approval controls still require implementation/UAT as applicable. Bill approval and mobile IN source creation are proven; do not silently broaden those proofs.
5. MySQL lock/concurrency, full historical source parity, browser/mobile UAT and controlled release remain C2/C3/D2/D3 requirements. This batch is not authorization for live cutover.

Do not exercise remaining legacy writers as a substitute for typed workflows on an isolated V2 rehearsal. Do not activate production or owner schema changes. Both archive migrations remain unapplied to owner databases.

## Tests and checkpoint

Focused new original-flow suite: **27 tests / 175 assertions PASS**, including real original route middleware, Gate checks, failed-insert rollback and source-safe display. FINAL full suite: **828 tests / 3,735 assertions PASS** (86.498 seconds); all **98 changed/new PHP entries lint PASS**, diff whitespace check PASS. No .env edits, owner DB connection/mutation, migration/import/repair, commit, push or deployment.

Active checklist remains **7/12 accepted engineering deliverables**; B3 is IN PROGRESS, not completed. This is implementation progress within B3 and does not justify an eighth accepted deliverable yet. Owner action: NONE for independent coding. Next coherent batch: original labour and remaining cash/customer source creation/clearance, then original report compatibility.
