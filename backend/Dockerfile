# =====================================================================
# DaetSys API — production image untuk Render Web Service (Docker).
#
# Basis php:8.3-cli-alpine + `php artisan serve` yang menempel pada
# 0.0.0.0:$PORT (Render menyuntikkan PORT, default 10000).
# Jangan mengandalkan file .env apa pun di dalam image: seluruh
# konfigurasi dibaca dari Environment Variables Render (dotenv bersifat
# immutable sehingga env asli selalu menang).
# =====================================================================
FROM php:8.3-cli-alpine

# Binari Composer dari image resmi
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- Ekstensi PHP ------------------------------------------------------
# - libpq-dev : prasyarat pdo_pgsql (Supabase PostgreSQL)
# - pdo_pgsql : driver database
# - opcache   : performa produksi (aktif juga untuk CLI server)
# - bcmath    : aritmetika desimal presisi
RUN apk add --no-cache libpq-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql opcache bcmath

# --- Konfigurasi PHP produksi -----------------------------------------
RUN { \
        echo 'memory_limit = 256M'; \
        echo 'max_execution_time = 60'; \
        echo 'post_max_size = 10M'; \
        echo 'upload_max_filesize = 10M'; \
        echo 'opcache.enable = 1'; \
        echo 'opcache.enable_cli = 1'; \
        echo 'opcache.validate_timestamps = 0'; \
        echo 'opcache.memory_consumption = 128'; \
        echo 'opcache.max_accelerated_files = 10000'; \
        echo 'realpath_cache_size = 4096K'; \
        echo 'realpath_cache_ttl = 600'; \
    } > /usr/local/etc/php/conf.d/daetsys-production.ini

WORKDIR /var/www/html

# --- Dependensi Composer terpisah agar cache layer build efektif -------
COPY composer.json composer.lock ./
# --no-scripts: script post-autoload-dump (package:discover) dikerjakan
# setelah seluruh kode aplikasi masuk image.
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

# --- Kode aplikasi (isi .dockerignore menyaring yang tidak perlu) ------
COPY . .

RUN composer dump-autoload --optimize --no-interaction \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache

# Jalankan sebagai non-root; storage/ & bootstrap/cache sudah miliknya
USER www-data

# Pekerja CLI server ganda (artisan serve mendukung env ini).
ENV PHP_CLI_SERVER_WORKERS=4

# Warm-up cache saat boot (env var asli sudah tersedia di container),
# jalankan migrasi + seeder production sebagai jaring pengaman (sumber
# kebenaran seharusnya `preDeployCommand` di render.yaml / dashboard
# — bagi service yang dibuat manual tanpa preDeploy, blok ini membuat
# skema tetap terpasang; migrate & seeder idempotent), lalu serve.
CMD php artisan migrate --force \
    && (php artisan db:seed --class=ProductionDatabaseSeeder --force \
        || echo 'Seeder production dilewati — periksa BOOTSTRAP_ADMIN_* / koneksi DB.') \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
