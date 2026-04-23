/**
 * NK Admin SPA Module: WhatsApp Cloud API
 */
(function (NK) {
  'use strict';
  NK.MODULES = NK.MODULES || {};

  NK.MODULES['whatsapp-cloud'] = {
    _container: null,
    _authClient: null,
    _phpApiUrl: null,
    _workspace: null,
    _selectedDraftId: 0,
    _selectedVersionId: 0,
    _draftHeaderImagePreviewUrl: '',
    _draftPreviewTimer: null,

    init: function (container, authClient, user) {
      this._container = container;
      this._authClient = authClient;
      this._phpApiUrl = (window.NK_DATA_API && window.NK_DATA_API.phpApiUrl) || '';
      this._workspace = null;
      this._selectedDraftId = 0;
      this._selectedVersionId = 0;
      this._draftHeaderImagePreviewUrl = '';
      this._draftPreviewTimer = null;

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
      if (this._draftPreviewTimer) {
        window.clearTimeout(this._draftPreviewTimer);
      }
      this._clearDraftHeaderImagePreview();
      this._container = null;
      this._authClient = null;
      this._phpApiUrl = null;
      this._workspace = null;
      this._selectedDraftId = 0;
      this._selectedVersionId = 0;
      this._draftPreviewTimer = null;
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
        '      <label class="wac-label">Tracked Version<select id="wacMappingVersionSelect" class="wac-input"></select></label>',
        '      <label class="wac-label">Enable Mapping<div class="wac-toggle-wrap"><input id="wacEnableMapping" type="checkbox"><span>Send automatically when this event is triggered</span></div></label>',
        '    </div>',
        '    <div id="wacEventTriggerSummary" class="wac-chip-row"></div>',
        '    <div id="wacEventMeta" class="wac-note">Choose an approved Meta template whose body parameters match the listed sample variables.</div>',
        '    <div class="wac-table-wrap">',
        '      <table class="wac-table">',
        '        <thead><tr><th>Variable</th><th>Source</th><th>Example Value</th><th>Description</th></tr></thead>',
        '        <tbody id="wacEventValueRows"><tr><td colspan="4" class="wac-empty">Select an event to inspect available values.</td></tr></tbody>',
        '      </table>',
        '    </div>',
        '    <div class="wac-actions">',
        '      <button type="button" id="wacSaveMappingBtn" class="wac-btn">Save Event Mapping</button>',
        '      <button type="button" id="wacPreviewTemplateBtn" class="wac-btn wac-btn-secondary">Preview Approved Template</button>',
        '      <button type="button" id="wacUseTemplateAsDraftBtn" class="wac-btn wac-btn-secondary">Edit As New Draft</button>',
        '    </div>',
        '  </section>',

        '  <section class="wac-panel">',
        '    <div class="wac-panel-head">',
        '      <h3 class="wac-section-title">Tracked Event Versions</h3>',
        '      <div class="wac-inline-actions">',
        '        <select id="wacVersionSelect" class="wac-input wac-select-inline"></select>',
        '        <button type="button" id="wacLoadVersionBtn" class="wac-btn wac-btn-secondary">Load Version</button>',
        '      </div>',
        '    </div>',
        '    <div class="wac-note">Each draft save can persist a tracked event version. Use these tracked versions when enabling live mappings so the mapping is pinned to both a version record and the approved Meta template UID.</div>',
        '    <div class="wac-table-wrap">',
        '      <table class="wac-table">',
        '        <thead><tr><th>Version</th><th>Template</th><th>Meta Status</th><th>Current</th><th>Updated</th><th>Meta UID</th></tr></thead>',
        '        <tbody id="wacVersionRows"><tr><td colspan="6" class="wac-empty">No tracked versions for this event yet.</td></tr></tbody>',
        '      </table>',
        '    </div>',
        '  </section>',

        '  <section class="wac-panel">',
        '    <div class="wac-panel-head">',
        '      <h3 class="wac-section-title">Template Preview</h3>',
        '      <span id="wacPreviewMeta" class="wac-meta">Choose an event and template to preview.</span>',
        '    </div>',
        '    <div id="wacPreviewWarnings" class="wac-preview-warnings"></div>',
        '    <div class="wac-preview-layout">',
        '      <div class="wac-preview-card">',
        '        <div class="wac-preview-phone">',
        '          <div class="wac-preview-phone-head"><span class="wac-preview-avatar">NK</span><div><div class="wac-preview-contact">Namaste Kalyan</div><div class="wac-preview-contact-sub">WhatsApp preview</div></div></div>',
        '          <div id="wacPreviewMedia" class="wac-preview-media"></div>',
        '          <div id="wacPreviewHeader" class="wac-preview-header"></div>',
        '          <div id="wacPreviewBody" class="wac-preview-body">No preview loaded.</div>',
        '          <div id="wacPreviewFooter" class="wac-preview-footer"></div>',
        '          <div id="wacPreviewButtons" class="wac-preview-buttons"></div>',
        '          <div class="wac-preview-time">now</div>',
        '        </div>',
        '      </div>',
        '      <div class="wac-preview-card">',
        '        <div class="wac-preview-summary">',
        '          <div><strong>Parameter Format:</strong> <span id="wacPreviewFormat">-</span></div>',
        '          <div><strong>Placeholders:</strong> <span id="wacPreviewPlaceholders">-</span></div>',
        '        </div>',
        '        <div class="wac-table-wrap">',
        '          <table class="wac-table">',
        '            <thead><tr><th>Variable</th><th>Example Value</th><th>Required</th></tr></thead>',
        '            <tbody id="wacPreviewValueRows"><tr><td colspan="3" class="wac-empty">Preview values will appear here.</td></tr></tbody>',
        '          </table>',
        '        </div>',
        '      </div>',
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
        '      <label id="wacDraftHeaderTextWrap" class="wac-label">Header Text<input id="wacDraftHeaderText" class="wac-input" type="text" placeholder="Visible header text"></label>',
        '      <label id="wacDraftMediaHandleWrap" class="wac-label">Meta Media Handle<input id="wacDraftMediaHandle" class="wac-input" type="text" placeholder="Paste the Meta image handle used on submit"></label>',
        '      <label id="wacDraftHeaderImageWrap" class="wac-label">Header Image Upload<input id="wacDraftHeaderImageFile" class="wac-input" type="file" accept="image/png,image/jpeg,image/webp,image/gif"></label>',
        '    </div>',
        '    <div class="wac-grid wac-grid-stack">',
        '      <label class="wac-label">Body Text<textarea id="wacDraftBodyText" class="wac-input wac-textarea" placeholder="Use {{1}}, {{2}} placeholders exactly as Meta expects."></textarea></label>',
        '      <label class="wac-label">Footer Text<input id="wacDraftFooterText" class="wac-input" type="text" placeholder="Optional footer"></label>',
        '      <label class="wac-label">Sample Variables<input id="wacDraftSampleVariables" class="wac-input" type="text" placeholder="Guest Name, Event Name, 24 Apr 2025, 7:00 PM"></label>',
        '    </div>',
        '    <div id="wacDraftHeaderImageStatus" class="wac-note">For image headers, pick an image to preview it live and keep the Meta media handle for final template submission.</div>',
        '    <div class="wac-panel-head">',
        '      <h4 class="wac-section-title">CTA Buttons</h4>',
        '      <button type="button" id="wacAddDraftButtonBtn" class="wac-btn wac-btn-secondary">Add CTA Button</button>',
        '    </div>',
        '    <div id="wacDraftButtonsRows" class="wac-draft-buttons"></div>',
        '    <div class="wac-note">Use URL CTAs like {{verification_url}} for event registration so guests get tappable verification buttons. URL placeholders are resolved at send time.</div>',
        '    <div class="wac-table-wrap">',
        '      <table class="wac-table">',
        '        <thead><tr><th>Event Variable</th><th>Example Value</th><th>Use In Draft</th></tr></thead>',
        '        <tbody id="wacDraftValueRows"><tr><td colspan="3" class="wac-empty">Select an event to load available values for this draft.</td></tr></tbody>',
        '      </table>',
        '    </div>',
        '    <div class="wac-actions">',
        '      <button type="button" id="wacNewDraftBtn" class="wac-btn wac-btn-secondary">New Draft</button>',
        '      <button type="button" id="wacSaveDraftBtn" class="wac-btn">Save Draft</button>',
        '      <button type="button" id="wacPreviewDraftBtn" class="wac-btn wac-btn-secondary">Preview Draft</button>',
        '      <button type="button" id="wacCompareDraftBtn" class="wac-btn wac-btn-secondary">Compare With Approved</button>',
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
        '    <div class="wac-panel-head">',
        '      <h3 class="wac-section-title">Version Compare</h3>',
        '      <span id="wacCompareMeta" class="wac-meta">Compare an approved template with the editable draft version.</span>',
        '    </div>',
        '    <div id="wacCompareContent" class="wac-empty-box">No comparison loaded yet.</div>',
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
      c.querySelector('#wacPreviewTemplateBtn').addEventListener('click', function () {
        module._previewApprovedTemplate();
      });
      c.querySelector('#wacUseTemplateAsDraftBtn').addEventListener('click', function () {
        module._useApprovedTemplateAsDraft();
      });
      c.querySelector('#wacRunSchedulerBtn').addEventListener('click', function () {
        module._runScheduler();
      });
      c.querySelector('#wacVersionSelect').addEventListener('change', function () {
        module._selectVersion();
      });
      c.querySelector('#wacLoadVersionBtn').addEventListener('click', function () {
        module._loadSelectedVersion();
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
      c.querySelector('#wacPreviewDraftBtn').addEventListener('click', function () {
        module._previewDraft();
      });
      c.querySelector('#wacCompareDraftBtn').addEventListener('click', function () {
        module._compareDraftAgainstApproved();
      });
      c.querySelector('#wacSubmitDraftBtn').addEventListener('click', function () {
        module._submitDraft();
      });
      c.querySelector('#wacTestSendBtn').addEventListener('click', function () {
        module._sendTestMessage();
      });
      c.querySelector('#wacEventSelect').addEventListener('change', function () {
        module._renderSelectedEvent();
        module._refreshActivePreview();
      });
      c.querySelector('#wacTemplateSelect').addEventListener('change', function () {
        module._previewSelectedTemplateLocal();
      });
      c.querySelector('#wacDraftHeaderType').addEventListener('change', function () {
        module._renderDraftHeaderFields();
        module._queueDraftPreview();
      });
      c.querySelector('#wacDraftHeaderImageFile').addEventListener('change', function (event) {
        module._onDraftHeaderImageSelected(event);
      });
      c.querySelector('#wacAddDraftButtonBtn').addEventListener('click', function () {
        module._addDraftButtonRow();
      });
      c.querySelector('#wacDraftButtonsRows').addEventListener('click', function (event) {
        var target = event && event.target ? event.target : null;
        if (!target || !target.getAttribute) return;
        if (target.getAttribute('data-action') === 'remove-draft-button') {
          var row = target.closest ? target.closest('.wac-draft-button-row') : null;
          if (row && row.parentNode) {
            row.parentNode.removeChild(row);
            if (!module._collectDraftButtons().length) {
              module._renderDraftButtonsEditor([]);
            }
            module._queueDraftPreview();
          }
        }
      });
      c.querySelector('#wacDraftButtonsRows').addEventListener('input', function () {
        module._queueDraftPreview();
      });
      c.querySelector('#wacDraftButtonsRows').addEventListener('change', function () {
        module._queueDraftPreview();
      });
      ['#wacDraftName', '#wacDraftTemplateName', '#wacDraftCategory', '#wacDraftLanguageCode', '#wacDraftHeaderText', '#wacDraftMediaHandle', '#wacDraftBodyText', '#wacDraftFooterText', '#wacDraftSampleVariables'].forEach(function (selector) {
        var el = c.querySelector(selector);
        if (!el) return;
        el.addEventListener('input', function () {
          module._queueDraftPreview();
        });
        el.addEventListener('change', function () {
          module._queueDraftPreview();
        });
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
      var versionId = Number((c.querySelector('#wacMappingVersionSelect') || {}).value || 0);
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
            versionId: versionId,
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
      this._renderVersions();
      this._renderDrafts();
      this._renderTemplates();
      this._renderLogs();
      this._renderDraftHeaderFields();
      this._renderPreview(null);
      this._renderCompare(null);
      this._refreshActivePreview();
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
      var mappingVersionSelect = c.querySelector('#wacMappingVersionSelect');
      var templates = Array.isArray(this._workspace.templates) ? this._workspace.templates : [];

      eventSelect.innerHTML = events.map(function (event) {
        return '<option value="' + event.eventKey + '">' + event.label + '</option>';
      }).join('');

      templateSelect.innerHTML = ['<option value="">Select approved template</option>'].concat(templates.map(function (template) {
        var value = template.templateName + '||' + template.languageCode;
        return '<option value="' + value + '">' + template.templateName + ' [' + template.languageCode + ']</option>';
      })).join('');

      mappingVersionSelect.innerHTML = '<option value="0">Select tracked version</option>';

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
      this._renderVersionsForEvent(event.eventKey, event.mapping || {});
      c.querySelector('#wacEventMeta').textContent = event.description + ' Sample variables: ' + (event.sampleVariables || []).join(', ');

      var trigger = event.trigger || {};
      c.querySelector('#wacEventTriggerSummary').innerHTML = [
        '<span class="wac-chip is-ok">Mode: ' + (trigger.triggerMode || '-') + '</span>',
        '<span class="wac-chip is-ok">Source: ' + (trigger.triggerSource || '-') + '</span>',
        '<span class="wac-chip is-ok">Rule: ' + (trigger.scheduleRule || trigger.triggerAction || '-') + '</span>'
      ].join('');

      var valueRows = Array.isArray(event.valueTable) ? event.valueTable : [];
      c.querySelector('#wacEventValueRows').innerHTML = valueRows.length
        ? valueRows.map(function (row) {
          return '<tr>'
            + '<td>' + (row.variableKey || '-') + '</td>'
            + '<td>' + (row.sourceField || '-') + '</td>'
            + '<td>' + (row.exampleValue || '-') + '</td>'
            + '<td>' + (row.description || '-') + '</td>'
            + '</tr>';
        }).join('')
        : '<tr><td colspan="4" class="wac-empty">No variable metadata defined for this event.</td></tr>';
      this._renderDraftValueTable(event);
    },

    _renderDraftValueTable: function (event) {
      var rowsEl = this._container.querySelector('#wacDraftValueRows');
      var rows = event && Array.isArray(event.valueTable) ? event.valueTable : [];
      rowsEl.innerHTML = rows.length
        ? rows.map(function (row) {
          var placeholder = row.variableKey ? ('{{' + row.variableKey + '}}') : '-';
          return '<tr>'
            + '<td>' + (row.variableKey || '-') + '</td>'
            + '<td>' + (row.exampleValue || '-') + '</td>'
            + '<td>' + placeholder + '</td>'
            + '</tr>';
        }).join('')
        : '<tr><td colspan="3" class="wac-empty">No event values available for this draft yet.</td></tr>';
    },

    _renderVersions: function () {
      var selectedKey = String((this._container.querySelector('#wacEventSelect') || {}).value || '').trim();
      if (!selectedKey) return;
      var event = this._findEventByKey(selectedKey);
      this._renderVersionsForEvent(selectedKey, event && event.mapping ? event.mapping : {});
    },

    _versionsForEvent: function (eventKey) {
      var versions = Array.isArray(this._workspace && this._workspace.versions) ? this._workspace.versions : [];
      return versions.filter(function (version) {
        return String(version.eventKey || '') === String(eventKey || '');
      });
    },

    _renderVersionsForEvent: function (eventKey, mapping) {
      var c = this._container;
      var versions = this._versionsForEvent(eventKey);
      var versionSelect = c.querySelector('#wacVersionSelect');
      var mappingSelect = c.querySelector('#wacMappingVersionSelect');
      var rowsEl = c.querySelector('#wacVersionRows');
      var selectedVersionId = Number(mapping && mapping.mappedVersionId ? mapping.mappedVersionId : (this._selectedVersionId || 0));

      versionSelect.innerHTML = ['<option value="0">Select tracked version</option>'].concat(versions.map(function (version) {
        return '<option value="' + version.id + '">' + (version.versionLabel || ('Version ' + version.id)) + ' [' + (version.metaStatus || 'draft') + ']</option>';
      })).join('');

      mappingSelect.innerHTML = ['<option value="0">Select tracked version</option>'].concat(versions.map(function (version) {
        return '<option value="' + version.id + '">' + (version.versionLabel || ('Version ' + version.id)) + ' → ' + (version.templateName || '-') + ' [' + (version.languageCode || '-') + ']</option>';
      })).join('');

      if (selectedVersionId > 0) {
        versionSelect.value = String(selectedVersionId);
        mappingSelect.value = String(selectedVersionId);
        this._selectedVersionId = selectedVersionId;
      }

      rowsEl.innerHTML = versions.length
        ? versions.map(function (version) {
          return '<tr>'
            + '<td>' + (version.versionLabel || ('Version ' + version.id)) + '</td>'
            + '<td>' + ((version.templateName || '-') + (version.languageCode ? ' [' + version.languageCode + ']' : '')) + '</td>'
            + '<td>' + (version.metaStatus || '-') + '</td>'
            + '<td>' + (version.isCurrent ? 'Yes' : 'No') + '</td>'
            + '<td>' + (version.updatedAt || '-') + '</td>'
            + '<td>' + (version.metaTemplateUid || '-') + '</td>'
            + '</tr>';
        }).join('')
        : '<tr><td colspan="6" class="wac-empty">No tracked versions for this event yet.</td></tr>';
    },

    _previewApprovedTemplate: async function () {
      if (!this._previewSelectedTemplateLocal()) {
        this._setStatus('Select an event and approved template before previewing.', 'error');
        return;
      }
      this._setStatus('Approved template preview generated.', 'success');
    },

    _useApprovedTemplateAsDraft: function () {
      var c = this._container;
      var templateValue = String((c.querySelector('#wacTemplateSelect') || {}).value || '');
      var parts = templateValue.split('||');
      var templateName = parts[0] || '';
      var languageCode = parts[1] || '';
      var selectedEventKey = String((c.querySelector('#wacEventSelect') || {}).value || '').trim();
      var event = this._findEventByKey(selectedEventKey);
      var template = this._findTemplateByNameLanguage(templateName, languageCode);

      if (!template) {
        this._setStatus('Select an approved template before loading it into the draft editor.', 'error');
        return;
      }

      this._selectedDraftId = 0;
      this._selectedVersionId = 0;
      this._clearDraftHeaderImagePreview();
      this._fillDraftForm(this._draftFromTemplate(template, event));
      c.querySelector('#wacDraftSelect').value = '0';
      c.querySelector('#wacVersionSelect').value = '0';
      this._queueDraftPreview();
      this._setStatus('Approved template loaded into the draft editor. You can now edit, preview, and submit it as a new version.', 'success');
    },

    _previewDraft: async function () {
      if (!this._hasDraftContent()) {
        this._setStatus('Select an event and enter at least a template name and body text before previewing a draft.', 'error');
        return;
      }
      this._renderLocalDraftPreview();
      this._setStatus('Draft preview generated.', 'success');
    },

    _renderPreview: function (preview) {
      var c = this._container;
      if (!c) return;

      var previewMeta = c.querySelector('#wacPreviewMeta');
      var previewWarnings = c.querySelector('#wacPreviewWarnings');
      var previewMedia = c.querySelector('#wacPreviewMedia');
      var previewHeader = c.querySelector('#wacPreviewHeader');
      var previewBody = c.querySelector('#wacPreviewBody');
      var previewFooter = c.querySelector('#wacPreviewFooter');
      var previewButtons = c.querySelector('#wacPreviewButtons');
      var previewFormat = c.querySelector('#wacPreviewFormat');
      var previewPlaceholders = c.querySelector('#wacPreviewPlaceholders');
      var previewValueRows = c.querySelector('#wacPreviewValueRows');

      if (!preview) {
        previewMeta.textContent = 'Choose an event and template to preview.';
        previewWarnings.innerHTML = '';
        previewMedia.innerHTML = '';
        previewHeader.textContent = '';
        previewBody.textContent = 'No preview loaded.';
        previewFooter.textContent = '';
        previewButtons.innerHTML = '';
        previewFormat.textContent = '-';
        previewPlaceholders.textContent = '-';
        previewValueRows.innerHTML = '<tr><td colspan="3" class="wac-empty">Preview values will appear here.</td></tr>';
        return;
      }

      previewMeta.textContent = (preview.templateName || '-') + (preview.languageCode ? (' [' + preview.languageCode + ']') : '') + ' · ' + (preview.sourceType || 'preview');
      previewWarnings.innerHTML = Array.isArray(preview.warnings) && preview.warnings.length
        ? preview.warnings.map(function (warning) {
          return '<div class="wac-warning">' + warning + '</div>';
        }).join('')
        : '';

      var headerImageUrl = preview.rendered && preview.rendered.headerImageUrl ? preview.rendered.headerImageUrl : '';
      previewMedia.innerHTML = headerImageUrl
        ? '<img class="wac-preview-image" src="' + this._escapeAttribute(headerImageUrl) + '" alt="Preview header image">'
        : '';

      previewHeader.textContent = preview.rendered && preview.rendered.header ? preview.rendered.header : '';
      previewBody.textContent = preview.rendered && preview.rendered.body ? preview.rendered.body : 'Template has no body preview.';
      previewFooter.textContent = preview.rendered && preview.rendered.footer ? preview.rendered.footer : '';

      var buttons = preview.rendered && Array.isArray(preview.rendered.buttons) ? preview.rendered.buttons : [];
      previewButtons.innerHTML = buttons.map(function (button) {
        var suffix = button.value ? ('<span class="wac-preview-button-meta">' + button.value + '</span>') : '';
        return '<div class="wac-preview-button"><strong>' + (button.text || button.type || 'Button') + '</strong>' + suffix + '</div>';
      }).join('');

      previewFormat.textContent = (preview.placeholderSummary && preview.placeholderSummary.parameterFormat) || 'none';
      previewPlaceholders.textContent = preview.placeholderSummary && Array.isArray(preview.placeholderSummary.placeholders) && preview.placeholderSummary.placeholders.length
        ? preview.placeholderSummary.placeholders.join(', ')
        : 'none';

      var rows = Array.isArray(preview.valueTable) ? preview.valueTable : [];
      previewValueRows.innerHTML = rows.length
        ? rows.map(function (row) {
          return '<tr>'
            + '<td>' + (row.variableKey || '-') + '</td>'
            + '<td>' + (row.exampleValue || '-') + '</td>'
            + '<td>' + (row.required ? 'Yes' : 'No') + '</td>'
            + '</tr>';
        }).join('')
        : '<tr><td colspan="3" class="wac-empty">No preview values available.</td></tr>';
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
      this._selectedVersionId = this._resolveVersionIdForDraft(selected);
      this._clearDraftHeaderImagePreview();
      this._fillDraftForm(this._findDraftById(selected));
      this._queueDraftPreview();
    },

    _findDraftById: function (id) {
      var drafts = Array.isArray(this._workspace && this._workspace.drafts) ? this._workspace.drafts : [];
      for (var i = 0; i < drafts.length; i += 1) {
        if (Number(drafts[i].id || 0) === Number(id || 0)) return drafts[i];
      }
      return null;
    },

    _findEventByKey: function (eventKey) {
      var events = Array.isArray(this._workspace && this._workspace.events) ? this._workspace.events : [];
      for (var i = 0; i < events.length; i += 1) {
        if (String(events[i].eventKey || '') === String(eventKey || '')) return events[i];
      }
      return null;
    },

    _findTemplateByNameLanguage: function (templateName, languageCode) {
      var templates = Array.isArray(this._workspace && this._workspace.templates) ? this._workspace.templates : [];
      for (var i = 0; i < templates.length; i += 1) {
        var template = templates[i];
        if (String(template.templateName || '') === String(templateName || '') && String(template.languageCode || '') === String(languageCode || '')) {
          return template;
        }
      }
      return null;
    },

    _findVersionById: function (id) {
      var versions = Array.isArray(this._workspace && this._workspace.versions) ? this._workspace.versions : [];
      for (var i = 0; i < versions.length; i += 1) {
        if (Number(versions[i].id || 0) === Number(id || 0)) return versions[i];
      }
      return null;
    },

    _resolveVersionIdForDraft: function (draftId) {
      var eventKey = String((this._container.querySelector('#wacEventSelect') || {}).value || '').trim();
      var versions = this._versionsForEvent(eventKey);
      for (var i = 0; i < versions.length; i += 1) {
        if (Number(versions[i].sourceDraftId || 0) === Number(draftId || 0)) return Number(versions[i].id || 0);
      }
      return 0;
    },

    _selectVersion: function () {
      this._selectedVersionId = Number((this._container.querySelector('#wacVersionSelect') || {}).value || 0);
    },

    _loadSelectedVersion: function () {
      var version = this._findVersionById(this._selectedVersionId);
      if (!version) {
        this._setStatus('Select a tracked version before loading it into the editor.', 'error');
        return;
      }
      this._clearDraftHeaderImagePreview();
      this._fillDraftForm(version);
      this._queueDraftPreview();
      this._setStatus('Tracked version loaded into the editor.', 'success');
    },

    _draftFromTemplate: function (template, event) {
      var components = Array.isArray(template && template.components) ? template.components : [];
      var headerType = 'NONE';
      var headerText = '';
      var exampleMediaHandle = '';
      var bodyText = '';
      var footerText = '';
      var buttons = [];
      var sampleVariables = [];

      components.forEach(function (component) {
        if (!component || typeof component !== 'object') return;
        var type = String(component.type || '').toUpperCase();
        if (type === 'HEADER') {
          headerType = String(component.format || 'TEXT').toUpperCase();
          if (headerType === 'TEXT') {
            headerText = String(component.text || '');
          } else if (headerType === 'IMAGE') {
            var handle = component.example && Array.isArray(component.example.header_handle) ? component.example.header_handle[0] : '';
            exampleMediaHandle = String(handle || '');
          }
          return;
        }
        if (type === 'BODY') {
          bodyText = String(component.text || '');
          if (component.example && Array.isArray(component.example.body_text) && Array.isArray(component.example.body_text[0])) {
            sampleVariables = component.example.body_text[0].slice();
          }
          return;
        }
        if (type === 'FOOTER') {
          footerText = String(component.text || '');
          return;
        }
        if (type === 'BUTTONS' && Array.isArray(component.buttons)) {
          buttons = component.buttons.map(function (button) {
            return {
              type: button.type || '',
              text: button.text || '',
              url: button.url || '',
              phoneNumber: button.phone_number || ''
            };
          });
        }
      });

      if (!sampleVariables.length && event && Array.isArray(event.valueTable)) {
        sampleVariables = event.valueTable.map(function (row) { return row.exampleValue || ''; }).filter(Boolean);
      }

      return {
        id: 0,
        versionId: 0,
        draftName: (event && event.eventKey ? event.eventKey + '_v2' : (template.templateName || 'template')),
        templateName: String(template.templateName || ''),
        category: String(template.category || 'UTILITY'),
        languageCode: String(template.languageCode || 'en'),
        headerType: headerType,
        headerText: headerText,
        bodyText: bodyText,
        footerText: footerText,
        buttons: buttons,
        sampleVariables: sampleVariables,
        exampleMediaHandle: exampleMediaHandle
      };
    },

    _fillDraftForm: function (draft) {
      var c = this._container;
      if (!draft) {
        return;
      }
      this._selectedVersionId = Number(draft.versionId || draft.id || 0);
      if (c.querySelector('#wacVersionSelect')) {
        c.querySelector('#wacVersionSelect').value = this._selectedVersionId ? String(this._selectedVersionId) : '0';
      }
      c.querySelector('#wacDraftName').value = draft.draftName || '';
      c.querySelector('#wacDraftTemplateName').value = draft.templateName || '';
      c.querySelector('#wacDraftCategory').value = draft.category || 'UTILITY';
      c.querySelector('#wacDraftLanguageCode').value = draft.languageCode || 'en';
      c.querySelector('#wacDraftHeaderType').value = draft.headerType || 'NONE';
      c.querySelector('#wacDraftHeaderText').value = draft.headerText || '';
      c.querySelector('#wacDraftMediaHandle').value = draft.exampleMediaHandle || '';
      c.querySelector('#wacDraftBodyText').value = draft.bodyText || '';
      c.querySelector('#wacDraftFooterText').value = draft.footerText || '';
      c.querySelector('#wacDraftSampleVariables').value = Array.isArray(draft.sampleVariables) ? draft.sampleVariables.join(', ') : '';
      this._renderDraftButtonsEditor(Array.isArray(draft.buttons) ? draft.buttons : []);
      if (c.querySelector('#wacDraftHeaderImageFile')) {
        c.querySelector('#wacDraftHeaderImageFile').value = '';
      }
      this._renderDraftHeaderFields();
    },

    _clearDraftForm: function () {
      this._selectedDraftId = 0;
      this._selectedVersionId = 0;
      this._clearDraftHeaderImagePreview();
      var c = this._container;
      ['#wacDraftName', '#wacDraftTemplateName', '#wacDraftHeaderText', '#wacDraftMediaHandle', '#wacDraftBodyText', '#wacDraftFooterText', '#wacDraftSampleVariables'].forEach(function (selector) {
        var el = c.querySelector(selector);
        if (el) el.value = '';
      });
      c.querySelector('#wacDraftCategory').value = 'UTILITY';
      c.querySelector('#wacDraftLanguageCode').value = 'en';
      c.querySelector('#wacDraftHeaderType').value = 'NONE';
      c.querySelector('#wacDraftSelect').value = '0';
      c.querySelector('#wacVersionSelect').value = '0';
      this._renderDraftButtonsEditor([]);
      if (c.querySelector('#wacDraftHeaderImageFile')) {
        c.querySelector('#wacDraftHeaderImageFile').value = '';
      }
      this._renderDraftHeaderFields();
      this._refreshActivePreview();
    },

    _renderDraftButtonsEditor: function (buttons) {
      var rowsEl = this._container && this._container.querySelector('#wacDraftButtonsRows');
      var rows = Array.isArray(buttons) ? buttons.filter(function (button) { return button && typeof button === 'object'; }) : [];
      if (!rowsEl) return;

      if (!rows.length) {
        rowsEl.innerHTML = '<div class="wac-empty-box">No CTA buttons yet. Add a URL button for verification, location, or support.</div>';
        return;
      }

      rowsEl.innerHTML = rows.map(function (button, index) {
        var type = String(button.type || '').toUpperCase();
        var value = String(button.url || button.phoneNumber || button.phone_number || '');
        return '<div class="wac-draft-button-row" data-index="' + index + '">'
          + '<label class="wac-label">Type<select class="wac-input" data-role="draft-button-type"><option value="URL"' + (type === 'URL' ? ' selected' : '') + '>URL</option><option value="PHONE_NUMBER"' + (type === 'PHONE_NUMBER' ? ' selected' : '') + '>PHONE_NUMBER</option></select></label>'
          + '<label class="wac-label">Text<input class="wac-input" data-role="draft-button-text" type="text" placeholder="Verify Booking" value="' + this._escapeAttribute(button.text || '') + '"></label>'
          + '<label class="wac-label">URL / Phone<input class="wac-input" data-role="draft-button-value" type="text" placeholder="Use {{verification_url}} for the verification CTA" value="' + this._escapeAttribute(value) + '"></label>'
          + '<button type="button" class="wac-btn wac-btn-secondary" data-action="remove-draft-button">Remove</button>'
          + '</div>';
      }, this).join('');
    },

    _defaultDraftButton: function () {
      return {
        type: 'URL',
        text: 'Verify Booking',
        url: '{{verification_url}}'
      };
    },

    _addDraftButtonRow: function () {
      var buttons = this._collectDraftButtons();
      buttons.push(this._defaultDraftButton());
      this._renderDraftButtonsEditor(buttons);
      this._queueDraftPreview();
    },

    _collectDraftButtons: function () {
      var rowsEl = this._container && this._container.querySelector('#wacDraftButtonsRows');
      var rowNodes = rowsEl ? rowsEl.querySelectorAll('.wac-draft-button-row') : [];
      var buttons = [];
      for (var i = 0; i < rowNodes.length; i += 1) {
        var row = rowNodes[i];
        var type = String((row.querySelector('[data-role="draft-button-type"]') || {}).value || '').trim().toUpperCase();
        var text = String((row.querySelector('[data-role="draft-button-text"]') || {}).value || '').trim();
        var value = String((row.querySelector('[data-role="draft-button-value"]') || {}).value || '').trim();
        if (!type || !text || !value) {
          continue;
        }
        buttons.push(type === 'PHONE_NUMBER'
          ? { type: 'PHONE_NUMBER', text: text, phoneNumber: value }
          : { type: 'URL', text: text, url: value });
      }
      return buttons;
    },

    _collectDraftPayload: function () {
      var c = this._container;
      var headerType = String((c.querySelector('#wacDraftHeaderType') || {}).value || 'NONE').trim();
      var headerText = String((c.querySelector('#wacDraftHeaderText') || {}).value || '').trim();
      var mediaHandle = String((c.querySelector('#wacDraftMediaHandle') || {}).value || '').trim();
      var buttons = this._collectDraftButtons();
      return {
        id: this._selectedDraftId || 0,
        versionId: this._selectedVersionId || 0,
        eventKey: String((c.querySelector('#wacEventSelect') || {}).value || '').trim(),
        draftName: String((c.querySelector('#wacDraftName') || {}).value || '').trim(),
        templateName: String((c.querySelector('#wacDraftTemplateName') || {}).value || '').trim(),
        category: String((c.querySelector('#wacDraftCategory') || {}).value || 'UTILITY').trim(),
        languageCode: String((c.querySelector('#wacDraftLanguageCode') || {}).value || 'en').trim(),
        headerType: headerType,
        headerText: headerType === 'TEXT' ? headerText : '',
        exampleMediaHandle: headerType === 'IMAGE' ? mediaHandle : '',
        bodyText: String((c.querySelector('#wacDraftBodyText') || {}).value || '').trim(),
        footerText: String((c.querySelector('#wacDraftFooterText') || {}).value || '').trim(),
        sampleVariables: String((c.querySelector('#wacDraftSampleVariables') || {}).value || '').split(',').map(function (item) {
          return String(item || '').trim();
        }).filter(Boolean),
        buttons: buttons
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
        this._selectedVersionId = payload && payload.result && payload.result.versionId ? Number(payload.result.versionId) : this._selectedVersionId;
        this._selectedDraftId = this._resolveDraftIdByName(draft.templateName);
        this._renderWorkspace();
        this._queueDraftPreview();
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

    _compareDraftAgainstApproved: function () {
      var c = this._container;
      var templateValue = String((c.querySelector('#wacTemplateSelect') || {}).value || '');
      var parts = templateValue.split('||');
      var template = this._findTemplateByNameLanguage(parts[0] || '', parts[1] || '');
      var event = this._findEventByKey(String((c.querySelector('#wacEventSelect') || {}).value || '').trim());
      var draft = this._collectDraftPayload();

      if (!template) {
        this._setStatus('Select an approved template before running a compare.', 'error');
        return;
      }
      if (!draft.templateName || !draft.bodyText) {
        this._setStatus('Enter or load a draft version before comparing.', 'error');
        return;
      }

      var approved = this._draftFromTemplate(template, event);
      var rows = [
        this._buildCompareRow('Template Name', approved.templateName || '-', draft.templateName || '-'),
        this._buildCompareRow('Language', approved.languageCode || '-', draft.languageCode || '-'),
        this._buildCompareRow('Header Type', approved.headerType || '-', draft.headerType || '-'),
        this._buildCompareRow('Header Value', approved.headerType === 'IMAGE' ? (approved.exampleMediaHandle || '-') : (approved.headerText || '-'), draft.headerType === 'IMAGE' ? (draft.exampleMediaHandle || '-') : (draft.headerText || '-')),
        this._buildCompareRow('Body', approved.bodyText || '-', draft.bodyText || '-'),
        this._buildCompareRow('Footer', approved.footerText || '-', draft.footerText || '-'),
        this._buildCompareRow('Sample Values', Array.isArray(approved.sampleVariables) ? approved.sampleVariables.join(', ') : '-', Array.isArray(draft.sampleVariables) ? draft.sampleVariables.join(', ') : '-'),
        this._buildCompareRow('Buttons', JSON.stringify(approved.buttons || []), JSON.stringify(draft.buttons || []))
      ];

      this._renderCompare({
        approved: approved,
        draft: draft,
        rows: rows,
        templateName: template.templateName || '-',
        languageCode: template.languageCode || '-'
      });
      this._setStatus('Version compare generated.', 'success');
    },

    _buildCompareRow: function (label, approvedValue, draftValue) {
      return {
        label: label,
        approvedValue: approvedValue,
        draftValue: draftValue,
        changed: String(approvedValue || '') !== String(draftValue || '')
      };
    },

    _renderCompare: function (compare) {
      var metaEl = this._container.querySelector('#wacCompareMeta');
      var contentEl = this._container.querySelector('#wacCompareContent');
      if (!metaEl || !contentEl) return;

      if (!compare) {
        metaEl.textContent = 'Compare an approved template with the editable draft version.';
        contentEl.className = 'wac-empty-box';
        contentEl.textContent = 'No comparison loaded yet.';
        return;
      }

      metaEl.textContent = (compare.templateName || '-') + ' [' + (compare.languageCode || '-') + '] vs current draft version';
      contentEl.className = 'wac-compare-layout';
      contentEl.innerHTML = ''
        + '<div class="wac-compare-card">'
        +   '<h4 class="wac-compare-title">Approved Template</h4>'
        +   '<div class="wac-compare-block">' + this._escapeHtml(compare.approved.bodyText || '-') + '</div>'
        + '</div>'
        + '<div class="wac-compare-card">'
        +   '<h4 class="wac-compare-title">Editable Draft Version</h4>'
        +   '<div class="wac-compare-block">' + this._escapeHtml(compare.draft.bodyText || '-') + '</div>'
        + '</div>'
        + '<div class="wac-table-wrap wac-compare-table">'
        +   '<table class="wac-table">'
        +     '<thead><tr><th>Field</th><th>Approved</th><th>Draft Version</th><th>Changed</th></tr></thead>'
        +     '<tbody>' + compare.rows.map(function (row) {
              return '<tr>'
                + '<td>' + row.label + '</td>'
                + '<td>' + this._escapeHtml(row.approvedValue || '-') + '</td>'
                + '<td>' + this._escapeHtml(row.draftValue || '-') + '</td>'
                + '<td>' + (row.changed ? 'Yes' : 'No') + '</td>'
                + '</tr>';
            }, this).join('') + '</tbody>'
        +   '</table>'
        + '</div>';
    },

    _escapeHtml: function (value) {
      return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
        .replace(/\n/g, '<br>');
    },

    _escapeAttribute: function (value) {
      return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
    },

    _renderDraftHeaderFields: function () {
      var c = this._container;
      if (!c) return;
      var headerType = String((c.querySelector('#wacDraftHeaderType') || {}).value || 'NONE').trim().toUpperCase();
      var textWrap = c.querySelector('#wacDraftHeaderTextWrap');
      var mediaWrap = c.querySelector('#wacDraftMediaHandleWrap');
      var imageWrap = c.querySelector('#wacDraftHeaderImageWrap');
      var statusEl = c.querySelector('#wacDraftHeaderImageStatus');

      if (textWrap) textWrap.style.display = headerType === 'TEXT' ? '' : 'none';
      if (mediaWrap) mediaWrap.style.display = headerType === 'IMAGE' ? '' : 'none';
      if (imageWrap) imageWrap.style.display = headerType === 'IMAGE' ? '' : 'none';

      if (statusEl) {
        if (headerType === 'IMAGE') {
          statusEl.textContent = this._draftHeaderImagePreviewUrl
            ? 'Header image selected for live preview. Keep the Meta media handle filled in before submitting the template to Meta.'
            : 'Pick an image to preview it live. The Meta media handle field is still used when the template is submitted.';
        } else if (headerType === 'TEXT') {
          statusEl.textContent = 'Text headers appear above the body in the WhatsApp-style preview.';
        } else {
          statusEl.textContent = 'Select TEXT or IMAGE to configure a header for this template.';
        }
      }
    },

    _onDraftHeaderImageSelected: function (event) {
      var input = event && event.target ? event.target : null;
      var file = input && input.files && input.files[0] ? input.files[0] : null;
      this._clearDraftHeaderImagePreview();
      if (!file) {
        this._renderDraftHeaderFields();
        this._queueDraftPreview();
        return;
      }
      this._draftHeaderImagePreviewUrl = window.URL && typeof window.URL.createObjectURL === 'function'
        ? window.URL.createObjectURL(file)
        : '';
      this._renderDraftHeaderFields();
      this._queueDraftPreview();
    },

    _clearDraftHeaderImagePreview: function () {
      if (this._draftHeaderImagePreviewUrl && window.URL && typeof window.URL.revokeObjectURL === 'function') {
        window.URL.revokeObjectURL(this._draftHeaderImagePreviewUrl);
      }
      this._draftHeaderImagePreviewUrl = '';
    },

    _hasDraftContent: function () {
      var draft = this._collectDraftPayload();
      return !!(draft.templateName || draft.bodyText || draft.headerText || draft.exampleMediaHandle || this._draftHeaderImagePreviewUrl || (Array.isArray(draft.buttons) && draft.buttons.length));
    },

    _queueDraftPreview: function () {
      var module = this;
      if (module._draftPreviewTimer) {
        window.clearTimeout(module._draftPreviewTimer);
      }
      module._draftPreviewTimer = window.setTimeout(function () {
        module._draftPreviewTimer = null;
        module._refreshActivePreview();
      }, 120);
    },

    _refreshActivePreview: function () {
      if (this._hasDraftContent()) {
        this._renderLocalDraftPreview();
        return;
      }
      if (this._previewSelectedTemplateLocal()) {
        return;
      }
      this._renderPreview(null);
    },

    _previewSelectedTemplateLocal: function () {
      var c = this._container;
      var selectedEventKey = String((c.querySelector('#wacEventSelect') || {}).value || '').trim();
      var templateValue = String((c.querySelector('#wacTemplateSelect') || {}).value || '');
      var parts = templateValue.split('||');
      var template = this._findTemplateByNameLanguage(parts[0] || '', parts[1] || '');
      var event = this._findEventByKey(selectedEventKey);
      if (!template || !event) {
        return false;
      }
      this._renderPreview(this._buildPreviewFromTemplate(template, event));
      return true;
    },

    _renderLocalDraftPreview: function () {
      var eventKey = String((this._container.querySelector('#wacEventSelect') || {}).value || '').trim();
      var event = this._findEventByKey(eventKey);
      if (!event) {
        this._renderPreview(null);
        return;
      }
      this._renderPreview(this._buildPreviewFromDraft(this._collectDraftPayload(), event));
    },

    _buildPreviewFromTemplate: function (template, event) {
      var components = Array.isArray(template && template.components) ? template.components : [];
      return this._composePreview({
        event: event,
        templateName: template.templateName || '',
        languageCode: template.languageCode || '',
        category: template.category || '',
        status: template.status || '',
        sourceType: 'approved_template',
        components: components,
        sampleVariables: event && Array.isArray(event.valueTable) ? event.valueTable.map(function (row) { return row.exampleValue || ''; }).filter(Boolean) : [],
        exampleMediaHandle: '',
        localHeaderImageUrl: ''
      });
    },

    _buildPreviewFromDraft: function (draft, event) {
      var components = [];
      var headerType = String(draft.headerType || 'NONE').toUpperCase();
      if (headerType === 'TEXT' && draft.headerText) {
        components.push({ type: 'HEADER', format: 'TEXT', text: draft.headerText });
      } else if (headerType === 'IMAGE') {
        components.push({ type: 'HEADER', format: 'IMAGE' });
      }
      components.push({ type: 'BODY', text: draft.bodyText || '' });
      if (draft.footerText) {
        components.push({ type: 'FOOTER', text: draft.footerText });
      }
      if (Array.isArray(draft.buttons) && draft.buttons.length) {
        components.push({ type: 'BUTTONS', buttons: draft.buttons.map(function (button) {
          var next = { type: button.type || '', text: button.text || '' };
          if (button.url) next.url = button.url;
          if (button.phoneNumber || button.phone_number) next.phone_number = button.phoneNumber || button.phone_number;
          return next;
        }) });
      }
      return this._composePreview({
        event: event,
        templateName: draft.templateName || '',
        languageCode: draft.languageCode || '',
        category: draft.category || '',
        status: 'draft',
        sourceType: 'draft',
        components: components,
        sampleVariables: Array.isArray(draft.sampleVariables) ? draft.sampleVariables : [],
        exampleMediaHandle: draft.exampleMediaHandle || '',
        localHeaderImageUrl: this._draftHeaderImagePreviewUrl || ''
      });
    },

    _composePreview: function (options) {
      var event = options.event || {};
      var components = Array.isArray(options.components) ? options.components : [];
      var valueTable = Array.isArray(event.valueTable) ? event.valueTable : [];
      var namedValues = {};
      valueTable.forEach(function (row) {
        if (row && row.variableKey) {
          namedValues[String(row.variableKey)] = String(row.exampleValue || '');
        }
      });
      var positionalValues = Array.isArray(options.sampleVariables) && options.sampleVariables.length
        ? options.sampleVariables.slice()
        : valueTable.map(function (row) { return row.exampleValue || ''; }).filter(Boolean);

      var rendered = {
        header: '',
        headerImageUrl: '',
        body: '',
        footer: '',
        buttons: []
      };
      var warnings = [];
      var placeholders = [];
      var module = this;

      components.forEach(function (component) {
        if (!component || typeof component !== 'object') return;
        var type = String(component.type || '').toUpperCase();
        if (type === 'HEADER') {
          var format = String(component.format || '').toUpperCase();
          if (format === 'IMAGE') {
            rendered.headerImageUrl = module._resolvePreviewHeaderImage(event, options.exampleMediaHandle, options.localHeaderImageUrl);
            rendered.header = rendered.headerImageUrl ? 'Header image' : (options.exampleMediaHandle ? ('Meta handle: ' + options.exampleMediaHandle) : 'Image header configured');
          } else {
            rendered.header = module._renderPreviewText(String(component.text || ''), positionalValues, namedValues, placeholders, warnings);
          }
          return;
        }
        if (type === 'BODY') {
          rendered.body = module._renderPreviewText(String(component.text || ''), positionalValues, namedValues, placeholders, warnings);
          return;
        }
        if (type === 'FOOTER') {
          rendered.footer = String(component.text || '');
          return;
        }
        if (type === 'BUTTONS' && Array.isArray(component.buttons)) {
          rendered.buttons = component.buttons.map(function (button) {
            return {
              type: String(button.type || '').toUpperCase(),
              text: String(button.text || ''),
              value: module._renderPreviewText(String(button.url || button.phone_number || ''), positionalValues, namedValues, placeholders, warnings)
            };
          });
        }
      });

      return {
        eventKey: event.eventKey || '',
        event: event.trigger || {},
        sourceType: options.sourceType || 'preview',
        templateName: options.templateName || '',
        languageCode: options.languageCode || '',
        category: options.category || '',
        status: options.status || '',
        components: components,
        rendered: rendered,
        valueTable: valueTable,
        warnings: warnings.filter(Boolean).filter(function (value, index, list) { return list.indexOf(value) === index; }),
        placeholderSummary: {
          parameterFormat: this._detectParameterFormat(placeholders),
          placeholders: placeholders.filter(function (value, index, list) { return list.indexOf(value) === index; }),
          placeholderCount: placeholders.filter(function (value, index, list) { return list.indexOf(value) === index; }).length
        }
      };
    },

    _renderPreviewText: function (text, positionalValues, namedValues, placeholders, warnings) {
      var rendered = String(text || '');
      if (!rendered) return '';

      rendered = rendered.replace(/\{\{\s*([a-z0-9_]+)\s*\}\}/ig, function (match, token) {
        var key = String(token || '').trim();
        placeholders.push('{{' + key + '}}');
        if (/^\d+$/.test(key)) {
          return match;
        }
        if (Object.prototype.hasOwnProperty.call(namedValues, key) && namedValues[key]) {
          return namedValues[key];
        }
        warnings.push('No sample value found for named parameter ' + key + '.');
        return '[' + key + ']';
      });

      rendered = rendered.replace(/\{\{\s*(\d+)\s*\}\}/g, function (match, token) {
        var index = Number(token || 0);
        var resolved = index > 0 ? (positionalValues[index - 1] || '') : '';
        placeholders.push('{{' + index + '}}');
        if (resolved) {
          return resolved;
        }
        warnings.push('No sample value found for positional parameter ' + index + '.');
        return '[' + index + ']';
      });

      return rendered;
    },

    _detectParameterFormat: function (placeholders) {
      var hasNamed = false;
      var hasPositional = false;
      (placeholders || []).forEach(function (placeholder) {
        if (/\{\{\s*\d+\s*\}\}/.test(String(placeholder || ''))) {
          hasPositional = true;
        } else if (/\{\{\s*[a-z_][a-z0-9_]*\s*\}\}/i.test(String(placeholder || ''))) {
          hasNamed = true;
        }
      });
      if (hasNamed && hasPositional) return 'mixed';
      if (hasNamed) return 'named';
      if (hasPositional) return 'positional';
      return 'none';
    },

    _resolvePreviewHeaderImage: function (event, exampleMediaHandle, localHeaderImageUrl) {
      if (localHeaderImageUrl) {
        return localHeaderImageUrl;
      }
      if (/^https?:\/\//i.test(String(exampleMediaHandle || ''))) {
        return String(exampleMediaHandle || '');
      }
      var valueTable = event && Array.isArray(event.valueTable) ? event.valueTable : [];
      for (var i = 0; i < valueTable.length; i += 1) {
        if (String(valueTable[i].variableKey || '') === 'header_image_url' && valueTable[i].exampleValue) {
          return String(valueTable[i].exampleValue || '');
        }
      }
      return '';
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
        '.wac-inline-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}',
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
        '.wac-draft-buttons{display:grid;gap:12px;margin-top:12px;}',
        '.wac-draft-button-row{display:grid;grid-template-columns:160px minmax(180px,1fr) minmax(220px,1.2fr) auto;gap:12px;align-items:end;padding:12px;border:1px solid rgba(123,94,67,0.14);border-radius:12px;background:#faf6ef;}',
        '.wac-preview-layout{display:grid;grid-template-columns:minmax(280px,380px) minmax(260px,1fr);gap:16px;align-items:start;}',
        '.wac-preview-card{border:1px solid rgba(123,94,67,0.14);border-radius:12px;background:#faf6ef;padding:14px;}',
        '.wac-preview-phone{background:linear-gradient(180deg,#e8f3eb 0%,#dbe9df 100%);border:1px solid rgba(123,94,67,0.18);border-radius:22px;padding:14px;box-shadow:0 12px 26px rgba(80,57,36,0.07);display:grid;gap:10px;}',
        '.wac-preview-phone-head{display:flex;align-items:center;gap:10px;padding-bottom:4px;}',
        '.wac-preview-avatar{width:34px;height:34px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:#214038;color:#fff;font-size:0.72rem;font-weight:800;}',
        '.wac-preview-contact{font-size:0.82rem;font-weight:800;color:#214038;}',
        '.wac-preview-contact-sub{font-size:0.72rem;color:#5f6f66;}',
        '.wac-preview-media{display:grid;gap:8px;}',
        '.wac-preview-image{display:block;width:100%;max-height:220px;object-fit:cover;border-radius:14px;border:1px solid rgba(123,94,67,0.14);}',
        '.wac-preview-header{font-weight:700;color:#214038;}',
        '.wac-preview-body{white-space:pre-wrap;line-height:1.55;color:#3e3128;background:#fff;border-radius:16px 16px 16px 4px;padding:12px 14px;box-shadow:0 6px 16px rgba(80,57,36,0.06);}',
        '.wac-preview-footer{font-size:0.8rem;color:#7d6a59;padding:0 2px;}',
        '.wac-preview-buttons{display:grid;gap:8px;}',
        '.wac-preview-button{padding:10px 12px;border-radius:12px;background:#fff;border:1px solid rgba(123,94,67,0.14);display:grid;gap:4px;}',
        '.wac-preview-button-meta{font-size:0.76rem;color:#7d6a59;word-break:break-all;}',
        '.wac-preview-time{justify-self:end;font-size:0.72rem;color:#5f6f66;padding-right:4px;}',
        '.wac-preview-summary{display:grid;gap:6px;margin-bottom:12px;font-size:0.85rem;color:#5f4d3f;}',
        '.wac-preview-warnings{display:grid;gap:8px;margin-bottom:12px;}',
        '.wac-warning{padding:10px 12px;border-radius:10px;background:rgba(164,83,72,0.12);color:#7a342b;font-size:0.82rem;font-weight:600;}',
        '.wac-empty-box{padding:18px;border:1px dashed rgba(123,94,67,0.24);border-radius:12px;background:#faf6ef;color:#7d6a59;text-align:center;}',
        '.wac-compare-layout{display:grid;grid-template-columns:1fr 1fr;gap:14px;}',
        '.wac-compare-card{border:1px solid rgba(123,94,67,0.14);border-radius:12px;background:#faf6ef;padding:14px;}',
        '.wac-compare-title{margin:0 0 10px;font-size:0.95rem;font-weight:700;color:#214038;}',
        '.wac-compare-block{min-height:110px;white-space:pre-wrap;line-height:1.55;color:#3e3128;}',
        '.wac-compare-table{grid-column:1 / -1;}',
        '.wac-table-wrap{overflow:auto;border:1px solid rgba(123,94,67,0.14);border-radius:12px;}',
        '.wac-table{width:100%;border-collapse:collapse;font-size:0.85rem;}',
        '.wac-table th,.wac-table td{padding:10px 12px;border-bottom:1px solid rgba(123,94,67,0.1);text-align:left;vertical-align:top;}',
        '.wac-table th{background:#f5efe2;font-size:0.76rem;text-transform:uppercase;letter-spacing:0.04em;color:#7d6a59;}',
        '.wac-table tr:last-child td{border-bottom:none;}',
        '.wac-empty{text-align:center;color:#7d6a59;}',
        '.wac-meta{font-size:0.82rem;color:#7d6a59;}',
        '@media(max-width:920px){.wac-preview-layout{grid-template-columns:1fr;}.wac-compare-layout{grid-template-columns:1fr;}}',
        '@media(max-width:720px){.wac-wrap{padding:10px;}.wac-head{flex-direction:column;}.wac-actions{flex-direction:column;}.wac-btn{width:100%;}.wac-draft-button-row{grid-template-columns:1fr;}}'
      ].join('');
      document.head.appendChild(style);
    }
  };
})(window.NK || (window.NK = {}));