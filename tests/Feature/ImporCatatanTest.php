<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BkCatatan;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use App\Support\Xlsx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Impor catatan BK lama dari templat Excel (semua-atau-tidak-sama-sekali). */
class ImporCatatanTest extends TestCase
{
    use RefreshDatabase;

    private User $bk;

    protected function setUp(): void
    {
        parent::setUp();
        $k = Kelas::create(['pusat_uuid' => 'k1', 'nama' => 'X TKJ 1', 'tingkat' => 10, 'tahun_ajaran' => '2026/2027', 'tahun_aktif' => true]);
        Siswa::create(['pusat_uuid' => 's1', 'nis' => '1001', 'nama' => 'Budi Santoso', 'jk' => 'L', 'kelas_id' => $k->id, 'status' => 'aktif']);
        Siswa::create(['pusat_uuid' => 's2', 'nis' => '1002', 'nama' => 'Sari', 'jk' => 'P', 'kelas_id' => $k->id, 'status' => 'aktif']);
        $this->bk = User::create(['username' => 'bk', 'nama' => 'Bu BK', 'roles' => ['bk'], 'aktif' => true]);
    }

    private function berkas(array $lembar): UploadedFile
    {
        $tpl = \App\Services\ImporCatatan::templat();
        foreach ($lembar as $nama => $rows) {
            $tpl[$nama] = array_merge($tpl[$nama], $rows);
        }
        $path = tempnam(sys_get_temp_dir(), 'bk').'.xlsx';
        file_put_contents($path, Xlsx::build($tpl));

        return new UploadedFile($path, 'impor.xlsx', null, null, true);
    }

    private function unggah(array $lembar, bool $uji = false)
    {
        return $this->actingAs($this->bk)->post('/impor', ['berkas' => $this->berkas($lembar), 'uji' => $uji ? 1 : 0]);
    }

    public function test_templat_berisi_lembar_tiap_jenis_dan_daftar_siswa(): void
    {
        $this->actingAs($this->bk)->get('/impor')->assertOk()->assertSee('Unduh templat');
        $res = $this->get('/impor/templat')->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'tp').'.xlsx';
        file_put_contents($path, $res->getContent());
        $s = Xlsx::readAllSheets($path, 'x.xlsx');
        $this->assertCount(7, $s); // petunjuk + 5 jenis + daftar siswa
        $this->assertSame(['Tanggal', 'NIS', 'Nama Siswa', 'Tingkat', 'Pelanggaran', 'Kronologi / keterangan', 'Tindak lanjut / sanksi', 'Status'], $s[1][0]);
        $this->assertContains(['X TKJ 1', '1001', 'Budi Santoso'], $s[6]);
    }

    public function test_impor_semua_jenis_dan_periksa_saja_tidak_menyimpan(): void
    {
        $isi = [
            'PELANGGARAN' => [['2025-09-01', '1001', '', 'ringan', 'Membolos', 'Jam ke-3', 'Teguran', '']],
            'BUKU KASUS' => [['02/09/2025', '1002', '', 'Pribadi', 'Murung', 'konseling individu', 'Sering diam', 'Pantau', 'proses', 'Ya']],
            'PRESTASI' => [['2025-08-17', '', 'budi  santoso', 'Kabupaten/Kota', 'Lomba Poster', 'Juara 2', '']],
            'HOME VISIT' => [['2025-10-01', '1001', '', 'Alpa 5 hari', 'Guru BK', 'Ibu', '', 'Sakit', 'Masuk lagi', 'Selesai']],
            'RIWAYAT SURAT' => [['2025-10-02', '1001', '', 'Surat Panggilan Orang Tua', 'Panggilan', '421/BK/2025', '', '']],
        ];
        $this->unggah($isi, true)->assertSessionHas('info');
        $this->assertSame(0, BkCatatan::count());

        $this->unggah($isi)->assertSessionHas('success');
        $this->assertSame(5, BkCatatan::count());
        $p = BkCatatan::where('jenis', 'pelanggaran')->first();
        $this->assertSame(['Ringan', 'Selesai', 'Teguran'], [$p->kategori, $p->status, $p->tindak_lanjut]);
        $k = BkCatatan::where('jenis', 'kasus')->first();
        $this->assertSame([true, 'Proses', 'Konseling Individu', '2025-09-02'], [$k->rahasia, $k->status, $k->x('layanan'), $k->tanggal]);
        $this->assertSame('Juara 2', BkCatatan::where('jenis', 'prestasi')->first()->x('peringkat'));
        $this->assertSame('421/BK/2025', BkCatatan::where('jenis', 'surat')->first()->x('nomor'));
        $this->assertSame(1, AuditLog::where('aksi', 'Impor catatan BK lama')->count());

        $this->unggah($isi)->assertSessionHas('success', fn ($m) => str_contains($m, '5 catatan sudah ada'));
        $this->assertSame(5, BkCatatan::count());
    }

    public function test_satu_baris_salah_membatalkan_semua(): void
    {
        $this->unggah(['PELANGGARAN' => [
            ['2025-09-01', '1001', '', 'Ringan', 'Membolos', '', '', ''],
            ['2099-01-01', '9999', '', 'Sedikit', '', '', '', 'Beres'],
        ]])->assertSessionHas('error')->assertSessionHas('galat', fn ($g) => str_contains($g[0], 'Pelanggaran baris 3')
            && str_contains($g[0], 'masa depan') && str_contains($g[0], 'NIS "9999"') && str_contains($g[0], 'Tingkat "Sedikit"') && str_contains($g[0], 'status "Beres"'));
        $this->assertSame(0, BkCatatan::count());
    }

    public function test_hanya_guru_bk(): void
    {
        $wali = User::create(['username' => 'wali', 'nama' => 'Wali', 'roles' => ['guru'], 'aktif' => true, 'pegawai_uuid' => 'p-w']);
        $this->actingAs($wali)->get('/impor')->assertForbidden();
        $this->post('/impor')->assertForbidden();
    }
}
