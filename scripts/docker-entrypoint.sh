#!/bin/bash
# =============================================
# PLANLY APP - DOCKER ENTRYPOINT PRODUCTION
# Script ini akan dijalankan saat container start
# =============================================

set -e

echo "🚀 Starting Planly App Production..."

# Wait for MySQL to be ready
echo "⏳ Waiting for MySQL connection..."
max_tries=30
counter=0
while ! php artisan tinker --execute="DB::connection()->getPdo();" 2>/dev/null; do
    counter=$((counter + 1))
    if [ $counter -gt $max_tries ]; then
        echo "❌ MySQL connection failed after $max_tries attempts"
        exit 1
    fi
    echo "   Attempt $counter/$max_tries - MySQL not ready, waiting..."
    sleep 2
done
echo "✅ MySQL connected!"

# Create storage link if not exists
if [ ! -L "public/storage" ]; then
    echo "📁 Creating storage link..."
    php artisan storage:link
fi

# Run migrations
echo "🔄 Running migrations..."
php artisan migrate --force

# Cache configuration for production
echo "⚡ Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Optimize application
echo "🔧 Optimizing application..."
php artisan optimize

# Set proper permissions
echo "🔐 Setting permissions..."
chmod -R 775 storage bootstrap/cache

echo ""
echo "✅ ================================"
echo "   PLANLY APP READY!"
echo "   URL: http://103.56.148.34:8082"
echo "✅ ================================"
echo ""

# Start FrankenPHP
exec frankenphp run --config /app/Caddyfile
