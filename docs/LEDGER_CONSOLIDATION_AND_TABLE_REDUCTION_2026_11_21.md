# Ledger consolidation and schema reduction — 2026-11-21

## Target
- The existing ledgers table is the authoritative account debit/credit table for every voucher: CR, DR, CCR, CDR, BOR, BOP, JV, SV, CV, BD, BW, PPR, PP, and other financial sources.
- Keep source tables only for genuinely source-specific fields, document details, allocations, bank custody, inventory, and legally required approval/history evidence.
- All ledger lines must use their actual displayed voucher series, with verifiable project, source, and detail-level ownership.
- Pending/Rejected transactions must not affect active balances.
- No unsafe CP-to-DR blanket SQL, table drops, or independent finance ledger in the final design.

## Verified schema and architectural evidence
- The branch has 149 create-table migration definitions, including 93 in the consolidated Nov 17 baseline. This is not proof of exactly 149 physical tables in the selected database.
- finance_posting_lines already has legacy_ledger_id for linkage. ledger_source_attributions already captures some provenance. No additional mapping table is needed for this batch.
- BD/BW and typed plot/SV writers currently create finance_posting_lines but do not universally create ledgers. Cash/DR/commission/JV have other legacy paths.
- Legacy CP rows can belong to DR, CDR or BOP display families. All CP sources must be classified by project and original customer_ledger and project_voucher_display_numbers records before relabeling.

## Migration gates
1. Measure actual tables, dependencies, project-scoped linked and unlinked ledger lines, invalid links, and CP display identities. New read-only command finance:audit-ledger-consolidation provides evidence and optional --strict gate. Existing schema:audit-unused-tables inventory reports table dependency candidates.
2. Design the authoritative ledger link (project, source table, source ID, detail ID, actual voucher series) using existing FK/data wherever possible; preserve voucher-specific fields and allocation detail tables.
3. Implement transactional ledger creation for each voucher family. Create/Approve must write balanced active or inactive ledger entries; retries must be idempotent.
4. Reconcile legacy finance_posting_lines to exact ledgers through legacy_ledger_id and verify amount, direction, account, date, project and source. Never guess by type_id or voucher number.
5. Reconcile and support Edit/Delete/Reject/Reversal lifecycle, including inactive history. Check that reports do not double-count linked typed and ledger lines.
6. Run cross-project finance totals, Party/Head Ledger, Trial Balance and voucher regression/browser tests; switch canonical report consumers to ledgers.
7. Reduce tables only after backed-up, source-aware data migration, code/foreign-key reference audit and zero unmatched historical rows. Mark each physical table KEEP, MERGE or RETIRE; do not delete approval audit history or unique plot/stock allocations just because they have a similar label.

## Delivery status
- Implemented now: read-only finance:audit-ledger-consolidation command; safe explicit database selection; optional --strict completion gate; no-write contract test.
- NOT YET IMPLEMENTED: CP type conversion, dual-ledger consolidation writes, historical ledger backfill, unified report cutover, physical table drops. Do not claim they are finished.

## Safety acceptance
- Every financial voucher has its own actual series in ledgers with balanced debit and credit, exact source and project ownership.
- All active finance posting lines have verified ledger links; no account/amount/foreign-project mismatches.
- Approval ON/OFF, update, delete, reverse, no-change save, source replay and cross-project tests all pass.
- Source and report totals reconcile, no double-counting, and no unnecessary new tables.

## Local owner evidence — 2026-11-21
- Exact connected MySQL database: `bluenew_clean_final`; it has **149 physical tables**. Laravel `db:show` displays **1,113 tables across all locally accessible MySQL schemas**, not 1,113 tables in this one database.
- LedgerConsolidationSafetyContractTest owner run PASS (3 tests / 31 assertions).
- `finance:audit-ledger-consolidation --database=bluenew_clean_final`: project 3 has **43 finance_posting_lines with NULL legacy_ledger_id**, none with invalid existing links. Breakdown: BD 8, BW 8, PR 3, PP 2, EC 6, ECP 4, REV 6, CV 4, SV 2.
- Legacy CP ledger report: **11,087 require identity review**, including rows without valid customer_ledger FK. Only two project-3 CP rows had explicit DR/BOP display mapping. This is **not** evidence that all 11,087 are safely DR; no bulk type renaming is authorized.
- Existing unused-table audit was run by owner into `storage/app/table_audit.json`; its contents were not supplied, so no candidate table is approved for deletion.

## Implemented follow-on batch: exact source ledger backfill
- `finance:backfill-verified-ledgers --database=bluenew_clean_final --project=3` previews linked/unlinked **BD, BW, plot PR and PP** source batches. This targets up to **21 typed lines** in the owner inventory, *only if every source and posting identity passes strict checks*. PR is the Plot Receipt batch series; the final ledger's voucher type is `PPR`. No other PPR family is silently modified.
- On an explicitly approved backup copy, `--apply --confirm=bluenew_clean_final` inserts `ledgers` and links `finance_posting_lines.legacy_ledger_id`, with a matching `ledger_source_attributions` provenance row. The code checks project, source claim, source ID, account, amount, active status, balanced current legs and existing duplicate ledger sources.
- **No new tables or migrations.** A blocked source stops that batch; the apply command preflights all project batches before beginning writes. Use this only on a backed-up local disposable database with no concurrent voucher writers; remaining EC/ECP/CV/SV/REV 22 lines and CP identity migration need separate source-aware implementations.
- This backfill **does not** cut over new Create/Edit/Delete writers or reports; that work remains pending until account lifecycle parity is implemented. Do not consider `ledgers` fully authoritative yet.
