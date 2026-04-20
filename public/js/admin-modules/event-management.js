// admin-modules/event-management.js
// SPA Module: Event Management (prefix: evm)
(function (NK) {
  NK.MODULES = NK.MODULES || {};

  var SHARED_EVENTS_CACHE_KEY = 'nk_events_list_cache_v1';
  var SHARED_SELECTED_EVENT_KEY = 'nk_selected_event_v1';
  var DEFAULT_EVENT_VENUE = 'Venue: Rockmount Commercial Hub, 4th Floor, Khadakpada Circle, Kalyan West, Thane, Maharashtra 421301';

  NK.MODULES['event-management'] = {
    _container: null,
    _authClient: null,
    _phpApiUrl: null,
    _editEventId: null,
    _currentEvents: [],

    init: function (container, authClient) {
      this._container = container;
      this._authClient = authClient;
      this._phpApiUrl = (window.NK_DATA_API && window.NK_DATA_API.phpApiUrl) || '';
      this._editEventId = null;
      this._currentEvents = [];

      var module = this;
      container.innerHTML = module._buildHtml();
      module._injectStyles();
      module._bindEvents();
      module._loadEvents();
    },

    destroy: function () {
      this._container = null;
      this._authClient = null;
      this._phpApiUrl = null;
      this._editEventId = null;
      this._currentEvents = [];
    },

    // ── Helpers ──────────────────────────────────────────────────────────────

    _esc: function (v) {
      return NK.MODULE_BASE.escHtml(v);
    },

    _dotTimeToInput: function (v) {
      return String(v || '').replace('.', ':');
    },

    _inputTimeToDot: function (v) {
      return String(v || '').replace(':', '.');
    },

    _defaultVenue: function () {
      return DEFAULT_EVENT_VENUE;
    },

    _extractVenueFromDescription: function (description) {
      var lines = String(description || '').split(/\r?\n/);
      for (var index = 0; index < lines.length; index += 1) {
        var line = String(lines[index] || '').trim();
        if (/^venue\s*:/i.test(line)) return line;
      }
      return '';
    },

    _removeVenueFromDescription: function (description) {
      return String(description || '')
        .split(/\r?\n/)
        .filter(function (line) { return !/^venue\s*:/i.test(String(line || '').trim()); })
        .join('\n')
        .replace(/\n{3,}/g, '\n\n')
        .trim();
    },

    _composeDescriptionWithVenue: function (description, venue) {
      var body = this._removeVenueFromDescription(description);
      var safeVenue = String(venue || '').trim();
      if (!safeVenue) return body;
      return body ? (body + '\n' + safeVenue) : safeVenue;
    },

    _showForm: function (modeLabel) {
      var c = this._container;
      if (!c) return;
      var panel = c.querySelector('#evmEditorPanel');
      var createBtn = c.querySelector('#evmCreateBtn');
      var titleEl = c.querySelector('#evmFormTitle');
      var saveBtn = c.querySelector('#evmSaveBtn');
      if (panel) panel.style.display = '';
      if (createBtn) createBtn.style.display = 'none';
      if (titleEl) titleEl.textContent = modeLabel || 'Create Event';
      if (saveBtn) saveBtn.textContent = this._editEventId ? 'Save Changes' : 'Create Event';
    },

    _hideForm: function () {
      var c = this._container;
      if (!c) return;
      var panel = c.querySelector('#evmEditorPanel');
      var createBtn = c.querySelector('#evmCreateBtn');
      if (panel) panel.style.display = 'none';
      if (createBtn) createBtn.style.display = '';
    },

    _syncPaidControls: function () {
      var c = this._container;
      var typeEl = c.querySelector('#evmEventType');
      var payEl = c.querySelector('#evmPaymentEnabled');
      var priceEl = c.querySelector('#evmTicketPrice');
      if (!typeEl) return;
      if (typeEl.value !== 'paid') {
        if (payEl) payEl.checked = false;
        if (priceEl) priceEl.value = '0';
      }
    },

    _ordinalDay: function (day) {
      var mod100 = day % 100;
      if (mod100 >= 11 && mod100 <= 13) return day + 'th';
      switch (day % 10) {
        case 1: return day + 'st';
        case 2: return day + 'nd';
        case 3: return day + 'rd';
        default: return day + 'th';
      }
    },

    _deriveSubtitle: function (date, time, timeDisplayFormat) {
      var rawDate = String(date || '').trim();
      if (!rawDate) return '';

      var rawTime = String(time || '').trim();
      var normalizedTime = rawTime.length === 5 ? rawTime + ':00' : rawTime;
      var stamp = rawDate + 'T' + (normalizedTime || '00:00:00');
      var parsed = new Date(stamp);
      if (Number.isNaN(parsed.getTime())) return '';

      var month = parsed.toLocaleDateString('en-IN', { month: 'long', timeZone: 'Asia/Kolkata' });
      if (!rawTime) {
        return this._ordinalDay(parsed.getDate()) + ' ' + month;
      }

      var use24h = String(timeDisplayFormat || '12h').trim().toLowerCase() === '24h';
      var timeLabel = parsed.toLocaleTimeString('en-IN', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: !use24h,
        timeZone: 'Asia/Kolkata'
      }).replace(/\s+/g, ' ').trim();

      return this._ordinalDay(parsed.getDate()) + ' ' + month + ', ' + timeLabel + ' onwards';
    },

    _syncDerivedSubtitle: function () {
      var c = this._container;
      if (!c) return '';
      var subtitleEl = c.querySelector('#evmSubtitle');
      if (!subtitleEl) return '';
      var dateEl = c.querySelector('#evmStartDate');
      var timeEl = c.querySelector('#evmStartTime');
      var formatEl = c.querySelector('#evmTimeDisplayFormat');
      var subtitle = this._deriveSubtitle(
        dateEl ? dateEl.value : '',
        timeEl ? timeEl.value : '',
        formatEl ? formatEl.value : '12h'
      );
      subtitleEl.value = subtitle;
      return subtitle;
    },

    _clearForm: function () {
      var c = this._container;
      this._editEventId = null;

      var set = function (id, val) { var el = c.querySelector('#' + id); if (el) el.value = val; };
      var check = function (id, val) { var el = c.querySelector('#' + id); if (el) el.checked = val; };

      set('evmEventId', '');
      set('evmTitle', '');
      set('evmSubtitle', '');
      set('evmDescription', '');
      set('evmVenue', this._defaultVenue());
      set('evmImageUrl', '');
      set('evmVideoUrl', '');
      set('evmBadgeText', '');
      set('evmEventType', 'free');
      set('evmTicketPrice', '0');
      set('evmCurrency', 'INR');
      set('evmMaxTickets', '');
      set('evmPriority', '0');
      set('evmTimeDisplayFormat', '');
      set('evmStartDate', '');
      set('evmStartTime', '');
      set('evmEndDate', '');
      set('evmEndTime', '');
      set('evmCtaText', "I'm Interested");
      set('evmCtaUrl', '');
      set('evmPopupDelayHours', '0');
      set('evmPopupCooldownHours', '24');
      set('evmCancellationPolicyText', '');
      set('evmRefundPolicy', '');
      check('evmIsActive', true);
      check('evmPaymentEnabled', false);
      check('evmPopupEnabled', true);
      check('evmShowOncePerSession', false);
      check('evmShowVideo', false);
      this._syncDerivedSubtitle();

      var formTitle = c.querySelector('#evmFormTitle');
      if (formTitle) formTitle.textContent = 'Create Event';
      var saveBtn = c.querySelector('#evmSaveBtn');
      if (saveBtn) saveBtn.textContent = 'Create Event';
      var cancelBtn = c.querySelector('#evmCancelEditBtn');
      if (cancelBtn) cancelBtn.style.display = 'none';
      this._hideForm();
    },

    _fillFormFromEvent: function (item) {
      var c = this._container;
      this._editEventId = item.eventId || item.id || null;

      var set = function (id, val) { var el = c.querySelector('#' + id); if (el) el.value = (val == null ? '' : String(val)); };
      var check = function (id, val) { var el = c.querySelector('#' + id); if (el) el.checked = !!val; };

      set('evmEventId', item.eventId || item.id || '');
      set('evmTitle', item.title || '');
      set('evmSubtitle', item.subtitle || '');
      set('evmDescription', this._removeVenueFromDescription(item.description || ''));
      set('evmVenue', this._extractVenueFromDescription(item.description || '') || this._defaultVenue());
      set('evmImageUrl', item.imageUrl || '');
      set('evmVideoUrl', item.videoUrl || '');
      set('evmBadgeText', item.badgeText || '');
      set('evmEventType', item.eventType || 'free');
      set('evmTicketPrice', item.ticketPrice != null ? item.ticketPrice : '0');
      set('evmCurrency', item.currency || 'INR');
      set('evmMaxTickets', item.maxTickets != null ? item.maxTickets : '');
      set('evmPriority', item.priority != null ? item.priority : '0');
      set('evmTimeDisplayFormat', item.timeDisplayFormat || '');
      set('evmStartDate', item.startDate || '');
      set('evmStartTime', item.startTime || '');
      set('evmEndDate', item.endDate || '');
      set('evmEndTime', item.endTime || '');
      set('evmCtaText', item.ctaText || "I'm Interested");
      set('evmCtaUrl', item.ctaUrl || '');
      set('evmPopupDelayHours', item.popupDelayHours != null ? item.popupDelayHours : '0');
      set('evmPopupCooldownHours', item.popupCooldownHours != null ? item.popupCooldownHours : '24');
      set('evmCancellationPolicyText', item.cancellationPolicyText || '');
      set('evmRefundPolicy', item.refundPolicy || '');
      check('evmIsActive', item.isActive !== false);
      check('evmPaymentEnabled', !!item.paymentEnabled);
      check('evmPopupEnabled', item.popupEnabled !== false);
      check('evmShowOncePerSession', !!item.showOncePerSession);
      check('evmShowVideo', !!item.showVideo);
      this._syncDerivedSubtitle();

      var formTitle = c.querySelector('#evmFormTitle');
      if (formTitle) formTitle.textContent = 'Edit Event';
      var saveBtn = c.querySelector('#evmSaveBtn');
      if (saveBtn) saveBtn.textContent = 'Save Changes';
      var cancelBtn = c.querySelector('#evmCancelEditBtn');
      if (cancelBtn) cancelBtn.style.display = '';
      this._showForm('Edit Event');

      // Update image thumb if an imageUrl is set
      var thumb = c.querySelector('#evmImageThumb');
      if (thumb) {
        if (item.imageUrl) { thumb.src = item.imageUrl; thumb.style.display = 'block'; }
        else { thumb.src = ''; thumb.style.display = 'none'; }
      }

      this._updateCardPreview();
    },

    _collectPayload: function () {
      var c = this._container;
      var get = function (id) { var el = c.querySelector('#' + id); return el ? el.value : ''; };
      var chk = function (id) { var el = c.querySelector('#' + id); return el ? el.checked : false; };

      return {
        eventId: get('evmEventId') || undefined,
        title: get('evmTitle'),
        subtitle: this._syncDerivedSubtitle(),
        description: this._composeDescriptionWithVenue(get('evmDescription'), get('evmVenue')),
        venue: get('evmVenue'),
        imageUrl: get('evmImageUrl'),
        videoUrl: get('evmVideoUrl'),
        badgeText: get('evmBadgeText'),
        eventType: get('evmEventType'),
        ticketPrice: parseFloat(get('evmTicketPrice')) || 0,
        currency: get('evmCurrency') || 'INR',
        maxTickets: get('evmMaxTickets') ? parseInt(get('evmMaxTickets'), 10) : null,
        priority: parseInt(get('evmPriority'), 10) || 0,
        timeDisplayFormat: get('evmTimeDisplayFormat'),
        startDate: get('evmStartDate'),
        startTime: get('evmStartTime'),
        endDate: get('evmEndDate'),
        endTime: get('evmEndTime'),
        ctaText: get('evmCtaText'),
        ctaUrl: get('evmCtaUrl'),
        popupDelayHours: parseFloat(get('evmPopupDelayHours')) || 0,
        popupCooldownHours: parseFloat(get('evmPopupCooldownHours')) || 24,
        cancellationPolicyText: get('evmCancellationPolicyText'),
        refundPolicy: get('evmRefundPolicy'),
        isActive: chk('evmIsActive'),
        paymentEnabled: chk('evmPaymentEnabled'),
        popupEnabled: chk('evmPopupEnabled'),
        showOncePerSession: chk('evmShowOncePerSession'),
        showVideo: chk('evmShowVideo')
      };
    },

    _formatDateTime: function (date, time) {
      if (!date) return '';
      return date + (time ? ' ' + time : '');
    },

    _clearPublicEventCache: function () {
      try {
        sessionStorage.removeItem(SHARED_EVENTS_CACHE_KEY);
        sessionStorage.removeItem(SHARED_SELECTED_EVENT_KEY);
      } catch (err) {
        // Ignore storage access errors.
      }
    },

    _multipartPost: function (formData) {
      var module = this;
      var token = (module._authClient && module._authClient.getToken()) || '';
      formData.append('token', token);
      return fetch(module._phpApiUrl, { method: 'POST', body: formData })
        .then(function (r) {
          return r.json().then(function (payload) {
            if (!r.ok || !payload || payload.ok !== true) {
              throw new Error((payload && (payload.message || payload.error)) || ('HTTP ' + r.status));
            }
            return payload;
          });
        });
    },

    _deleteEvent: async function (eventId) {
      var module = this;
      if (!window.confirm('Delete this event? This cannot be undone.')) return;
      try {
        await module._authClient.apiPost({ action: 'admin_delete_event', eventId: eventId, force: false });
        module._clearPublicEventCache();
        await module._loadEvents();
      } catch (err) {
        var msg = err && err.message ? err.message : String(err);
        if (msg.indexOf('HAS_REGISTRATIONS') !== -1 || msg.indexOf('has registrations') !== -1) {
          if (!window.confirm('This event has registrations. Force-delete anyway? All registration data will be lost.')) return;
          try {
            await module._authClient.apiPost({ action: 'admin_delete_event', eventId: eventId, force: true });
            module._clearPublicEventCache();
            await module._loadEvents();
          } catch (err2) {
            window.alert('Delete failed: ' + (err2.message || err2));
          }
        } else {
          window.alert('Delete failed: ' + msg);
        }
      }
    },

    _cloneEvent: async function (eventId) {
      var module = this;
      var c = module._container;
      try {
        var result = await module._authClient.apiPost({ action: 'admin_clone_event', eventId: eventId });
        module._clearPublicEventCache();
        await module._loadEvents();
        // Open the cloned event in the form for editing
        if (result && result.newEventId) {
          var cloned = module._currentEvents.find(function (ev) {
            return String(ev.eventId || ev.id) === String(result.newEventId);
          });
          if (cloned) module._fillFormFromEvent(cloned);
        }
        var statusEl = c ? c.querySelector('#evmStatus') : null;
        if (statusEl) statusEl.textContent = (result && result.message) || 'Event cloned — edit and save to publish.';
      } catch (err) {
        window.alert('Clone failed: ' + (err.message || err));
      }
    },

    _uploadEventImage: function (file) {
      var module = this;
      var c = module._container;
      if (!file) return;
      var eventId = (c && c.querySelector('#evmEventId')) ? c.querySelector('#evmEventId').value.trim() : '';
      if (!eventId) {
        window.alert('Save the event first to get an Event ID before uploading an image.');
        return;
      }
      var fd = new FormData();
      fd.append('action', 'admin_event_image_upload');
      fd.append('eventId', eventId);
      fd.append('file', file);
      module._multipartPost(fd).then(function (res) {
        var urlInput = c.querySelector('#evmImageUrl');
        var thumb = c.querySelector('#evmImageThumb');
        if (urlInput && res.imageUrl) {
          urlInput.value = res.imageUrl;
          if (thumb) { thumb.src = res.imageUrl; thumb.style.display = 'block'; }
          module._updateCardPreview();
        }
      }).catch(function (err) {
        window.alert('Image upload failed: ' + (err.message || err));
      });
    },

    _updateCardPreview: function () {
      var module = this;
      var c = module._container;
      if (!c) return;
      var previewEl = c.querySelector('#evmCardPreview');
      if (!previewEl) return;
      var val = function (id) { var el = c.querySelector(id); return el ? (el.value || '') : ''; };
      var title = val('#evmTitle') || 'Event Title';
      var subtitle = module._syncDerivedSubtitle();
      var imageUrl = val('#evmImageUrl');
      var badge = val('#evmBadgeText');
      var startDate = val('#evmStartDate');
      var esc = module._esc.bind(module);
      previewEl.innerHTML = '<div class="evm-card-preview">'
        + (imageUrl ? '<img class="evm-card-img" src="' + esc(imageUrl) + '" alt="">' : '<div class="evm-card-img-placeholder">No image</div>')
        + '<div class="evm-card-body">'
        + (badge ? '<span class="evm-card-badge">' + esc(badge) + '</span>' : '')
        + '<div class="evm-card-title">' + esc(title) + '</div>'
        + (subtitle ? '<div class="evm-card-sub">' + esc(subtitle) + '</div>' : '')
        + (startDate ? '<div class="evm-card-date">' + esc(startDate) + '</div>' : '')
        + '</div></div>';
    },

    _formatEventCurrency: function (type, price, currency) {
      if (type !== 'paid') return 'Free';
      return (currency || 'INR') + ' ' + (parseFloat(price) || 0).toFixed(2);
    },

    // ── Data layer ────────────────────────────────────────────────────────────

    _loadEvents: async function () {
      var module = this;
      var c = module._container;
      if (!c) return;
      var statusEl = c.querySelector('#evmStatus');
      if (statusEl) statusEl.textContent = 'Loading events...';
      try {
        var payload = await module._authClient.apiGet('admin_list_events', {});
        module._currentEvents = Array.isArray(payload.items) ? payload.items : [];
        module._renderEventRows(module._currentEvents);
        if (statusEl) statusEl.textContent = 'Loaded ' + module._currentEvents.length + ' event(s).';
      } catch (err) {
        if (statusEl) statusEl.textContent = 'Failed to load events: ' + (err.message || err);
      }
    },

    _saveEvent: async function () {
      var module = this;
      var c = module._container;
      if (!c) return;
      var statusEl = c.querySelector('#evmStatus');
      var payload = module._collectPayload();

      if (!payload.title) {
        if (statusEl) statusEl.textContent = 'Title is required.';
        return;
      }
      if (!payload.startDate) {
        if (statusEl) statusEl.textContent = 'Start date is required.';
        return;
      }

      var saveBtn = c.querySelector('#evmSaveBtn');
      if (saveBtn) saveBtn.disabled = true;
      if (statusEl) statusEl.textContent = module._editEventId ? 'Saving event...' : 'Creating event...';

      try {
        var action = module._editEventId ? 'admin_update_event' : 'admin_create_event';
        await module._authClient.apiPost(Object.assign({ action: action }, payload));
        module._clearPublicEventCache();
        if (statusEl) statusEl.textContent = module._editEventId ? 'Event updated.' : 'Event created.';
        module._clearForm();
        await module._loadEvents();
      } catch (err) {
        if (statusEl) statusEl.textContent = 'Save failed: ' + (err.message || err);
      } finally {
        if (saveBtn) saveBtn.disabled = false;
      }
    },

    _toggleEvent: async function (eventId, currentActive) {
      var module = this;
      var c = module._container;
      if (!c) return;
      var statusEl = c.querySelector('#evmStatus');
      if (statusEl) statusEl.textContent = 'Updating event status...';
      try {
        await module._authClient.apiPost({ action: 'admin_toggle_event', eventId: eventId, isActive: !currentActive });
        module._clearPublicEventCache();
        if (statusEl) statusEl.textContent = 'Event status updated.';
        await module._loadEvents();
      } catch (err) {
        if (statusEl) statusEl.textContent = 'Toggle failed: ' + (err.message || err);
      }
    },

    // ── Rendering ─────────────────────────────────────────────────────────────

    _renderEventRows: function (items) {
      var module = this;
      var c = module._container;
      if (!c) return;
      var tbody = c.querySelector('#evmEventRows');
      if (!tbody) return;
      var esc = module._esc.bind(module);

      if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="evm-muted">No events found.</td></tr>';
        return;
      }

      tbody.innerHTML = items.map(function (item) {
        var id = item.eventId || item.id || '';
        var typePill = '<span class="evm-pill evm-' + esc(item.eventType || 'free') + '">' + esc(item.eventType || 'free') + '</span>';
        var statusPill = '<span class="evm-pill evm-' + (item.isActive ? 'active' : 'inactive') + '">' + (item.isActive ? 'Active' : 'Inactive') + '</span>';
        var price = item.eventType === 'paid' ? (esc((item.currency || 'INR') + ' ' + (parseFloat(item.ticketPrice) || 0).toFixed(2))) : 'Free';
        var start = esc(module._formatDateTime(item.startDate, item.startTime));
        var popup = item.popupEnabled ? 'Yes' : 'No';

        return '<tr>'
          + '<td><span class="evm-item-title">' + esc(item.title || '') + '</span>'
          + (item.subtitle ? '<div class="evm-muted">' + esc(item.subtitle) + '</div>' : '')
          + '</td>'
          + '<td>' + start + '</td>'
          + '<td>' + typePill + '</td>'
          + '<td>' + price + '</td>'
          + '<td>' + esc(popup) + '</td>'
          + '<td>' + statusPill + '</td>'
          + '<td>'
          + '<button class="evm-btn evm-btn-sm" data-action="edit" data-id="' + esc(String(id)) + '">Edit</button> '
          + '<button class="evm-btn evm-btn-sm evm-btn-sec" data-action="clone" data-id="' + esc(String(id)) + '">Clone</button> '
          + '<button class="evm-btn evm-btn-sm evm-btn-sec" data-action="toggle" data-id="' + esc(String(id)) + '" data-active="' + (item.isActive ? '1' : '0') + '">'
          + (item.isActive ? 'Deactivate' : 'Activate')
          + '</button> '
          + '<button class="evm-btn evm-btn-sm evm-btn-danger" data-action="delete" data-id="' + esc(String(id)) + '">Delete</button>'
          + '</td>'
          + '</tr>';
      }).join('');
    },

    // ── Events ────────────────────────────────────────────────────────────────

    _bindEvents: function () {
      var module = this;
      var c = module._container;

      // Table event delegation
      var tbody = c.querySelector('#evmEventRows');
      if (tbody) {
        tbody.addEventListener('click', function (e) {
          var btn = e.target.closest('[data-action]');
          if (!btn) return;
          var action = btn.getAttribute('data-action');
          var id = btn.getAttribute('data-id');
          if (action === 'edit') {
            var found = module._currentEvents.find(function (ev) {
              return String(ev.eventId || ev.id) === String(id);
            });
            if (found) module._fillFormFromEvent(found);
          } else if (action === 'toggle') {
            var active = btn.getAttribute('data-active') === '1';
            module._toggleEvent(id, active);
          } else if (action === 'clone') {
            module._cloneEvent(id);
          } else if (action === 'delete') {
            module._deleteEvent(id);
          }
        });
      }

      var createBtn = c.querySelector('#evmCreateBtn');
      if (createBtn) {
        createBtn.addEventListener('click', function () {
          module._clearForm();
          module._showForm('Create Event');
        });
      }

      // Form save
      var saveBtn = c.querySelector('#evmSaveBtn');
      if (saveBtn) saveBtn.addEventListener('click', function () { module._saveEvent(); });

      // Cancel edit
      var cancelBtn = c.querySelector('#evmCancelEditBtn');
      if (cancelBtn) cancelBtn.addEventListener('click', function () { module._clearForm(); });

      // Reload
      var reloadBtn = c.querySelector('#evmReloadBtn');
      if (reloadBtn) reloadBtn.addEventListener('click', function () { module._loadEvents(); });

      // Event type → sync paid controls
      var typeEl = c.querySelector('#evmEventType');
      if (typeEl) typeEl.addEventListener('change', function () { module._syncPaidControls(); });

      // Image upload button
      var imgUploadBtn = c.querySelector('#evmImageUploadBtn');
      if (imgUploadBtn) {
        imgUploadBtn.addEventListener('click', function () {
          var fi = c.querySelector('#evmImageFileInput');
          if (fi) fi.click();
        });
      }
      var imgFileInput = c.querySelector('#evmImageFileInput');
      if (imgFileInput) {
        imgFileInput.addEventListener('change', function () {
          if (imgFileInput.files && imgFileInput.files.length) {
            module._uploadEventImage(imgFileInput.files[0]);
          }
        });
      }

      // Live card preview — update on any form field change
      var previewFields = ['#evmTitle','#evmImageUrl','#evmBadgeText','#evmStartDate','#evmStartTime','#evmTimeDisplayFormat'];
      previewFields.forEach(function (sel) {
        var el = c.querySelector(sel);
        if (el) el.addEventListener('input', function () { module._updateCardPreview(); });
        if (el) el.addEventListener('change', function () { module._updateCardPreview(); });
      });
    },

    // ── HTML ──────────────────────────────────────────────────────────────────

    _buildHtml: function () {
      return [
        '<div class="evm-wrap">',
        '<div class="evm-header-row">',
        '  <h2 class="evm-heading">Event Management</h2>',
        '  <div class="evm-header-actions">',
        '    <button class="evm-btn" id="evmCreateBtn">Create Event</button>',
        '    <button class="evm-btn evm-btn-sec" id="evmReloadBtn">Reload</button>',
        '  </div>',
        '</div>',
        '<div id="evmStatus" class="evm-status evm-muted">Loading...</div>',

        // ── Form ──
        '<section class="evm-panel" id="evmEditorPanel" style="display:none">',
        '  <h3 class="evm-subheading" id="evmFormTitle">Create Event</h3>',
        '  <div class="evm-form-grid">',
        '    <label class="evm-label">Event ID (auto)<input id="evmEventId" class="evm-input evm-input-readonly" readonly disabled placeholder="(assigned on save)"></label>',
        '    <label class="evm-label">Title *<input id="evmTitle" class="evm-input" placeholder="Event title"></label>',
        '    <label class="evm-label">Schedule Summary (auto)<input id="evmSubtitle" class="evm-input evm-input-readonly" placeholder="Generated from start date/time" readonly></label>',
        '    <label class="evm-label">Venue<input id="evmVenue" class="evm-input" value="' + this._esc(this._defaultVenue()) + '" placeholder="Venue address"></label>',
        '    <label class="evm-label">Badge Text<input id="evmBadgeText" class="evm-input" placeholder="e.g. New"></label>',
        '    <label class="evm-label">Event Type',
        '      <select id="evmEventType" class="evm-input">',
        '        <option value="free">Free</option>',
        '        <option value="paid">Paid</option>',
        '      </select>',
        '    </label>',
        '    <label class="evm-label">Ticket Price<input id="evmTicketPrice" class="evm-input" type="number" min="0" step="0.01" value="0"></label>',
        '    <label class="evm-label">Currency<input id="evmCurrency" class="evm-input" value="INR"></label>',
        '    <label class="evm-label">Max Tickets<input id="evmMaxTickets" class="evm-input" type="number" min="0" placeholder="Unlimited"></label>',
        '    <label class="evm-label">Priority<input id="evmPriority" class="evm-input" type="number" min="0" value="0"></label>',
        '    <label class="evm-label">Time Display Format',
        '      <select id="evmTimeDisplayFormat" class="evm-input">',
        '        <option value="12h" selected>12h</option>',
        '        <option value="24h">24h</option>',
        '      </select>',
        '    </label>',
        '    <label class="evm-label">Start Date *<input id="evmStartDate" class="evm-input" type="date"></label>',
        '    <label class="evm-label">Start Time<input id="evmStartTime" class="evm-input" type="time"></label>',
        '    <label class="evm-label">End Date<input id="evmEndDate" class="evm-input" type="date"></label>',
        '    <label class="evm-label">End Time<input id="evmEndTime" class="evm-input" type="time"></label>',
        '    <label class="evm-label">CTA Text<input id="evmCtaText" class="evm-input" value="I\'m Interested"></label>',
        '    <label class="evm-label">CTA URL<input id="evmCtaUrl" class="evm-input" type="url" placeholder="https://..."></label>',
        '    <label class="evm-label">Popup Delay (hrs)<input id="evmPopupDelayHours" class="evm-input" type="number" min="0" step="0.5" value="0"></label>',
        '    <label class="evm-label">Popup Cooldown (hrs)<input id="evmPopupCooldownHours" class="evm-input" type="number" min="0" step="1" value="24"></label>',
        '  </div>',
        '  <label class="evm-label evm-full">Description<textarea id="evmDescription" class="evm-input evm-textarea" rows="3" placeholder="Event description only. Date/time summary is generated automatically."></textarea></label>',
        '  <label class="evm-label evm-full">Image URL',
        '    <div class="evm-image-row">',
        '      <input id="evmImageUrl" class="evm-input" type="url" placeholder="https://...">',
        '      <button class="evm-btn evm-btn-sec evm-btn-sm" type="button" id="evmImageUploadBtn">Upload</button>',
        '    </div>',
        '    <input id="evmImageFileInput" type="file" accept="image/*" style="display:none">',
        '    <img id="evmImageThumb" src="" alt="preview" class="evm-image-thumb" style="display:none">',
        '  </label>',
        '  <label class="evm-label evm-full">Video URL<input id="evmVideoUrl" class="evm-input" type="url" placeholder="https://..."></label>',
        '  <label class="evm-label evm-full">Cancellation Policy<textarea id="evmCancellationPolicyText" class="evm-input evm-textarea" rows="2"></textarea></label>',
        '  <label class="evm-label evm-full">Refund Policy<input id="evmRefundPolicy" class="evm-input" placeholder="e.g. No Refunds"></label>',
        '  <div class="evm-checks-row">',
        '    <label class="evm-check-label"><input type="checkbox" id="evmIsActive" checked> Active</label>',
        '    <label class="evm-check-label"><input type="checkbox" id="evmPaymentEnabled"> Payment Enabled</label>',
        '    <label class="evm-check-label"><input type="checkbox" id="evmPopupEnabled" checked> Popup Enabled</label>',
        '    <label class="evm-check-label"><input type="checkbox" id="evmShowOncePerSession"> Show Once Per Session</label>',
        '    <label class="evm-check-label"><input type="checkbox" id="evmShowVideo"> Show Video</label>',
        '  </div>',
        '  <div class="evm-form-actions">',
        '    <button class="evm-btn" id="evmSaveBtn">Create Event</button>',
        '    <button class="evm-btn evm-btn-sec" id="evmCancelEditBtn">Cancel</button>',
        '  </div>',
        '  <div id="evmCardPreview" class="evm-card-preview-wrap"></div>',
        '</section>',

        // ── Table ──
        '<section class="evm-panel">',
        '  <h3 class="evm-subheading">Events</h3>',
        '  <div class="evm-table-wrap">',
        '    <table class="evm-table">',
        '      <thead><tr>',
        '        <th>Event</th><th>Start</th><th>Type</th><th>Price</th><th>Popup</th><th>Status</th><th>Actions</th>',
        '      </tr></thead>',
        '      <tbody id="evmEventRows"><tr><td colspan="7" class="evm-muted">Loading...</td></tr></tbody>',
        '    </table>',
        '  </div>',
        '</section>',
        '</div>'
      ].join('\n');
    },

    _injectStyles: function () {
      var id = 'evm-styles';
      if (document.getElementById(id)) return;
      var style = document.createElement('style');
      style.id = id;
      style.textContent = [
        '.evm-wrap { padding: 16px; }',
        '.evm-header-row { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }',
        '.evm-header-actions { display:flex; gap:8px; }',
        '.evm-heading { font-size:1.25rem; font-weight:700; margin:0; }',
        '.evm-subheading { font-size:1rem; font-weight:700; margin:0 0 12px; }',
        '.evm-status { font-size:0.85rem; margin-bottom:10px; padding:6px 10px; border-radius:8px; background:rgba(0,0,0,0.04); }',
        '.evm-muted { color:#888; }',
        '.evm-panel { background:#fff; border:1px solid rgba(0,0,0,0.1); border-radius:12px; padding:16px; margin-bottom:16px; }',
        '.evm-form-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:10px; margin-bottom:10px; }',
        '.evm-label { display:flex; flex-direction:column; font-size:0.78rem; font-weight:600; color:#555; gap:4px; }',
        '.evm-full { display:flex; flex-direction:column; font-size:0.78rem; font-weight:600; color:#555; gap:4px; margin-bottom:10px; }',
        '.evm-input { padding:7px 10px; border:1px solid rgba(123,94,67,0.24); border-radius:8px; font-size:0.84rem; outline:none; width:100%; box-sizing:border-box; }',
        '.evm-input:focus { border-color:rgba(148,89,43,0.5); box-shadow:0 0 0 2px rgba(182,123,69,0.18); }',
        '.evm-input-readonly { background:#f5f1ea; color:#7d6d5d; cursor:not-allowed; }',
        '.evm-input:disabled { opacity:1; }',
        '.evm-textarea { resize:vertical; min-height:56px; }',
        '.evm-checks-row { display:flex; flex-wrap:wrap; gap:14px; margin:10px 0; }',
        '.evm-check-label { display:flex; align-items:center; gap:6px; font-size:0.85rem; cursor:pointer; }',
        '.evm-form-actions { display:flex; gap:8px; margin-top:12px; }',
        '.evm-btn { padding:7px 16px; border-radius:8px; border:none; background:#214038; color:#fff; font-size:0.84rem; font-weight:600; cursor:pointer; }',
        '.evm-btn:disabled { opacity:0.5; cursor:default; }',
        '.evm-btn-sec { background:transparent; border:1px solid rgba(0,0,0,0.18); color:#214038; }',
        '.evm-btn-sm { padding:4px 10px; font-size:0.78rem; }',
        '.evm-btn-danger { background:#c0392b; color:#fff; }',
        '.evm-btn-danger:hover { background:#a93226; }',
        '.evm-image-row { display:flex; gap:8px; align-items:center; }',
        '.evm-image-row .evm-input { flex:1; }',
        '.evm-image-thumb { margin-top:6px; width:100%; max-width:200px; height:auto; border-radius:8px; object-fit:cover; border:1px solid rgba(0,0,0,0.12); }',
        '.evm-card-preview-wrap { margin-top:14px; }',
        '.evm-card-preview { display:flex; gap:12px; background:#faf5ed; border:1px solid rgba(182,123,69,0.25); border-radius:12px; overflow:hidden; max-width:340px; }',
        '.evm-card-img { width:110px; min-height:90px; object-fit:cover; flex-shrink:0; }',
        '.evm-card-img-placeholder { width:110px; min-height:90px; background:#e8ddd0; display:flex; align-items:center; justify-content:center; font-size:0.72rem; color:#aaa; flex-shrink:0; }',
        '.evm-card-body { padding:10px 10px 10px 0; display:flex; flex-direction:column; gap:4px; }',
        '.evm-card-badge { background:#214038; color:#fff; border-radius:999px; font-size:0.66rem; font-weight:700; padding:2px 8px; align-self:flex-start; }',
        '.evm-card-title { font-weight:700; font-size:0.9rem; color:#222; }',
        '.evm-card-sub { font-size:0.78rem; color:#666; }',
        '.evm-card-date { font-size:0.72rem; color:#999; margin-top:2px; }',
        '.evm-table-wrap { overflow-x:auto; border-radius:10px; border:1px solid rgba(0,0,0,0.1); }',
        '.evm-table { width:100%; border-collapse:collapse; font-size:0.84rem; }',
        '.evm-table thead th { background:#f5efe2; padding:8px 10px; text-align:left; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; color:#888; border-bottom:1px solid rgba(0,0,0,0.1); white-space:nowrap; }',
        '.evm-table tbody td { padding:8px 10px; border-bottom:1px solid rgba(0,0,0,0.07); vertical-align:middle; }',
        '.evm-table tbody tr:last-child td { border-bottom:none; }',
        '.evm-table tbody tr:hover td { background:rgba(182,123,69,0.06); }',
        '.evm-item-title { font-weight:600; }',
        '.evm-pill { display:inline-flex; padding:2px 10px; border-radius:999px; font-size:0.72rem; font-weight:700; border:1px solid transparent; }',
        '.evm-pill.evm-paid { background:rgba(33,64,56,0.12); color:#214038; border-color:rgba(33,64,56,0.2); }',
        '.evm-pill.evm-free { background:rgba(182,123,69,0.12); color:#7a4a1a; border-color:rgba(182,123,69,0.2); }',
        '.evm-pill.evm-active { background:rgba(56,142,60,0.12); color:#2e7d32; border-color:rgba(56,142,60,0.25); }',
        '.evm-pill.evm-inactive { background:rgba(164,83,72,0.12); color:#7a342b; border-color:rgba(164,83,72,0.2); }',
        '@media(max-width:700px){.evm-header-row{align-items:flex-start; gap:8px; flex-direction:column;} .evm-header-actions{width:100%;} .evm-header-actions .evm-btn{flex:1;} .evm-form-grid{grid-template-columns:1fr;} .evm-wrap{padding:8px;}}'
      ].join('\n');
      document.head.appendChild(style);
    }
  };
})(window.NK || (window.NK = {}));
