<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One-table baseline generated from verified MySQL 8.4.3 bluenew export. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0' 
CREATE TABLE `finance_cheque_lifecycle_events` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `customer_ledger_id` bigint UNSIGNED NOT NULL,
  `event_type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_status` tinyint UNSIGNED DEFAULT NULL,
  `to_status` tinyint UNSIGNED DEFAULT NULL,
  `purpose` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `actor_id` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0 );
        DB::unprepared(<<<'BMDDL1' 
ALTER TABLE `finance_cheque_lifecycle_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cheque_events_project_source_idx` (`project_id`,`customer_ledger_id`,`id`),
  ADD KEY `cheque_events_project_type_idx` (`project_id`,`event_type`,`created_at`);
BMDDL1 );
        DB::unprepared(<<<'BMDDL2' 
ALTER TABLE `finance_cheque_lifecycle_events`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
BMDDL2 );
    }
    public function down(): void { Schema::dropIfExists('finance_cheque_lifecycle_events'); }
};
