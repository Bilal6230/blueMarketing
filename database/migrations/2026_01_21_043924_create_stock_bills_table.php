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
        // 000005_create_stock_bills_table.php
        Schema::create('stock_bills', function (Blueprint $table) {
            $table->id();
            $table->string('bill_no', 30)->unique();
            $table->date('bill_date')->index();
            $table->enum('type', ['purchase', 'sale'])->index();
            $table->foreignId('party_id')->constrained('stock_parties');
            $table->foreignId('entry_id')->nullable()->constrained('stock_entries')->nullOnDelete();
            $table->unsignedBigInteger('total_minor')->default(0);

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
        Schema::dropIfExists('stock_bills');
    }
};
