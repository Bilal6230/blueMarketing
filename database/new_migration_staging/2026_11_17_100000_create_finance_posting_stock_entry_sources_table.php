<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `finance_posting_stock_entry_sources` (
  `posting_batch_id` bigint UNSIGNED NOT NULL,
  `source_kind` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'stock_entry',
  `stock_entry_id` bigint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `finance_posting_stock_entry_sources`
  ADD PRIMARY KEY (`posting_batch_id`),
  ADD UNIQUE KEY `finance_posting_stock_entry_sources_stock_entry_id_unique` (`stock_entry_id`),
  ADD KEY `v2_finance_posting_stock_entry_sources_claim_fk` (`posting_batch_id`,`source_kind`);
BMDDL1);
    }
    public function down(): void { Schema::dropIfExists('finance_posting_stock_entry_sources'); }
};
