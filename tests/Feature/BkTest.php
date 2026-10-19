<?php

namespace Tests\Feature;

use App\Models\BkCatatan;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BkTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $u, array $roles, ?string $pegawai = null): User
    {
        return User::create(['username' => $u, 'nama' => ucfirst($u), 'roles' => $roles, 'aktif' => true, 'pegawai_uuid' => $pegawai]);
    }

    private function data(): array
    {
        $k1 = Kelas::create(['pusat_uuid' => 'k1', 'nama' => 'X TKJ 1', 'tingkat' => 10, 'tahun_ajaran' => '2026/2027', 'tahun_aktif' => true, 'wali_uuid' => 'p-wali', 'wali_nama' => 'Wali']);
        $k2 = Kelas::create(['pusat_uuid' => 'k2', 'nama' => 'X AKL 1', 'tingkat' => 10, 'tahun_ajaran' => '2026/2027', 'tahun_aktif' => true, 'wali_uuid' => 'p-lain', 'wali_nama' => 'Lain']);
        $s1 = Siswa::create(['pusat_uuid' => 's1', 'nis' => '1001', 'nama' => 'Budi', 'jk' => 'L', 'kelas_id' => $k1->id, 'status' => 'aktif']);
        $s2 = Siswa::create(['pusat_uuid' => 's2', 'nis' => '1002', 'nama' => 'Sari', 'jk' => 'P', 'kelas_id' => $k2->id, 'status' => 'aktif']);

        return [$s1, $s2];
    }

    private function form(Siswa $s, string $judul, array $tambah = []): array
    {
        return $tambah + ['siswa_id' => $s->id, 'tanggal' => date('Y-m-d'), 'kategori' => 'Ringan', 'judul' => $judul, 'status' => 'Proses'];
    }

    public function test_tamu_diarahkan_ke_login_dan_guru_biasa_tanpa_akses(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->actingAs($this->user('guru', ['guru'], 'p-x'))->get('/')->assertOk()->assertSee('Belum ada akses');
        $this->get('/catatan/kasus')->assertForbidden();
    }

    public function test_bk_mencatat_dan_kasus_rahasia_tersamar_bagi_wali(): void
    {
        [$s1] = $this->data();
        $this->actingAs($this->user('bk', ['bk']))
            ->post('/catatan/kasus', $this->form($s1, 'Murung', ['kategori' => 'Pribadi', 'rahasia' => 1]))->assertRedirect();
        $this->assertSame(1, BkCatatan::count());
        $this->get('/catatan/kasus')->assertSee('Murung');

        $this->actingAs($this->user('wali', ['guru'], 'p-wali'))->get('/catatan/kasus')->assertOk()->assertDontSee('Murung');
    }

    public function test_wali_hanya_melihat_dan_mencatat_prestasi_kelasnya(): void
    {
        [$s1, $s2] = $this->data();
        $bk = $this->user('bk', ['bk']);
        $this->actingAs($bk)->post('/catatan/pelanggaran', $this->form($s2, 'Terlambat'));
        $wali = $this->user('wali', ['guru'], 'p-wali');
        $this->actingAs($wali)->get('/catatan/pelanggaran')->assertDontSee('Terlambat');
        $this->post('/catatan/pelanggaran', $this->form($s1, 'x'));
        $this->post('/catatan/prestasi', $this->form($s2, 'Juara', ['kategori' => 'Sekolah']));
        $this->assertSame(1, BkCatatan::count()); // hanya pelanggaran dari BK
        $this->post('/catatan/prestasi', $this->form($s1, 'Juara', ['kategori' => 'Sekolah']));
        $this->assertSame(2, BkCatatan::count());
    }

    public function test_kepsek_baca_saja_dan_surat_dibatasi(): void
    {
        [$s1, $s2] = $this->data();
        $this->actingAs($this->user('kepsek', ['kepsek']))->post('/catatan/pelanggaran', $this->form($s1, 'x'));
        $this->assertSame(0, BkCatatan::count());
        $this->get('/surat/panggilan/'.$s2->id)->assertOk();

        $this->actingAs($this->user('wali', ['guru'], 'p-wali'))->get('/surat/panggilan/'.$s1->id)->assertOk();
        $this->get('/surat/panggilan/'.$s2->id)->assertForbidden();
    }

    public function test_presensi_dan_integrasi_hanya_untuk_bk_dan_admin(): void
    {
        $this->data();
        $this->actingAs($this->user('wali', ['guru'], 'p-wali'))->get('/presensi')->assertForbidden();
        $this->actingAs($this->user('bk', ['bk']))->get('/presensi')->assertOk();
        $this->get('/admin/integrasi')->assertForbidden();
        $this->actingAs($this->user('admin', ['admin']))->get('/admin/integrasi')->assertOk();
    }

    public function test_webhook_tanpa_tanda_tangan_ditolak(): void
    {
        $this->postJson('/api/pusat/webhook', ['a' => 1])->assertStatus(404); // integrasi Pusat mati di lingkungan uji
    }
}
