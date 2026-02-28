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
        // 000003_create_stock_entries_table.php
        Schema::create('stock_entries', function (Blueprint $table) {
            $table->id();
            $table->string('slip_no', 30)->unique();
            $table->date('entry_date')->index();
            $table->enum('flow', ['IN', 'OUT'])->index();
            $table->foreignId('party_id')->constrained('stock_parties');
            $table->string('vehicle_no', 80)->nullable();
            $table->string('driver_name', 120)->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('billed_at')->nullable()->index();

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
        Schema::dropIfExists('stock_entries');
    }
};
