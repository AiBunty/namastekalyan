/**
 * NK Admin SPA Module: Passcode / WhatsApp Settings
 * Source: admin_settings.html
 */
(function (NK) {
  'use strict';
  NK.MODULES = NK.MODULES || {};
  NK.MODULES['settings'] = {
    _container: null,
    _authClient: null,
    _alertTimer: null,

    init: function (container, authClient) {
      this._container = container;
      this._authClient = authClient;
      this._alertTimer = null;
      var module = this;

      container.innerHTML = '<style>'
        + '.stg-container{max-width:600px;margin:2rem auto;padding:0 1rem}'
        + '.stg-header{text-align:center;margin-bottom:2rem}'
        + '.stg-header h2{font-size:1.8rem;color:#333;margin:0 0 .5rem}'
        + '.stg-subtitle{color:#666;font-size:.95rem;margin:0}'
        + '.stg-alert{padding:1rem;margin-bottom:1.5rem;border-radius:8px;display:flex;justify-content:space-between;align-items:center;}'
        + '.stg-alert.success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}'
        + '.stg-alert.error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}'
        + '.stg-alert.info{background:#d1ecf1;color:#0c5460;border:1px solid #bee5eb}'
        + '.stg-alert .stg-close-btn{background:none;border:none;font-size:1.5rem;cursor:pointer;color:inherit;padding:0;line-height:1}'
        + '.stg-form{background:#fff;padding:2rem;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.1)}'
        + '.stg-form-group{margin-bottom:1.8rem}'
        + '.stg-label{display:block;font-weight:600;color:#333;margin-bottom:.6rem;font-size:1rem}'
        + '.stg-input-wrap{display:flex;flex-direction:column}'
        + '.stg-input-wrap input{padding:.75rem;border:2px solid #e0e0e0;border-radius:6px;font-size:1rem;transition:all .2s ease;font-family:inherit}'
        + '.stg-input-wrap input:focus{outline:none;border-color:#ff6b35;box-shadow:0 0 0 3px rgba(255,107,53,.1)}'
        + '.stg-input-hint{font-size:.85rem;color:#666;margin-top:.4rem}'
        + '.stg-info-box{display:flex;align-items:flex-start;padding:1rem;background:#f8f9fa;border-left:4px solid #17a2b8;border-radius:6px;gap:.8rem}'
        + '.stg-info-title{font-size:.9rem;color:#666;margin:0 0 .3rem;font-weight:600}'
        + '.stg-info-val{font-size:.95rem;color:#333;margin:0;font-family:"Courier New",monospace}'
        + '.stg-form-actions{display:flex;gap:1rem;margin-top:2rem}'
        + '.stg-btn{flex:1;padding:.85rem 1.5rem;border:none;border-radius:6px;font-size:1rem;font-weight:600;cursor:pointer;transition:all .2s ease;display:flex;align-items:center;justify-content:center;gap:.5rem;font-family:inherit}'
        + '.stg-btn-primary{background:#ff6b35;color:#fff}'
        + '.stg-btn-primary:hover{background:#e55a2a;box-shadow:0 4px 12px rgba(255,107,53,.3)}'
        + '.stg-btn-primary:disabled{background:#ccc;cursor:not-allowed;box-shadow:none}'
        + '.stg-btn-secondary{background:#e0e0e0;color:#333}'
        + '.stg-btn-secondary:hover{background:#d0d0d0}'
        + '@media(max-width:600px){.stg-form-actions{flex-direction:column}.stg-btn{width:100%}}'
        + '</style>'
        + '<div class="stg-container">'
        + '<div class="stg-header"><h2>Passcode / WhatsApp Settings</h2><p class="stg-subtitle">Manage WhatsApp number, menu blocker passcode, and event entry passcode</p></div>'
        + '<div id="stgAlert" class="stg-alert" style="display:none;"><span id="stgAlertMsg"></span><button type="button" class="stg-close-btn" id="stgCloseAlert">&times;</button></div>'
        + '<form id="stgForm" class="stg-form">'
        + '<div class="stg-form-group">'
        + '<label for="stgWhatsappNo" class="stg-label">Hotel WhatsApp Number</label>'
        + '<div class="stg-input-wrap">'
        + '<input type="tel" id="stgWhatsappNo" name="hotelWhatsappNo" placeholder="e.g., 91 9371 519 999" pattern="[0-9\\s\\-\\+]+" required>'
        + '<span class="stg-input-hint">Include country code (e.g., 91 for India)</span>'
        + '</div></div>'
        + '<div class="stg-form-group">'
        + '<label for="stgStaffCode" class="stg-label">Menu Blocker Staff Code</label>'
        + '<div class="stg-input-wrap">'
        + '<input type="text" id="stgStaffCode" name="menuBlockerStaffCode" placeholder="e.g., NKSTAFF2026" pattern="[A-Za-z0-9]{4,}" minlength="4" required>'
        + '<span class="stg-input-hint">Minimum 4 characters. Used by staff to bypass menu blocker.</span>'
        + '</div></div>'
        + '<div class="stg-form-group">'
        + '<label for="stgEventEntryPasscode" class="stg-label">Event Entry Passcode</label>'
        + '<div class="stg-input-wrap">'
        + '<input type="text" id="stgEventEntryPasscode" name="eventEntryPasscode" placeholder="e.g., GATE2026" pattern="[A-Za-z0-9]{4,}" minlength="4" required>'
        + '<span class="stg-input-hint">Minimum 4 characters. Staff enter this on the public event verification page before check-in.</span>'
        + '</div></div>'
        + '<div class="stg-form-group">'
        + '<div class="stg-info-box"><div><p class="stg-info-title">Last Updated</p><p id="stgLastUpdated" class="stg-info-val">Loading...</p></div></div>'
        + '</div>'
        + '<div class="stg-form-actions">'
        + '<button type="submit" id="stgSaveBtn" class="stg-btn stg-btn-primary">Save Settings</button>'
        + '<button type="reset" id="stgResetBtn" class="stg-btn stg-btn-secondary">Reset</button>'
        + '</div>'
        + '</form></div>';

      var alertEl = container.querySelector('#stgAlert');
      var alertMsg = container.querySelector('#stgAlertMsg');
      var closeAlertBtn = container.querySelector('#stgCloseAlert');
      var form = container.querySelector('#stgForm');
      var saveBtn = container.querySelector('#stgSaveBtn');
      var whatsappInput = container.querySelector('#stgWhatsappNo');
      var staffCodeInput = container.querySelector('#stgStaffCode');
      var eventEntryPasscodeInput = container.querySelector('#stgEventEntryPasscode');
      var lastUpdatedEl = container.querySelector('#stgLastUpdated');

      function showAlert(message, type) {
        if (module._alertTimer) { clearTimeout(module._alertTimer); module._alertTimer = null; }
        alertMsg.textContent = message;
        alertEl.className = 'stg-alert ' + (type || 'info');
        alertEl.style.display = 'flex';
        if (type === 'success') {
          module._alertTimer = setTimeout(function () { alertEl.style.display = 'none'; module._alertTimer = null; }, 4000);
        }
      }

      function closeAlert() {
        if (module._alertTimer) { clearTimeout(module._alertTimer); module._alertTimer = null; }
        alertEl.style.display = 'none';
      }

      closeAlertBtn.addEventListener('click', closeAlert);

      function loadSettings() {
        authClient.apiPost({ action: 'auth_get_app_settings' })
          .then(function (data) {
            var settings = data && data.settings ? data.settings : {};
            whatsappInput.value = settings.hotelWhatsappNo || '';
            staffCodeInput.value = settings.menuBlockerStaffCode || '';
            eventEntryPasscodeInput.value = settings.eventEntryPasscode || settings.menuBlockerStaffCode || '';
            if (data && data.updatedAt) {
              lastUpdatedEl.textContent = new Date(data.updatedAt).toLocaleString();
            } else {
              lastUpdatedEl.textContent = '-';
            }
          })
          .catch(function (err) {
            showAlert('Failed to load settings: ' + err.message, 'error');
            lastUpdatedEl.textContent = 'Error loading';
          });
      }

      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var whatsappNo = String(whatsappInput.value || '').trim();
        var staffCode = String(staffCodeInput.value || '').trim();
        var eventEntryPasscode = String(eventEntryPasscodeInput.value || '').trim();
        if (!whatsappNo || !staffCode || !eventEntryPasscode) { showAlert('Please fill in all fields.', 'error'); return; }
        saveBtn.disabled = true;

        authClient.apiPost({
          action: 'auth_set_app_settings',
          settings: { hotelWhatsappNo: whatsappNo, menuBlockerStaffCode: staffCode, eventEntryPasscode: eventEntryPasscode }
        })
          .then(function (data) {
            saveBtn.disabled = false;
            if (data && data.ok) {
              showAlert('Settings saved successfully!', 'success');
              if (data.updatedAt) {
                lastUpdatedEl.textContent = new Date(data.updatedAt).toLocaleString();
              }
              staffCodeInput.value = data && data.settings ? (data.settings.menuBlockerStaffCode || staffCode) : staffCode;
              eventEntryPasscodeInput.value = data && data.settings ? (data.settings.eventEntryPasscode || eventEntryPasscode) : eventEntryPasscode;
              if (window.MenuBlockerInitReload) {
                window.MenuBlockerInitReload().catch(function (error) {
                  console.warn('Menu blocker settings reload failed:', error);
                });
              } else if (window.MenuBlockerInitClearCache) {
                window.MenuBlockerInitClearCache();
              }
            } else {
              showAlert((data && (data.message || data.error)) || 'Failed to save settings.', 'error');
            }
          })
          .catch(function (err) {
            saveBtn.disabled = false;
            showAlert('Failed to save settings: ' + err.message, 'error');
          });
      });

      loadSettings();
    },

    destroy: function () {
      if (this._alertTimer) { clearTimeout(this._alertTimer); this._alertTimer = null; }
      this._container = null;
      this._authClient = null;
    }
  };
})(window.NK || (window.NK = {}));
