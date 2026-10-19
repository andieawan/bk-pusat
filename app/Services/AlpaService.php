<?php

namespace App\Services;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Support\Dates;
use Illuminate\Support\Facades\Cache;

/** Rekap kehadiran dari Absensi per kelas (di-cache singkat) — bila Absensi mati, hasil kosong, bukan error. */
class AlpaService
{
    /** @return array<string,array> rekap per siswa_uuid, atau null bila tidak tersedia */
    public static function kelas(Kelas $k, string $dari, string $sampai): ?array
    {
        if (! AbsensiKlien::aktif() || ! $k->pusat_uuid) {
            return null;
        }

        return Cache::remember("rekap.{$k->pusat_uuid}.$dari.$sampai", 120, function () use ($k, $dari, $sampai) {
            try {
                return collect(AbsensiKlien::ringkasan($k->pusat_uuid, $dari, $sampai))->keyBy('siswa_uuid')->all();
            } catch (\Throwable $e) {
                return null; // jangan menyimpan kegagalan terlalu lama
            }
        });
    }

    /** Jumlah alpa siswa sejak awal tahun ajaran berjalan sampai hari ini, atau null bila tidak diketahui. */
    public static function alpaTahunIni(Siswa $s): ?int
    {
        if (! $s->kelas) {
            return null;
        }
        $mulai = (int) substr((string) PusatKlien::baca('tahun_aktif', ''), 0, 4);
        $dari = ($mulai ?: (int) date('Y') - 1).'-07-01';
        $r = self::kelas($s->kelas, $dari, Dates::today());

        return $r === null ? null : (int) ($r[$s->pusat_uuid]['alpa'] ?? 0);
    }
}
