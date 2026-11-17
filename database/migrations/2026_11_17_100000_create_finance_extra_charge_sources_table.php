<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `finance_extra_charge_sources` (
  `customer_ledger_id` bigint UNSIGNED NOT NULL,
  `booking_id` bigint UNSIGNED NOT NULL,
  `charge_type_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `finance_extra_charge_sources`
  ADD PRIMARY KEY (`customer_ledger_id`),
  ADD KEY `finance_extra_charge_sources_booking_id_foreign` (`booking_id`),
  ADD KEY `finance_extra_charge_sources_charge_type_id_foreign` (`charge_type_id`);
BMDDL1);
    }
    public function down(): void { Schema::dropIfExists('finance_extra_charge_sources'); }
};
