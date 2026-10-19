<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('username', 60)->unique();
            $t->string('nama', 150);
            $t->string('email')->nullable();
            $t->string('password')->nullable(); // hanya admin lokal darurat; akun Pusat tanpa password
            $t->json('roles');
            $t->boolean('aktif')->default(true);
            $t->string('pusat_uuid', 36)->nullable()->unique();
            $t->string('pegawai_uuid', 36)->nullable()->index(); // penaut ke guru di Pusat (penentu wali kelas)
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('password_reset_tokens', function (Blueprint $t) {
            $t->string('email')->primary();
            $t->string('token');
            $t->timestamp('created_at')->nullable();
        });
        Schema::create('sessions', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->foreignId('user_id')->nullable()->index();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->longText('payload');
            $t->integer('last_activity')->index();
        });

        // Salinan data induk dari Pusat (hanya baca; sumber kebenaran tetap Pusat).
        Schema::create('kelas', function (Blueprint $t) {
            $t->id();
            $t->string('pusat_uuid', 36)->unique();
            $t->string('nama', 50);
            $t->string('tingkat', 5)->nullable();
            $t->string('jurusan', 30)->nullable();
            $t->string('tahun_ajaran', 20)->nullable();
            $t->boolean('tahun_aktif')->default(false);
            $t->string('wali_uuid', 36)->nullable()->index();
            $t->string('wali_nama', 150)->nullable();
            $t->timestamps();
        });
        Schema::create('siswa', function (Blueprint $t) {
            $t->id();
            $t->string('pusat_uuid', 36)->unique();
            $t->string('nis', 30)->index();
            $t->string('nisn', 20)->nullable();
            $t->string('nama', 150)->index();
            $t->string('jk', 1)->nullable();
            $t->foreignId('kelas_id')->nullable()->constrained('kelas')->nullOnDelete();
            $t->string('status', 10)->default('aktif');
            $t->string('tempat_lahir', 100)->nullable();
            $t->string('tanggal_lahir', 10)->nullable();
            $t->string('no_hp_ortu', 30)->nullable();
            $t->string('nama_ortu', 150)->nullable();
            $t->timestamps();
        });

        Schema::create('bk_catatan', function (Blueprint $t) {
            $t->id();
            $t->string('jenis', 20)->index();
            $t->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $t->foreignId('kelas_id')->nullable()->constrained('kelas')->nullOnDelete(); // kelas siswa saat dicatat
            $t->string('tanggal', 10)->index();
            $t->string('kategori', 40)->nullable();
            $t->string('judul', 191);
            $t->text('uraian')->nullable();
            $t->text('tindak_lanjut')->nullable();
            $t->string('status', 10)->nullable();
            $t->boolean('rahasia')->default(false);
            $t->json('extra')->nullable();
            $t->string('berkas_path')->nullable();
            $t->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('pusat_state', function (Blueprint $t) {
            $t->string('kunci', 40)->primary();
            $t->text('nilai')->nullable();
            $t->string('updated_at', 20)->nullable();
        });
        Schema::create('pusat_webhook', function (Blueprint $t) {
            $t->string('delivery_id', 64)->primary(); // kunci idempotensi (X-Pusat-Delivery)
            $t->string('event', 60);
            $t->string('created_at', 20);
        });
        Schema::create('audit_log', function (Blueprint $t) {
            $t->id();
            $t->string('waktu', 20)->index();
            $t->string('aksi', 80);
            $t->string('pelaku', 150)->nullable();
            $t->text('rincian')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['audit_log', 'pusat_webhook', 'pusat_state', 'bk_catatan', 'siswa', 'kelas', 'sessions', 'password_reset_tokens', 'users'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
