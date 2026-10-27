# Historical JV reconciliation — 2026-10-27

**Deployment BLOCKED. Historical ownership not certified.** Repository baseline: `14560873feac8e2f3ff5f25aa3afa6622ddc88d3`. GitHub Actions run [40498082989](https://github.com/Bilal6230/blueMarketing/actions/runs/40498082989) was independently checked: completed/success for that exact SHA. This does not cover the new uncommitted changes.

## Evidence availability and actual counts

No live historical rows were read in this session. Restricted staging credentials and the previous PowerShell `$password` variable are unavailable. No credential file was searched, password reset attempted, MySQL connection opened or `.env` edited. The inventory below comes from the supplied handoff, not a fresh scan; it cannot establish current ownership, discrepancies or balances.

| Priority | Project | Reported unresolved JV-type rows | Reported JV headers | Newly assessed independent ownership |
|---|---:|---:|---:|---|
| 1 | 6 | 1430 | 672 | Not assessed |
| 2 | 5 | 749 | 186 | Not assessed |
| 3 | 3 | 692 | 122 | Not assessed |
| 4 | 1 | 2 | 0 | Not assessed |
| 5 | 2 | 0 | 0 | Not assessed |
| 6 | 4 | 0 | 0 | Not assessed |
| Total | All six | **2873** | **980** | **Not assessed** |

Historical project-6 evidence in MASTER_ROADMAP/AUDIT_WORKLOG records a complete earlier 1430-row scan: **1420 JV-header-only candidates, 10 neither-header, zero both-header/CV-only candidates**. These counts describe numeric candidates, not actual owners; they were not revalidated against current staging. The ten recorded missing-header ledger IDs are **12179, 12180, 13047–13054**. Start manual source retrieval with those cases, then review the 1420 remaining candidates. The old packet for ledger 12179 established no same-ID header, not an accounting error. No equivalent reference-strength counts exist in the supplied evidence for projects 5, 3 or 1.

| Requested evidence class | Real-data finding now | Required next evidence |
|---|---|---|
| Strong independently verifiable ownership | Unknown; no real record certified | Authenticated original document/posting trail plus project/account/detail reconciliation and independent review |
| Multiple plausible ownership candidates | Current count unknown; prior project-6 numeric dual-header count was zero | Retain competing JV/CV/document hypotheses; absence of numeric collision does not prove uniqueness |
| Missing evidence/references | Prior project-6 scan reports ten missing numeric headers; current references unassessed | Original source documents, legacy deletion/migration history and authoritative external references |
| Contradictory evidence | No new contradictions established | Compare authenticated claims, project ownership, source details and original records; candidate number disagreement alone is not a proved contradiction |
| Requires human financial judgment | All reported 2873 remain unresolved pending current evidence | Review completeness and independent signoff; do not bulk infer owners |

## Tooling investigation and changes

Existing packet/worksheet/checkpoint/portfolio/coverage/conflict/release tools were inspected. Packets contain project-scoped numeric JV/CV header candidates and original voucher numbers. Worksheet fingerprints bind those packet fields; they do **not** cover amounts, detail lines or external document authenticity. The checkpoint checks declared reviewers and hashes, not authenticated signatures. `source_verified=false` must remain authoritative for these tools.

Added `--evidence-summary` to the existing `finance:audit-legacy-cases` command. It reuses the collision inventory's project-scoped query and existing `LegacyVoucherEvidencePacket::inspectBatch` reader, in batches of at most 100. No alternative ownership resolver, posting path or schema was introduced. The existing per-case packet shape and worksheet fingerprints are unchanged.

The new summary reports mutually exclusive categories, with precedence in this order:

| Category | Exact meaning | Interpretation |
|---|---|---|
| `ambiguous_candidates` | Both same-numeric-ID JV and CV headers exist in the project | Competing candidates; neither is proved |
| `missing_candidate_header` | Neither same-ID header exists in the project | Source lookup incomplete, not proof of orphaned finance |
| `missing_voucher_reference` | Exactly one candidate, but ledger or candidate voucher number is blank | Missing comparison evidence |
| `candidate_reference_disagreement` | Exactly one candidate; both references present but not exactly equal | Candidate mismatch only; series/format differences may explain it |
| `matching_reference_requires_manual_review` | Exactly one candidate with equal nonblank references | Corroborating hint only; matching IDs/references do not prove source ownership |

Because categories are exclusive, reference gaps/disagreements within ambiguous or absent-header cases are not separately counted. Comparison preserves leading zeros and formatting. It does not search all alternative headers by voucher number, infer from descriptions, compare detail/amounts, or detect all duplicate/orphan patterns. No new report can call these unperformed checks complete.

Output contains counts and at most five example ledger IDs per category, no raw voucher references, names, descriptions or financial amounts. `independent_ownership_evidence_assessed=false`, `independently_verified_ownership_count=null`, `source_verified=false` and `deployment_approved=false` prevent unknown evidence from being misreported as zero or certified. Every scanned row is marked for manual review. Scan cap is 1–10000 (default 5000), `available` remains the full scope, and `incomplete=true` prevents truncated results being mistaken for full coverage. Exit 1 means unresolved rows; exit 0 means no rows in this scope, not deployment approval.

Fixed a confirmed coverage defect: coverage still excluded a ledger whenever any attribution row existed, even if its project/source/evidence fields were invalid. Coverage and all collision review modes now use the same query. Invalid metadata stays in review, and incomplete attribution schema fails closed with a domain error. Coverage fixture tests were updated to represent complete typed metadata; a new partial-schema rejection test preserves fail-closed coverage. No attribution or historical data is corrected by this change.

## Verification and review scope

The interrupted work's six modified PHP files and this untracked report were preserved and reviewed in full. Batch-boundary coverage now verifies 101 rows across two packet batches and a 100-row cap on the same population: available remains 101, scanned/category totals remain 100, and the report is incomplete. Example IDs remain capped at five. Tests also cover empty projects, invalid limits/mode combinations, all five categories, exact reference formatting, CV-only matches, project isolation, aggregate output redaction, read-only SQL, invalid attribution metadata and incomplete metadata schema. Existing packet/review/checkpoint/release tests remain enabled.

No genuine historical ownership is certified by these tests: fixtures are synthetic SQLite memory records. No real-data category counts were newly established. Source/evidence fields that pass the apparent-attribution filter are not authenticated proof, and duplicate/conflicting otherwise-valid metadata is not fully reconciled by that filter. The existing conflict/release workflow and manual review remain required.

The resumed task explicitly forbids MySQL connections. The next operational audit requires a new authorization to resume read-only staging access as well as secure restricted credentials. Nothing is staged or committed.

## Exact next action (blocked on authorization and secure credential availability)

Provide the restricted staging credential through a secure process-scoped handoff, not chat or committed files. Once the authorized staging session is available, independently verify `SELECT DATABASE()` is exactly `bluemarketing_l13_staging`, selected account/host are restricted and expected, and no cached Laravel config or URL override can redirect the connection. Keep the original `.env` untouched. The commands below are **not executed** and must not be run using default `bluenew` settings.

1. Execute existing `finance:mysql-staging-preflight --ack-staging --expected-host=127.0.0.1 --expected-database=bluemarketing_l13_staging --limit=100` with process-scoped staging configuration.
2. Independently recheck the selected database before each finance invocation. For projects **6, 5, 3, 1, 2, 4**, execute `finance:audit-legacy-cases PROJECT --evidence-summary --max-rows=5000` on the same stable staging snapshot. This can issue several reads per ledger because it deliberately reuses the packet reader; no MySQL runtime/performance claim is made. Review scan completeness and reconcile the totals with readiness; do not extrapolate truncated category counts.
3. Retrieve bounded existing evidence packets for selected ledger IDs (start with the ten historical project-6 missing-header cases after confirming they remain in scope). Keep sensitive material in the approved review channel, outside Git/chat. Request authoritative source documents and reconcile detail/account/project/amount evidence before assigning any strong-evidence label.
4. Use the existing worksheet, independent checkpoint, conflict and coverage tools. Obtain authenticated finance review outside these declarative tools. Any future attribution/backfill still requires a separate exact proposal and explicit approval; this task authorizes none.

Other blockers remain: current schema/history comparison, authentic source evidence, detail-level reconciliation, MySQL constraint/concurrency validation on an approved disposable test database, backup/restore verification, role-based UAT and explicit release authorization. No migrations, operational writes, commit, push, merge or deployment in this session.

## Final resumed test results

Focused finance/evidence suites: **113 tests, 412 assertions, exit 0**. Full isolated SQLite suite: **467 tests, 1908 assertions, exit 0**. No warnings. All six changed PHP files pass lint; git diff --check passes. Tests use synthetic fixtures only. No real-row ownership certified. Nothing staged or committed.
