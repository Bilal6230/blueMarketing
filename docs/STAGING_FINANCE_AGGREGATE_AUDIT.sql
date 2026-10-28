-- MySQL 8.0, aggregate-only SELECT. Run only after schema preflight PASS.
-- Accept only result_status=ACCEPTABLE_FOR_MANUAL_REVIEW for all six rows.
-- Matching IDs/references and apparently-valid metadata never prove ownership.
WITH
identity_gate AS (
  SELECT DATABASE()='bluemarketing_l13_staging'
    AND BINARY CURRENT_USER()=BINARY 'blue_l13_audit@127.0.0.1'
    AND @@port=3306
    AND @@transaction_isolation IN ('READ-COMMITTED','REPEATABLE-READ') allowed
),
expected AS (
  SELECT 6 project_id,1430 prior_reported,1 priority
  UNION ALL SELECT 5,749,2 UNION ALL SELECT 3,692,3
  UNION ALL SELECT 1,2,4 UNION ALL SELECT 2,0,5 UNION ALL SELECT 4,0,6
),
project_gate AS (
  SELECT e.*,EXISTS(SELECT 1 FROM bluemarketing_l13_staging.projects p WHERE p.id=e.project_id) project_exists
  FROM expected e
),
integrity_gate AS (
  SELECT
    NOT EXISTS(SELECT 1 FROM bluemarketing_l13_staging.ledgers GROUP BY id HAVING COUNT(*)>1)
    AND NOT EXISTS(SELECT 1 FROM bluemarketing_l13_staging.project_head_subheads GROUP BY id HAVING COUNT(*)>1)
    AND NOT EXISTS(SELECT 1 FROM bluemarketing_l13_staging.journal_vouchers GROUP BY id,project_id HAVING COUNT(*)>1)
    AND NOT EXISTS(SELECT 1 FROM bluemarketing_l13_staging.commision_vouchers GROUP BY id,project_id HAVING COUNT(*)>1)
    AND NOT EXISTS(SELECT 1 FROM bluemarketing_l13_staging.ledger_source_attributions GROUP BY ledger_id HAVING COUNT(*)>1)
    AND NOT EXISTS(SELECT 1 FROM bluemarketing_l13_staging.ledgers l
      LEFT JOIN bluemarketing_l13_staging.project_head_subheads p ON p.id=l.project_head_subheads_id
      WHERE l.type='JV' AND p.id IS NULL)
    AND NOT EXISTS(SELECT 1 FROM bluemarketing_l13_staging.ledgers
      WHERE type='JV' AND type_id<0)
    AND NOT EXISTS(SELECT 1 FROM bluemarketing_l13_staging.journal_vouchers
      WHERE project_id<0)
    AS passed
),
scope AS (
  SELECT p.project_id,l.id,l.type_id,l.voucher_number
  FROM bluemarketing_l13_staging.ledgers l
  JOIN bluemarketing_l13_staging.project_head_subheads p ON p.id=l.project_head_subheads_id
  JOIN expected e ON e.project_id=p.project_id
  WHERE l.type='JV' AND (SELECT allowed FROM identity_gate)=1
    AND (SELECT passed FROM integrity_gate)=1
),
metadata AS (
  SELECT s.project_id,s.id,s.type_id,s.voucher_number,COUNT(a.ledger_id) metadata_rows,
    SUM(CASE WHEN a.project_id=s.project_id
       AND a.source_kind IN ('journal_voucher','commission_voucher')
       AND a.evidence_kind IN ('new_write_transaction','verified_external_reference','manual_review')
       AND a.source_id=s.type_id THEN 1 ELSE 0 END) apparent_rows
  FROM scope s LEFT JOIN bluemarketing_l13_staging.ledger_source_attributions a ON a.ledger_id=s.id
  GROUP BY s.project_id,s.id,s.type_id,s.voucher_number
),
population AS (
  SELECT e.project_id,COUNT(m.id) candidate_jv_type_rows,
    COALESCE(SUM(m.apparent_rows>0),0) apparently_attributed_rows,
    COALESCE(SUM(m.apparent_rows=0),0) unresolved_rows,
    COALESCE(SUM(m.metadata_rows>m.apparent_rows),0) ledgers_with_invalid_metadata,
    COALESCE(SUM(m.metadata_rows-m.apparent_rows),0) invalid_metadata_rows,
    COALESCE(SUM(m.metadata_rows>1),0) ledgers_with_multiple_metadata_rows,
    COALESCE(SUM(m.apparent_rows>0 AND m.metadata_rows>m.apparent_rows),0) apparent_with_invalid_metadata
  FROM expected e LEFT JOIN metadata m ON m.project_id=e.project_id GROUP BY e.project_id
),
ranked AS (
  SELECT m.*,ROW_NUMBER() OVER (PARTITION BY m.project_id ORDER BY m.id) rn
  FROM metadata m WHERE m.apparent_rows=0
),
triage AS (
  SELECT r.project_id,
    CASE WHEN j.id IS NOT NULL AND c.id IS NOT NULL THEN 'both_headers'
         WHEN j.id IS NOT NULL THEN 'jv_only'
         WHEN c.id IS NOT NULL THEN 'cv_only' ELSE 'neither_header' END header_category,
    CASE WHEN j.id IS NOT NULL AND c.id IS NOT NULL THEN 'ambiguous_candidates'
         WHEN j.id IS NULL AND c.id IS NULL THEN 'missing_candidate_header'
         WHEN HEX(COALESCE(CAST(r.voucher_number AS CHAR CHARACTER SET utf8mb4),''))
              REGEXP '^(00|09|0A|0B|0D|20)*$'
           OR HEX(COALESCE(CASE WHEN j.id IS NOT NULL
                THEN CAST(j.voucher_number AS CHAR CHARACTER SET utf8mb4)
                ELSE CAST(c.voucher_number AS CHAR CHARACTER SET utf8mb4) END,''))
              REGEXP '^(00|09|0A|0B|0D|20)*$' THEN 'missing_voucher_reference'
         WHEN CAST(COALESCE(CAST(r.voucher_number AS CHAR CHARACTER SET utf8mb4),'') AS BINARY)
           =CAST(COALESCE(CASE WHEN j.id IS NOT NULL
              THEN CAST(j.voucher_number AS CHAR CHARACTER SET utf8mb4)
              ELSE CAST(c.voucher_number AS CHAR CHARACTER SET utf8mb4) END,'') AS BINARY)
           THEN 'matching_reference_requires_manual_review'
         ELSE 'candidate_reference_disagreement' END evidence_category,
    j.id IS NULL AND jn.id IS NOT NULL same_id_jv_missing_project
  FROM ranked r
  LEFT JOIN bluemarketing_l13_staging.journal_vouchers j
    ON j.id=r.type_id AND j.project_id=r.project_id
  LEFT JOIN bluemarketing_l13_staging.journal_vouchers jn
    ON jn.id=r.type_id AND jn.project_id IS NULL
  LEFT JOIN bluemarketing_l13_staging.commision_vouchers c
    ON c.id=r.type_id AND c.project_id=r.project_id
  WHERE r.rn<=5000
),
totals AS (
  SELECT project_id,COUNT(*) scanned,
    SUM(header_category='both_headers') both_headers,SUM(header_category='jv_only') jv_only,
    SUM(header_category='cv_only') cv_only,SUM(header_category='neither_header') neither_header,
    SUM(evidence_category='ambiguous_candidates') ambiguous_candidates,
    SUM(evidence_category='missing_candidate_header') missing_candidate_header,
    SUM(evidence_category='missing_voucher_reference') missing_voucher_reference,
    SUM(evidence_category='candidate_reference_disagreement') candidate_reference_disagreement,
    SUM(evidence_category='matching_reference_requires_manual_review') matching_reference_requires_manual_review,
    SUM(same_id_jv_missing_project) same_id_jv_missing_project
  FROM triage GROUP BY project_id
),
result AS (
  SELECT e.project_id,e.priority,e.prior_reported,DATABASE() selected_database,CURRENT_USER() authenticated_account,
    @@transaction_isolation transaction_isolation,
    g.allowed identity_gate_passed,e.project_exists,i.passed integrity_gate_passed,
    p.candidate_jv_type_rows,p.apparently_attributed_rows,p.unresolved_rows current_unresolved,
    CAST(p.unresolved_rows AS SIGNED)-e.prior_reported delta_from_reported,
    p.ledgers_with_invalid_metadata,p.invalid_metadata_rows,p.ledgers_with_multiple_metadata_rows,
    p.apparent_with_invalid_metadata,COALESCE(t.scanned,0) scanned,
    p.unresolved_rows>COALESCE(t.scanned,0) incomplete,
    COALESCE(t.both_headers,0) both_headers,COALESCE(t.jv_only,0) jv_only,
    COALESCE(t.cv_only,0) cv_only,COALESCE(t.neither_header,0) neither_header,
    COALESCE(t.ambiguous_candidates,0) ambiguous_candidates,
    COALESCE(t.missing_candidate_header,0) missing_candidate_header,
    COALESCE(t.missing_voucher_reference,0) missing_voucher_reference,
    COALESCE(t.candidate_reference_disagreement,0) candidate_reference_disagreement,
    COALESCE(t.matching_reference_requires_manual_review,0) matching_reference_requires_manual_review,
    COALESCE(t.same_id_jv_missing_project,0) same_id_jv_missing_project
  FROM project_gate e CROSS JOIN identity_gate g CROSS JOIN integrity_gate i
  JOIN population p ON p.project_id=e.project_id LEFT JOIN totals t ON t.project_id=e.project_id
)
SELECT /*+ MAX_EXECUTION_TIME(60000) */ r.*,
  COUNT(*) OVER ()=6 six_project_row_count_valid,
  SUM(r.prior_reported) OVER () portfolio_prior_reported,
  SUM(r.current_unresolved) OVER () portfolio_current_unresolved,
  SUM(r.current_unresolved) OVER ()-SUM(r.prior_reported) OVER () portfolio_delta_from_reported,
  r.candidate_jv_type_rows=r.apparently_attributed_rows+r.current_unresolved population_arithmetic_valid,
  r.scanned=LEAST(r.current_unresolved,5000) scan_arithmetic_valid,
  r.scanned=r.both_headers+r.jv_only+r.cv_only+r.neither_header header_arithmetic_valid,
  r.scanned=r.ambiguous_candidates+r.missing_candidate_header+r.missing_voucher_reference
    +r.candidate_reference_disagreement+r.matching_reference_requires_manual_review evidence_arithmetic_valid,
  IF(COUNT(*) OVER ()=6
    AND r.identity_gate_passed=1 AND r.project_exists=1 AND r.integrity_gate_passed=1 AND r.incomplete=0
    AND r.candidate_jv_type_rows=r.apparently_attributed_rows+r.current_unresolved
    AND r.scanned=LEAST(r.current_unresolved,5000)
    AND r.scanned=r.both_headers+r.jv_only+r.cv_only+r.neither_header
    AND r.scanned=r.ambiguous_candidates+r.missing_candidate_header+r.missing_voucher_reference
      +r.candidate_reference_disagreement+r.matching_reference_requires_manual_review,
    'ACCEPTABLE_FOR_MANUAL_REVIEW','BLOCKED') result_status,
  0 independent_ownership_evidence_assessed,NULL independently_verified_ownership_count,
  0 source_verified,0 deployment_approved
FROM result r ORDER BY r.priority;
