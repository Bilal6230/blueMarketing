<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `stock_item_import_batches` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `created_by` bigint UNSIGNED NOT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'staged',
  `total_rows` int UNSIGNED NOT NULL DEFAULT '0',
  `added_rows` int UNSIGNED NOT NULL DEFAULT '0',
  `updated_rows` int UNSIGNED NOT NULL DEFAULT '0',
  `skipped_rows` int UNSIGNED NOT NULL DEFAULT '0',
  `failed_rows` int UNSIGNED NOT NULL DEFAULT '0',
  `applied_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `stock_item_import_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stock_item_import_batches_created_by_foreign` (`created_by`),
  ADD KEY `stock_item_import_batch_lookup_idx` (`project_id`,`status`,`created_at`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `stock_item_import_batches`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('stock_item_import_batches'); }
};
