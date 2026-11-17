<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One-table baseline generated from verified MySQL 8.4.3 bluenew export. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `commision_voucher_details` (
  `id` bigint UNSIGNED NOT NULL,
  `commision_voucher_id` bigint UNSIGNED NOT NULL,
  `account_id` bigint UNSIGNED NOT NULL,
  `debit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `credit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `commision_voucher_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `commision_voucher_details_commision_voucher_id_foreign` (`commision_voucher_id`),
  ADD KEY `v2_commision_voucher_details_account_id_fk` (`account_id`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `commision_voucher_details`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('commision_voucher_details'); }
};
