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
        // 000002_create_stock_parties_table.php
        Schema::create('stock_parties', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['Supplier', 'Consumer', 'Site'])->index();
            $table->string('name')->index();
            $table->string('mobile', 40)->nullable();
            $table->string('address')->nullable();
            $table->string('head_account')->nullable();
            $table->string('sub_head_account')->nullable();
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
        Schema::dropIfExists('stock_parties');
    }
};
