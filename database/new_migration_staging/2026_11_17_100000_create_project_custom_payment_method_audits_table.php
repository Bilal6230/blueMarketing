<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `project_custom_payment_method_audits` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `method_id` bigint UNSIGNED NOT NULL,
  `action` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_values` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `new_values` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor_id` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `project_custom_payment_method_audits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_custom_payment_method_audits_project_id_foreign` (`project_id`),
  ADD KEY `project_custom_payment_method_audits_method_id_foreign` (`method_id`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `project_custom_payment_method_audits`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('project_custom_payment_method_audits'); }
};
