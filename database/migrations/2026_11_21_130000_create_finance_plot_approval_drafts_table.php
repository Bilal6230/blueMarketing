<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_plot_approval_drafts',function(Blueprint $t){
            $t->id();
            $t->unsignedBigInteger('project_id');
            $t->string('voucher_type',8);
            $t->string('source_number',64);
            $t->date('effective_date');
            $t->string('request_key',128);
            $t->char('payload_sha256',64);
            $t->longText('payload_json');
            $t->string('status',16)->default('pending');
            $t->unsignedBigInteger('posted_source_id')->nullable();
            $t->unsignedBigInteger('posting_batch_id')->nullable();
            $t->unsignedBigInteger('created_by');
            $t->timestamps();
            $t->unique(['project_id','request_key'],'plot_draft_request_unique');
            $t->unique(['project_id','voucher_type','source_number'],'plot_draft_number_unique');
            $t->index(['project_id','voucher_type','status'],'plot_draft_status_index');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('finance_plot_approval_drafts');
    }
};
