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
        Schema::create('plots', function (Blueprint $table) {
            $table->id();
            $table->string('name');//
            $table->string('type');//
            $table->string('size');//
            $table->string('unit');//
            $table->longText('description');
            $table->integer('is_corner')->default(0);//
            $table->integer('project_id');//
            $table->integer('road_id');//
            $table->integer('facing_id');//
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
        Schema::dropIfExists('plots');
    }
};
