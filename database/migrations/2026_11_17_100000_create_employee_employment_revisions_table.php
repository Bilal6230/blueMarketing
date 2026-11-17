<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One-table baseline generated from verified MySQL 8.4.3 bluenew export. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `employee_employment_revisions` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `employee_profile_id` bigint UNSIGNED NOT NULL,
  `effective_from` date NOT NULL,
  `basic_salary` decimal(12,2) NOT NULL DEFAULT '0.00',
  `allowances` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `payment_method` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `salary_status` enum('active','hold') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `duty_start` time DEFAULT NULL,
  `duty_end` time DEFAULT NULL,
  `break_minutes` smallint UNSIGNED NOT NULL DEFAULT '0',
  `daily_hours` decimal(5,2) DEFAULT NULL,
  `working_days_per_week` tinyint UNSIGNED DEFAULT NULL,
  `weekly_off_days` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `grace_minutes` smallint UNSIGNED NOT NULL DEFAULT '0',
  `late_fine_policy` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `absence_deduction` decimal(12,2) NOT NULL DEFAULT '0.00',
  `leave_deduction` decimal(12,2) NOT NULL DEFAULT '0.00',
  `half_day_after_minutes` smallint UNSIGNED DEFAULT NULL,
  `short_leave_minutes` smallint UNSIGNED DEFAULT NULL,
  `early_departure_method` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `early_departure_deduction` decimal(12,2) NOT NULL DEFAULT '0.00',
  `overtime_method` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `overtime_rate` decimal(12,2) NOT NULL DEFAULT '0.00',
  `other_adjustments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `reason` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `salary_proration_method` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual_review',
  `half_day_deduction` decimal(12,2) NOT NULL DEFAULT '0.00',
  `short_leave_method` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `short_leave_deduction` decimal(12,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `employee_employment_revisions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employment_revision_effective_uq` (`employee_profile_id`,`effective_from`),
  ADD KEY `employment_revisions_profile_project_fk` (`employee_profile_id`,`project_id`),
  ADD KEY `employment_revision_project_date_idx` (`project_id`,`effective_from`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `employee_employment_revisions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('employee_employment_revisions'); }
};
