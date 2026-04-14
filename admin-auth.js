(function (window) {
  'use strict';

  const AUTH_ERRORS = {
    UNAUTHORIZED: true,
    TOKEN_EXPIRED: true,
    INVALID_TOKEN: true,
    FORBIDDEN: true
  };

  function safeParseJson(text) {
    try {
      return JSON.parse(text);
    } catch (err) {
      return null;
    }
  }

  function normalizeApiBase(url) {
    return String(url || '').split('?')[0].trim();
  }

  function ensureCenterLoaderStyle(doc) {
    if (!doc || doc.getElementById('nkAdminCenterLoaderStyle')) return;
    const style = doc.createElement('style');
    style.id = 'nkAdminCenterLoaderStyle';
    style.textContent = [
      '.nk-admin-center-loader{position:fixed;inset:0;z-index:99999;display:none;flex-direction:column;align-items:center;justify-content:center;gap:14px;background:radial-gradient(circle at 20% 20%,#17202f 0%,#090d14 58%,#000 100%);opacity:1;transition:opacity .25s ease;}',
      '.nk-admin-center-loader.is-visible{display:flex;}',
      '.nk-admin-center-loader.is-hiding{opacity:0;pointer-events:none;}',
      '.nk-admin-loader-orbit{position:relative;width:92px;height:92px;display:grid;place-items:center;}',
      '.nk-admin-loader-ring{position:absolute;inset:0;border-radius:50%;border:2px solid rgba(255,255,255,.16);border-top-color:#d4af74;animation:nkAdminLoaderSpin 1.05s linear infinite;}',
      '.nk-admin-loader-ring.inner{inset:12px;border-top-color:#b67b45;animation-duration:1.5s;animation-direction:reverse;}',
      '.nk-admin-loader-dot{width:8px;height:8px;border-radius:50%;background:#fff;box-shadow:0 0 12px rgba(255,255,255,.8);animation:nkAdminLoaderPulse .95s ease-in-out infinite alternate;}',
      '.nk-admin-loader-text{min-height:20px;font-size:.74rem;letter-spacing:2px;color:#fff;text-transform:uppercase;font-family:"Avenir Next","Trebuchet MS",Verdana,sans-serif;font-weight:700;}',
      '.nk-admin-loader-sub{color:rgba(255,255,255,.66);font-size:.63rem;letter-spacing:1.2px;text-transform:uppercase;font-family:"Avenir Next","Trebuchet MS",Verdana,sans-serif;}',
      '@keyframes nkAdminLoaderSpin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}',
      '@keyframes nkAdminLoaderPulse{from{opacity:.55;transform:scale(.9)}to{opacity:1;transform:scale(1.12)}}'
    ].join('');
    doc.head.appendChild(style);
  }

  function ensureCenterLoader(doc) {
    if (!doc || !doc.body) return null;
    let loader = doc.getElementById('nkAdminCenterLoader');
    if (loader) return loader;

    loader = doc.createElement('div');
    loader.id = 'nkAdminCenterLoader';
    loader.className = 'nk-admin-center-loader';
    loader.setAttribute('aria-live', 'polite');
    loader.setAttribute('aria-busy', 'true');
    loader.innerHTML = [
      '<div class="nk-admin-loader-orbit" aria-hidden="true">',
      '  <div class="nk-admin-loader-ring"></div>',
      '  <div class="nk-admin-loader-ring inner"></div>',
      '  <div class="nk-admin-loader-dot"></div>',
      '</div>',
      '<div class="nk-admin-loader-text" id="nkAdminCenterLoaderText">Loading...</div>',
      '<div class="nk-admin-loader-sub">Fetching live data</div>'
    ].join('');
    doc.body.appendChild(loader);
    return loader;
  }

  function createAuthClient(options) {
    const settings = options || {};
    const sessionKey = String(settings.sessionKey || 'nk_admin_auth_session_v1');
    const apiBase = normalizeApiBase(
      settings.apiBase ||
      (window.APPS_SCRIPT_URL || (window.NK_DATA_API && window.NK_DATA_API.appsScriptUrl) || '')
    );
    const onAuthError = typeof settings.onAuthError === 'function' ? settings.onAuthError : null;

    let authSession = null;
    let pendingRequestCount = 0;
    let loaderVisibleAt = 0;

    function showRequestLoader(message) {
      if (!window.document || !window.document.body) return;
      ensureCenterLoaderStyle(window.document);
      const loader = ensureCenterLoader(window.document);
      if (!loader) return;

      const textEl = loader.querySelector('#nkAdminCenterLoaderText');
      if (textEl) {
        textEl.textContent = String(message || 'Loading...');
      }

      loader.classList.remove('is-hiding');
      loader.classList.add('is-visible');
      loaderVisibleAt = Date.now();
    }

    function hideRequestLoader() {
      const loader = window.document && window.document.getElementById('nkAdminCenterLoader');
      if (!loader) return;

      const elapsed = Date.now() - loaderVisibleAt;
      const remaining = Math.max(0, 180 - elapsed);
      window.setTimeout(function () {
        loader.classList.add('is-hiding');
        window.setTimeout(function () {
          loader.classList.remove('is-visible');
          loader.classList.remove('is-hiding');
        }, 220);
      }, remaining);
    }

    function beginRequest(message) {
      pendingRequestCount += 1;
      if (pendingRequestCount === 1) {
        showRequestLoader(message);
      }
    }

    function endRequest() {
      pendingRequestCount = Math.max(0, pendingRequestCount - 1);
      if (pendingRequestCount === 0) {
        hideRequestLoader();
      }
    }

    function getQueryToken() {
      try {
        const params = new URLSearchParams(window.location.search || '');
        return String(params.get('authToken') || '').trim();
      } catch (err) {
        return '';
      }
    }

    function hydrateSessionFromQueryToken() {
      if (authSession && authSession.token) {
        return;
      }

      const queryToken = getQueryToken();
      if (!queryToken) {
        return;
      }

      saveSession({
        token: queryToken,
        expiresAt: '',
        user: null
      });
    }

    function sessionToken() {
      return authSession && authSession.token ? String(authSession.token) : '';
    }

    function getSession() {
      return authSession;
    }

    function saveSession(session) {
      authSession = session || null;
      try {
        if (authSession) {
          window.sessionStorage.setItem(sessionKey, JSON.stringify(authSession));
        } else {
          window.sessionStorage.removeItem(sessionKey);
        }
      } catch (err) {
        // Ignore browser storage errors.
      }
    }

    function clearSession() {
      saveSession(null);
    }

    function requestError(response, payload) {
      const message = payload && (payload.message || payload.error)
        ? (payload.message || payload.error)
        : ('HTTP ' + String(response && response.status ? response.status : '500'));
      return new Error(message);
    }

    function handleAuthFailure(payload) {
      const errorCode = payload && payload.error ? String(payload.error) : '';
      if (!AUTH_ERRORS[errorCode]) return;

      clearSession();
      if (onAuthError) {
        onAuthError(payload);
      }
    }

    async function apiGet(action, params) {
      if (!apiBase) {
        throw new Error('Apps Script URL is missing in data-config.js.');
      }

      beginRequest('Loading data...');

      const query = new URLSearchParams(Object.assign({ action: action }, params || {}));
      if (sessionToken() && !query.has('token')) {
        query.set('token', sessionToken());
      }

      try {
        const response = await fetch(apiBase + '?' + query.toString(), { cache: 'no-store' });
        const payload = await response.json();
        if (!response.ok || !payload || payload.ok !== true) {
          handleAuthFailure(payload);
          throw requestError(response, payload);
        }
        return payload;
      } finally {
        endRequest();
      }
    }

    async function apiPost(body) {
      if (!apiBase) {
        throw new Error('Apps Script URL is missing in data-config.js.');
      }

      beginRequest('Fetching data...');

      const postBody = Object.assign({}, body || {});
      if (sessionToken() && !postBody.token) {
        postBody.token = sessionToken();
      }

      const formBody = new URLSearchParams();
      formBody.set('payload', JSON.stringify(postBody));

      try {
        const response = await fetch(apiBase, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
          body: formBody.toString()
        });
        const payload = await response.json();
        if (!response.ok || !payload || payload.ok !== true) {
          handleAuthFailure(payload);
          throw requestError(response, payload);
        }
        return payload;
      } finally {
        endRequest();
      }
    }

    async function login(username, password, source) {
      const payload = await apiPost({
        action: 'auth_login',
        username: String(username || ''),
        password: String(password || ''),
        source: String(source || '')
      });

      saveSession({
        token: payload.token,
        expiresAt: payload.expiresAt,
        user: payload.user
      });

      return payload;
    }

    async function restoreSession() {
      hydrateSessionFromQueryToken();
      const raw = window.sessionStorage.getItem(sessionKey);
      if (!raw) {
        return null;
      }

      const parsed = safeParseJson(raw);
      if (!parsed || !parsed.token) {
        clearSession();
        return null;
      }

      saveSession(parsed);
      const payload = await apiGet('auth_me');
      authSession.user = payload.user;
      saveSession(authSession);
      return payload;
    }

    async function logout() {
      try {
        if (sessionToken()) {
          await apiPost({ action: 'auth_logout' });
        }
      } catch (err) {
        // Ignore transport errors on logout.
      }
      clearSession();
    }

    return {
      getApiBase: function () { return apiBase; },
      getSession: getSession,
      getToken: sessionToken,
      saveSession: saveSession,
      clearSession: clearSession,
      login: login,
      restoreSession: restoreSession,
      logout: logout,
      apiGet: apiGet,
      apiPost: apiPost
    };
  }

  window.NKAdminAuth = {
    createAuthClient: createAuthClient
  };
})(window);
