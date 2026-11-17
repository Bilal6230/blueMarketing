<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `stock_bill_lines` (
  `id` bigint UNSIGNED NOT NULL,
  `bill_id` bigint UNSIGNED NOT NULL,
  `item_id` bigint UNSIGNED NOT NULL,
  `qty` decimal(18,3) NOT NULL,
  `rate_minor` bigint UNSIGNED NOT NULL,
  `amount_minor` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `project_id` bigint UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `stock_bill_lines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `stock_bill_lines_id_project_unique` (`id`,`project_id`),
  ADD KEY `stock_bill_lines_item_id_foreign` (`item_id`),
  ADD KEY `stock_bill_lines_bill_id_item_id_index` (`bill_id`,`item_id`),
  ADD KEY `stock_bill_lines_project_parent_idx` (`project_id`,`bill_id`),
  ADD KEY `stock_bill_lines_bill_project_fk` (`bill_id`,`project_id`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `stock_bill_lines`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('stock_bill_lines'); }
};
