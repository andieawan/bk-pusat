<?php

namespace App\Console\Commands;

use App\Support\CadanganDb;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kosongkan data BK untuk memulai ulang bersama Pusat (mis. ganti data dummy).
 * DIHAPUS : kelas, siswa, catatan BK, akun selain admin, status sinkron Pusat. Berkas unggahan dipindah ke storage/app/cadangan.
 * DISIMPAN: akun admin, pengaturan, log audit, sambungan & profil dari Pusat.
 */
class KosongkanData extends Command
{
    protected $signature = 'bk:kosongkan-data
        {--yakin : Lewati pertanyaan konfirmasi (untuk skrip)}
        {--tanpa-cadangan : Jangan buat cadangan database dulu (tidak disarankan)}';

    protected $description = 'Hapus semua data siswa/kelas & catatan BK serta akun non-admin — cadangan dibuat otomatis lebih dulu';

    /** Urutan: anak dulu, induk belakangan. */
    private const TABEL = [
        'bk_catatan' => 'Catatan BK', 'siswa' => 'Siswa', 'kelas' => 'Kelas',
        'pusat_webhook' => 'Catatan webhook', 'pusat_state' => 'Status sinkron Pusat',
    ];

    public function handle(): int
    {
        $ada = array_filter(self::TABEL, fn ($t) => Schema::hasTable($t), ARRAY_FILTER_USE_KEY);
        $hapusAkun = $this->akunDihapus();
        $baris = [];
        foreach ($ada as $t => $label) {
            $baris[] = [$label, DB::table($t)->count()];
        }
        $baris[] = ['Akun selain admin', count($hapusAkun)];
        $this->warn('Data yang akan DIHAPUS PERMANEN dari BK:');
        $this->table(['Data', 'Jumlah'], $baris);
        $this->line('Tetap disimpan: akun admin, pengaturan, log audit, sambungan & profil dari Pusat.');

        if (! $this->option('yakin') && $this->ask('Ketik HAPUS SEMUA untuk melanjutkan') !== 'HAPUS SEMUA') {
            $this->info('Dibatalkan. Tidak ada yang dihapus.');

            return self::FAILURE;
        }
        if (! $this->option('tanpa-cadangan')) {
            try {
                $this->info('Cadangan dibuat: '.CadanganDb::buat('bk-sebelum-kosongkan'));
            } catch (\Throwable $e) {
                $this->error($e->getMessage());
                $this->line('Tidak ada yang dihapus. Perbaiki masalah cadangan, atau jalankan ulang dengan --tanpa-cadangan bila sudah punya cadangan lain.');

                return self::FAILURE;
            }
        }

        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($ada, $hapusAkun) {
                foreach (array_keys($ada) as $t) {
                    DB::table($t)->delete();
                }
                DB::table('users')->whereIn('id', $hapusAkun)->delete();
                $kosong = array_fill_keys(array_values(array_filter(['pegawai_uuid', 'siswa_uuid'], fn ($k) => Schema::hasColumn('users', $k))), null);
                if ($kosong) {
                    DB::table('users')->update($kosong);
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
        $pindah = $this->pindahkanBerkas();
        if ($pindah) {
            $this->info('Berkas unggahan lama dipindah ke: '.$pindah);
        }
        \App\Services\Audit::catat('Kosongkan data', 'artisan', 'Semua data dikosongkan; '.count($hapusAkun).' akun dihapus');

        $this->info('Selesai. BK kosong; jalankan sinkron (tombol Sinkron di Admin, atau php artisan bk:sinkron) setelah data baru diimpor di Pusat.');

        return self::SUCCESS;
    }

    /** @return int[] id akun tanpa peran admin */
    private function akunDihapus(): array
    {
        return DB::table('users')->get(['id', 'roles'])
            ->reject(fn ($u) => in_array('admin', (array) json_decode((string) $u->roles, true), true))
            ->pluck('id')->map(fn ($v) => (int) $v)->all();
    }

    /** Berkas unggahan (surat, foto) tidak ikut cadangan database → dipindah, bukan dihapus. */
    private function pindahkanBerkas(): ?string
    {
        $sumber = storage_path('app/private');
        $isi = is_dir($sumber) ? array_diff(scandir($sumber) ?: [], ['.', '..', '.gitignore']) : [];
        if ($isi === []) {
            return null;
        }
        $tujuan = storage_path('app/cadangan/berkas-'.now()->format('Ymd-His'));
        @mkdir($tujuan, 0750, true);
        foreach ($isi as $n) {
            @rename($sumber.'/'.$n, $tujuan.'/'.$n);
        }

        return $tujuan;
    }
}
