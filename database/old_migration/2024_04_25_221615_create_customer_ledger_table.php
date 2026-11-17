<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customer_ledger', function (Blueprint $table) {
            $table->id();
            $table->date('date'); // Add the 'date' field
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('project_id');
            $table->foreign('customer_id')->references('id')->on('leads')->onDelete('cascade');
            $table->string('transaction_type');
            $table->decimal('amount_in', 10, 2);
            $table->decimal('amount_out', 10, 2);
            $table->string('description')->nullable();
            $table->json('check_history')->nullable(); // Adding the check_history column
            $table->string('delete_reason')->nullable();
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
        Schema::dropIfExists('customer_ledger');
    }
};
