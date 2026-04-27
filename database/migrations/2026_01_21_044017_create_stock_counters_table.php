<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 000009_create_stock_counters_table.php
        Schema::create('stock_counters', function (Blueprint $table) {
            $table->string('key', 50)->primary();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('stock_counters');
    }
};
