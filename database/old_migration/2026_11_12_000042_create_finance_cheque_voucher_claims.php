<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('finance_cheque_intakes')
            || !Schema::hasTable('finance_cheque_intake_actions')) {
            throw new \RuntimeException('Cheque intake and clearing evidence migrations are required.');
        }
        if(!Schema::hasTable('finance_cheque_voucher_claims')) {
            Schema::create('finance_cheque_voucher_claims',function(Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('cheque_intake_id');
                $table->string('voucher_type',16);
                $table->unsignedBigInteger('voucher_id');
                $table->unsignedBigInteger('linked_by');
                $table->timestamp('linked_at');
                $table->timestamps();
                $table->unique(['project_id','cheque_intake_id'],'fin_cheque_single_voucher_claim');
                $table->index(['project_id','voucher_type','voucher_id'],'fin_cheque_claim_voucher');
                $table->foreign('cheque_intake_id','fin_cheque_claim_intake_fk')
                    ->references('id')->on('finance_cheque_intakes')->restrictOnDelete();
            });
        }
    }
    public function down(): void
    {
        // Financial claim/audit evidence must never be dropped by rollback.
    }
};
