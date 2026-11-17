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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->integer('project_id');
            $table->integer('customer_id'); // Assuming you have a customers table
            $table->integer('plot_id');
            $table->tinyInteger('plot_type')->default(0); // Assuming plot_type is a tinyint
            $table->string('plot_size');
            $table->decimal('plot_rate', 10, 2);
            $table->integer('is_corner')->default(0);//
            $table->integer('is_park')->default(0);//
            $table->decimal('park_facing', 10, 2);
            $table->decimal('carner_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->integer('broker_id');
            $table->timestamp('booking_date');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->foreignId('user_id')->constrained(); // Assuming you have a users table
            $table->enum('cancel_status', ['0', '1'])->default('0');
            $table->longText('reason')->nullable();
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
        Schema::dropIfExists('bookings');
    }
};
