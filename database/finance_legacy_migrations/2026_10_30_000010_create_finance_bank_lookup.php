<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function guard(): void
    {
        \App\Services\Finance\FinanceConnectionGuard::assertAllowed();
        // The default configured connection is the only finance connection.
        // Refuse to overwrite existing tables in up(), not by database name.
    }

    public function up(): void
    {
        $this->guard();
        if (Schema::hasTable('banks')) {
            throw new RuntimeException('Banks table already exists; refusing replacement.');
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
        // A historical fixture may share the ordinary production connection.
        // Do not permit an automated rollback to delete live bank master data.
        throw new \LogicException('Bank lookup history cannot be rolled back destructively.');
    }
};
