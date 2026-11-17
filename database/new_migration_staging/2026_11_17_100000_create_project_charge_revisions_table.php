<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `project_charge_revisions` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `corner_charge_percent` decimal(5,2) NOT NULL,
  `facing_charge_percent` decimal(5,2) NOT NULL,
  `effective_from` date NOT NULL,
  `changed_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `project_charge_revisions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `project_charge_revision_effective_unique` (`project_id`,`effective_from`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `project_charge_revisions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('project_charge_revisions'); }
};
