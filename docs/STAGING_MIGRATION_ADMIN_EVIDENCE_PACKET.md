# Disposable MySQL rehearsal administrator evidence packet

This packet is a non-executable checklist. It records evidence required before any disposable MySQL rehearsal provisioning is approved. It does not request passwords, customer records, financial rows, database dumps, or credentials. Documentation claims are not administrator evidence; every control remains **BLOCKED** until the named evidence is supplied and approved.

Target repository commit for this packet: `188f34aec16c8e017103d5bb6aba86debe2d47c4`.

## Evidence register

| Control | Required evidence | Provider / approver | Safe verification | PASS / FAIL | Remaining uncertainty |
|---|---|---|---|---|---|
| Disposable host identity | Host ID, owner, OS, region, purpose, lifecycle and isolation boundary | Infrastructure administrator; release owner approves | Review signed host inventory and compare to approval record | PASS only if host is newly dedicated and lifecycle is recorded; otherwise FAIL | Host ownership and teardown require administrator attestation |
| Network and firewall isolation | Inbound/outbound rules, security-group/firewall export, and blocked routes to staging, `bluenew`, master-live and production | Network administrator; security reviewer approves | Inspect rules and perform non-database route probes from the host | PASS only if only approved rehearsal endpoints are reachable; any broad route is STOP | DNS, VPN and provider routing need separate evidence |
| DNS and routing restrictions | Resolver configuration, hosts file, route table and explicit disposable endpoint mapping | Infrastructure/network administrator | Compare resolved names/IPs and routes against approved inventory without opening a database session | PASS only if no production/staging alias resolves or routes; mismatch is STOP | External DNS changes cannot be guaranteed by repository files |
| MySQL 8.0 instance identity | Version, hostname, port, instance owner, SQL mode, charset/collation and instance isolation | Database administrator; release owner approves | Capture server metadata and compare to the approved disposable host/database | PASS only if MySQL 8.0 and exact host/port match; otherwise FAIL | Instance isolation and other schemas require administrator proof |
| Temporary least-privilege account | Redacted account identifier, host restriction, grant scope and expiration/revocation plan; never the password | Database administrator; security reviewer approves | Review `SHOW GRANTS` output with secrets removed and verify database scope | PASS only for the disposable schema with no global write/admin privileges; otherwise STOP | Grant changes after capture require a new packet |
| Synthetic fixture provenance | Fixture generator/version, source commit, checksum, schema/row-count manifest and statement that no real records were used | Data administrator; privacy/security approver | Review generator artifacts and aggregate-only validation; do not inspect business rows | PASS only if synthetic or formally sanitized and reproducible; otherwise FAIL | Sanitization quality cannot be inferred from row counts alone |
| Separate application worktree | Path, commit/hash, clean status, copied vendor dependencies and exclusion of the current project worktree | Release engineer; migration reviewer approves | Compare worktree hash/status and verify no production `.env` or config cache is present | PASS only for a separately copied, hash-pinned worktree; current worktree is never approved | Filesystem permissions require host evidence |
| Environment and Laravel isolation | Redacted environment allowlist, unset-variable report, absent config cache, explicit disposable DB settings, pre-bootstrap identity probe and reviewed migration allowlist | Release engineer; application reviewer approves | Inspect process launch manifest and pre-bootstrap probe output without exposing secrets | PASS only if bootstrap fails closed on target/account mismatch; otherwise STOP | Runtime code requires sandboxing to restrict arbitrary resources |
| Backup and restore verification | Backup artifact checksum, restore log, independent post-restore checksum, metadata baseline hash and aggregate-only comparison | Database administrator; recovery owner approves | Recompute checksums and compare before/after metadata captures | PASS only if backup and restore are independently reproducible; mismatch is RESTORE | Backup confidentiality and retention need administrator controls |
| Credential revocation and disposal | Revocation timestamp/evidence, disposable database teardown evidence, host cleanup, retained sanitized logs and final route check | Database/infrastructure administrator; security owner signs off | Verify account no longer authenticates and database/host no longer exists through administrative records | PASS only after all temporary resources are removed; otherwise FAIL | Provider retention, snapshots and backups may need destruction evidence |

## Provisioning approval gate

Provisioning is **BLOCKED** by default. Approval requires every row above to have a provider, evidence reference, reviewer, timestamp and PASS result. A missing, stale, contradictory or unverifiable artifact is not a PASS.

The approval record must include the unique disposable database name; exact host, port, MySQL version, account scope and expiration; the 38 migration hashes and repository commit; synthetic fixture and backup/restore checksums; separate worktree hash and config-cache absence; the reviewed migration allowlist; recovery and disposal owners; and explicit exclusion of all unapproved history writes, backfills, repairs, `up()` and `down()` calls.

## Stop conditions

Stop before provisioning or execution on any unexpected database/host/port/account identity, broad grant, route to protected infrastructure, inherited configuration, missing worktree isolation, real-data provenance, hash mismatch, backup/restore mismatch, unreviewed SQL, schema collision, changed aggregate, incomplete output or uncertain cleanup. Preserve sanitized evidence and request review; do not guess or continue.

## Documentation versus evidence

The rehearsal proposal, reconciliation manifest, readiness audit, roadmap and worklog describe intended controls and prior repository observations. They do not establish current host identity, grants, routing, fixture provenance, backup integrity, worktree isolation, credential revocation or disposal. Those facts require the administrator evidence listed above. Until then, no disposable database may be created and production deployment remains **BLOCKED**.
