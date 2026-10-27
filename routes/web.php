<?php

use App\Http\Controllers\Admin\IntegrasiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BerandaController;
use App\Http\Controllers\CatatanController;
use App\Http\Controllers\PklController;
use App\Http\Controllers\PresensiController;
use App\Http\Controllers\PusatSsoController;
use App\Http\Controllers\PusatWebhookController;
use App\Http\Controllers\SuratController;
use Illuminate\Support\Facades\Route;

// Penerima webhook dari Pusat Sekolah (tanda tangan HMAC; tanpa CSRF — lihat bootstrap/app.php).
Route::post('/api/pusat/webhook', PusatWebhookController::class)->middleware('throttle:120,1');

// Login: utama lewat Pusat (SSO), admin lokal darurat dengan password.
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'form'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});
Route::get('/sso/login', [PusatSsoController::class, 'mulai'])->middleware('throttle:30,1')->name('sso.login');
Route::get('/sso/callback', [PusatSsoController::class, 'callback'])->middleware('throttle:30,1')->name('sso.callback');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth', 'akun.aktif'])->group(function () {
    Route::get('/', [BerandaController::class, 'index'])->name('beranda');

    Route::middleware('bk:buka')->group(function () {
        Route::get('/catatan/{jenis}', [CatatanController::class, 'index'])->name('catatan');
        Route::post('/catatan/{jenis}', [CatatanController::class, 'store'])->name('catatan.simpan');
        Route::put('/catatan/{jenis}/{catatan}', [CatatanController::class, 'update'])->name('catatan.ubah');
        Route::delete('/catatan/{jenis}/{catatan}', [CatatanController::class, 'destroy'])->name('catatan.hapus');
        Route::get('/catatan/{jenis}/{catatan}/berkas', [CatatanController::class, 'berkas'])->name('catatan.berkas');
        Route::get('/surat/{jenis}/{siswa}', [SuratController::class, 'form'])->name('surat');
    });

    Route::middleware('bk:konselor')->group(function () {
        Route::get('/presensi', [PresensiController::class, 'index'])->name('presensi');
        Route::post('/presensi/manual', [PresensiController::class, 'manual'])->name('presensi.manual');
        Route::post('/presensi/delegasi', [PresensiController::class, 'delegasi'])->name('presensi.delegasi');
        Route::post('/presensi/delegasi/cabut', [PresensiController::class, 'cabut'])->name('presensi.cabut');
        Route::get('/pkl', [PklController::class, 'index'])->name('pkl');
        Route::get('/pkl/siswa/{siswa}', [PklController::class, 'siswa'])->whereNumber('siswa')->name('pkl.siswa');
    });

    Route::middleware('bk:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/integrasi', [IntegrasiController::class, 'index'])->name('integrasi');
        Route::post('/integrasi/uji-pusat', [IntegrasiController::class, 'ujiPusat'])->middleware('throttle:10,1')->name('integrasi.pusat');
        Route::post('/integrasi/sinkron', [IntegrasiController::class, 'sinkron'])->middleware('throttle:5,1')->name('integrasi.sinkron');
        Route::post('/integrasi/uji-absensi', [IntegrasiController::class, 'ujiAbsensi'])->middleware('throttle:10,1')->name('integrasi.absensi');
        Route::post('/integrasi/uji-pkl', [IntegrasiController::class, 'ujiPkl'])->middleware('throttle:10,1')->name('integrasi.pkl');
    });
});
