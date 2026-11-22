<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One dedicated request table is necessary because daily_voucher_approvals has
 * a UNIQUE (source_table, source_id), while bank sources can be corrected repeatedly.
 * All per-voucher financial history still uses existing edit snapshots.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('finance_bank_correction_requests')) return;
        Schema::create('finance_bank_correction_requests', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('project_id');
            $t->string('source_method', 12);
            $t->unsignedBigInteger('source_id');
            $t->unsignedBigInteger('submitted_by');
            $t->unsignedBigInteger('assigned_approver_id')->nullable();
            $t->unsignedBigInteger('decided_by')->nullable();
            $t->string('status',16)->default('pending');
            $t->char('fingerprint',64);
            $t->decimal('proposed_amount',18,2);
            $t->longText('proposed_vouchers_json');
            $t->string('reason',500);
            $t->string('decision_reason',500)->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
            $t->index(['project_id','source_method','source_id','status'],'bank_corr_source_status');
            $t->index(['project_id','assigned_approver_id','status'],'bank_corr_approver_queue');
        });
    }

    public function down(): void
    {
        // Financial correction requests and approval evidence are never silently dropped.
    }
};
