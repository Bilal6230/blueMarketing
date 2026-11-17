<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One-table baseline generated from verified MySQL 8.4.3 bluenew export. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `finance_bank_settlement_events` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `customer_ledger_id` bigint UNSIGNED NOT NULL,
  `actor_id` bigint UNSIGNED DEFAULT NULL,
  `posting_batch_id` bigint UNSIGNED DEFAULT NULL,
  `request_key` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload_sha256` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_status` tinyint UNSIGNED NOT NULL,
  `to_status` tinyint UNSIGNED NOT NULL,
  `effective_date` date DEFAULT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `finance_bank_settlement_events`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bank_settlement_event_request_unique` (`project_id`,`request_key`),
  ADD KEY `finance_bank_settlement_events_customer_ledger_id_foreign` (`customer_ledger_id`),
  ADD KEY `finance_bank_settlement_events_actor_id_foreign` (`actor_id`),
  ADD KEY `finance_bank_settlement_events_posting_batch_id_foreign` (`posting_batch_id`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `finance_bank_settlement_events`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('finance_bank_settlement_events'); }
};
