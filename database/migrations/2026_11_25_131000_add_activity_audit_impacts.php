<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activity_audit_impacts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('activity_audit_id');
            $table->string('table_name', 100);
            $table->string('operation', 10);
            $table->string('record_id', 100)->nullable();
            $table->string('capture_type', 24); // eloquent_row or sql_statement
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->unsignedInteger('statement_count')->default(1);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['activity_audit_id', 'table_name'], 'audit_impacts_audit_table_index');
            $table->index(['table_name', 'record_id'], 'audit_impacts_table_record_index');
        });
    }

    public function down(): void
    {
        // Retain audit evidence on rollback. No destructive drop.
    }
};
