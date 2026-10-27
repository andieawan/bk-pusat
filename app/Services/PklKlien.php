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
            throw new RuntimeException('Sambungan ke PKL belum diatur. Nyalakan "PKL → BK" di Pusat → Administrasi → Sambungan.');
        }
        try {
            $res = Http::baseUrl(config('bk.pkl.url').'/api/v1')->withToken((string) config('bk.pkl.api_key'))->acceptJson()
                ->withOptions(['verify' => (bool) config('bk.pkl.verify_ssl')])->timeout(config('bk.pkl.timeout'))->get($path);
        } catch (\Throwable $e) {
            report($e);
            throw new RuntimeException('Aplikasi PKL tidak dapat dihubungi. Pastikan aplikasi PKL menyala dan alamatnya di Pusat → Aplikasi benar.');
        }
        if ($res->status() === 401) {
            throw new RuntimeException('Kunci API PKL ditolak. Di Pusat → Administrasi → Sambungan klik "Kirim ulang" pada PKL → BK (pastikan aplikasi PKL sudah diperbarui).');
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
