<?php

namespace App\Http\Controllers;

use App\Models\Siswa;
use App\Services\Audit;
use App\Support\LetterForms;
use Illuminate\Http\Request;

/** Surat resmi siap cetak (Ctrl+P → Simpan sebagai PDF). */
class SuratController extends Controller
{
    public function form(Request $request, string $jenis, Siswa $siswa)
    {
        $u = $this->me();
        abort_unless($u->isCounselor() || $u->hasRole('kepsek') || in_array($siswa->kelas_id, $u->kelasWaliIds(), true), 403, 'Surat hanya untuk Wali Kelas siswa, Guru BK, atau Kepala Sekolah.');
        $judul = LetterForms::TITLES[$jenis] ?? abort(404);
        Audit::catat('Cetak '.$judul, $u->nama, "{$siswa->nama} ({$siswa->nis})");
        $siswa->loadMissing('kelas');
        $fields = LetterForms::fields($jenis, $siswa, $u, $request);
        $v = collect($fields)->mapWithKeys(fn ($f) => [$f['k'] => $f['v']])->all();

        return view('surat.form', [
            'jenis' => $jenis, 'title' => $judul, 'siswa' => $siswa, 'fields' => $fields, 'v' => $v,
            'now' => LetterForms::dateParts(), 'cfg' => config('bk.surat'),
        ]);
    }
}
