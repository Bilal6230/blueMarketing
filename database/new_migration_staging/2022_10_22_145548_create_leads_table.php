<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `leads` (
  `id` bigint UNSIGNED NOT NULL,
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `relate` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `father_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` int DEFAULT NULL,
  `nic_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `area_id` bigint UNSIGNED DEFAULT NULL,
  `type` int DEFAULT NULL,
  `business` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `designation` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zone_id` bigint UNSIGNED DEFAULT NULL,
  `home_address` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `office_address` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `follow_id` bigint UNSIGNED NOT NULL DEFAULT '1',
  `follow_up` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `follow_status` int NOT NULL DEFAULT '6',
  `customer_type` int DEFAULT '0',
  `is_customer` int DEFAULT '0',
  `project_id` bigint UNSIGNED DEFAULT '1',
  `is_active` int NOT NULL,
  `create_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `leads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `v2_leads_area_id_fk` (`area_id`),
  ADD KEY `v2_leads_zone_id_fk` (`zone_id`),
  ADD KEY `v2_leads_follow_id_fk` (`follow_id`),
  ADD KEY `v2_leads_project_id_fk` (`project_id`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `leads`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13399;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('leads'); }
};
