<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Klien API Absensi (kunci API dengan izin kehadiran:baca dan kehadiran:tulis).
 * Absensi tetap pemilik data kehadiran; BK hanya membaca rekap dan menulis lewat endpoint khusus.
 */
class AbsensiKlien
{
    public static function aktif(): bool
    {
        return config('bk.absensi.url') !== '' && config('bk.absensi.api_key') !== '';
    }

    public static function http(): PendingRequest
    {
        return Http::baseUrl(config('bk.absensi.url').'/api/v1')
            ->withOptions(['verify' => config('bk.absensi.ca_bundle') ?: (bool) config('bk.absensi.verify_ssl')])
            ->withToken((string) config('bk.absensi.api_key'))
            ->acceptJson()
            ->timeout(config('bk.absensi.timeout'));
    }

    /** @return array hasil JSON; melempar RuntimeException berpesan jelas bila gagal. */
    private static function panggil(string $metode, string $path, array $data = []): array
    {
        if (! self::aktif()) {
            throw new RuntimeException('Sambungan ke Absensi belum diatur. Nyalakan "Absensi → BK" di Pusat → Administrasi → Sambungan.');
        }
        try {
            $res = $metode === 'get' ? self::http()->get($path, $data) : self::http()->{$metode}($path, $data);
        } catch (\Throwable $e) {
            report($e);
            throw new RuntimeException('Absensi tidak dapat dihubungi. Pastikan aplikasi Absensi menyala dan alamatnya di Pusat → Aplikasi benar.');
        }
        if (! $res->successful()) {
            $galat = $res->json('error');
            $pesan = (is_array($galat) ? ($galat['message'] ?? null) : $galat) ?: $res->json('message') ?: ($res->status() === 401 ? 'Kunci API Absensi salah atau dicabut.' : 'HTTP '.$res->status());
            if ($res->status() === 403) {
                $pesan = 'Kunci API Absensi belum punya izin yang dibutuhkan ('.$pesan.').';
            }
            throw new RuntimeException($pesan);
        }

        return (array) $res->json();
    }

    public static function ping(): array
    {
        return self::panggil('get', '/ping')['data'] ?? [];
    }

    /** Rekap harian per siswa aktif satu kelas (uuid Pusat) pada rentang tanggal. @return array<int,array> */
    public static function ringkasan(string $kelasUuid, string $dari, string $sampai): array
    {
        return self::panggil('get', '/kehadiran/ringkasan', ['kelas_uuid' => $kelasUuid, 'dari' => $dari, 'sampai' => $sampai])['data'] ?? [];
    }

    public static function manual(string $siswaUuid, string $tanggal, string $status, ?string $catatan, string $petugas): array
    {
        return self::panggil('post', '/kehadiran/manual', [
            'siswa_uuid' => $siswaUuid, 'tanggal' => $tanggal, 'status' => $status, 'catatan' => $catatan, 'petugas' => $petugas,
        ])['data'] ?? [];
    }

    /** Status absensi harian tiap siswa satu kelas pada satu tanggal. @return array<int,array{siswa_uuid:?string,status:?string,catatan:?string}> */
    public static function harianKelas(string $kelasUuid, string $tanggal): array
    {
        return self::panggil('get', '/kehadiran/harian-kelas', ['kelas_uuid' => $kelasUuid, 'tanggal' => $tanggal])['data'] ?? [];
    }

    /** Absensi harian banyak siswa satu kelas sekaligus. @param array<int,array{siswa_uuid:string,status:string,catatan:?string}> $entri */
    public static function manualKelas(string $kelasUuid, string $tanggal, array $entri, string $petugas): array
    {
        return self::panggil('post', '/kehadiran/manual/kelas', ['kelas_uuid' => $kelasUuid, 'tanggal' => $tanggal, 'entri' => $entri, 'petugas' => $petugas])['data'] ?? [];
    }

    /** @return array<int,array{token:string,tautan:string,berlaku_sampai:?string}> */
    public static function delegasi(string $kelasUuid): array
    {
        return self::panggil('get', '/delegasi', ['kelas_uuid' => $kelasUuid])['data'] ?? [];
    }

    public static function buatDelegasi(string $kelasUuid, int $jam, string $petugas): array
    {
        return self::panggil('post', '/delegasi', ['kelas_uuid' => $kelasUuid, 'jam' => $jam, 'petugas' => $petugas])['data'] ?? [];
    }

    public static function cabutDelegasi(string $token, string $petugas): void
    {
        self::panggil('delete', '/delegasi/'.rawurlencode($token), ['petugas' => $petugas]);
    }
}
