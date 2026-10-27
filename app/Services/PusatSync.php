<?php

namespace App\Services;

use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Menyalin data induk (kelas, siswa) dari Pusat. Tidak ada yang dihapus: siswa yang hilang di Pusat
 * diberi status "keluar" sehingga riwayat catatan BK tetap utuh. Dipakai oleh tombol sinkron dan webhook.
 */
class PusatSync
{
    /** @return array{kelas:array,siswa:array,tahun_aktif:?string} */
    public function jalankan(): array
    {
        Sambungan::tarikAman(); // sambungan antar-aplikasi ikut diperbarui setiap sinkron
        $tahun = PusatKlien::semua('tahun-ajaran');
        $kelas = PusatKlien::semua('kelas');
        $siswa = PusatKlien::semua('siswa');

        $aktif = collect($tahun)->firstWhere('aktif', true);
        if (! $aktif) {
            throw new RuntimeException('Pusat belum punya tahun ajaran aktif. Atur dulu di Pusat.');
        }
        $tertaut = Siswa::count();
        if ($tertaut > 0 && count($siswa) === 0) {
            throw new RuntimeException('Pusat mengembalikan 0 siswa padahal BK sudah punya '.$tertaut.' siswa. Dibatalkan demi keamanan.');
        }

        return DB::transaction(function () use ($aktif, $kelas, $siswa) {
            PusatKlien::simpan('tahun_aktif', $aktif['nama']);
            $h = ['kelas' => ['baru' => 0, 'ubah' => 0, 'sama' => 0], 'siswa' => ['baru' => 0, 'ubah' => 0, 'sama' => 0, 'keluar' => 0], 'tahun_aktif' => $aktif['nama']];
            $adaKelas = [];
            foreach ($kelas as $k) {
                $adaKelas[$k['uuid']] = true;
                $h['kelas'][self::upsertKelas($k, true)]++;
            }
            foreach (Kelas::whereNull('dihapus_pada')->get(['id', 'pusat_uuid']) as $k) {
                if (! isset($adaKelas[$k->pusat_uuid])) {
                    Kelas::whereKey($k->id)->update(['dihapus_pada' => now('UTC')->format('Y-m-d H:i:s')]);
                }
            }
            self::tandaiTahunAktif();
            $ada = [];
            foreach ($siswa as $p) {
                $ada[$p['uuid']] = true;
                $h['siswa'][self::upsertSiswa($p, true)]++;
            }
            foreach (Siswa::where('status', 'aktif')->get(['id', 'pusat_uuid']) as $s) {
                if (! isset($ada[$s->pusat_uuid])) {
                    Siswa::whereKey($s->id)->update(['status' => 'keluar']);
                    $h['siswa']['keluar']++;
                }
            }
            PusatKlien::simpan('sinkron_terakhir', now('UTC')->format('Y-m-d H:i:s'));
            PusatKlien::hapus('perlu_sinkron');

            return $h;
        });
    }

    /** Waktu "diperbarui" dari Pusat (ISO 8601) → UTC "Y-m-d H:i:s", atau null. */
    private static function versi(array $p): ?string
    {
        try {
            return empty($p['diperbarui']) ? null : \Illuminate\Support\Carbon::parse((string) $p['diperbarui'])->utc()->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    /** Kiriman lebih lama dari data yang sudah ada (mis. pengiriman ulang yang terlambat) tidak boleh menimpa. */
    private static function kedaluwarsa($lama, ?string $versi): bool
    {
        return $lama && $versi !== null && $lama->pusat_diperbarui !== null && $versi < (string) $lama->pusat_diperbarui;
    }

    /** $terkini: data diambil langsung dari API (sinkron penuh), bukan kiriman webhook yang bisa terlambat. @return 'baru'|'ubah'|'sama' */
    public static function upsertKelas(array $k, bool $terkini = false): string
    {
        $lama = Kelas::where('pusat_uuid', $k['uuid'])->first();
        $versi = self::versi($k);
        if (! $terkini && self::kedaluwarsa($lama, $versi)) { // sinkron penuh = keadaan terkini Pusat, selalu diterapkan
            return 'sama';
        }
        $data = [
            'nama' => (string) ($k['nama'] ?? $lama?->nama ?? ''), 'tingkat' => $k['tingkat'] ?? null, 'jurusan' => $k['jurusan_kode'] ?? null,
            'tahun_ajaran' => $k['tahun_ajaran'] ?? null, 'wali_uuid' => $k['wali_uuid'] ?? null, 'wali_nama' => $k['wali_nama'] ?? null,
            // Kelas yang dihapus di Pusat tetap disimpan (riwayat catatan BK) tetapi tidak lagi aktif.
            'dihapus_pada' => ! empty($k['dihapus']) ? ($lama?->dihapus_pada ?? now('UTC')->format('Y-m-d H:i:s')) : null,
        ];
        if ($versi !== null) {
            $data['pusat_diperbarui'] = $versi;
        }

        return self::simpanModel($lama, Kelas::class, ['pusat_uuid' => $k['uuid']], $data);
    }

    /** @return 'baru'|'ubah'|'sama' */
    public static function upsertSiswa(array $p, bool $terkini = false): string
    {
        $lama = Siswa::where('pusat_uuid', $p['uuid'])->first();
        $versi = self::versi($p);
        if (! $terkini && self::kedaluwarsa($lama, $versi)) { // sinkron penuh = keadaan terkini Pusat, selalu diterapkan
            return 'sama';
        }
        $peta = ['nis' => 'nis', 'nisn' => 'nisn', 'nama' => 'nama', 'jenis_kelamin' => 'jk', 'status' => 'status', 'tempat_lahir' => 'tempat_lahir',
            'tanggal_lahir' => 'tanggal_lahir', 'no_hp_ortu' => 'no_hp_ortu', 'nama_ortu' => 'nama_ortu'];
        $data = [];
        foreach ($peta as $dari => $ke) { // hanya kolom yang dikirim (cakupan "dasar" tidak memuat data pribadi): jangan menimpa dengan null
            if (array_key_exists($dari, $p)) {
                $data[$ke] = $p[$dari];
            }
        }
        if (array_key_exists('kelas_uuid', $p)) {
            $kelasId = $p['kelas_uuid'] ? Kelas::where('pusat_uuid', $p['kelas_uuid'])->value('id') : null;
            if ($p['kelas_uuid'] && ! $kelasId) {
                // Kelas tujuan belum dikenal (kirimannya belum tiba): pertahankan kelas lama, minta sinkron penuh.
                PusatKlien::simpan('perlu_sinkron', 1);
            } else {
                $data['kelas_id'] = $kelasId;
            }
        }
        if (! empty($p['dihapus'])) {
            $data['status'] = 'keluar';
        }
        if ($versi !== null) {
            $data['pusat_diperbarui'] = $versi;
        }
        if (! $lama) {
            $data += ['nis' => (string) ($p['nis'] ?? ''), 'nama' => (string) ($p['nama'] ?? '')];
        }

        return self::simpanModel($lama, Siswa::class, ['pusat_uuid' => $p['uuid']], $data);
    }

    private static function simpanModel($lama, string $kelas, array $kunci, array $data): string
    {
        if (! $lama) {
            $kelas::create($kunci + $data);

            return 'baru';
        }
        $lama->fill($data);
        if (! $lama->isDirty()) {
            return 'sama';
        }
        $lama->save();

        return 'ubah';
    }

    /** Tandai kelas di tahun ajaran aktif (sumber: state "tahun_aktif"). */
    public static function tandaiTahunAktif(): void
    {
        $nama = (string) PusatKlien::baca('tahun_aktif', '');
        Kelas::query()->update(['tahun_aktif' => false]);
        if ($nama !== '') {
            Kelas::where('tahun_ajaran', $nama)->whereNull('dihapus_pada')->update(['tahun_aktif' => true]);
        }
    }
}
