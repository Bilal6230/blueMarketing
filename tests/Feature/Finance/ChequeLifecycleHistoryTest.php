<?php

namespace Tests\Feature\Finance;

use App\Models\CustomerLedger;
use App\Services\Finance\ChequeLifecycleHistory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ChequeLifecycleHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropAllTables();
        Schema::create('customer_ledger', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('project_id'); $table->string('transaction_type', 12);
            $table->unsignedTinyInteger('payment_type')->nullable(); $table->string('cheque_purpose', 500)->nullable();
            $table->unsignedTinyInteger('passing_status')->nullable(); $table->timestamps();
        });
        $migration = require database_path('migrations/2026_11_05_000002_add_cheque_lifecycle_history.php');
        $migration->up();
    }

    public function test_cheque_status_changes_append_project_scoped_history(): void
    {
        $source = CustomerLedger::create([
            'project_id' => 7, 'transaction_type' => 'CR', 'payment_type' => 3,
            'cheque_purpose' => 'Customer installment', 'passing_status' => 0,
        ]);
        $history = app(ChequeLifecycleHistory::class);
        $history->record($source, 'cleared', 0, 1, 'Bank confirmed');
        $history->record($source, 'returned', 1, 2, 'Signature mismatch');

        $this->assertSame(3, DB::table('finance_cheque_lifecycle_events')->where('customer_ledger_id', $source->id)->count());
        $this->assertSame(['received', 'cleared', 'returned'], $history->events(7, $source->id)->pluck('event_type')->all());
        $this->assertSame(0, $history->events(8, $source->id)->count());
        $this->assertSame('Customer installment', $history->events(7, $source->id)->last()->purpose);
    }

    public function test_event_reader_is_safe_before_lifecycle_migration(): void
    {
        Schema::dropIfExists('finance_cheque_lifecycle_events');
        $this->assertCount(0, app(ChequeLifecycleHistory::class)->events(7, 10));
    }
}
