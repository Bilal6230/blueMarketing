<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `stock_party_project_accounts` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `stock_party_id` bigint UNSIGNED NOT NULL,
  `project_head_subhead_id` bigint UNSIGNED NOT NULL,
  `changed_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `stock_party_project_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `stock_party_project_account_unique` (`project_id`,`stock_party_id`),
  ADD KEY `stock_party_project_account_party_fk` (`stock_party_id`),
  ADD KEY `stock_party_project_account_account_fk` (`project_head_subhead_id`,`project_id`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `stock_party_project_accounts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('stock_party_project_accounts'); }
};
