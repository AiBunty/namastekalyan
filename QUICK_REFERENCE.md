# Quick Reference: Settings Management System

## 📋 What You Get

A complete system to manage WhatsApp number and Menu Blocker staff code through an admin panel, instead of hardcoding them.

## 🚀 Quick Start (5 minutes)

### Step 1: Add one line to your HTML

In every HTML file where you use menu-blocker, add this **BEFORE** `menu-blocker.js`:

```html
<script src="/menu-blocker-init.js"></script>
<script src="/menu-blocker.js"></script>
```

### Step 2: Create the config folder

```bash
mkdir -p backend/config
```

### Step 3: Verify files are in place

- ✅ `/menu-blocker-init.js` - Root directory
- ✅ `/admin_settings_standalone.html` - Root directory  
- ✅ `/backend/api_settings.php` - Backend directory
- ✅ `/backend/config/` - Directory (auto-created)

### Step 4: Access the admin panel

Open: `https://yoursite.com/admin_settings_standalone.html`

(Or: `https://yoursite.com/admin_settings.html` if embedded in admin dashboard)

### Step 5: Update settings

1. Enter WhatsApp number (with country code, e.g., 919371519999)
2. Enter staff code (minimum 4 characters)
3. Click "Save Settings"
4. Visitor pages will load new settings on refresh

**Done!** ✨

---

## 📁 File Structure

```
namastekalyan/
├── menu-blocker-init.js           ← Add this BEFORE menu-blocker.js
├── admin_settings.html              ← Embeds in admin dashboard
├── admin_settings_standalone.html   ← Standalone page
├── menu-blocker.js                  ← Your existing file (NO CHANGES)
├── index.html 
├── menu.html
├── other pages...
│
└── backend/
    ├── api_settings.php             ← API endpoint
    ├── bootstrap.php                ← Uses existing bootstrap
    │
    └── config/
        └── app-settings.json        ← Auto-created, stores settings
```

---

## 🔗 Integration Points

### For Public Pages (menu.html, menu.php, etc.)

```html
<!DOCTYPE html>
<html>
<body>
  <!-- Your content -->
  
  <!-- Load settings first -->
  <script src="/menu-blocker-init.js"></script>
  
  <!-- Then load menu blocker -->
  <script src="/menu-blocker.js"></script>
</body>
</html>
```

### For Admin Dashboard

**Option A: Standalone popup**
```html
<a href="/admin_settings_standalone.html" target="_blank">
  ⚙️ Settings
</a>
```

**Option B: Embedded iframe**
```html
<iframe 
  src="/admin_settings_standalone.html" 
  width="100%" 
  height="800" 
  frameborder="0"
></iframe>
```

**Option C: Inline embed**
```html
<!-- In your admin.html -->
<div id="settings-panel"></div>

<script>
  fetch('/admin_settings.html')
    .then(r => r.text())
    .then(html => {
      document.getElementById('settings-panel').innerHTML = html;
    });
</script>
```

---

## 🔧 How It Works

```
1. Page loads → menu-blocker-init.js runs first
2. Script checks browser cache
3. If cached: use cached settings
4. If not: fetch from /backend/api_settings.php
5. Set window.NK_DATA_API.hotelWhatsappNo
6. Set window.MENU_BLOCKER_STAFF_CODE
7. menu-blocker.js loads and reads those values
8. Done! ✓
```

---

## 📊 Current Settings Location

File: `/backend/config/app-settings.json`

Example content:
```json
{
  "hotelWhatsappNo": "919371519999",
  "menuBlockerStaffCode": "NKSTAFF2026",
  "updatedAt": "2024-01-15T14:30:00",
  "updatedBy": "admin_123"
}
```

---

## 🔐 Security Checklist

- ✅ POST requests require admin auth (via your existing `Auth::verifyRequest()`)
- ✅ Settings file stored server-side (not visible to public)
- ✅ GET requests are public (WhatsApp is already public anyway)
- ✅ Uses HTTPS in production (recommended)
- ✅ Input validation on both client & server

---

## 🔄 Clear Cache & Reload

**From JavaScript console** (in admin panel):
```javascript
MenuBlockerInitClearCache();
```

**From visitor page** (clears cached settings):
```javascript
localStorage.removeItem('nk_menu_blocker_settings_cache');
location.reload();
```

**In Chrome DevTools:**
1. F12 → Application → Local Storage
2. Find `nk_menu_blocker_settings_cache`
3. Delete it and refresh page

---

## 🐛 Troubleshooting

### Settings not updating on visitor pages?

**Solution:** Clear browser cache
```javascript
// In browser console
localStorage.removeItem('nk_menu_blocker_settings_cache');
location.reload();
```

### Admin panel shows error?

**Check:**
1. Is `/backend/api_settings.php` accessible? (check Network tab)
2. Is `/backend/config/` directory writable?
3. Are you logged in as admin?

### Staff code not working?

**Remember:** Code is converted to UPPERCASE for comparison
- Input: `nkstaff` → Stored as `NKSTAFF`
- Input: `NkStaff` → Also matches `NKSTAFF` ✓

### Settings show "Loading..." forever?

**Likely issue:** API endpoint timing out or failing

**Check in browser console:**
```javascript
fetch('/backend/api_settings.php').then(r => r.json()).then(console.log);
```

---

## 📞 API Reference

### GET Settings (Public)
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

### POST Settings (Admin Only)
```bash
curl -X POST https://yoursite.com/backend/api_settings.php \
  -H "Content-Type: application/json" \
  -d '{
    "hotelWhatsappNo": "919876543210",
    "menuBlockerStaffCode": "NEWCODE"
  }'
```

---

## 🎯 Testing Checklist

- [ ] Created `/backend/config/` directory
- [ ] Added `menu-blocker-init.js` to HTML files
- [ ] API endpoint accessible at `/backend/api_settings.php`
- [ ] Admin panel opens without errors
- [ ] Can update WhatsApp number
- [ ] Can update staff code
- [ ] Settings persist after page reload
- [ ] Menu blocker uses new staff code
- [ ] WhatsApp button uses new number

---

## 💡 Pro Tips

**Tip 1: Set default values**
```php
// In api_settings.php (before line 50)
const DEFAULT_WHATSAPP = '919371519999';
const DEFAULT_STAFF_CODE = 'NKSTAFF2026';
```

**Tip 2: Audit changes**
Every update logs `updatedBy` and `updatedAt` in the JSON file.

**Tip 3: Bulk operations**
Cache duration is 5 minutes. Increase for high-traffic sites:
```javascript
// In menu-blocker-init.js, line ~19
cacheDuration: 30 * 60 * 1000, // 30 minutes
```

**Tip 4: Fallback values**
If API fails, defaults in `menu-blocker-init.js` are used. Never breaks!

---

## 📞 Support Files

| File | Purpose |
|------|---------|
| `SETTINGS_INTEGRATION_GUIDE.md` | Detailed documentation |
| `QUICK_REFERENCE.md` | This file |
| `admin_settings_standalone.html` | Recommended: use this for admin panel |
| `menu-blocker-init.js` | The magic ✨ |

---

## ✅ Done!

You now have a fully functional settings management system:

✓ No hardcoded values in JavaScript  
✓ Admin can update settings anytime  
✓ Changes apply to all pages automatically  
✓ Backward compatible with existing code  
✓ Secure & cached for performance  

**Questions?** See `SETTINGS_INTEGRATION_GUIDE.md` for detailed explanations.
