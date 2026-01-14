# 🚀 Planly App - Production Deployment Guide

## 📋 Deployment via Portainer Git Repository

Panduan ini menjelaskan cara deploy Planly App ke VPS menggunakan Portainer dengan Git Repository method.

---

## 📌 Informasi Server

| Item | Value |
|------|-------|
| **VPS IP** | `103.56.148.34:8082` |
| **OS** | Ubuntu |
| **Container Manager** | Portainer.io |
| **Web Server** | FrankenPHP + Caddy |
| **Database** | MySQL 8.4 |

---

## 🔧 Langkah-langkah Deployment

### Step 1: Persiapan Repository GitHub

1. **Push semua file production ke GitHub:**
   ```bash
   git add .
   git commit -m "Add production configuration files"
   git push origin main
   ```

2. **Pastikan file-file berikut sudah ada di repository:**
   - ✅ `compose.production.yaml`
   - ✅ `Dockerfile.production`
   - ✅ `Caddyfile.production`
   - ✅ `.env.production.example`
   - ✅ `vite.config.js` (updated)
   - ✅ `scripts/docker-entrypoint.sh`

---

### Step 2: Buat Personal Access Token (PAT) di GitHub

1. Buka [GitHub Settings → Developer settings → Personal access tokens → Tokens (classic)](https://github.com/settings/tokens)
2. Klik **"Generate new token (classic)"**
3. Berikan nama token: `Portainer-Planly-Deploy`
4. Pilih scopes:
   - ✅ `repo` (Full control of private repositories)
5. Klik **"Generate token"**
6. **SIMPAN TOKEN INI!** Token hanya ditampilkan sekali.

---

### Step 3: Deploy di Portainer

1. **Login ke Portainer:**
   ```
   http://103.56.148.34:9000
   ```

2. **Navigasi ke Stacks:**
   - Klik **"local"** → **"Stacks"** → **"Add stack"**

3. **Isi Konfigurasi Stack:**

   | Field | Value |
   |-------|-------|
   | **Name** | `planly-app` |
   | **Build method** | ✅ Repository |
   | **Authentication** | ✅ ON |
   | **Username** | `<GitHub Username Anda>` |
   | **Personal Access Token** | `<PAT dari Step 2>` |
   | **Repository URL** | `https://github.com/<username>/planning-monitoring` |
   | **Repository reference** | `refs/heads/main` |
   | **Compose path** | `compose.production.yaml` |

4. **Environment Variables:**
   
   Klik **"+ Add an environment variable"** untuk setiap variabel berikut:

   | Name | Value |
   |------|-------|
   | `DB_PASSWORD` | `PlanlySecure2026!` |
   | `MYSQL_ROOT_PASSWORD` | `PlanlyRoot2026!` |
   | `APP_KEY` | *(akan di-generate nanti)* |

   > ⚠️ **PENTING:** Ganti password dengan yang lebih aman untuk production!

5. **Klik "Deploy the stack"**

---

### Step 4: Generate APP_KEY

Setelah container berjalan:

1. **Akses container via Portainer:**
   - Pergi ke **"Containers"**
   - Klik **"planly-app"**
   - Klik tab **"Console"**
   - Pilih **"Command"**: `/bin/bash`
   - Klik **"Connect"**

2. **Generate APP_KEY:**
   ```bash
   php artisan key:generate --show
   ```

3. **Copy key yang di-generate** (format: `base64:xxxxx...`)

4. **Update Environment Variable:**
   - Kembali ke **"Stacks"** → **"planly-app"**
   - Klik **"Editor"**
   - Tambahkan di bagian environment:
     ```yaml
     APP_KEY: "base64:xxxxx..."
     ```
   - Atau tambahkan via **"Environment variables"** section
   - Klik **"Update the stack"**

---

### Step 5: Jalankan Migrasi Database

1. **Akses container console:**
   ```bash
   # Di console container
   php artisan migrate --force
   ```

2. **Seed data jika diperlukan:**
   ```bash
   php artisan db:seed --force
   ```

3. **Optimize Laravel:**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan optimize
   ```

---

### Step 6: Verifikasi Deployment

1. **Akses aplikasi:**
   ```
   http://103.56.148.34:8082
   ```

2. **Cek status container:**
   - Di Portainer: **"Containers"** → pastikan status **"running"** dan **"healthy"**

3. **Cek logs jika ada error:**
   - Di Portainer: **"Containers"** → **"planly-app"** → **"Logs"**

---

## 📦 Environment Variables di Portainer

Berikut daftar lengkap environment variables yang perlu diset di Portainer:

```
# Required
APP_KEY=base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
DB_PASSWORD=PlanlySecure2026!
MYSQL_ROOT_PASSWORD=PlanlyRoot2026!

# Optional (sudah ada default)
APP_NAME=Planly App
APP_ENV=production
APP_DEBUG=false
APP_URL=http://103.56.148.34:8082
DB_DATABASE=planly_production
DB_USERNAME=planly_user
```

---

## 🔄 Update Aplikasi

Untuk update aplikasi setelah push ke GitHub:

1. Di Portainer, buka **"Stacks"** → **"planly-app"**
2. Klik **"Pull and redeploy"** (icon refresh)
3. Centang **"Re-pull image"** dan **"Force redeployment"**
4. Klik **"Update"**

Atau dengan GitOps updates (automatis):
1. Di stack, enable **"GitOps updates"**
2. Set interval polling (misalnya: 5 menit)

---

## 🔒 Keamanan Production

### Firewall (UFW)
```bash
# Di VPS Ubuntu
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 8082/tcp  # Planly App
sudo ufw allow 9000/tcp  # Portainer (pertimbangkan untuk restrict IP)
sudo ufw enable
```

### Ganti Password Default
Pastikan mengganti semua password default:
- `DB_PASSWORD`
- `MYSQL_ROOT_PASSWORD`
- Password user di aplikasi

---

## 🛠️ Troubleshooting

### Container tidak start atau restart terus
```bash
# Cek logs
docker logs planly-app

# Cek MySQL logs
docker logs planly-mysql
```

### Database connection error
```bash
# Pastikan MySQL sudah ready
docker exec planly-mysql mysqladmin ping -h localhost -u root -pPlanlyRoot2026!

# Cek dari app container
docker exec planly-app php artisan tinker
>>> DB::connection()->getPdo();
```

### Assets tidak load (CSS/JS 404)
```bash
# Rebuild assets di container
docker exec planly-app npm run build

# Atau rebuild seluruh container
# Di Portainer: Pull and redeploy dengan "Re-pull image" checked
```

### Permission denied errors
```bash
docker exec -u root planly-app chown -R planly:planly /app/storage /app/bootstrap/cache
docker exec -u root planly-app chmod -R 775 /app/storage /app/bootstrap/cache
```

---

## 📁 Struktur File Production

```
planning-monitoring/
├── compose.production.yaml      # Docker Compose untuk production
├── Dockerfile.production        # Dockerfile optimized untuk production
├── Caddyfile.production        # Caddy config untuk IP (tanpa HTTPS)
├── .env.production.example     # Template environment production
├── vite.config.js              # Vite config (support dev & prod)
└── scripts/
    └── docker-entrypoint.sh    # Entrypoint script
```

---

## 📞 Support

Jika mengalami masalah:
1. Cek container logs di Portainer
2. Cek network connectivity antar container
3. Pastikan environment variables sudah benar
4. Verify GitHub repository dapat diakses dengan PAT

---

**Selamat! Planly App Anda sudah di-deploy ke production! 🎉**
