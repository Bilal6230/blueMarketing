<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `project_voucher_display_numbers` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `source_kind` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_id` bigint UNSIGNED NOT NULL,
  `series` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `financial_year` smallint UNSIGNED NOT NULL,
  `display_number` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `project_voucher_display_numbers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `voucher_display_source_uq` (`project_id`,`source_kind`,`source_id`),
  ADD UNIQUE KEY `voucher_display_project_number_uq` (`project_id`,`display_number`),
  ADD KEY `voucher_display_project_series_year_idx` (`project_id`,`series`,`financial_year`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `project_voucher_display_numbers`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('project_voucher_display_numbers'); }
};
