<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One-table baseline generated from verified MySQL 8.4.3 bluenew export. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0' 
CREATE TABLE `employee_payroll_payment_links` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `payroll_run_id` bigint UNSIGNED NOT NULL,
  `payroll_run_item_id` bigint UNSIGNED NOT NULL,
  `customer_ledger_id` bigint UNSIGNED NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0 );
        DB::unprepared(<<<'BMDDL1' 
ALTER TABLE `employee_payroll_payment_links`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payroll_payment_item_source_uq` (`payroll_run_item_id`,`customer_ledger_id`),
  ADD KEY `payroll_payment_run_project_fk` (`payroll_run_id`,`project_id`),
  ADD KEY `payroll_payment_source_fk` (`customer_ledger_id`),
  ADD KEY `payroll_payment_source_lookup_idx` (`project_id`,`customer_ledger_id`);
BMDDL1 );
        DB::unprepared(<<<'BMDDL2' 
ALTER TABLE `employee_payroll_payment_links`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;
BMDDL2 );
    }
    public function down(): void { Schema::dropIfExists('employee_payroll_payment_links'); }
};
