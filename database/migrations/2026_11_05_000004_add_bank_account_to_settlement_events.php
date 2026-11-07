<?php

use App\Services\Finance\V2IsolatedConnectionGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        V2IsolatedConnectionGuard::assertAllowed();
        if (!Schema::hasTable('finance_bank_settlement_events') || !Schema::hasTable('project_bank_accounts')
            || Schema::hasColumn('finance_bank_settlement_events', 'bank_account_id')) {
            return;
        }
        Schema::table('finance_bank_settlement_events', function (Blueprint $table): void {
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->foreign(['bank_account_id', 'project_id'], 'bank_settlement_account_project_fk')
                ->references(['id', 'project_id'])->on('project_bank_accounts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        throw new LogicException('Bank settlement audit identity must be preserved; use a reviewed forward migration.');
    }
};
