// admin-modules/event-guests.js
// SPA Module: Event Guests (prefix: evg)
(function (NK) {
  NK.MODULES = NK.MODULES || {};

  NK.MODULES['event-guests'] = {
    _container: null,
    _authClient: null,
    _allGuestRows: [],
    _filteredGuestRows: [],
    _allReconRows: [],

    init: function (container, authClient) {
      this._container = container;
      this._authClient = authClient;
      this._allGuestRows = [];
      this._filteredGuestRows = [];
      this._allReconRows = [];

      var module = this;
      container.innerHTML = module._buildHtml();
      module._injectStyles();
      module._bindEvents();

      // Load XLSX CDN then load report
      NK.MODULE_BASE.loadCdnScript('https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js').then(function () {
        module._loadReport(null);
      }).catch(function () {
        module._loadReport(null); // proceed without xlsx — CSV export still works
      });
      module._loadMailLogReport('');
    },

    destroy: function () {
      this._container = null;
      this._authClient = null;
      this._allGuestRows = [];
      this._filteredGuestRows = [];
      this._allReconRows = [];
    },

    // ── Helpers ───────────────────────────────────────────────────────────────

    _esc: function (v) { return NK.MODULE_BASE.escHtml(v); },

    _normText: function (v) {
      return String(v == null ? '' : v).trim().toLowerCase();
    },

    _containsFilter: function (v, filter) {
      if (!filter) return true;
      return this._normText(v).indexOf(this._normText(filter)) !== -1;
    },

    _downloadBlob: function (content, fileName, mimeType) {
      var blob = new Blob([content], { type: mimeType });
      var url = URL.createObjectURL(blob);
      var a = document.createElement('a');
      a.href = url;
      a.download = fileName;
      document.body.appendChild(a);
      a.click();
      setTimeout(function () {
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
      }, 1000);
    },

    _buildGuestFileBaseName: function () {
      var c = this._container;
      var eventEl = c ? c.querySelector('#evgEventFilter') : null;
      var eventName = eventEl ? (eventEl.options[eventEl.selectedIndex] && eventEl.options[eventEl.selectedIndex].text || '') : '';
      var ts = new Date().toISOString().slice(0, 16).replace('T', '_').replace(':', '-');
      var safeName = (eventName || 'all').replace(/[^a-zA-Z0-9_-]/g, '_');
      return 'event-guests_' + safeName + '_' + ts;
    },

    _mapGuestForExport: function (item) {
      return {
        Event: item.eventTitle || '',
        TransactionId: item.transactionId || '',
        GuestName: item.guestName || '',
        Email: item.email || '',
        Phone: item.phone || '',
        BookingType: item.bookingType || '',
        CollectionType: item.collectionType || '',
        Tickets: item.tickets != null ? item.tickets : '',
        Attendees: item.attendees != null ? item.attendees : '',
        Status: item.status || '',
        EmailStatus: item.emailStatus || '',
        EmailSentAt: item.emailSentAt || '',
        RegisteredAt: item.registeredAt || '',
        CheckedInAt: item.checkedInAt || '',
        CheckInHistory: item.checkinHistorySummary || ''
      };
    },

    // ── Data ──────────────────────────────────────────────────────────────────

    _loadReport: async function (eventId) {
      var module = this;
      var c = module._container;
      if (!c) return;
      var statusEl = c.querySelector('#evgStatus');
      if (statusEl) statusEl.textContent = 'Loading report...';
      try {
        var params = eventId ? { eventId: eventId } : null;
        var payload = await module._authClient.apiGet('event_guest_report', params);
        var report = payload && payload.report ? payload.report : payload || {};

        var summary = Array.isArray(report.eventSummary) ? report.eventSummary : [];
        var selectedEventId = report.selectedEventId || eventId || null;
        var totals = report.totals || {};
        var guests = Array.isArray(report.guests) ? report.guests : [];
        var recon = report.razorpayReconciliation || {};

        module._allGuestRows = guests;
        module._allReconRows = Array.isArray(recon.entries) ? recon.entries : [];

        module._renderEventOptions(summary, selectedEventId);
        module._updateStats(totals);
        module._renderReconciliation(recon);
        module._applyGuestFilters();

        if (statusEl) statusEl.textContent = 'Loaded ' + guests.length + ' guest(s).';
      } catch (err) {
        if (statusEl) statusEl.textContent = 'Failed to load report: ' + (err.message || err);
      }
    },

    _loadMailLogReport: async function (fileName) {
      var module = this;
      var c = module._container;
      if (!c) return;
      var statusEl = c.querySelector('#evgMailLogStatus');
      if (statusEl) statusEl.textContent = 'Loading mail support logs...';
      try {
        var params = { limit: 80 };
        if (fileName) {
          params.file = fileName;
        }
        var payload = await module._authClient.apiGet('admin_mail_log_report', params);
        var report = payload && payload.report ? payload.report : payload || {};
        module._renderMailLogOptions(report.availableFiles || [], report.selectedFile || '');
        module._updateMailLogStats(report.summary || {});
        module._renderMailLogTable(report.entries || []);
        if (statusEl) {
          statusEl.textContent = 'Loaded ' + ((report.entries && report.entries.length) || 0) + ' mail log entr' + (((report.entries && report.entries.length) || 0) === 1 ? 'y.' : 'ies.');
        }
      } catch (err) {
        module._renderMailLogOptions([], '');
        module._updateMailLogStats({});
        module._renderMailLogTable([]);
        if (statusEl) statusEl.textContent = 'Failed to load mail logs: ' + (err.message || err);
      }
    },

    // ── Stats ──────────────────────────────────────────────────────────────────

    _updateStats: function (totals) {
      var c = this._container;
      if (!c) return;
      var set = function (id, val) {
        var el = c.querySelector('#' + id);
        if (el) el.textContent = val != null ? val : '--';
      };
      set('evgStatRegistrations', totals.registrations);
      set('evgStatGuests', totals.guests);
      set('evgStatFree', totals.free);
      set('evgStatPaid', totals.paid);
      set('evgStatCheckedIn', totals.checkedIn);
      set('evgStatEmailSent', totals.emailSent);
      set('evgStatEmailFailed', totals.emailFailed);
      set('evgStatEmailPending', totals.emailPending);
      set('evgStatRazorpayCollected', totals.razorpayCollected != null ? ('₹' + totals.razorpayCollected) : '--');
      set('evgStatCashCollected', totals.cashCollected != null ? ('₹' + totals.cashCollected) : '--');
      set('evgStatRazorpayPending', totals.razorpayPending != null ? ('₹' + totals.razorpayPending) : '--');
    },

    _updateMailLogStats: function (summary) {
      var c = this._container;
      if (!c) return;
      var safe = summary || {};
      var set = function (id, val) {
        var el = c.querySelector('#' + id);
        if (el) el.textContent = val != null ? val : '--';
      };
      set('evgMailAttempted', safe.attempted || 0);
      set('evgMailAccepted', safe.accepted || 0);
      set('evgMailFailed', safe.failed || 0);
      set('evgMailSkipped', safe.skipped || 0);
    },

    _renderReconciliation: function (reconciliation) {
      var module = this;
      var c = module._container;
      if (!c) return;
      var rt = (reconciliation && reconciliation.totals) ? reconciliation.totals : {};
      var set = function (id, val) {
        var el = c.querySelector('#' + id);
        if (el) el.textContent = val != null ? val : '--';
      };
      set('evgReconCollectedRows', rt.collectedRows);
      set('evgReconCollectedAmount', rt.collectedAmount != null ? ('₹' + rt.collectedAmount) : '--');
      set('evgReconPendingAmount', rt.pendingAmount != null ? ('₹' + rt.pendingAmount) : '--');
      set('evgReconCancelledRows', rt.cancelledRows);

      module._applyReconciliationFilters();
    },

    _renderEventOptions: function (summary, selectedEventId) {
      var c = this._container;
      var sel = c.querySelector('#evgEventFilter');
      if (!sel) return;
      var esc = this._esc.bind(this);
      var opts = '<option value="">All Events</option>';
      (summary || []).forEach(function (ev) {
        var val = ev.eventId || ev.id || '';
        var label = ev.eventTitle || ev.title || ev.eventId || val;
        var selected = String(val) === String(selectedEventId) ? ' selected' : '';
        opts += '<option value="' + esc(String(val)) + '"' + selected + '>' + esc(label) + '</option>';
      });
      sel.innerHTML = opts;
    },

    _renderMailLogOptions: function (files, selectedFile) {
      var c = this._container;
      if (!c) return;
      var sel = c.querySelector('#evgMailLogFile');
      if (!sel) return;
      var esc = this._esc.bind(this);
      var options = Array.isArray(files) ? files : [];
      var html = options.length ? '' : '<option value="">No log files found</option>';
      options.forEach(function (fileName) {
        var selected = String(fileName) === String(selectedFile) ? ' selected' : '';
        html += '<option value="' + esc(String(fileName)) + '"' + selected + '>' + esc(String(fileName)) + '</option>';
      });
      sel.innerHTML = html;
      sel.disabled = !options.length;
    },

    // ── Filters ───────────────────────────────────────────────────────────────

    _readFilterVal: function (id) {
      var c = this._container;
      var el = c.querySelector('#' + id);
      return el ? el.value : '';
    },

    _applyGuestFilters: function () {
      var module = this;
      var c = module._container;
      if (!c) return;
      var cf = module._containsFilter.bind(module);

      var globalSearch = module._readFilterVal('evgGuestSearch');
      var fEvent = module._readFilterVal('evgFEvent');
      var fGuest = module._readFilterVal('evgFGuest');
      var fContact = module._readFilterVal('evgFContact');
      var fType = module._readFilterVal('evgFType');
      var fTickets = module._readFilterVal('evgFTickets');
      var fAttendees = module._readFilterVal('evgFAttendees');
      var fStatus = module._readFilterVal('evgFStatus');
      var fRegistered = module._readFilterVal('evgFRegistered');
      var fCheckedIn = module._readFilterVal('evgFCheckedIn');

      module._filteredGuestRows = module._allGuestRows.filter(function (item) {
        if (globalSearch) {
          var all = [item.eventTitle, item.guestName, item.email, item.phone, item.transactionId,
            item.bookingType, item.collectionType, item.status, item.emailStatus, item.emailSentAt,
            item.registeredAt, item.checkedInAt, item.tickets, item.attendees, item.checkinHistorySummary].join(' ');
          if (!cf(all, globalSearch)) return false;
        }
        if (!cf(item.eventTitle, fEvent)) return false;
        if (!cf(item.guestName, fGuest)) return false;
        if (!cf((item.email || '') + ' ' + (item.phone || ''), fContact)) return false;
        if (fType && module._normText(item.bookingType) !== module._normText(fType) && module._normText(item.collectionType) !== module._normText(fType)) return false;
        if (!cf(String(item.tickets || ''), fTickets)) return false;
        if (!cf(String(item.attendees || ''), fAttendees)) return false;
        if (!cf((item.status || '') + ' ' + (item.emailStatus || '') + ' ' + (item.emailSentAt || ''), fStatus)) return false;
        if (!cf(item.registeredAt, fRegistered)) return false;
        if (!cf((item.checkedInAt || '') + ' ' + (item.checkinHistorySummary || ''), fCheckedIn)) return false;
        return true;
      });

      module._renderGuestTable(module._filteredGuestRows);
    },

    _applyReconciliationFilters: function () {
      var module = this;
      var c = module._container;
      if (!c) return;
      var cf = module._containsFilter.bind(module);

      var globalSearch = module._readFilterVal('evgReconSearch');
      var fEvent = module._readFilterVal('evgRFEvent');
      var fGuest = module._readFilterVal('evgRFGuest');
      var fTransaction = module._readFilterVal('evgRFTransaction');
      var fOrderPayment = module._readFilterVal('evgRFOrderPayment');
      var fAmount = module._readFilterVal('evgRFAmount');
      var fStatus = module._readFilterVal('evgRFStatus');
      var fRefund = module._readFilterVal('evgRFRefund');
      var fCreated = module._readFilterVal('evgRFCreated');
      var fConfirmed = module._readFilterVal('evgRFConfirmed');

      var filtered = module._allReconRows.filter(function (item) {
        if (globalSearch) {
          var all = [item.eventTitle, item.guestName, item.transactionId, item.orderId,
            item.paymentId, item.amount, item.status, item.refundStatus, item.createdAt, item.confirmedAt].join(' ');
          if (!cf(all, globalSearch)) return false;
        }
        if (!cf(item.eventTitle, fEvent)) return false;
        if (!cf(item.guestName, fGuest)) return false;
        if (!cf(item.transactionId, fTransaction)) return false;
        if (!cf((item.orderId || '') + ' ' + (item.paymentId || ''), fOrderPayment)) return false;
        if (!cf(String(item.amount || ''), fAmount)) return false;
        if (!cf(item.status, fStatus)) return false;
        if (!cf(item.refundStatus, fRefund)) return false;
        if (!cf(item.createdAt, fCreated)) return false;
        if (!cf(item.confirmedAt, fConfirmed)) return false;
        return true;
      });

      module._renderReconTable(filtered);
    },

    // ── Rendering ─────────────────────────────────────────────────────────────

    _renderGuestTable: function (rows) {
      var module = this;
      var c = module._container;
      if (!c) return;
      var tbody = c.querySelector('#evgGuestRows');
      if (!tbody) return;
      var esc = module._esc.bind(module);
      var countEl = c.querySelector('#evgGuestCount');
      if (countEl) countEl.textContent = rows.length + ' row(s)';

      if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="evg-muted">No guests match the current filters.</td></tr>';
        return;
      }

      tbody.innerHTML = rows.map(function (item) {
        var emailStatus = item.emailStatus || 'Pending';
        var emailSentAt = item.emailSentAt ? '<div class="evg-muted">Mail handoff: ' + esc(item.emailSentAt) + '</div>' : '';
        return '<tr>'
          + '<td>' + esc(item.eventTitle || '') + '</td>'
          + '<td>' + esc(item.guestName || '') + '</td>'
          + '<td>' + esc(item.email || '') + (item.phone ? '<div class="evg-muted">' + esc(item.phone) + '</div>' : '') + '</td>'
          + '<td>' + esc(item.bookingType || '') + '</td>'
          + '<td>' + esc(item.tickets != null ? item.tickets : '') + '</td>'
          + '<td>' + esc(item.attendees != null ? item.attendees : '') + '</td>'
          + '<td>' + esc(item.status || '') + '<div class="evg-muted">Email: ' + esc(emailStatus) + '</div>' + emailSentAt + '</td>'
          + '<td>' + esc(item.registeredAt || '') + '</td>'
          + '<td>' + esc(item.checkedInAt || '')
            + (item.checkedInCount ? '<div class="evg-muted">Checked in: ' + esc(String(item.checkedInCount)) + ' / ' + esc(String(item.qty || item.tickets || '')) + '</div>' : '')
            + (item.checkinHistorySummary ? '<div class="evg-muted">' + esc(item.checkinHistorySummary) + '</div>' : '')
            + '</td>'
          + '</tr>';
      }).join('');
    },

    _renderReconTable: function (rows) {
      var module = this;
      var c = module._container;
      if (!c) return;
      var tbody = c.querySelector('#evgReconRows');
      if (!tbody) return;
      var esc = module._esc.bind(module);
      var countEl = c.querySelector('#evgReconCount');
      if (countEl) countEl.textContent = rows.length + ' row(s)';

      if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="evg-muted">No reconciliation entries found.</td></tr>';
        return;
      }

      tbody.innerHTML = rows.map(function (item) {
        return '<tr>'
          + '<td>' + esc(item.eventTitle || '') + '</td>'
          + '<td>' + esc(item.guestName || '') + '</td>'
          + '<td>' + esc(item.transactionId || '') + '</td>'
          + '<td>' + esc(item.orderId || '') + (item.paymentId ? '<div class="evg-muted">' + esc(item.paymentId) + '</div>' : '') + '</td>'
          + '<td>' + (item.amount != null ? '₹' + esc(String(item.amount)) : '--') + '</td>'
          + '<td>' + esc(item.status || '') + '</td>'
          + '<td>' + esc(item.refundStatus || '') + '</td>'
          + '<td>' + esc(item.createdAt || '') + '</td>'
          + '<td>' + esc(item.confirmedAt || '') + '</td>'
          + '</tr>';
      }).join('');
    },

    _renderMailLogTable: function (rows) {
      var module = this;
      var c = module._container;
      if (!c) return;
      var tbody = c.querySelector('#evgMailLogRows');
      if (!tbody) return;
      var esc = module._esc.bind(module);

      if (!rows || !rows.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="evg-muted">No mail-support log entries found in the selected file.</td></tr>';
        return;
      }

      tbody.innerHTML = rows.map(function (item) {
        var details = [
          item.subject ? '<div><strong>Subject:</strong> ' + esc(item.subject) + '</div>' : '',
          item.error ? '<div><strong>Error:</strong> ' + esc(item.error) + '</div>' : '',
          item.messageId ? '<div><strong>Message ID:</strong> ' + esc(item.messageId) + '</div>' : '',
          item.smtpHost ? '<div><strong>SMTP:</strong> ' + esc(item.smtpHost + (item.smtpPort ? ':' + item.smtpPort : '') + (item.smtpSecure ? ' (' + item.smtpSecure + ')' : '')) + '</div>' : ''
        ].join('');

        return '<tr>'
          + '<td>' + esc(item.time || '') + '</td>'
          + '<td>' + esc(item.level || '') + '</td>'
          + '<td>' + esc(item.kind || '-') + '</td>'
          + '<td>' + esc(item.to || '-') + (item.recipientDomain ? '<div class="evg-muted">' + esc(item.recipientDomain) + '</div>' : '') + '</td>'
          + '<td>' + esc(item.transactionId || '-') + '</td>'
          + '<td>' + esc(item.message || '') + '</td>'
          + '<td class="evg-log-details">' + details + '</td>'
          + '</tr>';
      }).join('');
    },

    // ── Export ────────────────────────────────────────────────────────────────

    _exportFilteredGuestsCsv: function () {
      var module = this;
      if (!module._filteredGuestRows.length) return;
      var rows = module._filteredGuestRows.map(function (item) { return module._mapGuestForExport(item); });
      var keys = Object.keys(rows[0]);
      var csvLines = [keys.join(',')];
      rows.forEach(function (row) {
        var line = keys.map(function (k) {
          var v = String(row[k] == null ? '' : row[k]).replace(/"/g, '""');
          return '"' + v + '"';
        }).join(',');
        csvLines.push(line);
      });
      module._downloadBlob(csvLines.join('\r\n'), module._buildGuestFileBaseName() + '.csv', 'text/csv');
    },

    _exportAllGuestsExcel: function () {
      var module = this;
      if (!window.XLSX) { alert('XLSX library not loaded.'); return; }
      var rows = module._allGuestRows.map(function (item) { return module._mapGuestForExport(item); });
      if (!rows.length) return;
      var ws = window.XLSX.utils.json_to_sheet(rows);
      var wb = window.XLSX.utils.book_new();
      window.XLSX.utils.book_append_sheet(wb, ws, 'Guests');
      window.XLSX.writeFile(wb, module._buildGuestFileBaseName() + '.xlsx');
    },

    // ── Events ────────────────────────────────────────────────────────────────

    _bindEvents: function () {
      var module = this;
      var c = module._container;

      // Event filter → reload
      var eventFilter = c.querySelector('#evgEventFilter');
      if (eventFilter) {
        eventFilter.addEventListener('change', function () {
          module._loadReport(eventFilter.value || null);
        });
      }

      // Refresh
      var refreshBtn = c.querySelector('#evgRefreshBtn');
      if (refreshBtn) {
        refreshBtn.addEventListener('click', function () {
          var ev = c.querySelector('#evgEventFilter');
          module._loadReport((ev && ev.value) || null);
        });
      }

      // Guest search inputs → filter
      var guestSearchEl = c.querySelector('#evgGuestSearch');
      if (guestSearchEl) guestSearchEl.addEventListener('input', function () { module._applyGuestFilters(); });

      var guestColumnIds = ['evgFEvent','evgFGuest','evgFContact','evgFType','evgFTickets','evgFAttendees','evgFStatus','evgFRegistered','evgFCheckedIn'];
      guestColumnIds.forEach(function (id) {
        var el = c.querySelector('#' + id);
        if (el) el.addEventListener('input', function () { module._applyGuestFilters(); });
        if (el && el.tagName === 'SELECT') el.addEventListener('change', function () { module._applyGuestFilters(); });
      });

      // Clear guest filters
      var clearGuestBtn = c.querySelector('#evgClearGuestFilters');
      if (clearGuestBtn) {
        clearGuestBtn.addEventListener('click', function () {
          var allIds = ['evgGuestSearch'].concat(guestColumnIds);
          allIds.forEach(function (id) { var el = c.querySelector('#' + id); if (el) el.value = ''; });
          module._applyGuestFilters();
        });
      }

      // Recon search inputs → filter
      var reconSearchEl = c.querySelector('#evgReconSearch');
      if (reconSearchEl) reconSearchEl.addEventListener('input', function () { module._applyReconciliationFilters(); });

      var reconColumnIds = ['evgRFEvent','evgRFGuest','evgRFTransaction','evgRFOrderPayment','evgRFAmount','evgRFStatus','evgRFRefund','evgRFCreated','evgRFConfirmed'];
      reconColumnIds.forEach(function (id) {
        var el = c.querySelector('#' + id);
        if (el) el.addEventListener('input', function () { module._applyReconciliationFilters(); });
      });

      // Clear recon filters
      var clearReconBtn = c.querySelector('#evgClearReconFilters');
      if (clearReconBtn) {
        clearReconBtn.addEventListener('click', function () {
          var allIds = ['evgReconSearch'].concat(reconColumnIds);
          allIds.forEach(function (id) { var el = c.querySelector('#' + id); if (el) el.value = ''; });
          module._applyReconciliationFilters();
        });
      }

      // Export buttons
      var csvBtn = c.querySelector('#evgExportCsvBtn');
      if (csvBtn) csvBtn.addEventListener('click', function () { module._exportFilteredGuestsCsv(); });

      var xlsxBtn = c.querySelector('#evgExportXlsxBtn');
      if (xlsxBtn) xlsxBtn.addEventListener('click', function () { module._exportAllGuestsExcel(); });

      var mailLogFile = c.querySelector('#evgMailLogFile');
      if (mailLogFile) {
        mailLogFile.addEventListener('change', function () {
          module._loadMailLogReport(mailLogFile.value || '');
        });
      }

      var mailLogRefreshBtn = c.querySelector('#evgMailLogRefreshBtn');
      if (mailLogRefreshBtn) {
        mailLogRefreshBtn.addEventListener('click', function () {
          var selected = c.querySelector('#evgMailLogFile');
          module._loadMailLogReport((selected && selected.value) || '');
        });
      }
    },

    // ── HTML ──────────────────────────────────────────────────────────────────

    _buildHtml: function () {
      return [
        '<div class="evg-wrap">',

        // Header
        '<div class="evg-header-row">',
        '  <h2 class="evg-heading">Event Guests</h2>',
        '  <div class="evg-header-actions">',
        '    <select id="evgEventFilter" class="evg-input"><option value="">All Events</option></select>',
        '    <button class="evg-btn evg-btn-sec" id="evgRefreshBtn">Refresh</button>',
        '  </div>',
        '</div>',
        '<div id="evgStatus" class="evg-status evg-muted">Loading...</div>',

        // Stats
        '<div class="evg-stats-grid">',
        '  <div class="evg-stat-card"><div class="evg-stat-val" id="evgStatRegistrations">--</div><div class="evg-stat-label">Registrations</div></div>',
        '  <div class="evg-stat-card"><div class="evg-stat-val" id="evgStatGuests">--</div><div class="evg-stat-label">Guests</div></div>',
        '  <div class="evg-stat-card"><div class="evg-stat-val" id="evgStatFree">--</div><div class="evg-stat-label">Free</div></div>',
        '  <div class="evg-stat-card"><div class="evg-stat-val" id="evgStatPaid">--</div><div class="evg-stat-label">Paid</div></div>',
        '  <div class="evg-stat-card"><div class="evg-stat-val" id="evgStatCheckedIn">--</div><div class="evg-stat-label">Checked In</div></div>',
        '  <div class="evg-stat-card"><div class="evg-stat-val" id="evgStatEmailSent">--</div><div class="evg-stat-label">Email Sent</div></div>',
        '  <div class="evg-stat-card"><div class="evg-stat-val" id="evgStatEmailFailed">--</div><div class="evg-stat-label">Email Failed</div></div>',
        '  <div class="evg-stat-card"><div class="evg-stat-val" id="evgStatEmailPending">--</div><div class="evg-stat-label">Email Pending</div></div>',
        '  <div class="evg-stat-card"><div class="evg-stat-val" id="evgStatRazorpayCollected">--</div><div class="evg-stat-label">Razorpay Collected</div></div>',
        '  <div class="evg-stat-card"><div class="evg-stat-val" id="evgStatCashCollected">--</div><div class="evg-stat-label">Cash Collected</div></div>',
        '  <div class="evg-stat-card"><div class="evg-stat-val" id="evgStatRazorpayPending">--</div><div class="evg-stat-label">Razorpay Pending</div></div>',
        '</div>',

        // Guest table
        '<section class="evg-panel">',
        '  <div class="evg-panel-header">',
        '    <h3 class="evg-subheading">Guests <span id="evgGuestCount" class="evg-count-chip"></span></h3>',
        '    <div class="evg-export-btns">',
        '      <button class="evg-btn evg-btn-sec" id="evgExportCsvBtn">Export CSV (filtered)</button>',
        '      <button class="evg-btn evg-btn-sec" id="evgExportXlsxBtn">Export XLSX (all)</button>',
        '      <button class="evg-btn evg-btn-sec evg-btn-sm" id="evgClearGuestFilters">Clear Filters</button>',
        '    </div>',
        '  </div>',
        '  <div class="evg-filter-row">',
        '    <input id="evgGuestSearch" class="evg-input" placeholder="Search all columns...">',
        '  </div>',
        '  <div class="evg-table-wrap">',
        '    <table class="evg-table">',
        '      <thead>',
        '        <tr>',
        '          <th>Event</th><th>Guest</th><th>Contact</th><th>Type</th><th>Tickets</th><th>Attendees</th><th>Status</th><th>Registered</th><th>Checked In</th>',
        '        </tr>',
        '        <tr class="evg-filter-tr">',
        '          <th><input id="evgFEvent" class="evg-col-filter" placeholder="Filter..."></th>',
        '          <th><input id="evgFGuest" class="evg-col-filter" placeholder="Filter..."></th>',
        '          <th><input id="evgFContact" class="evg-col-filter" placeholder="Filter..."></th>',
        '          <th><input id="evgFType" class="evg-col-filter" placeholder="Filter..."></th>',
        '          <th><input id="evgFTickets" class="evg-col-filter" placeholder="Filter..."></th>',
        '          <th><input id="evgFAttendees" class="evg-col-filter" placeholder="Filter..."></th>',
        '          <th><input id="evgFStatus" class="evg-col-filter" placeholder="Filter..."></th>',
        '          <th><input id="evgFRegistered" class="evg-col-filter" placeholder="Filter..."></th>',
        '          <th><input id="evgFCheckedIn" class="evg-col-filter" placeholder="Filter..."></th>',
        '        </tr>',
        '      </thead>',
        '      <tbody id="evgGuestRows"><tr><td colspan="9" class="evg-muted">Loading...</td></tr></tbody>',
        '    </table>',
        '  </div>',
        '</section>',

        '<section class="evg-panel">',
        '  <div class="evg-panel-header">',
        '    <h3 class="evg-subheading">Mail Support Logs</h3>',
        '    <div class="evg-header-actions">',
        '      <select id="evgMailLogFile" class="evg-input"><option value="">Loading log files...</option></select>',
        '      <button class="evg-btn evg-btn-sec evg-btn-sm" id="evgMailLogRefreshBtn">Refresh Logs</button>',
        '    </div>',
        '  </div>',
        '  <div id="evgMailLogStatus" class="evg-status evg-muted">Loading mail support logs...</div>',
        '  <div class="evg-recon-stats-grid">',
        '    <div class="evg-stat-card"><div class="evg-stat-val" id="evgMailAttempted">--</div><div class="evg-stat-label">Attempts</div></div>',
        '    <div class="evg-stat-card"><div class="evg-stat-val" id="evgMailAccepted">--</div><div class="evg-stat-label">Accepted</div></div>',
        '    <div class="evg-stat-card"><div class="evg-stat-val" id="evgMailFailed">--</div><div class="evg-stat-label">Failed</div></div>',
        '    <div class="evg-stat-card"><div class="evg-stat-val" id="evgMailSkipped">--</div><div class="evg-stat-label">Skipped</div></div>',
        '  </div>',
        '  <div class="evg-table-wrap">',
        '    <table class="evg-table">',
        '      <thead>',
        '        <tr>',
        '          <th>Time</th><th>Level</th><th>Kind</th><th>Recipient</th><th>Transaction</th><th>Message</th><th>Details</th>',
        '        </tr>',
        '      </thead>',
        '      <tbody id="evgMailLogRows"><tr><td colspan="7" class="evg-muted">Loading mail support logs...</td></tr></tbody>',
        '    </table>',
        '  </div>',
        '</section>',

        // Reconciliation stats
        '<div class="evg-recon-stats-grid">',
        '  <div class="evg-stat-card"><div class="evg-stat-val" id="evgReconCollectedRows">--</div><div class="evg-stat-label">Collected Rows</div></div>',
        '  <div class="evg-stat-card"><div class="evg-stat-val" id="evgReconCollectedAmount">--</div><div class="evg-stat-label">Collected Amount</div></div>',
        '  <div class="evg-stat-card"><div class="evg-stat-val" id="evgReconPendingAmount">--</div><div class="evg-stat-label">Pending Amount</div></div>',
        '  <div class="evg-stat-card"><div class="evg-stat-val" id="evgReconCancelledRows">--</div><div class="evg-stat-label">Cancelled Rows</div></div>',
        '</div>',

        // Reconciliation table
        '<section class="evg-panel">',
        '  <div class="evg-panel-header">',
        '    <h3 class="evg-subheading">Razorpay Reconciliation <span id="evgReconCount" class="evg-count-chip"></span></h3>',
        '    <button class="evg-btn evg-btn-sec evg-btn-sm" id="evgClearReconFilters">Clear Filters</button>',
        '  </div>',
        '  <div class="evg-filter-row">',
        '    <input id="evgReconSearch" class="evg-input" placeholder="Search all columns...">',
        '  </div>',
        '  <div class="evg-table-wrap">',
        '    <table class="evg-table">',
        '      <thead>',
        '        <tr>',
        '          <th>Event</th><th>Guest</th><th>Transaction</th><th>Order/Payment</th><th>Amount</th><th>Status</th><th>Refund</th><th>Created</th><th>Confirmed</th>',
        '        </tr>',
        '        <tr class="evg-filter-tr">',
        '          <th><input id="evgRFEvent" class="evg-col-filter" placeholder="Filter..."></th>',
        '          <th><input id="evgRFGuest" class="evg-col-filter" placeholder="Filter..."></th>',
        '          <th><input id="evgRFTransaction" class="evg-col-filter" placeholder="Filter..."></th>',
        '          <th><input id="evgRFOrderPayment" class="evg-col-filter" placeholder="Filter..."></th>',
        '          <th><input id="evgRFAmount" class="evg-col-filter" placeholder="Filter..."></th>',
        '          <th><input id="evgRFStatus" class="evg-col-filter" placeholder="Filter..."></th>',
        '          <th><input id="evgRFRefund" class="evg-col-filter" placeholder="Filter..."></th>',
        '          <th><input id="evgRFCreated" class="evg-col-filter" placeholder="Filter..."></th>',
        '          <th><input id="evgRFConfirmed" class="evg-col-filter" placeholder="Filter..."></th>',
        '        </tr>',
        '      </thead>',
        '      <tbody id="evgReconRows"><tr><td colspan="9" class="evg-muted">Loading...</td></tr></tbody>',
        '    </table>',
        '  </div>',
        '</section>',

        '</div>'
      ].join('\n');
    },

    _injectStyles: function () {
      var id = 'evg-styles';
      if (document.getElementById(id)) return;
      var style = document.createElement('style');
      style.id = id;
      style.textContent = [
        '.evg-wrap { padding:16px; }',
        '.evg-header-row { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; margin-bottom:12px; }',
        '.evg-header-actions { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }',
        '.evg-heading { font-size:1.25rem; font-weight:700; margin:0; }',
        '.evg-subheading { font-size:1rem; font-weight:700; margin:0; }',
        '.evg-status { font-size:0.85rem; margin-bottom:10px; padding:6px 10px; border-radius:8px; background:rgba(0,0,0,0.04); }',
        '.evg-muted { color:#888; }',
        '.evg-panel { background:#fff; border:1px solid rgba(0,0,0,0.1); border-radius:12px; padding:16px; margin-bottom:16px; }',
        '.evg-panel-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; margin-bottom:10px; }',
        '.evg-export-btns { display:flex; gap:6px; flex-wrap:wrap; }',
        '.evg-stats-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:10px; margin-bottom:16px; }',
        '.evg-recon-stats-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:10px; margin-bottom:16px; }',
        '.evg-stat-card { background:#fff; border:1px solid rgba(0,0,0,0.1); border-radius:10px; padding:12px; text-align:center; }',
        '.evg-stat-val { font-size:1.4rem; font-weight:700; color:#214038; }',
        '.evg-stat-label { font-size:0.72rem; color:#888; margin-top:2px; }',
        '.evg-input { padding:7px 10px; border:1px solid rgba(123,94,67,0.24); border-radius:8px; font-size:0.84rem; outline:none; }',
        '.evg-input:focus { border-color:rgba(148,89,43,0.5); box-shadow:0 0 0 2px rgba(182,123,69,0.18); }',
        '.evg-filter-row { margin-bottom:8px; }',
        '.evg-filter-row .evg-input { width:100%; box-sizing:border-box; }',
        '.evg-count-chip { font-size:0.75rem; font-weight:400; color:#888; margin-left:6px; }',
        '.evg-table-wrap { overflow-x:auto; border-radius:10px; border:1px solid rgba(0,0,0,0.1); }',
        '.evg-table { width:100%; border-collapse:collapse; font-size:0.83rem; }',
        '.evg-table thead th { background:#f5efe2; padding:7px 8px; text-align:left; font-size:0.74rem; text-transform:uppercase; letter-spacing:0.05em; color:#888; border-bottom:1px solid rgba(0,0,0,0.1); white-space:nowrap; }',
        '.evg-table .evg-filter-tr th { background:#faf7f2; padding:4px 6px; }',
        '.evg-col-filter { width:100%; padding:4px 6px; border:1px solid rgba(123,94,67,0.2); border-radius:6px; font-size:0.78rem; outline:none; box-sizing:border-box; }',
        '.evg-table tbody td { padding:7px 8px; border-bottom:1px solid rgba(0,0,0,0.07); vertical-align:middle; }',
        '.evg-table tbody tr:last-child td { border-bottom:none; }',
        '.evg-table tbody tr:hover td { background:rgba(182,123,69,0.06); }',
        '.evg-log-details { min-width:240px; font-size:0.78rem; line-height:1.5; }',
        '.evg-log-details strong { color:#214038; }',
        '.evg-btn { padding:7px 14px; border-radius:8px; border:none; background:#214038; color:#fff; font-size:0.84rem; font-weight:600; cursor:pointer; }',
        '.evg-btn:disabled { opacity:0.5; cursor:default; }',
        '.evg-btn-sec { background:transparent; border:1px solid rgba(0,0,0,0.18); color:#214038; }',
        '.evg-btn-sm { padding:4px 10px; font-size:0.78rem; }',
        '@media(max-width:700px){.evg-wrap{padding:8px;} .evg-stats-grid{grid-template-columns:repeat(2,1fr);}}'
      ].join('\n');
      document.head.appendChild(style);
    }
  };
})(window.NK || (window.NK = {}));
