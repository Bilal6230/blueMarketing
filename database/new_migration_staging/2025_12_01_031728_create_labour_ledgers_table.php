<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `labour_ledgers` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `contractor_id` bigint UNSIGNED DEFAULT NULL,
  `labour_id` bigint UNSIGNED NOT NULL,
  `date` date DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `labour_ledgers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `labour_ledgers_labour_id_foreign` (`labour_id`),
  ADD KEY `idx_labour_ledgers_project_id` (`project_id`),
  ADD KEY `labour_ledgers_project_labour_idx` (`project_id`,`labour_id`),
  ADD KEY `labour_ledgers_project_contractor_idx` (`project_id`,`contractor_id`),
  ADD KEY `labour_ledgers_labour_project_fk` (`labour_id`,`project_id`),
  ADD KEY `labour_ledgers_contractor_project_fk` (`contractor_id`,`project_id`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `labour_ledgers`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=628;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('labour_ledgers'); }
};
