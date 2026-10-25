<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_the_application_returns_a_successful_response()
    {
        Schema::create('landing_pages', function (Blueprint $table): void {
            $table->id();
        });

        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
