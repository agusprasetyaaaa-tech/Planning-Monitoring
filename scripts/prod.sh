#!/bin/bash

# ========================================
# Script untuk PRODUCTION DEPLOYMENT
# ========================================

set -e  # Exit on error

echo "🚀 PRODUCTION DEPLOYMENT SCRIPT"
echo "================================"
echo ""

# Check if we're in production
read -p "⚠️  Apakah Anda yakin untuk DEPLOY ke PRODUCTION? (yes/no): " confirm
if [ "$confirm" != "yes" ]; then
    echo "❌ Deployment dibatalkan."
    exit 1
fi

# Check if .env exists
if [ ! -f .env ]; then
    echo "⚠️  File .env tidak ditemukan!"
    echo "   Silakan copy .env.production.example ke .env dan isi dengan nilai production."
    exit 1
fi

# Check if production domain is configured
if grep -q "yourdomain.com" Caddyfile.production; then
    echo "⚠️  PERHATIAN: Domain masih 'yourdomain.com' di Caddyfile.production!"
    echo "   Silakan ganti dengan domain production Anda yang sebenarnya."
    read -p "Lanjutkan? (yes/no): " continue
    if [ "$continue" != "yes" ]; then
        exit 1
    fi
fi

echo ""
echo "📦 Step 1: Building assets..."
npm install --production
npm run build

echo ""
echo "📦 Step 2: Pulling latest code..."
git pull origin main || echo "⚠️  Git pull skipped (jika tidak ada repo)"

echo ""
echo "📦 Step 3: Stopping old containers..."
docker compose -f compose.yaml -f compose.production.yaml down

echo ""
echo "📦 Step 4: Building and starting production containers..."
docker compose -f compose.yaml -f compose.production.yaml up -d --build

echo ""
echo "⏳ Step 5: Waiting for containers to be healthy..."
sleep 10

echo ""
echo "📦 Step 6: Running migrations..."
docker compose exec -u root laravel.test php artisan migrate --force

echo ""
echo "📦 Step 7: Clearing and caching config..."
docker compose exec -u root laravel.test php artisan config:clear
docker compose exec -u root laravel.test php artisan cache:clear
docker compose exec -u root laravel.test php artisan view:clear
docker compose exec -u root laravel.test php artisan route:clear

docker compose exec -u root laravel.test php artisan config:cache
docker compose exec -u root laravel.test php artisan route:cache
docker compose exec -u root laravel.test php artisan view:cache

echo ""
echo "📦 Step 8: Optimizing..."
docker compose exec -u root laravel.test php artisan optimize

echo ""
echo "📦 Step 9: Setting proper permissions..."
docker compose exec -u root laravel.test chown -R sail:sail /app/storage /app/bootstrap/cache

echo ""
echo "📊 Container Status:"
docker compose ps

echo ""
echo "🎉 ================================"
echo "✅ PRODUCTION DEPLOYMENT SELESAI!"
echo "🎉 ================================"
echo ""
echo "🌐 Aplikasi dapat diakses di:"
echo "   HTTP: http://your-server-ip"
echo "   HTTPS: https://yourdomain.com (jika domain sudah configured)"
echo ""
echo "📝 Useful commands:"
echo "   - Logs: docker compose -f compose.yaml -f compose.production.yaml logs -f"
echo "   - Status: docker compose -f compose.yaml -f compose.production.yaml ps"
echo "   - Shell: docker compose exec laravel.test bash"
echo "   - Stop: docker compose -f compose.yaml -f compose.production.yaml down"
echo ""
echo "⚠️  PENTING: Jangan lupa setup:"
echo "   1. Firewall (UFW): sudo ufw allow 80/tcp && sudo ufw allow 443/tcp"
echo "   2. DNS: Point domain ke IP server ini"
echo "   3. Backup: Setup cron job untuk backup database & files"
echo ""
