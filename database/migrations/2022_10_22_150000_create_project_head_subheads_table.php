<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `project_head_subheads` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `head_accounting_id` bigint UNSIGNED NOT NULL,
  `subhead_accounting_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `plot_id` bigint UNSIGNED DEFAULT NULL,
  `customer_id` bigint UNSIGNED DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `project_head_subheads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `project_head_subheads_id_project_unique` (`id`,`project_id`),
  ADD KEY `head_subhead_head_id_foreign` (`head_accounting_id`),
  ADD KEY `head_subhead_subhead_id_foreign` (`subhead_accounting_id`),
  ADD KEY `project_head_subheads_project_id_foreign` (`project_id`),
  ADD KEY `v2_project_head_subheads_plot_id_fk` (`plot_id`),
  ADD KEY `v2_project_head_subheads_customer_id_fk` (`customer_id`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `project_head_subheads`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=582;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('project_head_subheads'); }
};
