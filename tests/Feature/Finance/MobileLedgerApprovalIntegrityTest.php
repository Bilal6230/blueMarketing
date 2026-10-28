<?php

namespace Tests\Feature\Finance;

use App\Http\Controllers\Api\V1\Mobile\MobileApprovalController;
use App\Models\PendingUpdate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MobileLedgerApprovalIntegrityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDefaultConnection() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->fail('SQLite :memory: only.');
        }

        Schema::dropAllTables();
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('project');
            $table->boolean('is_active')->default(true);
        });
        DB::table('projects')->insert([
            ['id' => 7, 'project' => 'Primary', 'is_active' => 1],
            ['id' => 8, 'project' => 'Other', 'is_active' => 1],
        ]);
        Schema::create('project_head_subheads', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
        });
        Schema::create('ledgers', function (Blueprint $table): void {
            $table->id();
            $table->string('voucher_number');
            $table->string('type');
            $table->unsignedBigInteger('type_id');
            $table->unsignedBigInteger('project_head_subheads_id');
            $table->string('reference')->nullable();
            $table->decimal('amount_in', 15, 2)->default(0);
            $table->decimal('amount_out', 15, 2)->default(0);
            $table->text('detail')->nullable();
            $table->unsignedBigInteger('create_by')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('status')->nullable();
            $table->date('date')->nullable();
            $table->timestamps();
        });

        DB::table('project_head_subheads')->insert([
            ['id' => 11, 'project_id' => 7],
            ['id' => 22, 'project_id' => 8],
        ]);
        DB::table('ledgers')->insert([
            'id' => 1,
            'voucher_number' => 'JV-1',
            'type' => 'JV',
            'type_id' => 10,
            'project_head_subheads_id' => 11,
            'reference' => 'source-10',
            'amount_in' => 100,
            'amount_out' => 0,
            'detail' => 'original',
            'is_active' => true,
            'status' => 'posted',
            'date' => '2026-10-26',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_ledger_approval_rejects_identity_and_financial_mutations(): void
    {
        $controller = app(MobileApprovalController::class);
        $method = new \ReflectionMethod($controller, 'assertSafeLedgerApprovalValues');
        $method->setAccessible(true);

        foreach ([
            'type', 'type_id', 'voucher_number', 'reference', 'customer_ledger_id',
            'project_head_subheads_id', 'is_active', 'status', 'amount_in',
            'amount_out', 'date', 'delete_reason',
        ] as $field) {
            try {
                $method->invoke($controller, [$field => 'changed']);
                $this->fail("Ledger approval accepted forbidden field {$field}.");
            } catch (\DomainException $exception) {
                $this->assertSame('ledger_identity_or_financial_mutation_forbidden', $exception->getMessage());
            }
        }

        $this->assertSame('original', DB::table('ledgers')->where('id', 1)->value('detail'));
        $this->assertSame('JV', DB::table('ledgers')->where('id', 1)->value('type'));
        $this->assertSame('JV-1', DB::table('ledgers')->where('id', 1)->value('voucher_number'));
    }

    public function test_ledger_approval_allowlist_only_preserves_non_identity_detail_edits(): void
    {
        $controller = app(MobileApprovalController::class);
        $method = new \ReflectionMethod($controller, 'getSafeUpdateValues');
        $method->setAccessible(true);

        $values = $method->invoke($controller, 'ledgers', [
            'detail' => 'reviewed note',
            'type' => 'CV',
            'voucher_number' => 'rewritten',
            'amount_in' => 999,
        ]);

        $this->assertSame(['detail' => 'reviewed note'], $values);
    }

    public function test_ledger_project_resolution_is_source_account_scoped(): void
    {
        $controller = app(MobileApprovalController::class);
        $method = new \ReflectionMethod($controller, 'resolveApprovalProjectId');
        $method->setAccessible(true);

        $approval = new PendingUpdate(['table_name' => 'ledgers', 'record_id' => 1]);
        $this->assertSame(7, $method->invoke($controller, $approval));

        DB::table('ledgers')->where('id', 1)->update(['project_head_subheads_id' => 22]);
        $this->assertSame(8, $method->invoke($controller, $approval));
    }
    public function test_ledger_approval_does_not_bypass_project_ownership_for_privileged_access(): void
    {
        $controller = new class extends MobileApprovalController {
            protected function hasPrivilegedApprovalAccess(\App\Models\User $user): bool
            {
                return true;
            }
        };

        $method = new \ReflectionMethod($controller, 'ensureCanAccessApproval');
        $method->setAccessible(true);

        $user = new \App\Models\User();
        $user->project_id = 7;
        $user->setRelation('roles', collect());

        $approval = new PendingUpdate(['table_name' => 'ledgers', 'record_id' => 1]);
        $method->invoke($controller, $approval, $user);

        DB::table('ledgers')->where('id', 1)->update(['project_head_subheads_id' => 22]);

        try {
            $method->invoke($controller, $approval, $user);
            $this->fail('Privileged approval bypassed foreign project ownership.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }

        DB::table('ledgers')->where('id', 1)->update(['project_head_subheads_id' => 999]);

        try {
            $method->invoke($controller, $approval, $user);
            $this->fail('Privileged approval accepted orphaned project attribution.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }
    }

}
