<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One-table baseline generated from verified MySQL 8.4.3 bluenew export. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0' 
CREATE TABLE `daily_voucher_approval_events` (
  `id` bigint UNSIGNED NOT NULL,
  `approval_id` bigint UNSIGNED NOT NULL,
  `event` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor_id` bigint UNSIGNED NOT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0 );
        DB::unprepared(<<<'BMDDL1' 
ALTER TABLE `daily_voucher_approval_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `daily_voucher_approval_events_history_index` (`approval_id`,`id`);
BMDDL1 );
        DB::unprepared(<<<'BMDDL2' 
ALTER TABLE `daily_voucher_approval_events`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;
BMDDL2 );
    }
    public function down(): void { Schema::dropIfExists('daily_voucher_approval_events'); }
};
