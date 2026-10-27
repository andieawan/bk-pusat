<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('siswa', 'alamat')) {
            Schema::table('siswa', fn (Blueprint $t) => $t->string('alamat', 255)->nullable()); // dari Pusat: surat & home visit
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('siswa', 'alamat')) {
            Schema::table('siswa', fn (Blueprint $t) => $t->dropColumn('alamat'));
        }
    }
};
