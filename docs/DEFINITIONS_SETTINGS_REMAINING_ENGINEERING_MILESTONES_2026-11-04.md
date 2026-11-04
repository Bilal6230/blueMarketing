# Definitions & Settings: remaining engineering milestones

Browser acceptance is assigned to the client and is excluded from this engineering plan. Code completion does not imply deployment completion. Each milestone closes only after its focused automated tests pass.

| Order | Milestone | Engineering exit criteria | Status |
|---|---|---|---|
| 1 | DS-07 payroll accounting | An approved immutable run can be linked to real project-scoped payment vouchers, without changing posted rows or allowing duplicate/overpaid links. | In progress |
| 2 | DS-08 voucher settings | January calendar year, project-scoped prefixes and display sequences, custom payment-method master, and bank-account identity checks cover all supported writers. Existing numeric voucher IDs retain their meaning. | Pending |
| 3 | DS-01 to DS-06 integrity closure | Remaining inactive/foreign-ID posting paths, merge concurrency, charge-rate consumers, and definition history are tested and fixed. | Pending |
| 4 | DS-09 inventory closure | Project item master, imports, stock posts, large-file/permission cases and mobile paths pass focused tests. | Pending |
| 5 | DS-10 automated acceptance | Isolated migration rehearsal, row/totals reconciliation, permission and regression gates, and deployment runbook are complete. Live database changes remain a separate deployment action. | Pending |

Progress is tracked in the local audit document after each verified milestone. Current project-stock focused gate: 49 passed / 126 assertions. The older V2 stock workflow fixture still lacks required setup tables and is not counted as passing.
