<?php

namespace Tests\Feature;

use App\Services\Sambungan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Sambungan antar-aplikasi diatur dari Pusat (Administrasi → Sambungan): diambil lewat API, disimpan terenkripsi, menimpa .env. */
class SambunganPusatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        @unlink(Sambungan::berkas());
        config(['pusat.enabled' => true, 'pusat.url' => 'https://p.test', 'pusat.client_id' => 'c', 'pusat.client_secret' => 's', 'pusat.webhook_secret' => 'wh']);
    }

    protected function tearDown(): void
    {
        @unlink(Sambungan::berkas());
        parent::tearDown();
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

    private function pusat(array $data, int $status = 200): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory); // ganti balasan palsu sebelumnya
        Http::fake(['p.test/api/v1/sambungan' => Http::response(['data' => $data], $status)]);
    }

    private function s(string $jenis, bool $aktif, string $url = '', string $kunci = ''): array
    {
        return ['jenis' => $jenis, 'aktif' => $aktif, 'peran' => 'x', 'mitra' => 'm', 'url' => $url, 'kunci' => $kunci];
    }

    public function test_webhook_mengisi_alamat_dan_kunci_absensi_serta_pkl(): void
    {
        config(['bk.absensi.url' => '', 'bk.absensi.api_key' => '', 'bk.pkl.url' => 'https://lama.test', 'bk.pkl.api_key' => 'lama']);
        $this->pusat([$this->s('absensi-bk', true, 'https://absensi.test', str_repeat('a', 40)), $this->s('pkl-bk', false)]);
        $this->kirim('sambungan.diperbarui', ['jenis' => 'absensi-bk'], 'd1')->assertOk();
        $this->assertSame('https://absensi.test', config('bk.absensi.url'));
        $this->assertSame(str_repeat('a', 40), config('bk.absensi.api_key'));
        $this->assertSame('', config('bk.pkl.url'));
        $this->assertTrue(\App\Services\AbsensiKlien::aktif());
        $this->assertFalse(\App\Services\PklKlien::aktif());
    }

    public function test_pusat_gagal_webhook_tidak_dicatat(): void
    {
        $this->pusat([], 500);
        $this->kirim('sambungan.diperbarui', ['jenis' => 'pkl-bk'], 'd9')->assertStatus(500);
        $this->assertSame(0, DB::table('pusat_webhook')->count());
    }
}
