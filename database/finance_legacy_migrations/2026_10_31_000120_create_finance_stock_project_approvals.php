<?php

use App\Services\Finance\V2IsolatedConnectionGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Original stock_entries/stock_bills do NOT contain project_id.
 * Never pretend party_id is a project FK. Require separate explicit
 * project/source authorization before any financial posting can exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        V2IsolatedConnectionGuard::assertAllowed();
        foreach (['projects', 'users', 'stock_entries', 'stock_bills'] as $name) {
            if (!Schema::hasTable($name)) {
                throw new RuntimeException('V2 stock authorization baseline missing: ' . $name);
            }
        }
        foreach (['finance_stock_entry_project_approvals',
            'finance_stock_bill_project_approvals'] as $name) {
            if (Schema::hasTable($name)) {
                throw new RuntimeException('V2 stock approval table already exists: ' . $name);
            }
        }
        Schema::create('finance_stock_entry_project_approvals', function (Blueprint $table): void {
            $table->foreignId('stock_entry_id')->primary()
                ->constrained('stock_entries')->restrictOnDelete();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->string('evidence_reference', 128);
            $table->char('source_sha256', 64);
            $table->timestamp('approved_at');
            $table->timestamps();
        });
        Schema::create('finance_stock_bill_project_approvals', function (Blueprint $table): void {
            $table->foreignId('stock_bill_id')->primary()
                ->constrained('stock_bills')->restrictOnDelete();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->string('evidence_reference', 128);
            $table->char('source_sha256', 64);
            $table->timestamp('approved_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        V2IsolatedConnectionGuard::assertAllowed();
        Schema::dropIfExists('finance_stock_bill_project_approvals');
        Schema::dropIfExists('finance_stock_entry_project_approvals');
    }
};
