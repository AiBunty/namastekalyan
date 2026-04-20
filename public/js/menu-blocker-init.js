/**
 * Menu Blocker Settings Initializer
 * Loads WhatsApp number and staff code from API before menu-blocker.js runs
 * Include this BEFORE menu-blocker.js in your HTML
 */

(function() {
  'use strict';

  const CONFIG = {
    apiEndpoint: '/api_settings.php',
    cacheKey: 'nk_menu_blocker_settings_cache',
    cacheDuration: 30 * 1000,
    timeout: 3000 // 3 second timeout for API call
  };

  /**
   * Get cached settings
   */
  function getCachedSettings() {
    try {
      const cached = localStorage.getItem(CONFIG.cacheKey);
      if (!cached) return null;

      const data = JSON.parse(cached);
      const now = Date.now();

      if (data.expiresAt && now < data.expiresAt && data.settings) {
        console.log('[MenuBlocker Init] Using cached settings');
        return data.settings;
      }

      localStorage.removeItem(CONFIG.cacheKey);
    } catch (error) {
      console.warn('[MenuBlocker Init] Cache error:', error);
      try {
        localStorage.removeItem(CONFIG.cacheKey);
      } catch (e) {}
    }

    return null;
  }

  /**
   * Cache settings
   */
  function setCachedSettings(settings) {
    try {
      const data = {
        settings: settings,
        expiresAt: Date.now() + CONFIG.cacheDuration,
        cachedAt: new Date().toISOString()
      };
      localStorage.setItem(CONFIG.cacheKey, JSON.stringify(data));
    } catch (error) {
      console.warn('[MenuBlocker Init] Failed to cache settings:', error);
    }
  }

  /**
   * Load settings from API
   */
  function loadSettingsFromAPI() {
    return Promise.race([
      fetch(CONFIG.apiEndpoint, {
        method: 'GET',
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      })
      .then(response => {
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return response.json();
      })
      .then(data => {
        if (data && data.ok && data.settings) {
          console.log('[MenuBlocker Init] Settings loaded from API');
          return data.settings;
        }
        throw new Error(data.error || 'Invalid API response');
      }),
      
      new Promise((_, reject) =>
        setTimeout(() => reject(new Error('API timeout')), CONFIG.timeout)
      )
    ]);
  }

  /**
   * Initialize settings
   */
  function initSettings() {
    // First, try cached settings
    const cached = getCachedSettings();
    if (cached) {
      applySettings(cached);
      return Promise.resolve(cached);
    }

    // If not cached, fetch from API
    return loadSettingsFromAPI()
      .then(settings => {
        setCachedSettings(settings);
        applySettings(settings);
        return settings;
      })
      .catch(error => {
        console.warn('[MenuBlocker Init] Failed to load settings:', error.message);
        // Continue with defaults
        return null;
      });
  }

  /**
   * Apply settings to window object for menu-blocker.js
   */
  function applySettings(settings) {
    if (!settings) return;

    // Initialize NK_DATA_API if not already present
    if (typeof window.NK_DATA_API === 'undefined') {
      window.NK_DATA_API = {};
    }

    // Set WhatsApp number
    if (settings.hotelWhatsappNo) {
      window.NK_DATA_API.hotelWhatsappNo = settings.hotelWhatsappNo;
      console.log('[MenuBlocker Init] WhatsApp number set:', settings.hotelWhatsappNo);
    }

    // Set staff code
    if (typeof settings.menuBlockerStaffCode !== 'undefined' && settings.menuBlockerStaffCode !== null) {
      window.MENU_BLOCKER_STAFF_CODE = String(settings.menuBlockerStaffCode || '').trim().toUpperCase();
      console.log('[MenuBlocker Init] Staff code configured');
    }

    window.NK_APP_SETTINGS = settings;
  }

  /**
   * Clear cache (call from admin panel after saving settings)
   */
  window.MenuBlockerInitClearCache = function() {
    try {
      localStorage.removeItem(CONFIG.cacheKey);
      console.log('[MenuBlocker Init] Cache cleared');
    } catch (error) {
      console.warn('[MenuBlocker Init] Failed to clear cache:', error);
    }
  };

  /**
   * Reload settings (call from admin panel to refresh)
   */
  window.MenuBlockerInitReload = function() {
    window.MenuBlockerInitClearCache();
    return initSettings().then(function(settings) {
      if (settings) {
        applySettings(settings);
      }
      return settings;
    });
  };

  // Initialize on DOM ready or immediately
  window.NK_APP_SETTINGS_READY = initSettings()
    .then(function(settings) {
      document.dispatchEvent(new CustomEvent('nk:app-settings-ready', {
        detail: { settings: settings || null }
      }));
      return settings;
    });
})();
