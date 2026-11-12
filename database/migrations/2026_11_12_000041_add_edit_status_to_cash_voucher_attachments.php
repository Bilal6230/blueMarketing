<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cash_voucher_attachments')) {
            throw new \RuntimeException('Apply the original cash_voucher_attachments migration first.');
        }
        Schema::table('cash_voucher_attachments', function (Blueprint $table) {
            if (!Schema::hasColumn('cash_voucher_attachments', 'edit_snapshot_id')) {
                $table->unsignedBigInteger('edit_snapshot_id')->nullable()->index();
            }
            if (!Schema::hasColumn('cash_voucher_attachments', 'status')) {
                // Existing attachments stay available as approved/applied documents.
                $table->string('status', 16)->default('applied');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('cash_voucher_attachments')) return;
        Schema::table('cash_voucher_attachments', function (Blueprint $table) {
            if (Schema::hasColumn('cash_voucher_attachments', 'edit_snapshot_id')) {
                $table->dropColumn('edit_snapshot_id');
            }
            if (Schema::hasColumn('cash_voucher_attachments', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
