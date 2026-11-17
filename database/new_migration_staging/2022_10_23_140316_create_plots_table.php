<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `plots` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `size` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_corner` int NOT NULL DEFAULT '0',
  `project_id` bigint UNSIGNED NOT NULL DEFAULT '1',
  `road_id` bigint UNSIGNED NOT NULL,
  `facing_id` bigint UNSIGNED NOT NULL,
  `is_active` int NOT NULL,
  `sold` int DEFAULT '0',
  `plot_history` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `create_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `plot_category_definition_id` bigint UNSIGNED DEFAULT NULL,
  `plot_type_definition_id` bigint UNSIGNED DEFAULT NULL,
  `plot_size_definition_id` bigint UNSIGNED DEFAULT NULL,
  `plot_unit_definition_id` bigint UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `plots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `v2_plots_project_id_fk` (`project_id`),
  ADD KEY `v2_plots_road_id_fk` (`road_id`),
  ADD KEY `v2_plots_facing_id_fk` (`facing_id`),
  ADD KEY `plot_category_definition_id_project_fk` (`plot_category_definition_id`,`project_id`),
  ADD KEY `plot_type_definition_id_project_fk` (`plot_type_definition_id`,`project_id`),
  ADD KEY `plot_size_definition_id_project_fk` (`plot_size_definition_id`,`project_id`),
  ADD KEY `plot_unit_definition_id_project_fk` (`plot_unit_definition_id`,`project_id`),
  ADD KEY `plot_category_definition_id_idx` (`plot_category_definition_id`),
  ADD KEY `plot_type_definition_id_idx` (`plot_type_definition_id`),
  ADD KEY `plot_size_definition_id_idx` (`plot_size_definition_id`),
  ADD KEY `plot_unit_definition_id_idx` (`plot_unit_definition_id`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `plots`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=736;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('plots'); }
};
