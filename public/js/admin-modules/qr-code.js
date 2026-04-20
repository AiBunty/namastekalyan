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
    _authClient: null,

    init: function (container, authClient) {
      this._container = container;
      this._authClient = authClient;
      var module = this;

      var APPS_SCRIPT_URL = (
        (window.NK_DATA_API && window.NK_DATA_API.appsScriptUrl) ||
        window.APPS_SCRIPT_URL ||
        ''
      ).trim();
      var totalScansEl;
      var nextEmailEl;
      var lastUpdateEl;
      var registryMetaEl;
      var registryGridEl;
      var registryEmptyEl;

      container.innerHTML = '<style>'
        + '.qrc-wrap{font-family:"Segoe UI",Tahoma,Geneva,Verdana,sans-serif;padding:20px}'
        + '.qrc-box{background:#fff;border-radius:20px;box-shadow:0 4px 24px rgba(0,0,0,.12);max-width:980px;margin:0 auto;padding:40px;text-align:center}'
        + '.qrc-logo{font-size:48px;margin-bottom:15px}'
        + '.qrc-box h2{color:#333;font-size:28px;margin:0 0 10px}'
        + '.qrc-subtitle{color:#666;font-size:14px;margin:0 0 10px}'
        + '.qrc-divider{width:60px;height:3px;background:linear-gradient(90deg,#667eea,#764ba2);margin:20px auto;border-radius:2px}'
        + '.qrc-summary{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin:30px 0}'
        + '.qrc-section{background:#f8f9fa;padding:24px;border-radius:15px;text-align:left}'
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
        + '.qrc-registry{margin-top:28px;text-align:left}'
        + '.qrc-registry-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-bottom:16px}'
        + '.qrc-registry-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px}'
        + '.qrc-registry-empty{padding:18px;border-radius:12px;background:#f8f9fa;color:#666}'
        + '.qrc-card{background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:18px;padding:18px;box-shadow:0 8px 24px rgba(0,0,0,.06)}'
        + '.qrc-card-top{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}'
        + '.qrc-card-title{font-size:1.05rem;font-weight:700;color:#333;margin:0}'
        + '.qrc-card-sub{margin-top:4px;color:#7b6b5b;font-size:.85rem;word-break:break-word}'
        + '.qrc-badges{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}'
        + '.qrc-badge{padding:4px 8px;border-radius:999px;font-size:.72rem;font-weight:700}'
        + '.qrc-badge-on{background:#d9f3e1;color:#216c41}'
        + '.qrc-badge-off{background:#f9e2dd;color:#8a3a2c}'
        + '.qrc-badge-system{background:#efe4d5;color:#6d513c}'
        + '.qrc-card-body{display:grid;gap:12px;margin-top:14px}'
        + '.qrc-card-lines{display:grid;gap:8px;font-size:.9rem;color:#4f4338}'
        + '.qrc-card-lines strong{color:#2d241c}'
        + '.qrc-inline-code{display:block;margin-top:4px;padding:8px 10px;border-radius:10px;background:#2d241c;color:#f8efe4;font-family:"Courier New",monospace;font-size:.78rem;word-break:break-all}'
        + '.qrc-card-actions{display:flex;gap:8px;flex-wrap:wrap}'
        + '.qrc-card-actions .qrc-btn{flex:initial;min-width:0;padding:10px 14px;font-size:13px}'
        + '.qrc-alert{padding:15px;border-radius:8px;margin-bottom:20px;display:none;text-align:left}'
        + '.qrc-alert.success{background:#d4edda;color:#155724;border-left:4px solid #28a745;display:block}'
        + '.qrc-alert.info{background:#d1ecf1;color:#0c5460;border-left:4px solid #17a2b8;display:block}'
        + '.qrc-footer{margin-top:30px;padding-top:20px;border-top:1px solid #eee;color:#999;font-size:12px}'
        + '@media(max-width:600px){.qrc-summary{grid-template-columns:1fr}.qrc-code-frame img{width:200px;height:200px}.qrc-actions{flex-direction:column}.qrc-btn{min-width:100%}}'
        + '</style>'
        + '<div class="qrc-wrap"><div class="qrc-box">'
        + '<div class="qrc-logo">🍜</div>'
        + '<h2>QR Code Center</h2>'
        + '<p class="qrc-subtitle">View every saved QR redirect, including newly created QR targets for active pages and events.</p>'
        + '<div class="qrc-divider"></div>'
        + '<div id="qrcAlertBox" class="qrc-alert"></div>'
        + '<div class="qrc-summary">'
        + '<div class="qrc-section">'
        + '<div class="qrc-label">Registry Summary</div>'
        + '<div id="qrcRegistryMeta" class="qrc-card-lines">Loading saved QR records...</div>'
        + '</div>'
        + '<div class="qrc-section">'
        + '<div class="qrc-label">Scan Analytics</div>'
        + '<div class="qrc-card-lines">'
        + '<div><strong>Total Scans:</strong> <span id="qrcTotalScans">Loading...</span></div>'
        + '<div><strong>Next Email:</strong> <span id="qrcNextEmail">Loading...</span></div>'
        + '<div><strong>Milestone:</strong> Every 100 scans</div>'
        + '</div>'
        + '</div>'
        + '</div>'
        + '<div class="qrc-registry">'
        + '<div class="qrc-registry-head">'
        + '<div>'
        + '<h3 style="margin:0;color:#333;">Saved QR Registry</h3>'
        + '<p style="margin:6px 0 0;color:#666;font-size:14px;">Every QR saved in Landing Routing appears here automatically.</p>'
        + '</div>'
        + '<div class="qrc-actions" style="margin-top:0;">'
        + '<button class="qrc-btn qrc-btn-secondary" id="qrcReloadBtn">Reload Registry</button>'
        + '<button class="qrc-btn qrc-btn-secondary" id="qrcReportBtn">View Full Report</button>'
        + '</div>'
        + '</div>'
        + '<div id="qrcRegistryEmpty" class="qrc-registry-empty">Loading saved QR records...</div>'
        + '<div id="qrcRegistryGrid" class="qrc-registry-grid" hidden></div>'
        + '</div>'
        + '<div class="qrc-footer"><p>QR Code expires never &bull; Tracked by Dcore Systems &bull; Last Updated: <span id="qrcLastUpdate"></span></p></div>'
        + '</div></div>';

      var alertBox = container.querySelector('#qrcAlertBox');
      totalScansEl = container.querySelector('#qrcTotalScans');
      nextEmailEl = container.querySelector('#qrcNextEmail');
      lastUpdateEl = container.querySelector('#qrcLastUpdate');
      registryMetaEl = container.querySelector('#qrcRegistryMeta');
      registryGridEl = container.querySelector('#qrcRegistryGrid');
      registryEmptyEl = container.querySelector('#qrcRegistryEmpty');

      function showAlert(message, type) {
        if (module._alertTimer) { clearTimeout(module._alertTimer); module._alertTimer = null; }
        alertBox.textContent = message;
        alertBox.className = 'qrc-alert ' + (type || 'info');
        module._alertTimer = setTimeout(function () { alertBox.className = 'qrc-alert'; module._alertTimer = null; }, 4000);
      }

      function escapeHtml(value) {
        return String(value || '')
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#39;');
      }

      function setRegistrySummary(items) {
        var activeCount = items.filter(function (item) { return item.isActive; }).length;
        var systemCount = items.filter(function (item) { return item.isSystem; }).length;
        registryMetaEl.innerHTML = ''
          + '<div><strong>Total QR Records:</strong> ' + String(items.length) + '</div>'
          + '<div><strong>Active:</strong> ' + String(activeCount) + '</div>'
          + '<div><strong>System QR:</strong> ' + String(systemCount) + '</div>';
      }

      function renderRegistry(items) {
        setRegistrySummary(items);
        if (!items.length) {
          registryEmptyEl.hidden = false;
          registryGridEl.hidden = true;
          registryEmptyEl.textContent = 'No QR redirects saved yet. Create one in Landing Routing and it will appear here.';
          registryGridEl.innerHTML = '';
          return;
        }

        registryEmptyEl.hidden = true;
        registryGridEl.hidden = false;
        registryGridEl.innerHTML = items.map(function (item, index) {
          var qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' + encodeURIComponent(item.publicUrl || '') + '&v=' + String(Date.now() + index);
          return ''
            + '<article class="qrc-card">'
            + '  <div class="qrc-card-top">'
            + '    <div>'
            + '      <h4 class="qrc-card-title">' + escapeHtml(item.name || 'Untitled QR') + '</h4>'
            + '      <div class="qrc-card-sub">/qr/' + escapeHtml(item.slug || '') + '</div>'
            + '    </div>'
            + '    <div class="qrc-badges">'
            + '      ' + (item.isSystem ? '<span class="qrc-badge qrc-badge-system">System</span>' : '')
            + '      <span class="qrc-badge ' + (item.isActive ? 'qrc-badge-on' : 'qrc-badge-off') + '">' + (item.isActive ? 'Active' : 'Inactive') + '</span>'
            + '    </div>'
            + '  </div>'
            + '  <div class="qrc-card-body">'
            + '    <div class="qrc-code-frame"><img id="qrcDynamicImg' + String(index) + '" src="' + escapeHtml(qrImageUrl) + '" alt="QR for ' + escapeHtml(item.name || 'QR') + '"></div>'
            + '    <div class="qrc-card-lines">'
            + '      <div><strong>Target:</strong> ' + escapeHtml(item.destinationLabel || '-') + '</div>'
            + '      <div><strong>Public URL:</strong><span class="qrc-inline-code">' + escapeHtml(item.publicUrl || '-') + '</span></div>'
            + '      <div><strong>Resolves To:</strong><span class="qrc-inline-code">' + escapeHtml(item.resolvedUrl || '-') + '</span></div>'
            + '    </div>'
            + '    <div class="qrc-card-actions">'
            + '      <button class="qrc-btn qrc-btn-primary" type="button" data-copy-url="' + escapeHtml(item.publicUrl || '') + '">Copy URL</button>'
            + '      <button class="qrc-btn qrc-btn-secondary" type="button" data-open-url="' + escapeHtml(item.publicUrl || '') + '">Open</button>'
            + '      <button class="qrc-btn qrc-btn-secondary" type="button" data-download-img="qrcDynamicImg' + String(index) + '" data-download-name="' + escapeHtml((item.slug || ('qr-' + String(index + 1))) + '.png') + '">Download QR</button>'
            + '    </div>'
            + '  </div>'
            + '</article>';
        }).join('');

        Array.prototype.forEach.call(registryGridEl.querySelectorAll('[data-copy-url]'), function (button) {
          button.addEventListener('click', function () {
            var url = String(button.getAttribute('data-copy-url') || '');
            if (!url) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
              navigator.clipboard.writeText(url)
                .then(function () { showAlert('QR URL copied!', 'success'); })
                .catch(function () { fallbackCopy(url); showAlert('QR URL copied!', 'success'); });
            } else {
              fallbackCopy(url);
              showAlert('QR URL copied!', 'success');
            }
          });
        });

        Array.prototype.forEach.call(registryGridEl.querySelectorAll('[data-open-url]'), function (button) {
          button.addEventListener('click', function () {
            var url = String(button.getAttribute('data-open-url') || '');
            if (!url) return;
            window.open(url, '_blank', 'noopener,noreferrer');
          });
        });

        Array.prototype.forEach.call(registryGridEl.querySelectorAll('[data-download-img]'), function (button) {
          button.addEventListener('click', function () {
            downloadQrImage(button.getAttribute('data-download-img'), button.getAttribute('data-download-name') || 'namastekalyan-qr.png');
          });
        });
      }

      function loadRegistry() {
        if (!authClient || typeof authClient.apiPost !== 'function') {
          registryEmptyEl.hidden = false;
          registryGridEl.hidden = true;
          registryEmptyEl.textContent = 'QR registry is unavailable because the admin API client is not ready.';
          return Promise.resolve();
        }

        registryEmptyEl.hidden = false;
        registryGridEl.hidden = true;
        registryEmptyEl.textContent = 'Loading saved QR records...';
        return authClient.apiPost({ action: 'auth_list_qr_redirects' })
          .then(function (data) {
            renderRegistry((data && data.items) ? data.items : []);
          })
          .catch(function (err) {
            registryEmptyEl.hidden = false;
            registryGridEl.hidden = true;
            registryEmptyEl.textContent = 'Failed to load saved QR records.';
            showAlert('Unable to load QR registry: ' + err.message, 'info');
          });
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

      container.querySelector('#qrcReloadBtn').addEventListener('click', function () {
        loadRegistry();
      });
      container.querySelector('#qrcReportBtn').addEventListener('click', function () {
        window.open(new URL('../qr/report.html', window.location.href).href, 'QR_Report', 'width=1200,height=800,resizable=yes,scrollbars=yes');
      });

      // Init
      ensureQrSheetReady();
      loadRegistry();
      loadScanStats();
      lastUpdateEl.textContent = new Date().toLocaleString('en-IN');
      module._statsInterval = setInterval(loadScanStats, 60000);
    },

    destroy: function () {
      if (this._statsInterval) { clearInterval(this._statsInterval); this._statsInterval = null; }
      if (this._alertTimer) { clearTimeout(this._alertTimer); this._alertTimer = null; }
      this._container = null;
      this._authClient = null;
    }
  };
})(window.NK || (window.NK = {}));
