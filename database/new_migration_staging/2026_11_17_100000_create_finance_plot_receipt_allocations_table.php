<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `finance_plot_receipt_allocations` (
  `id` bigint UNSIGNED NOT NULL,
  `receipt_id` bigint UNSIGNED NOT NULL,
  `booking_id` bigint UNSIGNED NOT NULL,
  `plot_id` bigint UNSIGNED NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `finance_plot_receipt_allocations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `v2_plot_receipt_booking_unique` (`receipt_id`,`booking_id`),
  ADD KEY `finance_plot_receipt_allocations_booking_id_foreign` (`booking_id`),
  ADD KEY `finance_plot_receipt_allocations_plot_id_foreign` (`plot_id`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `finance_plot_receipt_allocations`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('finance_plot_receipt_allocations'); }
};
