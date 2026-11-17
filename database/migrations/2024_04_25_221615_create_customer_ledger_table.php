<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One-table baseline generated from verified MySQL 8.4.3 bluenew export. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0' 
CREATE TABLE `customer_ledger` (
  `id` bigint UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `reference` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type_id` bigint DEFAULT NULL,
  `customer_id` bigint UNSIGNED DEFAULT NULL,
  `contractor_id` bigint UNSIGNED DEFAULT NULL,
  `project_id` bigint UNSIGNED DEFAULT NULL,
  `plot_id` bigint UNSIGNED DEFAULT NULL,
  `payment_type` bigint DEFAULT NULL,
  `t_number` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_id` bigint UNSIGNED DEFAULT NULL,
  `passing_date` timestamp NULL DEFAULT NULL,
  `passing_status` bigint DEFAULT '0',
  `check_history` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `transaction_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount_in` decimal(10,2) NOT NULL,
  `amount_out` decimal(10,2) NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` bigint DEFAULT NULL,
  `is_approve` bigint DEFAULT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `delete_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_post_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `custom_payment_method_id` bigint UNSIGNED DEFAULT NULL,
  `cheque_purpose` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0 );
        DB::unprepared(<<<'BMDDL1' 
ALTER TABLE `customer_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_ledger_customer_id_foreign` (`customer_id`),
  ADD KEY `v2_customer_ledger_project_id_fk` (`project_id`),
  ADD KEY `v2_customer_ledger_plot_id_fk` (`plot_id`),
  ADD KEY `v2_customer_ledger_bank_id_fk` (`bank_id`),
  ADD KEY `customer_ledger_project_contractor_idx` (`project_id`,`contractor_id`),
  ADD KEY `customer_ledger_contractor_project_fk` (`contractor_id`,`project_id`),
  ADD KEY `customer_ledger_custom_method_idx` (`project_id`,`custom_payment_method_id`,`payment_type`),
  ADD KEY `customer_ledger_custom_method_scope_fk` (`custom_payment_method_id`,`project_id`,`payment_type`);
BMDDL1 );
        DB::unprepared(<<<'BMDDL2' 
ALTER TABLE `customer_ledger`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5667;
BMDDL2 );
    }
    public function down(): void { Schema::dropIfExists('customer_ledger'); }
};
