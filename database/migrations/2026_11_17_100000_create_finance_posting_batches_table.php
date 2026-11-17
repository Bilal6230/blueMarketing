<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `finance_posting_batches` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `voucher_series` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `voucher_number` bigint UNSIGNED DEFAULT NULL,
  `request_key` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload_sha256` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `effective_date` date NOT NULL,
  `posted_by` bigint UNSIGNED DEFAULT NULL,
  `posted_at` timestamp NULL DEFAULT NULL,
  `reversal_of_batch_id` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `finance_posting_batches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `v2_posting_project_idempotency_unique` (`project_id`,`request_key`),
  ADD UNIQUE KEY `v2_one_reversal_per_batch_unique` (`reversal_of_batch_id`),
  ADD KEY `finance_posting_batches_posted_by_foreign` (`posted_by`),
  ADD KEY `v2_posting_project_date_index` (`project_id`,`effective_date`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `finance_posting_batches`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('finance_posting_batches'); }
};
