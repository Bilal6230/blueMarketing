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
        Schema::create('draft_ledgers', function (Blueprint $table) {
            $table->id();

            // Customer Ledger Fields
            $table->date('date');
            $table->string('reference', 250)->nullable();
            $table->bigInteger('type_id')->nullable();
            $table->bigInteger('customer_id')->nullable()->unsigned();
            $table->bigInteger('project_id')->nullable();
            $table->bigInteger('plot_id')->nullable();
            $table->bigInteger('payment_type')->nullable();
            $table->string('t_number', 250)->nullable();
            $table->bigInteger('bank_id')->nullable();
            $table->timestamp('passing_date')->nullable();
            $table->bigInteger('passing_status')->default(0);
            $table->longText('check_history')->nullable();
            $table->string('transaction_type', 255)->nullable();
            $table->decimal('amount_in', 10, 2)->nullable();
            $table->decimal('amount_out', 10, 2)->nullable();
            $table->string('description', 255)->nullable();
            $table->bigInteger('is_active')->nullable();
            $table->bigInteger('is_approve')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('bank_post_at')->nullable();

            // Ledger Fields
            $table->string('type', 255)->nullable();
            $table->bigInteger('project_head_subheads_id')->nullable()->unsigned();
            $table->text('detail')->nullable();
            $table->integer('create_by')->nullable();
            $table->integer('update_by')->nullable();
            $table->string('status', 255)->nullable();

            // Common
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
        Schema::dropIfExists('draft_ledgers');
    }
};
