<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One-table baseline generated from verified MySQL 8.4.3 bluenew export. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0' 
CREATE TABLE `attendance_device_user_mappings` (
  `id` bigint UNSIGNED NOT NULL,
  `attendance_device_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `device_user_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0 );
        DB::unprepared(<<<'BMDDL1' 
ALTER TABLE `attendance_device_user_mappings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `adm_user_unique` (`attendance_device_id`,`user_id`),
  ADD UNIQUE KEY `adm_device_user_unique` (`attendance_device_id`,`device_user_id`),
  ADD KEY `attendance_device_user_mappings_user_id_foreign` (`user_id`);
BMDDL1 );
        DB::unprepared(<<<'BMDDL2' 
ALTER TABLE `attendance_device_user_mappings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
BMDDL2 );
    }
    public function down(): void { Schema::dropIfExists('attendance_device_user_mappings'); }
};
