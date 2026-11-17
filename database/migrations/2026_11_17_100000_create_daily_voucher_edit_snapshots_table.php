<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One-table baseline generated from verified MySQL 8.4.3 bluenew export. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `daily_voucher_edit_snapshots` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `source_id` bigint UNSIGNED NOT NULL,
  `approval_id` bigint UNSIGNED DEFAULT NULL,
  `status` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `before_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `proposed_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `submitted_by` bigint UNSIGNED NOT NULL,
  `decided_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `daily_voucher_edit_snapshots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `voucher_edit_project_source_status` (`project_id`,`source_id`,`status`),
  ADD KEY `voucher_edit_approval_status` (`approval_id`,`status`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `daily_voucher_edit_snapshots`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('daily_voucher_edit_snapshots'); }
};
