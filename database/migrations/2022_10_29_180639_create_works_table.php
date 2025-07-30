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
        Schema::create('works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads');
            $table->longText("comment")->nullable();
            $table->string("call_duration")->nullable();
            $table->integer("call_status")->nullable();
            $table->string("recording_path")->nullable();
            $table->integer('type')->default(1);
            $table->integer('user_id')->nullable();
            $table->timestamp('follow_up')->nullable();
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
        Schema::dropIfExists('works');
    }
};
