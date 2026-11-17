<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `stock_bills` (
  `id` bigint UNSIGNED NOT NULL,
  `bill_no` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `bill_date` date NOT NULL,
  `type` enum('purchase','sale') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `party_id` bigint UNSIGNED NOT NULL,
  `entry_id` bigint UNSIGNED DEFAULT NULL,
  `total_minor` bigint UNSIGNED NOT NULL DEFAULT '0',
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `updated_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `project_id` bigint UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `stock_bills`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `stock_bills_bill_no_unique` (`bill_no`),
  ADD UNIQUE KEY `stock_bills_id_project_unique` (`id`,`project_id`),
  ADD KEY `stock_bills_party_id_foreign` (`party_id`),
  ADD KEY `stock_bills_entry_id_foreign` (`entry_id`),
  ADD KEY `stock_bills_bill_date_index` (`bill_date`),
  ADD KEY `stock_bills_type_index` (`type`),
  ADD KEY `stock_bills_project_lookup_idx` (`project_id`,`id`),
  ADD KEY `stock_bills_party_project_fk` (`project_id`,`party_id`),
  ADD KEY `stock_bills_entry_project_fk` (`entry_id`,`project_id`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `stock_bills`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('stock_bills'); }
};
