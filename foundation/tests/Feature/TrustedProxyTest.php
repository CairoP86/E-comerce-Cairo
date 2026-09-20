<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_forwarded_headers_from_the_local_reverse_proxy_are_honoured(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get('/', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'tienda.example', 'X-Forwarded-Port' => '443'])
            ->assertOk();
        $request = app('request');
        $this->assertTrue($request->isSecure());
        $this->assertSame('tienda.example', $request->getHost());
    }

    public function test_forwarded_headers_from_an_unknown_client_are_ignored(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->get('/', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'atacante.example'])
            ->assertOk();
        $request = app('request');
        $this->assertFalse($request->isSecure());
        $this->assertNotSame('atacante.example', $request->getHost());
    }

    public function test_the_trusted_proxy_list_is_configurable_and_defaults_to_loopback(): void
    {
        $this->assertSame('127.0.0.1,::1', config('trustedproxy.proxies'));
    }
}
