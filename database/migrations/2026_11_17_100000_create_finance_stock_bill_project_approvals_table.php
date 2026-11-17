<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `finance_stock_bill_project_approvals` (
  `stock_bill_id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `approved_by` bigint UNSIGNED NOT NULL,
  `evidence_reference` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_sha256` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `approved_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `finance_stock_bill_project_approvals`
  ADD PRIMARY KEY (`stock_bill_id`),
  ADD KEY `finance_stock_bill_project_approvals_project_id_foreign` (`project_id`),
  ADD KEY `finance_stock_bill_project_approvals_approved_by_foreign` (`approved_by`);
BMDDL1);
    }
    public function down(): void { Schema::dropIfExists('finance_stock_bill_project_approvals'); }
};
