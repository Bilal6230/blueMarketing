<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('project_bank_accounts', function (Blueprint $table) {
            $table->string('display_title', 120)->nullable()->after('account_title');
        });
        Schema::create('project_cash_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('account_mapping_id');
            $table->string('display_title',120);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['project_id','account_mapping_id'],'cash_project_mapping_unique');
            $table->index(['project_id','is_active']);
        });
        Schema::create('project_cash_account_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('cash_account_id');
            $table->string('action',40);
            $table->json('old_values')->nullable();
            $table->json('new_values');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['project_id','cash_account_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('project_cash_account_audits');
        Schema::dropIfExists('project_cash_accounts');
        Schema::table('project_bank_accounts', fn (Blueprint $table) => $table->dropColumn('display_title'));
    }
};