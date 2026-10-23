<?php

namespace Tests\Feature;

use App\Http\Middleware\HeaderKeamanan;
use Illuminate\Http\Request;
use Tests\TestCase;

class KeamananHttpsTest extends TestCase
{
    public function test_hsts_hanya_dikirim_saat_https_dan_diaktifkan(): void
    {
        config(['app.hsts' => true]);

        $response = (new HeaderKeamanan)->handle(
            Request::create('https://localhost/scan', 'GET'),
            fn () => response('ok'),
        );

        $this->assertSame('max-age=31536000', $response->headers->get('Strict-Transport-Security'));

        config(['app.hsts' => false]);

        $response = (new HeaderKeamanan)->handle(
            Request::create('https://localhost/scan', 'GET'),
            fn () => response('ok'),
        );

        $this->assertNull($response->headers->get('Strict-Transport-Security'));

        config(['app.hsts' => true]);

        $response = (new HeaderKeamanan)->handle(
            Request::create('http://localhost/scan', 'GET'),
            fn () => response('ok'),
        );

        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }

    public function test_header_keamanan_dasar_selalu_ada(): void
    {
        $response = (new HeaderKeamanan)->handle(
            Request::create('http://localhost/scan', 'GET'),
            fn () => response('ok'),
        );

        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        $this->assertSame('camera=(self), microphone=(), geolocation=()', $response->headers->get('Permissions-Policy'));
    }
}
