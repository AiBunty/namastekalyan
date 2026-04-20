# Menu Blocker & WhatsApp Settings Integration Guide

## Overview

This system allows you to manage the WhatsApp number and menu-blocker staff code through an admin panel, eliminating the need to hardcode values in JavaScript files.

## Components

### 1. **Backend API** (`backend/api_settings.php`)
- Endpoint: `/backend/api_settings.php`
- Handles storing/retrieving settings from `backend/config/app-settings.json`
- Requires admin authentication for POST requests
- GET requests return current settings

### 2. **Admin Settings Panel** (`admin_settings.html`)
- Complete UI for managing settings
- Form validation and error handling
- Shows last updated timestamp
- Real-time feedback with alert messages

### 3. **Menu Blocker Initializer** (`menu-blocker-init.js`)
- Loads settings from API at page startup
- Caches settings for 5 minutes
- Provides window variables for existing `menu-blocker.js`
- No changes needed to existing menu-blocker.js code

## Setup Instructions

### Step 1: Include Files in Your HTML

Add these scripts to your HTML **before** `menu-blocker.js`:

```html
<!-- Load settings from API before menu-blocker initializes -->
<script src="/menu-blocker-init.js"></script>

<!-- Your existing menu blocker script -->
<script src="/menu-blocker.js"></script>
```

**Important Order:**
```
1. menu-blocker-init.js  ← Loads settings from API
2. menu-blocker.js       ← Uses those settings
```

### Step 2: Add Settings Panel to Admin Dashboard

Include the admin settings panel in your admin dashboard:

```html
<!-- In your admin.html or admin dashboard -->
<iframe src="/admin_settings.html" width="100%" height="800"></iframe>

<!-- OR embed directly -->
<div id="admin-settings-container"></div>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    fetch('/admin_settings.html')
      .then(r => r.text())
      .then(html => {
        document.getElementById('admin-settings-container').innerHTML = html;
      });
  });
</script>
```

### Step 3: Create Config Directory

Ensure the `backend/config/` directory exists:

```bash
mkdir -p backend/config
chmod 755 backend/config
```

### Step 4: Initialize Default Settings

The API will create `app-settings.json` automatically on first use. You can pre-populate it:

**`backend/config/app-settings.json`:**
```json
{
  "hotelWhatsappNo": "919371519999",
  "menuBlockerStaffCode": "NKSTAFF2026",
  "updatedAt": "2024-01-01T10:00:00",
  "updatedBy": "system"
}
```

## How It Works

### On Page Load

1. **menu-blocker-init.js** runs FIRST
2. Checks for cached settings in localStorage
3. If no cache, fetches from `/backend/api_settings.php`
4. Sets `window.NK_DATA_API.hotelWhatsappNo` and `window.MENU_BLOCKER_STAFF_CODE`
5. Caches settings for 5 minutes

### When Admin Updates Settings

1. Admin submits form in settings panel
2. Form sends POST to `/backend/api_settings.php`
3. API validates and saves to `app-settings.json`
4. Admin panel calls `window.MenuBlockerInitClearCache()` 
5. Next visitor gets fresh settings from API

### Staff Code Verification

Your existing `menu-blocker.js` already checks:
```javascript
const STAFF_SECRET_CODE = window.MENU_BLOCKER_STAFF_CODE || 'NKSTAFF2026';
```

The initializer sets `window.MENU_BLOCKER_STAFF_CODE` from the API.

## API Endpoints

### GET `/backend/api_settings.php`
**Public endpoint** - Returns current settings

```bash
curl https://yoursite.com/backend/api_settings.php
```

Response:
```json
{
  "success": true,
  "data": {
    "hotelWhatsappNo": "919371519999",
    "menuBlockerStaffCode": "NKSTAFF2026",
    "updatedAt": "2024-01-15T14:30:00"
  }
}
```

### POST `/backend/api_settings.php`
**Admin-only endpoint** - Updates settings

Requires admin session/authentication.

```bash
curl -X POST https://yoursite.com/backend/api_settings.php \
  -H "Content-Type: application/json" \
  -d '{
    "hotelWhatsappNo": "919876543210",
    "menuBlockerStaffCode": "NEWCODE2025"
  }'
```

Response:
```json
{
  "success": true,
  "message": "Settings updated successfully",
  "data": {
    "hotelWhatsappNo": "919876543210",
    "menuBlockerStaffCode": "NEWCODE2025",
    "updatedAt": "2024-01-15T14:35:00"
  }
}
```

## Caching & Performance

### Cache Duration: 5 minutes

Settings are cached in `localStorage` to avoid API calls on every page load.

**Clear cache programmatically:**
```javascript
// From JavaScript console
MenuBlockerInitClearCache();

// Or reload with fresh settings
MenuBlockerInitReload().then(settings => {
  console.log('Settings reloaded:', settings);
});
```

**Cache key:** `nk_menu_blocker_settings_cache`

## Security

### What's Protected

- ✅ POST requests (updating settings) require admin authentication
- ✅ Settings file has restricted permissions
- ✅ Input validation on both client and server

### What's Public

- ℹ️ GET requests (reading settings) are public
  - This is intentional so pages can load settings without auth
  - WhatsApp number is already public (displayed in UI)
  - Staff code hidden in password field

### Recommendations

1. **HTTPS Only** - Always use HTTPS in production
2. **Admin Auth** - Ensure your admin authentication works
3. **File Permissions** - Set `app-settings.json` to 0644
4. **Rate Limiting** - Add rate limiting to API endpoint if needed

## Troubleshooting

### Settings Not Loading

**Check browser console:**
```javascript
// In DevTools console, type:
localStorage.getItem('nk_menu_blocker_settings_cache')
localStorage.getItem('nk_menu_blocker_cache')
```

**Check Network tab:**
- Verify `/backend/api_settings.php` returns 200 OK
- Check response body for errors

### Cache Issues

Clear cache and reload:
```javascript
localStorage.removeItem('nk_menu_blocker_settings_cache');
location.reload();
```

### Staff Code Not Working

1. Check case sensitivity (code converted to UPPERCASE for comparison)
2. Remove extra spaces before/after code
3. Reload page to refresh cached settings
4. Check browser console for debug messages

### Settings Stuck After Update

Manually clear browser storage:
1. Open DevTools (F12)
2. Application → Local Storage → Your Site
3. Delete `nk_menu_blocker_settings_cache`
4. Refresh page

## Integration Examples

### Example 1: Include in Template

```html
<!DOCTYPE html>
<html>
<head>
  <title>Namaste Kalyan</title>
</head>
<body>
  <!-- Your content -->
  <div id="menu">
    <!-- Menu items -->
  </div>

  <!-- Settings loader (BEFORE menu-blocker.js) -->
  <script src="/menu-blocker-init.js"></script>
  
  <!-- Existing menu blocker -->
  <script src="/menu-blocker.js"></script>
</body>
</html>
```

### Example 2: Admin Dashboard Widget

```html
<div class="admin-dashboard">
  <div class="widget">
    <h3>⚙️ Quick Settings</h3>
    <p>WhatsApp: <span id="quickWa">Loading...</span></p>
    <p>Staff Code: <span id="quickCode">••••••</span></p>
    <a href="/admin_settings.html" target="_blank">Edit Settings →</a>
  </div>
</div>

<script>
  // Display current settings
  fetch('/backend/api_settings.php')
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        document.getElementById('quickWa').textContent = data.data.hotelWhatsappNo;
        document.getElementById('quickCode').textContent = 
          '●'.repeat(data.data.menuBlockerStaffCode.length);
      }
    });
</script>
```

### Example 3: Programmatic Updates

```javascript
// Update settings from JavaScript
async function updateSettings(whatsappNo, staffCode) {
  const response = await fetch('/backend/api_settings.php', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      hotelWhatsappNo: whatsappNo,
      menuBlockerStaffCode: staffCode
    })
  });

  const data = await response.json();
  
  if (data.success) {
    console.log('Settings updated!');
    // Clear cache so next page load gets fresh settings
    MenuBlockerInitClearCache();
  } else {
    console.error('Update failed:', data.error);
  }
}

// Usage
updateSettings('919876543210', 'NEWCODE2025');
```

## Migration from Hardcoded Values

### Before (Hardcoded)
```javascript
// In menu-blocker.js
const HOTEL_WHATSAPP_NO = '919371519999';
const MENU_BLOCKER_STAFF_CODE = 'NKSTAFF2026';
```

### After (Dynamic)
```javascript
// Settings loaded from API via menu-blocker-init.js
// No changes to existing code needed!
```

## Fallback Behavior

If API fails or is unreachable:

1. **From cache:** Use cached settings (up to 5 minutes old)
2. **If no cache:** Fall back to defaults defined in menu-blocker-init.js
3. **Graceful degradation:** Menu blocker continues to work

Defaults in `menu-blocker-init.js`:
```javascript
window.NK_DATA_API = { hotelWhatsappNo: '919371519999' };
window.MENU_BLOCKER_STAFF_CODE = 'NKSTAFF2026';
```

## Support & Debugging

### Enable Detailed Logging

Check browser console for debug messages:
```
[MenuBlocker Init] Using cached settings
[MenuBlocker Init] Settings loaded from API
[MenuBlocker Init] WhatsApp number set: 919371519999
[MenuBlocker Init] Staff code configured
```

### Common Messages

| Message | Meaning |
|---------|---------|
| `Using cached settings` | API not called, using localStorage cache |
| `Settings loaded from API` | Fresh settings fetched from API |
| `Cache cleared` | Settings cache has been purged |
| `Failed to load settings` | API unreachable, using defaults |

## Files Summary

| File | Purpose | Location |
|------|---------|----------|
| `menu-blocker-init.js` | Loads settings from API | Root directory |
| `api_settings.php` | Backend API endpoint | `backend/` |
| `app-settings.json` | Stores settings | `backend/config/` |
| `admin_settings.html` | Admin UI panel | Root directory |

## Next Steps

1. ✅ Copy files to your server
2. ✅ Create `backend/config/` directory
3. ✅ Include `menu-blocker-init.js` before `menu-blocker.js`
4. ✅ Add settings panel to admin dashboard
5. ✅ Test by visiting `/admin_settings.html`
6. ✅ Update settings and verify menu-blocker loads new values

---

**Questions?** Check the troubleshooting section or review the inline code comments.
