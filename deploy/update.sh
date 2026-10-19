#!/usr/bin/env bash
# Perbarui aplikasi BK ke versi terbaru di GitHub (jalankan sebagai root di VM).
set -euo pipefail
APP_DIR="${APP_DIR:-/var/www/bk-pusat}"
cd "$APP_DIR"
artisan() { runuser -u www-data -- php "$APP_DIR/artisan" "$@"; }

artisan down --retry=30 || true
trap 'artisan up || true' EXIT

# vendor/ ikut di repo; composer bisa mengubah berkas autoload -> kembalikan dulu agar pull tidak bentrok
git ls-files -m -- vendor | xargs -r git checkout --
git pull --ff-only

# Pengerasan & kompresi Nginx (idempotent; aman dijalankan berulang).
if [ -d /etc/nginx/conf.d ]; then
    {
        echo "# Dikelola oleh deploy/update.sh"
        echo "server_tokens off;"
        # Ubuntu mengaktifkan gzip tetapi hanya untuk HTML; tambahkan CSS/JS/SVG bila belum diatur.
        if ! grep -Eqs '^\s*gzip_types' /etc/nginx/nginx.conf; then
            echo "gzip on;"
            echo "gzip_comp_level 5;"
            echo "gzip_min_length 512;"
            echo "gzip_vary on;"
            echo "gzip_types text/css application/javascript text/javascript application/json image/svg+xml;"
        fi
    } > /etc/nginx/conf.d/bk-hardening.conf
    if nginx -t 2>/dev/null; then systemctl reload nginx; else rm -f /etc/nginx/conf.d/bk-hardening.conf; echo "PERINGATAN: konfigurasi hardening Nginx dilewati (nginx -t gagal)."; fi
fi
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction
chown -R www-data:www-data storage bootstrap/cache
artisan migrate --force
artisan config:cache
artisan route:cache
artisan view:cache
PHPV="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
systemctl reload "php${PHPV}-fpm"
artisan bk:periksa || echo "PERHATIAN: ada masalah di atas — perbaiki sebelum dipakai."
echo "Selesai diperbarui: $(git log --oneline -1)"
