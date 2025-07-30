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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->integer('gender')->nullable();
            $table->string('nic_number')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('mobile_number')->nullable();
            $table->integer('area_id')->nullable();
            $table->integer('type')->nullable();
            $table->string('business')->nullable();
            $table->string('designation')->nullable();
            $table->integer('zone_id')->nullable();
            $table->longText('home_address')->nullable();
            $table->longText('office_address')->nullable();
            $table->integer('follow_id')->default(1);
            $table->timestamp('follow_up')->useCurrent();
            $table->integer('follow_status')->default(6);
            $table->integer('is_active');
            $table->integer('create_by');


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
        Schema::dropIfExists('leads');
    }
};
