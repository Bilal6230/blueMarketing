<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `finance_labour_attendance_journals` (
  `labour_attendance_id` bigint UNSIGNED NOT NULL,
  `journal_voucher_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `finance_labour_attendance_journals`
  ADD PRIMARY KEY (`labour_attendance_id`),
  ADD KEY `finance_labour_attendance_journals_journal_voucher_id_foreign` (`journal_voucher_id`);
BMDDL1);
    }
    public function down(): void { Schema::dropIfExists('finance_labour_attendance_journals'); }
};
