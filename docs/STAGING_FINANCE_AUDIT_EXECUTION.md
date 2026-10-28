# Staging finance aggregate audit execution

**Production deployment remains BLOCKED.** These steps are read-only and do not certify historical ownership.

## Acceptance rules

Reject the run unless every condition below holds:

- The authenticated account is exactly `blue_l13_audit@127.0.0.1`, the selected database is exactly `bluemarketing_l13_staging`, the server port is 3306, and `@@transaction_isolation` is `READ-COMMITTED` or `REPEATABLE-READ`.
- `SHOW GRANTS` contains only global `USAGE` and staging-scoped `SELECT`, with no active/default role adding privileges. This cannot be proved by the SQL files because the account cannot inspect all role/account metadata.
- Every schema and data-integrity preflight row has `passed=1` and `overall_status=PASS`.
- The aggregate returns exactly six rows in project order 6, 5, 3, 1, 2, 4.
- Every aggregate row has `identity_gate_passed=1`, `project_exists=1`, `integrity_gate_passed=1`, all four arithmetic flags equal to 1, `incomplete=0`, and `result_status=ACCEPTABLE_FOR_MANUAL_REVIEW`.
- For each project, `candidate_jv_type_rows = apparently_attributed_rows + current_unresolved`, `scanned = current_unresolved`, and each of the two category families sums to `scanned`.
- The six `current_unresolved` values are summed once and compared with the historical 2,873 baseline. Category counts are not added to population counts.

`matching_reference_requires_manual_review` is a candidate hint only. `independently_verified_ownership_count` stays `NULL`; `source_verified` and `deployment_approved` stay 0.
`apparently_attributed_rows` means only that metadata matches the committed Laravel inventory predicate. It does not prove the referenced header exists, authenticate the evidence, reconcile amounts/details, or certify ownership.
`same_id_jv_missing_project` counts scanned unresolved ledger rows whose numeric JV header exists but has `project_id IS NULL`. Such a header is deliberately not a project candidate and remains subject to manual evidence review.

## Proposed execution order

Run from `C:\laragon\www\blueMarketing`. Enter the audit password only at each masked prompt. Do not add `--force`, redirect output to a repository file, or put a password in any command argument.

1. Verify identity, server, selected database, and grants:

   ```powershell
   & 'C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe' --no-defaults --protocol=TCP --host=127.0.0.1 --port=3306 --user=blue_l13_audit --database=bluemarketing_l13_staging --password --connect-timeout=5 --batch --raw --execute="SELECT DATABASE(),CURRENT_USER(),USER(),@@hostname,@@port,VERSION(),@@transaction_isolation; SHOW GRANTS;"
   $LASTEXITCODE
   ```

   Stop if the exit code is nonzero, the database/account/port differs, isolation is not `READ-COMMITTED` or `REPEATABLE-READ`, or grants exceed global `USAGE` plus staging-scoped `SELECT`.

2. Run the schema preflight. Stop on any failed row or nonzero exit:

   ```powershell
   & 'C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe' --no-defaults --protocol=TCP --host=127.0.0.1 --port=3306 --user=blue_l13_audit --database=bluemarketing_l13_staging --password --connect-timeout=5 --batch --raw --execute="source C:/laragon/www/blueMarketing/docs/STAGING_FINANCE_SCHEMA_PREFLIGHT.sql"
   $LASTEXITCODE
   ```

3. Only after complete schema PASS, run the data-integrity preflight:

   ```powershell
   & 'C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe' --no-defaults --protocol=TCP --host=127.0.0.1 --port=3306 --user=blue_l13_audit --database=bluemarketing_l13_staging --password --connect-timeout=5 --batch --raw --execute="source C:/laragon/www/blueMarketing/docs/STAGING_FINANCE_DATA_INTEGRITY_PREFLIGHT.sql"
   $LASTEXITCODE
   ```

   Stop if the exit code is nonzero, any row has `passed=0`, or any row reports `overall_status=BLOCKED`.

4. Only after both preflights completely PASS, run the aggregate audit:

   ```powershell
   & 'C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe' --no-defaults --protocol=TCP --host=127.0.0.1 --port=3306 --user=blue_l13_audit --database=bluemarketing_l13_staging --password --connect-timeout=5 --batch --raw --execute="source C:/laragon/www/blueMarketing/docs/STAGING_FINANCE_AGGREGATE_AUDIT.sql"
   $LASTEXITCODE
   ```

   Stop if the exit code is nonzero or any aggregate acceptance condition fails. Do not interpret partial output as a completed audit.

5. Apply all acceptance rules above. Then rerun both preflight commands and require complete PASS results. A different before/after result blocks reconciliation.

## Snapshot limitations

Each SQL file contains one SELECT statement. For InnoDB tables, the aggregate’s table reads belong to that statement’s consistent read. The separate schema preflight, data-integrity preflight and aggregate connections do not share a snapshot, so another authorized session could change data or DDL between them. The aggregate repeats the critical duplicate/orphan gates inside its own statement. Repeating both preflights detects some drift but cannot prove that no transient change occurred.

A cross-statement guarantee requires a separately reviewed strategy, such as an administrator-controlled immutable snapshot/backup, an approved maintenance freeze, or one connection using an explicitly reviewed read-only consistent transaction. `START TRANSACTION`, isolation/session settings, locks, and transaction control are intentionally absent and must not be introduced or executed without separate approval. SELECT-only access also cannot guarantee that other accounts are idle, inspect every effective role on all server versions, prevent concurrent DDL, or prove backup provenance.

The schema preflight reads `information_schema` solely for metadata about `bluemarketing_l13_staging`; missing or unexpected schema returns `BLOCKED` without touching business tables. Only after that PASS does the data-integrity preflight read explicitly qualified staging tables. Duplicate join keys, orphan JV ledger accounts, negative signed legacy join keys, identity, or target yield `BLOCKED`.
