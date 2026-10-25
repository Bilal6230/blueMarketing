# Laravel 13 upgrade audit (2026-10-25)

Target branch: `upgrade/laravel-framework` only. Do not merge into `master_live` or deploy until dependencies, application tests, financial flows and authentication are verified.

## Current evidence
- Local PHP: 8.3.33; local Laravel: 9.52.20. Current branch `composer.json` targets Laravel `^10.0`; `composer.lock` still targets 9.52.20.
- `minimum-stability` is `stable`. Composer's security audit blocks all Laravel 10 releases because they have known advisories. **Never disable Composer security blocking to force Laravel 10.**
- Target Laravel 13 release must be selected from non-vulnerable stable versions and verified with `composer audit`.
- `config/recaptcha.php` specifies Google reCAPTCHA v2, the `RECAPTCHA_SITE_KEY` and `RECAPTCHA_SECRET_KEY` environment values, and the existing validation route. The `biscolab/laravel-recaptcha` package is abandoned. Do not remove it without finding **all** render/validation usage and preserving server-side verification.
- `routes/web.php` disables public registration; `LoginController` authenticates a user using email/password plus selected project. Login, mobile/Sanctum APIs and session continuity require regression tests.
- `config/modules.php` explicitly lists Nwidart module-generator commands and paths; verify against Nwidart Modules 13 **before** changing them.
- Official Yajra Laravel DataTables 13 documentation specifies `yajra/laravel-datatables-oracle:^13.0` for Laravel 13 and PHP 8.3+.
- Official Laravel 13 upgrade guide specifies `laravel/framework:^13.0`, `laravel/tinker:^3.0`, `phpunit/phpunit:^12.0`. Check all remaining direct dependencies and runtime PHP extensions before editing to a coherent Laravel 13 set.

## Required gates before installing or deploying
1. Find all uses of reCAPTCHA in Controllers, Modules, views, JS, routes, service providers and middleware. Choose a compatible maintained replacement and test both valid and invalid tokens.
2. Establish compatible stable versions for Sanctum, UI, Nwidart Modules, permission, file manager, generator, alerts, DataTables, debugbar, collision and PHPUnit. Keep production dependencies under `require`, tooling under `require-dev`.
3. Solve dependencies with `composer update --dry-run --no-scripts --no-plugins -W`; no `*-dev` packages and no ignored advisories. Then commit a newly generated `composer.lock` with corresponding `composer.json` changes.
4. Execute Composer installation on an isolated test copy (not live hosting), lint files, list routes, run unit/feature tests, and validate authentication and permissions.
5. Validate booking, accounting vouchers/ledgers, payroll/attendance, CRM, file uploads and module loading against non-production data. **No production migrations** without backup and a reviewed deployment plan.
6. Only then prepare a reviewable PR from the *existing* upgrade branch into `master_live`.

Sources:
- https://github.com/laravel/docs/blob/13.x/upgrade.md
- https://yajrabox.com/docs/laravel-datatables/13.0
- https://yajrabox.com/docs/laravel-datatables/master/upgrade
