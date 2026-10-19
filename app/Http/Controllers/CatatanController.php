<?php

namespace App\Http\Controllers;

use App\Models\BkCatatan;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Services\BkService;
use App\Support\BkModules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Pelanggaran, Buku Kasus, Prestasi, Home Visit, Riwayat Surat. */
class CatatanController extends Controller
{
    private function modul(string $jenis): array
    {
        return BkModules::get($jenis) ?? abort(404);
    }

    /** Kelas (tahun ajaran aktif) yang siswanya boleh dipilih pada form tambah. */
    private function kelasTulis(string $jenis)
    {
        $u = $this->me();
        if (BkService::isCounselor($u)) {
            return Kelas::where('tahun_aktif', true)->orderBy('nama')->get();
        }

        return BkService::canWrite($u, $jenis) ? Kelas::whereIn('id', $u->kelasWaliIds())->orderBy('nama')->get() : collect();
    }

    public function index(Request $request, string $jenis)
    {
        $m = $this->modul($jenis);
        $u = $this->me();
        $fk = (int) $request->query('kelas');
        $status = in_array($request->query('status'), BkModules::STATUS, true) ? $request->query('status') : '';
        $q = trim((string) $request->query('q'));
        $catatan = BkService::visible($u, $jenis)->with(['siswa', 'kelas', 'pencatat'])
            ->when($fk, fn ($x) => $x->where('kelas_id', $fk))
            ->when($status, fn ($x) => $x->where('status', $status))
            ->when($request->query('siswa'), fn ($x) => $x->where('siswa_id', (int) $request->query('siswa')))
            ->when($q !== '', function ($x) use ($q) {
                $t = str_replace(['%', '_'], ['\\%', '\\_'], $q);
                $x->where(fn ($w) => $w->where('judul', 'like', "%$t%")
                    ->orWhereIn('siswa_id', Siswa::where('nama', 'like', "%$t%")->orWhere('nis', 'like', "%$t%")->pluck('id')));
            })
            ->orderByDesc('tanggal')->orderByDesc('id')->paginate(25)->withQueryString();
        $tulis = $this->kelasTulis($jenis);

        return view('catatan.index', [
            'jenis' => $jenis, 'm' => $m, 'catatan' => $catatan, 'fk' => $fk, 'status' => $status, 'q' => $q,
            'kelasFilter' => BkService::seesAll($u) ? Kelas::orderByDesc('tahun_aktif')->orderBy('nama')->get() : Kelas::whereIn('id', $u->kelasWaliIds() ?: [-1])->get(),
            'kelasTulis' => $tulis,
            'pilihSiswa' => $tulis->isEmpty() ? collect() : Siswa::aktif()->whereIn('kelas_id', $tulis->pluck('id'))->orderBy('nama')->get()->groupBy('kelas_id'),
            'hariIni' => \App\Support\Dates::today(), 'preselect' => (int) $request->query('siswa'),
        ]);
    }

    public function store(Request $request, string $jenis)
    {
        $this->modul($jenis);
        $request->validate(['berkas' => 'nullable|file|max:8192|mimes:jpg,jpeg,png,webp,heic,pdf']);
        BkService::save($this->me(), $jenis, null, $request->all(), $request->file('berkas'));

        return back()->with('success', BkModules::get($jenis)['label'].' berhasil dicatat.');
    }

    public function update(Request $request, string $jenis, BkCatatan $catatan)
    {
        abort_unless($catatan->jenis === $jenis, 404);
        $request->validate(['berkas' => 'nullable|file|max:8192|mimes:jpg,jpeg,png,webp,heic,pdf']);
        BkService::save($this->me(), $jenis, $catatan, $request->all(), $request->file('berkas'));

        return back()->with('success', 'Catatan diperbarui.');
    }

    public function destroy(string $jenis, BkCatatan $catatan)
    {
        abort_unless($catatan->jenis === $jenis, 404);
        BkService::delete($this->me(), $catatan);

        return back()->with('success', 'Catatan dihapus.');
    }

    public function berkas(string $jenis, BkCatatan $catatan)
    {
        $u = $this->me();
        abort_unless($catatan->jenis === $jenis && $catatan->berkas_path, 404);
        abort_unless(BkService::visible($u)->whereKey($catatan->id)->exists() && ! BkService::isMasked($u, $catatan), 403);

        return Storage::disk('local')->response($catatan->berkas_path);
    }
}
