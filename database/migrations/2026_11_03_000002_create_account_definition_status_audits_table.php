<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One-table baseline generated from verified MySQL 8.4.3 bluenew export. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `account_definition_status_audits` (
  `id` bigint UNSIGNED NOT NULL,
  `definition_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `definition_id` bigint UNSIGNED NOT NULL,
  `old_status` tinyint(1) NOT NULL,
  `new_status` tinyint(1) NOT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_by` bigint UNSIGNED DEFAULT NULL,
  `changed_at` timestamp NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `account_definition_status_audits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_status_audit_definition_idx` (`definition_type`,`definition_id`),
  ADD KEY `account_status_audit_changed_at_idx` (`changed_at`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `account_definition_status_audits`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('account_definition_status_audits'); }
};
