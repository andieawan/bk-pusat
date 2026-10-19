<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Catatan BK (Pelanggaran, Buku Kasus, Prestasi, Home Visit, Riwayat Surat). Definisi tiap jenis: App\Support\BkModules. */
class BkCatatan extends Model
{
    protected $table = 'bk_catatan';
    protected $guarded = [];

    protected function casts(): array
    {
        return ['rahasia' => 'boolean', 'extra' => 'array', 'siswa_id' => 'integer', 'kelas_id' => 'integer', 'dicatat_oleh' => 'integer'];
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }

    public function pencatat()
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }

    public function x(string $key): string
    {
        return (string) ($this->extra[$key] ?? '');
    }
}
