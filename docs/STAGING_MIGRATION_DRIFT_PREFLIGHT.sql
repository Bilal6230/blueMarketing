-- MySQL 8.0, SELECT-only migration-history and schema metadata capture.
-- Review identity first. Any unexpected identity or query error blocks reconciliation.
SELECT DATABASE() selected_database, CURRENT_USER() authenticated_account,
  @@port server_port, @@transaction_isolation transaction_isolation,
  DATABASE()='bluemarketing_l13_staging'
    AND BINARY CURRENT_USER()=BINARY 'blue_l13_audit@127.0.0.1'
    AND @@port=3306 identity_gate_passed;

-- History evidence only. A row does not prove its effects still exist; absence does not prove unapplied.
SELECT migration, batch
FROM bluemarketing_l13_staging.migrations
ORDER BY migration;

-- Existing tables that can collide with Schema::create migrations.
SELECT table_name, table_type, engine, table_collation
FROM information_schema.tables
WHERE table_schema='bluemarketing_l13_staging'
ORDER BY table_name;

-- Exact columns needed to reconcile verified alter/create hazards.
SELECT table_name, column_name, ordinal_position, column_type, is_nullable,
  column_default, extra, collation_name
FROM information_schema.columns
WHERE table_schema='bluemarketing_l13_staging'
  AND (
    table_name IN ('permissions','roles','model_has_permissions','model_has_roles','role_has_permissions')
    OR (table_name='journal_vouchers' AND column_name IN
      ('id','project_id','site_id','voucher_number','description','deleted_at'))
    OR (table_name='plots' AND column_name IN ('id','unit','amount'))
    OR (table_name='landing_pages' AND column_name IN
      ('id','draft_payload','published_at','section_order','project_overrides'))
    OR table_name IN ('ledger_source_attributions','financial_posting_requests')
  )
ORDER BY table_name, ordinal_position;

-- Index names, ordering, uniqueness and prefixes for the same hazard scope.
SELECT table_name, index_name, non_unique, seq_in_index, column_name,
  sub_part, index_type
FROM information_schema.statistics
WHERE table_schema='bluemarketing_l13_staging'
  AND table_name IN (
    'permissions','roles','model_has_permissions','model_has_roles','role_has_permissions',
    'journal_vouchers','plots','landing_pages',
    'ledger_source_attributions','financial_posting_requests'
  )
ORDER BY table_name, index_name, seq_in_index;

-- Foreign-key shape for permission and site-related tables; no business rows are returned.
SELECT k.table_name, k.constraint_name, k.column_name,
  k.referenced_table_name, k.referenced_column_name,
  r.update_rule, r.delete_rule
FROM information_schema.key_column_usage k
LEFT JOIN information_schema.referential_constraints r
  ON r.constraint_schema=k.constraint_schema
  AND r.table_name=k.table_name
  AND r.constraint_name=k.constraint_name
WHERE k.constraint_schema='bluemarketing_l13_staging'
  AND k.referenced_table_name IS NOT NULL
  AND k.table_name IN (
    'model_has_permissions','model_has_roles','role_has_permissions','journal_vouchers'
  )
ORDER BY k.table_name, k.constraint_name, k.ordinal_position;
