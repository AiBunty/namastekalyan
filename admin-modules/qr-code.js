/**
 * NK Admin SPA Module: QR Code Manager
 * Source: qr-code.html
 */
(function (NK) {
  'use strict';
  NK.MODULES = NK.MODULES || {};
  NK.MODULES['qr-code'] = {
    _container: null,
    _statsInterval: null,
    _alertTimer: null,

    init: function (container) {
      this._container = container;
      var module = this;

      var APPS_SCRIPT_URL = (
        (window.NK_DATA_API && window.NK_DATA_API.appsScriptUrl) ||
        window.APPS_SCRIPT_URL ||
        ''
      ).trim();
      var MENU_URL = 'https://namastekalyan.asianwokandgrill.in/menu.html';
      var QR_TRACK_URL = 'https://namastekalyan.asianwokandgrill.in/scan.html';
      var ADMIN_PANEL_URL = 'https://namastekalyan.asianwokandgrill.in/admin-portal.html';

      container.innerHTML = '<style>'
        + '.qrc-wrap{font-family:"Segoe UI",Tahoma,Geneva,Verdana,sans-serif;padding:20px}'
        + '.qrc-box{background:#fff;border-radius:20px;box-shadow:0 4px 24px rgba(0,0,0,.12);max-width:780px;margin:0 auto;padding:40px;text-align:center}'
        + '.qrc-logo{font-size:48px;margin-bottom:15px}'
        + '.qrc-box h2{color:#333;font-size:28px;margin:0 0 10px}'
        + '.qrc-subtitle{color:#666;font-size:14px;margin:0 0 10px}'
        + '.qrc-divider{width:60px;height:3px;background:linear-gradient(90deg,#667eea,#764ba2);margin:20px auto;border-radius:2px}'
        + '.qrc-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin:30px 0}'
        + '.qrc-section{background:#f8f9fa;padding:30px;border-radius:15px}'
        + '.qrc-label{color:#666;font-size:13px;text-transform:uppercase;letter-spacing:1px;margin-bottom:15px;font-weight:600}'
        + '.qrc-code-frame{display:inline-block;padding:15px;background:#fff;border-radius:10px;box-shadow:0 4px 15px rgba(0,0,0,.1)}'
        + '.qrc-code-frame img{display:block;width:250px;height:250px}'
        + '.qrc-stats{background:#e8f4f8;padding:20px;border-radius:10px;border-left:4px solid #20c997;margin-top:20px;text-align:left}'
        + '.qrc-stats h3{color:#333;font-size:16px;margin-bottom:12px}'
        + '.qrc-stat-item{display:flex;justify-content:space-between;padding:10px 0;color:#666;font-size:14px;border-bottom:1px solid rgba(32,201,151,.2)}'
        + '.qrc-stat-item:last-child{border-bottom:none}'
        + '.qrc-stat-label{font-weight:600}'
        + '.qrc-scan-count{color:#667eea;font-size:24px;font-weight:bold}'
        + '.qrc-instructions{background:#fffbea;padding:20px;border-radius:10px;border-left:4px solid #ffc107;margin-top:30px;text-align:left}'
        + '.qrc-instructions h3{color:#333;font-size:16px;margin-bottom:12px}'
        + '.qrc-instructions ol{padding-left:20px;color:#666;font-size:14px;line-height:1.8}'
        + '.qrc-instructions li{margin-bottom:8px}'
        + '.qrc-actions{margin-top:30px;display:flex;gap:10px;flex-wrap:wrap}'
        + '.qrc-btn{flex:1;min-width:150px;padding:12px 24px;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;transition:all .3s ease;display:inline-flex;align-items:center;justify-content:center;gap:8px}'
        + '.qrc-btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff}'
        + '.qrc-btn-primary:hover{transform:translateY(-2px);box-shadow:0 10px 20px rgba(102,126,234,.3)}'
        + '.qrc-btn-secondary{background:#f0f0f0;color:#333}'
        + '.qrc-btn-secondary:hover{background:#e0e0e0;transform:translateY(-2px)}'
        + '.qrc-alert{padding:15px;border-radius:8px;margin-bottom:20px;display:none;text-align:left}'
        + '.qrc-alert.success{background:#d4edda;color:#155724;border-left:4px solid #28a745;display:block}'
        + '.qrc-alert.info{background:#d1ecf1;color:#0c5460;border-left:4px solid #17a2b8;display:block}'
        + '.qrc-footer{margin-top:30px;padding-top:20px;border-top:1px solid #eee;color:#999;font-size:12px}'
        + '@media(max-width:600px){.qrc-grid{grid-template-columns:1fr}.qrc-code-frame img{width:200px;height:200px}.qrc-actions{flex-direction:column}.qrc-btn{min-width:100%}}'
        + '</style>'
        + '<div class="qrc-wrap"><div class="qrc-box">'
        + '<div class="qrc-logo">🍜</div>'
        + '<h2>Namaste Kalyan</h2>'
        + '<p class="qrc-subtitle">Asian Wok &amp; Grill</p>'
        + '<div class="qrc-divider"></div>'
        + '<div id="qrcAlertBox" class="qrc-alert"></div>'
        + '<div class="qrc-grid">'
        + '<div class="qrc-section"><div class="qrc-label">Scan to View Menu</div><div class="qrc-code-frame"><img id="qrcImg" src="" alt="Menu QR Code"></div></div>'
        + '<div class="qrc-section"><div class="qrc-label">Scan to Open Admin Panel</div><div class="qrc-code-frame"><img id="qrcAdminImg" src="" alt="Admin Panel QR Code"></div></div>'
        + '</div>'
        + '<div class="qrc-stats"><h3>Scan Analytics</h3>'
        + '<div class="qrc-stat-item"><span class="qrc-stat-label">Total Scans:</span><span class="qrc-scan-count" id="qrcTotalScans">Loading...</span></div>'
        + '<div class="qrc-stat-item"><span class="qrc-stat-label">Email Milestone:</span><span>Every 100 scans</span></div>'
        + '<div class="qrc-stat-item"><span class="qrc-stat-label">Next Email:</span><span id="qrcNextEmail">Loading...</span></div>'
        + '</div>'
        + '<div class="qrc-instructions"><h3>How to Use</h3><ol>'
        + '<li><strong>Print or Display:</strong> Print this QR code or display it on your website/social media</li>'
        + '<li><strong>Customers Scan:</strong> Customers scan the QR code with their mobile camera</li>'
        + '<li><strong>View Menu:</strong> They are directed to your digital menu</li>'
        + '<li><strong>Track Engagement:</strong> Every scan is tracked automatically</li>'
        + '<li><strong>Get Notified:</strong> You receive an email every 100 scans</li>'
        + '<li><strong>Admin QR:</strong> Staff can scan the admin QR to open the admin login panel directly</li>'
        + '</ol></div>'
        + '<div class="qrc-actions">'
        + '<button class="qrc-btn qrc-btn-primary" id="qrcDlBtn">Download QR Code</button>'
        + '<button class="qrc-btn qrc-btn-secondary" id="qrcCopyMenuBtn">Copy Menu URL</button>'
        + '<button class="qrc-btn qrc-btn-primary" id="qrcDlAdminBtn">Download Admin QR</button>'
        + '<button class="qrc-btn qrc-btn-secondary" id="qrcCopyAdminBtn">Copy Admin URL</button>'
        + '<button class="qrc-btn qrc-btn-secondary" id="qrcReportBtn">View Full Report</button>'
        + '</div>'
        + '<div class="qrc-footer"><p>QR Code expires never &bull; Tracked by Dcore Systems &bull; Last Updated: <span id="qrcLastUpdate"></span></p></div>'
        + '</div></div>';

      var alertBox = container.querySelector('#qrcAlertBox');
      var totalScansEl = container.querySelector('#qrcTotalScans');
      var nextEmailEl = container.querySelector('#qrcNextEmail');
      var lastUpdateEl = container.querySelector('#qrcLastUpdate');

      function showAlert(message, type) {
        if (module._alertTimer) { clearTimeout(module._alertTimer); module._alertTimer = null; }
        alertBox.textContent = message;
        alertBox.className = 'qrc-alert ' + (type || 'info');
        module._alertTimer = setTimeout(function () { alertBox.className = 'qrc-alert'; module._alertTimer = null; }, 4000);
      }

      function generateQrCodes() {
        var cb = Date.now();
        container.querySelector('#qrcImg').src = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' + encodeURIComponent(QR_TRACK_URL) + '&v=' + cb;
        container.querySelector('#qrcAdminImg').src = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' + encodeURIComponent(ADMIN_PANEL_URL) + '&v=' + cb;
      }

      function loadScanStats() {
        if (!APPS_SCRIPT_URL) {
          totalScansEl.textContent = 'N/A';
          nextEmailEl.textContent = 'N/A';
          return;
        }
        fetch(APPS_SCRIPT_URL + '?action=qr_report', { method: 'GET', mode: 'cors', cache: 'no-store' })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (data && data.ok) {
              var total = data.totalScans || 0;
              var nextMilestone = Math.ceil((total + 1) / 100) * 100;
              totalScansEl.textContent = total.toLocaleString('en-IN');
              nextEmailEl.textContent = nextMilestone.toLocaleString('en-IN') + ' scans';
            } else {
              totalScansEl.textContent = 'N/A'; nextEmailEl.textContent = 'N/A';
            }
          })
          .catch(function () { totalScansEl.textContent = 'N/A'; nextEmailEl.textContent = 'N/A'; });
      }

      function ensureQrSheetReady() {
        if (!APPS_SCRIPT_URL) return;
        fetch(APPS_SCRIPT_URL + '?action=ensure_qr_sheet', { method: 'GET' })
          .catch(function () { /* non-critical */ });
      }

      function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
      }

      function downloadQrImage(imgId, fileName) {
        var src = container.querySelector('#' + imgId).src;
        fetch(src, { mode: 'cors' })
          .then(function (r) { if (!r.ok) throw new Error('fetch failed'); return r.blob(); })
          .then(function (blob) {
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url; a.download = fileName;
            document.body.appendChild(a); a.click(); document.body.removeChild(a);
            URL.revokeObjectURL(url);
            showAlert('QR Code downloaded successfully!', 'success');
          })
          .catch(function () {
            window.open(src, '_blank', 'noopener,noreferrer');
            showAlert('Direct download blocked by browser. QR image opened in new tab.', 'info');
          });
      }

      container.querySelector('#qrcDlBtn').addEventListener('click', function () {
        downloadQrImage('qrcImg', 'namastekalyan-menu-qr-code.png');
      });
      container.querySelector('#qrcDlAdminBtn').addEventListener('click', function () {
        downloadQrImage('qrcAdminImg', 'namastekalyan-admin-panel-qr-code.png');
      });
      container.querySelector('#qrcCopyMenuBtn').addEventListener('click', function () {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(QR_TRACK_URL)
            .then(function () { showAlert('QR target URL copied!', 'success'); })
            .catch(function () { fallbackCopy(QR_TRACK_URL); showAlert('QR target URL copied!', 'success'); });
        } else { fallbackCopy(QR_TRACK_URL); showAlert('QR target URL copied!', 'success'); }
      });
      container.querySelector('#qrcCopyAdminBtn').addEventListener('click', function () {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(ADMIN_PANEL_URL)
            .then(function () { showAlert('Admin panel URL copied!', 'success'); })
            .catch(function () { fallbackCopy(ADMIN_PANEL_URL); showAlert('Admin panel URL copied!', 'success'); });
        } else { fallbackCopy(ADMIN_PANEL_URL); showAlert('Admin panel URL copied!', 'success'); }
      });
      container.querySelector('#qrcReportBtn').addEventListener('click', function () {
        window.open('qr-report.html', 'QR_Report', 'width=1200,height=800,resizable=yes,scrollbars=yes');
      });

      // Init
      ensureQrSheetReady();
      generateQrCodes();
      loadScanStats();
      lastUpdateEl.textContent = new Date().toLocaleString('en-IN');
      module._statsInterval = setInterval(loadScanStats, 60000);
    },

    destroy: function () {
      if (this._statsInterval) { clearInterval(this._statsInterval); this._statsInterval = null; }
      if (this._alertTimer) { clearTimeout(this._alertTimer); this._alertTimer = null; }
      this._container = null;
    }
  };
})(window.NK || (window.NK = {}));
