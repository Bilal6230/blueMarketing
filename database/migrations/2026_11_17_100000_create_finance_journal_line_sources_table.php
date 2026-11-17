<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `finance_journal_line_sources` (
  `posting_line_id` bigint UNSIGNED NOT NULL,
  `journal_voucher_detail_id` bigint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `finance_journal_line_sources`
  ADD PRIMARY KEY (`posting_line_id`),
  ADD UNIQUE KEY `finance_journal_line_sources_journal_voucher_detail_id_unique` (`journal_voucher_detail_id`);
BMDDL1);
    }
    public function down(): void { Schema::dropIfExists('finance_journal_line_sources'); }
};
