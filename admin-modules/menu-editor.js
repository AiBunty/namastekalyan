// admin-modules/menu-editor.js
// SPA Module: Menu Price Editor (prefix: mne)
(function (NK) {
  NK.MODULES = NK.MODULES || {};

  NK.MODULES['menu-editor'] = {
    _container: null,
    _authClient: null,
    _phpApiUrl: null,

    // State
    _currentSheet: 'AWGNK MENU',
    _headers: [],
    _editableColumns: {},       // normalizeKey(header) → type string
    _baseRows: [],              // [{rowNumber, id, cells{header:value}}]
    _changedCells: {},          // "rowNumber|header" → new string value
    _selectedRows: {},          // rowNumber string → bool
    _locked: false,
    _pendingImageItemId: null,

    init: function (container, authClient) {
      this._container = container;
      this._authClient = authClient;
      this._phpApiUrl = (window.NK_DATA_API && window.NK_DATA_API.phpApiUrl) || '';
      this._currentSheet = 'AWGNK MENU';
      this._headers = [];
      this._editableColumns = {};
      this._baseRows = [];
      this._changedCells = {};
      this._selectedRows = {};
      this._locked = false;
      this._pendingImageItemId = null;

      var module = this;
      container.innerHTML = module._buildHtml();
      module._injectStyles();
      module._bindEvents();
      module._loadSheet();
    },

    destroy: function () {
      this._container = null;
      this._authClient = null;
      this._phpApiUrl = null;
      this._headers = [];
      this._editableColumns = {};
      this._baseRows = [];
      this._changedCells = {};
      this._selectedRows = {};
      this._locked = false;
      this._pendingImageItemId = null;
    },

    // ── Key/normalization helpers ─────────────────────────────────────────────

    _normalizeKey: function (v) {
      return String(v).trim().toLowerCase().replace(/\s+/g, ' ');
    },

    _cellKey: function (rowNumber, header) {
      return String(rowNumber) + '|' + header;
    },

    _parseCellKey: function (key) {
      var idx = key.indexOf('|');
      return { rowNumber: Number(key.slice(0, idx)), header: key.slice(idx + 1) };
    },

    _toLookupKeys: function (header) {
      var raw = String(header);
      var lower = raw.toLowerCase();
      var compact = lower.replace(/\s+/g, '');
      var camel = compact.replace(/([-_][a-z])/g, function (m) { return m[1].toUpperCase(); });
      var aliases = {
        'itemname': ['name', 'item'],
        'availability': ['available', 'isavailable', 'visible'],
        'baseprice': ['price', 'cost'],
        'ischefspecial': ['chefspecial', 'chefs special', 'chef\'s special'],
        'isjain': ['jain'],
        'spicelevel': ['spice'],
        'servingunit': ['unit', 'pcs', 'unit (pcs)'],
        'foodcategory': ['category', 'foodcat'],
        'subcategory': ['subcat'],
        'imageurl': ['image', 'img', 'photo']
      };
      var extra = aliases[compact] || [];
      return [raw, lower, compact, camel].concat(extra);
    },

    _headerToPayloadKey: function (header) {
      var k = this._normalizeKey(header);
      var map = {
        'item name': 'itemName',
        'price': 'basePrice',
        'availability': 'availability',
        'jain': 'isJain',
        'chef special': 'isChefSpecial',
        "chef's special": 'isChefSpecial',
        'chef\'s special': 'isChefSpecial',
        'spice level': 'spiceLevel',
        'unit (pcs)': 'servingUnit',
        'food category': 'foodCategory',
        'sub category': 'subCategory',
        'image url': 'imageUrl'
      };
      return map[k] || k.replace(/\s+(\w)/g, function (_, c) { return c.toUpperCase(); });
    },

    // ── Value helpers ─────────────────────────────────────────────────────────

    _getBaseValue: function (rowNumber, header) {
      var row = this._baseRows.find(function (r) { return r.rowNumber === rowNumber; });
      return row ? (row.cells[header] !== undefined ? row.cells[header] : '') : '';
    },

    _getCurrentValue: function (rowNumber, header) {
      var k = this._cellKey(rowNumber, header);
      return this._changedCells.hasOwnProperty(k) ? this._changedCells[k] : this._getBaseValue(rowNumber, header);
    },

    _isVisibilityOn: function (v) {
      if (v === null || v === undefined) return true;
      var s = String(v).trim().toLowerCase();
      return !(['no', 'hidden', 'inactive', 'off', '0', 'false'].indexOf(s) !== -1);
    },

    _visibilityText: function (v) { return this._isVisibilityOn(v) ? 'Yes' : 'No'; },

    _chefSpecialText: function (v) {
      if (v === null || v === undefined || String(v).trim() === '') return 'No';
      var s = String(v).trim().toLowerCase();
      return ['yes', 'true', '1', 'on'].indexOf(s) !== -1 ? 'Yes' : 'No';
    },

    _spiceLevelText: function (v) {
      if (!v && v !== 0) return '';
      var s = String(v).trim();
      var map = { '1': 'Mild', '2': 'Medium', '3': 'Spicy', '4': 'Hot', 'mild': 'Mild', 'medium': 'Medium', 'spicy': 'Spicy', 'hot': 'Hot' };
      return map[s.toLowerCase()] || s;
    },

    _buildSpiceOptionsHtml: function (value) {
      var module = this;
      var current = module._spiceLevelText(value);
      return ['', 'Mild', 'Medium', 'Spicy', 'Hot'].map(function (opt) {
        return '<option value="' + opt + '"' + (current === opt ? ' selected' : '') + '>' + (opt || '-- None --') + '</option>';
      }).join('');
    },

    // ── Data normalization ────────────────────────────────────────────────────

    _normalizeEditorRows: function (rawRows) {
      var headers = this._headers;
      return rawRows.map(function (row, i) {
        var cells = {};
        headers.forEach(function (header) {
          var keys = [header, header.toLowerCase(), header.toLowerCase().replace(/\s+/g, ''),
            header.toLowerCase().replace(/\s+/g, '_'), 'availability' === header.toLowerCase() ? 'isAvailable' : ''];
          var val = undefined;
          for (var ki = 0; ki < keys.length; ki++) {
            if (keys[ki] && row[keys[ki]] !== undefined) {
              val = row[keys[ki]];
              break;
            }
          }
          if (val === undefined) {
            // Try camelCase of header
            var cc = header.trim().replace(/(?:^\w|\s+\w)/g, function (m, off) {
              return off === 0 ? m.trim().toLowerCase() : m.trim().toUpperCase();
            });
            val = row[cc];
          }
          var headerNorm = header.toLowerCase();
          if (headerNorm === 'availability') {
            val = val === undefined ? 'Yes' : (String(val).trim().toLowerCase() === 'no' ||
              String(val).trim().toLowerCase() === 'false' || String(val).trim() === '0' ? 'No' : 'Yes');
          }
          cells[header] = val !== undefined ? String(val) : '';
        });
        return { rowNumber: i + 2, id: Number(row.id || row.rowId || 0), cells: cells };
      });
    },

    // ── Payload + summary ─────────────────────────────────────────────────────

    _buildUpdatesPayload: function () {
      var module = this;
      var perRow = {};
      Object.keys(module._changedCells).forEach(function (k) {
        var p = module._parseCellKey(k);
        var rowNumber = p.rowNumber;
        var header = p.header;
        if (!perRow[rowNumber]) {
          // Include the row's DB id so PHP can find it without rowNumber lookup
          var row = module._baseRows.find(function (r) { return r.rowNumber === rowNumber; });
          perRow[rowNumber] = { rowNumber: rowNumber, id: row ? Number(row.id) : 0, cells: {} };
        }
        perRow[rowNumber].cells[header] = module._changedCells[k];
      });
      return Object.values(perRow);
    },

    _getDirtySummary: function () {
      var celleCount = Object.keys(this._changedCells).length;
      var rowSet = {};
      Object.keys(this._changedCells).forEach(function (k) {
        rowSet[k.split('|')[0]] = true;
      });
      return { changedCells: celleCount, changedRows: Object.keys(rowSet).length };
    },

    _getSelectedRowNumbers: function () {
      return Object.keys(this._selectedRows)
        .filter(function (k) { return true; })
        .map(Number).sort(function (a, b) { return a - b; });
    },

    _getSelectedRowIds: function () {
      var module = this;
      return module._getSelectedRowNumbers().map(function (rn) {
        var row = module._baseRows.find(function (r) { return r.rowNumber === rn; });
        return row ? Number(row.id) : null;
      }).filter(function (id) { return id !== null && id > 0; });
    },

    // ── UI state ──────────────────────────────────────────────────────────────

    _updateChips: function () {
      var c = this._container;
      if (!c) return;
      var dirty = this._getDirtySummary();
      var selectedCount = Object.keys(this._selectedRows).length;

      this._setChip('#mneChangedRowsChip', dirty.changedRows);
      this._setChip('#mneChangedCellsChip', dirty.changedCells);
      this._setChip('#mneSelectedRowsChip', selectedCount);
    },

    _setChip: function (selector, count) {
      var c = this._container;
      if (!c) return;
      var el = c.querySelector(selector);
      if (!el) return;
      el.textContent = el.getAttribute('data-label') + ': ' + count;
      if (count > 0) el.classList.add('warn');
      else el.classList.remove('warn');
    },

    _updateToolbarState: function () {
      var module = this;
      var c = module._container;
      if (!c) return;
      var dirty = module._getDirtySummary();
      var hasSelected = Object.keys(module._selectedRows).length > 0;
      var hasDirty = dirty.changedCells > 0;
      var locked = module._locked;

      var set = function (id, disabled) {
        var el = c.querySelector(id);
        if (el) el.disabled = !!disabled;
      };

      set('#mneAddRowBtn', locked);
      set('#mneDeleteRowsBtn', locked || !hasSelected);
      set('#mneSetVisibleBtn', locked || !hasSelected);
      set('#mneSetHiddenBtn', locked || !hasSelected);
      set('#mneDiscardBtn', locked || !hasDirty);
      set('#mneSaveBtn', locked || !hasDirty);
    },

    _dietPillsHtml: function (primaryDiet, computedDiets) {
      var diets = Array.isArray(computedDiets) ? computedDiets : [];
      var primary = String(primaryDiet || '').toLowerCase();
      var pills = [
        { key: 'veg',       label: 'V', title: 'Veg',       color: '#22843b' },
        { key: 'jain',      label: 'J', title: 'Jain',      color: '#1565c0' },
        { key: 'nonveg',    label: 'N', title: 'Non-Veg',   color: '#b71c1c' },
        { key: 'universal', label: 'U', title: 'Universal', color: '#6700a8' },
      ];
      return pills.map(function (p) {
        var active = diets.indexOf(p.key) !== -1 || primary === p.key ||
                     (primary === 'mixed' && (p.key === 'veg' || p.key === 'nonveg'));
        var style = active
          ? 'background:' + p.color + ';color:#fff;border-color:' + p.color + ';'
          : 'background:transparent;color:#bbb;border-color:#ddd;';
        return '<span class="diet-pill" title="' + p.title + '" style="' + style + '">' + p.label + '</span>';
      }).join('');
    },

    // ── Rendering ─────────────────────────────────────────────────────────────

    _renderGrid: function () {
      var module = this;
      var c = module._container;
      if (!c) return;
      var gridHeadEl = c.querySelector('#mneGridHead');
      var gridBodyEl = c.querySelector('#mneGridBody');
      if (!gridHeadEl || !gridBodyEl) return;

      var headers = module._headers;
      var rows = module._baseRows;

      // Head — add Diet pill column before the data headers, and Image column at end
      gridHeadEl.innerHTML = '<tr>'
        + '<th style="width:32px"><input type="checkbox" id="mneSelectAllRows"></th>'
        + '<th style="width:84px;white-space:nowrap">Diet</th>'
        + headers.map(function (h) {
            return '<th class="mne-th-sortable" data-header="' + NK.MODULE_BASE.escHtml(h) + '" title="Double-click to rename">' + NK.MODULE_BASE.escHtml(h) + '</th>';
          }).join('')
        + '<th style="width:40px;text-align:center">Img</th>'
        + '</tr>';

      // Body
      if (!rows.length) {
        gridBodyEl.innerHTML = '<tr><td colspan="' + (headers.length + 3) + '" style="text-align:center;padding:16px;color:#888">No rows loaded.</td></tr>';
        return;
      }

      // Group rows by category for visual separation
      var lastCat = null;
      var bodyHtml = '';
      rows.forEach(function (row) {
        var catCell = row.cells ? row.cells['Category'] : '';
        var cat = String(catCell || '').trim();
        if (cat !== lastCat) {
          lastCat = cat;
          bodyHtml += '<tr class="mne-cat-row"><td colspan="' + (headers.length + 3) + '">'
            + NK.MODULE_BASE.escHtml(cat || '(No Category)') + '</td></tr>';
        }

        var selected = !!module._selectedRows[String(row.rowNumber)];
        var dirty = headers.some(function (h) { return module._changedCells.hasOwnProperty(module._cellKey(row.rowNumber, h)); });
        var rowClass = (selected ? 'row-selected' : '') + (dirty ? ' row-dirty' : '');

        // Diet pills from cells
        var primaryDiet = row.cells ? (row.cells['Primary Diet'] || '') : '';
        var dietFlags = row.cells ? (row.cells['Diet Flags'] || '') : '';
        var computedDiets = dietFlags ? dietFlags.split(',').map(function (s) { return s.trim().toLowerCase(); }) : [];
        var isVegCell     = row.cells ? String(row.cells['Is Veg']      || '').toLowerCase() : '';
        var isNonvegCell  = row.cells ? String(row.cells['Is Nonveg']   || '').toLowerCase() : '';
        var isUnivCell    = row.cells ? String(row.cells['Is Universal'] || '').toLowerCase() : '';
        if (isVegCell === 'yes' || isVegCell === '1') { if (computedDiets.indexOf('veg') === -1) computedDiets.push('veg'); }
        if (isNonvegCell === 'yes' || isNonvegCell === '1') { if (computedDiets.indexOf('nonveg') === -1) computedDiets.push('nonveg'); }
        if (isUnivCell === 'yes' || isUnivCell === '1') { if (computedDiets.indexOf('universal') === -1) computedDiets.push('universal'); }

        bodyHtml += '<tr class="' + rowClass.trim() + '" data-row-number="' + row.rowNumber + '">'
          + '<td style="text-align:center"><input type="checkbox" class="row-select" data-row-number="' + row.rowNumber + '"' + (selected ? ' checked' : '') + '></td>'
          + '<td class="mne-cell mne-diet-cell">' + module._dietPillsHtml(primaryDiet, computedDiets) + '</td>'
          + headers.map(function (header) {
            var editType = module._editableColumns[module._normalizeKey(header)];
            var currentVal = module._getCurrentValue(row.rowNumber, header);
            var baseVal = module._getBaseValue(row.rowNumber, header);
            var cellDirty = module._changedCells.hasOwnProperty(module._cellKey(row.rowNumber, header));
            var cellClass = 'mne-cell' + (cellDirty ? ' cell-dirty' : '') + (editType ? '' : ' cell-readonly');
            if (!editType) {
              return '<td class="' + cellClass + '"><span>' + NK.MODULE_BASE.escHtml(currentVal) + '</span></td>';
            }

            var cellHtml = '';
            if (editType === 'visibility') {
              var isOn = module._isVisibilityOn(currentVal);
              cellHtml = '<select class="grid-visibility" data-row-number="' + row.rowNumber + '" data-header="' + NK.MODULE_BASE.escHtml(header) + '">'
                + '<option value="Yes"' + (isOn ? ' selected' : '') + '>Visible</option>'
                + '<option value="No"' + (!isOn ? ' selected' : '') + '>Hidden</option>'
                + '</select>';
            } else if (editType === 'chef') {
              var chefVal = module._chefSpecialText(currentVal);
              cellHtml = '<select class="grid-select" data-edit-type="chef" data-row-number="' + row.rowNumber + '" data-header="' + NK.MODULE_BASE.escHtml(header) + '">'
                + '<option value="Yes"' + (chefVal === 'Yes' ? ' selected' : '') + '>Yes</option>'
                + '<option value="No"' + (chefVal !== 'Yes' ? ' selected' : '') + '>No</option>'
                + '</select>';
            } else if (editType === 'spice') {
              cellHtml = '<select class="grid-select" data-edit-type="spice" data-row-number="' + row.rowNumber + '" data-header="' + NK.MODULE_BASE.escHtml(header) + '">'
                + module._buildSpiceOptionsHtml(currentVal) + '</select>';
            } else if (editType === 'diet') {
              var dietOptions = ['', 'veg', 'nonveg', 'jain', 'mixed', 'universal', 'bar'];
              cellHtml = '<select class="grid-select" data-edit-type="diet" data-row-number="' + row.rowNumber + '" data-header="' + NK.MODULE_BASE.escHtml(header) + '">'
                + dietOptions.map(function (opt) {
                  return '<option value="' + opt + '"' + (currentVal === opt ? ' selected' : '') + '>' + (opt || '-- Auto --') + '</option>';
                }).join('')
                + '</select>';
            } else if (editType === 'vegflag' || editType === 'nonvegflag' || editType === 'universalflag') {
              var flagVal = String(currentVal).toLowerCase();
              var isOn = flagVal === 'yes' || flagVal === '1' || flagVal === 'true';
              cellHtml = '<select class="grid-select" data-edit-type="' + NK.MODULE_BASE.escHtml(editType) + '" data-row-number="' + row.rowNumber + '" data-header="' + NK.MODULE_BASE.escHtml(header) + '">'
                + '<option value="Yes"' + (isOn ? ' selected' : '') + '>Yes</option>'
                + '<option value="No"' + (!isOn ? ' selected' : '') + '>No</option>'
                + '</select>';
            } else {
              cellHtml = '<input class="grid-input" type="text" value="' + NK.MODULE_BASE.escHtml(currentVal) + '" data-row-number="' + row.rowNumber + '" data-header="' + NK.MODULE_BASE.escHtml(header) + '" data-base="' + NK.MODULE_BASE.escHtml(baseVal) + '">';
            }
            return '<td class="' + cellClass + '">' + cellHtml + '</td>';
          }).join('')
          + '<td style="text-align:center">'
          + '<button class="mne-btn mne-btn-sec mne-btn-img" data-action="upload-img" data-item-id="' + row.id + '" title="Upload image for this item" style="padding:2px 7px;font-size:0.72rem;">\uD83D\uDDBC</button>'
          + '</td>'
          + '</tr>';
      });
      gridBodyEl.innerHTML = bodyHtml;

      module._updateChips();
      module._updateToolbarState();
    },

    // ── API Calls ─────────────────────────────────────────────────────────────

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

    _addColumn: async function () {
      var module = this;
      var name = window.prompt('New column name (e.g. "Portion Size"):');
      if (!name || !name.trim()) return;
      try {
        await module._authClient.apiPost({
          action: 'admin_menu_editor_add_column',
          sheetType: module._currentSheet,
          columnName: name.trim()
        });
        await module._loadSheet();
        module._setStatus('Column "' + name.trim() + '" added.');
      } catch (err) {
        window.alert('Add column failed: ' + (err.message || err));
      }
    },

    _renameColumn: async function (oldName) {
      var module = this;
      if (!oldName) return;
      var newName = window.prompt('Rename column "' + oldName + '" to:', oldName);
      if (!newName || !newName.trim() || newName.trim() === oldName) return;
      try {
        await module._authClient.apiPost({
          action: 'admin_menu_editor_rename_column',
          sheetType: module._currentSheet,
          oldName: oldName,
          newName: newName.trim()
        });
        await module._loadSheet();
        module._setStatus('Column renamed to "' + newName.trim() + '".');
      } catch (err) {
        window.alert('Rename failed: ' + (err.message || err));
      }
    },

    _uploadItemImage: function (itemId, file) {
      var module = this;
      if (!itemId || !file) return;
      // Determine sheetType from currentSheet name
      var sheetType = module._currentSheet.toLowerCase().indexOf('bar') !== -1 ? 'bar' : 'food';
      var fd = new FormData();
      fd.append('action', 'admin_menu_item_upload_image');
      fd.append('sheetType', sheetType);
      fd.append('itemId', String(itemId));
      fd.append('file', file);
      module._setStatus('Uploading image for item #' + itemId + '…');
      module._multipartPost(fd).then(function (res) {
        module._setStatus('Image uploaded: ' + (res.imageUrl || ''));
        // Reload to reflect updated image URL cell
        module._loadSheet();
      }).catch(function (err) {
        window.alert('Image upload failed: ' + (err.message || err));
        module._setStatus('Image upload failed.');
      });
    },

    _loadSheet: async function () {
      var module = this;
      var c = module._container;
      if (!c) return;
      module._locked = true;
      module._updateToolbarState();
      module._setStatus('Loading sheet...');
      try {
        var data = await module._authClient.apiPost({
          action: 'admin_menu_editor_load',
          sheetName: module._currentSheet
        });
        module._headers = Array.isArray(data.headers) ? data.headers : [];
        // editableMeta keyed by header display name → type, or use editableColumns array
        module._editableColumns = {};
        if (data.editableMeta && typeof data.editableMeta === 'object') {
          var meta = data.editableMeta;
          Object.keys(meta).forEach(function (key) {
            module._editableColumns[module._normalizeKey(key)] = meta[key];
          });
        } else if (Array.isArray(data.editableColumns)) {
          data.editableColumns.forEach(function (col) {
            if (col.header && col.type) {
              module._editableColumns[module._normalizeKey(col.header)] = col.type;
            }
          });
        }
        module._baseRows = Array.isArray(data.items) ? data.items : [];
        module._changedCells = {};
        module._selectedRows = {};
        module._renderGrid();
        module._setStatus('Loaded ' + module._baseRows.length + ' rows.');
      } catch (err) {
        module._setStatus('Load failed: ' + (err.message || err));
      } finally {
        module._locked = false;
        module._updateToolbarState();
      }
    },

    _addRow: async function () {
      var module = this;
      var c = module._container;
      if (!c) return;
      module._setStatus('Adding row...');
      try {
        await module._authClient.apiPost({ action: 'admin_menu_editor_add_row', sheetName: module._currentSheet });
        await module._loadSheet();
        module._setStatus('Row added.');
      } catch (err) {
        module._setStatus('Add row failed: ' + (err.message || err));
      }
    },

    _deleteSelectedRows: async function () {
      var module = this;
      var c = module._container;
      if (!c) return;
      var rowNumbers = module._getSelectedRowNumbers();
      if (!rowNumbers.length) {
        module._setStatus('No rows selected.');
        return;
      }
      var answer = window.prompt(
        'You are about to delete ' + rowNumbers.length + ' row(s). This cannot be undone.\nType DELETE to confirm.'
      );
      if (answer !== 'DELETE') {
        module._setStatus('Delete cancelled.');
        return;
      }
      module._setStatus('Deleting...');
      try {
        var ids = module._getSelectedRowIds();
        await module._authClient.apiPost({
          action: 'admin_menu_editor_delete_rows',
          sheetName: module._currentSheet,
          ids: ids
        });
        await module._loadSheet();
        module._setStatus('Deleted ' + rowNumbers.length + ' row(s).');
      } catch (err) {
        module._setStatus('Delete failed: ' + (err.message || err));
      }
    },

    _setVisibility: async function (isAvailable) {
      var module = this;
      var c = module._container;
      if (!c) return;
      var rowNumbers = module._getSelectedRowNumbers();
      if (!rowNumbers.length) {
        module._setStatus('No rows selected.');
        return;
      }
      module._setStatus((isAvailable ? 'Showing' : 'Hiding') + ' ' + rowNumbers.length + ' row(s)...');
      try {
        var visIds = module._getSelectedRowIds();
        await module._authClient.apiPost({
          action: 'admin_menu_editor_set_visibility',
          sheetName: module._currentSheet,
          ids: visIds,
          isAvailable: isAvailable
        });
        await module._loadSheet();
        module._setStatus('Visibility updated.');
      } catch (err) {
        module._setStatus('Visibility update failed: ' + (err.message || err));
      }
    },

    _saveChanges: async function () {
      var module = this;
      var c = module._container;
      if (!c) return;
      var changes = module._buildUpdatesPayload();
      if (!changes.length) {
        module._setStatus('Nothing to save.');
        return;
      }
      module._locked = true;
      module._updateToolbarState();
      module._setStatus('Saving ' + changes.length + ' row(s)...');
      try {
        var result = await module._authClient.apiPost({
          action: 'admin_menu_editor_save_changes',
          sheetName: module._currentSheet,
          updates: changes
        });
        await module._loadSheet();
        var msg = 'Saved ' + (result.updatedCount || 0) + ' row(s).';
        if (result.skipped && result.skipped.length) msg += ' Skipped: ' + result.skipped.length;
        module._setStatus(msg);
      } catch (err) {
        module._setStatus('Save failed: ' + (err.message || err));
        module._locked = false;
        module._updateToolbarState();
      }
    },

    _discardChanges: function () {
      this._changedCells = {};
      this._renderGrid();
      this._setStatus('Changes discarded.');
    },

    // ── Events ────────────────────────────────────────────────────────────────

    _bindEvents: function () {
      var module = this;
      var c = module._container;

      // Sheet selector
      var sheetSel = c.querySelector('#mneSheetSelect');
      if (sheetSel) sheetSel.addEventListener('change', function () {
        var dirty = module._getDirtySummary();
        if (dirty.changedCells > 0) {
          if (!window.confirm('You have unsaved changes. Continue and discard them?')) {
            sheetSel.value = module._currentSheet;
            return;
          }
        }
        module._currentSheet = sheetSel.value;
        module._loadSheet();
      });

      // Toolbar buttons
      var bind = function (id, fn) {
        var el = c.querySelector(id);
        if (el) el.addEventListener('click', fn);
      };
      bind('#mneReloadBtn', function () { module._loadSheet(); });
      bind('#mneAddRowBtn', function () { module._addRow(); });
      bind('#mneDeleteRowsBtn', function () { module._deleteSelectedRows(); });
      bind('#mneSetVisibleBtn', function () { module._setVisibility(true); });
      bind('#mneSetHiddenBtn', function () { module._setVisibility(false); });
      bind('#mneAddColumnBtn', function () { module._addColumn(); });
      bind('#mneDiscardBtn', function () { module._discardChanges(); });
      bind('#mneSaveBtn', function () { module._saveChanges(); });

      // Column rename — double-click on sortable th
      var gridHeadEl = c.querySelector('#mneGridHead');
      if (gridHeadEl) {
        gridHeadEl.addEventListener('dblclick', function (e) {
          var th = e.target.closest('.mne-th-sortable');
          if (!th) return;
          var headerName = th.getAttribute('data-header');
          if (headerName) module._renameColumn(headerName);
        });
      }

      // Grid head — select all
      if (gridHeadEl) gridHeadEl.addEventListener('change', function (e) {
        if (e.target.id === 'mneSelectAllRows') {
          if (e.target.checked) {
            module._baseRows.forEach(function (r) { module._selectedRows[String(r.rowNumber)] = true; });
          } else {
            module._selectedRows = {};
          }
          module._renderGrid();
        }
      });

      // Grid body — row select + cell edits
      var gridBodyEl = c.querySelector('#mneGridBody');
      if (gridBodyEl) gridBodyEl.addEventListener('change', function (e) {
        var t = e.target;

        if (t.classList.contains('row-select')) {
          var rn = t.getAttribute('data-row-number');
          if (t.checked) module._selectedRows[rn] = true;
          else delete module._selectedRows[rn];
          // Update row class
          var tr = t.closest('tr');
          if (tr) {
            tr.classList.toggle('row-selected', t.checked);
          }
          module._updateChips();
          module._updateToolbarState();
          return;
        }

        if (t.classList.contains('grid-input') || t.classList.contains('grid-visibility') || t.classList.contains('grid-select')) {
          var rowNumber = Number(t.getAttribute('data-row-number'));
          var header = t.getAttribute('data-header');
          var k = module._cellKey(rowNumber, header);
          var newVal = t.value;
          var baseVal = module._getBaseValue(rowNumber, header);

          if (newVal === baseVal) {
            delete module._changedCells[k];
          } else {
            module._changedCells[k] = newVal;
          }
          // Update cell appearance in-place instead of full re-render
          var cell = t.closest('td');
          if (cell) {
            cell.classList.toggle('cell-dirty', module._changedCells.hasOwnProperty(k));
          }
          var tr2 = t.closest('tr');
          if (tr2) {
            var rowDirty = module._headers.some(function (h) {
              return module._changedCells.hasOwnProperty(module._cellKey(rowNumber, h));
            });
            tr2.classList.toggle('row-dirty', rowDirty);
          }
          module._updateChips();
          module._updateToolbarState();
        }
      });

      // Grid body — image upload button (click delegation)
      if (gridBodyEl) gridBodyEl.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-action="upload-img"]');
        if (!btn) return;
        var itemId = btn.getAttribute('data-item-id');
        if (!itemId || itemId === '0') {
          window.alert('This item must be saved first before uploading an image.');
          return;
        }
        module._pendingImageItemId = Number(itemId);
        var fi = c.querySelector('#mneItemImageInput');
        if (fi) fi.click();
      });

      // Hidden file input for item images
      var imgInput = c.querySelector('#mneItemImageInput');
      if (imgInput) {
        imgInput.addEventListener('change', function () {
          if (imgInput.files && imgInput.files.length && module._pendingImageItemId) {
            module._uploadItemImage(module._pendingImageItemId, imgInput.files[0]);
            imgInput.value = '';
            module._pendingImageItemId = null;
          }
        });
      }
    },

    // ── Misc helpers ──────────────────────────────────────────────────────────

    _setStatus: function (msg) {
      var c = this._container;
      if (!c) return;
      var el = c.querySelector('#mneStatus');
      if (el) el.textContent = msg;
    },

    // ── HTML ──────────────────────────────────────────────────────────────────

    _buildHtml: function () {
      return [
        '<div class="mne-wrap">',

        // Toolbar
        '<div class="mne-toolbar">',
        '  <div class="mne-toolbar-left">',
        '    <select id="mneSheetSelect" class="mne-toolbar-select">',
        '      <option value="AWGNK MENU">AWGNK MENU</option>',
        '      <option value="BAR MENU NK">BAR MENU NK</option>',
        '    </select>',
        '    <button class="mne-btn mne-btn-sec" id="mneReloadBtn">Reload</button>',
        '    <span class="meta-chip" id="mneChangedRowsChip" data-label="Changed Rows">Changed Rows: 0</span>',
        '    <span class="meta-chip" id="mneChangedCellsChip" data-label="Changed Cells">Changed Cells: 0</span>',
        '    <span class="meta-chip" id="mneSelectedRowsChip" data-label="Selected">Selected: 0</span>',
        '  </div>',
        '  <div class="mne-toolbar-right">',
        '    <button class="mne-btn mne-btn-sec" id="mneAddRowBtn">+ Add Row</button>',
        '    <button class="mne-btn mne-btn-sec" id="mneDeleteRowsBtn" disabled>Delete</button>',
        '    <button class="mne-btn mne-btn-sec" id="mneSetVisibleBtn" disabled>Set Visible</button>',
        '    <button class="mne-btn mne-btn-sec" id="mneSetHiddenBtn" disabled>Set Hidden</button>',
        '    <button class="mne-btn mne-btn-sec" id="mneAddColumnBtn">+ Column</button>',
        '    <button class="mne-btn mne-btn-sec" id="mneDiscardBtn" disabled>Discard</button>',
        '    <button class="mne-btn" id="mneSaveBtn" disabled>Save</button>',
        '  </div>',
        '</div>',

        '<input type="file" id="mneItemImageInput" accept="image/*" style="display:none">',

        '<div id="mneStatus" class="mne-status mne-muted">Loading...</div>',

        // Grid
        '<div class="mne-table-wrap">',
        '  <table class="mne-grid">',
        '    <thead id="mneGridHead"></thead>',
        '    <tbody id="mneGridBody"></tbody>',
        '  </table>',
        '</div>',

        '</div>'
      ].join('\n');
    },

    _injectStyles: function () {
      var id = 'mne-styles';
      if (document.getElementById(id)) return;
      var style = document.createElement('style');
      style.id = id;
      style.textContent = [
        '.mne-wrap { padding:16px; display:flex; flex-direction:column; height:100%; }',
        '.mne-toolbar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:6px; margin-bottom:8px; }',
        '.mne-toolbar-left,.mne-toolbar-right { display:flex; align-items:center; flex-wrap:wrap; gap:6px; }',
        '.mne-toolbar-select { padding:6px 10px; border-radius:8px; border:1px solid rgba(0,0,0,0.18); font-size:0.84rem; background:#fff; }',
        '.mne-status { font-size:0.82rem; color:#888; margin-bottom:6px; padding:4px 6px; }',
        '.meta-chip { display:inline-flex; padding:3px 10px; border-radius:999px; font-size:0.75rem; font-weight:600; background:rgba(0,0,0,0.06); border:1px solid transparent; }',
        '.meta-chip.warn { background:rgba(182,123,69,0.18); color:#7a4720; border-color:rgba(182,123,69,0.3); }',
        '.mne-table-wrap { flex:1; overflow:auto; border:1px solid rgba(0,0,0,0.12); border-radius:10px; }',
        '.mne-grid { width:100%; border-collapse:collapse; font-size:0.81rem; }',
        '.mne-grid thead { position:sticky; top:0; z-index:2; }',
        '.mne-grid thead th { background:#f5efe2; padding:7px 8px; text-align:left; font-size:0.72rem; text-transform:uppercase; letter-spacing:0.04em; color:#888; border-bottom:2px solid rgba(0,0,0,0.12); white-space:nowrap; }',
        '.mne-grid tbody td { padding:5px 8px; border-bottom:1px solid rgba(0,0,0,0.07); vertical-align:middle; }',
        '.mne-grid tbody tr:last-child td { border-bottom:none; }',
        '.mne-grid tbody tr:hover td { background:rgba(182,123,69,0.05); }',
        '.mne-grid tbody tr.row-selected td { background:rgba(33,64,56,0.06); }',
        '.mne-grid tbody tr.row-dirty td { border-left:2px solid #c8842a; }',
        '.mne-cell.cell-dirty { background:rgba(200,132,42,0.12) !important; }',
        '.mne-cell.cell-readonly span { color:#555; }',
        '.grid-input,.grid-visibility,.grid-select { padding:4px 6px; border:1px solid rgba(0,0,0,0.18); border-radius:6px; font-size:0.81rem; width:100%; min-width:80px; box-sizing:border-box; }',
        '.grid-input:focus,.grid-visibility:focus,.grid-select:focus { border-color:rgba(148,89,43,0.5); outline:none; }',
        '.mne-btn { padding:6px 13px; border-radius:8px; border:none; background:#214038; color:#fff; font-size:0.83rem; font-weight:600; cursor:pointer; }',
        '.mne-btn:disabled { opacity:0.45; cursor:default; }',
        '.mne-btn-sec { background:transparent; border:1px solid rgba(0,0,0,0.18); color:#214038; }',
        '.mne-muted { color:#888; }',
        '.mne-cat-row td { background:#f5efe2; font-weight:700; font-size:0.75rem; letter-spacing:0.05em; text-transform:uppercase; padding:5px 10px; border-bottom:2px solid rgba(0,0,0,0.1); color:#6b4226; }',
        '.mne-diet-cell { white-space:nowrap; }',
        '.diet-pill { display:inline-flex; align-items:center; justify-content:center; width:18px; height:18px; border-radius:50%; font-size:0.6rem; font-weight:700; border:1px solid; margin-right:2px; cursor:default; }',
        '.diet-pill.active { opacity:1; }',
        '.diet-pill.inactive { opacity:0.2; }',
        '@media(max-width:700px){.mne-toolbar{flex-direction:column;align-items:flex-start;} .mne-wrap{padding:8px;}}'
      ].join('\n');
      document.head.appendChild(style);
    }
  };
})(window.NK || (window.NK = {}));
