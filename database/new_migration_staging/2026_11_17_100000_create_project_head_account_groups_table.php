<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `project_head_account_groups` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `head_accounting_id` bigint UNSIGNED NOT NULL,
  `account_group_id` bigint UNSIGNED NOT NULL,
  `create_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `project_head_account_groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `project_head_group_head_uq` (`project_id`,`head_accounting_id`),
  ADD KEY `project_head_account_groups_head_accounting_id_foreign` (`head_accounting_id`),
  ADD KEY `project_head_group_scope_fk` (`account_group_id`,`project_id`),
  ADD KEY `project_head_group_group_idx` (`project_id`,`account_group_id`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `project_head_account_groups`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('project_head_account_groups'); }
};
