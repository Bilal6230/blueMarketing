<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('cash_voucher_attachments')) return;
        Schema::create('cash_voucher_attachments', function(Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('customer_ledger_id');
            $table->string('disk',32)->default('local');
            $table->string('path',255);
            $table->string('original_name',255);
            $table->string('mime_type',100);
            $table->unsignedBigInteger('size');
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamps();
            $table->index(['project_id','customer_ledger_id'],'cash_attachment_source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_voucher_attachments');
    }
};
