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
        // 000008_create_stock_item_balances_table.php
        Schema::create('stock_item_balances', function (Blueprint $table) {
            $table->foreignId('item_id')->primary()->constrained('stock_items')->cascadeOnDelete();
            $table->decimal('on_hand_qty', 18, 3)->default(0);
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
        Schema::dropIfExists('stock_item_balances');
    }
};
