<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `ledgers` (
  `id` bigint UNSIGNED NOT NULL,
  `voucher_number` bigint DEFAULT NULL,
  `delete_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_ledger_id` bigint UNSIGNED DEFAULT NULL,
  `charge_type_id` bigint UNSIGNED DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type_id` int DEFAULT NULL,
  `project_head_subheads_id` bigint UNSIGNED NOT NULL,
  `reference` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_in` decimal(10,2) NOT NULL,
  `amount_out` decimal(10,2) NOT NULL,
  `detail` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `create_by` int DEFAULT NULL,
  `update_by` int DEFAULT NULL,
  `is_active` tinyint NOT NULL DEFAULT '1',
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `journal_voucher_detail_id` bigint UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `ledgers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ledgers_unique_jv_detail` (`journal_voucher_detail_id`),
  ADD KEY `idx_customer_ledger_id` (`customer_ledger_id`),
  ADD KEY `idx_charge_type_id` (`charge_type_id`),
  ADD KEY `v2_ledgers_project_head_subheads_id_fk` (`project_head_subheads_id`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `ledgers`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20453;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('ledgers'); }
};
