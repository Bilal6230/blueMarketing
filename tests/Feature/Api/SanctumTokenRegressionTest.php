<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SanctumTokenRegressionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->string('password');
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function makeToken(): string
    {
        $user = User::factory()->create();

        return $user->createToken('regression_test')->plainTextToken;
    }

    public function test_valid_bearer_token_is_accepted(): void
    {
        $token = $this->makeToken();

        $this->getJson('/api/user', [
            'Authorization' => 'Bearer ' . $token,
        ])->assertOk();
    }

    public function test_mobile_logout_revokes_bearer_token(): void
    {
        $token = $this->makeToken();

        $headers = ['Authorization' => 'Bearer ' . $token];

        $this->postJson(
            '/api/v1/mobile/auth/logout',
            [],
            $headers
        )->assertOk();

        $tokenId = explode('|', $token)[0];

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $tokenId,
        ]);

        $this->assertNotNull(
            DB::table('personal_access_tokens')
                ->where('id', $tokenId)
                ->value('deleted_at')
        );

        Auth::forgetGuards();

        $this->getJson('/api/user', $headers)
            ->assertUnauthorized();
    }

    public function test_soft_deleted_token_must_be_rejected(): void
    {
        $token = $this->makeToken();

        $tokenId = explode('|', $token)[0];

        DB::table('personal_access_tokens')
            ->where('id', $tokenId)
            ->update(['deleted_at' => now()]);

        Auth::forgetGuards();

        $this->getJson('/api/user', [
            'Authorization' => 'Bearer ' . $token,
        ])->assertUnauthorized();
    }
}