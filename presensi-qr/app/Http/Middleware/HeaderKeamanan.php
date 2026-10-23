<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menambahkan header keamanan dasar pada setiap balasan.
 *
 * Header ini aman untuk semua halaman dan tidak mengganggu fitur apa pun.
 * Kamera tetap diizinkan (camera=(self)) karena halaman scan memakainya.
 */
class HeaderKeamanan
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');

        // HSTS hanya dikirim lewat HTTPS dan hanya kalau diaktifkan di .env.
        // Jangan aktifkan sebelum sertifikat produksi benar-benar valid.
        if (config('app.hsts') && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
