<?php

namespace App\Http\Controllers;

use App\Services\BkService;
use App\Support\BkModules;
use App\Support\Dates;

/** Ringkasan BK untuk tahun ajaran berjalan. */
class BerandaController extends Controller
{
    public function index()
    {
        $u = $this->me();
        if (! BkService::canOpen($u)) {
            return view('beranda_kosong');
        }
        $tahun = (string) \App\Services\PusatKlien::baca('tahun_aktif', '');
        [$a] = preg_match('/^(\d{4})/', $tahun, $m) ? [(int) $m[1]] : [(int) substr(Dates::today(), 0, 4) - (int) (substr(Dates::today(), 5, 2) < 7)];
        [$dari, $sampai] = [$a.'-07-01', ($a + 1).'-06-30'];
        $dasar = fn () => BkService::visible($u)->whereBetween('tanggal', [$dari, $sampai]);
        $stat = [];
        foreach (BkModules::all() as $jenis => $m) {
            $stat[$jenis] = [
                'total' => $dasar()->where('jenis', $jenis)->count(),
                'proses' => $m['status'] ? $dasar()->where('jenis', $jenis)->where('status', 'Proses')->count() : null,
            ];
        }

        return view('beranda', [
            'stat' => $stat, 'tahun' => $tahun ?: ($a.'/'.($a + 1)),
            'perTingkat' => $dasar()->where('jenis', 'pelanggaran')->selectRaw('kategori, COUNT(*) AS n')->groupBy('kategori')->pluck('n', 'kategori'),
            'terbaru' => BkService::visible($u)->with(['siswa', 'kelas'])->latest('id')->limit(10)->get(),
            'proses' => $dasar()->where('status', 'Proses')->with(['siswa', 'kelas'])->orderBy('tanggal')->limit(10)->get(),
        ]);
    }
}
