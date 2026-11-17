<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One-table baseline generated from verified MySQL 8.4.3 bluenew export. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `accounting_expenses` (
  `id` bigint UNSIGNED NOT NULL,
  `project_head_subheads_id` bigint UNSIGNED NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `detail` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no details',
  `cash_in` int NOT NULL DEFAULT '0',
  `create_by` int NOT NULL,
  `is_active` int NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `accounting_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `v2_accounting_expenses_project_head_subheads_id_fk` (`project_head_subheads_id`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `accounting_expenses`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('accounting_expenses'); }
};
