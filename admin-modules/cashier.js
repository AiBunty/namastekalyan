/**
 * NK Admin SPA Module: Cashier
 * Source: admin-cashier.html
 */
(function (NK) {
  'use strict';
  NK.MODULES = NK.MODULES || {};
  NK.MODULES['cashier'] = {
    _container: null,
    _authClient: null,

    init: function (container, authClient) {
      this._container = container;
      this._authClient = authClient;

      var html = '<main class="page" style="max-width:1180px;">'
        + '<section class="hero"><h1>Admin Cashier Desk</h1>'
        + '<p>Issue paid event passes against cash, track your daily counter, request handover, and raise cancel requests for superadmin review.</p></section>'

        + '<section class="panel"><div class="row">'
        + '<select id="cshEventSelect"></select>'
        + '<input id="cshLedgerDate" type="date">'
        + '<button id="cshRefreshBtn" class="secondary" type="button">Refresh Summary</button>'
        + '<button id="cshHandoverBtn" type="button">Request Cash Handover</button>'
        + '</div>'
        + '<div class="stats" style="display:flex;flex-wrap:wrap;gap:10px;margin-top:10px;">'
        + '<div class="stat"><span class="label">Today Total</span><span class="value" id="cshStatTotal">0</span></div>'
        + '<div class="stat"><span class="label">Pending Cash</span><span class="value" id="cshStatPending">0</span></div>'
        + '<div class="stat"><span class="label">Requested Handover</span><span class="value" id="cshStatRequested">0</span></div>'
        + '<div class="stat"><span class="label">Approved Today</span><span class="value" id="cshStatApproved">0</span></div>'
        + '</div>'
        + '<div id="cshSummaryStatus" class="status"></div></section>'

        + '<section class="panel"><h3>Issue Paid Pass Against Cash</h3>'
        + '<div class="row">'
        + '<input id="cshCustomerName" type="text" maxlength="80" placeholder="Customer name">'
        + '<input id="cshCustomerPhone" type="tel" inputmode="numeric" maxlength="15" placeholder="Customer phone">'
        + '<input id="cshCustomerEmail" type="email" maxlength="120" placeholder="Customer email (optional)">'
        + '<input id="cshQty" type="number" min="1" max="20" value="1" placeholder="Qty">'
        + '</div>'
        + '<div class="row" style="margin-top:10px;">'
        + '<textarea id="cshAttendees" style="min-width:180px;min-height:86px;" placeholder="Attendee names, comma or newline separated."></textarea>'
        + '</div>'
        + '<div class="row" style="margin-top:10px;">'
        + '<textarea id="cshNotes" style="min-width:180px;min-height:86px;" placeholder="Notes for cash issue (optional)"></textarea>'
        + '</div>'
        + '<div class="row" style="margin-top:10px;">'
        + '<button id="cshIssueBtn" type="button">Issue Cash Paid Pass</button>'
        + '</div>'
        + '<div id="cshIssueStatus" class="status"></div></section>'

        + '<section class="panel"><div class="row" style="justify-content:space-between;"><h3 style="margin:0;">Recent Cash Transactions</h3></div>'
        + '<div class="table-wrap"><table><thead><tr>'
        + '<th>Transaction</th><th>Event</th><th>Customer</th><th>Amount</th><th>Status</th><th>Created</th><th>Actions</th>'
        + '</tr></thead><tbody id="cshTxRows"><tr><td colspan="7">No data yet.</td></tr></tbody>'
        + '</table></div></section>'

        + '<section class="panel"><div class="row" style="justify-content:space-between;"><h3 style="margin:0;">Handover History</h3></div>'
        + '<div class="table-wrap"><table><thead><tr>'
        + '<th>Date</th><th>Batch</th><th>Transactions</th><th>Amount</th><th>Status</th><th>Approved By</th>'
        + '</tr></thead><tbody id="cshHandoverRows"><tr><td colspan="6">No handovers yet.</td></tr></tbody>'
        + '</table></div></section>'
        + '</main>';

      container.innerHTML = html;

      var esc = NK.MODULE_BASE.escHtml.bind(NK.MODULE_BASE);
      var todayIso = NK.MODULE_BASE.todayIso.bind(NK.MODULE_BASE);

      var eventSelectEl = container.querySelector('#cshEventSelect');
      var ledgerDateEl = container.querySelector('#cshLedgerDate');
      var refreshBtn = container.querySelector('#cshRefreshBtn');
      var handoverBtn = container.querySelector('#cshHandoverBtn');
      var summaryStatusEl = container.querySelector('#cshSummaryStatus');
      var issueStatusEl = container.querySelector('#cshIssueStatus');
      var customerNameEl = container.querySelector('#cshCustomerName');
      var customerPhoneEl = container.querySelector('#cshCustomerPhone');
      var customerEmailEl = container.querySelector('#cshCustomerEmail');
      var qtyEl = container.querySelector('#cshQty');
      var attendeesEl = container.querySelector('#cshAttendees');
      var notesEl = container.querySelector('#cshNotes');
      var issueBtn = container.querySelector('#cshIssueBtn');
      var txRowsEl = container.querySelector('#cshTxRows');
      var handoverRowsEl = container.querySelector('#cshHandoverRows');
      var statTotalEl = container.querySelector('#cshStatTotal');
      var statPendingEl = container.querySelector('#cshStatPending');
      var statRequestedEl = container.querySelector('#cshStatRequested');
      var statApprovedEl = container.querySelector('#cshStatApproved');

      ledgerDateEl.value = todayIso();

      function setSummaryStatus(msg) { summaryStatusEl.textContent = String(msg || ''); }
      function setIssueStatus(msg) { issueStatusEl.textContent = String(msg || ''); }
      function formatDate(v) { return NK.MODULE_BASE.formatDate(v); }
      function formatCurrency(v) { return NK.MODULE_BASE.formatCurrency(v); }

      function loadPaidEvents() {
        return authClient.apiGet('events_list', { limit: '20' })
          .then(function (payload) {
            var items = (payload && payload.ok && Array.isArray(payload.items))
              ? payload.items.filter(function (item) {
                return item && (item.paymentEnabled || String(item.eventType || '').toLowerCase() === 'paid');
              })
              : [];
            eventSelectEl.innerHTML = items.length ? '' : '<option value="">No active paid events</option>';
            items.forEach(function (item) {
              var option = document.createElement('option');
              option.value = String(item.id || '');
              option.textContent = String(item.title || item.id) + ' - INR ' + Number(item.ticketPrice || 0).toFixed(2);
              eventSelectEl.appendChild(option);
            });
            eventSelectEl.disabled = items.length === 0;
            issueBtn.disabled = items.length === 0;
            if (!items.length) {
              setIssueStatus('No active paid events are currently available.');
            }
          });
      }

      function renderSummary(summary) {
        var totals = (summary && summary.totals) ? summary.totals : {};
        statTotalEl.textContent = formatCurrency(totals.totalAmount || 0);
        statPendingEl.textContent = formatCurrency(totals.pendingAmount || 0);
        statRequestedEl.textContent = formatCurrency(totals.requestedAmount || 0);
        statApprovedEl.textContent = formatCurrency(totals.approvedAmount || 0);

        var txs = (summary && Array.isArray(summary.recentTransactions)) ? summary.recentTransactions : [];
        if (txs.length) {
          txRowsEl.innerHTML = txs.map(function (item) {
            var statusClass = String(item.status || '').toLowerCase().indexOf('cancel') !== -1 ? 'cancel' : 'pending';
            return '<tr>'
              + '<td>' + esc(item.transactionId || '-') + '</td>'
              + '<td>' + esc(item.eventTitle || '-') + '</td>'
              + '<td>' + esc(item.customerName || '-') + '<br><small>' + esc(item.customerPhone || '') + '</small></td>'
              + '<td>' + formatCurrency(item.amount || 0) + '</td>'
              + '<td><span class="pill ' + esc(statusClass) + '">' + esc(item.status || '-') + '</span></td>'
              + '<td>' + formatDate(item.createdAt) + '</td>'
              + '<td><button class="warn" data-action="cancel" data-tx="' + esc(item.transactionId || '') + '">Request Cancel</button></td>'
              + '</tr>';
          }).join('');
        } else {
          txRowsEl.innerHTML = '<tr><td colspan="7">No cash transactions found.</td></tr>';
        }

        var handovers = (summary && Array.isArray(summary.handoverHistory)) ? summary.handoverHistory : [];
        if (handovers.length) {
          handoverRowsEl.innerHTML = handovers.map(function (item) {
            var cls = String(item.status || '').toLowerCase() === 'approved' ? 'approved' : 'requested';
            return '<tr>'
              + '<td>' + esc(item.ledgerDate || '-') + '</td>'
              + '<td>' + esc(item.batchKey || '-') + '</td>'
              + '<td>' + Number(item.totalTransactions || 0) + '</td>'
              + '<td>' + formatCurrency(item.totalAmount || 0) + '</td>'
              + '<td><span class="pill ' + esc(cls) + '">' + esc(item.status || '-') + '</span></td>'
              + '<td>' + esc(item.approvedBy || '-') + '</td>'
              + '</tr>';
          }).join('');
        } else {
          handoverRowsEl.innerHTML = '<tr><td colspan="6">No handovers yet.</td></tr>';
        }
      }

      function loadSummary() {
        setSummaryStatus('Loading summary...');
        return authClient.apiGet('admin_cash_summary', { ledgerDate: ledgerDateEl.value || todayIso() })
          .then(function (payload) {
            renderSummary(payload.summary);
            setSummaryStatus('Summary loaded for ' + (payload.summary && payload.summary.ledgerDate ? payload.summary.ledgerDate : (ledgerDateEl.value || todayIso())) + '.');
          })
          .catch(function (err) {
            setSummaryStatus('Unable to load summary: ' + err.message);
          });
      }

      function issueCashPass() {
        var eventId = String(eventSelectEl.value || '').trim();
        var customerName = String(customerNameEl.value || '').trim();
        var customerPhone = String(customerPhoneEl.value || '').trim();
        var customerEmail = String(customerEmailEl.value || '').trim();
        var qty = Math.max(1, Number(qtyEl.value || 1));
        var attendeeNames = String(attendeesEl.value || '').trim();
        var notes = String(notesEl.value || '').trim();
        if (!eventId || !customerName || !customerPhone) {
          setIssueStatus('Paid event, customer name, and phone are required.');
          return;
        }
        issueBtn.disabled = true;
        setIssueStatus('Issuing cash paid pass...');
        authClient.apiPost({ action: 'admin_issue_cash_paid_pass', eventId: eventId, customerName: customerName, customerPhone: customerPhone, customerEmail: customerEmail, qty: qty, attendeeNames: attendeeNames, notes: notes })
          .then(function (payload) {
            setIssueStatus('Cash pass issued.\nTransaction: ' + payload.transactionId + '\nAmount: ' + formatCurrency(payload.amount) + '\nQR: ' + (payload.qrUrl || ''));
            attendeesEl.value = '';
            notesEl.value = '';
            return loadSummary();
          })
          .catch(function (err) {
            setIssueStatus('Issue failed: ' + err.message);
          })
          .finally(function () {
            issueBtn.disabled = false;
          });
      }

      function requestHandover() {
        handoverBtn.disabled = true;
        setSummaryStatus('Requesting handover...');
        authClient.apiPost({ action: 'admin_request_cash_handover', ledgerDate: ledgerDateEl.value || todayIso() })
          .then(function (payload) {
            setSummaryStatus('Handover requested. Batch ' + payload.batchKey + ' for ' + formatCurrency(payload.totalAmount) + '.');
            return loadSummary();
          })
          .catch(function (err) {
            setSummaryStatus('Handover request failed: ' + err.message);
          })
          .finally(function () {
            handoverBtn.disabled = false;
          });
      }

      function requestCancel(transactionId) {
        var reason = window.prompt('Reason for cancel request for ' + transactionId);
        if (!reason) return;
        setSummaryStatus('Requesting cancel for ' + transactionId + '...');
        authClient.apiPost({ action: 'admin_request_cash_cancel', transactionId: transactionId, reason: reason })
          .then(function () {
            setSummaryStatus('Cancel request sent for ' + transactionId + '.');
            return loadSummary();
          })
          .catch(function (err) {
            setSummaryStatus('Cancel request failed: ' + err.message);
          });
      }

      txRowsEl.addEventListener('click', function (event) {
        var target = event.target;
        if (!(target instanceof HTMLElement)) return;
        if (String(target.getAttribute('data-action') || '') === 'cancel') {
          requestCancel(String(target.getAttribute('data-tx') || ''));
        }
      });

      refreshBtn.addEventListener('click', loadSummary);
      handoverBtn.addEventListener('click', requestHandover);
      issueBtn.addEventListener('click', issueCashPass);

      loadPaidEvents().then(function () { return loadSummary(); });
    },

    destroy: function () {
      this._container = null;
      this._authClient = null;
    }
  };
})(window.NK || (window.NK = {}));
