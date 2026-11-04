<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_definition_status_audits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('definition_type', 20);
            $table->unsignedBigInteger('definition_id');
            $table->boolean('old_status');
            $table->boolean('new_status');
            $table->text('reason');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('changed_at');
            $table->index(['definition_type', 'definition_id'], 'account_status_audit_definition_idx');
            $table->index('changed_at', 'account_status_audit_changed_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_definition_status_audits');
    }
};
