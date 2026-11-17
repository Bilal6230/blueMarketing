<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `finance_labour_payment_members` (
  `labour_ledger_id` bigint UNSIGNED NOT NULL,
  `customer_ledger_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `finance_labour_payment_members`
  ADD PRIMARY KEY (`labour_ledger_id`),
  ADD KEY `finance_labour_payment_members_customer_ledger_id_foreign` (`customer_ledger_id`);
BMDDL1);
    }
    public function down(): void { Schema::dropIfExists('finance_labour_payment_members'); }
};
