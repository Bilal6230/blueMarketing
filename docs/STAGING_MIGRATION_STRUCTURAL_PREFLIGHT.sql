/*
BlueMarketing legacy migration structural preflight.

Safety contract:
- SELECT and CTE statements only.
- Reads connection identity and information_schema metadata only.
- Never reads business-table rows or values.
- Exact target schema: bluemarketing_l13_staging.
- A consumer MUST reject all later output unless identity_gate_passed = 1.
- Missing scope rows and absent metadata are evidence, never an implicit match.
*/

SELECT
    DATABASE() AS selected_database,
    CURRENT_USER() AS authenticated_account,
    USER() AS connection_account,
    @@port AS server_port,
    @@transaction_isolation AS transaction_isolation,
    (
        DATABASE() = 'bluemarketing_l13_staging'
        AND CURRENT_USER() = 'blue_l13_audit@127.0.0.1'
        AND @@port = 3306
    ) AS identity_gate_passed;

/* Result set 2: every collision target, missing target, comparison table, and dependency. */
WITH scope_tables (table_name, scope_role) AS (
    SELECT 'project_head_subheads', 'missing_history_create' UNION ALL
    SELECT 'lead_user', 'missing_history_create' UNION ALL
    SELECT 'works', 'missing_history_create' UNION ALL
    SELECT 'expences', 'missing_history_create_absent' UNION ALL
    SELECT 'accounting_expenses', 'legacy_comparison' UNION ALL
    SELECT 'ledgers', 'missing_history_create' UNION ALL
    SELECT 'bookings', 'missing_history_create' UNION ALL
    SELECT 'installments', 'missing_history_create' UNION ALL
    SELECT 'booking_details', 'missing_history_create' UNION ALL
    SELECT 'customer_ledger', 'missing_history_create' UNION ALL
    SELECT 'journal_vouchers', 'missing_history_create' UNION ALL
    SELECT 'journal_voucher_details', 'missing_history_create' UNION ALL
    SELECT 'dasticash', 'missing_history_create' UNION ALL
    SELECT 'draft_ledgers', 'missing_history_create' UNION ALL
    SELECT 'labours', 'missing_history_create' UNION ALL
    SELECT 'stocks', 'missing_history_create' UNION ALL
    SELECT 'hold_plots', 'missing_history_create' UNION ALL
    SELECT 'charge_types', 'missing_history_create' UNION ALL
    SELECT 'pending_updates', 'missing_history_create' UNION ALL
    SELECT 'commision_vouchers', 'missing_history_create' UNION ALL
    SELECT 'commision_voucher_details', 'missing_history_create' UNION ALL
    SELECT 'sites', 'missing_history_create' UNION ALL
    SELECT 'labour_attendances', 'missing_history_create' UNION ALL
    SELECT 'labour_ledgers', 'missing_history_create' UNION ALL
    SELECT 'account_types', 'missing_history_create' UNION ALL
    SELECT 'stock_items', 'missing_history_create' UNION ALL
    SELECT 'stock_parties', 'missing_history_create' UNION ALL
    SELECT 'stock_entries', 'missing_history_create' UNION ALL
    SELECT 'stock_entry_lines', 'missing_history_create' UNION ALL
    SELECT 'stock_bills', 'missing_history_create' UNION ALL
    SELECT 'stock_bill_lines', 'missing_history_create' UNION ALL
    SELECT 'stock_movements', 'missing_history_create' UNION ALL
    SELECT 'stock_item_balances', 'missing_history_create' UNION ALL
    SELECT 'stock_counters', 'missing_history_create' UNION ALL
    SELECT 'booking_vouchers', 'missing_history_create' UNION ALL
    SELECT 'booking_edit_audits', 'missing_history_create' UNION ALL
    SELECT 'users', 'foreign_key_dependency' UNION ALL
    SELECT 'leads', 'foreign_key_dependency' UNION ALL
    SELECT 'projects', 'foreign_key_dependency' UNION ALL
    SELECT 'plots', 'foreign_key_dependency' UNION ALL
    SELECT 'head_accountings', 'application_dependency' UNION ALL
    SELECT 'subhead_accountings', 'application_dependency'
)
SELECT
    s.scope_role,
    s.table_name,
    (t.TABLE_NAME IS NOT NULL) AS table_present,
    t.TABLE_TYPE,
    t.ENGINE,
    t.TABLE_COLLATION
FROM scope_tables AS s
LEFT JOIN information_schema.TABLES AS t
    ON t.TABLE_SCHEMA = 'bluemarketing_l13_staging'
   AND t.TABLE_NAME = s.table_name
WHERE DATABASE() = 'bluemarketing_l13_staging'
  AND CURRENT_USER() = 'blue_l13_audit@127.0.0.1'
  AND @@port = 3306
ORDER BY s.scope_role, s.table_name;

/* Result set 3: column definitions only; no table rows. */
WITH scope_tables (table_name) AS (
    SELECT 'project_head_subheads' UNION ALL SELECT 'lead_user' UNION ALL
    SELECT 'works' UNION ALL SELECT 'expences' UNION ALL
    SELECT 'accounting_expenses' UNION ALL SELECT 'ledgers' UNION ALL
    SELECT 'bookings' UNION ALL SELECT 'installments' UNION ALL
    SELECT 'booking_details' UNION ALL SELECT 'customer_ledger' UNION ALL
    SELECT 'journal_vouchers' UNION ALL SELECT 'journal_voucher_details' UNION ALL
    SELECT 'dasticash' UNION ALL SELECT 'draft_ledgers' UNION ALL
    SELECT 'labours' UNION ALL SELECT 'stocks' UNION ALL
    SELECT 'hold_plots' UNION ALL SELECT 'charge_types' UNION ALL
    SELECT 'pending_updates' UNION ALL SELECT 'commision_vouchers' UNION ALL
    SELECT 'commision_voucher_details' UNION ALL SELECT 'sites' UNION ALL
    SELECT 'labour_attendances' UNION ALL SELECT 'labour_ledgers' UNION ALL
    SELECT 'account_types' UNION ALL SELECT 'stock_items' UNION ALL
    SELECT 'stock_parties' UNION ALL SELECT 'stock_entries' UNION ALL
    SELECT 'stock_entry_lines' UNION ALL SELECT 'stock_bills' UNION ALL
    SELECT 'stock_bill_lines' UNION ALL SELECT 'stock_movements' UNION ALL
    SELECT 'stock_item_balances' UNION ALL SELECT 'stock_counters' UNION ALL
    SELECT 'booking_vouchers' UNION ALL SELECT 'booking_edit_audits' UNION ALL
    SELECT 'users' UNION ALL SELECT 'leads' UNION ALL SELECT 'projects' UNION ALL
    SELECT 'plots' UNION ALL SELECT 'head_accountings' UNION ALL
    SELECT 'subhead_accountings'
)
SELECT
    c.TABLE_NAME,
    c.ORDINAL_POSITION,
    c.COLUMN_NAME,
    c.COLUMN_TYPE,
    c.IS_NULLABLE,
    c.COLUMN_DEFAULT,
    c.EXTRA,
    c.COLLATION_NAME,
    c.GENERATION_EXPRESSION
FROM information_schema.COLUMNS AS c
INNER JOIN scope_tables AS s ON s.table_name = c.TABLE_NAME
WHERE c.TABLE_SCHEMA = 'bluemarketing_l13_staging'
  AND DATABASE() = 'bluemarketing_l13_staging'
  AND CURRENT_USER() = 'blue_l13_audit@127.0.0.1'
  AND @@port = 3306
ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION;

/* Result set 4: primary, unique, and secondary indexes. */
WITH scope_tables (table_name) AS (
    SELECT 'project_head_subheads' UNION ALL SELECT 'lead_user' UNION ALL SELECT 'works' UNION ALL
    SELECT 'expences' UNION ALL SELECT 'accounting_expenses' UNION ALL SELECT 'ledgers' UNION ALL
    SELECT 'bookings' UNION ALL SELECT 'installments' UNION ALL SELECT 'booking_details' UNION ALL
    SELECT 'customer_ledger' UNION ALL SELECT 'journal_vouchers' UNION ALL SELECT 'journal_voucher_details' UNION ALL
    SELECT 'dasticash' UNION ALL SELECT 'draft_ledgers' UNION ALL SELECT 'labours' UNION ALL SELECT 'stocks' UNION ALL
    SELECT 'hold_plots' UNION ALL SELECT 'charge_types' UNION ALL SELECT 'pending_updates' UNION ALL
    SELECT 'commision_vouchers' UNION ALL SELECT 'commision_voucher_details' UNION ALL SELECT 'sites' UNION ALL
    SELECT 'labour_attendances' UNION ALL SELECT 'labour_ledgers' UNION ALL SELECT 'account_types' UNION ALL
    SELECT 'stock_items' UNION ALL SELECT 'stock_parties' UNION ALL SELECT 'stock_entries' UNION ALL
    SELECT 'stock_entry_lines' UNION ALL SELECT 'stock_bills' UNION ALL SELECT 'stock_bill_lines' UNION ALL
    SELECT 'stock_movements' UNION ALL SELECT 'stock_item_balances' UNION ALL SELECT 'stock_counters' UNION ALL
    SELECT 'booking_vouchers' UNION ALL SELECT 'booking_edit_audits' UNION ALL SELECT 'users' UNION ALL
    SELECT 'leads' UNION ALL SELECT 'projects' UNION ALL SELECT 'plots' UNION ALL
    SELECT 'head_accountings' UNION ALL SELECT 'subhead_accountings'
)
SELECT
    i.TABLE_NAME,
    i.INDEX_NAME,
    i.NON_UNIQUE,
    i.SEQ_IN_INDEX,
    i.COLUMN_NAME,
    i.SUB_PART,
    i.COLLATION,
    i.INDEX_TYPE
FROM information_schema.STATISTICS AS i
INNER JOIN scope_tables AS s ON s.table_name = i.TABLE_NAME
WHERE i.TABLE_SCHEMA = 'bluemarketing_l13_staging'
  AND DATABASE() = 'bluemarketing_l13_staging'
  AND CURRENT_USER() = 'blue_l13_audit@127.0.0.1'
  AND @@port = 3306
ORDER BY i.TABLE_NAME, i.INDEX_NAME, i.SEQ_IN_INDEX;

/* Result set 5: foreign-key columns and referential actions. */
WITH scope_tables (table_name) AS (
    SELECT 'project_head_subheads' UNION ALL SELECT 'lead_user' UNION ALL SELECT 'works' UNION ALL
    SELECT 'expences' UNION ALL SELECT 'accounting_expenses' UNION ALL SELECT 'ledgers' UNION ALL
    SELECT 'bookings' UNION ALL SELECT 'installments' UNION ALL SELECT 'booking_details' UNION ALL
    SELECT 'customer_ledger' UNION ALL SELECT 'journal_vouchers' UNION ALL SELECT 'journal_voucher_details' UNION ALL
    SELECT 'dasticash' UNION ALL SELECT 'draft_ledgers' UNION ALL SELECT 'labours' UNION ALL SELECT 'stocks' UNION ALL
    SELECT 'hold_plots' UNION ALL SELECT 'charge_types' UNION ALL SELECT 'pending_updates' UNION ALL
    SELECT 'commision_vouchers' UNION ALL SELECT 'commision_voucher_details' UNION ALL SELECT 'sites' UNION ALL
    SELECT 'labour_attendances' UNION ALL SELECT 'labour_ledgers' UNION ALL SELECT 'account_types' UNION ALL
    SELECT 'stock_items' UNION ALL SELECT 'stock_parties' UNION ALL SELECT 'stock_entries' UNION ALL
    SELECT 'stock_entry_lines' UNION ALL SELECT 'stock_bills' UNION ALL SELECT 'stock_bill_lines' UNION ALL
    SELECT 'stock_movements' UNION ALL SELECT 'stock_item_balances' UNION ALL SELECT 'stock_counters' UNION ALL
    SELECT 'booking_vouchers' UNION ALL SELECT 'booking_edit_audits'
)
SELECT
    k.TABLE_NAME,
    k.CONSTRAINT_NAME,
    k.ORDINAL_POSITION,
    k.COLUMN_NAME,
    k.REFERENCED_TABLE_NAME,
    k.REFERENCED_COLUMN_NAME,
    r.UPDATE_RULE,
    r.DELETE_RULE
FROM information_schema.KEY_COLUMN_USAGE AS k
INNER JOIN scope_tables AS s ON s.table_name = k.TABLE_NAME
INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS AS r
    ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
   AND r.TABLE_NAME = k.TABLE_NAME
   AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
WHERE k.CONSTRAINT_SCHEMA = 'bluemarketing_l13_staging'
  AND k.REFERENCED_TABLE_NAME IS NOT NULL
  AND DATABASE() = 'bluemarketing_l13_staging'
  AND CURRENT_USER() = 'blue_l13_audit@127.0.0.1'
  AND @@port = 3306
ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION;

/* Result set 6: CHECK constraints, including MySQL-generated enum-independent checks if present. */
WITH scope_tables (table_name) AS (
    SELECT 'project_head_subheads' UNION ALL SELECT 'lead_user' UNION ALL SELECT 'works' UNION ALL
    SELECT 'expences' UNION ALL SELECT 'accounting_expenses' UNION ALL SELECT 'ledgers' UNION ALL
    SELECT 'bookings' UNION ALL SELECT 'installments' UNION ALL SELECT 'booking_details' UNION ALL
    SELECT 'customer_ledger' UNION ALL SELECT 'journal_vouchers' UNION ALL SELECT 'journal_voucher_details' UNION ALL
    SELECT 'dasticash' UNION ALL SELECT 'draft_ledgers' UNION ALL SELECT 'labours' UNION ALL SELECT 'stocks' UNION ALL
    SELECT 'hold_plots' UNION ALL SELECT 'charge_types' UNION ALL SELECT 'pending_updates' UNION ALL
    SELECT 'commision_vouchers' UNION ALL SELECT 'commision_voucher_details' UNION ALL SELECT 'sites' UNION ALL
    SELECT 'labour_attendances' UNION ALL SELECT 'labour_ledgers' UNION ALL SELECT 'account_types' UNION ALL
    SELECT 'stock_items' UNION ALL SELECT 'stock_parties' UNION ALL SELECT 'stock_entries' UNION ALL
    SELECT 'stock_entry_lines' UNION ALL SELECT 'stock_bills' UNION ALL SELECT 'stock_bill_lines' UNION ALL
    SELECT 'stock_movements' UNION ALL SELECT 'stock_item_balances' UNION ALL SELECT 'stock_counters' UNION ALL
    SELECT 'booking_vouchers' UNION ALL SELECT 'booking_edit_audits'
)
SELECT
    tc.TABLE_NAME,
    tc.CONSTRAINT_NAME,
    cc.CHECK_CLAUSE
FROM information_schema.TABLE_CONSTRAINTS AS tc
INNER JOIN scope_tables AS s ON s.table_name = tc.TABLE_NAME
INNER JOIN information_schema.CHECK_CONSTRAINTS AS cc
    ON cc.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA
   AND cc.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
WHERE tc.CONSTRAINT_SCHEMA = 'bluemarketing_l13_staging'
  AND tc.CONSTRAINT_TYPE = 'CHECK'
  AND DATABASE() = 'bluemarketing_l13_staging'
  AND CURRENT_USER() = 'blue_l13_audit@127.0.0.1'
  AND @@port = 3306
ORDER BY tc.TABLE_NAME, tc.CONSTRAINT_NAME;

/* Result set 7: fail-visible scope coverage. Expected scope_table_count = 42. */
WITH scope_tables (table_name) AS (
    SELECT 'project_head_subheads' UNION ALL SELECT 'lead_user' UNION ALL SELECT 'works' UNION ALL
    SELECT 'expences' UNION ALL SELECT 'accounting_expenses' UNION ALL SELECT 'ledgers' UNION ALL
    SELECT 'bookings' UNION ALL SELECT 'installments' UNION ALL SELECT 'booking_details' UNION ALL
    SELECT 'customer_ledger' UNION ALL SELECT 'journal_vouchers' UNION ALL SELECT 'journal_voucher_details' UNION ALL
    SELECT 'dasticash' UNION ALL SELECT 'draft_ledgers' UNION ALL SELECT 'labours' UNION ALL SELECT 'stocks' UNION ALL
    SELECT 'hold_plots' UNION ALL SELECT 'charge_types' UNION ALL SELECT 'pending_updates' UNION ALL
    SELECT 'commision_vouchers' UNION ALL SELECT 'commision_voucher_details' UNION ALL SELECT 'sites' UNION ALL
    SELECT 'labour_attendances' UNION ALL SELECT 'labour_ledgers' UNION ALL SELECT 'account_types' UNION ALL
    SELECT 'stock_items' UNION ALL SELECT 'stock_parties' UNION ALL SELECT 'stock_entries' UNION ALL
    SELECT 'stock_entry_lines' UNION ALL SELECT 'stock_bills' UNION ALL SELECT 'stock_bill_lines' UNION ALL
    SELECT 'stock_movements' UNION ALL SELECT 'stock_item_balances' UNION ALL SELECT 'stock_counters' UNION ALL
    SELECT 'booking_vouchers' UNION ALL SELECT 'booking_edit_audits' UNION ALL SELECT 'users' UNION ALL
    SELECT 'leads' UNION ALL SELECT 'projects' UNION ALL SELECT 'plots' UNION ALL
    SELECT 'head_accountings' UNION ALL SELECT 'subhead_accountings'
)
SELECT
    COUNT(*) AS scope_table_count,
    SUM(t.TABLE_NAME IS NOT NULL) AS present_table_count,
    SUM(t.TABLE_NAME IS NULL) AS absent_table_count,
    (COUNT(*) = 42) AS scope_definition_complete,
    (SUM(s.table_name = 'expences' AND t.TABLE_NAME IS NOT NULL) = 0) AS expences_expected_absent,
    (SUM(s.table_name <> 'expences' AND t.TABLE_NAME IS NULL) = 0) AS required_scope_tables_present,
    (DATABASE() = 'bluemarketing_l13_staging'
     AND CURRENT_USER() = 'blue_l13_audit@127.0.0.1'
     AND @@port = 3306) AS identity_gate_passed,
    CASE
        WHEN NOT (
            DATABASE() = 'bluemarketing_l13_staging'
            AND CURRENT_USER() = 'blue_l13_audit@127.0.0.1'
            AND @@port = 3306
        ) THEN 'BLOCKED_IDENTITY'
        WHEN COUNT(*) <> 42 THEN 'BLOCKED_SCOPE_DEFINITION'
        WHEN SUM(s.table_name = 'expences' AND t.TABLE_NAME IS NOT NULL) <> 0
            THEN 'BLOCKED_UNEXPECTED_EXPENCES_PRESENT'
        WHEN SUM(s.table_name <> 'expences' AND t.TABLE_NAME IS NULL) <> 0
            THEN 'BLOCKED_MISSING_SCOPE_METADATA'
        ELSE 'REVIEW_REQUIRED'
    END AS validation_status
FROM scope_tables AS s
LEFT JOIN information_schema.TABLES AS t
    ON t.TABLE_SCHEMA = 'bluemarketing_l13_staging'
   AND t.TABLE_NAME = s.table_name;
