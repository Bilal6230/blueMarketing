<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\EmployeePayrollPaymentLinker;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EmployeePayrollPaymentLinkerTest extends TestCase
{
    public function test_only_posted_project_cp_can_fund_approved_payroll_without_overpayment_or_replay(): void
    {
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::dropAllTables();
        Schema::create('projects', fn (Blueprint $t) => $t->id());
        Schema::create('employee_payroll_runs', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('project_id'); $t->string('status');
            $t->unique(['id', 'project_id']);
        });
        Schema::create('employee_payroll_run_items', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('project_id'); $t->unsignedBigInteger('payroll_run_id');
            $t->decimal('net_amount', 14, 2);
        });
        Schema::create('employee_payroll_run_audits', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('project_id'); $t->unsignedBigInteger('payroll_run_id');
            $t->string('action'); $t->json('details'); $t->unsignedBigInteger('actor_id')->nullable();
            $t->timestamp('created_at');
        });
        Schema::create('customer_ledger', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('project_id'); $t->string('transaction_type');
            $t->decimal('amount_out', 14, 2); $t->boolean('is_active'); $t->boolean('is_approve');
        });
        Schema::create('project_head_subheads', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('project_id');
        });
        Schema::create('ledgers', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('project_head_subheads_id');
            $t->unsignedBigInteger('customer_ledger_id'); $t->string('type');
            $t->decimal('amount_out', 14, 2); $t->boolean('is_active');
        });
        (require database_path('migrations/2026_11_04_000001_create_employee_payroll_payment_links.php'))->up();
        DB::table('projects')->insert([['id' => 5], ['id' => 6]]);
        DB::table('employee_payroll_runs')->insert([['id' => 1, 'project_id' => 5, 'status' => 'approved'],
            ['id' => 2, 'project_id' => 6, 'status' => 'approved']]);
        DB::table('employee_payroll_run_items')->insert([
            ['id' => 10, 'project_id' => 5, 'payroll_run_id' => 1, 'net_amount' => '100.00'],
            ['id' => 11, 'project_id' => 5, 'payroll_run_id' => 1, 'net_amount' => '100.00'],
            ['id' => 20, 'project_id' => 6, 'payroll_run_id' => 2, 'net_amount' => '100.00'],
        ]);
        DB::table('customer_ledger')->insert([
            ['id' => 100, 'project_id' => 5, 'transaction_type' => 'CP', 'amount_out' => '120.00', 'is_active' => 1, 'is_approve' => 1],
            ['id' => 200, 'project_id' => 6, 'transaction_type' => 'CP', 'amount_out' => '100.00', 'is_active' => 1, 'is_approve' => 1],
            ['id' => 300, 'project_id' => 5, 'transaction_type' => 'CP', 'amount_out' => '100.00', 'is_active' => 1, 'is_approve' => 0],
            ['id' => 400, 'project_id' => 5, 'transaction_type' => 'CP', 'amount_out' => '80.00', 'is_active' => 1, 'is_approve' => 1],
            ['id' => 500, 'project_id' => 5, 'transaction_type' => 'CP', 'amount_out' => '90.00', 'is_active' => 1, 'is_approve' => 1],
        ]);
        DB::table('project_head_subheads')->insert([['id' => 50, 'project_id' => 5], ['id' => 60, 'project_id' => 6]]);
        DB::table('ledgers')->insert([
            ['project_head_subheads_id' => 50, 'customer_ledger_id' => 100, 'type' => 'CP', 'amount_out' => '120.00', 'is_active' => 1],
            ['project_head_subheads_id' => 60, 'customer_ledger_id' => 200, 'type' => 'CP', 'amount_out' => '100.00', 'is_active' => 1],
            ['project_head_subheads_id' => 50, 'customer_ledger_id' => 400, 'type' => 'CP', 'amount_out' => '80.00', 'is_active' => 1],
        ]);
        $service = new EmployeePayrollPaymentLinker();
        $id = $service->link(5, 1, 10, 100, '70.00', 7);
        $this->assertSame($id, $service->link(5, 1, 10, 100, '70.00', 7));
        foreach ([[5, 1, 10, 100, '70.01'], [5, 1, 11, 100, '60.00'],
            [5, 1, 10, 200, '10.00'], [5, 1, 20, 100, '10.00'], [5, 1, 10, 300, '10.00'],
            [5, 1, 10, 400, '40.00'], [5, 1, 11, 500, '10.00']] as $input) {
            try {
                $service->link(...[...$input, 7]);
                $this->fail('Invalid payroll payment link was accepted.');
            } catch (HttpException $exception) {
                $this->assertContains($exception->getStatusCode(), [404, 409, 422]);
            }
        }
        $this->assertSame(1, DB::table('employee_payroll_payment_links')->count());
        $this->assertSame(1, DB::table('employee_payroll_run_audits')->count());
        $this->assertDatabaseHas('customer_ledger', ['id' => 100, 'amount_out' => '120']);
        $this->assertDatabaseCount('ledgers', 3);
    }
}
