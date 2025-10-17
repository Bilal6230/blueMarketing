<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCommisionVouchersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('commision_vouchers', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->string('voucher_number')->unique(); // Voucher number
            $table->unsignedBigInteger('project_id'); // Foreign key to JournalVoucher
            $table->string('reference')->nullable(); // Reference
            $table->text('description')->nullable(); // Description
            $table->date('date'); // Voucher date
            $table->decimal('total_debit', 15, 2); // Total debit amount
            $table->decimal('total_credit', 15, 2); // Total credit amount
            $table->unsignedBigInteger('created_by'); // User who created
            $table->unsignedBigInteger('updated_by')->nullable(); // User who updated
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending'); // Status
            $table->timestamps(); // Created and updated timestamps
            $table->softDeletes(); // This creates a DATETIME `deleted_at` column

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('commision_vouchers');
    }
}
