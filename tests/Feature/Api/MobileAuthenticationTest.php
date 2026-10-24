<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class MobileAuthenticationTest extends TestCase
{
    public function test_mobile_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/mobile/auth/me')
            ->assertStatus(401);
    }

    public function test_mobile_logout_requires_authentication(): void
    {
        $this->postJson('/api/v1/mobile/auth/logout')
            ->assertStatus(401);
    }
}
