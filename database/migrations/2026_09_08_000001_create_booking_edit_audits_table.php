<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One-table baseline generated from verified MySQL 8.4.3 bluenew export. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `booking_edit_audits` (
  `id` bigint UNSIGNED NOT NULL,
  `booking_id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `operation` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_total` decimal(10,2) DEFAULT NULL,
  `new_total` decimal(10,2) DEFAULT NULL,
  `delta` decimal(10,2) DEFAULT NULL,
  `old_accounting_principal` decimal(10,2) DEFAULT NULL,
  `delta_from_accounting` decimal(10,2) DEFAULT NULL,
  `journal_voucher_id` bigint UNSIGNED DEFAULT NULL,
  `request_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `booking_edit_audits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_edit_audits_request_key_unique` (`request_key`),
  ADD KEY `booking_edit_audits_booking_id_created_at_index` (`booking_id`,`created_at`),
  ADD KEY `booking_edit_audits_project_id_operation_index` (`project_id`,`operation`),
  ADD KEY `v2_booking_edit_audits_user_id_fk` (`user_id`),
  ADD KEY `v2_booking_edit_audits_journal_voucher_id_fk` (`journal_voucher_id`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `booking_edit_audits`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('booking_edit_audits'); }
};
