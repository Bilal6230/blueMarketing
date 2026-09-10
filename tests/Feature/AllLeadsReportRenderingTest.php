<?php

namespace Tests\Feature;

use App\Http\Controllers\ReportController;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AllLeadsReportRenderingTest extends TestCase
{
    public function test_lead_without_project_or_follow_status_renders_safe_fallbacks(): void
    {
        $user = new User();
        $user->id = 1;
        $user->name = 'Report User';
        $this->actingAs($user);
        Gate::before(static fn () => false);

        $lead = new Lead();
        $lead->id = 10;
        $lead->is_active = 1;
        $lead->first_name = 'Projectless';
        $lead->last_name = 'Lead';
        $lead->project_id = null;
        $lead->project_name = null;
        $lead->follow_status = null;
        $lead->setRelation('users', collect());

        $validLead = new Lead();
        $validLead->id = 11;
        $validLead->is_active = 1;
        $validLead->first_name = 'Normal';
        $validLead->last_name = 'Lead';
        $validLead->project_id = 2;
        $validLead->project_name = 'Alpha Town';
        $validLead->follow_status = 2;
        $validLead->setRelation('users', collect());

        $response = new TestResponse(response()->view('admin.reports.admin_lead', [
            'title' => 'Lead SetUp',
            'data' => collect([$lead, $validLead]),
            'role' => collect(),
            'users' => collect(),
            'power' => 'user',
            'filter' => ['type' => null, 'user' => $user->id],
            'projects' => collect(),
            'errors' => new ViewErrorBag(),
        ]));

        $response->assertOk();
        $response->assertSee('No Project');
        $response->assertSee('Unknown');
        $response->assertSee('btn-secondary', false);
        $response->assertSee('badge-secondary', false);
        $response->assertSee('Alpha Town');
        $response->assertSee('Interested');
        $response->assertSee('btn-success', false);
        $response->assertSee('badge-parpal', false);
    }

    public function test_filter_javascript_reads_current_values_and_uses_route_url(): void
    {
        $blade = file_get_contents(resource_path('views/admin/reports/admin_lead.blade.php'));

        $this->assertStringContainsString('new URLSearchParams()', $blade);
        $this->assertStringContainsString('const filter = $("#filter").val()', $blade);
        $this->assertStringContainsString("route('report.lead.index')", $blade);
        $this->assertStringNotContainsString('window.location.origin+"/admin/report/leads?"', $blade);
    }

    /** @dataProvider selectedFilterProvider */
    public function test_server_rendered_page_preserves_selected_filter(?string $filter, string $selectedValue): void
    {
        $user = new User();
        $user->id = 9;
        $user->name = 'Selected User';
        $this->actingAs($user);
        Gate::before(static fn () => true);

        $response = new TestResponse(response()->view('admin.reports.admin_lead', [
            'title' => 'Lead SetUp',
            'data' => collect(),
            'role' => collect(),
            'users' => collect([$user]),
            'power' => 'user',
            'filter' => ['type' => $filter, 'user' => 9],
            'projects' => collect(),
            'errors' => new ViewErrorBag(),
        ]));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertMatchesRegularExpression(
            '/<option value="' . preg_quote($selectedValue, '/') . '"\s+selected>/',
            $html
        );
        $this->assertMatchesRegularExpression('/<option value="9" selected>Selected User<\/option>/', $html);
    }

    public function selectedFilterProvider(): array
    {
        return [
            'no filter' => [null, ''],
            'all leads' => ['all', 'all'],
            'scheduled leads' => ['schedule', 'schedule'],
            'today leads' => ['today', 'today'],
        ];
    }

    /** @dataProvider malformedFilterProvider */
    public function test_array_filter_inputs_are_rejected(string $field, array $value): void
    {
        $request = Request::create('/admin/report/leads', 'GET', [$field => $value]);

        try {
            app(ReportController::class)->index($request);
            $this->fail('Expected malformed report filter input to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public function malformedFilterProvider(): array
    {
        return [
            'filter array' => ['filter', ['all']],
            'user array' => ['user', ['1']],
        ];
    }
}
