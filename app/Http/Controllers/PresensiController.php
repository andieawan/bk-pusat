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
 * Presensi BK: rekap ketidakhadiran per kelas (dibaca dari Absensi), input absensi manual dan tautan
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

        $rekap = null;
        $galat = null;
        $tautan = [];
        if ($k && AbsensiKlien::aktif()) {
            try {
                $rekap = collect(AbsensiKlien::ringkasan((string) $k->pusat_uuid, $dari, $sampai));
                $rekap = $rekap->map(fn ($r) => $r + ['absen' => $r['izin'] + $r['sakit'] + $r['alpa']])->sortByDesc('absen')->values();
                $tautan = AbsensiKlien::delegasi((string) $k->pusat_uuid);
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
            'tokenBaru' => session('token_baru'),
        ]);
    }

    public function manual(Request $request)
    {
        $d = $request->validate([
            'siswa' => 'required|integer|exists:siswa,id', 'status' => 'required|in:H,I,S,A',
            'tanggal' => 'required|date_format:Y-m-d|before_or_equal:today', 'catatan' => 'nullable|string|max:200',
        ], [], ['siswa' => 'siswa']);
        $s = Siswa::findOrFail($d['siswa']);
        try {
            $r = AbsensiKlien::manual($s->pusat_uuid, $d['tanggal'], $d['status'], $d['catatan'] ?? null, $this->me()->nama);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', 'Gagal menyimpan ke Absensi: '.$e->getMessage());
        }
        Audit::catat('Absensi manual BK', $this->me()->nama, "{$s->nama} {$d['tanggal']} = {$d['status']}");

        return redirect()->route('presensi', ['kelas' => $s->kelas_id])->with('success', "Absensi manual untuk {$s->nama} tersimpan di Absensi (".(($r['dibuat'] ?? 0) ? 'baru' : 'diperbarui').').');
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
