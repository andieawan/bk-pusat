# BK — Bimbingan Konseling SMKS Islam Bustanul Ulum

Aplikasi mandiri: catatan Pelanggaran, Buku Kasus, Prestasi, Home Visit, surat resmi siap cetak, dan Presensi BK.
Login lewat Pusat Sekolah (SSO); kelas & siswa disalin dari Pusat; kehadiran dibaca/ditulis ke Jurnal Guru lewat API (sambungan diatur di Pusat).

Pemasangan: [docs/PASANG-BK.md](docs/PASANG-BK.md). Laravel 13, PHP 8.3+, MariaDB.

## Impor catatan lama
Menu **Impor** (Guru BK & Admin): unduh templat → isi → *Periksa saja* → *Impor*. Templat berisi satu lembar per jenis
(Pelanggaran, Buku Kasus, Prestasi, Home Visit, Riwayat Surat; satu baris = satu catatan) dan lembar DAFTAR SISWA.
Siswa dicocokkan lewat NIS (atau nama persis). Bila ada satu baris salah, tidak ada yang disimpan dan daftar
kesalahannya ditampilkan; catatan yang sama yang sudah ada dilewati sehingga aman diulang. Kode: `app/Services/ImporCatatan.php`.
