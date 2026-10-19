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
            foreach ($kelas as $k) {
                $h['kelas'][self::upsertKelas($k)]++;
            }
            self::tandaiTahunAktif();
            $ada = [];
            foreach ($siswa as $p) {
                $ada[$p['uuid']] = true;
                $h['siswa'][self::upsertSiswa($p)]++;
            }
            foreach (Siswa::where('status', 'aktif')->get(['id', 'pusat_uuid']) as $s) {
                if (! isset($ada[$s->pusat_uuid])) {
                    Siswa::whereKey($s->id)->update(['status' => 'keluar']);
                    $h['siswa']['keluar']++;
                }
            }
            PusatKlien::simpan('sinkron_terakhir', now('UTC')->format('Y-m-d H:i:s'));

            return $h;
        });
    }

    /** @return 'baru'|'ubah'|'sama' */
    public static function upsertKelas(array $k): string
    {
        $data = [
            'nama' => (string) ($k['nama'] ?? ''), 'tingkat' => $k['tingkat'] ?? null, 'jurusan' => $k['jurusan_kode'] ?? null,
            'tahun_ajaran' => $k['tahun_ajaran'] ?? null, 'wali_uuid' => $k['wali_uuid'] ?? null, 'wali_nama' => $k['wali_nama'] ?? null,
        ];

        return self::simpanModel(Kelas::where('pusat_uuid', $k['uuid'])->first(), Kelas::class, ['pusat_uuid' => $k['uuid']], $data);
    }

    /** @return 'baru'|'ubah'|'sama' */
    public static function upsertSiswa(array $p): string
    {
        $peta = ['nis' => 'nis', 'nisn' => 'nisn', 'nama' => 'nama', 'jenis_kelamin' => 'jk', 'status' => 'status', 'tempat_lahir' => 'tempat_lahir',
            'tanggal_lahir' => 'tanggal_lahir', 'no_hp_ortu' => 'no_hp_ortu', 'nama_ortu' => 'nama_ortu'];
        $data = [];
        foreach ($peta as $dari => $ke) { // hanya kolom yang dikirim (cakupan "dasar" tidak memuat data pribadi): jangan menimpa dengan null
            if (array_key_exists($dari, $p)) {
                $data[$ke] = $p[$dari];
            }
        }
        if (array_key_exists('kelas_uuid', $p)) {
            $data['kelas_id'] = $p['kelas_uuid'] ? Kelas::where('pusat_uuid', $p['kelas_uuid'])->value('id') : null;
        }
        if (! empty($p['dihapus'])) {
            $data['status'] = 'keluar';
        }
        $lama = Siswa::where('pusat_uuid', $p['uuid'])->first();
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
            Kelas::where('tahun_ajaran', $nama)->update(['tahun_aktif' => true]);
        }
    }
}
