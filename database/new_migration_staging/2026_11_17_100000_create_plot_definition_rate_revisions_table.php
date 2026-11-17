<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `plot_definition_rate_revisions` (
  `id` bigint UNSIGNED NOT NULL,
  `plot_definition_id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `rate_per_unit` decimal(12,2) NOT NULL,
  `effective_from` date NOT NULL,
  `changed_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `plot_definition_rate_revisions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `plot_definition_rate_effective_unique` (`plot_definition_id`,`effective_from`),
  ADD KEY `plot_definition_rate_project_fk` (`project_id`),
  ADD KEY `plot_definition_rate_definition_project_fk` (`plot_definition_id`,`project_id`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `plot_definition_rate_revisions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('plot_definition_rate_revisions'); }
};
