<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One-table baseline generated from verified MySQL 8.4.3 bluenew export. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0' 
CREATE TABLE `finance_bank_voucher_allocations` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `source_method` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_id` bigint UNSIGNED NOT NULL,
  `voucher_type` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `voucher_id` bigint UNSIGNED NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `allocated_by` bigint UNSIGNED NOT NULL,
  `allocated_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0 );
        DB::unprepared(<<<'BMDDL1' 
ALTER TABLE `finance_bank_voucher_allocations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `fin_bank_alloc_unique_voucher` (`project_id`,`voucher_type`,`voucher_id`),
  ADD KEY `fin_bank_alloc_source` (`project_id`,`source_method`,`source_id`);
BMDDL1 );
        DB::unprepared(<<<'BMDDL2' 
ALTER TABLE `finance_bank_voucher_allocations`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
BMDDL2 );
    }
    public function down(): void { Schema::dropIfExists('finance_bank_voucher_allocations'); }
};
