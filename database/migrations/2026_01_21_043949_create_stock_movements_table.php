<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `stock_movements` (
  `id` bigint UNSIGNED NOT NULL,
  `movement_date` date NOT NULL,
  `direction` enum('IN','OUT') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_id` bigint UNSIGNED NOT NULL,
  `qty` decimal(18,3) NOT NULL,
  `rate_minor` bigint UNSIGNED NOT NULL DEFAULT '0',
  `amount_minor` bigint UNSIGNED NOT NULL DEFAULT '0',
  `source_type` enum('entry','bill') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_id` bigint UNSIGNED NOT NULL,
  `source_line_id` bigint UNSIGNED NOT NULL,
  `party_id` bigint UNSIGNED DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `project_id` bigint UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `stock_movements_source_type_source_line_id_unique` (`source_type`,`source_line_id`),
  ADD KEY `stock_movements_party_id_foreign` (`party_id`),
  ADD KEY `stock_movements_item_id_movement_date_index` (`item_id`,`movement_date`),
  ADD KEY `stock_movements_movement_date_index` (`movement_date`),
  ADD KEY `stock_movements_direction_index` (`direction`),
  ADD KEY `stock_movements_source_type_index` (`source_type`),
  ADD KEY `stock_movements_source_id_index` (`source_id`),
  ADD KEY `stock_movements_project_lookup_idx` (`project_id`,`id`),
  ADD KEY `stock_movements_party_project_fk` (`project_id`,`party_id`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `stock_movements`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('stock_movements'); }
};
