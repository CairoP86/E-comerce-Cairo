<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\TestCase;

class CsrfTest extends TestCase
{
    public function test_mutating_authentication_routes_reject_missing_csrf_tokens(): void
    {
        $this->withoutVite();
        $this->app->bind(PreventRequestForgery::class, EnforcedCsrf::class);
        foreach (['/login', '/register', '/forgot-password', '/reset-password', '/logout', '/email/verification-notification'] as $path) {
            $this->post($path)->assertStatus(419);
        }
    }
}

class EnforcedCsrf extends PreventRequestForgery
{
    protected function runningUnitTests()
    {
        return false;
    }
}
