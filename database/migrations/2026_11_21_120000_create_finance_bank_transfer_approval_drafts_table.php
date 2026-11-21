<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_bank_transfer_approval_drafts',function(Blueprint $table){
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('transfer_kind',16);
            $table->unsignedBigInteger('bank_account_id');
            $table->unsignedBigInteger('cash_account_id');
            $table->unsignedBigInteger('linked_customer_ledger_id')->nullable();
            $table->date('effective_date');
            $table->decimal('amount',16,2);
            $table->text('narration');
            $table->string('request_key',128);
            $table->string('payload_sha256',64);
            $table->unsignedBigInteger('voucher_number');
            $table->string('status',16)->default('pending');
            $table->unsignedBigInteger('posting_batch_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->unique(['project_id','request_key'],'bank_transfer_draft_request_unique');
            $table->unique(['project_id','transfer_kind','voucher_number'],'bank_transfer_draft_number_unique');
            $table->index(['project_id','transfer_kind','status'],'bank_transfer_draft_queue_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_bank_transfer_approval_drafts');
    }
};
