<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_edit_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('operation', 40);
            $table->text('reason')->nullable();
            $table->json('old_values');
            $table->json('new_values');
            $table->decimal('old_total', 10, 2)->nullable();
            $table->decimal('new_total', 10, 2)->nullable();
            $table->decimal('delta', 10, 2)->nullable();
            $table->decimal('old_accounting_principal', 10, 2)->nullable();
            $table->decimal('delta_from_accounting', 10, 2)->nullable();
            $table->unsignedBigInteger('journal_voucher_id')->nullable();
            $table->string('request_key')->nullable()->unique();
            $table->timestamps();
            $table->index(['booking_id', 'created_at']);
            $table->index(['project_id', 'operation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_edit_audits');
    }
};
