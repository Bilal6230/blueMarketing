<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `stock_legacy_reconciliation_audits` (
  `id` bigint UNSIGNED NOT NULL,
  `record_type` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `record_id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `reason` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor_id` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `stock_legacy_reconciliation_audits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `stock_legacy_reconcile_record_uq` (`record_type`,`record_id`),
  ADD KEY `stock_legacy_reconcile_project_idx` (`project_id`,`created_at`),
  ADD KEY `stock_legacy_reconcile_actor_fk` (`actor_id`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `stock_legacy_reconciliation_audits`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('stock_legacy_reconciliation_audits'); }
};
