# Employee mobile API security and implementation audit

Date: 2026-11-02
Base branch: upgrade/laravel-framework
Implementation branch: feature/employee-mobile-foundation

## Inventory

43 protected/versioned mobile routes including login (3 auth, 1 project, 1 dashboard, 8 CRM, 8 attendance, 9 stock, 7 reports, 6 approvals). The login route is public but throttled; the remaining 42 are Sanctum-protected. Legacy endpoints: 7 plus GET /api/user and GET /api/test. V2 finance pilot is conditionally registered in local/testing only.

## Findings

1. CRITICAL: Legacy POST /api/project/booking/list and /api/project/booking/detail have no route-level authentication or project authorization, and return customer/booking data. Do not expose to mobile app; assess and harden separately with backward-compatibility testing.
2. HIGH: An account disabled after login previously retained access via existing Sanctum token. Dedicated mobile protected routes now check active user per request with EnsureActiveMobileUser.
3. HIGH: MobileReportsController::inventory documents global stock balances/movements; project_id is access context only. Confirm whether stock is global or project-scoped before exposing cross-project information.
4. MEDIUM: Staff attendance accepts latitude/longitude without type/range validation and does not persist them. Do not claim location verification.
5. MEDIUM: ResolvesMobileProjectAccess allows only a single project for standard users; TODO mentions multi-project assignments.
6. MEDIUM: MobileDashboardController supplies CRM stats only; attendance, stock, reports and approvals are null placeholders.
7. MEDIUM: MobileAuthController login checks inactive accounts but does not use uniform device token abilities or per-device revocation. Existing legacy auth differs in response and token lifecycle.
8. REVIEW: Finance approvals and stock writes must be independently verified against existing V2 workflow gates; mobile frontend permissions are not an authorization boundary.
9. REVIEW: Role and permission labels in Flutter navigation are provisional; validate against server controller checks, especially stock/labour/approval.
10. REVIEW: Versioned API routes are present; runtime, migrations, role fixtures, and E2E tests have not been executed in this GitHub-only implementation.

## Implemented in this branch

- EnsureActiveMobileUser middleware on all authenticated /api/v1/mobile routes.
- Flutter starter under mobile/employee_app with login, secure token storage, session recovery, logout, initial permission-filtered navigation and unit tests.

## Release gates (not yet passed)

- Static PHP syntax and Laravel route:list verification.
- Flutter SDK generation of android platform scaffolding, flutter analyze and flutter test.
- Authentication, revoked-token, disabled-user, cross-project 403, unauthorized module 403 and masked PII integration tests.
- Permission names aligned to each controller, finance write workflow regression tests, stock isolation decision and hardened legacy booking routes.
- HTTPS production config, mobile signing, monitoring and deployment approval.

## Setup (PowerShell)

```powershell
cd C:\laragon\www\blueMarketing
git fetch origin
git switch feature/employee-mobile-foundation
git pull --ff-only origin feature/employee-mobile-foundation
cd mobile\employee_app
flutter create --platforms=android .
flutter pub get
flutter test
flutter run --dart-define=API_BASE_URL=https://YOUR-HTTPS-DOMAIN
```

Keep the existing Laravel deployment branch unchanged until reviewed and merged.
