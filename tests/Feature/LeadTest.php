<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckUserStatus;
use App\Http\Controllers\LeadController;
use App\Models\Lead;
use App\Models\User;
use App\Repository\Lead\LeadRepository as lead_repo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Middlewares\PermissionMiddleware;
use Tests\TestCase;

class LeadTest extends TestCase
{
    protected User $user;
    protected int $projectId;
    protected int $otherProjectId;
    protected int $areaId;
    protected int $zoneId;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->withoutMiddleware(CheckUserStatus::class);
        $this->withoutMiddleware(PermissionMiddleware::class);

        $this->createSchema();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->projectId = DB::table('projects')->insertGetId([
            'project' => 'Alpha Town',
            'address' => 'Alpha Address',
            'is_active' => 1,
            'create_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->otherProjectId = DB::table('projects')->insertGetId([
            'project' => 'Beta Town',
            'address' => 'Beta Address',
            'is_active' => 1,
            'create_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->areaId = DB::table('areas')->insertGetId([
            'name' => 'Central Area',
            'is_active' => 1,
            'create_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->zoneId = DB::table('zones')->insertGetId([
            'zone_name' => 'North Zone',
            'is_active' => 1,
            'create_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_create_lead_uses_selected_town_and_does_not_require_projects_id(): void
    {
        $response = $this->callLeadController('store', 'POST', $this->leadPayload([
            'phone_number' => '3000000001',
            'mobile_number' => '03123456789',
        ]), $this->projectId);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertFalse($response->getSession()->has('errors'));

        $this->assertDatabaseHas('leads', [
            'phone_number' => '3000000001',
            'project_id' => $this->projectId,
        ]);
    }

    public function test_create_lead_saves_office_address_and_syncs_assigned_users(): void
    {
        $otherUser = User::factory()->create();

        $response = $this->callLeadController('store', 'POST', $this->leadPayload([
            'phone_number' => '3000000008',
            'mobile_number' => '03123456790',
            'office_address' => 'Saved Office Address',
            'assign_id' => [$this->user->id, $otherUser->id],
        ]), $this->projectId);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertFalse($response->getSession()->has('errors'));

        $lead = Lead::where('phone_number', '3000000008')->firstOrFail();

        $this->assertSame('Saved Office Address', $lead->office_address);
        $this->assertEqualsCanonicalizing(
            [$this->user->id, $otherUser->id],
            $lead->users()->pluck('users.id')->all()
        );
    }

    public function test_update_same_lead_without_changing_phone_number_passes(): void
    {
        $lead = $this->createLead([
            'phone_number' => '3000000002',
            'mobile_number' => '03123456780',
            'project_id' => $this->projectId,
        ]);

        $response = $this->callLeadController('update', 'PUT', $this->leadPayload([
            'id' => $lead->id,
            'phone_number' => '3000000002',
            'mobile_number' => '03123456780',
            'old_phone' => '3000000002',
            'projects_id' => $this->otherProjectId,
            'business' => 'Updated Business',
        ]));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertFalse($response->getSession()->has('errors'));

        $lead->refresh();
        $this->assertSame('Updated Business', $lead->business);
        $this->assertSame($this->projectId, $lead->project_id);
    }

    public function test_update_same_lead_without_old_phone_still_passes(): void
    {
        $lead = $this->createLead([
            'phone_number' => '3000000003',
            'mobile_number' => '03123456781',
        ]);

        $response = $this->callLeadController('update', 'PUT', $this->leadPayload([
            'id' => $lead->id,
            'phone_number' => '3000000003',
            'mobile_number' => '03123456781',
            'business' => 'No old phone required',
        ]));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertFalse($response->getSession()->has('errors'));

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'business' => 'No old phone required',
        ]);
    }

    public function test_update_same_lead_without_changing_mobile_number_passes(): void
    {
        $lead = $this->createLead([
            'phone_number' => '3000000010',
            'mobile_number' => '03123456791',
        ]);

        $response = $this->callLeadController('update', 'PUT', $this->leadPayload([
            'id' => $lead->id,
            'phone_number' => '3000000010',
            'mobile_number' => '03123456791',
            'office_address' => 'Updated Office Address',
        ]));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertFalse($response->getSession()->has('errors'));
        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'office_address' => 'Updated Office Address',
        ]);
    }

    public function test_update_request_without_id_fails_validation(): void
    {
        $response = $this->callLeadController('update', 'PUT', $this->leadPayload([
            'phone_number' => '3000000011',
            'mobile_number' => '03123456792',
        ]));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertTrue($response->getSession()->get('errors')->has('id'));
    }

    public function test_update_to_another_leads_phone_number_fails(): void
    {
        $takenLead = $this->createLead([
            'phone_number' => '3000000004',
            'mobile_number' => '03123456782',
        ]);

        $lead = $this->createLead([
            'phone_number' => '3000000005',
            'mobile_number' => '03123456783',
        ]);

        $response = $this->callLeadController('update', 'PUT', $this->leadPayload([
            'id' => $lead->id,
            'phone_number' => $takenLead->phone_number,
            'mobile_number' => '03123456784',
        ]));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertTrue($response->getSession()->get('errors')->has('phone_number'));
    }

    public function test_update_to_another_leads_mobile_number_fails(): void
    {
        $takenLead = $this->createLead([
            'phone_number' => '3000000006',
            'mobile_number' => '03123456785',
        ]);

        $lead = $this->createLead([
            'phone_number' => '3000000007',
            'mobile_number' => '03123456786',
        ]);

        $response = $this->callLeadController('update', 'PUT', $this->leadPayload([
            'id' => $lead->id,
            'phone_number' => '3000000007',
            'mobile_number' => $takenLead->mobile_number,
        ]));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertTrue($response->getSession()->get('errors')->has('mobile_number'));
    }

    public function test_show_lead_returns_assigned_user_ids(): void
    {
        $otherUser = User::factory()->create();
        $lead = $this->createLead();
        $lead->users()->sync([$this->user->id, $otherUser->id]);

        $response = $this->callLeadController('show', 'POST', ['id' => $lead->id]);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertEqualsCanonicalizing(
            [(string) $this->user->id, (string) $otherUser->id],
            $payload['assigned_user_ids']
        );
        $this->assertSame('Existing Home', $payload['data']['home_address']);
        $this->assertSame('Existing Office', $payload['data']['office_address']);
        $this->assertCount(2, $payload['data']['users']);
    }

    public function test_lead_page_js_prefers_assigned_user_ids_for_edit_modal(): void
    {
        $html = file_get_contents(resource_path('views/admin/crm/lead.blade.php'));

        $this->assertStringContainsString('data.assigned_user_ids', $html);
        $this->assertStringContainsString('trigger(\'change.select2\')', $html);
    }

    public function test_create_modal_does_not_render_project_field(): void
    {
        $html = file_get_contents(resource_path('views/admin/crm/lead.blade.php'));
        $createStart = strpos($html, '<div class="modal fade" id="modal-tambah">');
        $editStart = strpos($html, '<div class="modal fade" id="modal-edit">');
        $createModal = substr($html, $createStart, $editStart - $createStart);

        $this->assertStringContainsString('Add Customer Lead', $createModal);
        $this->assertStringContainsString('Create Lead', $createModal);
        $this->assertStringContainsString('name="form_context" value="create"', $createModal);
        $this->assertStringNotContainsString('name="projects_id"', $createModal);
        $this->assertStringNotContainsString('Project</label>', $createModal);
        $this->assertStringNotContainsString("@error('projects_id')", $createModal);
    }

    public function test_update_modal_does_not_render_project_field(): void
    {
        $html = file_get_contents(resource_path('views/admin/crm/lead.blade.php'));
        $editStart = strpos($html, '<div class="modal fade" id="modal-edit">');
        $deleteStart = strpos($html, '<div class="modal fade" id="modal-delete">');
        $editModal = substr($html, $editStart, $deleteStart - $editStart);

        $this->assertStringContainsString('Edit Customer Lead', $editModal);
        $this->assertStringContainsString('Update Lead', $editModal);
        $this->assertStringContainsString('name="form_context" value="edit"', $editModal);
        $this->assertStringContainsString('name="id"', $editModal);
        $this->assertStringNotContainsString('name="projects_id"', $editModal);
        $this->assertStringNotContainsString('Project</label>', $editModal);
        $this->assertStringNotContainsString("@error('projects_id')", $editModal);
    }

    public function test_lead_page_does_not_render_project_fields_or_project_js_in_modals(): void
    {
        $html = file_get_contents(resource_path('views/admin/crm/lead.blade.php'));

        $this->assertStringNotContainsString('name="projects_id"', $html);
        $this->assertStringNotContainsString("setVal('projects_id')", $html);
        $this->assertStringNotContainsString('find(\'[name="projects_id"]\')', $html);
    }

    public function test_delete_modal_posts_to_lead_destroy_route(): void
    {
        $html = file_get_contents(resource_path('views/admin/crm/lead.blade.php'));
        $deleteStart = strpos($html, '<div class="modal fade" id="modal-delete">');
        $deleteModal = substr($html, $deleteStart);

        $this->assertStringContainsString("route('crm.lead.destroy')", $deleteModal);
        $this->assertStringNotContainsString("route('user.destroy')", $deleteModal);
        $this->assertStringContainsString('Delete Lead', $deleteModal);
    }

    public function test_lead_page_renders_project_badge_with_valid_project(): void
    {
        $lead = $this->createLead([
            'project_id' => $this->projectId,
        ]);

        $leadRow = lead_repo::getLeadsList($this->user->id)->firstWhere('id', $lead->id);

        $this->assertSame('Alpha Town', $leadRow->project_name);

        $html = $this->renderLeadProjectBadge($leadRow);

        $this->assertStringContainsString('Alpha Town', $html);
        $this->assertStringContainsString((string) \Setting::getProjectColorClass($this->projectId), $html);
    }

    public function test_lead_page_does_not_crash_when_project_relation_is_missing(): void
    {
        $lead = $this->createLead([
            'project_id' => null,
        ]);

        $leadRow = lead_repo::getLeadsList($this->user->id)->firstWhere('id', $lead->id);

        $this->assertSame('No Project', $leadRow->project_name);

        $html = $this->renderLeadProjectBadge($leadRow);

        $this->assertStringContainsString('No Project', $html);
    }

    public function test_lead_page_does_not_crash_if_project_name_is_array(): void
    {
        $leadRow = (object) [
            'project_id' => [$this->projectId],
            'project_name' => ['Alpha Town', 'Beta Town'],
            'project' => null,
        ];

        $html = $this->renderLeadProjectBadge($leadRow);

        $this->assertStringContainsString('Alpha Town, Beta Town', $html);
        $this->assertStringNotContainsString('Array', $html);
    }

    public function test_lead_project_badge_shows_no_project_when_project_is_missing(): void
    {
        $leadRow = (object) [
            'project_id' => null,
            'project_name' => null,
            'project' => null,
        ];

        $html = $this->renderLeadProjectBadge($leadRow);

        $this->assertStringContainsString('No Project', $html);
    }

    public function test_deleting_existing_lead_marks_it_inactive_and_keeps_users(): void
    {
        $otherUser = User::factory()->create();
        $lead = $this->createLead();
        $lead->users()->sync([$this->user->id, $otherUser->id]);

        $response = $this->callLeadController('destroy', 'DELETE', ['id' => $lead->id]);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertFalse($response->getSession()->has('errors'));
        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'is_active' => 0,
        ]);
        $this->assertDatabaseHas('users', ['id' => $this->user->id]);
        $this->assertDatabaseHas('users', ['id' => $otherUser->id]);
    }

    public function test_deleting_with_invalid_id_fails_validation_gracefully(): void
    {
        try {
            $this->callLeadController('destroy', 'DELETE', ['id' => 999999]);
            $this->fail('Expected validation exception was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('id', $exception->errors());
        }
    }

    public function test_delete_regression_assigned_user_still_exists_after_lead_delete(): void
    {
        $assignedUser = User::factory()->create();
        $lead = $this->createLead();
        $lead->users()->sync([$assignedUser->id]);

        $response = $this->callLeadController('destroy', 'DELETE', ['id' => $lead->id]);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertDatabaseHas('users', ['id' => $assignedUser->id]);
        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'is_active' => 0,
        ]);
    }

    protected function leadPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Test',
            'last_name' => 'Lead',
            'relate' => 'S/O',
            'father_name' => 'Parent Lead',
            'gender' => 1,
            'nic_number' => '3520212345671',
            'phone_number' => '3000000000',
            'mobile_number' => '03123456788',
            'area_id' => $this->areaId,
            'type' => 1,
            'business' => 'Distribution',
            'zone_id' => $this->zoneId,
            'home_address' => 'Home Address',
            'office_address' => 'Office Address',
            'designation' => 'Owner',
            'is_active' => 1,
            'follow_id' => $this->user->id,
            'assign_id' => [$this->user->id],
        ], $overrides);
    }

    protected function createLead(array $overrides = []): Lead
    {
        $lead = Lead::create(array_merge([
            'first_name' => 'Existing',
            'last_name' => 'Lead',
            'relate' => 'S/O',
            'father_name' => 'Existing Parent',
            'gender' => 1,
            'nic_number' => '3520212345672',
            'phone_number' => '3999999999',
            'mobile_number' => '03111111111',
            'area_id' => $this->areaId,
            'type' => 1,
            'business' => 'Existing Business',
            'zone_id' => $this->zoneId,
            'home_address' => 'Existing Home',
            'office_address' => 'Existing Office',
            'designation' => 'Existing Designation',
            'follow_id' => $this->user->id,
            'project_id' => $this->projectId,
            'is_active' => 1,
            'create_by' => $this->user->id,
        ], $overrides));

        $lead->users()->sync([$this->user->id]);

        return $lead;
    }

    protected function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('gender')->nullable();
            $table->string('nic_number')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('department')->nullable();
            $table->string('address')->nullable();
            $table->string('avatar')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('project');
            $table->string('address');
            $table->integer('is_active');
            $table->integer('create_by');
            $table->timestamps();
        });

        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('is_active');
            $table->integer('create_by');
            $table->timestamps();
        });

        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('zone_name');
            $table->integer('is_active');
            $table->integer('create_by');
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('relate')->nullable();
            $table->string('father_name')->nullable();
            $table->integer('gender')->nullable();
            $table->string('nic_number')->nullable();
            $table->string('phone_number')->nullable()->unique();
            $table->string('mobile_number')->nullable()->unique();
            $table->integer('area_id')->nullable();
            $table->integer('type')->nullable();
            $table->string('business')->nullable();
            $table->string('designation')->nullable();
            $table->integer('zone_id')->nullable();
            $table->longText('home_address')->nullable();
            $table->longText('office_address')->nullable();
            $table->integer('follow_id')->default(1);
            $table->timestamp('follow_up')->nullable();
            $table->integer('follow_status')->default(6);
            $table->unsignedBigInteger('project_id')->nullable();
            $table->integer('is_active');
            $table->integer('create_by');
            $table->timestamps();
        });

        Schema::create('lead_user', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });

        Schema::create('works', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->longText('comment')->nullable();
            $table->string('call_duration')->nullable();
            $table->integer('call_status')->nullable();
            $table->string('recording_path')->nullable();
            $table->integer('type')->default(1);
            $table->integer('user_id')->nullable();
            $table->timestamp('follow_up')->nullable();
            $table->timestamps();
        });
    }

    protected function callLeadController(string $action, string $method, array $payload, ?int $selectedProjectId = null)
    {
        $request = Request::create('/admin/crm/lead', $method, $payload);

        if ($selectedProjectId !== null) {
            $request->cookies->set('selected_action', (string) $selectedProjectId);
        }

        $session = app('session.store');
        $session->start();
        $session->setPreviousUrl('/admin/crm/lead');
        $request->setLaravelSession($session);

        $this->app->instance('request', $request);
        auth()->setUser($this->user);

        return app(LeadController::class)->{$action}($request);
    }

    protected function renderLeadProjectBadge(object $leadRow): string
    {
        return trim(Blade::render(<<<'BLADE'
@php
    $projectId = is_array($i->project_id) ? null : $i->project_id;

    $projectClass = Setting::getProjectColorClass($projectId);
    $projectClass = is_array($projectClass)
        ? implode(' ', array_filter($projectClass))
        : (string) ($projectClass ?? '');

    $projectName = $i->project_name ?? optional($i->project)->project ?? 'No Project';
    $projectName = is_array($projectName)
        ? implode(', ', array_filter($projectName))
        : (string) ($projectName ?? 'No Project');

    if (trim($projectName) === '') {
        $projectName = 'No Project';
    }
@endphp

<span class="btn btn-sm {{ $projectClass }}">
    {{ $projectName }}
</span>
BLADE, ['i' => $leadRow]));
    }
}
