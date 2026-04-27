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
        // 000006_create_stock_bill_lines_table.php
        Schema::create('stock_bill_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained('stock_bills')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('stock_items');

            $table->decimal('qty', 18, 3);
            $table->unsignedBigInteger('rate_minor');
            $table->unsignedBigInteger('amount_minor');

            $table->timestamps();
            $table->index(['bill_id', 'item_id']);
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('stock_bill_lines');
    }
};
