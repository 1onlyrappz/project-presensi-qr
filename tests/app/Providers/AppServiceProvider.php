<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Aktifkan di .env produksi setelah sertifikat SSL terpasang.
        // Jangan paksa HTTPS saat aplikasi masih diakses lewat http://localhost,
        // karena cookie sesi yang secure tidak akan dikirim browser.
        if (config('app.force_https')) {
            URL::forceScheme('https');
        }
    }
}
