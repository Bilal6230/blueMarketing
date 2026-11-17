<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One-table baseline generated from verified MySQL 8.4.3 bluenew export. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0' 
CREATE TABLE `employee_attendance_records` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `employee_profile_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `work_date` date NOT NULL,
  `snapshot_batch_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unreviewed',
  `punch_in` datetime DEFAULT NULL,
  `punch_out` datetime DEFAULT NULL,
  `source_punch_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `source_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `snapshot_by` bigint UNSIGNED DEFAULT NULL,
  `reviewed_by` bigint UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_reason` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
BMDDL0 );
        DB::unprepared(<<<'BMDDL1' 
ALTER TABLE `employee_attendance_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_att_profile_day_uq` (`employee_profile_id`,`work_date`),
  ADD UNIQUE KEY `employee_att_record_project_uq` (`id`,`project_id`),
  ADD KEY `employee_att_profile_user_project_fk` (`employee_profile_id`,`user_id`,`project_id`),
  ADD KEY `employee_att_project_day_status_idx` (`project_id`,`work_date`,`status`),
  ADD KEY `employee_att_snapshot_batch_idx` (`project_id`,`snapshot_batch_id`);
BMDDL1 );
        DB::unprepared(<<<'BMDDL2' 
ALTER TABLE `employee_attendance_records`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
BMDDL2 );
    }
    public function down(): void { Schema::dropIfExists('employee_attendance_records'); }
};
