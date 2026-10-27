<?php

namespace App\Http\Controllers;

use App\Models\Siswa;
use App\Services\PklKlien;
use Illuminate\Http\Request;

/** PKL (dibaca dari aplikasi PKL): siswa PKL yang perlu perhatian BK, dan riwayat PKL satu siswa. Khusus Guru BK/Admin. */
class PklController extends Controller
{
    public function index()
    {
        $galat = null;
        $data = ['data' => [], 'meta' => []];
        try {
            $data = PklKlien::perhatian();
        } catch (\RuntimeException $e) {
            $galat = $e->getMessage();
        }
        $peta = Siswa::whereIn('pusat_uuid', collect($data['data'] ?? [])->pluck('siswa.uuid')->filter())->get()->keyBy('pusat_uuid');

        return view('pkl.index', ['rows' => $data['data'] ?? [], 'meta' => $data['meta'] ?? [], 'galat' => $galat, 'peta' => $peta]);
    }

    public function siswa(Siswa $siswa)
    {
        $galat = null;
        $rows = [];
        try {
            $rows = $siswa->pusat_uuid ? PklKlien::siswa($siswa->pusat_uuid) : [];
        } catch (\RuntimeException $e) {
            $galat = $e->getMessage();
        }

        return view('pkl.siswa', ['s' => $siswa->load('kelas'), 'rows' => $rows, 'galat' => $galat]);
    }
}
