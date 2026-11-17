<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One-table baseline generated from verified MySQL 8.4.3 bluenew export. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0' 
CREATE TABLE `employee_payroll_run_audits` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `payroll_run_id` bigint UNSIGNED NOT NULL,
  `action` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `actor_id` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
BMDDL0 );
        DB::unprepared(<<<'BMDDL1' 
ALTER TABLE `employee_payroll_run_audits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_payroll_audit_run_project_fk` (`payroll_run_id`,`project_id`),
  ADD KEY `employee_payroll_audit_lookup_idx` (`project_id`,`payroll_run_id`,`created_at`);
BMDDL1 );
        DB::unprepared(<<<'BMDDL2' 
ALTER TABLE `employee_payroll_run_audits`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;
BMDDL2 );
    }
    public function down(): void { Schema::dropIfExists('employee_payroll_run_audits'); }
};
