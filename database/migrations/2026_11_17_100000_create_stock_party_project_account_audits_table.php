<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `stock_party_project_account_audits` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `stock_party_id` bigint UNSIGNED NOT NULL,
  `previous_account_id` bigint UNSIGNED DEFAULT NULL,
  `new_account_id` bigint UNSIGNED NOT NULL,
  `actor_id` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `stock_party_project_account_audits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stock_party_account_audit_party_fk` (`stock_party_id`),
  ADD KEY `stock_party_account_audit_old_fk` (`previous_account_id`,`project_id`),
  ADD KEY `stock_party_account_audit_new_fk` (`new_account_id`,`project_id`),
  ADD KEY `stock_party_account_audit_lookup_idx` (`project_id`,`stock_party_id`,`created_at`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `stock_party_project_account_audits`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('stock_party_project_account_audits'); }
};
