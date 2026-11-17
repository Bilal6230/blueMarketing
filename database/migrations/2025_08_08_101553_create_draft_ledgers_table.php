<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One-table baseline generated from verified MySQL 8.4.3 bluenew export. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0' 
CREATE TABLE `draft_ledgers` (
  `id` bigint UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `reference` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type_id` bigint DEFAULT NULL,
  `customer_id` bigint UNSIGNED DEFAULT NULL,
  `project_id` bigint UNSIGNED DEFAULT NULL,
  `plot_id` bigint UNSIGNED DEFAULT NULL,
  `payment_type` bigint DEFAULT NULL,
  `t_number` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_id` bigint UNSIGNED DEFAULT NULL,
  `passing_date` timestamp NULL DEFAULT NULL,
  `passing_status` bigint NOT NULL DEFAULT '0',
  `check_history` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `transaction_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_in` decimal(10,2) DEFAULT NULL,
  `amount_out` decimal(10,2) DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` bigint DEFAULT NULL,
  `is_approve` bigint DEFAULT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `bank_post_at` timestamp NULL DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `project_head_subheads_id` bigint UNSIGNED DEFAULT NULL,
  `detail` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `create_by` int DEFAULT NULL,
  `update_by` int DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0 );
        DB::unprepared(<<<'BMDDL1' 
ALTER TABLE `draft_ledgers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `v2_draft_ledgers_customer_id_fk` (`customer_id`),
  ADD KEY `v2_draft_ledgers_project_id_fk` (`project_id`),
  ADD KEY `v2_draft_ledgers_plot_id_fk` (`plot_id`),
  ADD KEY `v2_draft_ledgers_bank_id_fk` (`bank_id`),
  ADD KEY `v2_draft_ledgers_project_head_subheads_id_fk` (`project_head_subheads_id`);
BMDDL1 );
        DB::unprepared(<<<'BMDDL2' 
ALTER TABLE `draft_ledgers`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
BMDDL2 );
    }
    public function down(): void { Schema::dropIfExists('draft_ledgers'); }
};
