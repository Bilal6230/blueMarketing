<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LoginRecaptchaTest extends TestCase
{
    public function test_login_requires_captcha_response_when_keys_are_configured(): void
    {
        config()->set('recaptcha.api_site_key', 'test-site-key');
        config()->set('recaptcha.api_secret_key', 'test-secret-key');

        Http::fake();

        $response = $this->from('/login')->post('/login', [
            'email' => 'person@example.test',
            'password' => 'not-a-real-password',
            'projects_id' => '1',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('g-recaptcha-response');
        Http::assertNothingSent();
    }

    public function test_login_rejects_invalid_captcha_without_authenticating(): void
    {
        config()->set('recaptcha.api_site_key', 'test-site-key');
        config()->set('recaptcha.api_secret_key', 'test-secret-key');

        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response(['success' => false], 200),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'person@example.test',
            'password' => 'not-a-real-password',
            'projects_id' => '1',
            'g-recaptcha-response' => 'invalid-test-token',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('g-recaptcha-response');
        $this->assertGuest();
        Http::assertSentCount(1);
    }
}
