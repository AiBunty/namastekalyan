(function (window) {
  'use strict';

  if (window.__NK_DATA_CONFIG_LOADED__) return;
  window.__NK_DATA_CONFIG_LOADED__ = true;

  const runtimeHost = (window.location && window.location.hostname)
    ? String(window.location.hostname).toLowerCase()
    : '';

  const runtimeProtocol = (window.location && window.location.protocol)
    ? String(window.location.protocol).toLowerCase()
    : '';

  const runtimePath = (window.location && window.location.pathname)
    ? String(window.location.pathname).toLowerCase()
    : '';

  const isLocalRuntime =
    runtimeProtocol === 'file:' ||
    runtimeHost === 'localhost' ||
    runtimeHost === '127.0.0.1';

  const runtimeOrigin = (window.location && window.location.origin)
    ? String(window.location.origin).trim()
    : '';

  const documentRef = window.document || null;
  const currentScript = documentRef
    ? (
        documentRef.currentScript ||
        documentRef.querySelector('script[src*="js/data-config.js"]')
      )
    : null;
  const currentScriptSrc = currentScript && currentScript.src
    ? String(currentScript.src).trim()
    : '';

  let publicRootUrl = '';
  const localPublicRootUrl = runtimeOrigin ? (runtimeOrigin.replace(/\/+$/, '') + '/public/') : '';
  if (isLocalRuntime && localPublicRootUrl) {
    publicRootUrl = localPublicRootUrl;
  } else if (currentScriptSrc) {
    try {
      publicRootUrl = new URL('../', currentScriptSrc).toString();
    } catch (err) {
      publicRootUrl = '';
    }
  }

  const resolvePublicUrl = function resolvePublicUrl(value) {
    const raw = String(value || '').trim();
    if (!raw) {
      return publicRootUrl;
    }

    if (
      raw.startsWith('http://') ||
      raw.startsWith('https://') ||
      raw.startsWith('data:') ||
      raw.startsWith('mailto:') ||
      raw.startsWith('tel:') ||
      raw.startsWith('javascript:')
    ) {
      return raw;
    }

    if (publicRootUrl) {
      try {
        return new URL(raw, publicRootUrl).toString();
      } catch (err) {
        // Fall through to raw value.
      }
    }

    return raw;
  };

  const isLocalAssetReference = function isLocalAssetReference(value) {
    const raw = String(value || '').trim();
    if (!raw) return false;
    if (
      raw.startsWith('http://') ||
      raw.startsWith('https://') ||
      raw.startsWith('data:') ||
      raw.startsWith('mailto:') ||
      raw.startsWith('tel:') ||
      raw.startsWith('javascript:') ||
      raw.startsWith('#')
    ) {
      return false;
    }

    return /^(?:\.\.\/|\.\/|\/)?assets\//i.test(raw);
  };

  const canonicalizeLocalAssetReference = function canonicalizeLocalAssetReference(value) {
    const raw = String(value || '').trim();
    if (!isLocalAssetReference(raw)) {
      return raw;
    }

    const normalized = raw
      .replace(/^(?:\.\.\/)+/g, '')
      .replace(/^\.\//, '')
      .replace(/^\/+/, '');

    return resolvePublicUrl(normalized);
  };

  const configuredSiteBase = (window.NK_RUNTIME_CONFIG && window.NK_RUNTIME_CONFIG.publicSiteBase)
    ? String(window.NK_RUNTIME_CONFIG.publicSiteBase).trim()
    : '';
  const detectedSiteBase = runtimeOrigin
    ? (runtimeOrigin.replace(/\/+$/, '') + '/')
    : (publicRootUrl ? publicRootUrl : '');
  const localPhpApiBase = runtimeOrigin ? (runtimeOrigin.replace(/\/+$/, '') + '/') : '/';
  const deployedSiteBase = (configuredSiteBase || detectedSiteBase || 'https://namastekalyan.asianwokandgrill.in/').replace(/\/+$/, '') + '/';
  const remotePhpApiBase = deployedSiteBase;
  const phpApiBase = isLocalRuntime ? localPhpApiBase : remotePhpApiBase;
  const localSitePathMap = {
    home: '',
    menu: 'menu/',
    cocktail: 'cocktails/cocktail.html',
    admin: 'admin/'
  };
  const remoteSitePathMap = {
    home: '',
    menu: 'menu.html',
    cocktail: 'cocktail.html',
    admin: 'admin/'
  };

  const resolveSiteUrl = function resolveSiteUrl(pageKey) {
    const key = String(pageKey || '').trim().toLowerCase();
    const pathMap = remoteSitePathMap;
    const resolvedPath = Object.prototype.hasOwnProperty.call(pathMap, key)
      ? pathMap[key]
      : pathMap.home;

    const siteBase = deployedSiteBase.replace(/\/+$/, '') + '/';
    try {
      return new URL(resolvedPath, siteBase).toString();
    } catch (err) {
      return siteBase;
    }
  };

  window.NK_DATA_API = window.NK_DATA_API || {};
  Object.assign(window.NK_DATA_API, {
    // PHP-only backend for both local and remote.
    // Legacy keys are kept only as inert aliases for compatibility.
    legacyAppsScriptUrl: '',
    legacyAppsScriptBackupUrl: '',
    publicRootUrl: publicRootUrl,
    deployedSiteBase: deployedSiteBase,
    resolvePublicUrl: resolvePublicUrl,
    resolveSiteUrl: resolveSiteUrl,
    sitePathMap: remoteSitePathMap,
    qrDestinationUrls: {
      home: resolveSiteUrl('home'),
      menu: resolveSiteUrl('menu'),
      cocktail: resolveSiteUrl('cocktail'),
      admin: resolveSiteUrl('admin')
    },
    canonicalizeLocalAssetReference: canonicalizeLocalAssetReference,
    // Keep backward-compatible key name, but always route to PHP API.
    appsScriptUrl: phpApiBase,
    phpApiUrl: phpApiBase,
    // Backend migration is complete and validated, so route configured actions to PHP.
    enablePhpForSelectedActions: true,
    phpRoutedActions: [
      // Auth
      'auth_login', 'auth_logout', 'auth_me', 'auth_change_password',
      'auth_create_user', 'auth_set_user_status', 'auth_reset_password',
      'auth_set_user_permissions', 'auth_get_api_settings', 'auth_set_api_settings',
      'auth_get_app_settings', 'auth_set_app_settings',
      'auth_get_qr_redirect_settings', 'auth_set_qr_redirect_settings',
      'auth_list_qr_redirects', 'auth_save_qr_redirect', 'auth_set_qr_redirect_active',
      'auth_list_users', 'auth_bootstrap_status',
      // Events
      'events_list', 'event_list', 'event_detail', 'event_popup',
      'create_event_order', 'confirm_event_payment', 'register_free_event',
      'resend_event_confirmation', 'request_event_cancellation',
      'admin_create_event', 'admin_update_event', 'admin_toggle_event',
      'admin_list_events', 'event_guest_report', 'event_transactions_report', 'admin_mail_log_report',
      'verify_event_qr', 'admin_preview_event_qr', 'admin_batch_checkin_event_qr',
      // Menu editor
      'admin_menu_editor_load', 'admin_menu_editor_save_changes',
      'admin_menu_editor_add_row', 'admin_menu_editor_delete_rows',
      'admin_menu_editor_set_visibility',
      // Cashier
      'admin_issue_cash_paid_pass', 'admin_request_cash_handover',
      'admin_request_cash_cancel', 'superadmin_approve_cash_handover',
      'superadmin_resolve_cash_cancel', 'admin_cash_summary', 'superadmin_cash_dashboard',
      // Leads / Spin & Win
      'submit_lead', 'verify', 'redeem', 'regen_coupon', 'regenerate_coupon',
      'counter', 'qr_report', 'qr_scan_client', 'qr_redirect_resolve',
      'admin_crm_panel_status', 'admin_list_crm_contacts', 'admin_list_crm_push_logs',
      'admin_backfill_crm_contacts', 'admin_export_crm_contacts',
      'admin_crm_leads_status', 'admin_list_crm_leads', 'admin_export_crm_leads'
    ],
    hotelWhatsappNo: '919371519999'
  });

  // Backward-compatible global endpoint consumed by legacy and blocker scripts.
  window.APPS_SCRIPT_URL = String(window.NK_DATA_API.appsScriptUrl || '').trim();

  window.NK_DATA_API.resolveApiBaseForAction = function resolveApiBaseForAction(action) {
    const config = window.NK_DATA_API || {};
    const defaultBase = String(config.appsScriptUrl || '').trim();
    const phpBase = String(config.phpApiUrl || '').trim();
    // PHP is the only backend now (local + remote).
    return phpBase || defaultBase;
  };

  const normalizeElementAssetAttributes = function normalizeElementAssetAttributes(root) {
    const scope = root && root.querySelectorAll ? root : documentRef;
    if (!scope || !scope.querySelectorAll) return;

    const candidates = [];
    const videosToRefresh = new Set();
    if (scope.matches && scope.matches('img[src], source[src], video[src], video[poster], link[href], meta[content], [data-full]')) {
      candidates.push(scope);
    }
    scope.querySelectorAll('img[src], source[src], video[src], video[poster], link[href], meta[content], [data-full]').forEach(function (element) {
      candidates.push(element);
    });

    candidates.forEach(function (element) {
      if (element.closest && element.closest('[data-skip-asset-normalize="true"]')) {
        return;
      }

      if (element.hasAttribute('src')) {
        const rawSrc = element.getAttribute('src');
        const nextSrc = canonicalizeLocalAssetReference(rawSrc);
        if (nextSrc && nextSrc !== rawSrc) {
          element.setAttribute('src', nextSrc);
          if (String(element.tagName || '').toUpperCase() === 'SOURCE') {
            const parentVideo = element.parentElement;
            if (parentVideo && String(parentVideo.tagName || '').toUpperCase() === 'VIDEO') {
              videosToRefresh.add(parentVideo);
            }
          } else if (String(element.tagName || '').toUpperCase() === 'VIDEO') {
            videosToRefresh.add(element);
          }
        }
      }

      if (element.hasAttribute('poster')) {
        const rawPoster = element.getAttribute('poster');
        const nextPoster = canonicalizeLocalAssetReference(rawPoster);
        if (nextPoster && nextPoster !== rawPoster) {
          element.setAttribute('poster', nextPoster);
          if (String(element.tagName || '').toUpperCase() === 'VIDEO') {
            videosToRefresh.add(element);
          }
        }
      }

      if (element.hasAttribute('href')) {
        const rel = String(element.getAttribute('rel') || '').toLowerCase();
        if (rel.indexOf('icon') !== -1) {
          const rawHref = element.getAttribute('href');
          const nextHref = canonicalizeLocalAssetReference(rawHref);
          if (nextHref && nextHref !== rawHref) {
            element.setAttribute('href', nextHref);
          }
        }
      }

      if (element.hasAttribute('content')) {
        const property = String(element.getAttribute('property') || '').toLowerCase();
        const name = String(element.getAttribute('name') || '').toLowerCase();
        if (property === 'og:image' || name === 'twitter:image') {
          const rawContent = element.getAttribute('content');
          const nextContent = canonicalizeLocalAssetReference(rawContent);
          if (nextContent && nextContent !== rawContent) {
            element.setAttribute('content', nextContent);
          }
        }
      }

      if (element.hasAttribute('data-full')) {
        const rawFull = element.getAttribute('data-full');
        const nextFull = canonicalizeLocalAssetReference(rawFull);
        if (nextFull && nextFull !== rawFull) {
          element.setAttribute('data-full', nextFull);
        }
      }
    });

    videosToRefresh.forEach(function (videoEl) {
      if (!videoEl || typeof videoEl.load !== 'function') return;
      try {
        videoEl.load();
        if (videoEl.autoplay && typeof videoEl.play === 'function') {
          const playPromise = videoEl.play();
          if (playPromise && typeof playPromise.catch === 'function') {
            playPromise.catch(function () {});
          }
        }
      } catch (err) {
        // Ignore media refresh failures.
      }
    });
  };

  const setupAssetNormalization = function setupAssetNormalization() {
    normalizeElementAssetAttributes(documentRef);

    if (!documentRef || !documentRef.body || typeof MutationObserver !== 'function') {
      return;
    }

    const observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        mutation.addedNodes.forEach(function (node) {
          if (!node || node.nodeType !== 1) return;
          normalizeElementAssetAttributes(node);
        });
      });
    });

    observer.observe(documentRef.body, { childList: true, subtree: true });
  };

  if (documentRef) {
    if (documentRef.readyState === 'loading') {
      documentRef.addEventListener('DOMContentLoaded', setupAssetNormalization, { once: true });
    } else {
      setupAssetNormalization();
    }
  }

  // Optional diagnostics (dev/local/diag=1) to trace runtime base resolution.
  try {
    const params = new URLSearchParams(window.location.search || '');
    const isDebug = params.get('diag') === '1' || isLocalRuntime;
    if (isDebug) {
      window.NK_DATA_API.debug = {
        isLocalRuntime: isLocalRuntime,
        runtimeHost: runtimeHost,
        phpApiBase: phpApiBase,
      };
      if (window.console && typeof window.console.info === 'function') {
        window.console.info('[NK_DATA_API] PHP-only base resolved', {
          phpApiBase: phpApiBase,
          appsScriptAlias: window.NK_DATA_API.appsScriptUrl,
          globalAlias: window.APPS_SCRIPT_URL
        });
      }
    }
  } catch (err) {
    // Ignore diagnostics failures.
  }
})(window);
