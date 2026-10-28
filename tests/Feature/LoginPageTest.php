<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LoginPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('project');
            $table->string('address')->nullable();
            $table->string('logo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('create_by')->nullable();
            $table->timestamps();
        });

        DB::table('projects')->insert([
            'project' => 'Synthetic Demo Project',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('projects');

        parent::tearDown();
    }

    public function test_guest_login_page_renders_with_synthetic_project_choices(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Synthetic Demo Project');
    }
}
