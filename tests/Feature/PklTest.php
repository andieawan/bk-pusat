<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Menu PKL di BK: dibaca dari aplikasi PKL. */
class PklTest extends TestCase
{
    use RefreshDatabase;

    private function baris(): array
    {
        return ['siswa' => ['uuid' => 's1', 'nis' => '1001', 'nama' => 'Budi', 'kelas' => 'XII TKJ 1'], 'periode' => 'PKL Gel 1', 'mulai' => '2026-10-17', 'selesai' => '2026-11-26',
            'status' => 'aktif', 'dudi' => 'CV Jaringan', 'guru_pembimbing' => 'Guru Satu', 'pembimbing_industri' => null,
            'rekap' => ['H' => 2, 'I' => 0, 'S' => 1, 'A' => 3, 'T' => 1, 'kosong' => 2, 'hari' => 8, 'persen' => 25.0], 'jurnal_tertunda' => 4,
            'nilai_akhir' => null, 'predikat' => null, 'tautan' => 'https://pkl.test/bimbingan/1', 'alasan' => ['5 hari alpa / tanpa keterangan']];
    }

    public function test_daftar_perhatian_dan_riwayat_siswa(): void
    {
        config(['bk.pkl' => ['url' => 'https://pkl.test', 'api_key' => 'kunci', 'timeout' => 5, 'verify_ssl' => true]]);
        Http::fake([
            'pkl.test/api/v1/perhatian' => Http::response(['data' => [$this->baris()], 'meta' => ['batas' => 3, 'jumlah_pkl_aktif' => 20]]),
            'pkl.test/api/v1/siswa/s1' => Http::response(['data' => [$this->baris()]]),
            'pkl.test/api/v1/ping' => Http::response(['data' => ['ok' => true]]),
        ]);
        $k = Kelas::create(['pusat_uuid' => 'k1', 'nama' => 'XII TKJ 1', 'tingkat' => 12, 'tahun_ajaran' => '2026/2027', 'tahun_aktif' => true]);
        $s = Siswa::create(['pusat_uuid' => 's1', 'nis' => '1001', 'nama' => 'Budi', 'jk' => 'L', 'kelas_id' => $k->id, 'status' => 'aktif']);
        $bk = User::create(['username' => 'bk', 'nama' => 'Bk', 'roles' => ['bk'], 'aktif' => true]);
        $this->actingAs($bk)->get('/pkl')->assertOk()->assertSee('Budi')->assertSee('CV Jaringan')->assertSee('5 hari alpa')->assertSee('1 dari 20');
        $this->get('/pkl/siswa/'.$s->id)->assertOk()->assertSee('Jurnal 4 hari')->assertSee('2 tanpa keterangan');
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer kunci'));
        $guru = User::create(['username' => 'g', 'nama' => 'G', 'roles' => ['guru'], 'aktif' => true]);
        $this->actingAs($guru)->get('/pkl')->assertForbidden();
        $adm = User::create(['username' => 'adm', 'nama' => 'Adm', 'roles' => ['admin'], 'aktif' => true]);
        $this->actingAs($adm)->post('/admin/integrasi/uji-pkl')->assertSessionHas('success');
    }

    public function test_kunci_salah_tampil_jelas(): void
    {
        config(['bk.pkl' => ['url' => 'https://pkl.test', 'api_key' => 'salah', 'timeout' => 5, 'verify_ssl' => true]]);
        Http::fake(['pkl.test/*' => Http::response(['error' => ['kode' => 'kunci_salah']], 401)]);
        $bk = User::create(['username' => 'bk', 'nama' => 'Bk', 'roles' => ['bk'], 'aktif' => true]);
        $this->actingAs($bk)->get('/pkl')->assertOk()->assertSee('Kunci API PKL salah');
    }
}
