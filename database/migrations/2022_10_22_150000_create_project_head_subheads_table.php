<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProjectHeadSubheadsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('project_head_subheads', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->unsignedBigInteger('project_id')->nullable(); // Foreign key to Project
            $table->unsignedBigInteger('head_accounting_id')->nullable(); // Foreign key to HeadAccounting
            $table->unsignedBigInteger('subhead_accounting_id')->nullable(); // Foreign key to SubheadAccounting
            $table->unsignedBigInteger('plot_id')->nullable(); // Foreign key to Plot
            $table->unsignedBigInteger('customer_id')->nullable(); // Foreign key to Customer
            $table->timestamps(); // Created and updated timestamps

            // Foreign key constraints
            // $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            // $table->foreign('plot_id')->references('id')->on('plots')->onDelete('cascade');
            // $table->foreign('head_accounting_id')->references('id')->on('head_accountings')->onDelete('cascade');
            // $table->foreign('subhead_accounting_id')->references('id')->on('subhead_accountings')->onDelete('cascade');
            // $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('project_head_subheads');
    }
}
