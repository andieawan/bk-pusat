<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Services\AbsensiKlien;
use App\Services\AlpaService;
use App\Services\Audit;
use App\Support\Dates;
use Illuminate\Http\Request;

/**
 * Presensi BK: rekap ketidakhadiran per kelas (dibaca dari Absensi), absensi harian satu kelas sekaligus dan tautan
 * delegasi Ketua Kelas (ditulis ke Absensi lewat API). Khusus Guru BK/Admin.
 */
class PresensiController extends Controller
{
    public function index(Request $request)
    {
        $kelas = Kelas::where('tahun_aktif', true)->orderBy('nama')->get();
        $kelasId = (int) ($request->query('kelas') ?: $kelas->first()?->id);
        $k = $kelas->firstWhere('id', $kelasId);
        $hari = Dates::today();
        $dari = Dates::valid($request->query('dari')) ? $request->query('dari') : date('Y-m-d', (int) Dates::parse($hari) - 29 * 86400);
        $sampai = Dates::valid($request->query('sampai')) ? $request->query('sampai') : $hari;

        $tanggal = Dates::valid($request->query('tanggal')) && $request->query('tanggal') <= $hari ? $request->query('tanggal') : $hari;
        $rekap = null;
        $galat = null;
        $tautan = [];
        $harian = null; // uuid siswa => [status, catatan] dari Absensi; null = tidak bisa dibaca
        if ($k && AbsensiKlien::aktif()) {
            try {
                $rekap = collect(AbsensiKlien::ringkasan((string) $k->pusat_uuid, $dari, $sampai));
                $rekap = $rekap->map(fn ($r) => $r + ['absen' => $r['izin'] + $r['sakit'] + $r['alpa']])->sortByDesc('absen')->values();
                $tautan = AbsensiKlien::delegasi((string) $k->pusat_uuid);
                $harian = collect(AbsensiKlien::harianKelas((string) $k->pusat_uuid, $tanggal))->keyBy('siswa_uuid');
            } catch (\RuntimeException $e) {
                $galat = $e->getMessage();
            }
        } elseif ($k) {
            $galat = 'Sambungan ke Absensi belum diatur (ABSENSI_URL dan ABSENSI_API_KEY di .env).';
        }
        $siswa = $k ? Siswa::aktif()->where('kelas_id', $k->id)->orderBy('nama')->get() : collect();
        $uuidSiswa = $siswa->keyBy('pusat_uuid');

        return view('presensi.index', [
            'kelas' => $kelas, 'k' => $k, 'rekap' => $rekap, 'galat' => $galat, 'tautan' => $tautan, 'siswa' => $siswa,
            'peta' => $uuidSiswa, 'dari' => $dari, 'sampai' => $sampai, 'hari' => $hari, 'batas' => config('bk.batas_alpa'),
            'tanggal' => $tanggal, 'harian' => $harian,
            'tokenBaru' => session('token_baru'),
        ]);
    }

    /** Absensi harian satu kelas sekaligus (ditulis ke Absensi). Hanya baris yang baru/berubah yang dikirim. */
    public function kelas(Request $request)
    {
        $d = $request->validate([
            'kelas' => 'required|integer|exists:kelas,id', 'tanggal' => 'required|date_format:Y-m-d|before_or_equal:today',
            'status' => 'required|array', 'status.*' => 'required|in:H,I,S,A',
            'catatan' => 'nullable|array', 'catatan.*' => 'nullable|string|max:200',
            'lama' => 'nullable|array', 'lama_catatan' => 'nullable|array',
        ]);
        $k = Kelas::findOrFail($d['kelas']);
        $siswa = Siswa::aktif()->where('kelas_id', $k->id)->whereIn('id', array_keys($d['status']))->get()->keyBy('id');
        $entri = [];
        foreach ($d['status'] as $id => $st) {
            $s = $siswa[(int) $id] ?? null;
            if (! $s) {
                continue;
            }
            $cat = trim((string) ($d['catatan'][$id] ?? ''));
            $lama = (string) ($d['lama'][$id] ?? '');
            if ($lama !== '' && $lama === $st && $cat === trim((string) ($d['lama_catatan'][$id] ?? ''))) {
                continue; // tidak berubah: tidak menimpa catatan/pencatat di Absensi
            }
            $entri[] = ['siswa_uuid' => $s->pusat_uuid, 'status' => $st, 'catatan' => $cat !== '' ? $cat : null];
        }
        $balik = redirect()->route('presensi', ['kelas' => $k->id, 'tanggal' => $d['tanggal']]);
        if (! $entri) {
            return $balik->with('success', 'Tidak ada perubahan untuk disimpan.');
        }
        try {
            $r = AbsensiKlien::manualKelas((string) $k->pusat_uuid, $d['tanggal'], $entri, $this->me()->nama);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', 'Gagal menyimpan ke Absensi: '.$e->getMessage());
        }
        Audit::catat('Absensi kelas BK', $this->me()->nama, "{$k->nama} {$d['tanggal']}: ".count($entri).' siswa');

        return $balik->with('success', "Absensi {$k->nama} tanggal ".Dates::human($d['tanggal']).' tersimpan di Absensi ('
            .(int) ($r['dibuat'] ?? 0).' baru, '.(int) ($r['diperbarui'] ?? 0).' diubah).');
    }

    public function delegasi(Request $request)
    {
        $d = $request->validate(['kelas' => 'required|integer|exists:kelas,id', 'jam' => 'nullable|integer|min:1|max:24']);
        $k = Kelas::findOrFail($d['kelas']);
        try {
            $t = AbsensiKlien::buatDelegasi((string) $k->pusat_uuid, (int) ($d['jam'] ?? 24), $this->me()->nama);
        } catch (\RuntimeException $e) {
            return back()->with('error', 'Gagal membuat tautan di Absensi: '.$e->getMessage());
        }
        Audit::catat('Buat delegasi ketua kelas', $this->me()->nama, $k->nama);

        return redirect()->route('presensi', ['kelas' => $k->id])->with('token_baru', $t['tautan'] ?? '')->with('success', 'Tautan delegasi ketua kelas dibuat.');
    }

    public function cabut(Request $request)
    {
        $d = $request->validate(['token' => 'required|string|max:128', 'kelas' => 'required|integer']);
        try {
            AbsensiKlien::cabutDelegasi($d['token'], $this->me()->nama);
        } catch (\RuntimeException $e) {
            return back()->with('error', 'Gagal mencabut tautan: '.$e->getMessage());
        }
        Audit::catat('Cabut delegasi ketua kelas', $this->me()->nama, 'Kelas #'.$d['kelas']);

        return redirect()->route('presensi', ['kelas' => $d['kelas']])->with('success', 'Tautan delegasi dicabut.');
    }
}
