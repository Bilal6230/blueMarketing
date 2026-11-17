<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `finance_posting_lines` (
  `id` bigint UNSIGNED NOT NULL,
  `posting_batch_id` bigint UNSIGNED NOT NULL,
  `project_head_subhead_id` bigint UNSIGNED NOT NULL,
  `debit` decimal(18,2) NOT NULL DEFAULT '0.00',
  `credit` decimal(18,2) NOT NULL DEFAULT '0.00',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `legacy_ledger_id` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `finance_posting_lines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `finance_posting_lines_legacy_ledger_id_unique` (`legacy_ledger_id`),
  ADD KEY `finance_posting_lines_project_head_subhead_id_foreign` (`project_head_subhead_id`),
  ADD KEY `v2_posting_line_account_index` (`posting_batch_id`,`project_head_subhead_id`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `finance_posting_lines`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('finance_posting_lines'); }
};
