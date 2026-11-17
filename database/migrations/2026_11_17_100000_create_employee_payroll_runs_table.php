<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One-table baseline generated from verified MySQL 8.4.3 bluenew export. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `employee_payroll_runs` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `payroll_month` char(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `run_number` int UNSIGNED NOT NULL,
  `status` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_approval',
  `employee_count` int UNSIGNED NOT NULL,
  `gross_total` decimal(14,2) NOT NULL,
  `net_total` decimal(14,2) NOT NULL,
  `snapshot_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `approved_by` bigint UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `employee_payroll_runs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_payroll_run_version_uq` (`project_id`,`payroll_month`,`run_number`),
  ADD UNIQUE KEY `employee_payroll_run_id_project_uq` (`id`,`project_id`),
  ADD KEY `employee_payroll_run_lookup_idx` (`project_id`,`payroll_month`,`status`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `employee_payroll_runs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('employee_payroll_runs'); }
};
