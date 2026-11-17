<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One-table baseline generated from verified MySQL 8.4.3 bluenew export. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `bookings` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `customer_id` bigint UNSIGNED NOT NULL,
  `plot_id` bigint UNSIGNED NOT NULL,
  `plot_type` tinyint NOT NULL DEFAULT '0',
  `plot_size` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `plot_rate` decimal(10,2) NOT NULL,
  `is_corner` int NOT NULL DEFAULT '0',
  `is_park` int NOT NULL DEFAULT '0',
  `park_facing` decimal(10,2) NOT NULL,
  `carner_price` decimal(10,2) NOT NULL,
  `dicount_value` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `broker_id` bigint UNSIGNED DEFAULT NULL,
  `booking_date` timestamp NOT NULL,
  `profile_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `user_id` bigint UNSIGNED NOT NULL,
  `cancel_status` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `cancel_type` enum('payment_not_received','plot_purchase','resale','') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancel_effect` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancel_sale_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `cancel_adjustment_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `cancel_net_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `cancel_posted_at` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bookings_user_id_foreign` (`user_id`),
  ADD KEY `v2_bookings_project_id_fk` (`project_id`),
  ADD KEY `v2_bookings_customer_id_fk` (`customer_id`),
  ADD KEY `v2_bookings_plot_id_fk` (`plot_id`),
  ADD KEY `v2_bookings_broker_id_fk` (`broker_id`);
BMDDL1);
        DB::unprepared(<<<'BMDDL2'
ALTER TABLE `bookings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=241;
BMDDL2);
    }
    public function down(): void { Schema::dropIfExists('bookings'); }
};
