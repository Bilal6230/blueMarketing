<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `plot_definition_audits` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `plot_definition_id` bigint UNSIGNED NOT NULL,
  `action` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `reason` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actor_id` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `plot_definition_audits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `plot_definition_audit_definition_project_fk` (`plot_definition_id`,`project_id`),
  ADD KEY `plot_definition_audit_lookup_idx` (`project_id`,`plot_definition_id`,`created_at`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `plot_definition_audits`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('plot_definition_audits'); }
};
