<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memastikan tampilan selalu berbahasa Indonesia.
 *
 * Nama bulan (translatedFormat) dan pesan validasi mengikuti locale aplikasi.
 * Kalau di .env lupa mengisi APP_LOCALE=id, Laravel memakai "en" sehingga
 * rekap terbaca "October". Middleware ini mencegah hal itu.
 */
class AturBahasa
{
    public function handle(Request $request, Closure $next): Response
    {
        $bahasa = config('presensi.bahasa', 'id');

        app()->setLocale($bahasa);
        Carbon::setLocale($bahasa);

        return $next($request);
    }
}
