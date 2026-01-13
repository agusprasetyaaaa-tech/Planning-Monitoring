#!/bin/bash

# ========================================
# Script untuk LOCAL DEVELOPMENT
# ========================================

echo "🚀 Starting LOCAL Development Environment..."
echo ""

# Check if .env exists
if [ ! -f .env ]; then
    echo "⚠️  File .env tidak ditemukan!"
    echo "   Copying .env.example to .env..."
    cp .env.example .env
    echo "✅ .env created. Silakan update database credentials jika perlu."
    echo ""
fi

# Start containers
echo "📦 Starting Docker containers..."
./vendor/bin/sail up -d

# Wait for containers to be ready
echo "⏳ Waiting for containers to be ready..."
sleep 5

# Check status
echo ""
echo "📊 Container Status:"
docker ps -a | grep planning-monitoring

echo ""
echo "✅ Development environment is ready!"
echo ""
echo "🌐 Access aplikasi di: http://localhost:8082"
echo "📊 Akses database di: localhost:3309"
echo ""
echo "📝 Useful commands:"
echo "   - Logs: ./vendor/bin/sail logs -f"
echo "   - Shell: ./vendor/bin/sail shell"
echo "   - Artisan: ./vendor/bin/sail artisan [command]"
echo "   - NPM: npm run dev (untuk hot reload)"
echo "   - Stop: ./vendor/bin/sail down"
echo ""
