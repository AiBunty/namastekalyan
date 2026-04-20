# Namaste Kalyan - QR Code Tracking & Analytics System

## Overview
Complete QR code solution for your menu with automated scan tracking and email notifications to your business owner.

---

## 🎯 Quick Start

### 1. QR Code Details
- **QR Code URL:** `https://namastekalyan.asianwokandgrill.in/menu.html?qr=track`
- **Landing Page:** Display the QR code from `/qr-code.html`
- **Display Location:** Access at `https://namastekalyan.asianwokandgrill.in/qr-code.html`

### 2. Get Your QR Code

**Option A - View Online:**
1. Open: `https://namastekalyan.asianwokandgrill.in/qr-code.html`
2. Click "Download QR Code" button
3. Save as PNG image

**Option B - Generate Directly:**
Use any of these services to generate a QR code for:
- URL: `https://namastekalyan.asianwokandgrill.in/menu.html?qr=track`
- Services: qr-server.com, goqr.me, qr-code-generator.com

### 3. Print & Display:
- Print the QR code (A4 size recommended, at least 200x200 pixels)
- Display in restaurant (counter, tables, entrance, social media)
- Include text: "Scan to View Our Menu"

---

## 📊 Scan Tracking System

### How It Works:
1. **Customer scans QR code** → Redirected to menu with tracking parameter
2. **Backend logs scan** → Records timestamp, IP, user agent, browser info
3. **Data stored** → Google Sheet "QR Scans" tracks all interactions
4. **Analytics tracked** → Cumulative scan count maintained
5. **Email alerts** → Every 100 scans, email sent to owner

### Data Collected Per Scan:
- **Timestamp:** Exact date and time of scan
- **User Agent:** Device browser information (mobile/desktop)
- **Referer:** Source website (if any)
- **IP Address:** Visitor's IP location
- **Scan Number:** Sequential scan counter

### Scan Report Structure:
Sheet Name: `QR Scans`
Columns:
1. Timestamp
2. User Agent
3. Referer
4. IP Address
5. Scan Number

---

## 📧 Email Notification System

### Notification Trigger:
- **Every 100 scans** → Email notification sent
- **Milestones:** 100, 200, 300, 400, etc.

### Email Details:
- **From:** `noreply@dcoresystems.com`
- **Name:** Dcore Systems Support
- **To:** `support@dcoresystem.com`
- **Subject:** `🎉 Menu QR Code Milestone: {SCAN_COUNT} Scans!`

### Email Content Includes:
1. **Milestone Achievement** - Total scan count reached
2. **Quick Stats:**
   - Current total scans
   - Previous milestone
   - Next milestone target
   - Report timestamp
3. **QR Code Details:**
   - Tracking URL
   - Information about tracking

4. **Analytics Tip:**
   - Link to Google Spreadsheet for detailed analytics
   - Suggestion to view "QR Scans" sheet

---

## 🔧 Configuration Settings

Located in: `appscript/Code.gs`

```javascript
// QR Configuration
const QR_MENU_URL = 'https://namastekalyan.asianwokandgrill.in/menu.html';
const QR_TRACKING_URL_SUFFIX = '?qr=track';

// Email Configuration
const EMAIL_FROM = 'noreply@dcoresystems.com';
const EMAIL_FROM_NAME = 'Dcore Systems Support';
const EMAIL_TO = 'support@dcoresystem.com';
const EMAIL_SCAN_INTERVAL = 100; // Send email every 100 scans

// SMTP Configuration (pre-configured)
const SMTP_HOST = 'smtp.dcoresystems.com';
const SMTP_PORT = 465;
```

**To modify:**
- Change `EMAIL_TO` to different destination email
- Change `EMAIL_SCAN_INTERVAL` to send emails at different milestones
- Update `QR_MENU_URL` if menu location changes

---

## 📱 Display QR Code Page

A dedicated QR code display page has been created at: `/qr-code.html`

### Features:
✅ Beautiful responsive design
✅ Real-time scan statistics
✅ Download QR code as PNG
✅ Copy menu URL to clipboard
✅ View full analytics report
✅ Mobile-optimized (70% to 100% responsive)

### How to Access:
1. Open browser: `https://namastekalyan.asianwokandgrill.in/qr-code.html`
2. Share with staff for printing
3. Display on screens/posters
4. Check live statistics

---

## 🔗 API Endpoints

### Track QR Scan:
```
GET /apps-script-url?qr=track
```
Response:
```json
{
  "ok": true,
  "result": "qr_scan_tracked",
  "scanNumber": 150,
  "timestamp": "2026-04-12T10:30:45.000Z",
  "emailNotificationSent": true
}
```

### Get QR Scan Report:
```
GET /apps-script-url?action=qr_report
```
Response:
```json
{
  "ok": true,
  "totalScans": 325,
  "qrUrl": "https://namastekalyan.asianwokandgrill.in/menu.html?qr=track",
  "generatedAt": "2026-04-12T10:30:45.000Z",
  "recentScans": [...]
}
```

---

## 📈 Analytics Dashboard

### View Scan Statistics:
1. Open your Google Spreadsheet: `https://docs.google.com/spreadsheets/d/{SPREADSHEET_ID}`
2. Navigate to "QR Scans" sheet
3. See all recorded scans with:
   - Timestamp of each scan
   - Device information
   - User location (IP address)
   - Sequential scan number

### Export Data:
1. Select data in QR Scans sheet
2. File → Download → Choose format (Excel, CSV, PDF)
3. Use for custom analysis or reports

---

## 🎨 Print-Ready QR Code

The QR code can be printed in multiple sizes:

### Recommended Sizes:
- **Small:** 100x100 px (social media posts)
- **Medium:** 250x250 px (menu cards, leaflets)
- **Large:** 500x500 px (posters, entrance sign)
- **Extra Large:** 1000x1000 px (billboards, displays)

### Print Tips:
1. Use high resolution (300 DPI for print)
2. Ensure good contrast (black QR on white background)
3. Test scan with multiple devices before printing
4. Keep at least 10mm white border around QR code

---

## 🧪 Test Your QR Code

### Quick Test:
1. Open `/qr-code.html` in browser
2. Scan the displayed QR code with mobile phone camera
3. Should redirect to menu page with `?qr=track` parameter
4. Check "QR Scans" sheet for new entry

### Monitor In Real-Time:
1. Keep Google Sheet open
2. Have someone scan QR code
3. Refresh sheet (Ctrl+R)
4. New row appears with scan data

---

## 🚀 Deployment Status

### Apps Script Version:
- **Version 39** - Latest with QR tracking
- **Backend Functions:**
  - `handleQrScanTracking_()` - Track QR scans
  - `sendQrScanNotificationEmail_()` - Send milestone emails
  - `getQrScanReport_()` - Get scan analytics
  - `getOrCreateQrScansSheet_()` - Create tracking sheet

### Files Updated:
- ✅ `appscript/Code.gs` (v39)
- ✅ `qr-code.html` (created)
- ✅ Google Sheet: "QR Scans" sheet (auto-created)

---

## 📋 Troubleshooting

### QR Code Not Scanning:
- Try different QR scanning apps
- Ensure good lighting
- Check QR code resolution (at least 200x200 px)
- Test on different devices

### Emails Not Arriving:
- Check email address: `support@dcoresystem.com`
- Check spam/junk folder
- Verify email is configured correctly
- Allow 5-10 minutes for email delivery

### Scans Not Recording:
- Check if URL parameter `?qr=track` is present
- Ensure JavaScript is enabled in browser
- Check "QR Scans" sheet exists in Google Spreadsheet
- Verify Apps Script is deployed (v39+)

### Report Shows 0 Scans:
- First scan will take 1 minute to appear
- Refresh page to see latest count
- Check if new "QR Scans" sheet was created

---

## 📞 Support & Customization

### Current Setup:
- Email from: `noreply@dcoresystems.com`
- SMTP Host: `smtp.dcoresystems.com`
- SMTP Port: 465
- Email recipient: `support@dcoresystem.com`

### To Customize:
1. Modify `CODE.gs` constants (lines 13-18)
2. Deploy new version: `clasp deploy`
3. Changes take effect immediately

### Email Frequency:
- Default: Every 100 scans
- To change: Update `EMAIL_SCAN_INTERVAL` in Code.gs
- Examples: 50, 100, 200, 500, 1000

---

## 🎯 Success Metrics

Track your QR code success with:

| Metric | Location |
|--------|----------|
| **Total Scans** | "QR Scans" sheet row count |
| **Scan Rate** | Scans per day/week/month |
| **Peak Hours** | Timestamp analysis in sheet |
| **Device Types** | User Agent data in sheet |
| **Geographic Data** | IP address analysis |

---

## 🔒 Security & Privacy

- No personal data collected
- Only technical scan data recorded
- Data stored in your Google Spreadsheet
- No third-party access to scan data
- IP data used only for location analytics

---

## 📅 Maintenance

### Regular Tasks:
- Check email notifications (confirm receipt)
- Monitor scan trends weekly
- Archive old scan data monthly (optional)
- Test QR code scanning quarterly

### Backup:
- Google Sheet automatically backed up
- Download QR Scans sheet monthly as backup
- Keep printable QR codes in multiple locations

---

## 🎪 Use Cases

### Where to Display:
1. **Restaurant Entry** - Display at door/entrance
2. **Table Numbers** - Include on table tents
3. **Menus & Leaflets** - Print on physical menus
4. **Social Media** - Post on Instagram/Facebook
5. **WhatsApp** - Share in status/broadcast
6. **Email** - Include in restaurant emails
7. **Google Business Profile** - Add to business listing
8. **Website** - Embed on website footer
9. **Posters/Banners** - Large format displays
10. **Ads & Marketing** - All promotional materials

---

## 📞 Support Contact

**System Provider:** Dcore Systems
**Support Email:** support@dcoresystem.com
**System Email:** noreply@dcoresystems.com

For issues or customizations, contact the support team with:
- Scan count at time of issue
- Expected vs actual behavior
- Screenshots if applicable

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 39 | 2026-04-12 | Added QR report endpoint |
| 38 | 2026-04-12 | Initial QR tracking system with email notifications |

---

**Last Updated:** April 12, 2026  
**Status:** ✅ Production Ready  
**System:** Namaste Kalyan Menu QR Code Tracking
