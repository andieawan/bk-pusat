<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KosongkanDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_kosongkan_data(): void
    {
        $admin = User::create(['username' => 'admin', 'nama' => 'Admin', 'roles' => ['admin'], 'aktif' => true, 'pegawai_uuid' => 'p-1']);
        $guru = User::create(['username' => 'guru', 'nama' => 'Guru', 'roles' => ['guru'], 'aktif' => true]);
        $k = Kelas::create(['pusat_uuid' => 'k1', 'nama' => 'X TKJ 1', 'tahun_ajaran' => '2026/2027', 'tahun_aktif' => true]);
        Siswa::create(['pusat_uuid' => 's1', 'nis' => '1001', 'nama' => 'Budi', 'jk' => 'L', 'kelas_id' => $k->id, 'status' => 'aktif']);
        DB::table('pusat_state')->insert(['kunci' => 'uji', 'nilai' => '1']);

        $this->artisan('bk:kosongkan-data')->expectsQuestion('Ketik HAPUS SEMUA untuk melanjutkan', 'batal')->assertExitCode(1);
        $this->assertSame(1, Siswa::count());

        $this->artisan('bk:kosongkan-data', ['--yakin' => true, '--tanpa-cadangan' => true])->assertExitCode(0);
        $this->assertSame(0, Siswa::count());
        $this->assertSame(0, Kelas::count());
        $this->assertSame(0, DB::table('pusat_state')->count());
        $this->assertNull(User::find($guru->id));
        $this->assertNull(User::find($admin->id)->pegawai_uuid);
    }
}
