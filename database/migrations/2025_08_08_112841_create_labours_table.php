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
        Schema::create('labours', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('cnic')->unique();
            $table->string('phone')->nullable();
            $table->decimal('daily_wage', 10, 2);
            $table->date('join_date');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->unsignedBigInteger('project_id');
            $table->foreign('project_id')
                ->references('id')->on('projects')
                ->onDelete('cascade');
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
        Schema::dropIfExists('labours');
    }
};
