<?php

use App\Services\Finance\V2IsolatedConnectionGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        V2IsolatedConnectionGuard::assertAllowed();
        foreach (['projects', 'users', 'finance_posting_batches', 'customer_ledger', 'labour_ledgers', 'labour_attendances', 'journal_vouchers', 'bookings', 'charge_types'] as $table) {
            if (!Schema::hasTable($table)) { throw new RuntimeException('Original workflow source schema is missing.'); }
        }
        if (!Schema::hasTable('finance_original_customer_requests')) {
            Schema::create('finance_original_customer_requests', function (Blueprint $t): void {
                $t->id(); $t->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $t->string('request_key', 128); $t->char('payload_sha256', 64); $t->char('source_sha256', 64);
                $t->foreignId('customer_ledger_id')->unique()->constrained('customer_ledger')->restrictOnDelete();
                $t->timestamps(); $t->unique(['project_id', 'request_key'], 'original_customer_request_unique');
            });
        }
        if (!Schema::hasTable('finance_labour_payment_members')) {
            Schema::create('finance_labour_payment_members', function (Blueprint $t): void {
                $t->foreignId('labour_ledger_id')->primary()->constrained('labour_ledgers')->restrictOnDelete();
                $t->foreignId('customer_ledger_id')->constrained('customer_ledger')->restrictOnDelete(); $t->timestamps();
            });
        }
        if (!Schema::hasTable('finance_labour_attendance_journals')) {
            Schema::create('finance_labour_attendance_journals', function (Blueprint $t): void {
                $t->foreignId('labour_attendance_id')->primary()->constrained('labour_attendances')->restrictOnDelete();
                $t->foreignId('journal_voucher_id')->constrained('journal_vouchers')->restrictOnDelete(); $t->timestamps();
            });
        }
        if (!Schema::hasTable('finance_extra_charge_sources')) {
            Schema::create('finance_extra_charge_sources', function (Blueprint $t): void {
                $t->foreignId('customer_ledger_id')->primary()->constrained('customer_ledger')->restrictOnDelete();
                $t->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
                $t->foreignId('charge_type_id')->constrained('charge_types')->restrictOnDelete(); $t->timestamps();
            });
        }
        if (!Schema::hasTable('finance_extra_charge_payment_sources')) {
            Schema::create('finance_extra_charge_payment_sources', function (Blueprint $t): void {
                $t->foreignId('customer_ledger_id')->primary()->constrained('customer_ledger')->restrictOnDelete();
                $t->unsignedBigInteger('charge_customer_ledger_id');
                $t->foreign('charge_customer_ledger_id', 'ec_payment_charge_fk')->references('customer_ledger_id')->on('finance_extra_charge_sources')->restrictOnDelete();
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('finance_bank_settlement_events')) {
            Schema::create('finance_bank_settlement_events', function (Blueprint $t): void {
                $t->id(); $t->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $t->foreignId('customer_ledger_id')->constrained('customer_ledger')->restrictOnDelete();
                $t->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
                $t->foreignId('posting_batch_id')->nullable()->constrained('finance_posting_batches')->restrictOnDelete();
                $t->string('request_key', 128); $t->char('payload_sha256', 64);
                $t->unsignedTinyInteger('from_status'); $t->unsignedTinyInteger('to_status');
                $t->date('effective_date')->nullable(); $t->text('note'); $t->timestamp('created_at');
                $t->unique(['project_id', 'request_key'], 'bank_settlement_event_request_unique');
            });
        }
    }

    public function down(): void
    {
        throw new LogicException('Financial custody must be preserved; use a reviewed forward migration.');
    }
};
