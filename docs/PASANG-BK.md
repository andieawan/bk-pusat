# Memasang Aplikasi BK

Aplikasi BK berdiri sendiri (1 aplikasi = 1 VM), login lewat **Pusat Sekolah**, dan membaca/menulis kehadiran ke **Jurnal Guru** lewat API.

## 1. VM baru
Sama seperti TU dan PKL. Repo private: ambil dengan token GitHub, lalu jalankan installer.
```bash
sudo git config --global credential.helper store
sudo git clone https://github.com/andieawan/bk-pusat.git /var/www/bk-pusat
cd /var/www/bk-pusat
sudo DOMAIN=bk.smkibupakusari.sch.id APP_URL=https://bk.smkibupakusari.sch.id PROXY_IP=192.168.10.10 bash deploy/install.sh
```
Lalu di NPMplus buat Proxy Host `bk.smkibupakusari.sch.id` → IP VM, port 80, SSL Let's Encrypt, Force SSL (sama seperti Pusat/Jurnal), dan DNS Cloudflare.

## 2. Daftarkan di Pusat
Pusat → **Aplikasi → Tambah**:

| Isian | Nilai |
|---|---|
| Redirect URI | `https://bk.smkibupakusari.sch.id/sso/callback` |
| Webhook URL | `https://bk.smkibupakusari.sch.id/api/pusat/webhook` |
| Scope | `siswa`, `kelas`, `tahun_ajaran`, `akun` |
| Peran | `bk`, `kepsek`, `admin`, `guru` |

Salin `client_id`, `client_secret`, `webhook_secret` ke `.env` BK:
```
PUSAT_ENABLED=true
PUSAT_URL=https://pusat.smkibupakusari.sch.id
PUSAT_CLIENT_ID=...
PUSAT_CLIENT_SECRET=...
PUSAT_WEBHOOK_SECRET=...
```
Di Pusat, beri guru BK peran **bk** untuk aplikasi BK, Kepala Sekolah **kepsek**. Wali kelas tidak perlu peran khusus: otomatis dari data kelas di Pusat (wali kelas yang sama), cukup beri peran **guru**.

## 3. Sambungan ke Jurnal Guru
Tidak perlu kunci API manual. Di Pusat → **Administrasi → Sambungan**, klik **Sambungkan** pada "Jurnal Guru → BK"
(Jurnal dan BK harus sudah terdaftar). Pusat membuat kuncinya dan mengirim alamat + kunci ke kedua aplikasi;
`ABSENSI_URL` / `ABSENSI_API_KEY` di `.env` BK dibiarkan kosong.

## 4. Aktifkan
```bash
cd /var/www/bk-pusat
sudo nano .env                       # isi PUSAT_*
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan bk:periksa
sudo -u www-data php artisan bk:sinkron   # salin kelas & siswa, dan sambungan ke Jurnal, dari Pusat
```
Admin darurat (login password, bila SSO bermasalah): `sudo -u www-data php artisan bk:buat-admin namaadmin`. Matikan dengan `BK_LOGIN_LOKAL=false` bila tak diperlukan.

## Pembaruan & cadangan
`sudo bash deploy/update.sh` (dari folder aplikasi) dan `deploy/backup.sh` (jadwalkan di cron).
