<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('finance_online_intakes')) return;
        Schema::create('finance_online_intakes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('reference', 120);
            $table->string('reference_key', 120);
            $table->date('transaction_date');
            $table->decimal('amount', 15, 2);
            $table->string('type', 10); // receipt | payment
            $table->string('sending_bank', 150);
            $table->string('sending_account', 150);
            $table->string('receiving_bank', 150);
            $table->string('receiving_account', 150);
            $table->string('sender_name', 150);
            $table->string('receiver_name', 150);
            $table->unsignedBigInteger('account_mapping_id');
            $table->string('purpose', 500);
            $table->text('remarks')->nullable();
            $table->string('proof_path')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->foreign('project_id')->references('id')->on('projects')->restrictOnDelete();
            $table->unique(['project_id','reference_key'], 'fin_online_unique_project_ref');
            $table->index(['project_id','type','status','transaction_date'], 'fin_online_list_idx');
            $table->index(['project_id','account_mapping_id'], 'fin_online_account_idx');
        });
    }
    public function down(): void
    {
        // Never silently destroy financial source evidence.
    }
};
