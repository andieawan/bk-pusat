<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\BkCatatan;
use App\Models\Siswa;
use App\Models\User;
use App\Support\BkModules;
use App\Support\Dates;
use App\Support\Xlsx;
use Illuminate\Support\Facades\DB;

/**
 * Impor catatan BK lama (buku/Excel) dari templat: satu lembar per jenis catatan
 * (Pelanggaran, Buku Kasus, Prestasi, Home Visit, Riwayat Surat), satu baris = satu catatan.
 * Semua baris diperiksa dulu; bila ada satu kesalahan, tidak ada yang disimpan (daftar kesalahan ditampilkan).
 * Catatan yang sama persis (jenis + siswa + tanggal + judul) yang sudah ada dilewati, jadi aman diulang.
 */
class ImporCatatan
{
    /** Kolom lembar satu jenis: kunci => judul kolom. */
    public static function kolom(string $jenis): array
    {
        $m = BkModules::get($jenis) ?? throw new UserError('Jenis catatan tidak dikenal.');
        $k = ['tanggal' => 'Tanggal', 'nis' => 'NIS', 'nama' => 'Nama Siswa'];
        if ($m['kategori']) {
            $k['kategori'] = $m['kategori'][0];
        }
        $k['judul'] = $m['judul'][0];
        foreach ($m['extra'] as $key => [$label]) {
            $k['x_'.$key] = $label;
        }
        $k['uraian'] = $m['uraian'];
        if ($m['tindak']) {
            $k['tindak_lanjut'] = $m['tindak'];
        }
        if ($m['status']) {
            $k['status'] = 'Status';
        }
        if ($m['rahasia']) {
            $k['rahasia'] = 'Sangat Rahasia';
        }

        return $k;
    }

    public static function templat(): array
    {
        $petunjuk = [['PETUNJUK IMPOR CATATAN BK LAMA'], [''],
            ['1. Satu lembar per jenis catatan. Isi mulai baris ke-2; jangan ubah judul kolom dan nama lembar. Lembar yang tidak dipakai biarkan kosong.'],
            ['2. Tanggal: YYYY-MM-DD atau DD/MM/YYYY (tidak boleh di masa depan). NIS dipakai untuk mencocokkan siswa; bila NIS kosong, Nama Siswa harus sama persis dengan data Pusat.'],
            ['3. Pilihan yang tersedia:'],
        ];
        foreach (BkModules::all() as $m) {
            if ($m['kategori']) {
                $petunjuk[] = ['   • '.$m['label'].' — '.$m['kategori'][0].': '.implode(', ', $m['kategori'][1])];
            }
            foreach ($m['extra'] as [$label, $type, $opt]) {
                if ($type === 'select') {
                    $petunjuk[] = ['   • '.$m['label'].' — '.$label.': '.implode(', ', $opt).' (boleh kosong)'];
                }
            }
        }
        $petunjuk[] = ['4. Status: Proses atau Selesai (kosong = Selesai, karena catatan lama). Sangat Rahasia (Buku Kasus): Ya atau kosong.'];
        $petunjuk[] = ['5. Kelas siswa diambil dari data saat ini. Scan surat tidak ikut diimpor; unggah lewat menu Riwayat Surat bila perlu.'];
        $petunjuk[] = ['6. Bila ada satu baris salah, tidak ada yang disimpan dan kesalahannya ditampilkan. Pakai "Periksa saja" dulu.'];
        $petunjuk[] = ['7. Lembar DAFTAR SISWA berisi NIS dan nama siswa aktif untuk disalin.'];

        $sheets = ['PETUNJUK' => ['rows' => $petunjuk, 'tebal' => [0]]];
        foreach (BkModules::all() as $j => $m) {
            $sheets[strtoupper($m['label'])] = [array_values(self::kolom($j))];
        }
        $daftar = [['Kelas', 'NIS', 'Nama Siswa']];
        foreach (Siswa::aktif()->with('kelas')->get()->sortBy(fn ($s) => [$s->kelas?->nama, $s->nama]) as $s) {
            $daftar[] = [(string) $s->kelas?->nama, (string) $s->nis, $s->nama];
        }
        $sheets['DAFTAR SISWA'] = $daftar;

        return $sheets;
    }

    /** @return array{per_jenis:array<string,int>, dilewati:int} */
    public static function proses(User $u, string $path, string $nama, bool $uji): array
    {
        $lembar = Xlsx::readAllSheets($path, $nama);
        $namaLembar = self::namaLembar($path, $nama);
        $label = [];
        foreach (BkModules::all() as $j => $m) {
            $label[mb_strtolower($m['label'])] = $j;
        }
        $siswa = Siswa::get(['id', 'nis', 'nama', 'kelas_id']);
        $perNis = $siswa->groupBy(fn ($s) => mb_strtolower(trim((string) $s->nis)));
        $perNama = $siswa->groupBy(fn ($s) => mb_strtolower(preg_replace('/\s+/u', ' ', trim($s->nama))));
        $galat = [];
        $antre = []; // [jenis, input]
        $dilewati = 0;
        $kunci = [];

        foreach ($lembar as $i => $rows) {
            $jenis = $label[mb_strtolower(trim($namaLembar[$i] ?? ''))] ?? null;
            if (! $jenis || ! $rows) {
                continue;
            }
            $m = BkModules::get($jenis);
            $hdr = array_map(fn ($v) => mb_strtolower(trim((string) $v)), array_shift($rows));
            $kol = [];
            foreach (self::kolom($jenis) as $k => $judul) {
                $kol[$k] = array_search(mb_strtolower($judul), $hdr, true);
            }
            foreach (['tanggal', 'judul'] as $wajib) {
                if ($kol[$wajib] === false) {
                    throw new UserError("Lembar {$m['label']}: kolom \"".self::kolom($jenis)[$wajib].'" tidak ditemukan. Pakai templat dari halaman ini.');
                }
            }
            $ambil = fn (array $r, string $k) => ($kol[$k] ?? false) === false ? '' : trim((string) ($r[$kol[$k]] ?? ''));
            foreach ($rows as $n => $r) {
                if (trim(implode('', array_map('strval', $r))) === '') {
                    continue;
                }
                $baris = "{$m['label']} baris ".($n + 2);
                $tgl = self::tanggal($ambil($r, 'tanggal'));
                $nis = $ambil($r, 'nis');
                $nm = $ambil($r, 'nama');
                $cocok = $nis !== '' ? ($perNis[mb_strtolower($nis)] ?? collect()) : ($nm !== '' ? ($perNama[mb_strtolower(preg_replace('/\s+/u', ' ', $nm))] ?? collect()) : collect());
                $in = ['tanggal' => $tgl, 'judul' => $ambil($r, 'judul'), 'uraian' => $ambil($r, 'uraian'), 'tindak_lanjut' => $ambil($r, 'tindak_lanjut'), 'extra' => []];
                $err = [];
                if (! $tgl) {
                    $err[] = 'tanggal "'.$ambil($r, 'tanggal').'" tidak valid';
                } elseif (Dates::isFuture($tgl)) {
                    $err[] = "tanggal $tgl di masa depan";
                }
                if ($cocok->count() !== 1) {
                    $err[] = $cocok->isEmpty() ? ($nis !== '' ? "NIS \"$nis\" tidak ditemukan" : ($nm !== '' ? "siswa \"$nm\" tidak ditemukan" : 'NIS dan nama siswa kosong')) : "nama \"$nm\" dimiliki lebih dari satu siswa — isi NIS";
                }
                if ($in['judul'] === '') {
                    $err[] = $m['judul'][0].' kosong';
                }
                if ($m['kategori']) {
                    $v = $ambil($r, 'kategori');
                    $in['kategori'] = self::pilih($v, $m['kategori'][1]);
                    if (! $in['kategori']) {
                        $err[] = $m['kategori'][0]." \"$v\" tidak ada (pilihan: ".implode(', ', $m['kategori'][1]).')';
                    }
                }
                foreach ($m['extra'] as $key => [$lbl, $type, $opt]) {
                    $v = $ambil($r, 'x_'.$key);
                    if ($type === 'select' && $v !== '' && ! self::pilih($v, $opt)) {
                        $err[] = "$lbl \"$v\" tidak ada dalam pilihan";
                    }
                    $in['extra'][$key] = $type === 'select' ? (self::pilih($v, $opt) ?? '') : $v;
                }
                if ($m['status']) {
                    $v = $ambil($r, 'status');
                    $in['status'] = $v === '' ? 'Selesai' : self::pilih($v, BkModules::STATUS);
                    if (! $in['status']) {
                        $err[] = "status \"$v\" harus Proses atau Selesai";
                    }
                }
                if ($m['rahasia']) {
                    $in['rahasia'] = in_array(mb_strtolower($ambil($r, 'rahasia')), ['ya', 'y', '1', 'rahasia', 'sangat rahasia'], true);
                }
                if ($err) {
                    $galat[] = "$baris: ".implode('; ', $err).'.';

                    continue;
                }
                $in['siswa_id'] = $cocok->first()->id;
                $k = $jenis.'|'.$in['siswa_id'].'|'.$tgl.'|'.mb_strtolower($in['judul']);
                if (isset($kunci[$k]) || BkCatatan::where(['jenis' => $jenis, 'siswa_id' => $in['siswa_id'], 'tanggal' => $tgl, 'judul' => mb_substr($in['judul'], 0, 191)])->exists()) {
                    $dilewati++;

                    continue;
                }
                $kunci[$k] = true;
                $antre[] = [$jenis, $in];
            }
        }
        if ($galat) {
            throw new UserError(count($galat).' baris bermasalah — belum ada yang disimpan. Perbaiki lalu unggah lagi.'."\n".implode("\n", $galat));
        }
        if (! $antre && ! $dilewati) {
            throw new UserError('Tidak ada catatan yang terisi. Isi lembar PELANGGARAN, BUKU KASUS, dst. di templat.');
        }
        $per = array_fill_keys(array_keys(BkModules::all()), 0);
        DB::beginTransaction();
        try {
            foreach ($antre as [$jenis, $in]) {
                BkService::save($u, $jenis, null, $in, null, false);
                $per[$jenis]++;
            }
            $uji ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        if (! $uji) {
            Audit::catat('Impor catatan BK lama', $u->nama, array_sum($per).' catatan ditambahkan'.($dilewati ? ", $dilewati sudah ada" : ''));
        }

        return ['per_jenis' => array_filter($per), 'dilewati' => $dilewati];
    }

    private static function pilih(string $v, array $opsi): ?string
    {
        foreach ($opsi as $o) {
            if (mb_strtolower($o) === mb_strtolower(trim($v))) {
                return $o;
            }
        }

        return null;
    }

    /** YYYY-MM-DD, DD/MM/YYYY, atau nomor seri tanggal Excel → YYYY-MM-DD. */
    private static function tanggal(string $v): ?string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $v)) {
            $v = substr($v, 0, 10);
        } elseif (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})#', $v, $m)) {
            $v = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        } elseif (preg_match('/^\d{5}(\.\d+)?$/', $v) && (int) $v > 30000 && (int) $v < 80000) {
            $v = gmdate('Y-m-d', ((int) $v - 25569) * 86400);
        }

        return Dates::valid($v) ? $v : null;
    }

    /** Nama lembar berkas .xlsx sesuai urutan readAllSheets (berkas .csv = satu lembar tanpa nama). */
    private static function namaLembar(string $path, string $nama): array
    {
        if (strtolower(pathinfo($nama, PATHINFO_EXTENSION)) !== 'xlsx') {
            return [];
        }
        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            return [];
        }
        $xml = (string) $zip->getFromName('xl/workbook.xml');
        $zip->close();
        preg_match_all('/<sheet\b[^>]*\bname="([^"]*)"/', $xml, $m);

        return array_map(fn ($n) => html_entity_decode($n, ENT_QUOTES | ENT_XML1, 'UTF-8'), $m[1]);
    }
}
