# Project Status and Continuation Notes

**Updated:** 2026-10-26.  
**Repository:** `Bilal6230/blueMarketing`  
**Branch:** `upgrade/laravel-framework`  
**Known dependency lock commit:** `f7634fc` (subsequent commits may supersede it; check Git).

## Verified development progress
- Laravel **13.38.0** locally verified on two Windows/Laragon development machines, though their PHP patch levels differ (one PHP 8.3.33, another PHP 8.3.0).
- `composer validate --no-check-publish`: valid.
- `composer audit --locked`: no advisories at last reported check.
- Automated suite: **225 passed**, previously reported on first machine; do not imply freshly rerun on the second machine.
- `php artisan route:list --except-vendor`: **287 routes** on second machine.
- User reports browser smoke test PASS: CRM lead list/details/search; Booking list/details; Finance ledger/JV/CV/reports (read-only); Labour attendance/reports; Permissions access; Landing Page editor/published content. This is not yet complete integration/security testing.
- On second machine `git status --short` showed `?? vendor.before-laravel13-incomplete/` — a preserved incomplete Composer install backup. Don't commit it.
- Local database reported as `bluenew`; schema checks confirmed `ledgers`, `bookings`, `journal_vouchers`, `labours`, `landing_pages` exist.
- Local migration status lists many historical finance/booking/labour/stock migrations as Pending **despite existing tables**. This must be reconciled before any mass migration.
- Landing migration `2026_10_24_000003_add_landing_builder_fields` was Pending and columns `section_order`, `project_overrides` were absent at last check. User explicitly paused execution; do not assume this migration ran.
- SQL dump supplied: `u429032065_staging_bluema (1).sql` (uploaded in conversation, **not committed**); staging snapshot may differ from `bluenew`.

## Current phase
**Database architecture and full application security audit; READ ONLY.** User explicitly requested a deep inspection before implementation; subsequent approval should be specific to changes. No production merge or deployment authorized.

## Important historical notes
- Laravel 13 and package compatibility work are on upgrade branch; new native server-side Google reCAPTCHA v2 flow replaced old package. Local-only empty reCAPTCHA keys were used for smoke testing; production must retain configured CAPTCHA.
- A previous Windows Composer upgrade hit antivirus/search-indexer file locks; recovery was a clean vendor install. Cached old Debugbar provider caused startup failure until bootstrap caches were dealt with.
- Keep local `.env`, stashes, old Composer backups and DB backups safe.

## Next executable steps (no write operations)
1. Complete column/index/FK comparison of uploaded SQL dump versus **all** migration files; explicitly distinguish imported dump vs actual development DB.
2. Audit controller/repository write, delete, raw SQL and transaction paths for finance, booking, stock, users and logs.
3. Produce schema/data reconciliation matrix with orphan and uniqueness checks as **read-only queries**; sanitize outputs.
4. Agree on fresh-baseline vs existing-database upgrade design, soft delete and financial void/reversal rules.
5. Prototype and test **only in isolated disposable DB** after explicit approval; rehearse data-only import and rollback.
6. Design and implement activity logging and device/OTP security separately, with tests.
7. Production deployment only after separate staging rehearsal, backups, reconciliation and sign-off.

## How to resume in a new chat
Say: “Read `docs/README.md` and linked plans from `upgrade/laravel-framework`; continue the BlueMarketing audit from `docs/AUDIT_WORKLOG.md`. No database writes or production changes.”
