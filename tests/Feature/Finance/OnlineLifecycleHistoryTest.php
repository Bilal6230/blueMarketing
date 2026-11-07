<?php

namespace Tests\Feature\Finance;

use App\Models\CustomerLedger;
use App\Services\Finance\OnlineLifecycleHistory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OnlineLifecycleHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropAllTables();
        Schema::create('customer_ledger', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('project_id'); $table->string('transaction_type', 12);
            $table->unsignedTinyInteger('payment_type')->nullable(); $table->string('t_number', 100)->nullable();
            $table->unsignedBigInteger('bank_id')->nullable(); $table->date('passing_date')->nullable();
            $table->unsignedTinyInteger('passing_status')->nullable(); $table->timestamps();
        });
        $migration = require database_path('migrations/2026_11_05_000003_create_finance_online_lifecycle_events.php');
        $migration->up();
    }

    public function test_online_capture_and_status_transitions_append_project_scoped_metadata(): void
    {
        $source = CustomerLedger::create([
            'project_id' => 7, 'transaction_type' => 'CR', 'payment_type' => 2,
            't_number' => 'TXN-101', 'bank_id' => 14, 'passing_date' => '2026-11-05', 'passing_status' => 0,
        ]);
        $history = app(OnlineLifecycleHistory::class);
        $history->record($source, 'confirmed', 0, 1, 'Matched with bank statement');
        $history->record($source, 'rejected', 1, 2, 'Reconciliation reversed');

        $events = $history->events(7, $source->id);
        $this->assertSame(['captured', 'confirmed', 'rejected'], $events->pluck('event_type')->all());
        $this->assertSame(0, $history->events(8, $source->id)->count());
        $this->assertSame('TXN-101', $events->last()->transaction_number);
        $this->assertSame(14, (int) $events->last()->bank_id);
    }

    public function test_event_reader_is_safe_before_lifecycle_migration(): void
    {
        Schema::dropIfExists('finance_online_lifecycle_events');
        $this->assertCount(0, app(OnlineLifecycleHistory::class)->events(7, 10));
        $source = new CustomerLedger(['project_id' => 7, 'payment_type' => 2]);
        $source->setRawAttributes(['id' => 10, 'project_id' => 7, 'payment_type' => 2], true);
        app(OnlineLifecycleHistory::class)->record($source, 'confirmed', 0, 1, 'safe no-op');
        $this->assertTrue(true);
    }
}
