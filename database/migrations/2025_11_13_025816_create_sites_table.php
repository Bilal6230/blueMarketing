<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `sites` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED DEFAULT NULL,
  `head_accounting_id` bigint UNSIGNED DEFAULT NULL,
  `subhead_accounting_id` bigint UNSIGNED DEFAULT NULL,
  `site_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `site_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `project_head_subhead_id` bigint UNSIGNED DEFAULT NULL,
  `site_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `sites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sites_project_site_code_unique` (`project_id`,`site_code`),
  ADD KEY `idx_sites_project_id` (`project_id`),
  ADD KEY `v2_sites_head_accounting_id_fk` (`head_accounting_id`),
  ADD KEY `v2_sites_subhead_accounting_id_fk` (`subhead_accounting_id`),
  ADD KEY `v2_site_project_account_fk` (`project_head_subhead_id`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `sites`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('sites'); }
};
