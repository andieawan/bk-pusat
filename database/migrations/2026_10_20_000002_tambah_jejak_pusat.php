<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak versi dari Pusat: "pusat_diperbarui" (UTC) agar kiriman webhook lama yang datang terlambat
 * tidak menimpa data yang lebih baru, dan "dihapus_pada" untuk kelas yang dihapus di Pusat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kelas', function (Blueprint $t) {
            $t->dateTime('pusat_diperbarui')->nullable();
            $t->dateTime('dihapus_pada')->nullable();
        });
        Schema::table('siswa', function (Blueprint $t) {
            $t->dateTime('pusat_diperbarui')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('kelas', fn (Blueprint $t) => $t->dropColumn(['pusat_diperbarui', 'dihapus_pada']));
        Schema::table('siswa', fn (Blueprint $t) => $t->dropColumn('pusat_diperbarui'));
    }
};
