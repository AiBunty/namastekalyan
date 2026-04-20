/**
 * NK Admin SPA Module: CRM Workspace
 */
(function (NK, window) {
  'use strict';

  NK.MODULES = NK.MODULES || {};

  NK.MODULES['crm-panel'] = {
    _container: null,
    _authClient: null,
    _phpApiUrl: null,
    _lastLeadId: 0,
    _contactsPage: 1,
    _logsPage: 1,
    _pageSize: 25,

    init: function (container, authClient) {
      this._container = container;
      this._authClient = authClient;
      this._phpApiUrl = (window.NK_DATA_API && window.NK_DATA_API.phpApiUrl) || '';
      this._lastLeadId = 0;
      this._contactsPage = 1;
      this._logsPage = 1;

      container.innerHTML = this._buildHtml();
      this._bindEvents();
      this._loadWorkspace();
    },

    destroy: function () {
      this._container = null;
      this._authClient = null;
      this._phpApiUrl = null;
      this._lastLeadId = 0;
      this._contactsPage = 1;
      this._logsPage = 1;
    },

    _buildHtml: function () {
      return ''
        + '<main class="page" style="max-width:1280px;">'
        + '  <section class="hero">'
        + '    <h1>CRM Workspace</h1>'
        + '    <p>View canonical contacts, export filtered contacts to Excel, inspect CRM push history, and run a controlled CRM test from the admin panel.</p>'
        + '  </section>'
        + '  <section class="panel">'
        + '    <div class="row" style="justify-content:space-between;align-items:flex-start;gap:18px;">'
        + '      <div>'
        + '        <h3 style="margin:0 0 6px;">CRM Configuration</h3>'
        + '        <div id="crmPanelConfigStatus" class="status muted">Loading CRM configuration…</div>'
        + '        <div id="crmPanelLinkStatus" class="status muted" style="margin-top:6px;">Loading deployment links…</div>'
        + '        <div id="crmPanelSummary" class="status muted" style="margin-top:6px;">Loading CRM workspace summary…</div>'
        + '      </div>'
        + '      <div class="row" style="gap:10px;">'
        + '        <button id="crmPanelRefreshBtn" class="secondary" type="button">Refresh Workspace</button>'
        + '        <button id="crmPanelBackfillBtn" class="secondary" type="button">Backfill Contacts</button>'
        + '      </div>'
        + '    </div>'
        + '  </section>'
        + '  <section class="panel">'
        + '    <div class="row" style="justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;">'
        + '      <h3 style="margin:0;">Contacts Filter</h3>'
        + '      <div class="row" style="gap:10px;flex-wrap:wrap;">'
        + '        <input id="crmFilterSearch" type="text" maxlength="100" placeholder="Search by mobile or name">'
        + '        <input id="crmFilterSource" type="text" maxlength="60" placeholder="Source">'
        + '        <select id="crmFilterStatus">'
        + '          <option value="">All Sync Status</option>'
        + '          <option value="Pending">Pending</option>'
        + '          <option value="Success">Success</option>'
        + '          <option value="Failed">Failed</option>'
        + '          <option value="Skipped">Skipped</option>'
        + '        </select>'
        + '        <input id="crmFilterFromDate" type="date">'
        + '        <input id="crmFilterToDate" type="date">'
        + '        <button id="crmApplyFiltersBtn" type="button">Apply</button>'
        + '        <button id="crmResetFiltersBtn" class="secondary" type="button">Reset</button>'
        + '        <button id="crmExportContactsBtn" class="secondary" type="button">Download Excel</button>'
        + '      </div>'
        + '    </div>'
        + '    <div id="crmWorkspaceStatus" class="status muted" style="margin-top:10px;">Loading CRM contacts and history…</div>'
        + '  </section>'
        + '  <section class="panel">'
        + '    <div class="row" style="justify-content:space-between;align-items:center;">'
        + '      <h3 style="margin:0;">Contacts</h3>'
        + '      <div id="crmContactsPagination" class="status muted">Loading contacts…</div>'
        + '    </div>'
        + '    <div class="table-wrap" style="margin-top:12px;">'
        + '      <table>'
        + '        <thead>'
        + '          <tr>'
        + '            <th>Mobile</th>'
        + '            <th>Name</th>'
        + '            <th>DOB</th>'
        + '            <th>DOA</th>'
        + '            <th>Total Entries</th>'
        + '            <th>Last Seen</th>'
        + '            <th>Source</th>'
        + '            <th>Sync</th>'
        + '            <th>Code</th>'
        + '          </tr>'
        + '        </thead>'
        + '        <tbody id="crmContactsRows"></tbody>'
        + '      </table>'
        + '    </div>'
        + '    <div class="row" style="justify-content:flex-end;gap:10px;margin-top:12px;">'
        + '      <button id="crmContactsPrevBtn" class="secondary" type="button">Previous</button>'
        + '      <button id="crmContactsNextBtn" class="secondary" type="button">Next</button>'
        + '    </div>'
        + '  </section>'
        + '  <section class="panel">'
        + '    <div class="row" style="justify-content:space-between;align-items:center;">'
        + '      <h3 style="margin:0;">CRM Push History</h3>'
        + '      <div id="crmLogsPagination" class="status muted">Loading push logs…</div>'
        + '    </div>'
        + '    <div class="table-wrap" style="margin-top:12px;">'
        + '      <table>'
        + '        <thead>'
        + '          <tr>'
        + '            <th>When</th>'
        + '            <th>Mobile</th>'
        + '            <th>Name</th>'
        + '            <th>Source</th>'
        + '            <th>Result</th>'
        + '            <th>HTTP</th>'
        + '            <th>Attempts</th>'
        + '          </tr>'
        + '        </thead>'
        + '        <tbody id="crmLogsRows"></tbody>'
        + '      </table>'
        + '    </div>'
        + '    <div class="row" style="justify-content:flex-end;gap:10px;margin-top:12px;">'
        + '      <button id="crmLogsPrevBtn" class="secondary" type="button">Previous</button>'
        + '      <button id="crmLogsNextBtn" class="secondary" type="button">Next</button>'
        + '    </div>'
        + '  </section>'
        + '  <section class="panel">'
        + '    <h3>Controlled CRM Test</h3>'
        + '    <div class="row">'
        + '      <input id="crmTestName" type="text" maxlength="80" value="Parin Daulat" placeholder="Lead name">'
        + '      <input id="crmTestPhone" type="tel" inputmode="numeric" maxlength="15" value="9330033000" placeholder="Mobile number">'
        + '      <input id="crmTestDob" type="text" maxlength="20" value="12/06/1981" placeholder="DOB (DD/MM/YYYY)">'
        + '      <input id="crmTestDoa" type="text" maxlength="20" value="28/01/2006" placeholder="DOA (DD/MM/YYYY)">'
        + '    </div>'
        + '    <div class="row" style="margin-top:10px;">'
        + '      <button id="crmTestSubmitBtn" type="button">Run CRM Test</button>'
        + '      <button id="crmTestDeleteBtn" class="secondary" type="button" disabled>Delete Last Test Lead</button>'
        + '      <button id="crmTestClearBtn" class="secondary" type="button">Clear Results</button>'
        + '    </div>'
        + '    <div id="crmPanelStatus" class="status muted" style="margin-top:10px;">Ready to run a controlled CRM sync test.</div>'
        + '  </section>'
        + '  <section class="panel">'
        + '    <div class="row" style="justify-content:space-between;align-items:center;">'
        + '      <h3 style="margin:0;">Data Received</h3>'
        + '      <span class="status muted">Normalized data accepted by PHP before CRM push</span>'
        + '    </div>'
        + '    <div id="crmPanelReceivedEmpty" class="status muted" style="margin-top:10px;">No test has been run yet.</div>'
        + '    <div class="table-wrap" style="margin-top:10px;"><table><tbody id="crmPanelReceivedRows"></tbody></table></div>'
        + '  </section>'
        + '  <section class="panel">'
        + '    <div class="row" style="justify-content:space-between;align-items:center;">'
        + '      <h3 style="margin:0;">CRM Payload Preview</h3>'
        + '      <span class="status muted">Exact fields prepared for the CRM endpoint, excluding the token</span>'
        + '    </div>'
        + '    <div id="crmPanelPayloadEmpty" class="status muted" style="margin-top:10px;">No CRM payload generated yet.</div>'
        + '    <pre id="crmPanelPayloadPre" style="display:none;white-space:pre-wrap;word-break:break-word;background:#0f1720;color:#eaf2ff;padding:14px;border-radius:12px;overflow:auto;font-size:0.88rem;"></pre>'
        + '  </section>'
        + '  <section class="panel">'
        + '    <div class="row" style="justify-content:space-between;align-items:center;">'
        + '      <h3 style="margin:0;">Lead Stored In Database</h3>'
        + '      <span class="status muted">Latest lead row created by the controlled test</span>'
        + '    </div>'
        + '    <div id="crmPanelStoredEmpty" class="status muted" style="margin-top:10px;">No lead has been stored from this panel yet.</div>'
        + '    <div class="table-wrap" style="margin-top:10px;"><table><tbody id="crmPanelStoredRows"></tbody></table></div>'
        + '  </section>'
        + '  <section class="panel">'
        + '    <div class="row" style="justify-content:space-between;align-items:center;">'
        + '      <h3 style="margin:0;">Canonical Contact</h3>'
        + '      <span class="status muted">Unique contact row keyed by mobile number</span>'
        + '    </div>'
        + '    <div id="crmPanelContactEmpty" class="status muted" style="margin-top:10px;">No canonical contact captured yet.</div>'
        + '    <div class="table-wrap" style="margin-top:10px;"><table><tbody id="crmPanelContactRows"></tbody></table></div>'
        + '  </section>'
        + '  <section class="panel">'
        + '    <div class="row" style="justify-content:space-between;align-items:center;">'
        + '      <h3 style="margin:0;">CRM Push Confirmation</h3>'
        + '      <span class="status muted">HTTP response and retry attempt details from the actual CRM sync</span>'
        + '    </div>'
        + '    <div id="crmPanelSyncEmpty" class="status muted" style="margin-top:10px;">No CRM push confirmation yet.</div>'
        + '    <div class="table-wrap" style="margin-top:10px;"><table><tbody id="crmPanelSyncRows"></tbody></table></div>'
        + '  </section>'
        + '</main>';
    },

    _bindEvents: function () {
      var module = this;
      var c = module._container;
      if (!c) return;

      c.querySelector('#crmPanelRefreshBtn').addEventListener('click', function () {
        module._loadWorkspace();
      });

      c.querySelector('#crmPanelBackfillBtn').addEventListener('click', function () {
        module._backfillContacts();
      });

      c.querySelector('#crmApplyFiltersBtn').addEventListener('click', function () {
        module._contactsPage = 1;
        module._logsPage = 1;
        module._loadContacts();
        module._loadLogs();
      });

      c.querySelector('#crmResetFiltersBtn').addEventListener('click', function () {
        module._resetFilters();
      });

      c.querySelector('#crmExportContactsBtn').addEventListener('click', function () {
        module._exportContacts();
      });

      c.querySelector('#crmContactsPrevBtn').addEventListener('click', function () {
        if (module._contactsPage > 1) {
          module._contactsPage -= 1;
          module._loadContacts();
        }
      });

      c.querySelector('#crmContactsNextBtn').addEventListener('click', function () {
        module._contactsPage += 1;
        module._loadContacts();
      });

      c.querySelector('#crmLogsPrevBtn').addEventListener('click', function () {
        if (module._logsPage > 1) {
          module._logsPage -= 1;
          module._loadLogs();
        }
      });

      c.querySelector('#crmLogsNextBtn').addEventListener('click', function () {
        module._logsPage += 1;
        module._loadLogs();
      });

      c.querySelector('#crmTestSubmitBtn').addEventListener('click', function () {
        module._runTest();
      });

      c.querySelector('#crmTestDeleteBtn').addEventListener('click', function () {
        module._deleteLastLead();
      });

      c.querySelector('#crmTestClearBtn').addEventListener('click', function () {
        module._clearResults();
      });
    },

    _loadWorkspace: function () {
      this._loadStatus();
      this._loadContacts();
      this._loadLogs();
    },

    _collectFilters: function () {
      var c = this._container;
      if (!c) return {};
      return {
        search: String((c.querySelector('#crmFilterSearch') || {}).value || '').trim(),
        source: String((c.querySelector('#crmFilterSource') || {}).value || '').trim(),
        syncStatus: String((c.querySelector('#crmFilterStatus') || {}).value || '').trim(),
        fromDate: String((c.querySelector('#crmFilterFromDate') || {}).value || '').trim(),
        toDate: String((c.querySelector('#crmFilterToDate') || {}).value || '').trim()
      };
    },

    _resetFilters: function () {
      var c = this._container;
      if (!c) return;
      ['crmFilterSearch', 'crmFilterSource', 'crmFilterFromDate', 'crmFilterToDate'].forEach(function (id) {
        var el = c.querySelector('#' + id);
        if (el) el.value = '';
      });
      var statusEl = c.querySelector('#crmFilterStatus');
      if (statusEl) statusEl.value = '';
      this._contactsPage = 1;
      this._logsPage = 1;
      this._loadContacts();
      this._loadLogs();
    },

    _loadStatus: async function () {
      var c = this._container;
      if (!c) return;
      var statusEl = c.querySelector('#crmPanelConfigStatus');
      var linkEl = c.querySelector('#crmPanelLinkStatus');
      var summaryEl = c.querySelector('#crmPanelSummary');
      statusEl.textContent = 'Loading CRM configuration…';
      linkEl.textContent = 'Loading deployment links…';
      summaryEl.textContent = 'Loading CRM workspace summary…';

      try {
        var payload = await this._authClient.apiPost({ action: 'admin_crm_panel_status' });
        var config = payload && payload.config ? payload.config : {};
        var summary = payload && payload.summary ? payload.summary : {};
        linkEl.textContent = 'Public site: ' + String(config.publicSiteUrl || '-')
          + ' | Admin panel: ' + String(config.adminPanelUrl || '-');
        summaryEl.textContent = 'Canonical contacts: ' + String(summary.contacts || 0)
          + ' | CRM push logs: ' + String(summary.pushLogs || 0);
      } catch (err) {
        statusEl.textContent = 'Failed to load CRM configuration: ' + (err.message || err);
        linkEl.textContent = 'Failed to load deployment links.';
        summaryEl.textContent = 'Failed to load CRM workspace summary.';
      }
    },

    _loadContacts: async function () {
      var c = this._container;
      if (!c) return;
      var workspaceStatusEl = c.querySelector('#crmWorkspaceStatus');
      workspaceStatusEl.textContent = 'Loading CRM contacts…';

      try {
        var payload = await this._authClient.apiPost(Object.assign({
          action: 'admin_list_crm_contacts',
          page: this._contactsPage,
          pageSize: this._pageSize
        }, this._collectFilters()));

        this._renderContacts(payload.contacts || []);
        this._renderPagination('crmContactsPagination', payload.pagination || {}, 'contact');
        workspaceStatusEl.textContent = 'CRM contacts loaded.';
      } catch (err) {
        this._renderContacts([]);
        c.querySelector('#crmContactsPagination').textContent = 'Contacts unavailable';
        workspaceStatusEl.textContent = 'Failed to load contacts: ' + (err.message || err);
      }
    },

    _loadLogs: async function () {
      var c = this._container;
      if (!c) return;

      try {
        var payload = await this._authClient.apiPost(Object.assign({
          action: 'admin_list_crm_push_logs',
          page: this._logsPage,
          pageSize: this._pageSize
        }, this._collectFilters()));

        this._renderLogs(payload.logs || []);
        this._renderPagination('crmLogsPagination', payload.pagination || {}, 'log');
      } catch (err) {
        this._renderLogs([]);
        c.querySelector('#crmLogsPagination').textContent = 'Push logs unavailable';
      }
    },

    _backfillContacts: async function () {
      var c = this._container;
      if (!c) return;
      var workspaceStatusEl = c.querySelector('#crmWorkspaceStatus');
      var button = c.querySelector('#crmPanelBackfillBtn');
      if (!window.confirm('Backfill canonical contacts from existing leads now?')) {
        return;
      }

      button.disabled = true;
      workspaceStatusEl.textContent = 'Backfilling CRM contacts from existing leads…';
      try {
        var payload = await this._authClient.apiPost({ action: 'admin_backfill_crm_contacts' });
        workspaceStatusEl.textContent = 'Backfill complete. Contacts processed: ' + String(payload.processed || 0);
        this._contactsPage = 1;
        this._logsPage = 1;
        this._loadWorkspace();
      } catch (err) {
        workspaceStatusEl.textContent = 'Backfill failed: ' + (err.message || err);
      } finally {
        button.disabled = false;
      }
    },

    _exportContacts: function () {
      var module = this;
      var c = module._container;
      if (!c) return;
      var workspaceStatusEl = c.querySelector('#crmWorkspaceStatus');
      workspaceStatusEl.textContent = 'Preparing CRM contacts export…';

      module._blobPost(Object.assign({ action: 'admin_export_crm_contacts' }, module._collectFilters()), 'crm_contacts_' + module._timestampSuffix() + '.xlsx')
        .then(function () {
          workspaceStatusEl.textContent = 'CRM contacts export downloaded.';
        })
        .catch(function (err) {
          workspaceStatusEl.textContent = 'Export failed: ' + (err.message || err);
        });
    },

    _blobPost: function (body, filename) {
      var module = this;
      var token = (module._authClient && module._authClient.getToken()) || '';
      var postBody = Object.assign({}, body, { token: token });
      var actionParam = (body && body.action) ? ('?action=' + encodeURIComponent(body.action)) : '';
      return fetch(module._phpApiUrl.split('?')[0] + actionParam, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body: 'payload=' + encodeURIComponent(JSON.stringify(postBody))
      }).then(function (r) {
        var ct = r.headers.get('Content-Type') || '';
        if (ct.indexOf('application/json') !== -1) {
          return r.json().then(function (payload) {
            throw new Error((payload && (payload.message || payload.error)) || ('HTTP ' + r.status));
          });
        }
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.blob();
      }).then(function (blob) {
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
      });
    },

    _renderContacts: function (contacts) {
      var c = this._container;
      if (!c) return;
      var bodyEl = c.querySelector('#crmContactsRows');
      var esc = NK.MODULE_BASE.escHtml.bind(NK.MODULE_BASE);

      if (!Array.isArray(contacts) || !contacts.length) {
        bodyEl.innerHTML = '<tr><td colspan="9" class="muted">No contacts match the current filters.</td></tr>';
        return;
      }

      bodyEl.innerHTML = contacts.map(function (row) {
        var syncStatus = String(row.latestCrmSyncStatus || '-');
        var syncTone = syncStatus === 'Success'
          ? '#0f9d58'
          : (syncStatus === 'Failed' ? '#c62828' : (syncStatus === 'Pending' ? '#b26a00' : '#54616f'));

        return '<tr>'
          + '<td>' + esc(String(row.phone || '-')) + '</td>'
          + '<td>' + esc(String(row.name || '-')) + '</td>'
          + '<td>' + esc(String(row.dateOfBirth || '-')) + '</td>'
          + '<td>' + esc(String(row.dateOfAnniversary || '-')) + '</td>'
          + '<td>' + esc(String(row.totalSubmissions || 0)) + '</td>'
          + '<td style="white-space:nowrap;">' + esc(String(row.lastSeenAt || '-')) + '</td>'
          + '<td>' + esc(String(row.latestSource || '-')) + '</td>'
          + '<td><span style="display:inline-block;padding:4px 10px;border-radius:999px;background:' + syncTone + '14;color:' + syncTone + ';font-weight:600;">' + esc(syncStatus) + '</span></td>'
          + '<td>' + esc(String(row.latestCrmSyncCode || '-')) + '</td>'
          + '</tr>';
      }).join('');
    },

    _renderLogs: function (logs) {
      var c = this._container;
      if (!c) return;
      var bodyEl = c.querySelector('#crmLogsRows');
      var esc = NK.MODULE_BASE.escHtml.bind(NK.MODULE_BASE);

      if (!Array.isArray(logs) || !logs.length) {
        bodyEl.innerHTML = '<tr><td colspan="7" class="muted">No CRM push logs match the current filters.</td></tr>';
        return;
      }

      bodyEl.innerHTML = logs.map(function (row) {
        var resultText = row.success ? '&#10003; Success' : (row.attempted ? '&#10007; Failed' : 'Skipped');
        var resultTone = row.success ? '#0f9d58' : (row.attempted ? '#c62828' : '#54616f');

        return '<tr>'
          + '<td style="white-space:nowrap;">' + esc(String(row.createdAt || '-')) + '</td>'
          + '<td>' + esc(String(row.phone || '-')) + '</td>'
          + '<td>' + esc(String(row.contactName || '-')) + '</td>'
          + '<td>' + esc(String(row.triggerSource || '-')) + '</td>'
          + '<td><span style="display:inline-block;padding:4px 10px;border-radius:999px;background:' + resultTone + '14;color:' + resultTone + ';font-weight:700;">' + resultText + '</span></td>'
          + '<td>' + esc(String(row.httpCode || '-')) + '</td>'
          + '<td>' + esc(String(row.attemptCount || 0)) + '</td>'
          + '</tr>';
      }).join('');
    },

    _renderPagination: function (elementId, pagination, type) {
      var c = this._container;
      if (!c) return;
      var el = c.querySelector('#' + elementId);
      if (!el) return;

      var page = Number(pagination.page || 1);
      var pages = Number(pagination.pages || 1);
      var total = Number(pagination.total || 0);
      el.textContent = 'Page ' + page + ' of ' + pages + ' | Total ' + type + (total === 1 ? '' : 's') + ': ' + total;

      if (type === 'contact') {
        c.querySelector('#crmContactsPrevBtn').disabled = page <= 1;
        c.querySelector('#crmContactsNextBtn').disabled = page >= pages;
        if (page > pages && pages > 0) {
          this._contactsPage = pages;
        }
      } else {
        c.querySelector('#crmLogsPrevBtn').disabled = page <= 1;
        c.querySelector('#crmLogsNextBtn').disabled = page >= pages;
        if (page > pages && pages > 0) {
          this._logsPage = pages;
        }
      }
    },

    _runTest: async function () {
      var c = this._container;
      if (!c) return;

      var statusEl = c.querySelector('#crmPanelStatus');
      var submitBtn = c.querySelector('#crmTestSubmitBtn');
      var deleteBtn = c.querySelector('#crmTestDeleteBtn');
      var name = String((c.querySelector('#crmTestName') || {}).value || '').trim();
      var phone = String((c.querySelector('#crmTestPhone') || {}).value || '').trim();
      var dateOfBirth = String((c.querySelector('#crmTestDob') || {}).value || '').trim();
      var dateOfAnniversary = String((c.querySelector('#crmTestDoa') || {}).value || '').trim();

      if (!name || !phone) {
        statusEl.textContent = 'Name and phone are required.';
        return;
      }

      submitBtn.disabled = true;
      deleteBtn.disabled = true;
      statusEl.textContent = 'Submitting controlled lead to PHP and CRM…';

      try {
        var payload = await this._authClient.apiPost({
          action: 'admin_test_crm_sync',
          name: name,
          phone: phone,
          dateOfBirth: dateOfBirth,
          dateOfAnniversary: dateOfAnniversary,
          source: 'admin-crm-panel'
        });

        this._renderKeyValueRows('crmPanelReceivedRows', payload.received || null, 'crmPanelReceivedEmpty');
        this._renderPayloadPreview(payload.crmRequest || null);
        this._renderKeyValueRows('crmPanelStoredRows', payload.storedLead || null, 'crmPanelStoredEmpty');
        this._renderKeyValueRows('crmPanelContactRows', payload.storedContact || null, 'crmPanelContactEmpty');
        this._renderSyncResult(payload.crmSync || null, 'crmPanelSyncRows', 'crmPanelSyncEmpty');
        this._lastLeadId = Number((payload && payload.storedLead && payload.storedLead.id) || 0);
        deleteBtn.disabled = !this._lastLeadId;

        var crmSync = payload && payload.crmSync ? payload.crmSync : {};
        var storedLead = payload && payload.storedLead ? payload.storedLead : {};
        statusEl.textContent = 'CRM test completed. Lead #' + String(storedLead.id || '-')
          + ' | CRM success: ' + (crmSync.success ? 'Yes' : 'No')
          + ' | Status: ' + String(crmSync.status || crmSync.code || '-');

        this._contactsPage = 1;
        this._logsPage = 1;
        this._loadWorkspace();
      } catch (err) {
        statusEl.textContent = 'CRM test failed: ' + (err.message || err);
      } finally {
        submitBtn.disabled = false;
      }
    },

    _deleteLastLead: async function () {
      var c = this._container;
      if (!c) return;

      var statusEl = c.querySelector('#crmPanelStatus');
      var deleteBtn = c.querySelector('#crmTestDeleteBtn');
      if (!this._lastLeadId) {
        statusEl.textContent = 'No CRM panel test lead is selected for deletion.';
        return;
      }

      if (!window.confirm('Delete the last CRM panel test lead #' + this._lastLeadId + '?')) {
        return;
      }

      deleteBtn.disabled = true;
      statusEl.textContent = 'Deleting CRM test lead #' + this._lastLeadId + '…';

      try {
        var payload = await this._authClient.apiPost({
          action: 'admin_delete_crm_test_lead',
          leadId: this._lastLeadId
        });
        var deletedLead = payload && payload.deletedLead ? payload.deletedLead : {};
        this._lastLeadId = 0;
        this._setEmptyTable('crmPanelStoredRows', 'crmPanelStoredEmpty', 'No lead has been stored from this panel yet.');
        this._setEmptyTable('crmPanelContactRows', 'crmPanelContactEmpty', 'No canonical contact captured yet.');
        this._setEmptyTable('crmPanelSyncRows', 'crmPanelSyncEmpty', 'No CRM push confirmation yet.');
        statusEl.textContent = 'Deleted CRM test lead #' + String(deletedLead.id || '-') + '.';
        this._loadWorkspace();
      } catch (err) {
        statusEl.textContent = 'Delete failed: ' + (err.message || err);
        deleteBtn.disabled = false;
      }
    },

    _clearResults: function () {
      this._lastLeadId = 0;
      this._setEmptyTable('crmPanelReceivedRows', 'crmPanelReceivedEmpty', 'No test has been run yet.');
      this._setEmptyTable('crmPanelStoredRows', 'crmPanelStoredEmpty', 'No lead has been stored from this panel yet.');
      this._setEmptyTable('crmPanelContactRows', 'crmPanelContactEmpty', 'No canonical contact captured yet.');
      this._setEmptyTable('crmPanelSyncRows', 'crmPanelSyncEmpty', 'No CRM push confirmation yet.');

      var c = this._container;
      if (!c) return;
      c.querySelector('#crmTestDeleteBtn').disabled = true;
      c.querySelector('#crmPanelStatus').textContent = 'Results cleared.';
      c.querySelector('#crmPanelPayloadEmpty').style.display = 'block';
      c.querySelector('#crmPanelPayloadPre').style.display = 'none';
      c.querySelector('#crmPanelPayloadPre').textContent = '';
    },

    _renderPayloadPreview: function (crmRequest) {
      var c = this._container;
      if (!c) return;
      var emptyEl = c.querySelector('#crmPanelPayloadEmpty');
      var preEl = c.querySelector('#crmPanelPayloadPre');

      if (!crmRequest) {
        emptyEl.style.display = 'block';
        preEl.style.display = 'none';
        preEl.textContent = '';
        return;
      }

      emptyEl.style.display = 'none';
      preEl.style.display = 'block';
      preEl.textContent = JSON.stringify(crmRequest, null, 2);
    },

    _renderSyncResult: function (crmSync, tableId, emptyId) {
      if (!crmSync) {
        this._setEmptyTable(tableId, emptyId, 'No CRM push confirmation yet.');
        return;
      }

      var attempts = Array.isArray(crmSync.attempts) ? crmSync.attempts : [];
      var data = {
        attempted: crmSync.attempted,
        success: crmSync.success,
        status: crmSync.status || '',
        code: crmSync.code || '',
        message: crmSync.message || '',
        attempts: attempts.length ? attempts.map(function (item, index) {
          return 'Attempt ' + (index + 1) + ': success=' + String(!!item.success)
            + ', status=' + String(item.status || '-')
            + ', message=' + String(item.message || '-');
        }).join('\n') : 'No attempt metadata returned'
      };

      this._renderKeyValueRows(tableId, data, emptyId);
    },

    _renderKeyValueRows: function (tableId, data, emptyId) {
      var c = this._container;
      if (!c) return;
      var bodyEl = c.querySelector('#' + tableId);
      var emptyEl = c.querySelector('#' + emptyId);
      var esc = NK.MODULE_BASE.escHtml.bind(NK.MODULE_BASE);

      if (!bodyEl || !emptyEl) return;

      if (!data || typeof data !== 'object') {
        this._setEmptyTable(tableId, emptyId, emptyEl.textContent || 'No data available.');
        return;
      }

      var rows = Object.keys(data).map(function (key) {
        var value = data[key];
        if (value == null || value === '') {
          value = '-';
        } else if (typeof value === 'object') {
          value = JSON.stringify(value);
        }

        return '<tr>'
          + '<th style="width:240px;text-transform:capitalize;">' + esc(key) + '</th>'
          + '<td style="white-space:pre-wrap;word-break:break-word;">' + esc(String(value)) + '</td>'
          + '</tr>';
      }).join('');

      bodyEl.innerHTML = rows;
      emptyEl.style.display = 'none';
    },

    _setEmptyTable: function (tableId, emptyId, message) {
      var c = this._container;
      if (!c) return;
      var bodyEl = c.querySelector('#' + tableId);
      var emptyEl = c.querySelector('#' + emptyId);
      if (bodyEl) bodyEl.innerHTML = '';
      if (emptyEl) {
        emptyEl.textContent = message;
        emptyEl.style.display = 'block';
      }
    },

    _timestampSuffix: function () {
      var now = new Date();
      function pad(value) { return String(value).padStart(2, '0'); }
      return now.getFullYear()
        + pad(now.getMonth() + 1)
        + pad(now.getDate())
        + '_'
        + pad(now.getHours())
        + pad(now.getMinutes())
        + pad(now.getSeconds());
    }
  };
})(window.NK || (window.NK = {}), window);