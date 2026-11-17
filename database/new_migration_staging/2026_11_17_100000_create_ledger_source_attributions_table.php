<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `ledger_source_attributions` (
  `id` bigint UNSIGNED NOT NULL,
  `ledger_id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `source_kind` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_id` bigint UNSIGNED NOT NULL,
  `evidence_kind` varchar(48) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `evidence_reference` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attributed_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `ledger_source_attributions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ledger_source_attributions_ledger_id_unique` (`ledger_id`),
  ADD KEY `ledger_source_project_kind_id_idx` (`project_id`,`source_kind`,`source_id`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `ledger_source_attributions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('ledger_source_attributions'); }
};
