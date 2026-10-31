<?php

namespace Tests\Feature\Finance;

use App\Http\Controllers\StockController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StockDeletionIntegrityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::getDefaultConnection());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::dropAllTables();
        foreach (['stock_items', 'stock_parties'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->id();
                $table->timestamps();
                $table->softDeletes();
            });
            DB::table($name)->insert(['id' => 1]);
        }
        foreach (['stock_entry_lines', 'stock_bill_lines', 'stock_movements', 'stock_item_balances'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('item_id');
                $table->unsignedBigInteger('party_id')->nullable();
            });
        }
        foreach (['stock_entries', 'stock_bills'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('party_id');
            });
        }
        Schema::create('ledgers', function (Blueprint $table): void {
            $table->id();
            $table->string('reference');
            $table->decimal('amount_in', 15, 2);
        });
        DB::table('ledgers')->insert(['id' => 1, 'reference' => 'STOCK-ENTRY:1', 'amount_in' => '125.50']);
    }

    public static function historyCases(): array
    {
        return [
            ['deleteItem', 'stock_items', 'stock_entry_lines', 'item_id'],
            ['deleteItem', 'stock_items', 'stock_bill_lines', 'item_id'],
            ['deleteItem', 'stock_items', 'stock_movements', 'item_id'],
            ['deleteItem', 'stock_items', 'stock_item_balances', 'item_id'],
            ['deleteParty', 'stock_parties', 'stock_entries', 'party_id'],
            ['deleteParty', 'stock_parties', 'stock_bills', 'party_id'],
            ['deleteParty', 'stock_parties', 'stock_movements', 'party_id'],
        ];
    }

    #[DataProvider('historyCases')]
    public function test_used_master_cannot_delete_source_history(
        string $method, string $master, string $history, string $foreignKey
    ): void {
        $row = ['id' => 1, $foreignKey => 1];
        if ($history === 'stock_movements') {
            $row['item_id'] = 1;
        }
        DB::table($history)->insert($row);
        $response = (new StockController())->{$method}(Request::create('/', 'DELETE'), 1);
        $this->assertSame(409, $response->getStatusCode());
        $this->assertDatabaseHas($master, ['id' => 1]);
        $this->assertDatabaseHas($history, ['id' => 1, $foreignKey => 1]);
        $this->assertDatabaseHas('ledgers', ['id' => 1, 'reference' => 'STOCK-ENTRY:1', 'amount_in' => '125.50']);
    }

    public function test_unused_item_and_party_can_be_deleted_without_touching_ledger(): void
    {
        $controller = new StockController();
        $this->assertSame(200, $controller->deleteItem(Request::create('/', 'DELETE'), 1)->getStatusCode());
        $this->assertSame(200, $controller->deleteParty(Request::create('/', 'DELETE'), 1)->getStatusCode());
        $this->assertSoftDeleted('stock_items', ['id' => 1]);
        $this->assertSoftDeleted('stock_parties', ['id' => 1]);
        $this->assertSame(0, \App\Models\StockItem::count());
        $this->assertSame(0, \App\Models\StockParty::count());
        $this->assertDatabaseHas('ledgers', ['id' => 1, 'amount_in' => '125.50']);
    }
}
