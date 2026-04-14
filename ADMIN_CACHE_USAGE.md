/**
 * ═══════════════════════════════════════════════════════════════════
 * Admin Module Cache System - Usage Guide
 * ═══════════════════════════════════════════════════════════════════
 * 
 * This system provides:
 * 1. SessionStorage caching for module data (survives tab switches)
 * 2. Automatic loading animation during API calls
 * 3. Instant restoration when switching back to a cached tab
 * 
 * ═══════════════════════════════════════════════════════════════════
 * QUICK START
 * ═══════════════════════════════════════════════════════════════════
 * 
 * 1. LOAD DATA WITH CACHE CHECK:
 * 
 *    const loadApiSettings = async function() {
 *      // Check if we already have cached data
 *      const cached = window.AdminCache.get('admin-user-management', 'api_settings');
 *      if (cached) {
 *        displayData(cached);
 *        return; // Instant load, don't fetch again
 *      }
 *
 *      // No cache found, fetch from API (loader automatically shows)
 *      const payload = await apiPost({ action: 'auth_get_api_settings' });
 *      if (payload) {
 *        // Cache the result for future tab switches
 *        window.AdminCache.set('admin-user-management', 'api_settings', payload);
 *        displayData(payload);
 *      }
 *    };
 * 
 * 
 * 2. SHOW LOADING ANIMATION:
 * 
 *    const loader = window.AdminLoader.show(document.body, 'Loading settings...');
 *    // ... do work ...
 *    window.AdminLoader.hide(loader); // Auto-hides after 300ms
 * 
 * 
 * 3. CLEAR CACHE WHEN NEEDED:
 * 
 *    // After saving settings, clear cache to force refresh next time
 *    window.AdminCache.clear('admin-user-management');
 * 
 * ═══════════════════════════════════════════════════════════════════
 * API REFERENCE
 * ═══════════════════════════════════════════════════════════════════
 * 
 * window.AdminCache.set(moduleId, key, data)
 *   Sets a cache entry (auto-expires in 30 minutes)
 *   moduleId: string like 'admin-user-management'
 *   key: string identifier for the data (e.g., 'api_settings')
 *   data: any value to cache
 * 
 * window.AdminCache.get(moduleId, key)
 *   Gets cached data (returns null if expired or missing)
 *   Returns: any value or null
 * 
 * window.AdminCache.clear(moduleId)
 *   Clears all cached data for a module
 *   Useful after saves/deletes to force refresh
 * 
 * 
 * window.AdminLoader.show(container, message)
 *   Shows loading indicator at bottom-right
 *   container: DOM element (typically document.body)
 *   message: optional string like 'Loading settings...'
 *   Returns: loader element (pass to hide)
 * 
 * window.AdminLoader.hide(loader, delayMs)
 *   Hides the loader element
 *   loader: returned from show()
 *   delayMs: optional delay before hiding (default 300)
 * 
 * ═══════════════════════════════════════════════════════════════════
 * EXAMPLE MODULE PATTERN
 * ═══════════════════════════════════════════════════════════════════
 * 
 * (function() {
 *   const MODULE_ID = 'admin-example-module';
 *   
 *   async function loadData() {
 *     // Check cache first
 *     let data = window.AdminCache.get(MODULE_ID, 'main_data');
 *     if (data) {
 *       render(data);
 *       return;
 *     }
 *     
 *     // Show loader and fetch
 *     const loader = window.AdminLoader.show(document.body, 'Loading data...');
 *     try {
 *       const payload = await apiPost({ action: 'get_something' });
 *       window.AdminCache.set(MODULE_ID, 'main_data', payload);
 *       render(payload);
 *     } finally {
 *       window.AdminLoader.hide(loader);
 *     }
 *   }
 *   
 *   async function saveData(newData) {
 *     const loader = window.AdminLoader.show(document.body, 'Saving...');
 *     try {
 *       const result = await apiPost({ action: 'save_something', data: newData });
 *       // Clear cache to force refresh next time
 *       window.AdminCache.clear(MODULE_ID);
 *       showNotification('Saved!');
 *     } finally {
 *       window.AdminLoader.hide(loader);
 *     }
 *   }
 *   
 *   // Initialize on page load
 *   if (document.readyState === 'loading') {
 *     document.addEventListener('DOMContentLoaded', loadData);
 *   } else {
 *     loadData();
 *   }
 * })();
 * 
 * ═══════════════════════════════════════════════════════════════════
 * BENEFITS
 * ═══════════════════════════════════════════════════════════════════
 * 
 * ✅ Tab Switching: Switch tabs and come back - data is instant
 * ✅ Loading Feedback: Users see loader animation during fetches
 * ✅ Smart Caching: Data expires after 30 minutes automatically
 * ✅ Cache Control: Clear cache after saves to force refresh
 * ✅ No Boilerplate: Same system works for all modules
 * 
 * ═══════════════════════════════════════════════════════════════════
 */
