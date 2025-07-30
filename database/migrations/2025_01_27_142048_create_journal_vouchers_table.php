<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJournalVouchersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('journal_vouchers', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->string('voucher_number')->unique(); // Voucher number
            $table->string('reference')->nullable(); // Reference
            $table->text('description')->nullable(); // Description
            $table->date('date'); // Voucher date
            $table->decimal('total_debit', 15, 2); // Total debit amount
            $table->decimal('total_credit', 15, 2); // Total credit amount
            $table->unsignedBigInteger('created_by'); // User who created
            $table->unsignedBigInteger('updated_by')->nullable(); // User who updated
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending'); // Status
            $table->timestamps(); // Created and updated timestamps
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('journal_vouchers');
    }
}
