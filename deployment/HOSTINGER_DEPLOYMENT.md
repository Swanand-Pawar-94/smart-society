# Smart Society - Complete Hostinger Production Deployment Guide

This guide provides end-to-end instructions for deploying the **Smart Society** PHP Laravel Backend and MySQL Database to **Hostinger Web / Cloud Hosting**, and configuring the **Flutter Android Application** for production.

---

## 1. Architecture Overview

```
+------------------------------------+
|  Flutter Android Application (APK) |
+------------------------------------+
                  |
                  | HTTPS Requests (Port 443)
                  v
+------------------------------------+
|   Hostinger Web / Cloud Server     |
|   Subdomain: https://api.YOUR-DOMAIN.com
|   Web Root: /laravel/public        |
|   PHP Version: 8.3+ / 8.4+         |
+------------------------------------+
                  |
                  | Local MySQL Socket (Port 3306)
                  v
+------------------------------------+
|   Hostinger MySQL Database         |
|   Database: u123456_smartsociety   |
|   All 33 Tables Pre-configured     |
+------------------------------------+
```

---

## 2. Prerequisites

1. Active Hostinger Web, Cloud, or VPS Hosting account.
2. Registered domain (e.g. `YOUR-DOMAIN.com`).
3. Access to Hostinger **hPanel**.
4. Local Flutter SDK & Android build environment.

---

## 3. Step-by-Step Hostinger Deployment Procedure

### STEP 1: Add Domain or Create API Subdomain in Hostinger
1. Log in to your **Hostinger hPanel**.
2. Navigate to **Websites** ➔ **Domains / Subdomains**.
3. Create a dedicated subdomain for the API:
   - **Subdomain Name**: `api` (Result: `api.YOUR-DOMAIN.com`).
   - **Custom Folder for Subdomain**: Check the box and set folder to:
     ```
     public_html/api/public
     ```
     *(Pointing the document root directly to Laravel's `public` directory is critical for security so `.env` and source code are never web-accessible).*

---

### STEP 2: Create MySQL Database & User in Hostinger
1. In Hostinger hPanel, go to **Databases** ➔ **MySQL Databases**.
2. Create a new database:
   - **Database Name**: `u123456789_smartsociety` (Hostinger automatically prefixes your account ID).
   - **Username**: `u123456789_societyuser`.
   - **Password**: Generate a strong password (e.g., `P@ssw0rd_2026#Secure!`).
3. Note down the Database Name, Username, and Password for your `.env`.

---

### STEP 3: Import Database (`smart_society.sql`)
1. In Hostinger hPanel under **MySQL Databases**, locate your new database.
2. Click **Enter phpMyAdmin**.
3. Select your database from the left sidebar.
4. Click the **Import** tab at the top.
5. Click **Choose File** and upload:
   ```
   deployment/database/smart_society.sql
   ```
6. Click **Import** (or **Go**). All 33 tables and seed records will be imported cleanly.

---

### STEP 4: Upload Laravel Backend to Hostinger

#### Option A: Using SSH / Git (Recommended)
1. In Hostinger hPanel, enable **SSH Access** under **Advanced** ➔ **SSH Access**.
2. Connect to your server:
   ```bash
   ssh -p PORT u123456789@YOUR_SERVER_IP
   ```
3. Navigate to your website folder and upload/clone your backend:
   ```bash
   cd ~/domains/YOUR-DOMAIN.com/public_html
   mkdir -p api
   # Upload backend files into ~/domains/YOUR-DOMAIN.com/public_html/api
   ```

#### Option B: Using Hostinger File Manager
1. On your PC, zip the contents of the `backend/` folder (exclude `vendor/`, `node_modules/`, `.git/`, and local `.env`).
2. In Hostinger hPanel, open **File Manager**.
3. Navigate to `public_html/api/` (or your subdomain root directory).
4. Upload and extract the zip file.

---

### STEP 5: Verify Web Root Directory Structure
Ensure your directory structure conforms to:
```
public_html/api/
├── app/
├── bootstrap/
├── config/
├── database/
├── public/          <--- THIS MUST BE THE DOCUMENT ROOT (Web accessible)
│   ├── .htaccess
│   ├── index.php
│   └── favicon.ico
├── routes/
├── storage/
├── .env             <--- SECURE (NOT accessible via browser)
└── composer.json
```

---

### STEP 6: Configure `.env` on Hostinger
1. In Hostinger File Manager or SSH, create/edit the `.env` file in the Laravel root (`public_html/api/.env`).
2. Copy contents from `deployment/.env.example` and fill in your real Hostinger credentials:

```ini
APP_NAME="Smart Society"
SOCIETY_NAME="Kasliwal Marvel (West)"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.YOUR-DOMAIN.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=u123456789_smartsociety
DB_USERNAME=u123456789_societyuser
DB_PASSWORD=YOUR_STRONG_DATABASE_PASSWORD

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local

# Razorpay Payment Gateway (Test or Live)
PAYMENT_MODE=test
RAZORPAY_KEY_ID=YOUR_RAZORPAY_KEY_ID
RAZORPAY_KEY_SECRET=YOUR_RAZORPAY_KEY_SECRET
RAZORPAY_WEBHOOK_SECRET=YOUR_WEBHOOK_SECRET
```

---

### STEP 7: Install Composer Dependencies
Run via SSH in the Laravel project root:
```bash
composer install --no-dev --optimize-autoloader
```
*(If SSH is unavailable, install composer dependencies locally before zipping and uploading).*

---

### STEP 8: Generate Application Key & Link Storage
```bash
php artisan key:generate --force
php artisan storage:link
```

---

### STEP 9: Set Folder Permissions
Ensure the web server has write permissions to `storage` and `bootstrap/cache`:
```bash
chmod -R 775 storage bootstrap/cache
```

---

### STEP 10: Cache Configuration & Routes for Production
Optimize Laravel performance:
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

### STEP 11: Enable HTTPS / SSL Certificate
1. In Hostinger hPanel, go to **Security** ➔ **SSL**.
2. Select your domain/subdomain (`api.YOUR-DOMAIN.com`) and click **Install SSL** (Free Let's Encrypt).
3. Toggle **Force HTTPS** ON.

---

### STEP 12: Test Backend Health Check
Open in your browser or run curl:
```bash
curl https://api.YOUR-DOMAIN.com/api/health
```
**Expected Response:**
```json
{
  "status": "ok",
  "message": "Smart Society API is running",
  "service": "Smart Society API",
  "timestamp": "2026-08-24T...",
  "database": "u123456789_smartsociety"
}
```

---

## 4. Flutter Production Build & Configuration

### STEP 13: Build Flutter Android APK
On your development machine, open a terminal in `d:\SmartSociety\mobile`:

1. Clean previous build artifacts:
   ```bash
   flutter clean
   flutter pub get
   ```

2. Build the production release APK with your Hostinger production API URL:
   ```bash
   flutter build apk --release --dart-define=API_BASE_URL=https://api.YOUR-DOMAIN.com/api
   ```

3. The generated release APK will be located at:
   ```
   mobile/build/app/outputs/flutter-apk/app-release.apk
   ```

4. Install the APK onto the Android phone:
   ```bash
   flutter install
   # OR transfer app-release.apk to your phone and install
   ```

---

## 5. Production Verification Checklist

After deployment, test the following on the mobile device with the production API:

- [ ] **Health Endpoint**: `GET https://api.YOUR-DOMAIN.com/api/health` returns `HTTP 200` with status "ok".
- [ ] **Resident Login**: `resident@smartsociety.local` / `Resident@123` ➔ Loads Resident Dashboard.
- [ ] **Security Login**: `security@smartsociety.local` / `Security@123` ➔ Loads Security Dashboard & Metrics.
- [ ] **Administrator Login**: `admin@smartsociety.local` / `Admin@123` ➔ Loads Admin Dashboard.
- [ ] **Residents Screen**: Displays populated resident cards (names, unit numbers, contact info, owner/tenant badges).
- [ ] **Add Resident**: FAB (+) opens modal form, submits to `POST /api/admin/residents`, saves to MySQL, and refreshes list.
- [ ] **Visitors Screen**: Single AppBar title "Visitors", lists expected/approved visitors.
- [ ] **Parcels Screen**: Single AppBar title "Parcels", logs courier deliveries.
- [ ] **Security Quick Actions**: Check in/out visitor, vehicle entry, and parcel logging buttons respond instantly.
- [ ] **Error Handling**: When offline or unreachable, displays `"Unable to connect to server. Please try again."` with a Retry button.
