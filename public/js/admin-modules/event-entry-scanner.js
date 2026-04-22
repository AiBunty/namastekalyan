// admin-modules/event-entry-scanner.js  v=20260417-spa-1
// Module: Event Entry Scanner (prefix: scn)
(function (NK) {
  NK.MODULES = NK.MODULES || {};

  NK.MODULES['event-entry-scanner'] = {
    _container: null,
    _authClient: null,
    // scanner state stored on module for destroy()
    _renderQueueTimer: null,
    _persistQueueTimer: null,
    _html5QrCode: null,
    _scannerRunning: false,
    _audioContext: null,

    init: function (container, authClient, user) {
      this._container = container;
      this._authClient = authClient;
      this._renderQueueTimer = null;
      this._persistQueueTimer = null;
      this._html5QrCode = null;
      this._scannerRunning = false;
      this._audioContext = null;

      var mod = this;

      // ── Constants ──────────────────────────────────────────────────
      var CACHE_KEY = 'nk_event_scanner_queue_v2';
      var SCAN_DEDUP_MS = 1000;
      var QUEUE_RENDER_BATCH_MS = 80;
      var QUEUE_CACHE_BATCH_MS = 250;

      // ── CSS ─────────────────────────────────────────────────────────
      var styleEl = document.createElement('style');
      styleEl.textContent = [
        '.scn-page { font-family: "Avenir Next","Trebuchet MS",Verdana,sans-serif; color: #2f241b; }',
        '.scn-layout { display: grid; grid-template-columns: minmax(280px,360px) minmax(0,1fr); gap: 18px; align-items: start; }',
        '.scn-panel { padding: 18px; border-radius: 18px; background: rgba(255,255,255,0.84); border: 1px solid rgba(105,74,43,0.12); box-shadow: 0 16px 34px rgba(66,44,21,0.1); }',
        '.scn-panel-title { display: flex; justify-content: space-between; gap: 12px; align-items: center; margin-bottom: 10px; }',
        '.scn-panel-title h2 { margin: 0; font-size: 1rem; }',
        '.scn-panel-subtle { color: #7d6a59; font-size: 0.88rem; margin-bottom: 14px; }',
        '.scn-hero { margin-bottom: 18px; padding: 18px; border-radius: 18px; background: rgba(255,255,255,0.84); border: 1px solid rgba(105,74,43,0.12); }',
        '.scn-hero h1 { margin: 0 0 6px; font-size: 1.5rem; font-family: Georgia,serif; }',
        '.scn-hero p { margin: 0; color: #7d6a59; max-width: 720px; }',
        '.scn-camera-frame { position: relative; min-height: 250px; border-radius: 18px; background: linear-gradient(135deg,rgba(32,34,40,0.96),rgba(12,14,18,0.98)); border: 1px solid rgba(255,255,255,0.08); overflow: hidden; }',
        '.scn-camera-frame::after { content:""; position:absolute; inset:22px; border:2px dashed rgba(212,175,116,0.5); border-radius:18px; pointer-events:none; }',
        '#scnReader { min-height: 250px; border-radius: 18px; overflow: hidden; }',
        '.scn-camera-placeholder { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; text-align:center; padding:26px; color:rgba(255,255,255,0.76); font-size:0.95rem; line-height:1.5; pointer-events:none; }',
        '.scn-controls, .scn-manual-actions, .scn-queue-toolbar, .scn-summary-grid { display:flex; flex-wrap:wrap; gap:10px; }',
        '.scn-controls, .scn-manual-actions, .scn-queue-toolbar { margin-top:14px; }',
        '.scn-controls button, .scn-manual-actions button, .scn-queue-toolbar button { flex:1; min-width:130px; }',
        '.scn-controls select { flex:1 1 190px; margin-bottom:0; }',
        '.scn-status-line { margin-top:12px; padding:10px 12px; border-radius:14px; font-size:0.88rem; background:rgba(255,255,255,0.66); border:1px solid rgba(105,74,43,0.1); color:#6a4a30; }',
        '.scn-summary-grid { margin-top:16px; }',
        '.scn-card { flex:1 1 130px; min-width:130px; padding:14px; border-radius:16px; background:linear-gradient(180deg,rgba(255,255,255,0.92),rgba(248,241,232,0.92)); border:1px solid rgba(105,74,43,0.1); }',
        '.scn-card .scn-card-label { color:#7d6a59; font-size:0.77rem; text-transform:uppercase; letter-spacing:0.08em; font-weight:800; margin-bottom:6px; }',
        '.scn-card .scn-card-value { font-size:1.5rem; font-weight:800; color:#5d3718; }',
        '.scn-badge { display:inline-flex; align-items:center; justify-content:center; padding:5px 10px; border-radius:999px; font-size:0.74rem; letter-spacing:0.04em; text-transform:uppercase; font-weight:800; white-space:nowrap; }',
        '.scn-badge.valid { background:rgba(47,158,68,0.12); color:#25783b; }',
        '.scn-badge.confirmed { background:rgba(31,96,196,0.12); color:#1f60c4; }',
        '.scn-badge.error { background:rgba(198,40,40,0.1); color:#a22222; }',
        '.scn-badge.pending { background:rgba(212,175,116,0.18); color:#7a531c; }',
        '.scn-queue-wrap { overflow-x:auto; margin-top:14px; border-radius:16px; border:1px solid rgba(105,74,43,0.12); background:rgba(255,255,255,0.86); }',
        '.scn-queue-wrap table { width:100%; border-collapse:collapse; min-width:760px; }',
        '.scn-queue-wrap th,.scn-queue-wrap td { padding:12px 10px; border-bottom:1px solid rgba(105,74,43,0.1); text-align:left; vertical-align:top; font-size:0.9rem; }',
        '.scn-queue-wrap th { position:sticky; top:0; z-index:1; background:#f5ecdf; color:#6a4726; font-size:0.78rem; letter-spacing:0.08em; text-transform:uppercase; }',
        '.scn-queue-wrap tr:last-child td { border-bottom:none; }',
        '.scn-guest-meta { display:flex; flex-direction:column; gap:3px; }',
        '.scn-guest-meta small { color:#7d6a59; }',
        '.scn-message-cell { max-width:260px; }',
        '.scn-arrival-picker { display:grid; gap:6px; min-width:220px; }',
        '.scn-arrival-count { font-size:0.78rem; color:#7d6a59; font-weight:700; }',
        '.scn-name-list { display:grid; gap:4px; max-height:142px; overflow:auto; padding:8px; border:1px solid rgba(105,74,43,0.14); border-radius:12px; background:rgba(255,251,246,0.92); }',
        '.scn-name-option { display:flex; align-items:flex-start; gap:8px; font-size:0.82rem; color:#4f3a29; }',
        '.scn-name-option input { margin-top:2px; }',
        '.scn-inline-meta { display:flex; flex-wrap:wrap; gap:6px; margin-top:4px; }',
        '.scn-inline-pill { display:inline-flex; align-items:center; padding:3px 8px; border-radius:999px; background:rgba(212,175,116,0.14); color:#7a531c; font-size:0.72rem; font-weight:700; }',
        '.scn-select-all { display:inline-flex; align-items:center; gap:6px; font-size:0.85rem; color:#7d6a59; }',
        '.scn-empty-state { padding:28px; text-align:center; color:#7d6a59; }',
        '.scn-mobile-save-bar { display:none; position:fixed; bottom:0; left:0; right:0; z-index:200; flex-direction:column; gap:6px; padding:10px 14px; padding-bottom:calc(10px + env(safe-area-inset-bottom,0px)); background:rgba(250,243,233,0.97); border-top:1px solid rgba(105,74,43,0.2); backdrop-filter:blur(12px); box-shadow:0 -4px 20px rgba(66,44,21,0.12); }',
        '.scn-mobile-save-bar .scn-save-bar-info { margin:0; font-size:0.76rem; color:#7d6a59; text-align:center; }',
        '.scn-mobile-save-bar .scn-save-bar-btns { display:flex; gap:8px; }',
        '.scn-mobile-save-bar button { flex:1; min-height:50px; border-radius:12px; font-size:0.95rem; font-weight:700; }',
        'textarea.scn-bulk { min-height:108px; resize:vertical; width:100%; box-sizing:border-box; }',
        'input.scn-manual { width:100%; box-sizing:border-box; }',
        'label.scn-label { display:block; font-size:0.84rem; color:#5f4d3f; margin-bottom:4px; margin-top:10px; font-weight:700; }',
        '@media (max-width:1200px) { .scn-queue-wrap table { min-width:700px; } }',
        '@media (max-width:980px) { .scn-layout { grid-template-columns:1fr; } .scn-queue-toolbar { display:grid; grid-template-columns:1fr 1fr; gap:10px; } .scn-queue-toolbar button { min-width:0; } }',
        '@media (max-width:768px) {',
        '  .scn-mobile-save-bar { display:flex; }',
        '  .scn-page { padding-bottom:96px; }',
        '  .scn-controls { display:grid; grid-template-columns:1fr 1fr; gap:10px; }',
        '  .scn-controls select { grid-column:1/-1; }',
        '  .scn-manual-actions { display:grid; grid-template-columns:1fr 1fr; gap:10px; }',
        '  .scn-queue-toolbar { display:grid; grid-template-columns:1fr 1fr; gap:10px; }',
        '  .scn-summary-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; }',
        '  .scn-camera-frame,#scnReader { min-height:300px; }',
        '  .scn-queue-wrap { overflow-x:visible; border:none; background:transparent; border-radius:0; }',
        '  .scn-queue-wrap table { min-width:0; display:block; }',
        '  .scn-queue-wrap thead { display:none; }',
        '  .scn-queue-wrap tbody { display:block; }',
        '  .scn-queue-wrap tbody tr { display:block; position:relative; margin-bottom:10px; border-radius:14px; border:1px solid rgba(105,74,43,0.14); background:#fff; padding:12px 44px 14px 12px; }',
        '  .scn-queue-wrap tbody tr.scn-empty-row { position:static; padding:20px; text-align:center; }',
        '  .scn-queue-wrap td { display:block; border:none; padding:2px 0; font-size:0.88rem; }',
        '  .scn-queue-wrap td[data-label]::before { content:attr(data-label); display:block; font-size:0.68rem; font-weight:800; color:#7d6a59; letter-spacing:0.07em; text-transform:uppercase; margin-top:9px; margin-bottom:2px; }',
        '  .scn-queue-wrap td.scn-no-label::before { display:none; }',
        '  .scn-queue-wrap td.scn-no-label { padding:0; }',
        '  .scn-queue-wrap tbody tr:not(.scn-empty-row) > td:first-child { position:absolute; top:12px; right:12px; padding:0; display:flex; align-items:flex-start; }',
        '  .scn-queue-wrap td:first-child input[type="checkbox"] { width:22px; height:22px; cursor:pointer; }',
        '  .scn-message-cell { max-width:none !important; }',
        '}',
        '@media (max-width:520px) {',
        '  .scn-controls, .scn-manual-actions, .scn-queue-toolbar, .scn-mobile-save-bar .scn-save-bar-btns { grid-template-columns:1fr; }',
        '  .scn-camera-frame,#scnReader { min-height:260px; }',
        '}'
      ].join('\n');
      container.appendChild(styleEl);

      // ── HTML ────────────────────────────────────────────────────────
      var div = document.createElement('div');
      div.className = 'scn-page';
      div.innerHTML = [
        '<section class="scn-hero">',
        '  <h1>Event Entry Scanner</h1>',
        '  <p>Use the device camera for walk-in scanning, or paste QR text in bulk. Every scan is cached locally first, then the final save sends the batch to the server.</p>',
        '</section>',
        '<div class="scn-layout">',
        '  <section class="scn-panel">',
        '    <div class="scn-panel-title"><h2>Camera Scanner</h2><span id="scnScannerBadge" class="scn-badge pending">Idle</span></div>',
        '    <div class="scn-panel-subtle">Best for gate entry. Scans are captured locally with audible feedback.</div>',
        '    <div class="scn-camera-frame">',
        '      <div id="scnReader"></div>',
        '      <div id="scnCameraPlaceholder" class="scn-camera-placeholder">Start the scanner to use the rear camera for guest QR entry.</div>',
        '    </div>',
        '    <div class="scn-controls">',
        '      <select id="scnCameraSelect"><option value="">Loading cameras...</option></select>',
        '      <button id="scnStartBtn" type="button">Start Scanner</button>',
        '      <button id="scnStopBtn" type="button" class="secondary" disabled>Stop Scanner</button>',
        '    </div>',
        '    <div id="scnScannerStatus" class="scn-status-line">Preparing scanner session...</div>',
        '    <div class="scn-summary-grid">',
        '      <article class="scn-card"><div class="scn-card-label">Cached</div><div id="scnQueuedCount" class="scn-card-value">0</div></article>',
        '      <article class="scn-card"><div class="scn-card-label">Ready</div><div id="scnReadyCount" class="scn-card-value">0</div></article>',
        '      <article class="scn-card"><div class="scn-card-label">Saved</div><div id="scnConfirmedCount" class="scn-card-value">0</div></article>',
        '      <article class="scn-card"><div class="scn-card-label">Issues</div><div id="scnIssueCount" class="scn-card-value">0</div></article>',
        '    </div>',
        '  </section>',
        '  <section class="scn-panel">',
        '    <div class="scn-panel-title"><h2>Queue And Confirm</h2><span id="scnAuthBadge" class="scn-badge pending">Checking</span></div>',
        '    <div class="scn-panel-subtle">Scans are stored in browser cache immediately and survive accidental refresh.</div>',
        '    <label class="scn-label" for="scnManualInput">Single scan or scanner-wedge input</label>',
        '    <input id="scnManualInput" class="scn-manual" type="text" placeholder="Paste QR URL/text here and press Enter" autocomplete="off" />',
        '    <label class="scn-label" for="scnBulkInput">Bulk paste</label>',
        '    <textarea id="scnBulkInput" class="scn-bulk" placeholder="Paste one QR value per line"></textarea>',
        '    <div class="scn-manual-actions">',
        '      <button id="scnAddManualBtn" type="button">Add Single Scan</button>',
        '      <button id="scnAddBulkBtn" type="button" class="secondary">Add Bulk Scans</button>',
        '    </div>',
        '    <div id="scnQueueStatus" class="scn-status-line">Queue is empty. New scans will be cached here first.</div>',
        '    <div class="scn-queue-toolbar">',
        '      <button id="scnConfirmSelBtn" type="button">Save Selected</button>',
        '      <button id="scnConfirmAllBtn" type="button" class="secondary">Save All Ready</button>',
        '      <button id="scnRemoveSelBtn" type="button" class="secondary">Remove Selected</button>',
        '      <button id="scnClearBtn" type="button" class="secondary">Clear Queue</button>',
        '    </div>',
        '    <div class="scn-queue-wrap">',
        '      <table>',
        '        <thead><tr>',
        '          <th><label class="scn-select-all"><input id="scnSelectAll" type="checkbox"><span>Select</span></label></th>',
        '          <th>Status</th><th>Guest / Ticket</th><th>Event</th><th>Type</th>',
        '          <th>Source</th><th>Arrived Now</th><th>Local Check</th><th>Message</th><th>Scanned</th>',
        '        </tr></thead>',
        '        <tbody id="scnQueueBody"><tr><td colspan="10" class="scn-empty-state">No QR scans queued yet.</td></tr></tbody>',
        '      </table>',
        '    </div>',
        '  </section>',
        '</div>',
        '<div class="scn-mobile-save-bar" id="scnMobileSaveBar">',
        '  <p class="scn-save-bar-info" id="scnMobileSaveInfo">No scans queued</p>',
        '  <div class="scn-save-bar-btns">',
        '    <button id="scnMobileSaveAllBtn" type="button">Save All Ready</button>',
        '    <button id="scnMobileSaveSelBtn" type="button" class="secondary">Save Selected</button>',
        '  </div>',
        '</div>'
      ].join('');
      container.appendChild(div);

      // ── Element refs ────────────────────────────────────────────────
      var scannerBadge     = container.querySelector('#scnScannerBadge');
      var authBadge        = container.querySelector('#scnAuthBadge');
      var scannerStatusEl  = container.querySelector('#scnScannerStatus');
      var queueStatusEl    = container.querySelector('#scnQueueStatus');
      var queuedCountEl    = container.querySelector('#scnQueuedCount');
      var readyCountEl     = container.querySelector('#scnReadyCount');
      var confirmedCountEl = container.querySelector('#scnConfirmedCount');
      var issueCountEl     = container.querySelector('#scnIssueCount');
      var cameraSelectEl   = container.querySelector('#scnCameraSelect');
      var startBtn         = container.querySelector('#scnStartBtn');
      var stopBtn          = container.querySelector('#scnStopBtn');
      var cameraPlaceholder= container.querySelector('#scnCameraPlaceholder');
      var manualInputEl    = container.querySelector('#scnManualInput');
      var bulkInputEl      = container.querySelector('#scnBulkInput');
      var addManualBtn     = container.querySelector('#scnAddManualBtn');
      var addBulkBtn       = container.querySelector('#scnAddBulkBtn');
      var confirmSelBtn    = container.querySelector('#scnConfirmSelBtn');
      var confirmAllBtn    = container.querySelector('#scnConfirmAllBtn');
      var removeSelBtn     = container.querySelector('#scnRemoveSelBtn');
      var clearBtn         = container.querySelector('#scnClearBtn');
      var selectAllEl      = container.querySelector('#scnSelectAll');
      var queueBody        = container.querySelector('#scnQueueBody');
      var mobileSaveInfo   = container.querySelector('#scnMobileSaveInfo');
      var mobileSaveAllBtn = container.querySelector('#scnMobileSaveAllBtn');
      var mobileSaveSelBtn = container.querySelector('#scnMobileSaveSelBtn');

      // ── local state ─────────────────────────────────────────────────
      var queue = [];
      var rowSeq = 1;
      var recentScanText = '';
      var recentScanTime = 0;
      var authReady = false;
      var lastQueuedId = '';

      // ── Helpers ─────────────────────────────────────────────────────
      function esc(v) {
        return String(v == null ? '' : v)
          .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
      }

      function nowLabel() {
        return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
      }

      function safeParseJson(text) {
        try { return JSON.parse(text); } catch (e) { return null; }
      }

      function setScannerStatus(msg, ok) {
        scannerStatusEl.textContent = String(msg || '');
        scannerStatusEl.style.color = ok ? '#246a38' : '#7a4f22';
      }

      function setQueueStatus(msg, ok) {
        queueStatusEl.textContent = String(msg || '');
        queueStatusEl.style.color = ok ? '#246a38' : '#7a4f22';
      }

      function setBadge(el, text, kind) {
        if (!el) return;
        el.textContent = String(text || '');
        el.className = 'scn-badge ' + String(kind || 'pending');
      }

      // ── Audio ────────────────────────────────────────────────────────
      function ensureAudio() {
        var Ctor = window.AudioContext || window.webkitAudioContext;
        if (!Ctor) return null;
        if (!mod._audioContext) mod._audioContext = new Ctor();
        if (mod._audioContext.state === 'suspended') mod._audioContext.resume().catch(function () {});
        return mod._audioContext;
      }

      function playTone(freq, durMs, vol, wave, offset) {
        var ctx = ensureAudio();
        if (!ctx) return;
        var osc = ctx.createOscillator();
        var gain = ctx.createGain();
        var now = ctx.currentTime + (offset || 0);
        osc.type = wave || 'sine';
        osc.frequency.setValueAtTime(freq, now);
        gain.gain.setValueAtTime(0.0001, now);
        gain.gain.exponentialRampToValueAtTime(Math.max(0.001, vol || 0.08), now + 0.01);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + durMs / 1000);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(now);
        osc.stop(now + durMs / 1000 + 0.03);
      }

      function playSuccess() { playTone(880, 90, 0.06, 'triangle', 0); playTone(1174, 120, 0.05, 'triangle', 0.09); }
      function playFailure() { playTone(220, 130, 0.07, 'sawtooth', 0); playTone(180, 180, 0.06, 'square', 0.08); }

      // ── Queue persistence ────────────────────────────────────────────
      function serializeQueue() {
        try { window.localStorage.setItem(CACHE_KEY, JSON.stringify({ rowSequence: rowSeq, queue: queue })); } catch (e) { /* ignore */ }
      }

      function restoreQueue() {
        try {
          var parsed = safeParseJson(window.localStorage.getItem(CACHE_KEY) || '');
          var items = parsed && Array.isArray(parsed.queue) ? parsed.queue : [];
          queue.length = 0;
          items.forEach(function (item) {
            queue.push(Object.assign({ selected: false, confirmed: false, localValid: false, localMessage: '', saveFailed: false, saveFailedMessage: '', message: '' }, item || {}));
          });
          rowSeq = Math.max(1, Number(parsed && parsed.rowSequence || 1) || 1);
        } catch (e) { queue.length = 0; rowSeq = 1; }
      }

      // ── Scheduled flush ──────────────────────────────────────────────
      function scheduleRender() {
        if (mod._renderQueueTimer) return;
        mod._renderQueueTimer = window.setTimeout(function () {
          mod._renderQueueTimer = null;
          renderQueue();
        }, QUEUE_RENDER_BATCH_MS);
      }

      function schedulePersist() {
        if (mod._persistQueueTimer) window.clearTimeout(mod._persistQueueTimer);
        mod._persistQueueTimer = window.setTimeout(function () {
          mod._persistQueueTimer = null;
          serializeQueue();
        }, QUEUE_CACHE_BATCH_MS);
      }

      function flushQueueState() {
        if (mod._renderQueueTimer) { window.clearTimeout(mod._renderQueueTimer); mod._renderQueueTimer = null; }
        if (mod._persistQueueTimer) { window.clearTimeout(mod._persistQueueTimer); mod._persistQueueTimer = null; }
        serializeQueue();
        renderQueue();
      }

      // ── QR parsing ───────────────────────────────────────────────────
      function parseQr(raw) {
        var text = String(raw || '').trim();
        if (!text) return null;
        var candidates = [text];
        var qi = text.indexOf('?');
        if (qi >= 0 && qi < text.length - 1) candidates.push(text.substring(qi + 1));
        for (var i = 0; i < candidates.length; i++) {
          var c = candidates[i].trim();
          if (!c) continue;
          var params = null;
          try {
            if (/^https?:\/\//i.test(c)) params = new URL(c).searchParams;
            else params = new URLSearchParams(c.replace(/^[?#]/, ''));
          } catch (e) { params = null; }
          if (!params) continue;
          var tx  = String(params.get('tx') || params.get('transactionId') || '').trim();
          var eid = String(params.get('eventId') || '').trim();
          var pid = String(params.get('paymentId') || params.get('pid') || '').trim();
          var gid = String(params.get('guestId') || params.get('gid') || '').trim();
          var sig = String(params.get('sig') || params.get('signature') || '').trim();
          if (tx && eid && pid && sig) {
            return { transactionId: tx, eventId: eid, paymentId: pid, guestId: gid, signature: sig,
              duplicateKey: gid ? (tx + '::' + gid) : tx };
          }
        }
        return null;
      }

      // ── Badges / chips ───────────────────────────────────────────────
      function statusChip(item) {
        if (item.confirmed)  return '<span class="scn-badge confirmed">Saved</span>';
        if (item.saveFailed) return '<span class="scn-badge error">Retry</span>';
        if (item.localValid) return '<span class="scn-badge valid">Ready</span>';
        return '<span class="scn-badge error">Issue</span>';
      }

      function localCheckChip(item) {
        if (item.confirmed) return '<span class="scn-badge confirmed">Saved</span>';
        if (item.saveFailed && item.localValid) return '<span class="scn-badge pending">Retry</span>';
        if (item.localValid) return '<span class="scn-badge valid">Queued</span>';
        return '<span class="scn-badge error">Blocked</span>';
      }

      function bookingType(item) { return item.bookingType || (item.gateway === 'free' ? 'Free' : 'Paid'); }

      // ── Render ───────────────────────────────────────────────────────
      function getSelectedIds() {
        return Array.from(queueBody.querySelectorAll('input[data-row-select]:checked'))
          .map(function (el) { return String(el.getAttribute('data-row-select') || ''); });
      }

      function getItemById(id) {
        return queue.find(function (item) { return item.id === id; }) || null;
      }

      function syncSelectAll() {
        var sel = queue.filter(function (i) { return !i.confirmed; });
        var checked = sel.filter(function (i) { return !!i.selected; });
        selectAllEl.checked = !!sel.length && checked.length === sel.length;
        selectAllEl.indeterminate = checked.length > 0 && checked.length < sel.length;
      }

      function renderQueue() {
        if (!queue.length) {
          queueBody.innerHTML = '<tr class="scn-empty-row"><td colspan="10" class="scn-empty-state">No QR scans queued yet.</td></tr>';
          queuedCountEl.textContent = '0'; readyCountEl.textContent = '0';
          confirmedCountEl.textContent = '0'; issueCountEl.textContent = '0';
          if (mobileSaveInfo) mobileSaveInfo.textContent = 'No scans queued';
          syncSelectAll();
          return;
        }

        queueBody.innerHTML = queue.map(function (item) {
          var guestTitle = item.guestName || item.guestLabel || item.customerName || 'Ticket Holder';
          var ticketMeta = item.transactionId ? ('Tx: ' + item.transactionId) : 'QR not parsed yet';
          var eventMeta  = item.guestId ? ('Guest ID: ' + item.guestId) : (item.eventId ? ('Event: ' + item.eventId) : 'Awaiting save');
          var disabled   = item.confirmed ? 'disabled' : '';
          var admitDisabled = (item.confirmed || !item.localValid) ? 'disabled' : '';
          var admitVal   = Math.max(1, Number(item.admittedCount || 1) || 1);
          var remainingNames = Array.isArray(item.remainingAttendeeNames) ? item.remainingAttendeeNames : [];
          var selectedGuestNames = Array.isArray(item.selectedGuestNames) ? item.selectedGuestNames : [];
          var arrivedControl = '';
          if (remainingNames.length) {
            arrivedControl = [
              '<div class="scn-arrival-picker">',
              '<div class="scn-arrival-count">Selected ', esc(selectedGuestNames.length), ' of ', esc(remainingNames.length), ' remaining guest(s)</div>',
              '<div class="scn-name-list">',
              remainingNames.map(function (name, idx) {
                var choiceId = item.id + '-guest-' + idx;
                var checked = selectedGuestNames.some(function (selected) { return String(selected).toLowerCase() === String(name).toLowerCase(); });
                return '<label class="scn-name-option" for="' + esc(choiceId) + '">' +
                  '<input type="checkbox" id="' + esc(choiceId) + '" data-guest-name-toggle="' + esc(item.id) + '" value="' + esc(name) + '"' + (checked ? ' checked' : '') + (admitDisabled ? ' disabled' : '') + '>' +
                  '<span>' + esc(name) + '</span>' +
                '</label>';
              }).join(''),
              '</div>',
              '</div>'
            ].join('');
          } else {
            arrivedControl = '<input type="number" min="1" step="1" style="max-width:95px;" data-admit-count="' + esc(item.id) + '" value="' + esc(admitVal) + '"' + (admitDisabled ? ' disabled' : '') + '>';
          }
          return [
            '<tr>',
            '<td class="scn-no-label"><input type="checkbox" data-row-select="', esc(item.id), '"',
              item.selected ? ' checked' : '', disabled ? ' disabled' : '', '></td>',
            '<td class="scn-no-label">', statusChip(item), '</td>',
            '<td data-label="Guest"><div class="scn-guest-meta"><strong>', esc(guestTitle), '</strong><small>', esc(ticketMeta), '</small></div></td>',
            '<td data-label="Event"><div class="scn-guest-meta"><strong>', esc(item.eventTitle || item.eventId || '-'), '</strong><small>', esc(eventMeta), '</small><div class="scn-inline-meta">',
              (item.checkedInCount ? '<span class="scn-inline-pill">Used ' + esc(item.checkedInCount) + ' / ' + esc(item.qty || '-') + '</span>' : ''),
              (item.remainingEntries || item.remainingEntries === 0 ? '<span class="scn-inline-pill">Remaining ' + esc(item.remainingEntries) + '</span>' : ''),
            '</div></div></td>',
            '<td data-label="Type">', esc(bookingType(item)), '</td>',
            '<td data-label="Source">', esc(item.source || '-'), '</td>',
            '<td data-label="Arrived Now">', arrivedControl, '</td>',
            '<td data-label="Check">', localCheckChip(item), '</td>',
            '<td class="scn-message-cell" data-label="Message">', esc(item.message || '-'), '</td>',
            '<td data-label="Scanned"><div class="scn-guest-meta"><strong>', esc(item.scannedAt || '-'), '</strong><small>', esc(item.confirmedAt || ''), '</small></div></td>',
            '</tr>'
          ].join('');
        }).join('');

        var ready     = queue.filter(function (i) { return i.localValid && !i.confirmed; }).length;
        var confirmed = queue.filter(function (i) { return i.confirmed; }).length;
        var issues    = queue.filter(function (i) { return (!i.localValid || i.saveFailed) && !i.confirmed; }).length;
        queuedCountEl.textContent   = String(queue.length);
        readyCountEl.textContent    = String(ready);
        confirmedCountEl.textContent= String(confirmed);
        issueCountEl.textContent    = String(issues);
        if (mobileSaveInfo) {
          mobileSaveInfo.textContent = ready + ' ready · ' + confirmed + ' saved' + (issues ? ' · ' + issues + ' issue(s)' : '');
        }
        syncSelectAll();

        if (lastQueuedId) {
          var targetEl = queueBody.querySelector('input[data-row-select="' + lastQueuedId + '"]');
          var row = targetEl && targetEl.closest('tr');
          if (row) row.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
          lastQueuedId = '';
        }
      }

      // ── State recompute ──────────────────────────────────────────────
      function recompute(persist) {
        var seen = {};
        for (var i = queue.length - 1; i >= 0; i--) {
          var item = queue[i];
          if (item.confirmed) continue;
          item.localValid = false;
          item.admittedCount = Math.max(1, Math.floor(Number(item.admittedCount || 1) || 1));
          if (!item.transactionId || !item.eventId || !item.paymentId || !item.signature) {
            item.localMessage = 'QR format looks incomplete. Save will be blocked.';
            item.message = item.localMessage;
            continue;
          }
          if (item.duplicateKey && seen[item.duplicateKey]) {
            item.localMessage = 'Already scanned in this device queue.';
            item.message = item.localMessage;
            continue;
          }
          if (item.duplicateKey) seen[item.duplicateKey] = true;
          item.localValid = true;
          item.localMessage = 'Queued locally and ready to save.';
          if (item.saveFailed && item.saveFailedMessage) {
            item.message = item.saveFailedMessage;
          } else if (!item.confirmed) {
            item.message = item.localMessage;
          }
        }
        if (persist !== false) schedulePersist();
      }

      // ── Enqueue ──────────────────────────────────────────────────────
      function createItem(source, raw) {
        var parsed = parseQr(raw);
        return {
          id: 'scan-' + String(rowSeq++),
          source: source,
          rawScan: raw,
          scannedAt: nowLabel(),
          confirmed: false,
          selected: true,
          localValid: false,
          localMessage: '',
          saveFailed: false,
          saveFailedMessage: '',
          message: 'Stored locally. Not saved yet.',
          transactionId: parsed ? parsed.transactionId : '',
          eventId:       parsed ? parsed.eventId : '',
          paymentId:     parsed ? parsed.paymentId : '',
          guestId:       parsed ? parsed.guestId : '',
          signature:     parsed ? parsed.signature : '',
          duplicateKey:  parsed ? parsed.duplicateKey : '',
          admittedCount: 1,
          qty: 0,
          checkedInCount: 0,
          remainingEntries: 0,
          remainingAttendeeNames: [],
          selectedGuestNames: [],
          previewLoading: false,
          checkinHistory: []
        };
      }

      function updateItemPreview(item, payload) {
        item.previewLoading = false;
        item.eventTitle = payload && payload.eventTitle ? payload.eventTitle : item.eventTitle;
        item.customerName = payload && payload.customerName ? payload.customerName : item.customerName;
        item.bookingType = payload && payload.bookingType ? payload.bookingType : item.bookingType;
        item.qty = Number(payload && payload.qty || item.qty || 0) || 0;
        item.checkedInCount = Number(payload && payload.checkedInCount || 0) || 0;
        item.remainingEntries = Number(payload && payload.remainingEntries || 0);
        item.remainingAttendeeNames = Array.isArray(payload && payload.remainingAttendeeNames) ? payload.remainingAttendeeNames.slice() : [];
        item.checkinHistory = Array.isArray(payload && payload.checkinHistory) ? payload.checkinHistory.slice() : [];
        item.selectedGuestNames = Array.isArray(item.selectedGuestNames)
          ? item.selectedGuestNames.filter(function (name) {
              return item.remainingAttendeeNames.some(function (candidate) { return String(candidate).toLowerCase() === String(name).toLowerCase(); });
            })
          : [];
        if (item.remainingAttendeeNames.length && item.selectedGuestNames.length) {
          item.admittedCount = item.selectedGuestNames.length;
        }
        if (item.remainingEntries <= 0) {
          item.localValid = false;
          item.localMessage = 'All entries on this QR are already used. QR is closed.';
          item.message = item.localMessage;
          item.selected = false;
        } else if (item.remainingAttendeeNames.length && !item.selectedGuestNames.length) {
          item.localMessage = 'Select the guest names arriving now, then save this QR.';
          item.message = item.localMessage;
        } else if (payload && payload.message) {
          item.localMessage = payload.message;
          item.message = payload.message;
        }
      }

      function fetchPreviewForItem(item) {
        if (!item || !item.localValid || item.confirmed || !authReady || !item.rawScan) return Promise.resolve();
        item.previewLoading = true;
        item.message = 'Loading remaining guests...';
        scheduleRender();
        return authClient.apiPost({ action: 'admin_preview_event_qr', scanText: item.rawScan })
          .then(function (payload) {
            if (!payload || payload.ok !== true) {
              item.previewLoading = false;
              item.localValid = false;
              item.localMessage = payload && (payload.message || payload.error) ? String(payload.message || payload.error) : 'Unable to preview QR.';
              item.message = item.localMessage;
              return;
            }
            updateItemPreview(item, payload);
          })
          .catch(function (error) {
            item.previewLoading = false;
            item.message = error && error.message ? ('Preview failed: ' + error.message) : 'Preview failed.';
          })
          .finally(function () {
            schedulePersist();
            scheduleRender();
          });
      }

      function focusManual() {
        window.requestAnimationFrame(function () {
          if (manualInputEl && !mod._scannerRunning) { manualInputEl.focus(); manualInputEl.select(); }
        });
      }

      function enqueueScan(raw, source, opts) {
        opts = opts || {};
        var norm = String(raw || '').trim();
        if (!norm) { setQueueStatus('Nothing to add. Provide a QR value first.', false); if (!opts.silent) playFailure(); if (!opts.deferFocus) focusManual(); return; }
        var item = createItem(source, norm);
        queue.unshift(item);
        lastQueuedId = item.id;
        recompute(!opts.fastPath);
        if (opts.fastPath) { scheduleRender(); schedulePersist(); }
        else { renderQueue(); }
        if (!opts.silent) {
          if (item.localValid) { setQueueStatus('Scan cached locally. Continue scanning or save when ready.', true); playSuccess(); }
          else { setQueueStatus(item.message || 'Scan cached but blocked locally.', false); playFailure(); }
        }
        if (item.localValid) {
          fetchPreviewForItem(item);
        }
        if (!opts.deferFocus) focusManual();
      }

      function addBulkScans(lines, source) {
        var values = lines.map(function (l) { return String(l || '').trim(); }).filter(Boolean);
        if (!values.length) { setQueueStatus('Nothing to add. Provide at least one QR value.', false); playFailure(); focusManual(); return; }
        var seen = {};
        var unique = values.filter(function (v) { if (seen[v]) return false; seen[v] = true; return true; });
        for (var i = 0; i < unique.length; i++) enqueueScan(unique[i], source, { fastPath: true, silent: true, deferFocus: true });
        flushQueueState();
        setQueueStatus(String(unique.length) + ' scan(s) cached locally.', true);
        playSuccess();
        focusManual();
      }

      // ── Confirm batch ────────────────────────────────────────────────
      async function confirmItems(items) {
        var valid = items.filter(function (i) { return i && i.localValid && !i.confirmed; });
        if (!valid.length) { setQueueStatus('Select at least one locally valid QR before saving.', false); playFailure(); focusManual(); return; }
        var needsSelection = valid.find(function (item) {
          return Array.isArray(item.remainingAttendeeNames) && item.remainingAttendeeNames.length > 0 && (!Array.isArray(item.selectedGuestNames) || item.selectedGuestNames.length < 1);
        });
        if (needsSelection) {
          setQueueStatus('Select the arriving guest names for each queued QR before saving.', false);
          playFailure();
          focusManual();
          return;
        }
        setQueueStatus('Saving queued scans to the server...', true);
        try {
          var response = await authClient.apiPost({
            action: 'admin_batch_checkin_event_qr',
            scans: valid.map(function (i) {
              return {
                scanText: i.rawScan,
                admittedCount: Array.isArray(i.selectedGuestNames) && i.selectedGuestNames.length ? i.selectedGuestNames.length : Math.max(1, Math.floor(Number(i.admittedCount || 1) || 1)),
                selectedGuestNames: Array.isArray(i.selectedGuestNames) ? i.selectedGuestNames.slice() : []
              };
            })
          });
          (response.results || []).forEach(function (result, idx) {
            var item = valid[idx];
            if (!item) return;
            item.message         = result.message || (result.ok ? 'Saved successfully.' : 'Save failed.');
            item.confirmed       = !!result.ok;
            item.saveFailed      = !result.ok;
            item.saveFailedMessage = !result.ok ? item.message : '';
            item.eventTitle      = result.eventTitle  || item.eventTitle  || item.eventId || '';
            item.customerName    = result.customerName || item.customerName || '';
            item.guestName       = result.guestName    || item.guestName   || '';
            item.guestLabel      = result.guestLabel   || item.guestLabel  || '';
            item.bookingType     = result.bookingType  || item.bookingType || '';
            item.gateway         = result.gateway      || item.gateway     || '';
            item.confirmedAt     = result.checkedInAt ? ('Saved ' + new Date(result.checkedInAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })) : '';
            item.remainingEntries = Number(result.remainingEntries || 0);
            item.remainingAttendeeNames = Array.isArray(result.remainingAttendeeNames) ? result.remainingAttendeeNames.slice() : [];
            item.selectedGuestNames = [];
            if (result.ok && Number(result.remainingEntries || 0) > 0) {
              item.message = item.message + ' (' + Number(result.remainingEntries) + ' remaining)';
            }
            item.selected = false;
            item.localValid = !result.ok ? item.localValid : false;
          });
          var totals = response.totals || { success: 0, failed: 0 };
          recompute();
          if ((totals.failed || 0) > 0) playFailure(); else playSuccess();
          setQueueStatus('Batch completed. ' + String(totals.success || 0) + ' saved, ' + String(totals.failed || 0) + ' failed.', totals.failed === 0);
        } catch (err) {
          setQueueStatus(err && err.message ? err.message : 'Batch confirmation failed.', false);
          playFailure();
        }
        serializeQueue();
        renderQueue();
        focusManual();
      }

      function removeSelected() {
        var ids = getSelectedIds();
        if (!ids.length) { setQueueStatus('Select rows to remove.', false); playFailure(); focusManual(); return; }
        for (var i = queue.length - 1; i >= 0; i--) {
          if (ids.indexOf(queue[i].id) !== -1) queue.splice(i, 1);
        }
        recompute();
        setQueueStatus('Selected rows removed.', true);
        renderQueue();
        focusManual();
      }

      function clearQueue() {
        queue.length = 0;
        selectAllEl.checked = false;
        selectAllEl.indeterminate = false;
        serializeQueue();
        setQueueStatus('Queue cleared.', true);
        renderQueue();
        focusManual();
      }

      // ── Camera ───────────────────────────────────────────────────────
      function getScannerConfig() {
        var hw = Number(window.navigator.hardwareConcurrency || 4);
        var compact = window.innerWidth <= 480;
        var boxSize = compact ? 220 : (hw >= 8 ? 260 : 240);
        return { fps: hw >= 8 ? 15 : 12, qrbox: { width: boxSize, height: boxSize }, aspectRatio: 1.2, disableFlip: false };
      }

      async function ensureScannerLib() {
        if (window.Html5Qrcode && window.Html5Qrcode.getCameras) return true;
        var cdns = [
          'https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/minified/html5-qrcode.min.js',
          'https://unpkg.com/html5-qrcode'
        ];
        for (var i = 0; i < cdns.length; i++) {
          try {
            await NK.MODULE_BASE.loadCdnScript(cdns[i]);
            if (window.Html5Qrcode && window.Html5Qrcode.getCameras) return true;
          } catch (e) { /* try next */ }
        }
        return false;
      }

      async function loadCameras() {
        if (!window.Html5Qrcode || !window.Html5Qrcode.getCameras) {
          cameraSelectEl.innerHTML = '<option value="">Camera library unavailable</option>';
          startBtn.disabled = true;
          setScannerStatus('Camera scanning library could not be loaded. Manual mode is still available.', false);
          return;
        }
        try {
          var cameras = await window.Html5Qrcode.getCameras();
          if (!cameras.length) {
            cameraSelectEl.innerHTML = '<option value="">No cameras found</option>';
            startBtn.disabled = true;
            setScannerStatus('No camera devices found on this device.', false);
            return;
          }
          cameraSelectEl.innerHTML = cameras.map(function (cam, i) {
            return '<option value="' + esc(cam.id) + '">' + esc(cam.label || ('Camera ' + (i + 1))) + '</option>';
          }).join('');
          startBtn.disabled = false;
          setScannerStatus('Camera ready. Pick a device and start scanning.', true);
        } catch (err) {
          cameraSelectEl.innerHTML = '<option value="">Camera access failed</option>';
          startBtn.disabled = true;
          setScannerStatus(err && err.message ? err.message : 'Unable to load cameras.', false);
        }
      }

      async function startScanner() {
        if (mod._scannerRunning) return;
        var camId = String(cameraSelectEl.value || '').trim();
        if (!camId) { setScannerStatus('Choose a camera device first.', false); return; }
        if (!window.Html5Qrcode) { setScannerStatus('Scanner library unavailable.', false); playFailure(); return; }
        if (!mod._html5QrCode) mod._html5QrCode = new window.Html5Qrcode('scnReader');
        try {
          await mod._html5QrCode.start(camId, getScannerConfig(), function (decodedText) {
            var norm = String(decodedText || '').trim();
            var now = Date.now();
            if (!norm) return;
            if (norm === recentScanText && (now - recentScanTime) < SCAN_DEDUP_MS) return;
            recentScanText = norm;
            recentScanTime = now;
            setScannerStatus('QR detected. Caching scan locally...', true);
            enqueueScan(norm, 'camera', { fastPath: true, deferFocus: true });
          }, function () {});
          mod._scannerRunning = true;
          startBtn.disabled = true;
          stopBtn.disabled = false;
          cameraPlaceholder.style.display = 'none';
          setBadge(scannerBadge, 'Scanning', 'valid');
          setScannerStatus('Scanner active. Hold guest QR inside the frame.', true);
          ensureAudio();
        } catch (err) {
          setScannerStatus(err && err.message ? err.message : 'Unable to start scanner.', false);
          setBadge(scannerBadge, 'Error', 'error');
          playFailure();
        }
      }

      async function stopScanner() {
        if (!mod._html5QrCode || !mod._scannerRunning) return;
        try { await mod._html5QrCode.stop(); await mod._html5QrCode.clear(); } catch (e) { /* ignore */ }
        mod._scannerRunning = false;
        startBtn.disabled = false;
        stopBtn.disabled = true;
        cameraPlaceholder.style.display = 'flex';
        setBadge(scannerBadge, 'Idle', 'pending');
        setScannerStatus('Scanner stopped.', true);
        focusManual();
      }

      // ── Event wiring ─────────────────────────────────────────────────
      startBtn.addEventListener('click', function () { startScanner(); });
      stopBtn.addEventListener('click', function () { stopScanner(); });

      addManualBtn.addEventListener('click', function () {
        ensureAudio();
        var val = String(manualInputEl.value || '').trim();
        manualInputEl.value = '';
        enqueueScan(val, 'manual');
      });

      manualInputEl.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); addManualBtn.click(); }
      });

      addBulkBtn.addEventListener('click', function () {
        ensureAudio();
        var lines = String(bulkInputEl.value || '').split(/\r?\n/);
        bulkInputEl.value = '';
        addBulkScans(lines, 'bulk');
      });

      confirmSelBtn.addEventListener('click', function () {
        if (!authReady) { setQueueStatus('Staff session not ready. Please reopen this module.', false); playFailure(); return; }
        confirmItems(getSelectedIds().map(getItemById).filter(Boolean));
      });

      confirmAllBtn.addEventListener('click', function () {
        if (!authReady) { setQueueStatus('Staff session not ready. Please reopen this module.', false); playFailure(); return; }
        confirmItems(queue.filter(function (i) { return i.localValid && !i.confirmed; }));
      });

      removeSelBtn.addEventListener('click', function () { removeSelected(); });
      clearBtn.addEventListener('click', function () { clearQueue(); });

      selectAllEl.addEventListener('change', function () {
        var checked = !!selectAllEl.checked;
        queue.forEach(function (i) { if (!i.confirmed) i.selected = checked; });
        renderQueue();
      });

      queueBody.addEventListener('change', function (e) {
        var target = e.target;
        if (!target) return;
        if (target.matches('input[data-row-select]')) {
          var item = getItemById(String(target.getAttribute('data-row-select') || ''));
          if (item) { item.selected = !!target.checked; syncSelectAll(); }
          return;
        }
        if (target.matches('input[data-admit-count]')) {
          var item2 = getItemById(String(target.getAttribute('data-admit-count') || ''));
          if (!item2) return;
          item2.admittedCount = Math.max(1, Math.floor(Number(target.value || 1) || 1));
          target.value = String(item2.admittedCount);
          schedulePersist();
          return;
        }
        if (target.matches('input[data-guest-name-toggle]')) {
          var item3 = getItemById(String(target.getAttribute('data-guest-name-toggle') || ''));
          if (!item3) return;
          var selectedName = String(target.value || '').trim();
          var current = Array.isArray(item3.selectedGuestNames) ? item3.selectedGuestNames.slice() : [];
          if (target.checked) {
            if (!current.some(function (name) { return String(name).toLowerCase() === selectedName.toLowerCase(); })) {
              current.push(selectedName);
            }
          } else {
            current = current.filter(function (name) { return String(name).toLowerCase() !== selectedName.toLowerCase(); });
          }
          item3.selectedGuestNames = current;
          item3.admittedCount = current.length || 1;
          if (item3.remainingAttendeeNames.length) {
            item3.message = current.length
              ? ('Ready to admit ' + current.length + ' selected guest(s).')
              : 'Select the guest names arriving now, then save this QR.';
          }
          schedulePersist();
          scheduleRender();
        }
      });

      if (mobileSaveAllBtn) {
        mobileSaveAllBtn.addEventListener('click', function () {
          if (!authReady) { setQueueStatus('Staff session not ready.', false); playFailure(); return; }
          confirmItems(queue.filter(function (i) { return i.localValid && !i.confirmed; }));
        });
      }

      if (mobileSaveSelBtn) {
        mobileSaveSelBtn.addEventListener('click', function () {
          if (!authReady) { setQueueStatus('Staff session not ready.', false); playFailure(); return; }
          confirmItems(getSelectedIds().map(getItemById).filter(Boolean));
        });
      }

      // ── Bootstrap ────────────────────────────────────────────────────
      async function bootstrap() {
        setBadge(scannerBadge, 'Idle', 'pending');
        setBadge(authBadge, 'Checking', 'pending');
        restoreQueue();
        recompute(false);
        renderQueue();

        // Auth is guaranteed by the SPA portal, but confirm the session is live
        try {
          var username = (user && (user.username || user.displayName)) || 'staff';
          authReady = true;
          setBadge(authBadge, username, 'valid');
          setQueueStatus(queue.length ? 'Cached queue restored. Save when ready.' : 'Session ready. Start scanning or paste QR codes.', true);
          setScannerStatus('Preparing scanner library and camera...', true);
          await ensureScannerLib();
          await loadCameras();
        } catch (err) {
          authReady = false;
          setBadge(authBadge, 'Session required', 'error');
          setScannerStatus('Could not prepare scanner: ' + (err && err.message ? err.message : String(err)), false);
          setQueueStatus('Manual input is still available.', false);
          startBtn.disabled = true;
        }

        focusManual();
      }

      bootstrap();
    },

    destroy: function () {
      if (this._renderQueueTimer) { window.clearTimeout(this._renderQueueTimer); this._renderQueueTimer = null; }
      if (this._persistQueueTimer) { window.clearTimeout(this._persistQueueTimer); this._persistQueueTimer = null; }

      if (this._html5QrCode && this._scannerRunning) {
        try { this._html5QrCode.stop().then(function () { try { this._html5QrCode && this._html5QrCode.clear(); } catch (e) {} }.bind(this)); } catch (e) {}
      }
      this._html5QrCode = null;
      this._scannerRunning = false;

      if (this._audioContext) {
        try { this._audioContext.close(); } catch (e) {}
        this._audioContext = null;
      }

      this._container = null;
      this._authClient = null;
    }
  };
})(window.NK || (window.NK = {}));
