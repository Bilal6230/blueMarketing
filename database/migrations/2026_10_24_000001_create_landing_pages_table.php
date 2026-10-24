<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('landing_pages', function (Blueprint $table) {
            $table->id();
            $table->string('theme', 40)->default('corporate');
            $table->json('content')->nullable();
            $table->json('sections')->nullable();
            $table->string('projects_mode', 20)->default('default');
            $table->json('project_ids')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('landing_pages');
    }
};
