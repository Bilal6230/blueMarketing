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
        // 000007_create_stock_movements_table.php
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->date('movement_date')->index();
            $table->enum('direction', ['IN', 'OUT'])->index();

            $table->foreignId('item_id')->constrained('stock_items');
            $table->decimal('qty', 18, 3);

            $table->unsignedBigInteger('rate_minor')->default(0);
            $table->unsignedBigInteger('amount_minor')->default(0);

            $table->enum('source_type', ['entry', 'bill'])->index();
            $table->unsignedBigInteger('source_id')->index();
            $table->unsignedBigInteger('source_line_id'); // for idempotency

            $table->foreignId('party_id')->nullable()->constrained('stock_parties')->nullOnDelete();
            $table->json('meta')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            $table->unique(['source_type', 'source_line_id']); // prevents double-posting same line
            $table->index(['item_id', 'movement_date']);
        });


    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('stock_movements');
    }
};
