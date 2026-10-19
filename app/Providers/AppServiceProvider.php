<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Folder kerja wajib: bila terhapus/tidak ikut terunggah, buat otomatis.
        foreach (['framework/sessions', 'framework/views', 'framework/cache/data', 'logs', 'app/private', 'app/public'] as $d) {
            $path = storage_path($d);
            if (! is_dir($path)) {
                @mkdir($path, 0775, true);
            }
        }
    }

    public function boot(): void
    {
        // Alamat aset/tautan selalu https bila APP_URL https (produksi), walau header proxy tidak terbaca.
        // Matikan dengan APP_FORCE_HTTPS=false (mis. uji lewat http://IP-VM).
        $paksa = env('APP_FORCE_HTTPS');
        if ($paksa === null ? (app()->environment('production') && str_starts_with((string) config('app.url'), 'https://')) : filter_var($paksa, FILTER_VALIDATE_BOOLEAN)) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
