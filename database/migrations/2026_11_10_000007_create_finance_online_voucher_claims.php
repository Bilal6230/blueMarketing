<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('finance_online_intakes')
            || !Schema::hasTable('finance_online_confirmation_events')) {
            throw new \RuntimeException('Online intake and bank-confirmation migrations must run before source-claim contract.');
        }

        // Reserved for Phase 04 bank-voucher writers. Phase 02 does not create claims.
        // Unique source link makes duplicate voucher consumption impossible at DB level.
        if (!Schema::hasTable('finance_online_voucher_claims')) {
            Schema::create('finance_online_voucher_claims', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('online_intake_id');
                $table->string('voucher_type', 16);
                $table->unsignedBigInteger('voucher_id');
                $table->unsignedBigInteger('linked_by');
                $table->timestamp('linked_at');
                $table->timestamps();
                $table->foreign('online_intake_id','fin_online_claim_intake_fk')
                    ->references('id')->on('finance_online_intakes')->restrictOnDelete();
                $table->unique(['project_id','online_intake_id'],'fin_online_one_voucher_per_source');
                $table->index(['project_id','voucher_type','voucher_id'],'fin_online_voucher_lookup');
            });
        }
    }

    public function down(): void
    {
        // Financial voucher linkage evidence must never be silently discarded.
    }
};
