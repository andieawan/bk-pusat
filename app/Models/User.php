<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    private ?array $waliCache = null;

    protected function casts(): array
    {
        return ['roles' => 'array', 'aktif' => 'boolean', 'password' => 'hashed'];
    }

    public function hasRole(string ...$r): bool
    {
        return (bool) array_intersect($r, $this->roles ?? []);
    }

    /** Petugas BK penuh: Guru BK atau Admin (di Pusat, superadmin otomatis berperan admin). */
    public function isCounselor(): bool
    {
        return $this->hasRole('bk', 'admin');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /** @return int[] id kelas yang diwalikan pengguna ini (menurut data kelas dari Pusat). */
    public function kelasWaliIds(): array
    {
        if ($this->waliCache === null) {
            $this->waliCache = $this->pegawai_uuid ? Kelas::where('wali_uuid', $this->pegawai_uuid)->pluck('id')->all() : [];
        }

        return $this->waliCache;
    }

    public function roleLabel(): string
    {
        $m = ['admin' => 'Admin', 'bk' => 'Guru BK', 'kepsek' => 'Kepala Sekolah', 'guru' => $this->kelasWaliIds() ? 'Wali Kelas' : 'Guru'];

        return implode(', ', array_map(fn ($r) => $m[$r] ?? $r, $this->roles ?? []));
    }
}
