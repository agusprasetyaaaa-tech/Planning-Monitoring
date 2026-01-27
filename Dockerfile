FROM dunglas/frankenphp:php8.4

ENV SERVER_NAME=":80"
WORKDIR /app

# 1. Install System Dependencies & Database Client
# (NodeJS KITA HAPUS KARENA SUDAH TIDAK DIPERLUKAN LAGI)
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    mariadb-client \
    curl \
    gnupg \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 2. Install PHP Extensions
RUN install-php-extensions \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    opcache \
    intl

# 3. Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. Copy Seluruh Kode Aplikasi (TERMASUK folder public/build yang barusan kita buat)
COPY . /app

# 5. Install Library PHP
RUN composer install --no-dev --optimize-autoloader --no-interaction

# 6. (LANGKAH NPM KITA HAPUS TOTAL AGAR VPS TIDAK BERAT)

# 7. Atur Permission
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

# 8. Jalankan Server
CMD ["frankenphp", "run", "--config", "/app/Caddyfile"]