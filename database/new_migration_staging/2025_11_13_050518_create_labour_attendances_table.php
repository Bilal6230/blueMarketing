<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `labour_attendances` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED DEFAULT NULL,
  `labour_id` bigint UNSIGNED NOT NULL,
  `site_id` bigint UNSIGNED DEFAULT NULL,
  `date` date NOT NULL,
  `status` enum('present','absent','leave','holiday','not-marked') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not-marked',
  `hours` decimal(5,2) NOT NULL DEFAULT '0.00',
  `ot_hours` decimal(5,2) NOT NULL DEFAULT '0.00',
  `rate` decimal(10,2) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `remarks` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_approved` tinyint NOT NULL DEFAULT '0',
  `marked_by` bigint UNSIGNED DEFAULT NULL,
  `paid_status` enum('paid','unpaid') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `voucher_status` enum('created','notcreated') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'notcreated',
  `ratings` decimal(10,1) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `labour_attendances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_labour_attendance` (`labour_id`,`site_id`,`date`),
  ADD KEY `labour_attendances_site_id_foreign` (`site_id`),
  ADD KEY `labour_attendances_marked_by_foreign` (`marked_by`),
  ADD KEY `idx_labour_attendances_project_id` (`project_id`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `labour_attendances`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2698;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('labour_attendances'); }
};
