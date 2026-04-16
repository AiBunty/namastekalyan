# Local Development Setup Guide

## Prerequisites

- **PHP 8.2+** (verified, already on your system)
- **MySQL 8.0+** (download from [mysql.com](https://dev.mysql.com/downloads/mysql/))
- **Git Bash** or PowerShell (for running commands)

---

## Step 1: Install MySQL Locally

### Windows
1. Download MySQL Community Server from: https://dev.mysql.com/downloads/mysql/
2. Run the installer and choose **Developer Default** setup
3. Configure MySQL Server with:
   - **Port:** 3306 (default)
   - **MySQL X Protocol Port:** 33060
   - **Server Type:** Development Machine
4. Configure MySQL as a **Windows Service** (auto-start enabled)
5. Create a root user with password (e.g., `Root@123`)

### macOS
```bash
brew install mysql@8.0
brew services start mysql@8.0
mysql -u root -p  # Set root password on first login
```

### Linux (Ubuntu)
```bash
sudo apt-get install mysql-server-8.0
sudo mysql_secure_installation
```

---

## Step 2: Create Local Database

After installing MySQL, create the local database:

```bash
# Connect to MySQL console
mysql -u root -p

# Run these commands:
CREATE DATABASE `namastekalyan_local` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'namastes'@'localhost' IDENTIFIED BY 'LocalPass@123';
GRANT ALL PRIVILEGES ON namastekalyan_local.* TO 'namastes'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## Step 3: Create Local .env File

Create `backend/.env` (copy from `.env.example` and modify):

```bash
cd "d:\GITHUB Projects\Namaste Kalyan\namastekalyan"
copy backend\.env.example backend\.env
```

Edit `backend\.env` with these changes:

```env
# ==================================================
# Application
# ==================================================
APP_ENV=local
APP_TIMEZONE=Asia/Kolkata
APP_URL=http://localhost:8000/api

# ==================================================
# Database (MySQL) — LOCAL
# ==================================================
DB_HOST=localhost
DB_PORT=3306
DB_NAME=namastekalyan_local
DB_USER=namastes
DB_PASS=LocalPass@123

# ==================================================
# JWT
# ==================================================
JWT_SECRET=local_test_secret_key_minimum_64_chars_abcdefghijklmnopqrstuvwxyz1234

# ==================================================
# Bootstrap Superadmin
# ==================================================
BOOTSTRAP_SUPERADMIN_MOBILE=9371519999
BOOTSTRAP_SUPERADMIN_PASSWORD=8442

# ==================================================
# Razorpay (leave empty for local testing)
# ==================================================
RAZORPAY_KEY_ID=
RAZORPAY_KEY_SECRET=
RAZORPAY_WEBHOOK_SECRET=

# ==================================================
# CRM (leave empty for local testing)
# ==================================================
CRM_API_TOKEN=
CRM_API_ENDPOINT=

# ==================================================
# Email (optional for local)
# ==================================================
SMTP_HOST=localhost
SMTP_PORT=1025
SMTP_SECURE=
SMTP_USER=
SMTP_PASS=
```

---

## Step 4: Run Database Migrations

```bash
cd "d:\GITHUB Projects\Namaste Kalyan\namastekalyan"
php backend/migrate.php
```

Expected output:
```
[✓] Migrating 001_create_users.sql
[✓] Migrating 002_create_auth_audit.sql
[✓] Migrating 003_create_revoked_tokens.sql
...
[✓] Migrating 011_create_api_settings.sql
All migrations completed successfully!
```

---

## Step 5: Start PHP Development Server

```bash
cd "d:\GITHUB Projects\Namaste Kalyan\namastekalyan"
php -S localhost:8000 -t . backend/index.php
```

Output:
```
PHP 8.2.0 Development Server started at Wed Apr 14 15:00:00 2026
Listening on http://localhost:8000
```

Keep this terminal open while testing.

---

## Step 6: Test the API

Open a **new PowerShell/Git Bash terminal** and run:

### Test 1: Bootstrap Status (No Auth Required)
```bash
curl -X GET "http://localhost:8000/api/?action=auth_bootstrap_status"
```

Expected: `{"success":true,"bootstrapped":false}` or `true`

### Test 2: Events List
```bash
curl -X GET "http://localhost:8000/api/?action=events_list&limit=1"
```

Expected: `{"success":true,"data":[],"total":0}`

### Test 3: Create Event
```bash
curl -X POST "http://localhost:8000/api/?action=create_event" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test Event",
    "description": "Local test event",
    "category": "music",
    "event_date": "2026-05-01",
    "event_time": "19:00",
    "venue": "Test Venue",
    "price_free": true,
    "price_paid": 0
  }'
```

Expected: Event created successfully with ID

### Test 4: Auth Login (Create Admin)
```bash
curl -X POST "http://localhost:8000/api/?action=auth_login" \
  -H "Content-Type: application/json" \
  -d '{
    "mobile": "9371519999",
    "password": "8442"
  }'
```

Expected: `{"success":true,"token":"eyJ...","user":{...}}`

### Test 5: Get Menu Items
```bash
curl -X GET "http://localhost:8000/api/?action=menu_get_all&category=food"
```

Expected: `{"success":true,"data":[]}`

### Test 6: Leads Spin
```bash
curl -X POST "http://localhost:8000/api/?action=leads_spin_wheel" \
  -H "Content-Type: application/json" \
  -d '{
    "mobile": "9999999999",
    "name": "Test User"
  }'
```

Expected: `{"success":true,"spin_result":{...}}`

---

## Troubleshooting

### MySQL Connection Error
```
SQLSTATE[HY000]: General error: 2003 Can't connect to MySQL server on 'localhost' (111)
```

**Fix:**
```bash
# Check if MySQL is running
mysql -u root -p -e "SELECT 1"

# Or restart MySQL service:
# Windows: Services app → MySQL80 → Start
# macOS: brew services restart mysql@8.0
# Linux: sudo systemctl restart mysql
```

### PHP Extension Missing (`ext-mysqli`)
```
Fatal error: Call to undefined function mysqli_connect()
```

**Fix:**
```bash
# Verify PHP extensions
php -m | grep -i mysqli

# If missing, enable in php.ini:
# Linux/macOS: uncomment extension=mysqli
# Windows: Check C:\php\php.ini
```

### Database Already Exists
```
ERROR 1007: Can't create database 'namastekalyan_local'; database exists
```

**Fix:** Drop and recreate:
```bash
mysql -u root -p -e "DROP DATABASE IF EXISTS namastekalyan_local; CREATE DATABASE namastekalyan_local;"
```

### PORT 8000 Already in Use
```bash
# Use different port:
php -S localhost:8001 -t . backend/index.php

# Update APP_URL in .env:
APP_URL=http://localhost:8001/api
```

---

## Validation Checklist

- [ ] MySQL 8.0+ installed and running
- [ ] Local database `namastekalyan_local` created
- [ ] `.env` file configured for localhost
- [ ] All 11 migrations executed successfully
- [ ] All 6 API tests return 200 + valid JSON
- [ ] No PHP errors in console
- [ ] No database connection errors

Once all tests pass ✅, proceed to remote deployment.

---

## Next Steps

1. Run all 6 tests above
2. Capture response output (paste in chat)
3. Verify all responses are `{"success":true...}` format
4. Once validated, we'll deploy to live server with confidence
