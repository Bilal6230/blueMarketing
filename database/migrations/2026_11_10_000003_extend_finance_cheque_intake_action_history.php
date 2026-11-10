<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('finance_cheque_intake_actions')) {
            throw new \RuntimeException('Cheque action history migration 2026_11_10_000002 must be applied first.');
        }
        foreach ([
            'verification_confirmed_at' => function (Blueprint $table): void { $table->timestamp('verification_confirmed_at')->nullable(); },
            'bank_statement_reference' => function (Blueprint $table): void { $table->string('bank_statement_reference', 150)->nullable(); },
            'reason' => function (Blueprint $table): void { $table->string('reason', 1000)->nullable(); },
            'memo_path' => function (Blueprint $table): void { $table->string('memo_path')->nullable(); },
        ] as $name => $add) {
            if (!Schema::hasColumn('finance_cheque_intake_actions', $name)) {
                Schema::table('finance_cheque_intake_actions', $add);
            }
        }
    }

    public function down(): void
    {
        // Never delete financial lifecycle evidence on rollback.
    }
};
