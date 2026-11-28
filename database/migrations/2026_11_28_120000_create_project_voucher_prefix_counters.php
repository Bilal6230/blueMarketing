<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('project_voucher_prefix_counters')) return;
        Schema::create('project_voucher_prefix_counters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('series', 4);
            $table->string('prefix', 12);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->unsignedBigInteger('issued_count')->default(0);
            $table->timestamps();
            $table->unique(['project_id','series','prefix'], 'voucher_prefix_series_uq');
            $table->index(['project_id','prefix'], 'voucher_prefix_project_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_voucher_prefix_counters');
    }
};
