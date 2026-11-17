<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `project_voucher_fiscal_sequences` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `series` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `financial_year` smallint UNSIGNED NOT NULL,
  `last_number` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `project_voucher_fiscal_sequences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `project_voucher_fiscal_scope_uq` (`project_id`,`series`,`financial_year`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `project_voucher_fiscal_sequences`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('project_voucher_fiscal_sequences'); }
};
