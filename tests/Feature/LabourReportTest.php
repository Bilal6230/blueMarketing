<?php

namespace Tests\Feature;

use App\Http\Controllers\LabourController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class LabourReportTest extends TestCase
{
    protected int $projectId;
    protected int $labourId;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->createSchema();
        $this->seedMixedRateScenario();
    }

    public function test_person_wise_report_uses_current_rate_and_attendance_amount_sum(): void
    {
        $report = $this->buildPersonWiseReport()['personWiseReports']->first();

        $this->assertSame('1,000', $report['rate']);
        $this->assertSame('6', $report['days']);
        $this->assertSame('6,600', $report['amount']);
        $this->assertEquals(6600.0, $report['amount_raw']);
    }

    public function test_person_wise_report_does_not_recalculate_week_using_first_attendance_rate(): void
    {
        $report = $this->buildPersonWiseReport()['personWiseReports']->first();

        $this->assertNotSame('9,600', $report['amount']);
        $this->assertNotEquals(9600.0, $report['amount_raw']);
    }

    public function test_print_person_report_uses_same_corrected_amount_without_payment_inputs(): void
    {
        $request = $this->makeReportRequest();

        $view = app(LabourController::class)->printPersonReport($request);
        $html = $view->render();

        $this->assertStringContainsString('Person Wise Labour Report', $html);
        $this->assertStringContainsString('RS 1,000', $html);
        $this->assertStringContainsString('RS 6,600', $html);
        $this->assertStringContainsString('RS 0', $html);
    }

    private function buildPersonWiseReport(): array
    {
        $request = $this->makeReportRequest();
        $method = new ReflectionMethod(LabourController::class, 'buildPersonWiseReport');
        $method->setAccessible(true);

        return $method->invoke(app(LabourController::class), $request);
    }

    private function makeReportRequest(): Request
    {
        $request = Request::create('/admin/labours/person-report', 'POST', [
            'week' => '2026-07-17',
            'start_date' => '2026-07-17',
            'end_date' => '2026-07-23',
            'search' => '',
        ], [
            'selected_action' => (string) $this->projectId,
        ]);

        $this->app->instance('request', $request);

        return $request;
    }

    private function seedMixedRateScenario(): void
    {
        $now = now();

        $this->projectId = DB::table('projects')->insertGetId([
            'project' => 'Alpha Town',
            'address' => 'Alpha Address',
            'is_active' => 1,
            'create_by' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->labourId = DB::table('labours')->insertGetId([
            'name' => 'Test Labour',
            'father_name' => 'Parent Labour',
            'cnic' => '3520212345671',
            'phone' => '03123456789',
            'role' => 'Mason',
            'daily_wage' => 1000,
            'advance' => 0,
            'join_date' => '2026-07-01',
            'status' => 'active',
            'project_id' => $this->projectId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $attendanceRows = [
            ['date' => '2026-07-17', 'rate' => 1600, 'amount' => 1600],
            ['date' => '2026-07-18', 'rate' => 1000, 'amount' => 1000],
            ['date' => '2026-07-19', 'rate' => 1000, 'amount' => 1000],
            ['date' => '2026-07-20', 'rate' => 1000, 'amount' => 1000],
            ['date' => '2026-07-21', 'rate' => 1000, 'amount' => 1000],
            ['date' => '2026-07-22', 'rate' => 1000, 'amount' => 1000],
        ];

        foreach ($attendanceRows as $row) {
            DB::table('labour_attendances')->insert([
                'project_id' => $this->projectId,
                'labour_id' => $this->labourId,
                'site_id' => null,
                'date' => $row['date'],
                'status' => 'present',
                'hours' => 8,
                'ot_hours' => 0,
                'rate' => $row['rate'],
                'amount' => $row['amount'],
                'remarks' => null,
                'is_approved' => 0,
                'marked_by' => null,
                'paid_status' => 'unpaid',
                'voucher_status' => 'notcreated',
                'ratings' => 5,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('labour_ledgers')->insert([
            'project_id' => $this->projectId,
            'labour_id' => $this->labourId,
            'date' => '2026-07-23',
            'amount' => 1000,
            'details' => json_encode(['note' => 'Paid'], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('project');
            $table->string('address')->nullable();
            $table->integer('is_active')->default(1);
            $table->integer('create_by')->nullable();
            $table->timestamps();
        });

        Schema::create('labours', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('father_name')->nullable();
            $table->string('cnic')->unique();
            $table->string('phone')->nullable();
            $table->string('role')->nullable();
            $table->decimal('daily_wage', 10, 2)->nullable();
            $table->decimal('advance', 10, 2)->default(0);
            $table->date('join_date')->nullable();
            $table->string('status')->default('active');
            $table->unsignedBigInteger('project_id');
            $table->timestamps();
        });

        Schema::create('labour_attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('labour_id');
            $table->unsignedBigInteger('site_id')->nullable();
            $table->date('date');
            $table->string('status')->default('not-marked');
            $table->decimal('hours', 5, 2)->default(0);
            $table->decimal('ot_hours', 5, 2)->default(0);
            $table->decimal('rate', 10, 2)->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('remarks')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->unsignedBigInteger('marked_by')->nullable();
            $table->string('paid_status')->default('unpaid');
            $table->string('voucher_status')->default('notcreated');
            $table->decimal('ratings', 10, 1)->default(0);
            $table->timestamps();
        });

        Schema::create('labour_ledgers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('labour_id');
            $table->date('date')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->text('details')->nullable();
            $table->timestamps();
        });
    }
}
