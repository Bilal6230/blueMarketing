<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive finance metadata only. No legacy ledger mutation or attribution backfill.
 * Do not execute on live hosting without explicit backup/staging signoff.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('ledger_source_attributions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ledger_id')->unique();
            $table->unsignedBigInteger('project_id');
            $table->string('source_kind', 32);
            $table->unsignedBigInteger('source_id');
            $table->string('evidence_kind', 48);
            $table->string('evidence_reference', 191)->nullable();
            $table->unsignedBigInteger('attributed_by')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'source_kind', 'source_id'], 'ledger_source_project_kind_id_idx');
            // Intentionally no cascading FKs until legacy orphan/schema audit is approved.
        });

        Schema::create('financial_posting_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('source_kind', 32);
            $table->string('request_key', 128);
            $table->char('payload_hash', 64);
            $table->string('status', 16);
            $table->unsignedBigInteger('voucher_id')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'source_kind', 'request_key'], 'financial_request_unique_scope');
        });
    }

    public function down(): void
    {
        // Explicitly fail closed: migration rollback must not delete financial
        // provenance/idempotency metadata once postings may depend on it.
        throw new \LogicException('Financial metadata tables require a reviewed data-preserving rollback plan.');
    }
};
