<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One table per migration; verified MySQL source DDL. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMDDL0'
CREATE TABLE `finance_plot_purchase_postings` (
  `posting_batch_id` bigint UNSIGNED NOT NULL,
  `source_id` bigint UNSIGNED NOT NULL,
  `source_kind` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'plot_purchase'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
BMDDL0);
        DB::unprepared(<<<'BMDDL1'
ALTER TABLE `finance_plot_purchase_postings`
  ADD PRIMARY KEY (`posting_batch_id`),
  ADD UNIQUE KEY `v2_plot_purch_post_src_uq` (`source_id`),
  ADD KEY `v2_plot_purch_post_claim_fk` (`posting_batch_id`,`source_kind`);
BMDDL1);
    }
    public function down(): void { Schema::dropIfExists('finance_plot_purchase_postings'); }
};
