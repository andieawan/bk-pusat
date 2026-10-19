<?php

// Konfigurasi khusus aplikasi BK (Bimbingan Konseling).
return [
    'school_name' => env('SCHOOL_NAME', 'SMK Islam Bustanul Ulum Pakusari'),
    'timezone' => env('BK_TIMEZONE', 'Asia/Jakarta'),
    'batas_alpa' => (int) env('BK_BATAS_ALPA', 3), // siswa "perlu perhatian" bila alpa >= angka ini dalam rentang rekap

    // Aplikasi Absensi (sumber data kehadiran; BK menulis absensi manual & tautan delegasi lewat API-nya).
    'absensi' => [
        'url' => rtrim((string) env('ABSENSI_URL', ''), '/'),
        'api_key' => env('ABSENSI_API_KEY', ''),
        'timeout' => (int) env('ABSENSI_TIMEOUT', 15),
        'verify_ssl' => filter_var(env('ABSENSI_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN),
        'ca_bundle' => env('ABSENSI_CA_BUNDLE'),
    ],

    // Admin lokal darurat (bk:buat-admin). Login normal lewat Pusat.
    'login_lokal' => (bool) env('BK_LOGIN_LOKAL', true),

    // Identitas pada kop & nomor surat (sama dengan Absensi; ubah lewat .env)
    'surat' => [
        'yayasan' => env('SURAT_YAYASAN', 'YAYASAN PENDIDIKAN ISLAM'),
        'yayasan2' => env('SURAT_YAYASAN2', '“ BUSTANUL ULUM ”'),
        'nama' => env('SURAT_NAMA', 'SMK ISLAM BUSTANUL ULUM PAKUSARI'),
        'nss' => env('SURAT_NSS', '342052423288'),
        'npsn' => env('SURAT_NPSN', '20570966'),
        'bidang' => env('SURAT_BIDANG', 'Kelompok Bisnis Manajemen dan Teknologi Informasi Komunikasi'),
        'alamat' => env('SURAT_ALAMAT', 'Jl. Himalaya No. 17 Telp. (0331) 7255753 Kode Pos. 68181 Pakusari – Jember'),
        'kota' => env('SURAT_KOTA', 'Pakusari'),
        'kepsek' => env('SURAT_KEPSEK', 'MUHAMMAD MUSLIM, S.Pd., Gr'),
        'kepsek_nuptk' => env('SURAT_KEPSEK_NUPTK', '7951767668130072'),
        'nomor_awal' => env('SURAT_NOMOR_AWAL', '400.3.8.1'),
        'nomor_akhir' => env('SURAT_NOMOR_AKHIR', '101.6.20570966'),
    ],
];
