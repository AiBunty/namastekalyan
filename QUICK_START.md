# Local Testing - Quick Start

## What You Need to Do

### Phase 1: Environment Setup (One-time)

1. **Install MySQL 8.0+**
   - Download: https://dev.mysql.com/downloads/mysql/
   - Run installer with default settings
   - Set root password (remember it!)

2. **Create local database:**
   ```bash
   mysql -u root -p
   # Enter root password
   
   CREATE DATABASE `namastekalyan_local` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'namastes'@'localhost' IDENTIFIED BY 'LocalPass@123';
   GRANT ALL PRIVILEGES ON namastekalyan_local.* TO 'namastes'@'localhost';
   FLUSH PRIVILEGES;
   EXIT;
   ```

3. **Create `.env` file:**
   ```bash
   cd "d:\GITHUB Projects\Namaste Kalyan\namastekalyan"
   copy backend\.env.example backend\.env
   ```

   Edit `backend\.env` and change these lines:
   ```env
   APP_ENV=local
   APP_URL=http://localhost:8000/api
   DB_HOST=localhost
   DB_PORT=3306
   DB_NAME=namastekalyan_local
   DB_USER=namastes
   DB_PASS=LocalPass@123
   JWT_SECRET=local_test_secret_key_minimum_64_chars_abcdefghijklmnopqrstuvwxyz1234
   ```

4. **Run database migrations:**
   ```bash
   cd "d:\GITHUB Projects\Namaste Kalyan\namastekalyan"
   php backend/migrate.php
   ```
   
   You should see: `[✓] All migrations completed successfully!`

---

### Phase 2: Testing (Every time you want to test)

**Terminal 1 - Start PHP Server:**
```bash
cd "d:\GITHUB Projects\Namaste Kalyan\namastekalyan"
php -S localhost:8000 -t . backend/index.php
```

Keep this terminal open. You'll see:
```
PHP 8.2.0 Development Server started
Listening on http://localhost:8000
```

**Terminal 2 - Run Tests:**
```bash
cd "d:\GITHUB Projects\Namaste Kalyan\namastekalyan"
pwsh test-local-api.ps1
```

This will automatically run all 6 tests and show you the results.

---

## Expected Output

When all tests pass, you'll see:

```
╔════════════════════════════════════════════════════════════════╗
║                    TEST SUMMARY                               ║
╚════════════════════════════════════════════════════════════════╝

Total: 6 tests
✓ Passed: 6
✗ Failed: 0
Success Rate: 100%

✓ ALL TESTS PASSED!
The API is working correctly. Ready for remote deployment.
```

---

## Troubleshooting

### "Server not responding at http://localhost:8000/api"
- Check Terminal 1 - PHP dev server should still be running
- Check it says "Listening on http://localhost:8000"

### "Can't connect to MySQL server"
- Verify MySQL is running (check Services on Windows)
- Check DB credentials in `backend\.env`
- Test with: `mysql -u namastes -p namastekalyan_local`

### "FATAL: Migrations failed"
- Drop and recreate database:
  ```bash
  mysql -u root -p -e "DROP DATABASE namastekalyan_local; CREATE DATABASE namastekalyan_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  php backend/migrate.php
  ```

### "Extension ext-mysqli not loaded"
- Check PHP enabled extensions: `php -m | findstr mysqli`
- If missing, uncomment `extension=mysqli` in `php.ini`
- Restart dev server

---

## Test Failures?

If any test fails, **DO NOT deploy to remote**. Instead:

1. **Check the error message** in test output
2. **Verify PHP version:** `php --version` (must be 8.2+)
3. **Check MySQL connection:**
   ```bash
   mysql -u namastes -pLocalPass@123 namastekalyan_local -e "SELECT 1;"
   ```
4. **Check PHP error logs:**
   - Terminal 1 may show error output during request
   - Check `backend/logs/boot.log` for startup errors
5. **Paste the full error output** in chat for debugging

---

## Next Steps

1. ✅ Follow Phase 1 steps (one-time setup)
2. ✅ Follow Phase 2 steps and run tests
3. ✅ When all 6 tests pass, confirm with: "LOCAL TESTS PASSED"
4. 🚀 Then we deploy to remote with confidence

The whole setup takes ~15 minutes on first run, then tests run in <1 minute each time.

**Ready to start? Let me know once you complete Phase 1!**
