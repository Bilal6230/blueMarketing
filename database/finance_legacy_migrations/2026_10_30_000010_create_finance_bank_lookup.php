<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function guard(): void
    {
        $connection = DB::connection();
        $name = (string) $connection->getDatabaseName();
        if (!str_starts_with($name, 'bluemarketing_v2_') &&
            !($connection->getDriverName() === 'sqlite' && $name === ':memory:')) {
            throw new RuntimeException('V2 bank schema is isolated-only; refusing current database.');
        }
    }

    public function up(): void
    {
        $this->guard();
        if (Schema::hasTable('banks')) {
            throw new RuntimeException('V2 banks table already exists; refusing replacement.');
        }

        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('display_name', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->guard();
        Schema::dropIfExists('banks');
    }
};
