# Memasang Aplikasi BK

Aplikasi BK berdiri sendiri (1 aplikasi = 1 VM), login lewat **Pusat Sekolah**, dan membaca/menulis kehadiran ke **Absensi** lewat API.

## 1. VM baru
```bash
curl -fsSL https://raw.githubusercontent.com/andieawan/bk-pusat/main/deploy/install.sh -o install.sh
sudo DOMAIN=bk.smkibupakusari.sch.id APP_URL=https://bk.smkibupakusari.sch.id PROXY_IP=192.168.10.10 bash install.sh
```
Lalu di NPMplus buat Proxy Host `bk.smkibupakusari.sch.id` → IP VM, port 80, SSL Let's Encrypt, Force SSL (sama seperti Pusat/Absensi), dan DNS Cloudflare.

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

## 3. Kunci API Absensi
Absensi → **Admin → Integrasi API → Buat kunci** dengan izin `kehadiran:baca` dan `kehadiran:tulis`. Isi di `.env` BK:
```
ABSENSI_URL=https://absensi.smkibupakusari.sch.id
ABSENSI_API_KEY=...
```

## 4. Aktifkan
```bash
cd /var/www/bk-pusat
sudo nano .env                       # isi PUSAT_* dan ABSENSI_*
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan bk:periksa
sudo -u www-data php artisan bk:sinkron   # salin kelas & siswa dari Pusat
```
Admin darurat (login password, bila SSO bermasalah): `sudo -u www-data php artisan bk:buat-admin namaadmin`. Matikan dengan `BK_LOGIN_LOKAL=false` bila tak diperlukan.

## 5. Mematikan menu BK lama di Absensi
Setelah BK berjalan, isi `BK_URL=https://bk.smkibupakusari.sch.id` di `.env` Absensi lalu `config:clear`. Menu BK dan surat di Absensi berubah menjadi tautan ke aplikasi BK. Data BK lama tidak dipindahkan (mulai kosong).

## Pembaruan & cadangan
`sudo bash deploy/update.sh` (dari folder aplikasi) dan `deploy/backup.sh` (jadwalkan di cron).
