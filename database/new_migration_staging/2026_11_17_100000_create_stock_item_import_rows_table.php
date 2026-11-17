<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `stock_item_import_rows` (
  `id` bigint UNSIGNED NOT NULL,
  `batch_id` bigint UNSIGNED NOT NULL,
  `row_number` int UNSIGNED NOT NULL,
  `stock_item_id` bigint UNSIGNED DEFAULT NULL,
  `action` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `errors` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `stock_item_import_rows`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `stock_item_import_row_unique` (`batch_id`,`row_number`),
  ADD KEY `stock_item_import_rows_stock_item_id_foreign` (`stock_item_id`),
  ADD KEY `stock_item_import_row_action_idx` (`batch_id`,`action`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `stock_item_import_rows`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('stock_item_import_rows'); }
};
