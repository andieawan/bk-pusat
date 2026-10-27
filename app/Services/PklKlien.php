<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Klien API aplikasi PKL (baca saja). PKL tetap pemilik data PKL. */
class PklKlien
{
    public static function aktif(): bool
    {
        return config('bk.pkl.url') !== '' && config('bk.pkl.api_key') !== '';
    }

    private static function get(string $path): array
    {
        if (! self::aktif()) {
            throw new RuntimeException('Sambungan ke PKL belum diatur (PKL_URL dan PKL_API_KEY di .env).');
        }
        try {
            $res = Http::baseUrl(config('bk.pkl.url').'/api/v1')->withToken((string) config('bk.pkl.api_key'))->acceptJson()
                ->withOptions(['verify' => (bool) config('bk.pkl.verify_ssl')])->timeout(config('bk.pkl.timeout'))->get($path);
        } catch (\Throwable $e) {
            report($e);
            throw new RuntimeException('Aplikasi PKL tidak dapat dihubungi. Periksa PKL_URL dan jaringan.');
        }
        if ($res->status() === 401) {
            throw new RuntimeException('Kunci API PKL salah (samakan PKL_API_KEY di BK dengan PKL_API_KUNCI_BK di PKL).');
        }
        if (! $res->successful()) {
            throw new RuntimeException('PKL membalas HTTP '.$res->status().'.');
        }

        return (array) $res->json();
    }

    public static function ping(): array
    {
        return self::get('/ping')['data'] ?? [];
    }

    /** @return array{data: array, meta: array} */
    public static function perhatian(): array
    {
        return self::get('/perhatian');
    }

    public static function siswa(string $uuid): array
    {
        return self::get('/siswa/'.rawurlencode($uuid))['data'] ?? [];
    }
}
