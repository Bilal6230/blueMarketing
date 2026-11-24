<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('daily_voucher_approval_policies')
            && !Schema::hasColumn('daily_voucher_approval_policies','reversal_enabled')) {
            Schema::table('daily_voucher_approval_policies', function (Blueprint $table): void {
                // Keep existing reversal behaviour until an admin explicitly disables it.
                $table->boolean('reversal_enabled')->default(true);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('daily_voucher_approval_policies')
            && Schema::hasColumn('daily_voucher_approval_policies','reversal_enabled')) {
            Schema::table('daily_voucher_approval_policies', function (Blueprint $table): void {
                $table->dropColumn('reversal_enabled');
            });
        }
    }
};
