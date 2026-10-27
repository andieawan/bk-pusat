<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Services\AbsensiKlien;
use App\Services\Audit;
use App\Services\PusatKlien;
use App\Services\PusatSync;

/** Admin → Integrasi: status sambungan Pusat & Absensi, uji koneksi, sinkron data induk manual. */
class IntegrasiController extends Controller
{
    public function index()
    {
        $siap = PusatKlien::siap();

        return view('admin.integrasi', [
            'p' => [
                'aktif' => PusatKlien::aktif(), 'siap' => $siap, 'url' => config('pusat.url'),
                'callback' => route('sso.callback'), 'webhook' => url('/api/pusat/webhook'),
                'tahun' => $siap ? PusatKlien::baca('tahun_aktif') : null,
                'terakhir' => $siap ? PusatKlien::baca('sinkron_terakhir') : null,
                'jumlah' => ['kelas' => $siap ? Kelas::count() : 0, 'siswa aktif' => $siap ? Siswa::aktif()->count() : 0],
                'perluSinkron' => $siap && PusatKlien::baca('perlu_sinkron') !== null,
                'otomatis' => (bool) config('pusat.sinkron_otomatis'),
                'ruteUji' => route('admin.integrasi.pusat'), 'ruteSinkron' => route('admin.integrasi.sinkron'),
            ],
            'absensiAktif' => AbsensiKlien::aktif(), 'absensiUrl' => config('bk.absensi.url'),
        ]);
    }

    public function ujiPusat()
    {
        if (! PusatKlien::aktif() || ! PusatKlien::siap()) {
            return back()->with('error', 'Integrasi Pusat belum aktif atau database belum diperbarui.');
        }
        try {
            $res = PusatKlien::http()->get('/api/v1/ping');
        } catch (\Throwable $e) {
            return back()->with('error', 'Pusat tidak dapat dihubungi: '.mb_substr($e->getMessage(), 0, 200));
        }
        if ($res->status() === 401) {
            return back()->with('error', 'Pusat menolak kredensial (client_id/secret salah atau aplikasi nonaktif).');
        }

        return $res->ok() ? back()->with('success', 'Terhubung ke Pusat. Kredensial benar.') : back()->with('error', 'Pusat membalas HTTP '.$res->status().'.');
    }

    public function sinkron()
    {
        if (! PusatKlien::aktif() || ! PusatKlien::siap()) {
            return back()->with('error', 'Integrasi Pusat belum aktif atau database belum diperbarui.');
        }
        try {
            $h = app(PusatSync::class)->jalankan();
        } catch (\Throwable $e) {
            if (! $e instanceof \RuntimeException) {
                report($e);
            }

            return back()->with('error', 'Sinkron gagal, tidak ada data yang berubah: '.mb_substr($e->getMessage(), 0, 300));
        }
        Audit::catat('Sinkron Pusat', $this->me()->nama, 'Manual dari Admin → Integrasi');

        return back()->with('success', 'Sinkron selesai.')->with('hasil', $h);
    }

    public function ujiAbsensi()
    {
        try {
            $d = AbsensiKlien::ping();
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        $scopes = (array) ($d['scopes'] ?? []);
        $kurang = array_values(array_diff(['kehadiran:baca', 'kehadiran:tulis'], $scopes));

        return $kurang
            ? back()->with('error', 'Terhubung ke Absensi, tetapi kunci API belum punya izin: '.implode(', ', $kurang).'.')
            : back()->with('success', 'Terhubung ke Absensi. Kunci API benar dan izin lengkap.');
    }
}
