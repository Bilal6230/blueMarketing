<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `project_custom_payment_methods` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_method_code` bigint NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `updated_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `project_custom_payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `project_custom_payment_code_uq` (`project_id`,`code`),
  ADD UNIQUE KEY `project_custom_payment_scope_uq` (`id`,`project_id`,`base_method_code`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `project_custom_payment_methods`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('project_custom_payment_methods'); }
};
