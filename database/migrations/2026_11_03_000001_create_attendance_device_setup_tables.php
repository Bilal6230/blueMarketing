<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('attendance_devices')) {
            Schema::create('attendance_devices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->string('name')->default('Main Attendance Terminal');
                $table->string('vendor')->nullable();
                $table->string('model');
                $table->string('host', 255)->nullable();
                $table->unsignedSmallInteger('port')->default(80);
                $table->string('connection_type', 30)->default('network');
                $table->string('protocol', 80)->nullable();
                $table->string('username')->nullable();
                $table->text('password')->nullable();
                $table->boolean('enabled')->default(false);
                $table->timestamps();
                $table->index(['project_id', 'name']);
            });
        }

        if (!Schema::hasTable('attendance_device_user_mappings')) {
            Schema::create('attendance_device_user_mappings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('attendance_device_id')->constrained('attendance_devices')->restrictOnDelete();
                $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
                $table->string('device_user_id', 100);
                $table->timestamps();
            });
        }

        if (!Schema::hasIndex('attendance_device_user_mappings', 'adm_user_unique')) {
            Schema::table('attendance_device_user_mappings', function (Blueprint $table) {
                $table->unique(['attendance_device_id', 'user_id'], 'adm_user_unique');
            });
        }
        if (!Schema::hasIndex('attendance_device_user_mappings', 'adm_device_user_unique')) {
            Schema::table('attendance_device_user_mappings', function (Blueprint $table) {
                $table->unique(['attendance_device_id', 'device_user_id'], 'adm_device_user_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_device_user_mappings');
        Schema::dropIfExists('attendance_devices');
    }
};
