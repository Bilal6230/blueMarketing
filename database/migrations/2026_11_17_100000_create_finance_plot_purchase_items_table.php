<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `finance_plot_purchase_items` (
  `id` bigint UNSIGNED NOT NULL,
  `purchase_id` bigint UNSIGNED NOT NULL,
  `plot_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `area` decimal(18,4) NOT NULL,
  `unit` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_rate` decimal(18,2) NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `inventory_account_id` bigint UNSIGNED NOT NULL,
  `legacy_plot_id` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `finance_plot_purchase_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `v2_plot_purch_item_code_uq` (`purchase_id`,`plot_code`),
  ADD UNIQUE KEY `v2_plot_purch_item_plot_uq` (`legacy_plot_id`),
  ADD KEY `finance_plot_purchase_items_inventory_account_id_foreign` (`inventory_account_id`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `finance_plot_purchase_items`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('finance_plot_purchase_items'); }
};
