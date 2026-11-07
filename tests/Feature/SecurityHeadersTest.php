<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_responses_have_safe_baseline_headers(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertHeader('X-Content-Type-Options','nosniff')
            ->assertHeader('X-Frame-Options','SAMEORIGIN')
            ->assertHeader('Referrer-Policy','strict-origin-when-cross-origin');
    }

    public function test_account_and_login_pages_are_not_cached_by_shared_proxies(): void
    {
        $this->get(route('login'))->assertOk()->assertHeader('Cache-Control','private, no-store');
        $this->actingAs(User::factory()->create())->get(route('account'))->assertOk()
            ->assertHeader('Cache-Control','private, no-store');
    }

    public function test_secure_requests_receive_hsts_header(): void
    {
        $this->withServerVariables(['HTTPS'=>'on'])->get(route('home'))->assertOk()
            ->assertHeader('Strict-Transport-Security','max-age=31536000');
    }
}
