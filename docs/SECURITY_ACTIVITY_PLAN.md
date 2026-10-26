# Activity Audit, Trusted Devices and Adaptive OTP

**Status:** requirements/design only. **Updated:** 2026-10-26.

## Objectives
- Admin can investigate **who** logged in, when, success/failure, session/device context and approximate IP-derived location.
- For every protected create/update/soft-delete/approve/void action record actor, timestamp, module, table, record PK, request/trace ID, origin device/session/IP and old/new allowed fields.
- Unknown device / risky IP / material location anomaly initiates secure OTP challenge **before granting full session**.
- Enforce access controls for reading audit data; logging must be tamper-resistant and safe for confidential fields.

## Proposed logical tables (not implemented)
- `user_devices`: user, opaque per-device random token identifier/hash, display info (browser/OS), first/last seen, trust/revocation status.
- `login_attempts`: principal identifier appropriately minimized, outcome, reason, IP, timestamp, coarse risk signal.
- `login_challenges`: hashed OTP or challenge token, expiry, attempts, purpose, verified/consumed timestamp, delivery channel; rate-limited single-use.
- `user_sessions`: session identifier **hash/reference, not raw secret**, user, device, IP snapshot, last activity, revocation.
- `activity_logs`: append-only event ID, actor, action, record type/id, date/time, request correlation, IP/device/session, outcome.
- `activity_log_changes`: allowlisted, redacted field-level before/after values, or encrypted structured diff.
- `security_events`: change in device/IP, OTP failures, trust/revoke/security administration events.

These are conceptual tables. Exact columns, FK actions, indexes and retention must be agreed after schema review.

## Architecture safeguards
- Login verification: credentials and rate limits, existing reCAPTCHA when configured, risk calculation, OTP for risky/new device, then establish authenticated trusted session.
- Approximate GeoIP is NOT verified GPS location. IP is shared/dynamic/VPN-sensitive; location changes alone are weak indicators. Do not collect precise GPS without separate legal/privacy review and user consent.
- Browser fingerprinting is not reliable proof of hardware identity. Use rotating server-issued signed/high-entropy device identifiers, protected cookies and explicit device management/revocation.
- Prefer TOTP authenticator and recovery codes where feasible; email OTP may be fallback, avoid insecure SMS-only reliance. Never store codes, tokens or passwords in audit logs.
- Log even denied operations and authentication failures without leaking submitted credentials, CNIC, secrets or full request bodies.
- Use centralized middleware + model observers/events and explicit domain events for bulk SQL, approvals, voucher reversal and actions that bypass Eloquent events. Add transaction-aware/outbox strategy so high-value audit events are not silently lost.
- Enforce authorization for admin audit screens, redact sensitive fields, prevent audit log alteration by normal admins, choose retention/access/deletion policy and backup coverage.
- Preserve time in UTC + time-zone-aware display; device/location snapshots distinguish observation from verified identity.

## Planned test scenarios
- Normal/unknown device, changing IP, invalid/expired/replayed OTP, brute-force attempts, session revocation, logout, MFA recovery.
- Create/update/soft-delete/restore/approve/void, bulk updates, failed/rejected actions, impersonation if applicable, API requests.
- Verify actor + before/after diff + device/IP + request ID; ensure redaction and restricted audit access.
- Performance and transactional correctness under simultaneous financial activity.

**No OTP/audit implementation or configuration changes have been made yet.**
