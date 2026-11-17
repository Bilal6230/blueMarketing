<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One-table baseline generated from verified MySQL 8.4.3 bluenew export. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0' 
CREATE TABLE `finance_cheque_voucher_claims` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `cheque_intake_id` bigint UNSIGNED NOT NULL,
  `voucher_type` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `voucher_id` bigint UNSIGNED NOT NULL,
  `linked_by` bigint UNSIGNED NOT NULL,
  `linked_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0 );
        DB::unprepared(<<<'BMDDL1' 
ALTER TABLE `finance_cheque_voucher_claims`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `fin_cheque_single_voucher_claim` (`project_id`,`cheque_intake_id`),
  ADD KEY `fin_cheque_claim_voucher` (`project_id`,`voucher_type`,`voucher_id`),
  ADD KEY `fin_cheque_claim_intake_fk` (`cheque_intake_id`);
BMDDL1 );
        DB::unprepared(<<<'BMDDL2' 
ALTER TABLE `finance_cheque_voucher_claims`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;
BMDDL2 );
    }
    public function down(): void { Schema::dropIfExists('finance_cheque_voucher_claims'); }
};
