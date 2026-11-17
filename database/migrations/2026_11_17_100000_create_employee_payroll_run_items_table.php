<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One-table baseline generated from verified MySQL 8.4.3 bluenew export. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0' 
CREATE TABLE `employee_payroll_run_items` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `payroll_run_id` bigint UNSIGNED NOT NULL,
  `employee_profile_id` bigint UNSIGNED NOT NULL,
  `gross_amount` decimal(14,2) NOT NULL,
  `net_amount` decimal(14,2) NOT NULL,
  `snapshot_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `source_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
BMDDL0 );
        DB::unprepared(<<<'BMDDL1' 
ALTER TABLE `employee_payroll_run_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_payroll_item_employee_uq` (`payroll_run_id`,`employee_profile_id`),
  ADD KEY `employee_payroll_item_run_project_fk` (`payroll_run_id`,`project_id`),
  ADD KEY `employee_payroll_item_profile_project_fk` (`employee_profile_id`,`project_id`),
  ADD KEY `employee_payroll_item_project_employee_idx` (`project_id`,`employee_profile_id`);
BMDDL1 );
        DB::unprepared(<<<'BMDDL2' 
ALTER TABLE `employee_payroll_run_items`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;
BMDDL2 );
    }
    public function down(): void { Schema::dropIfExists('employee_payroll_run_items'); }
};
