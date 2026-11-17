<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One-table baseline generated from verified MySQL 8.4.3 bluenew export. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `daily_voucher_approval_policies` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `voucher_type` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `approval_required` tinyint(1) NOT NULL DEFAULT '0',
  `approver_user_id` bigint UNSIGNED DEFAULT NULL,
  `updated_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `daily_voucher_approval_policies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `daily_voucher_policy_project_type_unique` (`project_id`,`voucher_type`),
  ADD KEY `daily_voucher_policy_proj_approval_idx` (`project_id`,`approval_required`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `daily_voucher_approval_policies`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('daily_voucher_approval_policies'); }
};
