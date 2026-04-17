/**
 * NK Admin SPA Module: Cash Approvals (SuperAdmin only)
 * Source: superadmin-cash-approvals.html
 */
(function (NK) {
  'use strict';
  NK.MODULES = NK.MODULES || {};
  NK.MODULES['cash-approvals'] = {
    _container: null,
    _authClient: null,

    init: function (container, authClient, user) {
      this._container = container;
      this._authClient = authClient;

      var html = '<main class="page" style="max-width:1180px;">'
        + '<section class="hero"><h1>SuperAdmin Cash Receipt Desk</h1>'
        + '<p>Approve per-admin daily handovers, review cash cancel requests, and keep cash collections reconciled.</p></section>'

        + '<section class="panel"><div class="row">'
        + '<input id="caLedgerDate" type="date">'
        + '<button id="caRefreshBtn" class="secondary" type="button">Refresh</button>'
        + '</div><div id="caPanelStatus" class="status"></div></section>'

        + '<section class="panel"><h3>Pending Cash Handovers</h3>'
        + '<div class="table-wrap"><table><thead><tr>'
        + '<th>Date</th><th>Admin</th><th>Transactions</th><th>Amount</th><th>Requested</th><th>Action</th>'
        + '</tr></thead><tbody id="caHandoverRows"><tr><td colspan="6">No pending handovers.</td></tr></tbody>'
        + '</table></div></section>'

        + '<section class="panel"><h3>Cash Cancel Requests</h3>'
        + '<div class="table-wrap"><table><thead><tr>'
        + '<th>Transaction</th><th>Admin</th><th>Event</th><th>Amount</th><th>Reason</th><th>Action</th>'
        + '</tr></thead><tbody id="caCancelRows"><tr><td colspan="6">No pending cancel requests.</td></tr></tbody>'
        + '</table></div></section>'

        + '<section class="panel"><h3>Recent Approved Handovers</h3>'
        + '<div class="table-wrap"><table><thead><tr>'
        + '<th>Date</th><th>Admin</th><th>Transactions</th><th>Amount</th><th>Approved At</th><th>Status</th>'
        + '</tr></thead><tbody id="caApprovedRows"><tr><td colspan="6">No approvals yet.</td></tr></tbody>'
        + '</table></div></section>'
        + '</main>';

      container.innerHTML = html;
      var esc = NK.MODULE_BASE.escHtml.bind(NK.MODULE_BASE);
      var todayIso = NK.MODULE_BASE.todayIso.bind(NK.MODULE_BASE);

      var ledgerDateEl = container.querySelector('#caLedgerDate');
      var refreshBtn = container.querySelector('#caRefreshBtn');
      var panelStatusEl = container.querySelector('#caPanelStatus');
      var handoverRowsEl = container.querySelector('#caHandoverRows');
      var cancelRowsEl = container.querySelector('#caCancelRows');
      var approvedRowsEl = container.querySelector('#caApprovedRows');

      ledgerDateEl.value = todayIso();

      function setPanelStatus(msg) { panelStatusEl.textContent = String(msg || ''); }
      function formatDate(v) { return NK.MODULE_BASE.formatDate(v); }
      function formatCurrency(v) { return NK.MODULE_BASE.formatCurrency(v); }

      function renderDashboard(dashboard) {
        var handovers = (dashboard && Array.isArray(dashboard.pendingHandovers)) ? dashboard.pendingHandovers : [];
        if (handovers.length) {
          handoverRowsEl.innerHTML = handovers.map(function (item) {
            return '<tr>'
              + '<td>' + esc(item.ledgerDate || '-') + '</td>'
              + '<td>' + esc(item.adminDisplayName || item.adminUsername || '-') + '<br><small>' + esc(item.adminUsername || '') + '</small></td>'
              + '<td>' + Number(item.totalTransactions || 0) + '</td>'
              + '<td>' + formatCurrency(item.totalAmount || 0) + '</td>'
              + '<td>' + formatDate(item.requestedAt) + '</td>'
              + '<td><button data-action="approve-handover" data-admin="' + esc(item.adminUsername || '') + '" data-date="' + esc(item.ledgerDate || '') + '">Approve Received</button></td>'
              + '</tr>';
          }).join('');
        } else {
          handoverRowsEl.innerHTML = '<tr><td colspan="6">No pending handovers.</td></tr>';
        }

        var cancels = (dashboard && Array.isArray(dashboard.cancelRequests)) ? dashboard.cancelRequests : [];
        if (cancels.length) {
          cancelRowsEl.innerHTML = cancels.map(function (item) {
            return '<tr>'
              + '<td>' + esc(item.transactionId || '-') + '</td>'
              + '<td>' + esc(item.cancelRequestBy || item.issuedBy || '-') + '</td>'
              + '<td>' + esc(item.eventTitle || '-') + '</td>'
              + '<td>' + formatCurrency(item.amount || 0) + '</td>'
              + '<td>' + esc(item.cancelRequestReason || '-') + '</td>'
              + '<td>'
              + '<button data-action="approve-cancel" data-tx="' + esc(item.transactionId || '') + '">Approve Cancel</button> '
              + '<button class="secondary" data-action="reject-cancel" data-tx="' + esc(item.transactionId || '') + '">Reject</button>'
              + '</td></tr>';
          }).join('');
        } else {
          cancelRowsEl.innerHTML = '<tr><td colspan="6">No pending cancel requests.</td></tr>';
        }

        var approvals = (dashboard && Array.isArray(dashboard.recentApprovals)) ? dashboard.recentApprovals : [];
        if (approvals.length) {
          approvedRowsEl.innerHTML = approvals.map(function (item) {
            return '<tr>'
              + '<td>' + esc(item.ledgerDate || '-') + '</td>'
              + '<td>' + esc(item.adminDisplayName || item.adminUsername || '-') + '</td>'
              + '<td>' + Number(item.totalTransactions || 0) + '</td>'
              + '<td>' + formatCurrency(item.totalAmount || 0) + '</td>'
              + '<td>' + formatDate(item.approvedAt) + '</td>'
              + '<td><span class="pill approved">Approved</span></td>'
              + '</tr>';
          }).join('');
        } else {
          approvedRowsEl.innerHTML = '<tr><td colspan="6">No approvals yet.</td></tr>';
        }
      }

      function loadDashboard() {
        setPanelStatus('Loading dashboard...');
        authClient.apiGet('superadmin_cash_dashboard', { ledgerDate: ledgerDateEl.value || todayIso() })
          .then(function (payload) {
            renderDashboard(payload.dashboard);
            setPanelStatus('Dashboard loaded.');
          })
          .catch(function (err) {
            setPanelStatus('Unable to load dashboard: ' + err.message);
          });
      }

      function approveHandover(adminUsername, ledgerDate) {
        setPanelStatus('Approving ' + adminUsername + ' for ' + ledgerDate + '...');
        authClient.apiPost({ action: 'superadmin_approve_cash_handover', adminUsername: adminUsername, ledgerDate: ledgerDate })
          .then(function (payload) {
            setPanelStatus('Approved ' + formatCurrency(payload.totalAmount) + ' for ' + adminUsername + '.');
            loadDashboard();
          })
          .catch(function (err) {
            setPanelStatus('Approval failed: ' + err.message);
          });
      }

      function resolveCancel(transactionId, decision) {
        var note = window.prompt((decision === 'approve' ? 'Approve cancel' : 'Reject cancel') + ' for ' + transactionId + '. Optional note:') || '';
        setPanelStatus((decision === 'approve' ? 'Approving' : 'Rejecting') + ' cancel for ' + transactionId + '...');
        authClient.apiPost({ action: 'superadmin_resolve_cash_cancel', transactionId: transactionId, decision: decision, note: note })
          .then(function () {
            setPanelStatus('Cancel request ' + decision + 'd for ' + transactionId + '.');
            loadDashboard();
          })
          .catch(function (err) {
            setPanelStatus('Cancel review failed: ' + err.message);
          });
      }

      handoverRowsEl.addEventListener('click', function (event) {
        var target = event.target;
        if (!(target instanceof HTMLElement)) return;
        if (String(target.getAttribute('data-action') || '') === 'approve-handover') {
          approveHandover(String(target.getAttribute('data-admin') || ''), String(target.getAttribute('data-date') || ''));
        }
      });

      cancelRowsEl.addEventListener('click', function (event) {
        var target = event.target;
        if (!(target instanceof HTMLElement)) return;
        var action = String(target.getAttribute('data-action') || '');
        var tx = String(target.getAttribute('data-tx') || '');
        if (action === 'approve-cancel') resolveCancel(tx, 'approve');
        if (action === 'reject-cancel') resolveCancel(tx, 'reject');
      });

      refreshBtn.addEventListener('click', loadDashboard);

      loadDashboard();
    },

    destroy: function () {
      this._container = null;
      this._authClient = null;
    }
  };
})(window.NK || (window.NK = {}));
