<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One-table baseline generated from verified MySQL 8.4.3 bluenew export. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0' 
CREATE TABLE `daily_voucher_approvals` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `voucher_type` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_table` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_id` bigint UNSIGNED NOT NULL,
  `status` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `assigned_approver_id` bigint UNSIGNED NOT NULL,
  `submitted_by` bigint UNSIGNED NOT NULL,
  `decided_by` bigint UNSIGNED DEFAULT NULL,
  `rejection_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `decided_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0 );
        DB::unprepared(<<<'BMDDL1' 
ALTER TABLE `daily_voucher_approvals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `daily_voucher_approval_source_unique` (`source_table`,`source_id`),
  ADD KEY `daily_voucher_approval_queue_index` (`project_id`,`assigned_approver_id`,`status`),
  ADD KEY `daily_voucher_approval_type_index` (`project_id`,`voucher_type`,`status`);
BMDDL1 );
        DB::unprepared(<<<'BMDDL2' 
ALTER TABLE `daily_voucher_approvals`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;
BMDDL2 );
    }
    public function down(): void { Schema::dropIfExists('daily_voucher_approvals'); }
};
