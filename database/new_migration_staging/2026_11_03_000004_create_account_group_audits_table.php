<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One-table baseline generated from verified MySQL 8.4.3 bluenew export. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0' 
CREATE TABLE `account_group_audits` (
  `id` bigint UNSIGNED NOT NULL,
  `account_group_id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `head_accounting_id` bigint UNSIGNED DEFAULT NULL,
  `event` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `before_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `after_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `changed_by` bigint UNSIGNED DEFAULT NULL,
  `changed_at` timestamp NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
BMDDL0 );
        DB::unprepared(<<<'BMDDL1' 
ALTER TABLE `account_group_audits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_group_audit_scope_fk` (`account_group_id`,`project_id`),
  ADD KEY `account_group_audits_head_accounting_id_foreign` (`head_accounting_id`),
  ADD KEY `account_group_audit_group_time_idx` (`project_id`,`account_group_id`,`changed_at`);
BMDDL1 );
        DB::unprepared(<<<'BMDDL2' 
ALTER TABLE `account_group_audits`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;
BMDDL2 );
    }
    public function down(): void { Schema::dropIfExists('account_group_audits'); }
};
