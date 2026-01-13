# ========================================
# PLANNING MONITORING APP - SETUP GUIDE
# ========================================

## 📋 QUICK START

### Local Development
```bash
# 1. Clone repository
git clone <your-repo-url>
cd planning-monitoring

# 2. Copy .env
cp .env.example .env

# 3. Start development
./scripts/dev.sh

# 4. Access
http://localhost:8082
```

---

## 🚀 PRODUCTION DEPLOYMENT

### Prerequisites di Server Ubuntu
```bash
# 1. Install Docker & Docker Compose
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER

# 2. Install Git
sudo apt install git -y

# 3. Clone repository
git clone <your-repo-url> /var/www/planning-monitoring
cd /var/www/planning-monitoring
```

### Setup Production
```bash
# 1. Copy dan edit .env production
cp .env.production.example .env
nano .env

# PENTING: Isi semua nilai berikut:
# - APP_KEY (generate dengan: php artisan key:generate)
# - APP_URL (domain Anda)
# - DB_PASSWORD (password aman!)
# - MYSQL_ROOT_PASSWORD (password aman!)

# 2. Edit Caddyfile.production
nano Caddyfile.production
# Ganti 'yourdomain.com' dengan domain Anda dan email untuk SSL

# 3. Deploy!
./scripts/prod.sh
```

### Setup Firewall
```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow 22/tcp   # SSH
sudo ufw allow 80/tcp   # HTTP
sudo ufw allow 443/tcp  # HTTPS
sudo ufw enable
```

### Setup Automatic Backup
```bash
# Edit crontab
crontab -e

# Tambahkan line ini (backup setiap jam 2 pagi):
0 2 * * * /var/www/planning-monitoring/scripts/backup.sh >> /var/log/backup.log 2>&1

# Buat folder backup
sudo mkdir -p /backups/planning-monitoring
sudo chown $USER:$USER /backups/planning-monitoring
```

---

## 📂 FILE STRUCTURE

```
planning-monitoring/
├── .env                        # Environment variables (gitignored)
├── .env.example                # Template untuk development
├── .env.production.example     # Template untuk production
├── Caddyfile                   # Config untuk local dev
├── Caddyfile.production        # Config untuk production (SSL)
├── compose.yaml                # Docker Compose base
├── compose.production.yaml     # Production overrides
├── Dockerfile                  # Custom FrankenPHP image
└── scripts/
    ├── dev.sh                  # Start local development
    ├── prod.sh                 # Deploy to production
    └── backup.sh               # Automatic backup script
```

---

## 🔧 USEFUL COMMANDS

### Development
```bash
# Start
./scripts/dev.sh

# Logs
./vendor/bin/sail logs -f

# Shell
./vendor/bin/sail shell

# Artisan
./vendor/bin/sail artisan migrate

# NPM (hot reload)
npm run dev

# Stop
./vendor/bin/sail down
```

### Production
```bash
# Deploy
./scripts/prod.sh

# Logs
docker compose -f compose.yaml -f compose.production.yaml logs -f

# Shell
docker compose exec laravel.test bash

# Artisan
docker compose exec laravel.test php artisan [command]

# Stop
docker compose -f compose.yaml -f compose.production.yaml down

# Backup
./scripts/backup.sh
```

---

## 🔐 SECURITY CHECKLIST

- [ ] `.env` tidak ada di git (sudah di `.gitignore`)
- [ ] `APP_ENV=production` dan `APP_DEBUG=false`
- [ ] Password database yang kuat (min. 20 karakter random)
- [ ] Domain sudah terkonfigurasi di DNS
- [ ] SSL certificate auto-generated (via Caddy Let's Encrypt)
- [ ] Firewall aktif (UFW)
- [ ] Database port TIDAK exposed ke public
- [ ] Backup otomatis sudah setup (cron job)
- [ ] Monitoring/alerting sudah setup (optional: Uptime Kuma, Sentry)

---

## 🆘 TROUBLESHOOTING

### Container unhealthy
```bash
docker compose logs laravel.test
docker compose exec laravel.test curl -I http://localhost/
```

### Permission denied
```bash
docker compose exec -u root laravel.test chown -R sail:sail /app/storage /app/bootstrap/cache
```

### SSL certificate error
```bash
# Check Caddy logs
docker compose logs laravel.test | grep -i ssl

# Pastikan domain sudah pointing ke IP server
# Pastikan port 80 & 443 terbuka di firewall
```

### Database connection error
```bash
# Check MySQL status
docker compose exec mysql mysqladmin ping -ppassword

# Check .env credentials
cat .env | grep DB_
```

---

## 📞 SUPPORT

Untuk pertanyaan lebih lanjut, hubungi tim development.

**Selamat deployment! 🚀**
