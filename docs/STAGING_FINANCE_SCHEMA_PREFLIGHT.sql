-- MySQL 8.0, SELECT-only schema preflight. This file reads only information_schema.
-- Any passed=0 or overall_status=BLOCKED rejects the aggregate audit.
WITH
required_tables AS (
  SELECT 'projects' table_name UNION ALL SELECT 'ledgers'
  UNION ALL SELECT 'project_head_subheads' UNION ALL SELECT 'journal_vouchers'
  UNION ALL SELECT 'commision_vouchers' UNION ALL SELECT 'ledger_source_attributions'
),
required_columns AS (
  SELECT 'projects' table_name,'id' column_name,'bigint' data_type,'NO' is_nullable,'unsigned' unsigned_required
  UNION ALL SELECT 'ledgers','id','bigint','NO','unsigned'
  UNION ALL SELECT 'ledgers','type','varchar','YES','no'
  UNION ALL SELECT 'ledgers','type_id','int','YES','no'
  -- Legacy staging stores the display reference as a nullable BIGINT.
  UNION ALL SELECT 'ledgers','voucher_number','bigint','YES','no'
  UNION ALL SELECT 'ledgers','project_head_subheads_id','bigint','NO','unsigned'
  -- Legacy INT UNSIGNED PK safely widens into ledgers.project_head_subheads_id.
  UNION ALL SELECT 'project_head_subheads','id','int','NO','unsigned'
  UNION ALL SELECT 'project_head_subheads','project_id','bigint','NO','unsigned'
  UNION ALL SELECT 'journal_vouchers','id','bigint','NO','unsigned'
  -- Nullable project IDs never match the project-scoped equality join.
  UNION ALL SELECT 'journal_vouchers','project_id','int','YES','no'
  -- Legacy numeric references are normalized to strings by the aggregate.
  UNION ALL SELECT 'journal_vouchers','voucher_number','int','NO','no'
  UNION ALL SELECT 'commision_vouchers','id','bigint','NO','unsigned'
  UNION ALL SELECT 'commision_vouchers','project_id','bigint','NO','unsigned'
  UNION ALL SELECT 'commision_vouchers','voucher_number','varchar','NO','no'
  UNION ALL SELECT 'ledger_source_attributions','id','bigint','NO','unsigned'
  UNION ALL SELECT 'ledger_source_attributions','ledger_id','bigint','NO','unsigned'
  UNION ALL SELECT 'ledger_source_attributions','project_id','bigint','NO','unsigned'
  UNION ALL SELECT 'ledger_source_attributions','source_kind','varchar','NO','no'
  UNION ALL SELECT 'ledger_source_attributions','source_id','bigint','NO','unsigned'
  UNION ALL SELECT 'ledger_source_attributions','evidence_kind','varchar','NO','no'
),
schema_checks AS (
  SELECT CONCAT('table:',r.table_name) check_name,
    COUNT(t.table_name)=1 AND MAX(t.table_type)='BASE TABLE' AND MAX(t.engine)='InnoDB' passed,
    CONCAT('count=',COUNT(t.table_name),'; engine=',COALESCE(MAX(t.engine),'MISSING')) observed,
    'exactly one BASE TABLE using InnoDB' expected
  FROM required_tables r LEFT JOIN information_schema.tables t
    ON t.table_schema='bluemarketing_l13_staging' AND t.table_name=r.table_name
  GROUP BY r.table_name
  UNION ALL
  SELECT CONCAT('column:',r.table_name,'.',r.column_name),
    COUNT(c.column_name)=1 AND MAX(c.data_type)=r.data_type AND MAX(c.is_nullable)=r.is_nullable
      AND (r.unsigned_required='no' OR MAX(c.column_type) LIKE '%unsigned%'),
    CONCAT('count=',COUNT(c.column_name),'; type=',COALESCE(MAX(c.column_type),'MISSING'),
      '; nullable=',COALESCE(MAX(c.is_nullable),'MISSING')),
    CONCAT(r.data_type,'; nullable=',r.is_nullable,
      CASE WHEN r.unsigned_required='unsigned' THEN '; unsigned' ELSE '' END)
  FROM required_columns r LEFT JOIN information_schema.columns c
    ON c.table_schema='bluemarketing_l13_staging'
      AND c.table_name=r.table_name AND c.column_name=r.column_name
  GROUP BY r.table_name,r.column_name,r.data_type,r.is_nullable,r.unsigned_required
),
key_requirements AS (
  SELECT 'projects' table_name,'PRIMARY' index_name,'id' columns_csv
  UNION ALL SELECT 'ledgers','PRIMARY','id'
  UNION ALL SELECT 'project_head_subheads','PRIMARY','id'
  UNION ALL SELECT 'journal_vouchers','PRIMARY','id'
  UNION ALL SELECT 'commision_vouchers','PRIMARY','id'
  UNION ALL SELECT 'ledger_source_attributions','PRIMARY','id'
  UNION ALL SELECT 'ledger_source_attributions','ANY_UNIQUE','ledger_id'
),
actual_keys AS (
  SELECT table_name,index_name,non_unique,
    GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR ',') columns_csv
  FROM information_schema.statistics WHERE table_schema='bluemarketing_l13_staging'
  GROUP BY table_name,index_name,non_unique
),
key_checks AS (
  SELECT CONCAT('unique_key:',r.table_name,'.',r.columns_csv) check_name,
    COUNT(a.index_name)>0 passed,CONCAT('matching_unique_indexes=',COUNT(a.index_name)) observed,
    'at least one exact PRIMARY/UNIQUE key' expected
  FROM key_requirements r LEFT JOIN actual_keys a
    ON a.table_name=r.table_name AND a.non_unique=0 AND a.columns_csv=r.columns_csv
      AND (r.index_name<>'PRIMARY' OR a.index_name='PRIMARY')
  GROUP BY r.table_name,r.columns_csv
),
all_checks AS (
  SELECT * FROM schema_checks UNION ALL SELECT * FROM key_checks
  UNION ALL SELECT 'identity_and_target',
    DATABASE()='bluemarketing_l13_staging'
      AND BINARY CURRENT_USER()=BINARY 'blue_l13_audit@127.0.0.1' AND @@port=3306
      AND @@transaction_isolation IN ('READ-COMMITTED','REPEATABLE-READ'),
    CONCAT('database=',COALESCE(DATABASE(),'NULL'),'; account=',CURRENT_USER(),'; port=',@@port,
      '; isolation=',@@transaction_isolation),
    'bluemarketing_l13_staging; blue_l13_audit@127.0.0.1; port 3306; READ-COMMITTED or REPEATABLE-READ'
)
SELECT /*+ MAX_EXECUTION_TIME(60000) */ check_name,passed,observed,expected,
  IF(MIN(passed) OVER ()=1,'PASS','BLOCKED') overall_status
FROM all_checks ORDER BY passed,check_name;
