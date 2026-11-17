<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One-table baseline generated from verified MySQL 8.4.3 bluenew export. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `finance_bank_transfer_sources` (
  `id` bigint UNSIGNED NOT NULL,
  `posting_batch_id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `bank_account_id` bigint UNSIGNED NOT NULL,
  `linked_customer_ledger_id` bigint UNSIGNED DEFAULT NULL,
  `transfer_kind` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(16,2) NOT NULL,
  `narration` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `finance_bank_transfer_sources`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bank_transfer_batch_unique` (`posting_batch_id`),
  ADD UNIQUE KEY `bank_transfer_cheque_source_unique` (`linked_customer_ledger_id`),
  ADD KEY `bank_transfer_project_kind_idx` (`project_id`,`transfer_kind`,`posting_batch_id`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `finance_bank_transfer_sources`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('finance_bank_transfer_sources'); }
};
