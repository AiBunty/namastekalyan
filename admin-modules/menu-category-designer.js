// admin-modules/menu-category-designer.js
// SPA Module: Menu Category Designer (prefix: mnc)
(function (NK) {
  NK.MODULES = NK.MODULES || {};

  NK.MODULES['menu-category-designer'] = {
    _container: null,
    _authClient: null,
    _catSortable: null,
    _itemSortable: null,

    // State
    _state: null,

    _initialState: function () {
      return {
        user: null,
        sheetName: 'AWGNK MENU',
        categories: [],
        selectedCategory: '',
        itemOrderDirty: false,
        categoryOrderDirty: false
      };
    },

    init: function (container, authClient, user) {
      this._container = container;
      this._authClient = authClient;
      this._state = this._initialState();
      this._state.user = user;

      var module = this;

      container.innerHTML = module._buildHtml();
      module._injectStyles();
      module._setupGlobal();
      module._bindEvents();
      module._ensureSortable(function () { module._loadDesigner(); });
    },

    destroy: function () {
      if (this._catSortable) { this._catSortable.destroy(); this._catSortable = null; }
      if (this._itemSortable) { this._itemSortable.destroy(); this._itemSortable = null; }
      this._container = null;
      this._authClient = null;
      this._state = null;
      window.NKCategoryDesigner = null;
    },

    // ── Global onclick interface ──────────────────────────────────────────────

    _setupGlobal: function () {
      var module = this;
      window.NKCategoryDesigner = {
        selectCategory: function (encodedName) { module._selectCategory(encodedName); },
        moveCategory: function (index, delta) { module._moveCategory(index, delta); },
        moveItem: function (index, delta) { module._moveItem(index, delta); },
        toggleCategory: function (index) { module._toggleCategory(index); },
        toggleItem: function (index) { module._toggleItem(index); }
      };
    },

    // ── Data ──────────────────────────────────────────────────────────────────

    _loadDesigner: async function () {
      var module = this;
      var c = module._container;
      if (!c) return;
      module._setStatus('Loading...');
      try {
        var data = await module._authClient.apiPost({
          action: 'admin_menu_designer_load',
          sheetName: module._state.sheetName
        });
        var raw = Array.isArray(data.categories) ? data.categories : [];
        module._state.categories = raw.map(function (cat) {
          return {
            name: cat.name || '',
            isAvailable: cat.isAvailable !== false,
            items: Array.isArray(cat.items) ? cat.items.map(function (item) {
              return {
                id: item.id || 0,
                itemName: item.itemName || item.name || '',
                isAvailable: item.isAvailable !== false,
                basePrice: item.basePrice !== undefined ? item.basePrice : null
              };
            }) : []
          };
        });
        module._state.selectedCategory = '';
        module._state.itemOrderDirty = false;
        module._state.categoryOrderDirty = false;
        module._renderCategories();
        module._renderItems();
        module._updateCounters();
        module._setStatus('Loaded ' + module._state.categories.length + ' categories.');
      } catch (err) {
        module._setStatus('Load failed: ' + (err.message || err));
      }
    },

    _saveCategoryOrder: async function () {
      var module = this;
      var c = module._container;
      if (!c) return;
      module._setStatus('Saving category order...');
      var saveBtn = c.querySelector('#mncSaveCategoryBtn');
      if (saveBtn) saveBtn.disabled = true;
      try {
        await module._authClient.apiPost({
          action: 'admin_menu_designer_save_category_order',
          sheetName: module._state.sheetName,
          categories: module._state.categories.map(function (cat) { return cat.name; })
        });
        module._state.categoryOrderDirty = false;
        module._updateCounters();
        module._setStatus('Category order saved.');
      } catch (err) {
        module._setStatus('Save failed: ' + (err.message || err));
      } finally {
        if (saveBtn) saveBtn.disabled = false;
      }
    },

    _saveItemOrder: async function () {
      var module = this;
      var c = module._container;
      if (!c) return;
      var cat = module._state.categories.find(function (cat) { return cat.name === module._state.selectedCategory; });
      if (!cat) {
        module._setStatus('No category selected.');
        return;
      }
      module._setStatus('Saving item order...');
      var saveBtn = c.querySelector('#mncSaveItemBtn');
      if (saveBtn) saveBtn.disabled = true;
      try {
        await module._authClient.apiPost({
          action: 'admin_menu_designer_save_item_order',
          sheetName: module._state.sheetName,
          category: cat.name,
          itemIds: cat.items.map(function (item) { return item.id; })
        });
        module._state.itemOrderDirty = false;
        module._updateCounters();
        module._setStatus('Item order saved.');
      } catch (err) {
        module._setStatus('Save failed: ' + (err.message || err));
      } finally {
        if (saveBtn) saveBtn.disabled = false;
      }
    },

    _toggleCategory: async function (index) {
      var module = this;
      var c = module._container;
      if (!c) return;
      var cat = module._state.categories[index];
      if (!cat) return;
      var newAvailable = !cat.isAvailable;
      module._setStatus('Updating category...');
      try {
        await module._authClient.apiPost({
          action: 'admin_menu_designer_toggle_category',
          sheetName: module._state.sheetName,
          category: cat.name,
          isAvailable: newAvailable
        });
        cat.isAvailable = newAvailable;
        cat.items.forEach(function (item) { item.isAvailable = newAvailable; });
        module._renderCategories();
        module._renderItems();
        module._setStatus('Category ' + (newAvailable ? 'shown' : 'hidden') + ': ' + cat.name);
      } catch (err) {
        module._setStatus('Toggle failed: ' + (err.message || err));
      }
    },

    _toggleItem: async function (index) {
      var module = this;
      var c = module._container;
      if (!c) return;
      var cat = module._state.categories.find(function (cat) { return cat.name === module._state.selectedCategory; });
      if (!cat) return;
      var item = cat.items[index];
      if (!item) return;
      var newAvailable = !item.isAvailable;
      module._setStatus('Updating item...');
      try {
        await module._authClient.apiPost({
          action: 'admin_menu_designer_toggle_item',
          sheetName: module._state.sheetName,
          id: item.id,
          isAvailable: newAvailable
        });
        item.isAvailable = newAvailable;
        // Recompute category availability: visible if any item is visible
        cat.isAvailable = cat.items.some(function (i) { return i.isAvailable; });
        module._renderCategories();
        module._renderItems();
        module._setStatus('Item ' + (newAvailable ? 'shown' : 'hidden') + ': ' + item.itemName);
      } catch (err) {
        module._setStatus('Toggle failed: ' + (err.message || err));
      }
    },

    _ensureSortable: function (cb) {
      if (window.Sortable) {
        cb();
        return;
      }
      var script = document.createElement('script');
      script.src = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js';
      script.onload = cb;
      script.onerror = function () {
        console.warn('SortableJS failed to load; drag-and-drop unavailable.');
        cb();
      };
      document.head.appendChild(script);
    },

    _attachCategorySortable: function () {
      var module = this;
      var c = module._container;
      if (!c || !window.Sortable) return;
      if (module._catSortable) { module._catSortable.destroy(); module._catSortable = null; }
      var listEl = c.querySelector('#mncCategoryList');
      if (!listEl) return;
      module._catSortable = Sortable.create(listEl, {
        handle: '.mnc-drag-handle',
        animation: 150,
        onEnd: function (evt) {
          var from = evt.oldIndex;
          var to   = evt.newIndex;
          if (from === to) return;
          var cats = module._state.categories;
          var removed = cats.splice(from, 1)[0];
          cats.splice(to, 0, removed);
          module._state.categoryOrderDirty = true;
          module._updateCounters();
          module._renderCategories();
        }
      });
    },

    _attachItemSortable: function () {
      var module = this;
      var c = module._container;
      if (!c || !window.Sortable) return;
      if (module._itemSortable) { module._itemSortable.destroy(); module._itemSortable = null; }
      var listEl = c.querySelector('#mncItemList');
      if (!listEl) return;
      module._itemSortable = Sortable.create(listEl, {
        handle: '.mnc-drag-handle',
        animation: 150,
        onEnd: function (evt) {
          var from = evt.oldIndex;
          var to   = evt.newIndex;
          if (from === to) return;
          var cat = module._state.categories.find(function (cat) { return cat.name === module._state.selectedCategory; });
          if (!cat) return;
          var removed = cat.items.splice(from, 1)[0];
          cat.items.splice(to, 0, removed);
          module._state.itemOrderDirty = true;
          module._updateCounters();
          module._renderItems();
        }
      });
    },

    _selectCategory: function (encodedName) {
      this._state.selectedCategory = decodeURIComponent(encodedName);
      this._renderCategories();
      this._renderItems();
    },

    _moveCategory: function (index, delta) {
      var cats = this._state.categories;
      var newIndex = index + delta;
      if (newIndex < 0 || newIndex >= cats.length) return;
      var removed = cats.splice(index, 1)[0];
      cats.splice(newIndex, 0, removed);
      this._state.categoryOrderDirty = true;
      this._renderCategories();
      this._updateCounters();
    },

    _moveItem: function (index, delta) {
      var cat = this._state.categories.find(function (c) { return c.name === this._state.selectedCategory; }, this);
      if (!cat) return;
      var newIndex = index + delta;
      if (newIndex < 0 || newIndex >= cat.items.length) return;
      var removed = cat.items.splice(index, 1)[0];
      cat.items.splice(newIndex, 0, removed);
      this._state.itemOrderDirty = true;
      this._renderItems();
      this._updateCounters();
    },

    // ── Rendering ─────────────────────────────────────────────────────────────

    _renderCategories: function () {
      var module = this;
      var c = module._container;
      if (!c) return;
      var listEl = c.querySelector('#mncCategoryList');
      if (!listEl) return;
      var cats = module._state.categories;

      if (!cats.length) {
        listEl.innerHTML = '<div class="mnc-muted" style="padding:12px">No categories.</div>';
        return;
      }

      listEl.innerHTML = cats.map(function (cat, i) {
        var isActive = cat.name === module._state.selectedCategory;
        var rowClass = 'row-item' + (isActive ? ' active' : '') + (!cat.isAvailable ? ' dimmed' : '');
        var encName = encodeURIComponent(cat.name);
        return '<div class="' + rowClass + '" data-cat-index="' + i + '">'
          + '<span class="mnc-drag-handle" title="Drag to reorder">⠿</span>'
          + '<div class="mnc-row-main" onclick="window.NKCategoryDesigner.selectCategory(\'' + encName + '\')">'
          + '<span class="mnc-row-name">' + NK.MODULE_BASE.escHtml(cat.name) + '</span>'
          + '<span class="tag' + (!cat.isAvailable ? ' off' : '') + '">' + (cat.isAvailable ? 'Visible' : 'Hidden') + '</span>'
          + '</div>'
          + '<div class="mnc-row-actions" onclick="event.stopPropagation()">'
          + '<button class="mnc-icon-btn" title="' + (cat.isAvailable ? 'Hide' : 'Show') + '" onclick="window.NKCategoryDesigner.toggleCategory(' + i + ')">'
          + (cat.isAvailable ? 'Hide' : 'Show') + '</button>'
          + '</div>'
          + '</div>';
      }).join('');
      module._attachCategorySortable();
    },

    _renderItems: function () {
      var module = this;
      var c = module._container;
      if (!c) return;
      var listEl = c.querySelector('#mncItemList');
      if (!listEl) return;

      var cat = module._state.categories.find(function (cat) { return cat.name === module._state.selectedCategory; });

      if (!cat) {
        listEl.innerHTML = '<div class="mnc-muted" style="padding:12px">Select a category to view items.</div>';
        return;
      }
      var items = cat.items;

      if (!items.length) {
        listEl.innerHTML = '<div class="mnc-muted" style="padding:12px">No items in this category.</div>';
        return;
      }

      listEl.innerHTML = items.map(function (item, i) {
        var rowClass = 'row-item' + (!item.isAvailable ? ' dimmed' : '');
        var priceText = (item.basePrice !== null && item.basePrice !== undefined && item.basePrice !== '')
          ? '₹' + item.basePrice : '--';
        return '<div class="' + rowClass + '" data-item-index="' + i + '">'
          + '<span class="mnc-drag-handle" title="Drag to reorder">⠿</span>'
          + '<div class="mnc-row-main">'
          + '<span class="mnc-row-name">' + NK.MODULE_BASE.escHtml(item.itemName) + '</span>'
          + '<span class="mnc-row-price">' + priceText + '</span>'
          + '<span class="tag' + (!item.isAvailable ? ' off' : '') + '">' + (item.isAvailable ? 'Visible' : 'Hidden') + '</span>'
          + '</div>'
          + '<div class="mnc-row-actions">'
          + '<button class="mnc-icon-btn" onclick="window.NKCategoryDesigner.toggleItem(' + i + ')">'
          + (item.isAvailable ? 'Hide' : 'Show') + '</button>'
          + '</div>'
          + '</div>';
      }).join('');
      module._attachItemSortable();
    },

    _updateCounters: function () {
      var module = this;
      var c = module._container;
      if (!c) return;
      var state = module._state;

      var catChip = c.querySelector('#mncCategoryChip');
      if (catChip) {
        catChip.textContent = 'Category Order' + (state.categoryOrderDirty ? ' *' : '');
        catChip.classList.toggle('warn', !!state.categoryOrderDirty);
      }
      var itemChip = c.querySelector('#mncItemChip');
      if (itemChip) {
        itemChip.textContent = 'Item Order' + (state.itemOrderDirty ? ' *' : '');
        itemChip.classList.toggle('warn', !!state.itemOrderDirty);
      }

      var saveCatBtn = c.querySelector('#mncSaveCategoryBtn');
      if (saveCatBtn) saveCatBtn.disabled = !state.categoryOrderDirty;
      var saveItemBtn = c.querySelector('#mncSaveItemBtn');
      if (saveItemBtn) saveItemBtn.disabled = !state.itemOrderDirty;
    },

    // ── Events ────────────────────────────────────────────────────────────────

    _bindEvents: function () {
      var module = this;
      var c = module._container;

      var sheetSel = c.querySelector('#mncSheetSelect');
      if (sheetSel) sheetSel.addEventListener('change', function () {
        module._state.sheetName = sheetSel.value;
        module._state.selectedCategory = '';
        module._loadDesigner();
      });

      var reloadBtn = c.querySelector('#mncReloadBtn');
      if (reloadBtn) reloadBtn.addEventListener('click', function () { module._loadDesigner(); });

      var saveCatBtn = c.querySelector('#mncSaveCategoryBtn');
      if (saveCatBtn) saveCatBtn.addEventListener('click', function () { module._saveCategoryOrder(); });

      var saveItemBtn = c.querySelector('#mncSaveItemBtn');
      if (saveItemBtn) saveItemBtn.addEventListener('click', function () { module._saveItemOrder(); });
    },

    // ── Misc helpers ──────────────────────────────────────────────────────────

    _setStatus: function (msg) {
      var c = this._container;
      if (!c) return;
      var el = c.querySelector('#mncStatus');
      if (el) el.textContent = msg;
    },

    // ── HTML ──────────────────────────────────────────────────────────────────

    _buildHtml: function () {
      return [
        '<div class="mnc-wrap">',

        // Toolbar
        '<div class="mnc-toolbar">',
        '  <div class="mnc-toolbar-left">',
        '    <select id="mncSheetSelect" class="mnc-toolbar-select">',
        '      <option value="AWGNK MENU">AWGNK MENU</option>',
        '      <option value="BAR MENU NK">BAR MENU NK</option>',
        '    </select>',
        '    <button class="mnc-btn mnc-btn-sec" id="mncReloadBtn">Reload</button>',
        '    <span class="meta-chip" id="mncCategoryChip">Category Order</span>',
        '    <span class="meta-chip" id="mncItemChip">Item Order</span>',
        '  </div>',
        '  <div class="mnc-toolbar-right">',
        '    <button class="mnc-btn mnc-btn-sec" id="mncSaveCategoryBtn" disabled>Save Category Order</button>',
        '    <button class="mnc-btn mnc-btn-sec" id="mncSaveItemBtn" disabled>Save Item Order</button>',
        '  </div>',
        '</div>',

        '<div id="mncStatus" class="mnc-status mnc-muted">Loading...</div>',

        // Two-column grid
        '<div class="mnc-columns">',
        '  <div class="mnc-panel mnc-panel-categories">',
        '    <div class="mnc-panel-heading">Categories</div>',
        '    <div class="mnc-list" id="mncCategoryList"></div>',
        '  </div>',
        '  <div class="mnc-panel mnc-panel-items">',
        '    <div class="mnc-panel-heading">Items</div>',
        '    <div class="mnc-list" id="mncItemList"></div>',
        '  </div>',
        '</div>',

        '</div>'
      ].join('\n');
    },

    _injectStyles: function () {
      var id = 'mnc-styles';
      if (document.getElementById(id)) return;
      var style = document.createElement('style');
      style.id = id;
      style.textContent = [
        '.mnc-wrap { padding:16px; display:flex; flex-direction:column; gap:10px; }',
        '.mnc-toolbar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:6px; }',
        '.mnc-toolbar-left,.mnc-toolbar-right { display:flex; align-items:center; flex-wrap:wrap; gap:6px; }',
        '.mnc-toolbar-select { padding:6px 10px; border-radius:8px; border:1px solid rgba(0,0,0,0.18); font-size:0.84rem; background:#fff; }',
        '.mnc-status { font-size:0.82rem; color:#888; padding:2px 4px; }',
        '.mnc-muted { color:#888; }',
        '.mnc-columns { display:grid; grid-template-columns:1fr 1.4fr; gap:12px; }',
        '.mnc-panel { background:#fff; border:1px solid rgba(0,0,0,0.1); border-radius:12px; overflow:hidden; }',
        '.mnc-panel-heading { padding:10px 12px; font-size:0.78rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#888; background:#faf5ec; border-bottom:1px solid rgba(0,0,0,0.08); }',
        '.mnc-list { overflow-y:auto; max-height:calc(100vh - 280px); }',
        '.row-item { display:flex; align-items:center; justify-content:space-between; padding:8px 12px; border-bottom:1px solid rgba(0,0,0,0.06); cursor:pointer; font-size:0.85rem; gap:8px; }',
        '.row-item:last-child { border-bottom:none; }',
        '.row-item:hover { background:rgba(182,123,69,0.06); }',
        '.row-item.active { background:rgba(33,64,56,0.08); }',
        '.row-item.dimmed { opacity:0.55; }',
        '.mnc-row-main { display:flex; align-items:center; gap:8px; flex:1; min-width:0; }',
        '.mnc-row-name { font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; flex:1; }',
        '.mnc-row-price { color:#888; font-size:0.8rem; white-space:nowrap; }',
        '.mnc-row-actions { display:flex; gap:4px; flex-shrink:0; }',
        '.tag { display:inline-flex; padding:2px 8px; border-radius:999px; font-size:0.7rem; font-weight:700; background:rgba(56,142,60,0.12); color:#2e7d32; border:1px solid rgba(56,142,60,0.2); white-space:nowrap; }',
        '.tag.off { background:rgba(164,83,72,0.1); color:#7a342b; border-color:rgba(164,83,72,0.2); }',
        '.mnc-icon-btn { padding:3px 8px; border-radius:6px; border:1px solid rgba(0,0,0,0.16); background:#fff; font-size:0.78rem; cursor:pointer; }',
        '.mnc-icon-btn:disabled { opacity:0.35; cursor:default; }',
        '.mnc-icon-btn:not(:disabled):hover { background:#f0ece6; }',
        '.mnc-drag-handle { cursor:grab; padding:2px 6px 2px 0; color:#bbb; font-size:1.1rem; user-select:none; flex-shrink:0; }',
        '.mnc-drag-handle:active { cursor:grabbing; }',
        '.sortable-ghost { opacity:0.5; background:#e8f5e9; }',
        '.sortable-chosen { background:#fff9f0; }',
        '.row-item { touch-action:none; }',  /* required for mobile drag */
        '.mnc-btn { padding:6px 13px; border-radius:8px; border:none; background:#214038; color:#fff; font-size:0.83rem; font-weight:600; cursor:pointer; }',
        '.mnc-btn:disabled { opacity:0.45; cursor:default; }',
        '.mnc-btn-sec { background:transparent; border:1px solid rgba(0,0,0,0.18); color:#214038; }',
        '.meta-chip { display:inline-flex; padding:3px 10px; border-radius:999px; font-size:0.75rem; font-weight:600; background:rgba(0,0,0,0.06); border:1px solid transparent; }',
        '.meta-chip.warn { background:rgba(182,123,69,0.18); color:#7a4720; border-color:rgba(182,123,69,0.3); }',
        '@media(max-width:700px){.mnc-columns{grid-template-columns:1fr;} .mnc-wrap{padding:8px;}}'
      ].join('\n');
      document.head.appendChild(style);
    }
  };
})(window.NK || (window.NK = {}));
