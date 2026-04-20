# Serverbyt Deployment Issues And Resolution

## Purpose

This document captures the deployment issues discovered while moving the Namaste Kalyan project from local development to shared hosting, the root cause of each issue, and the resolution that made the live site stable.

Use this file as the reference for future deployments to `serverbyt.in` or any similar shared-hosting environment so that local development behaves like production and deployments fail less often.

## Project Layout Assumption

The production server should receive the project with this structure at the web root:

```text
/
  .htaccess
  index.php
  api_settings.php
  app/
  bootstrap/
  config/
  database/
  public/
  storage/
  vendor/
```

The browser must access public assets through rewrite rules, not by exposing internal PHP folders directly.

## What Broke During Deployment

### 1. Homepage returned 403 Forbidden

Symptom:
- `https://namastekalyan.asianwokandgrill.in/` returned `403 Forbidden`.
- `https://.../public/` also returned `403`.

Root cause:
- Apache rewrite rules sent `/` to `/public/`.
- Another rule blocked direct access to `/public/`.
- The server was therefore obeying two contradictory rules.

Resolution:
- Special-case the site root in `.htaccess`.
- Route `/` directly to `public/index.html`.
- Keep direct `/public/...` requests blocked, but allow rewrites into `public/`.

Required rule shape:
- Keep `RewriteRule ^public/ - [F,END]`.
- Add `RewriteRule ^$ public/index.html [END]` before generic frontend mapping.
- Map real frontend files with `DOCUMENT_ROOT/public%{REQUEST_URI}` checks.

Files involved:
- [.htaccess](d:/GITHUB%20Projects/Namaste%20Kalyan/namastekalyan/.htaccess)

### 2. API and auth routes failed even though code was present

Symptom:
- `?action=auth_bootstrap_status` failed.
- Event APIs failed.
- Logs showed DB connection errors.

Root cause:
- The live server was not using the DB host that works from the hosting runtime.
- The server had stale `.env` values.
- Workstation-accessible DB host assumptions did not hold inside the hosting environment.

Resolution:
- The live PHP runtime must use the internal hosting DB endpoint.
- For this deployment, the working host was `sdb-53.hosting.stackcp.net:3306`.
- Keep server `.env` authoritative for production values.
- Do not overwrite live `.env` blindly during deploy.

Rule for serverbyt-style hosting:
- Test DB access from the host itself, not just from your laptop.
- If the provider offers an internal MySQL hostname, prefer that for the PHP runtime.

Files involved:
- [bootstrap/app.php](d:/GITHUB%20Projects/Namaste%20Kalyan/namastekalyan/bootstrap/app.php)
- [docs/deployment/DEPLOYMENT_STATUS.md](d:/GITHUB%20Projects/Namaste%20Kalyan/namastekalyan/docs/deployment/DEPLOYMENT_STATUS.md)

### 3. Production PHP crashed on event flows

Symptom:
- Live logs showed a syntax error in `EventService.php`.
- Event routes failed even after DB connectivity improved.

Root cause:
- The deployed backend file had an invalid class-level assignment pattern near verification URL construction.

Resolution:
- Fix the verification URL builder so it computes the base inside the method and returns a valid string.
- Commit and deploy the fixed service file.

Files involved:
- [app/Services/EventService.php](d:/GITHUB%20Projects/Namaste%20Kalyan/namastekalyan/app/Services/EventService.php)

### 4. Migrations looked complete but schema was actually incomplete

Symptom:
- Normal migration execution suggested nothing was left to run.
- Live tables required by the new code were missing or incomplete.

Root cause:
- Migration state and actual schema were out of sync.
- The existing migration entrypoint was not sufficient for this recovery path.

Resolution:
- Validate the real schema, not just the migration runner output.
- Compare the `migrations` table to expected tables.
- Apply missing migrations explicitly when recovering a broken environment.

Operational rule:
- After every deployment, verify required tables directly.
- Never trust a single `migrate.php` success message without checking the resulting schema.

### 5. Frontend MP4 requests were aborted on the homepage

Symptom:
- Homepage videos existed on disk but browser requests were aborted.
- The page visually loaded, but media fetches were unstable.

Root cause:
- The asset normalization script rewrote video URLs and then called `load()` and `play()`.
- The homepage hero script also managed the same video elements.
- That double-loading behavior caused aborted requests.

Resolution:
- Keep global asset normalization for most assets.
- Add an explicit opt-out for homepage hero video blocks.
- Mark hero videos with `data-skip-asset-normalize="true"`.

Files involved:
- [public/js/data-config.js](d:/GITHUB%20Projects/Namaste%20Kalyan/namastekalyan/public/js/data-config.js)
- [public/index.html](d:/GITHUB%20Projects/Namaste%20Kalyan/namastekalyan/public/index.html)

### 6. Operational data migration risked corrupting live relationships

Symptom:
- Local operational tables had a mix of real and test rows.
- Live operational tables were mostly empty.
- Not every local record had a safe live foreign-key target.

Root cause:
- Event OTP and check-in records depended on event transaction history that did not exist on live.
- Bulk copying would have created false audit history.

Resolution:
- Migrate only records with safe natural-key mapping.
- For this recovery, only CRM contacts with matching live lead phones were migrated.
- Skip OTP, check-in, and CRM push-log history unless transaction and lead references can be mapped safely.

## Localhost Should Mirror Production Like This

To make localhost behave like deployment:

### A. Use the same folder structure

Local dev should serve from the project root with rewrite rules that mimic hosting behavior.

Recommended local command:

```powershell
php -S localhost:3000 router.php
```

### B. Keep profile-based environment values

Use profile-specific env keys so local and live can share the same code:

```env
NK_ENV_PROFILE=local

DB_HOST_LOCAL=127.0.0.1
DB_PORT_LOCAL=3308
DB_NAME_LOCAL=namastekalyan_local

DB_HOST_LIVE=sdb-53.hosting.stackcp.net
DB_PORT_LIVE=3306
```

Rules:
- Local profile must point to local DB only.
- Live profile must point to the provider's internal DB host.
- Local code must never assume that live DB host works from localhost.

### C. Do not deploy local `.env` over live `.env`

The deploy package may include templates such as `.env.example`, but production secrets and live DB host settings must stay on the server.

### D. Validate the same URLs before and after deploy

Always check these after local test and after live upload:

```text
/
/index.php?action=auth_bootstrap_status
/?action=events_list&limit=5
/?action=food_menu_items
/?action=bar_menu_items
```

### E. Verify schema after deployment

Check that these categories of tables exist and have expected row counts:
- menu tables
- QR redirect tables
- CRM tables
- event transaction and event verification tables

## Recommended Deployment Workflow For Serverbyt.in

### Step 1. Prepare local environment

- Confirm local app runs through `router.php`.
- Confirm homepage, auth bootstrap, event APIs, and menu APIs work.
- Confirm no new syntax/runtime errors.

### Step 2. Confirm live env settings separately

- Confirm the live `.env` contains the provider-valid DB host.
- Confirm FTP host, user, pass, and remote path are correct.
- Confirm `NK_ENV_PROFILE=live` is set where expected.

### Step 3. Upload only the intended files

For partial hotfix deployment, upload only the changed files, for example:
- `.htaccess`
- `public/index.html`
- `public/js/data-config.js`
- `app/Services/EventService.php`

For broader backend changes, deploy a validated full artifact instead of a piecemeal upload.

### Step 4. Re-run smoke checks immediately

Smoke checks:
- homepage loads
- auth bootstrap returns success JSON
- events list returns items
- food menu returns items
- bar menu returns items

### Step 5. Verify logs if anything fails

If production fails:
- check PHP syntax errors first
- check DB host resolution second
- check rewrite behavior third
- check migration/schema state fourth

## Deployment Checklist

Before deploy:
- [ ] Local homepage works
- [ ] Local auth bootstrap works
- [ ] Local event list works
- [ ] Local food and bar APIs work
- [ ] Changed files are known and minimal
- [ ] Live `.env` values are confirmed

During deploy:
- [ ] Upload only intended files
- [ ] Do not overwrite live `.env` accidentally
- [ ] Preserve directory structure exactly

After deploy:
- [ ] Homepage loads without 403
- [ ] Auth bootstrap returns `ok: true`
- [ ] Events API returns data
- [ ] Menu APIs return data
- [ ] No critical PHP errors in logs
- [ ] If operational data is migrated, it was mapped safely by natural key or trusted IDs

## Final Rule Set

1. Treat hosting runtime networking as different from developer-machine networking.
2. Keep rewrite rules explicit for root, API, and public assets.
3. Never trust migration success output without schema verification.
4. Do not bulk-migrate operational history unless foreign-key mapping is provably safe.
5. Keep live `.env` separate from local `.env`.
6. Prefer small, verified hotfix uploads for urgent recovery and full validated artifacts for broader releases.
