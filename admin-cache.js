/**
 * Admin Panel Caching System
 * Preserves module state across tab switches and provides loading feedback
 */

(function () {
  'use strict';

  // Cache configuration
  const CACHE_PREFIX = 'admin_cache_v1_';
  const CACHE_TTL = 30 * 60 * 1000; // 30 minutes
  const LOADER_TIMEOUT = 50; // ms before showing loader

  // Module state cache
  const ModuleCache = {
    set: function (moduleId, key, data) {
      try {
        const cacheKey = CACHE_PREFIX + moduleId + '_' + key;
        const wrapped = {
          timestamp: Date.now(),
          data: data
        };
        sessionStorage.setItem(cacheKey, JSON.stringify(wrapped));
      } catch (err) {
        console.warn('Cache storage full:', err);
      }
    },

    get: function (moduleId, key) {
      try {
        const cacheKey = CACHE_PREFIX + moduleId + '_' + key;
        const stored = sessionStorage.getItem(cacheKey);
        if (!stored) return null;

        const wrapped = JSON.parse(stored);
        if (Date.now() - wrapped.timestamp > CACHE_TTL) {
          sessionStorage.removeItem(cacheKey);
          return null;
        }

        return wrapped.data;
      } catch (err) {
        return null;
      }
    },

    clear: function (moduleId) {
      try {
        const prefix = CACHE_PREFIX + moduleId;
        const keysToDelete = [];

        for (let i = 0; i < sessionStorage.length; i += 1) {
          const key = sessionStorage.key(i);
          if (key && key.indexOf(prefix) === 0) {
            keysToDelete.push(key);
          }
        }

        keysToDelete.forEach(function (key) {
          sessionStorage.removeItem(key);
        });
      } catch (err) {
        console.warn('Error clearing cache:', err);
      }
    }
  };

  // Loading indicator system
  const LoadingIndicator = {
    create: function () {
      var existing = document.getElementById('adminModuleLoader');
      if (existing) return existing;

      var loader = document.createElement('div');
      loader.id = 'adminModuleLoader';
      loader.className = 'admin-module-loader';
      loader.innerHTML = '<div class="admin-loader-spinner"></div><p class="admin-loader-text">Loading...</p>';
      return loader;
    },

    show: function (container, message) {
      if (!container) return;

      var loader = this.create();
      if (message) {
        var textEl = loader.querySelector('.admin-loader-text');
        if (textEl) textEl.textContent = message;
      }

      loader.style.display = 'flex';
      if (!loader.parentNode || loader.parentNode !== container) {
        container.appendChild(loader);
      }

      loader._hideTimer = null;
      return loader;
    },

    hide: function (loader, delay) {
      if (!loader) return;

      delay = Number(delay) || 300;

      if (loader._hideTimer) {
        clearTimeout(loader._hideTimer);
      }

      loader._hideTimer = setTimeout(function () {
        if (loader && loader.style) {
          loader.style.display = 'none';
        }
        loader._hideTimer = null;
      }, delay);
    }
  };

  // API call wrapper with cache + loader
  const cachedApiPost = function (apiPost, moduleId, action, payload) {
    // Check cache first
    var cacheKey = action + '_' + JSON.stringify(payload);
    var cached = ModuleCache.get(moduleId, cacheKey);
    if (cached) {
      return Promise.resolve(cached);
    }

    // Show loader and make API call
    var loaderContainer = document.body;
    var loader = LoadingIndicator.show(loaderContainer, 'Fetching ' + action + '...');

    return apiPost(payload)
      .then(function (result) {
        // Cache the result
        ModuleCache.set(moduleId, cacheKey, result);
        LoadingIndicator.hide(loader, 200);
        return result;
      })
      .catch(function (err) {
        LoadingIndicator.hide(loader, 200);
        throw err;
      });
  };

  // Export to window for modules to use
  window.AdminCache = ModuleCache;
  window.AdminLoader = LoadingIndicator;
  window.AdminCachedApi = cachedApiPost;
})();
