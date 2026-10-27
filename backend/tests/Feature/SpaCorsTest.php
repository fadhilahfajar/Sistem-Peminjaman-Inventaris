<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpaCorsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'session.driver' => 'array',
        ]);
    }

    public function test_csrf_cookie_endpoint_sets_the_xsrf_cookie(): void
    {
        $response = $this->get('/sanctum/csrf-cookie');

        $response->assertNoContent();
        $response->assertCookie('XSRF-TOKEN');
    }

    public function test_frontend_origin_receives_credentialed_cors_headers(): void
    {
        $response = $this->options('/sanctum/csrf-cookie', [], [
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'GET',
        ]);

        $response->assertNoContent();
        $response->assertHeader('access-control-allow-origin', 'http://localhost:5173');
        $response->assertHeader('access-control-allow-credentials', 'true');
    }

    public function test_unlisted_origin_is_not_allowed_by_cors(): void
    {
        $response = $this->options('/sanctum/csrf-cookie', [], [
            'Origin' => 'http://example.test',
            'Access-Control-Request-Method' => 'GET',
        ]);

        $response->assertNoContent();
        $response->assertHeaderMissing('access-control-allow-origin');
        $response->assertHeaderMissing('access-control-allow-credentials');
    }
}
