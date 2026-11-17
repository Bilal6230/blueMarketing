<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One-table baseline generated from verified MySQL 8.4.3 bluenew export. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `employee_profiles` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `employee_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `joining_date` date DEFAULT NULL,
  `department_definition_id` bigint UNSIGNED DEFAULT NULL,
  `designation_definition_id` bigint UNSIGNED DEFAULT NULL,
  `employee_category_definition_id` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `employee_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_profiles_user_uq` (`user_id`),
  ADD UNIQUE KEY `employee_profiles_project_code_uq` (`project_id`,`employee_code`),
  ADD UNIQUE KEY `employee_profiles_id_project_uq` (`id`,`project_id`),
  ADD UNIQUE KEY `employee_profiles_id_user_project_uq` (`id`,`user_id`,`project_id`),
  ADD KEY `department_definition_id_project_fk` (`department_definition_id`,`project_id`),
  ADD KEY `designation_definition_id_project_fk` (`designation_definition_id`,`project_id`),
  ADD KEY `employee_category_definition_id_project_fk` (`employee_category_definition_id`,`project_id`),
  ADD KEY `employee_profiles_joining_idx` (`project_id`,`joining_date`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `employee_profiles`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('employee_profiles'); }
};
