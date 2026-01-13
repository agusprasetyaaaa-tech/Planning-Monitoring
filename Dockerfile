FROM dunglas/frankenphp:php8.4

ENV SERVER_NAME=":80"
WORKDIR /app

# Create sail user agar sinkron dengan permission Laravel
RUN groupadd --force -g 1000 sail && \
    useradd -ms /bin/bash --no-user-group -g 1000 -u 1337 sail

# Install extensions
RUN install-php-extensions \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    opcache \
    redis \
    intl

# Install Node.js (Latest LTS)
RUN apt-get update && apt-get install -y ca-certificates curl gnupg && \
    mkdir -p /etc/apt/keyrings && \
    curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg && \
    NODE_MAJOR=22 && \
    echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_MAJOR.x nodistro main" | tee /etc/apt/sources.list.d/nodesource.list && \
    apt-get update && \
    apt-get install -y nodejs && \
    npm install -g npm@latest && \
    apt-get clean && rm -rf /var/lib/apt/lists/*


# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy files
COPY . /app

# Set permission (Penting!)
RUN chown -R sail:sail /app && \
    chmod -R 775 /app/storage /app/bootstrap/cache

# Switch to root sebentar untuk install, lalu balik ke sail
USER sail

# Perintah CMD disesuaikan: Jika Octane belum diinstall, kita pakai mode standar dulu
# Agar container tidak langsung mati
CMD ["frankenphp", "php-server"]