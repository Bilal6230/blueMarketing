<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJournalVoucherDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('journal_voucher_details', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->unsignedBigInteger('journal_voucher_id'); // Foreign key to JournalVoucher
            $table->unsignedBigInteger('account_id'); // Related account ID
            $table->decimal('debit', 15, 2)->default(0); // Debit amount
            $table->decimal('credit', 15, 2)->default(0); // Credit amount
            $table->text('description')->nullable(); // Line item description
            $table->timestamps(); // Created and updated timestamps
            $table->softDeletes(); // This creates a DATETIME `deleted_at` column

            // Foreign key constraints
            $table->foreign('journal_voucher_id')->references('id')->on('journal_vouchers')->onDelete('cascade');
            // $table->foreign('account_id')->references('id')->on('project_head_subheads')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('journal_voucher_details');
    }
}
