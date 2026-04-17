// admin-modules/user-management.js
// SPA Module: User Management (prefix: usm) - SuperAdmin only
(function (NK) {
  NK.MODULES = NK.MODULES || {};

  var PERMISSION_OPTIONS = [
    { key: 'dashboard',       label: 'Dashboard' },
    { key: 'cashier',         label: 'Cashier' },
    { key: 'verification',    label: 'Verification' },
    { key: 'eventGuests',     label: 'Event Guests' },
    { key: 'eventScanner',    label: 'Event Scanner' },
    { key: 'eventManagement', label: 'Event Management' },
    { key: 'menuEditor',      label: 'Menu Editor' },
    { key: 'cashApprovals',   label: 'Cash Approvals' },
    { key: 'userManagement',  label: 'User Management' }
  ];

  var DEFAULT_ADMIN_PERMISSIONS = [
    'dashboard', 'cashier', 'verification', 'eventGuests',
    'eventScanner', 'eventManagement', 'menuEditor'
  ];

  var SETTING_FIELDS = [
    { key: 'RAZORPAY_KEY_ID',           label: 'Razorpay Key ID',           allowPlainText: true  },
    { key: 'RAZORPAY_KEY_SECRET',        label: 'Razorpay Key Secret',        allowPlainText: false },
    { key: 'RAZORPAY_WEBHOOK_SECRET',    label: 'Razorpay Webhook Secret',    allowPlainText: false },
    { key: 'RAZORPAY_WEBHOOK_TOKEN',     label: 'Razorpay Webhook Token',     allowPlainText: false },
    { key: 'CRM_API_TOKEN',              label: 'CRM API Token',              allowPlainText: false },
    { key: 'EVENT_QR_SIGNING_SECRET',    label: 'Event QR Signing Secret',    allowPlainText: false }
  ];

  NK.MODULES['user-management'] = {
    _container: null,
    _authClient: null,
    _usersCache: [],
    _selectedPermUsername: null,

    init: function (container, authClient, user) {
      this._container = container;
      this._authClient = authClient;
      this._usersCache = [];
      this._selectedPermUsername = null;

      var module = this;

      // SuperAdmin only check
      if (!user || user.role !== 'superadmin') {
        container.innerHTML = '<div style="padding:24px;color:#7a342b;font-weight:600;">Access denied. SuperAdmin only.</div>';
        return;
      }

      container.innerHTML = module._buildHtml();
      module._injectStyles();
      module._buildPermissionCheckboxes(container.querySelector('#usmNewUserPermissions'), 'new-user-perm');
      module._buildPermissionCheckboxes(container.querySelector('#usmEditUserPermissions'), 'edit-user-perm');
      module._setPermissionSelection(container.querySelector('#usmNewUserPermissions'), DEFAULT_ADMIN_PERMISSIONS);
      module._syncNewUserRolePermissionsState();
      module._bindEvents();
      module._loadUsers();
      module._loadApiSettings();
    },

    destroy: function () {
      this._container = null;
      this._authClient = null;
      this._usersCache = [];
      this._selectedPermUsername = null;
    },

    // ── Permission grid helpers ───────────────────────────────────────────────

    _buildPermissionCheckboxes: function (container, prefix) {
      if (!container) return;
      var html = '<div class="usm-permissions-grid">';
      PERMISSION_OPTIONS.forEach(function (opt) {
        html += '<label class="usm-perm-label">'
          + '<input type="checkbox" data-perm="' + opt.key + '" id="' + prefix + '-' + opt.key + '"> '
          + opt.label + '</label>';
      });
      html += '</div>';
      container.innerHTML = html;
    },

    _setPermissionSelection: function (container, permissions) {
      if (!container) return;
      var keys;
      if (Array.isArray(permissions)) {
        keys = permissions;
      } else if (permissions && typeof permissions === 'object') {
        keys = Object.keys(permissions).filter(function (k) { return !!permissions[k]; });
      } else {
        keys = [];
      }
      container.querySelectorAll('input[data-perm]').forEach(function (cb) {
        cb.checked = keys.indexOf(cb.getAttribute('data-perm')) !== -1;
      });
    },

    _getSelectedPermissions: function (container) {
      if (!container) return [];
      var result = [];
      container.querySelectorAll('input[data-perm]:checked').forEach(function (cb) {
        result.push(cb.getAttribute('data-perm'));
      });
      return result;
    },

    _setPermissionGridDisabled: function (container, disabled) {
      if (!container) return;
      container.querySelectorAll('input[data-perm]').forEach(function (cb) {
        cb.disabled = !!disabled;
      });
    },

    _syncNewUserRolePermissionsState: function () {
      var c = this._container;
      if (!c) return;
      var roleEl = c.querySelector('#usmNewUserRole');
      var gridEl = c.querySelector('#usmNewUserPermissions');
      if (!roleEl || !gridEl) return;
      if (roleEl.value === 'superadmin') {
        this._setPermissionSelection(gridEl, PERMISSION_OPTIONS.map(function (o) { return o.key; }));
        this._setPermissionGridDisabled(gridEl, true);
      } else {
        this._setPermissionGridDisabled(gridEl, false);
        var current = this._getSelectedPermissions(gridEl);
        if (!current.length) {
          this._setPermissionSelection(gridEl, DEFAULT_ADMIN_PERMISSIONS);
        }
      }
    },

    _setEditPermissionsForUser: function (user) {
      var c = this._container;
      if (!c) return;
      var gridEl = c.querySelector('#usmEditUserPermissions');
      var saveBtn = c.querySelector('#usmSavePermBtn');
      if (!gridEl) return;

      if (user.role === 'superadmin') {
        this._setPermissionSelection(gridEl, PERMISSION_OPTIONS.map(function (o) { return o.key; }));
        this._setPermissionGridDisabled(gridEl, true);
        if (saveBtn) saveBtn.disabled = true;
      } else {
        this._setPermissionGridDisabled(gridEl, false);
        this._setPermissionSelection(gridEl, user.permissions || DEFAULT_ADMIN_PERMISSIONS);
        if (saveBtn) saveBtn.disabled = false;
      }
    },

    // ── Data ──────────────────────────────────────────────────────────────────

    _loadUsers: async function () {
      var module = this;
      var c = module._container;
      if (!c) return;
      var statusEl = c.querySelector('#usmStatus');
      if (statusEl) statusEl.textContent = 'Loading users...';
      try {
        var payload = await module._authClient.apiPost({ action: 'auth_list_users' });
        module._usersCache = Array.isArray(payload.users) ? payload.users : [];
        module._renderUsers(module._usersCache);
        if (statusEl) statusEl.textContent = 'Loaded ' + module._usersCache.length + ' user(s).';
      } catch (err) {
        if (statusEl) statusEl.textContent = 'Failed to load users: ' + (err.message || err);
      }
    },

    _createUser: async function () {
      var module = this;
      var c = module._container;
      if (!c) return;
      var statusEl = c.querySelector('#usmStatus');

      var username = (c.querySelector('#usmNewUsername') || {}).value || '';
      var displayName = (c.querySelector('#usmNewDisplayName') || {}).value || '';
      var role = (c.querySelector('#usmNewUserRole') || {}).value || 'admin';
      var password = (c.querySelector('#usmNewPassword') || {}).value || '';
      var permissions = module._getSelectedPermissions(c.querySelector('#usmNewUserPermissions'));

      if (!username || !password) {
        if (statusEl) statusEl.textContent = 'Username and password are required.';
        return;
      }

      var createBtn = c.querySelector('#usmCreateUserBtn');
      if (createBtn) createBtn.disabled = true;
      if (statusEl) statusEl.textContent = 'Creating user...';
      try {
        await module._authClient.apiPost({
          action: 'auth_create_user',
          username: username,
          displayName: displayName,
          role: role,
          password: password,
          permissions: permissions
        });
        // Clear form
        var clearIds = ['usmNewUsername', 'usmNewDisplayName', 'usmNewPassword'];
        clearIds.forEach(function (id) { var el = c.querySelector('#' + id); if (el) el.value = ''; });
        if (statusEl) statusEl.textContent = 'User created: ' + username;
        await module._loadUsers();
      } catch (err) {
        if (statusEl) statusEl.textContent = 'Create failed: ' + (err.message || err);
      } finally {
        if (createBtn) createBtn.disabled = false;
      }
    },

    _toggleUserStatus: async function (username, nextStatus) {
      var module = this;
      var c = module._container;
      if (!c) return;
      var statusEl = c.querySelector('#usmStatus');
      if (statusEl) statusEl.textContent = 'Updating user status...';
      try {
        await module._authClient.apiPost({ action: 'auth_set_user_status', username: username, status: nextStatus });
        if (statusEl) statusEl.textContent = username + ' is now ' + nextStatus;
        await module._loadUsers();
      } catch (err) {
        if (statusEl) statusEl.textContent = 'Status update failed: ' + (err.message || err);
      }
    },

    _resetUserPassword: async function (username) {
      var module = this;
      var c = module._container;
      if (!c) return;
      var statusEl = c.querySelector('#usmStatus');
      var newPassword = window.prompt('Enter a temporary password for ' + username);
      if (!newPassword) {
        if (statusEl) statusEl.textContent = 'Password reset cancelled.';
        return;
      }
      if (statusEl) statusEl.textContent = 'Resetting password...';
      try {
        await module._authClient.apiPost({ action: 'auth_reset_password', username: username, newPassword: newPassword });
        if (statusEl) statusEl.textContent = 'Password reset for ' + username;
      } catch (err) {
        if (statusEl) statusEl.textContent = 'Reset failed: ' + (err.message || err);
      }
    },

    _saveUserPermissions: async function () {
      var module = this;
      var c = module._container;
      if (!c || !module._selectedPermUsername) return;
      var statusEl = c.querySelector('#usmStatus');
      var permissions = module._getSelectedPermissions(c.querySelector('#usmEditUserPermissions'));
      if (statusEl) statusEl.textContent = 'Saving permissions...';
      try {
        await module._authClient.apiPost({
          action: 'auth_set_user_permissions',
          username: module._selectedPermUsername,
          permissions: permissions
        });
        if (statusEl) statusEl.textContent = 'Permissions saved for ' + module._selectedPermUsername;
        await module._loadUsers();
      } catch (err) {
        if (statusEl) statusEl.textContent = 'Save permissions failed: ' + (err.message || err);
      }
    },

    // ── API Settings ──────────────────────────────────────────────────────────

    _loadApiSettings: async function () {
      var module = this;
      var c = module._container;
      if (!c) return;

      // Check AdminCache first
      if (window.AdminCache && typeof window.AdminCache.get === 'function') {
        var cached = window.AdminCache.get('admin-user-management', 'api_settings');
        if (cached) {
          module._renderApiSettings(cached);
          return;
        }
      }

      try {
        var payload = await module._authClient.apiPost({ action: 'auth_get_api_settings' });
        if (window.AdminCache && typeof window.AdminCache.set === 'function') {
          window.AdminCache.set('admin-user-management', 'api_settings', payload);
        }
        module._renderApiSettings(payload);
      } catch (err) {
        var statusEl = c.querySelector('#usmApiSettingsStatus');
        if (statusEl) statusEl.textContent = 'Failed to load API settings: ' + (err.message || err);
      }
    },

    _renderApiSettings: function (payload) {
      var c = this._container;
      if (!c) return;
      var listEl = c.querySelector('#usmApiSettingsStatusList');
      if (!listEl) return;
      var settings = (payload && payload.settings) ? payload.settings : {};
      var esc = this._esc.bind(this);
      listEl.innerHTML = SETTING_FIELDS.map(function (field) {
        var isSet = !!(settings[field.key]);
        return '<div class="usm-api-settings-status-item">'
          + '<span class="usm-state ' + (isSet ? 'is-set' : 'is-not-set') + '">' + (isSet ? 'Set' : 'Not Set') + '</span> '
          + esc(field.label)
          + '</div>';
      }).join('');
    },

    _saveApiSettings: async function () {
      var module = this;
      var c = module._container;
      if (!c) return;
      var statusEl = c.querySelector('#usmApiSettingsStatus');
      var saveBtn = c.querySelector('#usmSaveApiSettingsBtn');
      if (saveBtn) saveBtn.disabled = true;
      if (statusEl) statusEl.textContent = 'Saving API settings...';

      var newSettings = {};
      SETTING_FIELDS.forEach(function (field) {
        var el = c.querySelector('#usmApiSetting_' + field.key);
        if (el && el.value) newSettings[field.key] = el.value;
      });

      try {
        await module._authClient.apiPost({ action: 'auth_set_api_settings', settings: newSettings });
        // Clear cache
        if (window.AdminCache && typeof window.AdminCache.clear === 'function') {
          window.AdminCache.clear('admin-user-management', 'api_settings');
        }
        // Clear inputs
        SETTING_FIELDS.forEach(function (field) {
          var el = c.querySelector('#usmApiSetting_' + field.key);
          if (el) el.value = '';
        });
        if (statusEl) statusEl.textContent = 'API settings saved.';
        await module._loadApiSettings();
      } catch (err) {
        if (statusEl) statusEl.textContent = 'Save failed: ' + (err.message || err);
      } finally {
        if (saveBtn) saveBtn.disabled = false;
      }
    },

    // ── Change own password ───────────────────────────────────────────────────

    _changeOwnPassword: async function () {
      var module = this;
      var c = module._container;
      if (!c) return;
      var statusEl = c.querySelector('#usmPwdStatus');

      var currentPwd = (c.querySelector('#usmCurrentPassword') || {}).value || '';
      var newPwd = (c.querySelector('#usmNewOwnPassword') || {}).value || '';
      var confirmPwd = (c.querySelector('#usmConfirmOwnPassword') || {}).value || '';

      if (!currentPwd || !newPwd || !confirmPwd) {
        if (statusEl) statusEl.textContent = 'All three password fields are required.';
        return;
      }
      if (newPwd !== confirmPwd) {
        if (statusEl) statusEl.textContent = 'New passwords do not match.';
        return;
      }

      var btn = c.querySelector('#usmChangePwdBtn');
      if (btn) btn.disabled = true;
      if (statusEl) statusEl.textContent = 'Changing password...';
      try {
        await module._authClient.apiPost({
          action: 'auth_change_password',
          currentPassword: currentPwd,
          newPassword: newPwd
        });
        ['usmCurrentPassword', 'usmNewOwnPassword', 'usmConfirmOwnPassword'].forEach(function (id) {
          var el = c.querySelector('#' + id); if (el) el.value = '';
        });
        if (statusEl) statusEl.textContent = 'Password changed successfully.';
      } catch (err) {
        if (statusEl) statusEl.textContent = 'Change failed: ' + (err.message || err);
      } finally {
        if (btn) btn.disabled = false;
      }
    },

    // ── Rendering ─────────────────────────────────────────────────────────────

    _renderUsers: function (users) {
      var module = this;
      var c = module._container;
      if (!c) return;
      var tbody = c.querySelector('#usmUsersRows');
      if (!tbody) return;
      var esc = module._esc.bind(module);

      if (!users.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="usm-muted">No users found.</td></tr>';
        return;
      }

      tbody.innerHTML = users.map(function (user) {
        var isActive = user.status !== 'inactive';
        var statusPill = '<span class="usm-pill ' + (isActive ? 'usm-active' : 'usm-inactive') + '">' + esc(user.status || 'active') + '</span>';
        return '<tr>'
          + '<td><span class="usm-user-name">' + esc(user.username || '') + '</span>'
          + (user.displayName ? '<div class="usm-muted">' + esc(user.displayName) + '</div>' : '')
          + '</td>'
          + '<td>' + esc(user.role || '') + '</td>'
          + '<td>' + statusPill + '</td>'
          + '<td>' + esc((Array.isArray(user.permissions) ? user.permissions : Object.keys(user.permissions || {})).join(', ') || 'All') + '</td>'
          + '<td>'
          + '<button class="usm-btn usm-btn-sm usm-btn-sec" data-action="permissions" data-username="' + esc(user.username) + '">Permissions</button> '
          + '<button class="usm-btn usm-btn-sm usm-btn-sec" data-action="reset" data-username="' + esc(user.username) + '">Reset Pwd</button> '
          + '<button class="usm-btn usm-btn-sm usm-btn-sec" data-action="toggle" data-username="' + esc(user.username) + '" data-status="' + esc(user.status || 'active') + '">'
          + (isActive ? 'Deactivate' : 'Activate') + '</button> '
          + '<button class="usm-btn usm-btn-sm usm-btn-danger" data-action="delete" data-username="' + esc(user.username) + '">Delete</button>'
          + '</td>'
          + '</tr>';
      }).join('');

      // Re-apply edit form if someone was selected
      if (module._selectedPermUsername) {
        var selectedUser = users.find(function (u) { return u.username === module._selectedPermUsername; });
        if (selectedUser) module._setEditPermissionsForUser(selectedUser);
      }
    },

    _esc: function (v) { return NK.MODULE_BASE.escHtml(v); },

    _deleteUser: async function (username) {
      var module = this;
      if (!username) return;
      var confirmed = window.prompt(
        'Permanently delete user "' + username + '"?\nType  DELETE  (all caps) to confirm.'
      );
      if (confirmed !== 'DELETE') {
        if (confirmed !== null) window.alert('Cancelled — you must type DELETE exactly.');
        return;
      }
      try {
        await module._authClient.apiPost({ action: 'auth_delete_user', username: username });
        await module._loadUsers();
      } catch (err) {
        window.alert('Delete failed: ' + (err.message || err));
      }
    },

    // ── Events ────────────────────────────────────────────────────────────────

    _bindEvents: function () {
      var module = this;
      var c = module._container;

      // Table delegation
      var tbody = c.querySelector('#usmUsersRows');
      if (tbody) {
        tbody.addEventListener('click', function (e) {
          var btn = e.target.closest('[data-action]');
          if (!btn) return;
          var action = btn.getAttribute('data-action');
          var username = btn.getAttribute('data-username');
          if (action === 'toggle') {
            var currentStatus = btn.getAttribute('data-status');
            var next = currentStatus === 'active' ? 'inactive' : 'active';
            module._toggleUserStatus(username, next);
          } else if (action === 'reset') {
            module._resetUserPassword(username);
          } else if (action === 'permissions') {
            module._selectedPermUsername = username;
            var found = module._usersCache.find(function (u) { return u.username === username; });
            if (found) module._setEditPermissionsForUser(found);
            var editSection = c.querySelector('#usmEditPermSection');
            if (editSection) {
              editSection.style.display = '';
              var titleEl = c.querySelector('#usmEditPermTitle');
              if (titleEl) titleEl.textContent = 'Edit Permissions: ' + username;
            }
          } else if (action === 'delete') {
            module._deleteUser(username);
          }
        });
      }

      // Create user
      var createBtn = c.querySelector('#usmCreateUserBtn');
      if (createBtn) createBtn.addEventListener('click', function () { module._createUser(); });

      // New user role → sync checkboxes
      var newRoleEl = c.querySelector('#usmNewUserRole');
      if (newRoleEl) newRoleEl.addEventListener('change', function () { module._syncNewUserRolePermissionsState(); });

      // Save permissions
      var savePermBtn = c.querySelector('#usmSavePermBtn');
      if (savePermBtn) savePermBtn.addEventListener('click', function () { module._saveUserPermissions(); });

      // Save API settings
      var saveApiBtn = c.querySelector('#usmSaveApiSettingsBtn');
      if (saveApiBtn) saveApiBtn.addEventListener('click', function () { module._saveApiSettings(); });

      // Change own password
      var pwdBtn = c.querySelector('#usmChangePwdBtn');
      if (pwdBtn) pwdBtn.addEventListener('click', function () { module._changeOwnPassword(); });

      // Reload
      var reloadBtn = c.querySelector('#usmReloadBtn');
      if (reloadBtn) reloadBtn.addEventListener('click', function () { module._loadUsers(); });
    },

    // ── HTML ──────────────────────────────────────────────────────────────────

    _buildHtml: function () {
      var settingInputsHtml = SETTING_FIELDS.map(function (field) {
        var inputType = field.allowPlainText ? 'text' : 'password';
        return '<label class="usm-label">' + field.label
          + '<input id="usmApiSetting_' + field.key + '" class="usm-input" type="' + inputType + '" placeholder="Leave blank to keep existing">'
          + '</label>';
      }).join('');

      return [
        '<div class="usm-wrap">',

        '<div class="usm-header-row">',
        '  <h2 class="usm-heading">User Management</h2>',
        '  <button class="usm-btn usm-btn-sec" id="usmReloadBtn">Reload Users</button>',
        '</div>',
        '<div id="usmStatus" class="usm-status usm-muted">Loading...</div>',

        // Users table
        '<section class="usm-panel">',
        '  <h3 class="usm-subheading">Users</h3>',
        '  <div class="usm-table-wrap">',
        '    <table class="usm-table">',
        '      <thead><tr><th>Username</th><th>Role</th><th>Status</th><th>Permissions</th><th>Actions</th></tr></thead>',
        '      <tbody id="usmUsersRows"><tr><td colspan="5" class="usm-muted">Loading...</td></tr></tbody>',
        '    </table>',
        '  </div>',
        '</section>',

        // Edit permissions
        '<section class="usm-panel" id="usmEditPermSection" style="display:none">',
        '  <h3 class="usm-subheading" id="usmEditPermTitle">Edit Permissions</h3>',
        '  <div class="usm-permissions-box" id="usmEditUserPermissions"></div>',
        '  <div class="usm-form-actions">',
        '    <button class="usm-btn" id="usmSavePermBtn">Save Permissions</button>',
        '  </div>',
        '</section>',

        // Create user
        '<section class="usm-panel">',
        '  <h3 class="usm-subheading">Create User</h3>',
        '  <div class="usm-form-grid">',
        '    <label class="usm-label">Username (mobile)<input id="usmNewUsername" class="usm-input" type="tel" inputmode="numeric" maxlength="15" placeholder="Mobile number"></label>',
        '    <label class="usm-label">Display Name<input id="usmNewDisplayName" class="usm-input" placeholder="Full name"></label>',
        '    <label class="usm-label">Role',
        '      <select id="usmNewUserRole" class="usm-input">',
        '        <option value="admin">Admin</option>',
        '        <option value="superadmin">SuperAdmin</option>',
        '      </select>',
        '    </label>',
        '    <label class="usm-label">Password<input id="usmNewPassword" class="usm-input" type="password" maxlength="64" placeholder="Initial password"></label>',
        '  </div>',
        '  <div class="usm-permissions-box" id="usmNewUserPermissions"></div>',
        '  <div class="usm-form-actions">',
        '    <button class="usm-btn" id="usmCreateUserBtn">Create User</button>',
        '  </div>',
        '</section>',

        // API Settings
        '<section class="usm-panel">',
        '  <h3 class="usm-subheading">API Settings</h3>',
        '  <div id="usmApiSettingsStatusList" class="usm-api-settings-status-list"></div>',
        '  <div id="usmApiSettingsStatus" class="usm-muted" style="font-size:0.82rem;margin:8px 0;"></div>',
        '  <div class="usm-settings-grid">',
        settingInputsHtml,
        '  </div>',
        '  <div class="usm-form-actions">',
        '    <button class="usm-btn" id="usmSaveApiSettingsBtn">Save API Settings</button>',
        '  </div>',
        '</section>',

        // Change own password
        '<section class="usm-panel">',
        '  <h3 class="usm-subheading">Change Your Password</h3>',
        '  <div class="usm-form-grid">',
        '    <label class="usm-label">Current Password<input id="usmCurrentPassword" class="usm-input" type="password" maxlength="64"></label>',
        '    <label class="usm-label">New Password<input id="usmNewOwnPassword" class="usm-input" type="password" maxlength="64"></label>',
        '    <label class="usm-label">Confirm New Password<input id="usmConfirmOwnPassword" class="usm-input" type="password" maxlength="64"></label>',
        '  </div>',
        '  <div id="usmPwdStatus" class="usm-muted" style="font-size:0.82rem;margin:8px 0;"></div>',
        '  <div class="usm-form-actions">',
        '    <button class="usm-btn" id="usmChangePwdBtn">Change Password</button>',
        '  </div>',
        '</section>',

        '</div>'
      ].join('\n');
    },

    _injectStyles: function () {
      var id = 'usm-styles';
      if (document.getElementById(id)) return;
      var style = document.createElement('style');
      style.id = id;
      style.textContent = [
        '.usm-wrap { padding:16px; }',
        '.usm-header-row { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }',
        '.usm-heading { font-size:1.25rem; font-weight:700; margin:0; }',
        '.usm-subheading { font-size:1rem; font-weight:700; margin:0 0 12px; }',
        '.usm-status { font-size:0.85rem; margin-bottom:10px; padding:6px 10px; border-radius:8px; background:rgba(0,0,0,0.04); }',
        '.usm-muted { color:#888; }',
        '.usm-panel { background:#fff; border:1px solid rgba(0,0,0,0.1); border-radius:12px; padding:16px; margin-bottom:16px; }',
        '.usm-form-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:10px; margin-bottom:10px; }',
        '.usm-settings-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:10px; margin-bottom:10px; }',
        '.usm-label { display:flex; flex-direction:column; font-size:0.78rem; font-weight:600; color:#555; gap:4px; }',
        '.usm-input { padding:7px 10px; border:1px solid rgba(123,94,67,0.24); border-radius:8px; font-size:0.84rem; outline:none; width:100%; box-sizing:border-box; }',
        '.usm-input:focus { border-color:rgba(148,89,43,0.5); box-shadow:0 0 0 2px rgba(182,123,69,0.18); }',
        '.usm-form-actions { display:flex; gap:8px; margin-top:12px; }',
        '.usm-permissions-box { padding:10px; border:1px solid rgba(0,0,0,0.1); border-radius:8px; background:#fafafa; margin-bottom:10px; }',
        '.usm-permissions-grid { display:flex; flex-wrap:wrap; gap:10px; }',
        '.usm-perm-label { display:flex; align-items:center; gap:6px; font-size:0.83rem; cursor:pointer; }',
        '.usm-api-settings-status-list { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:12px; }',
        '.usm-api-settings-status-item { display:flex; align-items:center; gap:6px; font-size:0.83rem; }',
        '.usm-state { display:inline-flex; padding:2px 8px; border-radius:999px; font-size:0.72rem; font-weight:700; border:1px solid transparent; }',
        '.usm-state.is-set { background:rgba(56,142,60,0.12); color:#2e7d32; border-color:rgba(56,142,60,0.25); }',
        '.usm-state.is-not-set { background:rgba(164,83,72,0.12); color:#7a342b; border-color:rgba(164,83,72,0.2); }',
        '.usm-table-wrap { overflow-x:auto; border-radius:10px; border:1px solid rgba(0,0,0,0.1); }',
        '.usm-table { width:100%; border-collapse:collapse; font-size:0.83rem; }',
        '.usm-table thead th { background:#f5efe2; padding:8px 10px; text-align:left; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; color:#888; border-bottom:1px solid rgba(0,0,0,0.1); white-space:nowrap; }',
        '.usm-table tbody td { padding:8px 10px; border-bottom:1px solid rgba(0,0,0,0.07); vertical-align:middle; }',
        '.usm-table tbody tr:last-child td { border-bottom:none; }',
        '.usm-table tbody tr:hover td { background:rgba(182,123,69,0.06); }',
        '.usm-user-name { font-weight:600; }',
        '.usm-pill { display:inline-flex; padding:2px 10px; border-radius:999px; font-size:0.72rem; font-weight:700; border:1px solid transparent; }',
        '.usm-pill.usm-active { background:rgba(56,142,60,0.12); color:#2e7d32; border-color:rgba(56,142,60,0.25); }',
        '.usm-pill.usm-inactive { background:rgba(164,83,72,0.12); color:#7a342b; border-color:rgba(164,83,72,0.2); }',
        '.usm-btn { padding:7px 14px; border-radius:8px; border:none; background:#214038; color:#fff; font-size:0.84rem; font-weight:600; cursor:pointer; }',
        '.usm-btn:disabled { opacity:0.5; cursor:default; }',
        '.usm-btn-sec { background:transparent; border:1px solid rgba(0,0,0,0.18); color:#214038; }',
        '.usm-btn-sm { padding:4px 10px; font-size:0.78rem; }',
        '.usm-btn-danger { background:#c0392b; color:#fff; border:none; }',
        '.usm-btn-danger:hover { background:#a93226; }',
        '@media(max-width:700px){.usm-wrap{padding:8px;} .usm-form-grid{grid-template-columns:1fr;}}'
      ].join('\n');
      document.head.appendChild(style);
    }
  };
})(window.NK || (window.NK = {}));
