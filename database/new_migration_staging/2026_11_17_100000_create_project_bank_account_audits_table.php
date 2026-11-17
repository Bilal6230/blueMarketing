<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `project_bank_account_audits` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `bank_account_id` bigint UNSIGNED NOT NULL,
  `action` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `actor_id` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `project_bank_account_audits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_bank_account_audit_scope_fk` (`bank_account_id`,`project_id`),
  ADD KEY `project_bank_account_audit_idx` (`project_id`,`bank_account_id`,`created_at`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `project_bank_account_audits`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('project_bank_account_audits'); }
};
