<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\BkCatatan;
use App\Models\Siswa;
use App\Models\User;
use App\Support\BkModules;
use App\Support\Dates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Hak akses & penyimpanan catatan BK.
 * Guru BK dan Admin: penuh. Kepala Sekolah: lihat semua (baca saja). Wali Kelas (guru yang menjadi wali di Pusat): kelasnya.
 */
class BkService
{
    public static function isCounselor(User $u): bool
    {
        return $u->isCounselor();
    }

    public static function seesAll(User $u): bool
    {
        return self::isCounselor($u) || $u->hasRole('kepsek');
    }

    public static function canOpen(User $u): bool
    {
        return self::seesAll($u) || $u->kelasWaliIds() !== [];
    }

    public static function canWrite(User $u, string $jenis, ?int $kelasId = null): bool
    {
        if (self::isCounselor($u)) {
            return true;
        }
        $m = BkModules::get($jenis);

        return $m && $m['wali_tulis'] && $u->kelasWaliIds() !== [] && ($kelasId === null || in_array($kelasId, $u->kelasWaliIds(), true));
    }

    public static function canEdit(User $u, BkCatatan $r): bool
    {
        return self::isCounselor($u) || (self::canWrite($u, $r->jenis, $r->kelas_id) && $r->dicatat_oleh === $u->id);
    }

    public static function visible(User $u, ?string $jenis = null): Builder
    {
        return BkCatatan::query()
            ->when($jenis, fn ($q) => $q->where('jenis', $jenis))
            ->when(! self::seesAll($u), fn ($q) => $q->whereIn('kelas_id', $u->kelasWaliIds() ?: [-1]));
    }

    /** Detail kasus "Sangat Rahasia" disembunyikan dari selain BK. */
    public static function isMasked(User $u, BkCatatan $r): bool
    {
        return $r->rahasia && ! self::isCounselor($u);
    }

    public static function save(User $u, string $jenis, ?BkCatatan $r, array $in, ?UploadedFile $file = null): BkCatatan
    {
        $m = BkModules::get($jenis) ?? throw new UserError('Jenis catatan tidak dikenal.');
        $siswa = $r ? Siswa::find($r->siswa_id) : Siswa::find((int) ($in['siswa_id'] ?? 0)); // siswa tidak diganti saat edit
        if (! $siswa) {
            throw new UserError('Pilih siswa terlebih dahulu.');
        }
        $kelasId = $r ? $r->kelas_id : $siswa->kelas_id;
        if ($r ? ! self::canEdit($u, $r) : ! self::canWrite($u, $jenis, $kelasId)) {
            throw new UserError($jenis === 'prestasi' ? 'Prestasi hanya dapat dicatat oleh Guru BK atau Wali Kelas siswa tersebut.' : 'Catatan ini hanya dapat diisi oleh Guru BK.');
        }
        $tanggal = (string) ($in['tanggal'] ?? '');
        if (! Dates::valid($tanggal) || Dates::isFuture($tanggal)) {
            throw new UserError('Tanggal tidak valid atau berada di masa depan.');
        }
        $judul = trim((string) ($in['judul'] ?? ''));
        if ($judul === '') {
            throw new UserError($m['judul'][0].' wajib diisi.');
        }
        $kategori = null;
        if ($m['kategori']) {
            $kategori = (string) ($in['kategori'] ?? '');
            if (! in_array($kategori, $m['kategori'][1], true)) {
                throw new UserError('Pilih '.strtolower($m['kategori'][0]).' yang tersedia.');
            }
        }
        $extra = [];
        foreach ($m['extra'] as $key => [$label, $type, $opt]) {
            $v = trim((string) ($in['extra'][$key] ?? ''));
            if ($type === 'select' && $v !== '' && ! in_array($v, $opt, true)) {
                throw new UserError("Pilihan $label tidak valid.");
            }
            $extra[$key] = mb_substr($v, 0, 191);
        }
        $data = [
            'jenis' => $jenis, 'siswa_id' => $siswa->id, 'kelas_id' => $kelasId, 'tanggal' => $tanggal, 'kategori' => $kategori,
            'judul' => mb_substr($judul, 0, 191), 'uraian' => trim((string) ($in['uraian'] ?? '')) ?: null,
            'tindak_lanjut' => $m['tindak'] ? (trim((string) ($in['tindak_lanjut'] ?? '')) ?: null) : null,
            'status' => $m['status'] ? (in_array($in['status'] ?? '', BkModules::STATUS, true) ? $in['status'] : 'Proses') : null,
            'rahasia' => $m['rahasia'] && ! empty($in['rahasia']),
            'extra' => $extra,
        ];
        if ($file && $m['berkas']) {
            $ext = strtolower($file->getClientOriginalExtension());
            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'heic', 'pdf'], true)) {
                throw new UserError('Berkas scan harus berupa foto (JPG/PNG/WEBP/HEIC) atau PDF.');
            }
            if ($r?->berkas_path) {
                Storage::disk('local')->delete($r->berkas_path);
            }
            $data['berkas_path'] = $file->storeAs('bk-surat', 'surat_'.$siswa->id.'_'.bin2hex(random_bytes(8)).'.'.$ext, 'local');
        }
        $label = BkModules::get($jenis)['label'].": {$siswa->nama} — ".($data['rahasia'] ? '(rahasia)' : $data['judul']);
        if ($r) {
            $r->update($data);
            Audit::catat('Ubah catatan BK', $u->nama, $label);
        } else {
            $r = BkCatatan::create($data + ['dicatat_oleh' => $u->id]);
            Audit::catat('Tambah catatan BK', $u->nama, $label);
        }

        return $r;
    }

    public static function delete(User $u, BkCatatan $r): void
    {
        if (! self::canEdit($u, $r)) {
            throw new UserError('Anda tidak berwenang menghapus catatan ini.');
        }
        if ($r->berkas_path) {
            Storage::disk('local')->delete($r->berkas_path);
        }
        $r->delete();
        Audit::catat('Hapus catatan BK', $u->nama, BkModules::get($r->jenis)['label']." #{$r->id}");
    }
}
