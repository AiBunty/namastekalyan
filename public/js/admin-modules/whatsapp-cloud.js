/**
 * NK Admin SPA Module: WhatsApp Cloud API
 */
(function (NK) {
  'use strict';
  NK.MODULES = NK.MODULES || {};

  NK.MODULES['whatsapp-cloud'] = {
    _container: null,
    _authClient: null,
    _workspace: null,
    _selectedDraftId: 0,

    init: function (container, authClient, user) {
      this._container = container;
      this._authClient = authClient;
      this._workspace = null;
      this._selectedDraftId = 0;

      if (!user || user.role !== 'superadmin') {
        container.innerHTML = '<div style="padding:24px;color:#7a342b;font-weight:600;">Access denied. SuperAdmin only.</div>';
        return;
      }

      this._injectStyles();
      container.innerHTML = this._buildHtml();
      this._bindEvents();
      this._loadWorkspace('Loading WhatsApp workspace...');
    },

    destroy: function () {
      this._container = null;
      this._authClient = null;
      this._workspace = null;
      this._selectedDraftId = 0;
    },

    _buildHtml: function () {
      return [
        '<div class="wac-wrap">',
        '  <div class="wac-head">',
        '    <div>',
        '      <h2 class="wac-title">WhatsApp Cloud API</h2>',
        '      <p class="wac-subtitle">Store Meta credentials, sync approved templates, map live events, run reminder jobs, and draft templates without leaving the admin.</p>',
        '    </div>',
        '    <button type="button" id="wacReloadBtn" class="wac-btn wac-btn-secondary">Reload</button>',
        '  </div>',
        '  <div id="wacStatus" class="wac-status">Loading...</div>',

        '  <section class="wac-panel">',
        '    <h3 class="wac-section-title">Meta Credentials</h3>',
        '    <div id="wacConfigSummary" class="wac-chip-row"></div>',
        '    <div id="wacWebhookUrl" class="wac-note"></div>',
        '    <div class="wac-grid">',
        '      <label class="wac-label">Access Token<input id="wacAccessToken" class="wac-input" type="password" placeholder="Leave blank to keep current token"></label>',
        '      <label class="wac-label">Phone Number ID<input id="wacPhoneNumberId" class="wac-input" type="text" placeholder="Meta phone number ID"></label>',
        '      <label class="wac-label">Business Account ID<input id="wacBusinessAccountId" class="wac-input" type="text" placeholder="WhatsApp Business Account ID"></label>',
        '      <label class="wac-label">Verify Token<input id="wacVerifyToken" class="wac-input" type="password" placeholder="Webhook verify token"></label>',
        '    </div>',
        '    <div class="wac-actions">',
        '      <button type="button" id="wacSaveConfigBtn" class="wac-btn">Save Meta Settings</button>',
        '      <button type="button" id="wacSyncBtn" class="wac-btn wac-btn-secondary">Sync Templates</button>',
        '    </div>',
        '  </section>',

        '  <section class="wac-panel">',
        '    <h3 class="wac-section-title">Event Mapping</h3>',
        '    <div class="wac-grid">',
        '      <label class="wac-label">Event<select id="wacEventSelect" class="wac-input"></select></label>',
        '      <label class="wac-label">Approved Template<select id="wacTemplateSelect" class="wac-input"></select></label>',
        '      <label class="wac-label">Enable Mapping<div class="wac-toggle-wrap"><input id="wacEnableMapping" type="checkbox"><span>Send automatically when this event is triggered</span></div></label>',
        '    </div>',
        '    <div id="wacEventMeta" class="wac-note">Choose an approved Meta template whose body parameters match the listed sample variables.</div>',
        '    <div class="wac-actions">',
        '      <button type="button" id="wacSaveMappingBtn" class="wac-btn">Save Event Mapping</button>',
        '    </div>',
        '  </section>',

        '  <section class="wac-panel">',
        '    <div class="wac-panel-head">',
        '      <h3 class="wac-section-title">Reminder Engine</h3>',
        '      <button type="button" id="wacRunSchedulerBtn" class="wac-btn wac-btn-secondary">Run Due Reminders Now</button>',
        '    </div>',
        '    <div id="wacScheduleSummary" class="wac-chip-row"></div>',
        '    <div class="wac-note">24h, 6h, 2h, 30-minute missed check-in, and 12-hour thank-you reminders are queued automatically on event registration and check-in.</div>',
        '    <div class="wac-table-wrap">',
        '      <table class="wac-table">',
        '        <thead><tr><th>Due At</th><th>Event</th><th>Customer</th><th>Phone</th><th>Status</th><th>Attempts</th><th>Last Result</th></tr></thead>',
        '        <tbody id="wacSchedulesRows"><tr><td colspan="7" class="wac-empty">No scheduled reminders yet.</td></tr></tbody>',
        '      </table>',
        '    </div>',
        '  </section>',

        '  <section class="wac-panel">',
        '    <div class="wac-panel-head">',
        '      <h3 class="wac-section-title">Template Draft Studio</h3>',
        '      <select id="wacDraftSelect" class="wac-input wac-select-inline"></select>',
        '    </div>',
        '    <div class="wac-grid">',
        '      <label class="wac-label">Draft Label<input id="wacDraftName" class="wac-input" type="text" placeholder="Internal draft name"></label>',
        '      <label class="wac-label">Template Name<input id="wacDraftTemplateName" class="wac-input" type="text" placeholder="meta_ready_template_name"></label>',
        '      <label class="wac-label">Category<select id="wacDraftCategory" class="wac-input"><option value="UTILITY">UTILITY</option><option value="MARKETING">MARKETING</option></select></label>',
        '      <label class="wac-label">Language Code<input id="wacDraftLanguageCode" class="wac-input" type="text" value="en"></label>',
        '      <label class="wac-label">Header Type<select id="wacDraftHeaderType" class="wac-input"><option value="NONE">NONE</option><option value="TEXT">TEXT</option><option value="IMAGE">IMAGE</option></select></label>',
        '      <label class="wac-label">Header Text / Media Handle<input id="wacDraftHeaderText" class="wac-input" type="text" placeholder="Text header or uploaded image handle"></label>',
      '    </div>',
        '    <div class="wac-grid wac-grid-stack">',
        '      <label class="wac-label">Body Text<textarea id="wacDraftBodyText" class="wac-input wac-textarea" placeholder="Use {{1}}, {{2}} placeholders exactly as Meta expects."></textarea></label>',
        '      <label class="wac-label">Footer Text<input id="wacDraftFooterText" class="wac-input" type="text" placeholder="Optional footer"></label>',
        '      <label class="wac-label">Sample Variables<input id="wacDraftSampleVariables" class="wac-input" type="text" placeholder="Guest Name, Event Name, 24 Apr 2025, 7:00 PM"></label>',
        '    </div>',
        '    <div class="wac-note">For image headers, enter the Meta media handle if you already uploaded the asset. Body sample variables should be comma-separated in the same order as the placeholders.</div>',
        '    <div class="wac-actions">',
        '      <button type="button" id="wacNewDraftBtn" class="wac-btn wac-btn-secondary">New Draft</button>',
        '      <button type="button" id="wacSaveDraftBtn" class="wac-btn">Save Draft</button>',
        '      <button type="button" id="wacSubmitDraftBtn" class="wac-btn">Submit To Meta</button>',
        '    </div>',
        '    <div class="wac-table-wrap">',
        '      <table class="wac-table">',
        '        <thead><tr><th>Draft</th><th>Template</th><th>Status</th><th>Language</th><th>Updated</th><th>Reason</th></tr></thead>',
        '        <tbody id="wacDraftRows"><tr><td colspan="6" class="wac-empty">No template drafts yet.</td></tr></tbody>',
        '      </table>',
        '    </div>',
        '  </section>',

        '  <section class="wac-panel">',
        '    <h3 class="wac-section-title">Test Send</h3>',
        '    <div class="wac-grid">',
        '      <label class="wac-label">Phone<input id="wacTestPhone" class="wac-input" type="text" placeholder="10-digit mobile"></label>',
        '      <label class="wac-label">Customer Name<input id="wacTestName" class="wac-input" type="text" placeholder="Test Guest"></label>',
        '      <label class="wac-label">Reward Label<input id="wacTestReward" class="wac-input" type="text" placeholder="Free Mocktail"></label>',
        '      <label class="wac-label">Coupon Code<input id="wacTestCoupon" class="wac-input" type="text" placeholder="TEST123"></label>',
        '    </div>',
        '    <div class="wac-note">Uses the currently selected event mapping. If the selected event has no enabled template mapping, the send will be logged as skipped.</div>',
        '    <div class="wac-actions">',
        '      <button type="button" id="wacTestSendBtn" class="wac-btn">Send Test Message</button>',
        '    </div>',
        '  </section>',

        '  <section class="wac-panel">',
        '    <div class="wac-panel-head">',
        '      <h3 class="wac-section-title">Approved Templates</h3>',
        '      <span id="wacTemplateCount" class="wac-meta">0 templates</span>',
        '    </div>',
        '    <div class="wac-table-wrap">',
        '      <table class="wac-table">',
        '        <thead><tr><th>Name</th><th>Language</th><th>Category</th><th>Status</th><th>Quality</th><th>Synced</th></tr></thead>',
        '        <tbody id="wacTemplatesRows"><tr><td colspan="6" class="wac-empty">No templates synced yet.</td></tr></tbody>',
        '      </table>',
        '    </div>',
        '  </section>',

        '  <section class="wac-panel">',
        '    <div class="wac-panel-head">',
        '      <h3 class="wac-section-title">Recent Sends</h3>',
        '      <span class="wac-meta">Latest WhatsApp attempts and skips</span>',
        '    </div>',
        '    <div class="wac-table-wrap">',
        '      <table class="wac-table">',
        '        <thead><tr><th>Time</th><th>Event</th><th>Phone</th><th>Template</th><th>Result</th><th>Status</th><th>Message</th></tr></thead>',
        '        <tbody id="wacLogsRows"><tr><td colspan="7" class="wac-empty">No WhatsApp sends logged yet.</td></tr></tbody>',
        '      </table>',
        '    </div>',
        '  </section>',
        '</div>'
      ].join('');
    },

    _bindEvents: function () {
      var module = this;
      var c = module._container;
      c.querySelector('#wacReloadBtn').addEventListener('click', function () {
        module._loadWorkspace('Refreshing WhatsApp workspace...');
      });
      c.querySelector('#wacSaveConfigBtn').addEventListener('click', function () {
        module._saveConfig();
      });
      c.querySelector('#wacSyncBtn').addEventListener('click', function () {
        module._syncTemplates();
      });
      c.querySelector('#wacSaveMappingBtn').addEventListener('click', function () {
        module._saveMapping();
      });
      c.querySelector('#wacRunSchedulerBtn').addEventListener('click', function () {
        module._runScheduler();
      });
      c.querySelector('#wacDraftSelect').addEventListener('change', function () {
        module._selectDraft();
      });
      c.querySelector('#wacNewDraftBtn').addEventListener('click', function () {
        module._clearDraftForm();
      });
      c.querySelector('#wacSaveDraftBtn').addEventListener('click', function () {
        module._saveDraft();
      });
      c.querySelector('#wacSubmitDraftBtn').addEventListener('click', function () {
        module._submitDraft();
      });
      c.querySelector('#wacTestSendBtn').addEventListener('click', function () {
        module._sendTestMessage();
      });
      c.querySelector('#wacEventSelect').addEventListener('change', function () {
        module._renderSelectedEvent();
      });
    },

    _setStatus: function (message, kind) {
      var el = this._container && this._container.querySelector('#wacStatus');
      if (!el) return;
      el.textContent = String(message || '');
      el.className = 'wac-status' + (kind ? (' is-' + kind) : '');
    },

    _loadWorkspace: async function (statusMessage) {
      this._setStatus(statusMessage || 'Loading WhatsApp workspace...', 'info');
      try {
        var payload = await this._authClient.apiPost({ action: 'auth_get_whatsapp_workspace' });
        this._workspace = payload && payload.workspace ? payload.workspace : null;
        this._renderWorkspace();
        this._setStatus('WhatsApp workspace loaded.', 'success');
      } catch (err) {
        this._setStatus('Failed to load WhatsApp workspace: ' + (err.message || err), 'error');
      }
    },

    _saveConfig: async function () {
      var c = this._container;
      var updates = {};
      var accessToken = String((c.querySelector('#wacAccessToken') || {}).value || '').trim();
      var phoneNumberId = String((c.querySelector('#wacPhoneNumberId') || {}).value || '').trim();
      var businessAccountId = String((c.querySelector('#wacBusinessAccountId') || {}).value || '').trim();
      var verifyToken = String((c.querySelector('#wacVerifyToken') || {}).value || '').trim();

      if (accessToken) updates.WHATSAPP_META_ACCESS_TOKEN = accessToken;
      if (phoneNumberId) updates.WHATSAPP_META_PHONE_NUMBER_ID = phoneNumberId;
      if (businessAccountId) updates.WHATSAPP_META_BUSINESS_ACCOUNT_ID = businessAccountId;
      if (verifyToken) updates.WHATSAPP_META_VERIFY_TOKEN = verifyToken;

      if (!Object.keys(updates).length) {
        this._setStatus('Enter at least one Meta setting before saving.', 'error');
        return;
      }

      this._setStatus('Saving WhatsApp Meta settings...', 'info');
      try {
        var payload = await this._authClient.apiPost({ action: 'auth_save_whatsapp_config', settings: updates });
        this._workspace = payload && payload.workspace ? payload.workspace : this._workspace;
        ['wacAccessToken', 'wacPhoneNumberId', 'wacBusinessAccountId', 'wacVerifyToken'].forEach(function (id) {
          var el = c.querySelector('#' + id);
          if (el) el.value = '';
        });
        this._renderWorkspace();
        this._setStatus((payload && payload.message) || 'WhatsApp Meta settings saved.', 'success');
      } catch (err) {
        this._setStatus('Failed to save WhatsApp Meta settings: ' + (err.message || err), 'error');
      }
    },

    _syncTemplates: async function () {
      this._setStatus('Syncing approved templates from Meta...', 'info');
      try {
        var payload = await this._authClient.apiPost({ action: 'auth_sync_whatsapp_templates' });
        this._workspace = payload && payload.workspace ? payload.workspace : this._workspace;
        this._renderWorkspace();
        this._setStatus((payload && payload.message) || 'Templates synced successfully.', 'success');
      } catch (err) {
        this._setStatus('Template sync failed: ' + (err.message || err), 'error');
      }
    },

    _saveMapping: async function () {
      var c = this._container;
      var eventKey = String((c.querySelector('#wacEventSelect') || {}).value || '').trim();
      var templateValue = String((c.querySelector('#wacTemplateSelect') || {}).value || '');
      var parts = templateValue.split('||');
      var templateName = parts[0] || '';
      var languageCode = parts[1] || '';
      var isEnabled = !!((c.querySelector('#wacEnableMapping') || {}).checked);

      this._setStatus('Saving event mapping...', 'info');
      try {
        var payload = await this._authClient.apiPost({
          action: 'auth_save_whatsapp_mapping',
          eventKey: eventKey,
          mapping: {
            templateName: templateName,
            languageCode: languageCode,
            isEnabled: isEnabled
          }
        });
        this._workspace = payload && payload.workspace ? payload.workspace : this._workspace;
        this._renderWorkspace();
        this._setStatus((payload && payload.message) || 'Event mapping saved.', 'success');
      } catch (err) {
        this._setStatus('Failed to save event mapping: ' + (err.message || err), 'error');
      }
    },

    _sendTestMessage: async function () {
      var c = this._container;
      var eventKey = String((c.querySelector('#wacEventSelect') || {}).value || '').trim();
      var phone = String((c.querySelector('#wacTestPhone') || {}).value || '').trim();
      var customerName = String((c.querySelector('#wacTestName') || {}).value || '').trim();
      var rewardLabel = String((c.querySelector('#wacTestReward') || {}).value || '').trim();
      var couponCode = String((c.querySelector('#wacTestCoupon') || {}).value || '').trim();

      if (!eventKey || !phone) {
        this._setStatus('Select an event and enter a phone number before sending a test message.', 'error');
        return;
      }

      this._setStatus('Sending WhatsApp test message...', 'info');
      try {
        var payload = await this._authClient.apiPost({
          action: 'auth_send_test_whatsapp_template',
          eventKey: eventKey,
          phone: phone,
          customerName: customerName,
          rewardLabel: rewardLabel,
          couponCode: couponCode
        });
        this._workspace = payload && payload.workspace ? payload.workspace : this._workspace;
        this._renderWorkspace();
        this._setStatus((payload && payload.message) || 'WhatsApp test message sent.', 'success');
      } catch (err) {
        this._setStatus('Test send failed: ' + (err.message || err), 'error');
      }
    },

    _renderWorkspace: function () {
      if (!this._workspace || !this._container) return;
      this._renderConfigSummary();
      this._renderEvents();
      this._renderSchedules();
      this._renderDrafts();
      this._renderTemplates();
      this._renderLogs();
    },

    _renderConfigSummary: function () {
      var c = this._container;
      var config = this._workspace.config || {};
      var chips = [
        { label: 'Access Token', ok: !!config.accessTokenConfigured },
        { label: 'Phone Number ID', ok: !!config.phoneNumberIdConfigured },
        { label: 'Business Account ID', ok: !!config.businessAccountIdConfigured },
        { label: 'Verify Token', ok: !!config.verifyTokenConfigured },
        { label: 'Ready For Send', ok: !!config.readyForSend },
        { label: 'Ready For Sync', ok: !!config.readyForSync }
      ];

      c.querySelector('#wacConfigSummary').innerHTML = chips.map(function (chip) {
        return '<span class="wac-chip ' + (chip.ok ? 'is-ok' : 'is-bad') + '">' + chip.label + ': ' + (chip.ok ? 'Configured' : 'Missing') + '</span>';
      }).join('');

      c.querySelector('#wacWebhookUrl').textContent = config.webhookUrl
        ? ('Meta webhook URL: ' + config.webhookUrl)
        : 'Meta webhook URL will appear here once the workspace loads.';
    },

    _renderEvents: function () {
      var c = this._container;
      var events = Array.isArray(this._workspace.events) ? this._workspace.events : [];
      var eventSelect = c.querySelector('#wacEventSelect');
      var templateSelect = c.querySelector('#wacTemplateSelect');
      var templates = Array.isArray(this._workspace.templates) ? this._workspace.templates : [];

      eventSelect.innerHTML = events.map(function (event) {
        return '<option value="' + event.eventKey + '">' + event.label + '</option>';
      }).join('');

      templateSelect.innerHTML = ['<option value="">Select approved template</option>'].concat(templates.map(function (template) {
        var value = template.templateName + '||' + template.languageCode;
        return '<option value="' + value + '">' + template.templateName + ' [' + template.languageCode + ']</option>';
      })).join('');

      this._renderSelectedEvent();
    },

    _renderSelectedEvent: function () {
      var c = this._container;
      var events = Array.isArray(this._workspace && this._workspace.events) ? this._workspace.events : [];
      var selectedKey = String((c.querySelector('#wacEventSelect') || {}).value || '');
      var event = events.find(function (item) { return item.eventKey === selectedKey; }) || events[0];
      if (!event) return;

      c.querySelector('#wacEventSelect').value = event.eventKey;
      c.querySelector('#wacEnableMapping').checked = !!(event.mapping && event.mapping.isEnabled);
      c.querySelector('#wacTemplateSelect').value = event.mapping && event.mapping.templateName
        ? (event.mapping.templateName + '||' + event.mapping.languageCode)
        : '';
      c.querySelector('#wacEventMeta').textContent = event.description + ' Sample variables: ' + (event.sampleVariables || []).join(', ');
    },

    _runScheduler: async function () {
      this._setStatus('Running due reminder jobs...', 'info');
      try {
        var payload = await this._authClient.apiPost({ action: 'auth_run_whatsapp_scheduler', limit: 100 });
        this._workspace = payload && payload.workspace ? payload.workspace : this._workspace;
        this._renderWorkspace();
        var result = payload && payload.result ? payload.result : {};
        this._setStatus((payload && payload.message) || ('Processed ' + (result.processed || 0) + ' reminder job(s).'), 'success');
      } catch (err) {
        this._setStatus('Reminder run failed: ' + (err.message || err), 'error');
      }
    },

    _renderSchedules: function () {
      var summary = this._workspace.scheduleSummary || {};
      var summaryEl = this._container.querySelector('#wacScheduleSummary');
      var items = [
        { label: 'Pending', value: summary.pending || 0 },
        { label: 'Sent', value: summary.sent || 0 },
        { label: 'Failed', value: summary.failed || 0 },
        { label: 'Skipped', value: summary.skipped || 0 },
        { label: 'Cancelled', value: summary.cancelled || 0 }
      ];
      summaryEl.innerHTML = items.map(function (item) {
        return '<span class="wac-chip is-ok">' + item.label + ': ' + item.value + '</span>';
      }).join('');

      var rows = Array.isArray(this._workspace.scheduledMessages) ? this._workspace.scheduledMessages : [];
      var rowsEl = this._container.querySelector('#wacSchedulesRows');
      if (!rows.length) {
        rowsEl.innerHTML = '<tr><td colspan="7" class="wac-empty">No scheduled reminders yet.</td></tr>';
        return;
      }

      rowsEl.innerHTML = rows.map(function (row) {
        return '<tr>'
          + '<td>' + (row.dueAt || '-') + '</td>'
          + '<td>' + (row.eventTitle || row.eventKey || '-') + '</td>'
          + '<td>' + (row.customerName || '-') + '</td>'
          + '<td>' + (row.phone || '-') + '</td>'
          + '<td>' + (row.status || '-') + '</td>'
          + '<td>' + String(row.attemptCount || 0) + '</td>'
          + '<td>' + ((row.lastResultCode || '-') + ((row.lastResultMessage || '') ? (' - ' + row.lastResultMessage) : '')) + '</td>'
          + '</tr>';
      }).join('');
    },

    _renderDrafts: function () {
      var drafts = Array.isArray(this._workspace.drafts) ? this._workspace.drafts : [];
      var selectEl = this._container.querySelector('#wacDraftSelect');
      selectEl.innerHTML = ['<option value="0">Select saved draft</option>'].concat(drafts.map(function (draft) {
        return '<option value="' + draft.id + '">' + draft.draftName + ' [' + draft.status + ']</option>';
      })).join('');

      if (this._selectedDraftId) {
        selectEl.value = String(this._selectedDraftId);
      }

      var rowsEl = this._container.querySelector('#wacDraftRows');
      if (!drafts.length) {
        rowsEl.innerHTML = '<tr><td colspan="6" class="wac-empty">No template drafts yet.</td></tr>';
      } else {
        rowsEl.innerHTML = drafts.map(function (draft) {
          return '<tr>'
            + '<td>' + (draft.draftName || '-') + '</td>'
            + '<td>' + (draft.templateName || '-') + '</td>'
            + '<td>' + (draft.status || '-') + '</td>'
            + '<td>' + (draft.languageCode || '-') + '</td>'
            + '<td>' + (draft.updatedAt || '-') + '</td>'
            + '<td>' + (draft.rejectionReason || '-') + '</td>'
            + '</tr>';
        }).join('');
      }

      if (!this._selectedDraftId && drafts.length) {
        this._selectedDraftId = drafts[0].id;
      }
      if (this._selectedDraftId) {
        this._fillDraftForm(this._findDraftById(this._selectedDraftId));
      }
    },

    _selectDraft: function () {
      var selected = Number((this._container.querySelector('#wacDraftSelect') || {}).value || 0);
      this._selectedDraftId = selected;
      this._fillDraftForm(this._findDraftById(selected));
    },

    _findDraftById: function (id) {
      var drafts = Array.isArray(this._workspace && this._workspace.drafts) ? this._workspace.drafts : [];
      for (var i = 0; i < drafts.length; i += 1) {
        if (Number(drafts[i].id || 0) === Number(id || 0)) return drafts[i];
      }
      return null;
    },

    _fillDraftForm: function (draft) {
      var c = this._container;
      if (!draft) {
        return;
      }
      c.querySelector('#wacDraftName').value = draft.draftName || '';
      c.querySelector('#wacDraftTemplateName').value = draft.templateName || '';
      c.querySelector('#wacDraftCategory').value = draft.category || 'UTILITY';
      c.querySelector('#wacDraftLanguageCode').value = draft.languageCode || 'en';
      c.querySelector('#wacDraftHeaderType').value = draft.headerType || 'NONE';
      c.querySelector('#wacDraftHeaderText').value = draft.headerType === 'IMAGE'
        ? (draft.exampleMediaHandle || '')
        : (draft.headerText || '');
      c.querySelector('#wacDraftBodyText').value = draft.bodyText || '';
      c.querySelector('#wacDraftFooterText').value = draft.footerText || '';
      c.querySelector('#wacDraftSampleVariables').value = Array.isArray(draft.sampleVariables) ? draft.sampleVariables.join(', ') : '';
    },

    _clearDraftForm: function () {
      this._selectedDraftId = 0;
      var c = this._container;
      ['#wacDraftName', '#wacDraftTemplateName', '#wacDraftHeaderText', '#wacDraftBodyText', '#wacDraftFooterText', '#wacDraftSampleVariables'].forEach(function (selector) {
        var el = c.querySelector(selector);
        if (el) el.value = '';
      });
      c.querySelector('#wacDraftCategory').value = 'UTILITY';
      c.querySelector('#wacDraftLanguageCode').value = 'en';
      c.querySelector('#wacDraftHeaderType').value = 'NONE';
      c.querySelector('#wacDraftSelect').value = '0';
    },

    _collectDraftPayload: function () {
      var c = this._container;
      var headerType = String((c.querySelector('#wacDraftHeaderType') || {}).value || 'NONE').trim();
      var headerValue = String((c.querySelector('#wacDraftHeaderText') || {}).value || '').trim();
      return {
        id: this._selectedDraftId || 0,
        draftName: String((c.querySelector('#wacDraftName') || {}).value || '').trim(),
        templateName: String((c.querySelector('#wacDraftTemplateName') || {}).value || '').trim(),
        category: String((c.querySelector('#wacDraftCategory') || {}).value || 'UTILITY').trim(),
        languageCode: String((c.querySelector('#wacDraftLanguageCode') || {}).value || 'en').trim(),
        headerType: headerType,
        headerText: headerType === 'TEXT' ? headerValue : '',
        exampleMediaHandle: headerType === 'IMAGE' ? headerValue : '',
        bodyText: String((c.querySelector('#wacDraftBodyText') || {}).value || '').trim(),
        footerText: String((c.querySelector('#wacDraftFooterText') || {}).value || '').trim(),
        sampleVariables: String((c.querySelector('#wacDraftSampleVariables') || {}).value || '').split(',').map(function (item) {
          return String(item || '').trim();
        }).filter(Boolean),
        buttons: []
      };
    },

    _saveDraft: async function () {
      var draft = this._collectDraftPayload();
      if (!draft.templateName || !draft.bodyText) {
        this._setStatus('Template name and body text are required before saving a draft.', 'error');
        return;
      }

      this._setStatus('Saving template draft...', 'info');
      try {
        var payload = await this._authClient.apiPost({ action: 'auth_save_whatsapp_template_draft', draft: draft });
        this._workspace = payload && payload.workspace ? payload.workspace : this._workspace;
        this._selectedDraftId = this._resolveDraftIdByName(draft.templateName);
        this._renderWorkspace();
        this._setStatus((payload && payload.message) || 'Template draft saved.', 'success');
      } catch (err) {
        this._setStatus('Saving template draft failed: ' + (err.message || err), 'error');
      }
    },

    _submitDraft: async function () {
      if (!this._selectedDraftId) {
        this._setStatus('Select or save a draft before submitting it to Meta.', 'error');
        return;
      }

      this._setStatus('Submitting template draft to Meta...', 'info');
      try {
        var payload = await this._authClient.apiPost({ action: 'auth_submit_whatsapp_template_draft', draftId: this._selectedDraftId });
        this._workspace = payload && payload.workspace ? payload.workspace : this._workspace;
        this._renderWorkspace();
        this._setStatus((payload && payload.message) || 'Template draft submitted.', 'success');
      } catch (err) {
        this._setStatus('Template submission failed: ' + (err.message || err), 'error');
      }
    },

    _resolveDraftIdByName: function (templateName) {
      var drafts = Array.isArray(this._workspace && this._workspace.drafts) ? this._workspace.drafts : [];
      for (var i = 0; i < drafts.length; i += 1) {
        if (String(drafts[i].templateName || '') === String(templateName || '')) {
          return Number(drafts[i].id || 0);
        }
      }
      return this._selectedDraftId || 0;
    },

    _renderTemplates: function () {
      var templates = Array.isArray(this._workspace.templates) ? this._workspace.templates : [];
      var rowsEl = this._container.querySelector('#wacTemplatesRows');
      var countEl = this._container.querySelector('#wacTemplateCount');
      countEl.textContent = String(templates.length) + ' approved template' + (templates.length === 1 ? '' : 's');

      if (!templates.length) {
        rowsEl.innerHTML = '<tr><td colspan="6" class="wac-empty">No approved templates synced yet.</td></tr>';
        return;
      }

      rowsEl.innerHTML = templates.map(function (template) {
        return '<tr>'
          + '<td>' + template.templateName + '</td>'
          + '<td>' + template.languageCode + '</td>'
          + '<td>' + template.category + '</td>'
          + '<td>' + template.status + '</td>'
          + '<td>' + (template.qualityScore || '-') + '</td>'
          + '<td>' + (template.lastSyncedAt || '-') + '</td>'
          + '</tr>';
      }).join('');
    },

    _renderLogs: function () {
      var logs = Array.isArray(this._workspace.logs) ? this._workspace.logs : [];
      var rowsEl = this._container.querySelector('#wacLogsRows');
      if (!logs.length) {
        rowsEl.innerHTML = '<tr><td colspan="7" class="wac-empty">No WhatsApp sends logged yet.</td></tr>';
        return;
      }

      rowsEl.innerHTML = logs.map(function (log) {
        var resultText = !log.attempted ? 'Skipped' : (log.success ? 'Sent' : 'Failed');
        return '<tr>'
          + '<td>' + (log.createdAt || '-') + '</td>'
          + '<td>' + (log.eventKey || '-') + '</td>'
          + '<td>' + (log.phone || '-') + '</td>'
          + '<td>' + ((log.templateName || '-') + (log.languageCode ? ' [' + log.languageCode + ']' : '')) + '</td>'
          + '<td>' + resultText + (log.httpCode ? ' (' + log.httpCode + ')' : '') + '</td>'
          + '<td>' + (log.deliveryStatus || '-') + '</td>'
          + '<td>' + (log.responseMessage || '-') + '</td>'
          + '</tr>';
      }).join('');
    },

    _injectStyles: function () {
      if (document.getElementById('wac-styles')) return;
      var style = document.createElement('style');
      style.id = 'wac-styles';
      style.textContent = [
        '.wac-wrap{padding:18px;display:grid;gap:16px;}',
        '.wac-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;}',
        '.wac-title{margin:0;font-size:1.3rem;font-weight:700;}',
        '.wac-subtitle{margin:6px 0 0;color:#6b5a4b;font-size:0.9rem;max-width:70ch;line-height:1.5;}',
        '.wac-status{padding:10px 12px;border-radius:12px;background:rgba(33,64,56,0.07);color:#214038;font-size:0.9rem;font-weight:600;}',
        '.wac-status.is-success{background:rgba(77,129,100,0.12);color:#246a38;}',
        '.wac-status.is-error{background:rgba(164,83,72,0.12);color:#7a342b;}',
        '.wac-status.is-info{background:rgba(33,64,56,0.07);color:#214038;}',
        '.wac-panel{background:#fff;border:1px solid rgba(123,94,67,0.14);border-radius:16px;padding:16px;box-shadow:0 8px 20px rgba(80,57,36,0.05);}',
        '.wac-panel-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px;}',
        '.wac-section-title{margin:0 0 12px;font-size:1rem;font-weight:700;}',
        '.wac-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;}',
        '.wac-grid-stack{grid-template-columns:1fr;}',
        '.wac-label{display:flex;flex-direction:column;gap:6px;font-size:0.8rem;font-weight:700;color:#5f4d3f;}',
        '.wac-input{width:100%;padding:9px 10px;border:1px solid rgba(123,94,67,0.24);border-radius:10px;font:inherit;background:#fff;}',
        '.wac-input:focus{outline:none;border-color:rgba(148,89,43,0.5);box-shadow:0 0 0 2px rgba(182,123,69,0.16);}',
        '.wac-textarea{min-height:140px;resize:vertical;}',
        '.wac-select-inline{max-width:280px;}',
        '.wac-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px;}',
        '.wac-btn{padding:9px 14px;border:none;border-radius:10px;background:#214038;color:#fff;font-weight:700;cursor:pointer;}',
        '.wac-btn-secondary{background:#ead7bf;color:#5a412d;}',
        '.wac-chip-row{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;}',
        '.wac-chip{display:inline-flex;padding:5px 10px;border-radius:999px;font-size:0.75rem;font-weight:800;border:1px solid transparent;}',
        '.wac-chip.is-ok{background:rgba(77,129,100,0.12);color:#246a38;border-color:rgba(77,129,100,0.25);}',
        '.wac-chip.is-bad{background:rgba(164,83,72,0.12);color:#7a342b;border-color:rgba(164,83,72,0.25);}',
        '.wac-toggle-wrap{display:flex;align-items:center;gap:8px;min-height:42px;padding:0 2px;}',
        '.wac-note{margin-top:12px;padding:10px 12px;border-radius:12px;background:rgba(182,123,69,0.08);color:#5f4d3f;font-size:0.85rem;line-height:1.5;}',
        '.wac-table-wrap{overflow:auto;border:1px solid rgba(123,94,67,0.14);border-radius:12px;}',
        '.wac-table{width:100%;border-collapse:collapse;font-size:0.85rem;}',
        '.wac-table th,.wac-table td{padding:10px 12px;border-bottom:1px solid rgba(123,94,67,0.1);text-align:left;vertical-align:top;}',
        '.wac-table th{background:#f5efe2;font-size:0.76rem;text-transform:uppercase;letter-spacing:0.04em;color:#7d6a59;}',
        '.wac-table tr:last-child td{border-bottom:none;}',
        '.wac-empty{text-align:center;color:#7d6a59;}',
        '.wac-meta{font-size:0.82rem;color:#7d6a59;}',
        '@media(max-width:720px){.wac-wrap{padding:10px;}.wac-head{flex-direction:column;}.wac-actions{flex-direction:column;}.wac-btn{width:100%;}}'
      ].join('');
      document.head.appendChild(style);
    }
  };
})(window.NK || (window.NK = {}));