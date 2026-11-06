<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Services\ImporCatatan;
use App\Support\BkModules;
use App\Support\Xlsx;
use Illuminate\Http\Request;

/** Impor catatan BK lama dari Excel: unduh templat, isi, "Periksa saja", lalu Impor. Khusus Guru BK & Admin. */
class ImporController extends Controller
{
    public function index()
    {
        return view('impor', ['kolom' => collect(BkModules::all())->map(fn ($m, $j) => ['label' => $m['label'], 'kolom' => ImporCatatan::kolom($j)])]);
    }

    public function templat()
    {
        return Xlsx::download('TEMPLAT_CATATAN_BK_LAMA.xlsx', ImporCatatan::templat());
    }

    public function unggah(Request $request)
    {
        $request->validate(['berkas' => 'required|file|max:5120|mimes:xlsx', 'uji' => 'nullable|boolean'],
            ['berkas.required' => 'Pilih berkas Excel hasil isian templat.', 'berkas.mimes' => 'Berkas harus .xlsx (templat dari halaman ini).']);
        $f = $request->file('berkas');
        $uji = $request->boolean('uji');
        try {
            $h = ImporCatatan::proses($this->me(), $f->getRealPath(), $f->getClientOriginalName(), $uji);
        } catch (UserError $e) {
            $baris = explode("\n", $e->getMessage());

            return back()->with('error', array_shift($baris))->with('galat', $baris);
        }
        $rinci = collect($h['per_jenis'])->map(fn ($n, $j) => $n.' '.mb_strtolower(BkModules::get($j)['label']))->implode(', ');
        $pesan = ($uji ? 'Periksa saja (belum disimpan): ' : 'Impor selesai: ').($rinci ?: 'tidak ada catatan baru')
            .($h['dilewati'] ? '; '.$h['dilewati'].' catatan sudah ada dan dilewati' : '').'.';

        return back()->with($uji ? 'info' : 'success', $pesan);
    }
}
