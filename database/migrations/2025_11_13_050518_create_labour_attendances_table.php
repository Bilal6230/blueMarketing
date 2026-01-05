<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('labour_attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->foreign('project_id')
                ->references('id')->on('projects')
                ->onDelete('cascade');
            // Links to labour & site/project
            $table->foreignId('labour_id')->constrained()->onDelete('cascade');
            $table->foreignId('site_id')->nullable()->constrained('sites')->onDelete('set null');

            // Attendance details
            $table->date('date');
            $table->enum('status', ['present', 'absent', 'leave', 'holiday', 'not-marked'])->default('not-marked');

            // Work details
            $table->decimal('hours', 5, 2)->default(0);
            $table->decimal('ot_hours', 5, 2)->default(0);
            $table->decimal('rate', 10, 2)->nullable();
            $table->decimal('amount', 10, 2)->default(0);

            // Optional fields for notes or metadata
            $table->string('remarks')->nullable();

            $table->boolean('is_approved')->default(false);
            $table->foreignId('marked_by')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('paid_status', ['paid', 'unpaid'])->default('unpaid');
            $table->enum('voucher_status', ['created', 'notcreated'])->default('notcreated');
            $table->decimal('ratings', 10, 1)->default(0);

            $table->timestamps();

            // Prevent duplicate entries for same labour + date + project
            $table->unique(['labour_id', 'site_id', 'date'], 'unique_labour_attendance');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('labour_attendances');
    }
};

