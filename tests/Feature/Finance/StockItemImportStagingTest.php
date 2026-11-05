<?php

namespace Tests\Feature\Finance;

use App\Http\Controllers\StockController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StockItemImportStagingTest extends TestCase
{
    public function test_staged_rows_are_linked_to_their_project_batch_without_applying_items(): void
    {
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::dropAllTables();
        Schema::create('projects', fn (Blueprint $table) => $table->id());
        Schema::create('users', fn (Blueprint $table) => $table->id());
        Schema::create('stock_items', function (Blueprint $table): void {
            $table->id(); $table->string('name'); $table->string('unit');
            $table->unsignedBigInteger('default_rate_minor')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps(); $table->softDeletes();
        });
        (require database_path('migrations/2026_11_03_000016_create_stock_item_import_history.php'))->up();
        DB::table('projects')->insert(['id' => 5]);
        (require database_path('migrations/2026_11_03_000024_create_stock_project_item_definitions.php'))->up();
        DB::table('users')->insert(['id' => 7]);

        $user = \Mockery::mock(User::class)->makePartial();
        $user->id = 7;
        $user->shouldReceive('can')->andReturn(true);
        auth()->setUser($user);
        $denied = \Mockery::mock(User::class)->makePartial();
        $denied->id = 8;
        $denied->shouldReceive('can')->andReturn(false);
        $denied->shouldReceive('hasAnyRole')->andReturn(false);
        auth()->setUser($denied);
        $deniedRequest = Request::create('/', 'POST', [], ['selected_action' => 5], [
            'file' => UploadedFile::fake()->createWithContent('denied.csv', "item_id,name,unit,default_rate_minor\n,Denied,Bag,1\n"),
        ]);
        $this->app->instance('request', $deniedRequest);
        try {
            (new StockController())->stageItemImport($deniedRequest);
            $this->fail('Unauthorized stock import was staged.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('stock_item_import_batches', 0);
        auth()->setUser($user);
        $oversized = Request::create('/', 'POST', [], ['selected_action' => 5], [
            'file' => UploadedFile::fake()->create('large.csv', 5121, 'text/csv'),
        ]);
        $this->app->instance('request', $oversized);
        try {
            (new StockController())->stageItemImport($oversized);
            $this->fail('Oversized stock import was staged.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
        }
        $this->assertDatabaseCount('stock_item_import_batches', 0);
        $request = Request::create('/', 'POST', [], ['selected_action' => 5], [
            'file' => UploadedFile::fake()->createWithContent('items.csv', "item_id,name,unit,default_rate_minor\n,Concrete,Bag,100\n"),
        ]);
        $this->app->instance('request', $request);

        $response = (new StockController())->stageItemImport($request);
        $this->assertSame(200, $response->getStatusCode());
        $batchId = $response->getData(true)['data']['batch_id'];
        $this->assertDatabaseHas('stock_item_import_batches', ['id' => $batchId, 'project_id' => 5, 'created_by' => 7, 'status' => 'staged']);
        $this->assertDatabaseHas('stock_item_import_rows', ['batch_id' => $batchId, 'row_number' => 2, 'action' => 'added']);
        $this->assertDatabaseCount('stock_items', 0);

        $foreignProjectRequest = Request::create('/', 'GET', [], ['selected_action' => 6]);
        $this->app->instance('request', $foreignProjectRequest);
        try {
            (new StockController())->previewItemImport($batchId);
            $this->fail('Another project was able to preview this import batch.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
        $foreignApplyRequest = Request::create('/', 'POST', ['confirmed' => '1'], ['selected_action' => 6]);
        $this->app->instance('request', $foreignApplyRequest);
        try {
            (new StockController())->applyItemImport($foreignApplyRequest, $batchId);
            $this->fail('Another project was able to apply this import batch.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }

        $xlsxPath = (new \App\Services\Stock\StockItemExcel())->export([
            (object) ['id' => 42, 'name' => 'Sand', 'unit' => 'CFT', 'default_rate_minor' => 200],
        ]);
        try {
            $xlsxRequest = Request::create('/', 'POST', [], ['selected_action' => 5], [
                'file' => new UploadedFile($xlsxPath, 'items.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            ]);
            $this->app->instance('request', $xlsxRequest);
            $xlsxResponse = (new StockController())->stageItemImport($xlsxRequest);
            $this->assertSame(200, $xlsxResponse->getStatusCode());
            $this->assertDatabaseHas('stock_item_import_rows', ['batch_id' => $xlsxResponse->getData(true)['data']['batch_id'], 'action' => 'failed']);
            $failedBatchId = $xlsxResponse->getData(true)['data']['batch_id'];
        } finally {
            @unlink($xlsxPath);
        }

        $confirm = Request::create('/', 'POST', ['confirmed' => '1'], ['selected_action' => 5]);
        $this->app->instance('request', $confirm);
        try {
            (new StockController())->applyItemImport($confirm, $failedBatchId);
            $this->fail('Failed import rows were applied.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('stock_items', 0);
        $applied = (new StockController())->applyItemImport($confirm, $batchId)->getData(true);
        $this->assertSame(1, $applied['data']['added']);
        $this->assertDatabaseHas('stock_items', ['name' => 'Concrete', 'unit' => 'Bag', 'default_rate_minor' => 100]);
        $this->assertDatabaseHas('stock_project_item_definitions', ['project_id' => 5, 'name' => 'Concrete', 'unit' => 'Bag']);
        $itemId = (int) DB::table('stock_items')->value('id');
        DB::table('projects')->insert(['id' => 6]);
        $foreignUpdate = Request::create('/', 'PUT', ['name' => 'Leak', 'unit' => 'Ton'], ['selected_action' => 6]);
        $this->app->instance('request', $foreignUpdate);
        try {
            (new StockController())->updateItem($foreignUpdate, $itemId);
            $this->fail('Another project was able to edit an unowned stock item.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException|\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $exception) {
            $this->assertDatabaseHas('stock_project_item_definitions', ['project_id' => 5, 'item_id' => $itemId, 'name' => 'Concrete']);
        }
        DB::table('stock_project_item_definitions')->insert([
            'project_id' => 6, 'item_id' => $itemId, 'name' => 'Other project name', 'unit' => 'Ton',
            'default_rate_minor' => 200, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $update = Request::create('/', 'PUT', ['name' => 'Cement', 'unit' => 'Bag', 'default_rate' => 150], ['selected_action' => 5]);
        $this->app->instance('request', $update);
        $this->assertSame(200, (new StockController())->updateItem($update, $itemId)->getStatusCode());
        $this->assertDatabaseHas('stock_project_item_definitions', ['project_id' => 5, 'item_id' => $itemId, 'name' => 'Cement']);
        $this->assertDatabaseHas('stock_project_item_definitions', ['project_id' => 6, 'item_id' => $itemId, 'name' => 'Other project name']);
        $this->assertDatabaseHas('stock_items', ['id' => $itemId, 'name' => 'Concrete']);
        $updateImport = Request::create('/', 'POST', [], ['selected_action' => 5], [
            'file' => UploadedFile::fake()->createWithContent('update.csv',
                "item_id,name,unit,default_rate_minor\n{$itemId},Fine Cement,Bag,175\n"),
        ]);
        $this->app->instance('request', $updateImport);
        $updateBatch = (new StockController())->stageItemImport($updateImport)->getData(true)['data']['batch_id'];
        $applyUpdate = Request::create('/', 'POST', ['confirmed' => '1'], ['selected_action' => 5]);
        $this->app->instance('request', $applyUpdate);
        $this->assertSame(1, (new StockController())->applyItemImport($applyUpdate, $updateBatch)->getData(true)['data']['updated']);
        $this->assertDatabaseHas('stock_project_item_definitions', ['project_id' => 5, 'item_id' => $itemId, 'name' => 'Fine Cement']);
        $this->assertDatabaseHas('stock_project_item_definitions', ['project_id' => 6, 'item_id' => $itemId, 'name' => 'Other project name']);
        $this->assertDatabaseHas('stock_items', ['id' => $itemId, 'name' => 'Concrete']);
        $replayed = (new StockController())->applyItemImport($confirm, $batchId)->getData(true);
        $this->assertTrue($replayed['data']['already_applied']);
        $this->assertDatabaseCount('stock_items', 1);
    }
}
