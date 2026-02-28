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
        // 000004_create_stock_entry_lines_table.php
        Schema::create('stock_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained('stock_entries')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('stock_items');

            $table->decimal('qty', 18, 3);
            $table->unsignedBigInteger('rate_minor');
            $table->unsignedBigInteger('amount_minor');

            $table->timestamps();

            $table->index(['entry_id', 'item_id']);
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('stock_entry_lines');
    }
};
