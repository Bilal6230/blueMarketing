<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `stock_entries` (
  `id` bigint UNSIGNED NOT NULL,
  `slip_no` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entry_date` date NOT NULL,
  `flow` enum('IN','OUT') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `party_id` bigint UNSIGNED NOT NULL,
  `vehicle_no` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `driver_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `billed_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `updated_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `project_id` bigint UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `stock_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `stock_entries_slip_no_unique` (`slip_no`),
  ADD UNIQUE KEY `stock_entries_id_project_unique` (`id`,`project_id`),
  ADD KEY `stock_entries_party_id_foreign` (`party_id`),
  ADD KEY `stock_entries_entry_date_index` (`entry_date`),
  ADD KEY `stock_entries_flow_index` (`flow`),
  ADD KEY `stock_entries_billed_at_index` (`billed_at`),
  ADD KEY `stock_entries_project_lookup_idx` (`project_id`,`id`),
  ADD KEY `stock_entries_party_project_fk` (`project_id`,`party_id`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `stock_entries`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('stock_entries'); }
};
