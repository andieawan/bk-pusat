<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Services\PusatKlien;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Penerima webhook Pusat: urutan kiriman, kelas belum dikenal, kelas dihapus. */
class WebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['pusat.enabled' => true, 'pusat.url' => 'https://p.test', 'pusat.client_id' => 'c', 'pusat.client_secret' => 's', 'pusat.webhook_secret' => 'wh']);
        Kelas::create(['pusat_uuid' => 'k1', 'nama' => 'X TKJ 1', 'tahun_ajaran' => '2026/2027']);
        Siswa::create(['pusat_uuid' => 's1', 'nis' => '1', 'nama' => 'Budi', 'kelas_id' => 1, 'status' => 'aktif']);
    }

    private function kirim(string $event, array $data, string $id)
    {
        $badan = json_encode(['event' => $event, 'data' => $data]);
        $t = time();

        return $this->call('POST', '/api/pusat/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_PUSAT_EVENT' => $event, 'HTTP_X_PUSAT_DELIVERY' => $id,
            'HTTP_X_PUSAT_TIMESTAMP' => $t, 'HTTP_X_PUSAT_SIGNATURE' => 'sha256='.hash_hmac('sha256', $t.'.'.$badan, 'wh'),
        ], $badan);
    }

    public function test_kiriman_lama_tidak_menimpa_yang_baru(): void
    {
        $this->kirim('siswa.diperbarui', ['uuid' => 's1', 'nama' => 'Budi Baru', 'diperbarui' => '2026-10-20T02:00:00+07:00'], 'd1')->assertOk();
        $this->kirim('siswa.diperbarui', ['uuid' => 's1', 'nama' => 'Budi Lama', 'diperbarui' => '2026-10-20T01:00:00+07:00'], 'd2')->assertOk();
        $this->assertSame('Budi Baru', Siswa::first()->nama);
    }

    public function test_kelas_belum_dikenal_mempertahankan_kelas_lama(): void
    {
        $this->kirim('siswa.diperbarui', ['uuid' => 's1', 'kelas_uuid' => 'belum-ada'], 'd1')->assertOk();
        $this->assertSame(1, (int) Siswa::first()->kelas_id);
        $this->assertNotNull(PusatKlien::baca('perlu_sinkron'));
    }

    public function test_kelas_dihapus_tidak_aktif_lagi(): void
    {
        PusatKlien::simpan('tahun_aktif', '2026/2027');
        $this->kirim('kelas.dihapus', ['uuid' => 'k1', 'nama' => 'X TKJ 1', 'tahun_ajaran' => '2026/2027', 'dihapus' => '2026-10-20T03:00:00+07:00'], 'd1')->assertOk();
        $k = Kelas::first();
        $this->assertNotNull($k->dihapus_pada);
        $this->assertFalse((bool) $k->tahun_aktif);
    }

    public function test_pengiriman_ulang_dianggap_duplikat(): void
    {
        $this->kirim('siswa.diperbarui', ['uuid' => 's1', 'nama' => 'A'], 'd1')->assertOk();
        $this->kirim('siswa.diperbarui', ['uuid' => 's1', 'nama' => 'B'], 'd1')->assertJson(['duplikat' => true]);
        $this->assertSame('A', Siswa::first()->nama);
    }

    public function test_alamat_dari_pusat_mengisi_surat_dan_home_visit(): void
    {
        $this->kirim('siswa.diperbarui', ['uuid' => 's1', 'alamat' => 'Dusun Krajan RT 02, Pakusari', 'diperbarui' => '2026-10-20T02:00:00+07:00'], 'd1')->assertOk();
        $s = Siswa::first();
        $this->assertSame('Dusun Krajan RT 02, Pakusari', $s->alamat);
        $bk = \App\Models\User::create(['username' => 'bk', 'nama' => 'Bu BK', 'roles' => ['bk'], 'aktif' => true]);
        $this->actingAs($bk)->get('/surat/peringatan/'.$s->id)->assertOk()->assertSee('Dusun Krajan RT 02, Pakusari');
        $this->actingAs($bk)->post('/catatan/home_visit', ['siswa_id' => $s->id, 'tanggal' => date('Y-m-d'), 'judul' => 'Kunjungan', 'status' => 'Proses'])->assertRedirect();
        $this->assertSame('Dusun Krajan RT 02, Pakusari', \App\Models\BkCatatan::first()->x('alamat'));
    }
}
