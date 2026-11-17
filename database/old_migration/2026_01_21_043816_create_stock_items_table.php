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
        // database/migrations/2026_01_21_000001_create_stock_items_table.php
        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('unit', 40);
            $table->unsignedBigInteger('default_rate_minor')->default(0); // money minor units
            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('stock_items');
    }
};
