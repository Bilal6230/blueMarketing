<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `finance_plot_purchases` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `seller_account_id` bigint UNSIGNED NOT NULL,
  `purchase_number` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `purchase_date` date NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `narration` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `finance_plot_purchases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `v2_plot_purch_proj_number_uq` (`project_id`,`purchase_number`),
  ADD KEY `finance_plot_purchases_seller_account_id_foreign` (`seller_account_id`),
  ADD KEY `v2_plot_purch_creator_fk` (`created_by`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `finance_plot_purchases`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('finance_plot_purchases'); }
};
