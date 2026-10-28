# Disposable migration reconciliation rehearsal (proposal only)

This document is a review plan, not an executable script. It must not be copied into a production or staging shell. No database is provisioned by this proposal.

## Preconditions and approvals

1. Obtain written approval naming a disposable host, database, owner, retention period and reviewer. The database must be a new throwaway schema isolated from `bluemarketing_l13_staging`, `bluenew`, production, and all financial systems.
2. Record the exact repository commit, migration-file SHA-256 manifest, server version, SQL mode, character set/collation, and Laravel/PHP versions.
3. Restore only an approved non-production snapshot containing synthetic or sanitized data. Do not use a live customer or financial export unless separately approved and redacted.
4. Use a temporary least-privilege account. Verify `DATABASE()`, `CURRENT_USER()`, `USER()`, `@@hostname`, `@@port`, grants, and the disposable database name before any migration command. Abort on any mismatch or write scope beyond the throwaway schema.

## Evidence and execution gates

- Capture a schema/history baseline before any operation, including tables, columns, indexes, foreign keys, checks, row counts and migration rows; store only approved metadata and aggregate counts.
- Verify backup checksum, restore completion, and a second independent metadata capture before rehearsal.
- Run no command until the baseline, identity gate, account grants, and exact file hashes are reviewed. Never replay the 35 CREATE candidates against a schema containing their tables.
- Test only an explicitly approved forward-only remediation or history-only simulation on the throwaway database. History-only simulation must not run `up()` or `down()` and must not insert application records.
- Capture exit codes, errors, lock duration, schema diff, migration-history diff, and aggregate row-count diff. A failed gate, unexpected object, changed count, or incomplete output is `BLOCKED`, not a successful rehearsal.

## Recovery and disposal

Stop immediately on any unexpected write, data loss, constraint change, cross-database access, or account/grant mismatch. Restore the disposable database from the verified baseline, recapture metadata, compare checksums and aggregates, and obtain reviewer sign-off that recovery is complete. Do not attempt recovery against staging or production.

After review, destroy the throwaway database and temporary credentials through the authorized administrator, record the destruction evidence, and retain only sanitized logs and metadata. A successful rehearsal does not authorize staging execution, migration-history insertion, financial remediation, or deployment.

## Required deliverables before any future approval

The package must contain the identity/grants capture, baseline and post-run metadata, backup and restore checksums, exact migration hashes, command transcript without passwords or business rows, schema/history diffs, recovery evidence, reviewer and approver, and an explicit disposition for every affected migration. Missing evidence leaves the operation blocked.
