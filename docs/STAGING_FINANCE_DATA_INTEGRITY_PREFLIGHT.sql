-- MySQL 8.0, SELECT-only data-integrity preflight.
-- Run only after STAGING_FINANCE_SCHEMA_PREFLIGHT.sql returns complete PASS.
WITH checks AS (
  SELECT 'duplicate:ledgers.id' check_name,
    NOT EXISTS(SELECT 1 FROM bluemarketing_l13_staging.ledgers GROUP BY id HAVING COUNT(*)>1) passed,
    'zero duplicate groups' expected
  UNION ALL SELECT 'duplicate:project_head_subheads.id',
    NOT EXISTS(SELECT 1 FROM bluemarketing_l13_staging.project_head_subheads GROUP BY id HAVING COUNT(*)>1),'zero duplicate groups'
  UNION ALL SELECT 'duplicate:journal_vouchers.id_project',
    NOT EXISTS(SELECT 1 FROM bluemarketing_l13_staging.journal_vouchers GROUP BY id,project_id HAVING COUNT(*)>1),'zero duplicate groups'
  UNION ALL SELECT 'duplicate:commision_vouchers.id_project',
    NOT EXISTS(SELECT 1 FROM bluemarketing_l13_staging.commision_vouchers GROUP BY id,project_id HAVING COUNT(*)>1),'zero duplicate groups'
  UNION ALL SELECT 'duplicate:ledger_source_attributions.ledger_id',
    NOT EXISTS(SELECT 1 FROM bluemarketing_l13_staging.ledger_source_attributions GROUP BY ledger_id HAVING COUNT(*)>1),'zero duplicate groups'
  UNION ALL SELECT 'orphan:JV_ledger_project_account',
    NOT EXISTS(SELECT 1 FROM bluemarketing_l13_staging.ledgers l
      LEFT JOIN bluemarketing_l13_staging.project_head_subheads p ON p.id=l.project_head_subheads_id
      WHERE l.type='JV' AND p.id IS NULL),'zero orphan rows'
  UNION ALL SELECT 'unsafe:negative_JV_ledger_type_id',
    NOT EXISTS(SELECT 1 FROM bluemarketing_l13_staging.ledgers
      WHERE type='JV' AND type_id<0),'zero negative signed join keys'
  UNION ALL SELECT 'unsafe:negative_JV_header_project_id',
    NOT EXISTS(SELECT 1 FROM bluemarketing_l13_staging.journal_vouchers
      WHERE project_id<0),'zero negative signed join keys'
  UNION ALL SELECT 'identity_and_target',
    DATABASE()='bluemarketing_l13_staging'
      AND BINARY CURRENT_USER()=BINARY 'blue_l13_audit@127.0.0.1' AND @@port=3306
      AND @@transaction_isolation IN ('READ-COMMITTED','REPEATABLE-READ'),
    'exact staging database, account, port and consistent-read isolation'
)
SELECT /*+ MAX_EXECUTION_TIME(60000) */ check_name,passed,
  IF(passed,'count=0 / identity valid','count>0 / identity invalid') observed,expected,
  IF(MIN(passed) OVER ()=1,'PASS','BLOCKED') overall_status
FROM checks ORDER BY passed,check_name;
