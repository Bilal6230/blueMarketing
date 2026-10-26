# Phase 1Z — Finance voucher safety and route authorization consolidation

Date: 2026-10-26. Source review + isolated tests committed on `upgrade/laravel-framework`. No financial data mutation.

## Completed and observed

- Phase 1Y CV index AJAX test finally **PASS: 1 test / 4 assertions**, after minimal SQLite roles, users and permissions fixtures. Result reported by user following pull of `6d0e047`.
- Existing end-to-end HTTP route gate test covered only temporary 409 behavior with middleware turned **off**, and separate middleware-declaration assertions. This was not a guest authentication test.
- New `VoucherGuestAccessIntegrationTest.php` exercises **14 real named URLs** (7 JV and 7 CV) through Laravel HTTP layer with authentication middleware enabled, checking guest denial and route auth/permission declarations, plus the existing five blocked write actions after explicitly bypassing middleware for safety-gate isolation. It deliberately leaves active `journal.voucher.store` out of the 409 expected list.
- **This test is not yet locally executed.** Its outcomes must not be described as passed until confirmed in user's local PHP test output.

## JV store active — observed source gaps, not yet a fix

`JournalVoucherController::store` (lines around 326–401):
1. Accepts user-supplied arrays with `required|array|min:1`, but **no same-length validation** across accounts, sub_accounts, debit and credit in the current shown validator. Loop iterates `accounts`, reading optional corresponding entries in the other arrays.
2. Uses native `array_sum` and strict `!==` to balance debit and credit. No explicit decimal scale or nonnegative numeric item constraints in shown validation. Could accept amounts that should fail accounting validations; risk requires focused tests.
3. Uses `getLastJvVNumber() + 1` without an explicit row lock or atomic number allocator. Concurrent submissions may collide; concurrency claim remains untested.
4. `DB::beginTransaction()` and `DB::commit()` are present, but local exception catch and `DB::rollBack()` are commented out. Laravel transaction cleanup on request failure should not be assumed from this source; use `DB::transaction(fn()=>...)` in approved remediation.
5. No explicit idempotency key or committed voucher source/batch token. Network retries could duplicate financial postings. Not proven by test.
6. `JournalVoucherDetail::create` and `Ledger::create` use submitted subaccount ID without shown project scoping checks; validate tenant/project ownership before posting.
7. CV store/update/delete and JV update/delete remain temporarily HTTP 409 blocked; full reversal and typed posting not implemented.

## Required safe delivery plan, not implemented

- Add read-only validation tests for malformed arrays, negative amounts, nonnumeric amounts, empty/missing project, cross-project subaccounts, imbalance, and controlled failed transactions. Database must be SQLite :memory: only.
- Design exact decimal currency arithmetic, per-project serial allocation with uniqueness and locking, and explicit idempotency.
- Introduce an atomic single voucher posting service with typed provenance, balanced ledger rows, and reversible accounting trail. Any schema migrations require user approval; never silently modify production data.
- Preserve old financial reports and reconcile with approved historical sources before any JV/CV ledger remapping.
- Test users with/without action permissions (Spatie SQLite fixture), not only guest users; this is separate from current added regression.
- No rewriting of historical rows and no deployment to `master_live`.

## Local verification batch

```powershell
git pull --ff-only origin upgrade/laravel-framework
php -l tests/Feature/Finance/VoucherGuestAccessIntegrationTest.php
php artisan test --filter=VoucherGuestAccessIntegrationTest
php artisan test --filter="CommissionVoucherListingResponseTest|CommissionVoucherNumberingBaselineTest|VoucherSafetyHttpRouteTest|UnsafeVoucherActionGateTest"
```

Known deliberately RED source diagnostics and ambiguous ledger display tests will still prevent the entire suite from becoming green, independently of this new security test.
