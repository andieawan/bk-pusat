<?php

namespace Tests\Feature;

use App\Services\PusatSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Setelah sinkron, BK melapor hasilnya ke Pusat (tampil di menu Aplikasi Pusat + notifikasi admin). */
class LaporanSinkronTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['pusat.enabled' => true, 'pusat.url' => 'https://p.test', 'pusat.client_id' => 'c', 'pusat.client_secret' => 's']);
    }

    public function test_sinkron_berhasil_dan_gagal_dilaporkan_ke_pusat(): void
    {
        $kosong = ['data' => [], 'meta' => ['total' => 0, 'halaman_terakhir' => 1]];
        Http::fake([
            'p.test/api/v1/master/tahun-ajaran*' => Http::sequence()
                ->push(['data' => [['uuid' => 't1', 'nama' => '2026/2027', 'aktif' => true]], 'meta' => ['total' => 1, 'halaman_terakhir' => 1]])
                ->push($kosong),
            'p.test/api/v1/sinkron/laporan' => Http::response(['ok' => true]),
            'p.test/*' => Http::response($kosong),
        ]);

        $h = app(PusatSync::class)->jalankan();
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/v1/sinkron/laporan') && $r['ok'] === true
            && $r['pemicu'] === 'otomatis' && $r['hasil']['tahun_aktif'] === $h['tahun_aktif']);

        try {
            app(PusatSync::class)->jalankan(); // tahun ajaran aktif tidak ada → gagal
            $this->fail('seharusnya gagal');
        } catch (\RuntimeException $e) {
        }
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/v1/sinkron/laporan') && $r['ok'] === false
            && str_contains($r['galat'], 'tahun ajaran aktif'));
    }

    public function test_tombol_sinkronkan_di_pusat_langsung_menjalankan_sinkron_setelah_jawaban(): void
    {
        config(['pusat.webhook_secret' => 'wh']);
        Http::fake([
            'p.test/api/v1/master/tahun-ajaran*' => Http::response(['data' => [['uuid' => 't1', 'nama' => '2026/2027', 'aktif' => true]], 'meta' => ['total' => 1, 'halaman_terakhir' => 1]]),
            'p.test/api/v1/sinkron/laporan' => Http::response(['ok' => true]),
            'p.test/*' => Http::response(['data' => [], 'meta' => ['total' => 0, 'halaman_terakhir' => 1]]),
        ]);
        $badan = json_encode(['event' => 'sinkron.diminta', 'data' => ['alasan' => 'uji']]);
        $t = time();
        $this->call('POST', '/api/pusat/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_PUSAT_EVENT' => 'sinkron.diminta', 'HTTP_X_PUSAT_DELIVERY' => 'd-langsung',
            'HTTP_X_PUSAT_TIMESTAMP' => $t, 'HTTP_X_PUSAT_SIGNATURE' => 'sha256='.hash_hmac('sha256', $t.'.'.$badan, 'wh'),
        ], $badan)->assertOk();
        // Uji HTTP Laravel menjalankan terminate() setelah jawaban, sama seperti PHP-FPM setelah koneksi ditutup.
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/v1/sinkron/laporan') && $r['ok'] === true);
        $this->assertNull(\App\Services\PusatKlien::baca('perlu_sinkron'), 'sinkron langsung selesai → penjadwal tidak perlu mengulang');
    }
}
