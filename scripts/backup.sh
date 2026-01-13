#!/bin/bash

# ========================================
# Script BACKUP untuk PRODUCTION
# ========================================
# Setup cron job: 0 2 * * * /path/to/scripts/backup.sh
# (Jalan setiap jam 2 pagi)
# ========================================

BACKUP_DIR="/backups/planning-monitoring"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=7  # Simpan backup 7 hari terakhir

# Create backup directory if not exists
mkdir -p $BACKUP_DIR

echo "🔄 Starting backup at $(date)"

# ========================================
# 1. BACKUP DATABASE
# ========================================
echo "📦 Backing up database..."
docker compose exec -T mysql mysqldump \
    -u root \
    -p${MYSQL_ROOT_PASSWORD:-password} \
    --single-transaction \
    --quick \
    --lock-tables=false \
    laravel > "$BACKUP_DIR/db_$DATE.sql"

# Compress database backup
gzip "$BACKUP_DIR/db_$DATE.sql"
echo "✅ Database backup completed: db_$DATE.sql.gz"

# ========================================
# 2. BACKUP FILES (storage & uploads)
# ========================================
echo "📦 Backing up files..."
tar -czf "$BACKUP_DIR/files_$DATE.tar.gz" \
    storage/app/public \
    storage/logs \
    .env \
    2>/dev/null || echo "⚠️  Some files may be skipped"

echo "✅ Files backup completed: files_$DATE.tar.gz"

# ========================================
# 3. CLEANUP OLD BACKUPS
# ========================================
echo "🧹 Cleaning up old backups (older than $RETENTION_DAYS days)..."
find $BACKUP_DIR -name "db_*.sql.gz" -mtime +$RETENTION_DAYS -delete
find $BACKUP_DIR -name "files_*.tar.gz" -mtime +$RETENTION_DAYS -delete

echo "✅ Cleanup completed"

# ========================================
# 4. BACKUP SUMMARY
# ========================================
echo ""
echo "📊 Backup Summary:"
echo "   Location: $BACKUP_DIR"
echo "   Latest backups:"
ls -lh $BACKUP_DIR | tail -5

echo ""
echo "✅ Backup completed at $(date)"
echo ""
