<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One-table baseline generated from verified MySQL 8.4.3 bluenew export. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `finance_extra_charge_payment_sources` (
  `customer_ledger_id` bigint UNSIGNED NOT NULL,
  `charge_customer_ledger_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `finance_extra_charge_payment_sources`
  ADD PRIMARY KEY (`customer_ledger_id`),
  ADD KEY `ec_payment_charge_fk` (`charge_customer_ledger_id`);
BMDDL1);
    }
    public function down(): void { Schema::dropIfExists('finance_extra_charge_payment_sources'); }
};
