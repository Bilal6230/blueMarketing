<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One-table baseline generated from verified MySQL 8.4.3 bluenew export. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `booking_vouchers` (
  `id` bigint UNSIGNED NOT NULL,
  `booking_id` bigint UNSIGNED DEFAULT NULL,
  `customer_ledger_id` bigint UNSIGNED DEFAULT NULL,
  `ledger_id` bigint UNSIGNED DEFAULT NULL,
  `project_id` bigint UNSIGNED DEFAULT NULL,
  `customer_id` bigint UNSIGNED DEFAULT NULL,
  `plot_id` bigint UNSIGNED DEFAULT NULL,
  `voucher_series` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `voucher_number` int UNSIGNED DEFAULT NULL,
  `slip_reference` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_type` tinyint UNSIGNED DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `receipt_date` date DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `bank_id` bigint UNSIGNED DEFAULT NULL,
  `t_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `passing_date` date DEFAULT NULL,
  `is_active` tinyint NOT NULL DEFAULT '1',
  `delete_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_approve` tinyint NOT NULL DEFAULT '0',
  `create_by` bigint UNSIGNED DEFAULT NULL,
  `update_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `booking_vouchers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_vouchers_customer_ledger_id_unique` (`customer_ledger_id`),
  ADD UNIQUE KEY `booking_vouchers_ledger_id_unique` (`ledger_id`),
  ADD KEY `booking_vouchers_project_series_number_idx` (`project_id`,`voucher_series`,`voucher_number`),
  ADD KEY `booking_vouchers_booking_id_index` (`booking_id`),
  ADD KEY `booking_vouchers_project_id_index` (`project_id`),
  ADD KEY `booking_vouchers_customer_id_index` (`customer_id`),
  ADD KEY `booking_vouchers_plot_id_index` (`plot_id`),
  ADD KEY `booking_vouchers_voucher_series_index` (`voucher_series`),
  ADD KEY `booking_vouchers_slip_reference_index` (`slip_reference`),
  ADD KEY `booking_vouchers_receipt_date_index` (`receipt_date`),
  ADD KEY `booking_vouchers_bank_id_index` (`bank_id`),
  ADD KEY `booking_vouchers_is_active_index` (`is_active`),
  ADD KEY `booking_vouchers_is_approve_index` (`is_approve`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `booking_vouchers`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('booking_vouchers'); }
};
