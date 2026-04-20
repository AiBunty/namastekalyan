(function () {
  const configuredApi = (typeof window !== 'undefined' && window.APPS_SCRIPT_URL) ? String(window.APPS_SCRIPT_URL) : '';
  const WEBHOOK_URL = configuredApi ? configuredApi.split('?')[0] : '';
  const STORAGE_KEY = 'nk_menu_blocker_has_spun_v1';
  const SEEN_KEY = 'nk_menu_blocker_seen_once_v1';
  const COOKIE_KEY = 'nk_menu_blocker_has_spun';
  const STAFF_BYPASS_KEY = 'nk_menu_blocker_staff_bypass_v1';
  const LAST_COMPLETED_AT_KEY = 'nk_menu_blocker_last_completed_at_v1';
  const COOKIE_DAYS = 30;
  const SPIN_COOLDOWN_MS = 24 * 60 * 60 * 1000;

  const PRIZES = [
    'Dessert on the House',
    'Mocktail on the House',
    'Aerated Drink on the House',
    'Starter on the House',
    '10% OFF',
    '15% OFF',
    '20% OFF',
    '25% OFF',
    'Try Again'
  ];
  const PRIZE_COLORS = ['#ff4fd8', '#4dffea', '#89ff45', '#ffd84d', '#ff8a3d', '#7c6dff', '#ff5252', '#3dc7ff', '#ff4f87'];

  let spinAngle = 0;
  let spinning = false;
  let targetPrize = 'Try Again';
  let leadPayload = null;
  let leadMeta = { result: '', status: '' };
  let latestCouponCode = '';

  function $(id) {
    return document.getElementById(id);
  }

  function getConfiguredHotelWhatsapp() {
    const configuredHotelWa = (typeof window !== 'undefined' && window.NK_DATA_API && window.NK_DATA_API.hotelWhatsappNo)
      ? String(window.NK_DATA_API.hotelWhatsappNo)
      : '';

    return onlyDigits(configuredHotelWa) || resolveHotelWhatsappFromFooter() || '919371519999';
  }

  function normalizeStaffSecretCode(value) {
    return String(value || '').trim().toUpperCase();
  }

  function getStaffSecretCode() {
    return (typeof window !== 'undefined' && window.MENU_BLOCKER_STAFF_CODE)
      ? normalizeStaffSecretCode(window.MENU_BLOCKER_STAFF_CODE)
      : normalizeStaffSecretCode('NKSTAFF2026');
  }

  function getPublicAppSettings() {
    return (typeof window !== 'undefined' && window.NK_APP_SETTINGS && typeof window.NK_APP_SETTINGS === 'object')
      ? window.NK_APP_SETTINGS
      : {};
  }

  function resolveCurrentPageKey() {
    const path = String((window.location && window.location.pathname) || '').toLowerCase();
    if (path.indexOf('/menu/') !== -1) return 'menu';
    if (path.indexOf('/cocktails/') !== -1) return 'cocktail';
    return 'home';
  }

  function isBlockerEnabledForCurrentPage() {
    const settings = getPublicAppSettings();
    const pages = settings && settings.menuBlockerPages && typeof settings.menuBlockerPages === 'object'
      ? settings.menuBlockerPages
      : { home: true, menu: false, cocktail: false };
    const pageKey = resolveCurrentPageKey();
    return Boolean(pages[pageKey]);
  }

  function buildCountryOptionsMarkup() {
    return [
      ['91', 'IN +91', true],
      ['1', 'US/CA +1'],
      ['44', 'UK +44'],
      ['61', 'AU +61'],
      ['65', 'SG +65'],
      ['971', 'UAE +971'],
      ['974', 'QA +974'],
      ['966', 'SA +966'],
      ['968', 'OM +968'],
      ['977', 'NP +977']
    ].map(function (item) {
      return '<option value="' + item[0] + '"' + (item[2] ? ' selected' : '') + '>' + item[1] + '</option>';
    }).join('');
  }

  function buildOverlayMarkup() {
    return ''
      + '<div id="menuBlockerOverlay" class="mb-overlay" aria-modal="true" role="dialog" aria-label="Unlock menu">'
      + '  <div class="mb-card">'
      + '    <div id="mbStepForm" class="mb-step active">'
      + '      <h2 class="mb-title">Spin &amp; Win Discounts</h2>'
      + '      <p class="mb-subtitle">Fill the form below to unlock the spin wheel and reveal your offer.</p>'
      + '      <label class="mb-label" for="mbName">Name *</label>'
      + '      <input id="mbName" class="mb-input" type="text" maxlength="60" placeholder="Enter your name" autocomplete="name" />'
      + '      <label class="mb-label" for="mbPhone">Mobile Number *</label>'
      + '      <div class="mb-phone-row">'
      + '        <select id="mbCountryCode" class="mb-select" aria-label="Country code">' + buildCountryOptionsMarkup() + '</select>'
      + '        <input id="mbPhone" class="mb-input" type="tel" maxlength="14" inputmode="numeric" placeholder="10 digit mobile number" autocomplete="tel-national" />'
      + '      </div>'
      + '      <label class="mb-label" for="mbDob">Date of Birth</label>'
      + '      <div class="mb-date-row">'
      + '        <input id="mbDob" class="mb-input" type="text" inputmode="numeric" placeholder="DD/MM/YYYY" autocomplete="bday" />'
      + '        <input id="mbDobPicker" class="mb-date-native" type="date" tabindex="-1" aria-hidden="true" />'
      + '        <button id="mbDobPickerBtn" class="mb-date-btn" type="button" aria-label="Pick date of birth">Pick</button>'
      + '      </div>'
      + '      <label class="mb-label" for="mbAnniversary">Date of Anniversary</label>'
      + '      <div class="mb-date-row">'
      + '        <input id="mbAnniversary" class="mb-input" type="text" inputmode="numeric" placeholder="DD/MM/YYYY" />'
      + '        <input id="mbAnniversaryPicker" class="mb-date-native" type="date" tabindex="-1" aria-hidden="true" />'
      + '        <button id="mbAnniversaryPickerBtn" class="mb-date-btn" type="button" aria-label="Pick anniversary date">Pick</button>'
      + '      </div>'
      + '      <div id="mbFormError" class="mb-error" aria-live="polite"></div>'
      + '      <div id="mbFormStatus" class="mb-status">Fill details and submit to continue to spin.</div>'
      + '      <button id="mbFormSubmitBtn" class="mb-primary-btn" type="button">Submit &amp; Continue</button>'
      + '      <button id="mbStaffToggle" class="mb-staff-link" type="button">Staff bypass</button>'
      + '      <div id="mbStaffPanel" class="mb-staff-panel" aria-hidden="true">'
      + '        <input id="mbStaffCode" class="mb-input" type="password" placeholder="Enter staff secret code" />'
      + '        <button id="mbStaffBypassBtn" class="mb-secondary-btn" type="button">Unlock for Staff</button>'
      + '        <div id="mbStaffMsg" class="mb-status" aria-live="polite"></div>'
      + '      </div>'
      + '    </div>'
      + '    <div id="mbStepSpin" class="mb-step">'
      + '      <h2 class="mb-title">Spin the wheel</h2>'
      + '      <p class="mb-subtitle">Tap the button once to reveal your offer.</p>'
      + '      <div class="mb-wheel-wrap"><canvas id="mbWheelCanvas" width="320" height="320"></canvas></div>'
      + '      <div id="mbSpinStatus" class="mb-status" aria-live="polite"></div>'
      + '      <button id="mbSpinBtn" class="mb-primary-btn" type="button">Spin Now</button>'
      + '    </div>'
      + '    <div id="mbStepResult" class="mb-step">'
      + '      <h2 class="mb-title">Your reward</h2>'
      + '      <div id="mbResultText" class="mb-result-text">Try Again</div>'
      + '      <p id="mbResultHint" class="mb-subtitle"></p>'
      + '      <div id="mbCouponCode" class="mb-coupon-code" hidden></div>'
      + '      <div id="mbCouponActions" class="mb-actions-row" hidden>'
      + '        <button id="mbCopyCouponBtn" class="mb-secondary-btn" type="button">Copy Coupon</button>'
      + '        <button id="mbSendCouponBtn" class="mb-primary-btn" type="button">Send to Admin</button>'
      + '      </div>'
      + '      <div id="mbTryAgainActions" class="mb-actions-row" hidden>'
      + '        <button id="mbTryAgainWhatsappBtn" class="mb-primary-btn" type="button">Unlock Surprise Offer on WhatsApp</button>'
      + '      </div>'
      + '      <div id="mbCouponActionStatus" class="mb-status" aria-live="polite"></div>'
      + '      <button id="mbContinueBtn" class="mb-primary-btn" type="button">Continue to Menu</button>'
      + '    </div>'
      + '  </div>'
      + '</div>';
  }

  function ensureOverlayMarkup() {
    let overlay = $('menuBlockerOverlay');
    if (overlay) return overlay;

    document.body.insertAdjacentHTML('afterbegin', buildOverlayMarkup());
    return $('menuBlockerOverlay');
  }

  function setCookie(name, value, days) {
    const d = new Date();
    d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
    document.cookie = `${name}=${value};expires=${d.toUTCString()};path=/;SameSite=Lax`;
  }

  function getCookie(name) {
    const key = `${name}=`;
    return document.cookie
      .split(';')
      .map(s => s.trim())
      .find(s => s.startsWith(key))
      ?.substring(key.length) || '';
  }

  function isAlreadyCompleted() {
    const completedAtRaw = localStorage.getItem(LAST_COMPLETED_AT_KEY);
    const completedAt = Number(completedAtRaw || 0);
    const withinCooldown = Number.isFinite(completedAt) && completedAt > 0 && ((Date.now() - completedAt) < SPIN_COOLDOWN_MS);

    if (!withinCooldown && completedAt) {
      localStorage.removeItem(STORAGE_KEY);
      localStorage.removeItem(COOKIE_KEY);
      localStorage.removeItem(LAST_COMPLETED_AT_KEY);
      setCookie(COOKIE_KEY, '0', -1);
    }

    const local = withinCooldown && localStorage.getItem(STORAGE_KEY) === '1';
    const cookie = withinCooldown && getCookie(COOKIE_KEY) === '1';
    const staffBypass = sessionStorage.getItem(STAFF_BYPASS_KEY) === '1';
    return local || cookie || staffBypass;
  }

  function hasSeenBlockerOnce() {
    return localStorage.getItem(SEEN_KEY) === '1';
  }

  function markSeenBlockerOnce() {
    localStorage.setItem(SEEN_KEY, '1');
  }

  function markCompleted() {
    localStorage.setItem(STORAGE_KEY, '1');
    localStorage.setItem(LAST_COMPLETED_AT_KEY, String(Date.now()));
    setCookie(COOKIE_KEY, '1', COOKIE_DAYS);
  }

  function showStep(stepId) {
    ['mbStepForm', 'mbStepSpin', 'mbStepResult'].forEach(id => {
      const el = $(id);
      if (!el) return;
      el.classList.toggle('active', id === stepId);
    });
  }

  function onlyDigits(value) {
    return String(value || '').replace(/\D/g, '');
  }

  function validatePhone(localPhoneDigits, countryCodeDigits) {
    if (!/^\d{1,4}$/.test(countryCodeDigits)) return false;
    return /^\d{10}$/.test(localPhoneDigits);
  }

  function resolveHotelWhatsappFromFooter() {
    const telLink = document.querySelector('a[href^="tel:"]');
    const href = telLink ? String(telLink.getAttribute('href') || '') : '';
    const digits = onlyDigits(href);
    return digits || '';
  }

  function formatPhoneWithPlus(countryCode, phone) {
    const cc = onlyDigits(countryCode || '91');
    const local = onlyDigits(phone || '');
    if (!local) return '';
    if (local.startsWith(cc) && local.length > 10) return `+${local}`;
    return `+${cc}${local}`;
  }

  function buildAdminWhatsappMessage() {
    if (!leadPayload || !latestCouponCode) return '';

    const phoneIntl = formatPhoneWithPlus(leadPayload.countryCode, leadPayload.phone);
    const lines = [
      'Hello Admin, I want to redeem my Spin & Win coupon.',
      '',
      `Name: ${leadPayload.name || ''}`,
      `Mobile: ${phoneIntl}`,
      `DOB: ${leadPayload.dateOfBirth || '-'}`,
      `Anniversary: ${leadPayload.dateOfAnniversary || '-'}`,
      `Prize: ${targetPrize}`,
      `Coupon Code: ${latestCouponCode}`,
      `Requested At: ${new Date().toLocaleString()}`
    ];

    return lines.join('\n');
  }

  function buildTryAgainWhatsappMessage() {
    if (!leadPayload) return '';

    const phoneIntl = formatPhoneWithPlus(leadPayload.countryCode, leadPayload.phone);
    const lines = [
      'Hi Captain,',
      '',
      'I got "Try Again" on the Spin & Win at Namaste Kalyan.',
      '',
      `Name: ${leadPayload.name || ''}`,
      `Mobile: ${phoneIntl || '-'}`,
      '',
      'I have got try again, and I would like to know about the surprise offer.',
      'Please let me know what special offer is available for me.',
      '',
      'Thank you.'
    ];

    return lines.join('\n');
  }

  function isTryAgainPrize(prize) {
    return String(prize || '').trim().toLowerCase() === 'try again';
  }

  function setCouponActionsVisible(show) {
    const codeEl = $('mbCouponCode');
    const actionsEl = $('mbCouponActions');
    const statusEl = $('mbCouponActionStatus');
    if (!codeEl || !actionsEl || !statusEl) return;

    codeEl.hidden = !show;
    actionsEl.hidden = !show;
    if (!show) {
      codeEl.textContent = '';
      statusEl.textContent = '';
    }
  }

  function setTryAgainActionsVisible(show) {
    const actionsEl = $('mbTryAgainActions');
    const statusEl = $('mbCouponActionStatus');
    if (!actionsEl || !statusEl) return;

    actionsEl.hidden = !show;
    if (!show && $('mbCouponActions')?.hidden !== false) {
      statusEl.textContent = '';
    }
  }

  function renderResultStep() {
    showStep('mbStepResult');

    const result = $('mbResultText');
    const hint = $('mbResultHint');
    const couponCodeEl = $('mbCouponCode');
    const actionStatusEl = $('mbCouponActionStatus');
    const hasCoupon = !isTryAgainPrize(targetPrize) && !!latestCouponCode;
    const showTryAgainAction = isTryAgainPrize(targetPrize);
    const isDuplicate = String(leadMeta.result || '').toLowerCase() === 'duplicate';

    if (result) result.textContent = targetPrize;
    setCouponActionsVisible(hasCoupon);
    setTryAgainActionsVisible(showTryAgainAction);

    if (couponCodeEl && hasCoupon) {
      couponCodeEl.textContent = `Coupon Code: ${latestCouponCode}`;
    }

    if (actionStatusEl) {
      actionStatusEl.textContent = '';
    }

    if (hint) {
      if (showTryAgainAction) {
        hint.textContent = isDuplicate
          ? 'This number already has a Try Again result. Tap WhatsApp to ask the captain about your surprise offer.'
          : 'Tap WhatsApp now and ask the captain about the surprise offer waiting for you.';
      } else if (isDuplicate) {
        hint.textContent = 'This mobile already exists. Showing your previously assigned result.';
      } else {
        hint.textContent = 'Copy your code or send full details to admin on WhatsApp for redemption.';
      }
    }
  }

  function formatDdMmYyyyFromDigits(digits) {
    if (!digits) return '';
    if (digits.length <= 2) return digits;
    if (digits.length <= 4) return `${digits.slice(0, 2)}/${digits.slice(2)}`;
    return `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4, 8)}`;
  }

  function normalizeDateTyping(value) {
    const digits = onlyDigits(value).slice(0, 8);
    return formatDdMmYyyyFromDigits(digits);
  }

  function parseDateDdMmYyyy(value) {
    const normalized = String(value || '').trim();
    if (!normalized) return { valid: true, display: '', iso: '' };

    const match = normalized.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    if (!match) return { valid: false, display: normalized, iso: '' };

    const day = Number(match[1]);
    const month = Number(match[2]);
    const year = Number(match[3]);
    const candidate = new Date(year, month - 1, day);
    const valid = candidate.getFullYear() === year
      && candidate.getMonth() === (month - 1)
      && candidate.getDate() === day;

    if (!valid) return { valid: false, display: normalized, iso: '' };

    const iso = `${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    return { valid: true, display: normalized, iso: iso };
  }

  function isoToDdMmYyyy(isoDate) {
    const iso = String(isoDate || '').trim();
    const match = iso.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!match) return '';
    return `${match[3]}/${match[2]}/${match[1]}`;
  }

  function bindDateField(textInputId, pickerInputId, pickerButtonId) {
    const textInput = $(textInputId);
    const pickerInput = $(pickerInputId);
    const pickerButton = $(pickerButtonId);
    if (!textInput || !pickerInput || !pickerButton) return;

    textInput.addEventListener('input', () => {
      const normalized = normalizeDateTyping(textInput.value);
      if (textInput.value !== normalized) textInput.value = normalized;

      const parsed = parseDateDdMmYyyy(normalized);
      pickerInput.value = parsed.valid && parsed.iso ? parsed.iso : '';
    });

    textInput.addEventListener('blur', () => {
      const parsed = parseDateDdMmYyyy(textInput.value);
      if (!parsed.valid) return;
      textInput.value = parsed.display;
      pickerInput.value = parsed.iso;
    });

    pickerButton.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      
      // Try showPicker() first (modern browsers)
      if (typeof pickerInput.showPicker === 'function') {
        try {
          pickerInput.showPicker();
          return;
        } catch (err) {
          // Fallback if showPicker fails
        }
      }
      
      // Fallback: temporarily make the input visible and clickable
      const origDisplay = pickerInput.style.display;
      const origPointer = pickerInput.style.pointerEvents;
      const origOpacity = pickerInput.style.opacity;
      const origWidth = pickerInput.style.width;
      const origHeight = pickerInput.style.height;
      
      pickerInput.style.display = 'block';
      pickerInput.style.pointerEvents = 'auto';
      pickerInput.style.opacity = '1';
      pickerInput.style.width = '250px';
      pickerInput.style.height = '40px';
      
      pickerInput.focus();
      pickerInput.click();
      
      // Restore original styles after picker closes
      setTimeout(() => {
        pickerInput.style.display = origDisplay;
        pickerInput.style.pointerEvents = origPointer;
        pickerInput.style.opacity = origOpacity;
        pickerInput.style.width = origWidth;
        pickerInput.style.height = origHeight;
      }, 100);
    });

    pickerInput.addEventListener('change', () => {
      const display = isoToDdMmYyyy(pickerInput.value);
      if (display) textInput.value = display;
    });
  }

  function drawWheel(angle) {
    const canvas = $('mbWheelCanvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const cx = canvas.width / 2;
    const cy = canvas.height / 2;
    const radius = canvas.width / 2 - 6;
    const innerRadius = 34;
    const segments = PRIZES.length;
    const arc = (Math.PI * 2) / segments;

    ctx.clearRect(0, 0, canvas.width, canvas.height);

    const outerGlow = ctx.createRadialGradient(cx, cy, 18, cx, cy, radius + 16);
    outerGlow.addColorStop(0, 'rgba(255, 255, 255, 0)');
    outerGlow.addColorStop(0.72, 'rgba(255, 86, 214, 0.08)');
    outerGlow.addColorStop(0.88, 'rgba(61, 199, 255, 0.18)');
    outerGlow.addColorStop(1, 'rgba(0, 0, 0, 0)');
    ctx.fillStyle = outerGlow;
    ctx.beginPath();
    ctx.arc(cx, cy, radius + 18, 0, Math.PI * 2);
    ctx.fill();

    ctx.save();
    ctx.translate(cx, cy);
    ctx.rotate(angle);

    for (let i = 0; i < segments; i += 1) {
      const startAngle = i * arc;
      const endAngle = (i + 1) * arc;
      const midAngle = startAngle + arc / 2;
      const color = PRIZE_COLORS[i];
      const edgeX = Math.cos(midAngle) * radius;
      const edgeY = Math.sin(midAngle) * radius;
      const segmentGradient = ctx.createLinearGradient(0, 0, edgeX, edgeY);
      segmentGradient.addColorStop(0, '#fffef7');
      segmentGradient.addColorStop(0.12, color);
      segmentGradient.addColorStop(0.72, color);
      segmentGradient.addColorStop(1, 'rgba(20, 12, 28, 0.94)');

      ctx.beginPath();
      ctx.moveTo(0, 0);
      ctx.fillStyle = segmentGradient;
      ctx.arc(0, 0, radius, startAngle, endAngle);
      ctx.closePath();
      ctx.shadowColor = color;
      ctx.shadowBlur = 18;
      ctx.fill();

      ctx.shadowBlur = 0;
      ctx.lineWidth = 2;
      ctx.strokeStyle = 'rgba(255, 255, 255, 0.38)';
      ctx.stroke();

      ctx.save();
      ctx.rotate(midAngle);
      ctx.fillStyle = '#fffdf6';
      ctx.font = '800 13px Manrope';
      ctx.textAlign = 'right';
      ctx.textBaseline = 'middle';
      ctx.shadowColor = 'rgba(8, 8, 18, 0.85)';
      ctx.shadowBlur = 12;
      ctx.fillText(PRIZES[i], radius - 16, 6);
      ctx.restore();
    }

    ctx.beginPath();
    ctx.arc(0, 0, radius - 12, 0, Math.PI * 2);
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.26)';
    ctx.lineWidth = 3;
    ctx.stroke();

    ctx.beginPath();
    ctx.arc(0, 0, innerRadius, 0, Math.PI * 2);
    const hubGradient = ctx.createRadialGradient(0, 0, 6, 0, 0, innerRadius);
    hubGradient.addColorStop(0, '#fff8df');
    hubGradient.addColorStop(0.36, '#ffe45b');
    hubGradient.addColorStop(0.72, '#ff4fd8');
    hubGradient.addColorStop(1, '#4b123e');
    ctx.fillStyle = hubGradient;
    ctx.shadowColor = 'rgba(255, 79, 216, 0.65)';
    ctx.shadowBlur = 20;
    ctx.fill();
    ctx.shadowBlur = 0;
    ctx.lineWidth = 4;
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.46)';
    ctx.stroke();

    ctx.restore();

    ctx.beginPath();
    ctx.arc(cx, cy, 16, 0, Math.PI * 2);
    ctx.fillStyle = '#fff8df';
    ctx.fill();
    ctx.strokeStyle = 'rgba(126, 31, 95, 0.95)';
    ctx.lineWidth = 3.5;
    ctx.stroke();
  }

  function getPrizeIndex(prize) {
    const normalized = String(prize || '').trim().toLowerCase();
    const idx = PRIZES.findIndex((item) => String(item || '').trim().toLowerCase() === normalized);
    return idx >= 0 ? idx : (PRIZES.length - 1);
  }

  function computeTargetAngle(prize) {
    const idx = getPrizeIndex(prize);
    const segmentAngle = (Math.PI * 2) / PRIZES.length;
    const segmentMid = idx * segmentAngle + segmentAngle / 2;
    const pointerAngle = -Math.PI / 2;
    const align = pointerAngle - segmentMid;
    const turns = (Math.PI * 2) * (5 + Math.floor(Math.random() * 3));
    return turns + align;
  }

  function resolveServerEndpoint() {
    const fromResolver = (typeof window !== 'undefined'
      && window.NK_DATA_API
      && typeof window.NK_DATA_API.resolveApiBaseForAction === 'function')
      ? String(window.NK_DATA_API.resolveApiBaseForAction('submit_lead') || '').trim()
      : '';

    const fromConfig = (typeof window !== 'undefined' && window.NK_DATA_API)
      ? String(window.NK_DATA_API.phpApiUrl || window.NK_DATA_API.appsScriptUrl || '').trim()
      : '';

    const base = (fromResolver || fromConfig || WEBHOOK_URL || '').split('?')[0].trim();

    if (!base || base.indexOf('REPLACE_WITH_YOUR_WEBAPP_ID') !== -1) {
      throw new Error('PHP API endpoint is not configured.');
    }

    return base;
  }

  async function postLead(inputPayload) {
    const payload = {
      ...inputPayload,
      timestamp: new Date().toISOString(),
      source: 'menu-blocker-web'
    };

    const endpoint = resolveServerEndpoint();

    // Use simple form POST to avoid CORS preflight on local origins.
    const formBody = new URLSearchParams({ payload: JSON.stringify({ action: 'submit_lead', ...payload }) });
    const res = await fetch(endpoint.split('?')[0] + '?action=submit_lead', {
      method: 'POST',
      body: formBody
    });

    if (!res.ok) throw new Error(`Webhook failed: ${res.status}`);
    const json = await res.json();
    return json;
  }

  async function submitLeadAndGetPrize(payload) {
    const response = await postLead(payload);
    if (!response || response.ok !== true) {
      throw new Error((response && response.message) ? response.message : 'Unable to process request.');
    }
    leadMeta = {
      result: String(response.result || ''),
      status: String(response.status || '')
    };
    return {
      prize: response && response.prize ? String(response.prize) : 'Try Again',
      row: response.row || null,
      couponCode: response && response.couponCode ? String(response.couponCode) : ''
    };
  }

  function unlockMenu(reason) {
    const overlay = $('menuBlockerOverlay');
    if (overlay) overlay.classList.add('hidden');

    if (typeof document !== 'undefined') {
      document.dispatchEvent(new CustomEvent('nk:menu-blocker-closed', {
        detail: {
          reason: String(reason || 'unknown'),
          at: Date.now()
        }
      }));
    }
  }

  function continueAfterSpin() {
    const pageKey = resolveCurrentPageKey();
    if (pageKey === 'home') {
      unlockMenu('continue-after-spin-home');
      window.location.hash = 'foodMenuCard';
      const target = document.getElementById('foodMenuCard') || document.getElementById('menu');
      if (target) {
        window.setTimeout(() => {
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
          if (typeof target.focus === 'function') {
            target.focus({ preventScroll: true });
          }
        }, 40);
      }
      return;
    }

    window.location.href = '../#foodMenuCard';
  }

  function setStaffBypass() {
    sessionStorage.setItem(STAFF_BYPASS_KEY, '1');
    unlockMenu('staff-bypass');
  }

  function moveToSpinStep() {
    const status = $('mbFormStatus');
    if (status) status.textContent = 'Form submitted. Opening spin wheel...';
    showStep('mbStepSpin');
    drawWheel(spinAngle);
    const spinStatus = $('mbSpinStatus');
    if (spinStatus) spinStatus.textContent = '';
  }

  function setupEvents() {
    const formSubmitBtn = $('mbFormSubmitBtn');
    const spinBtn = $('mbSpinBtn');
    const continueBtn = $('mbContinueBtn');
    const copyCouponBtn = $('mbCopyCouponBtn');
    const sendCouponBtn = $('mbSendCouponBtn');
    const tryAgainWhatsappBtn = $('mbTryAgainWhatsappBtn');
    const staffToggle = $('mbStaffToggle');
    const staffPanel = $('mbStaffPanel');
    const staffCode = $('mbStaffCode');
    const staffBypassBtn = $('mbStaffBypassBtn');
    const staffMsg = $('mbStaffMsg');
    const phoneInput = $('mbPhone');

    bindDateField('mbDob', 'mbDobPicker', 'mbDobPickerBtn');
    bindDateField('mbAnniversary', 'mbAnniversaryPicker', 'mbAnniversaryPickerBtn');

    if (staffToggle && staffPanel) {
      staffToggle.addEventListener('click', () => {
        const open = staffPanel.classList.toggle('open');
        staffPanel.setAttribute('aria-hidden', open ? 'false' : 'true');
      });
    }

    if (staffBypassBtn && staffCode) {
      staffBypassBtn.addEventListener('click', () => {
        const code = normalizeStaffSecretCode(staffCode.value || '');
        if (code && code === getStaffSecretCode()) {
          if (staffMsg) staffMsg.textContent = '';
          setStaffBypass();
          return;
        }
        if (staffMsg) staffMsg.textContent = 'Invalid staff secret code.';
      });
    }

    if (phoneInput) {
      phoneInput.addEventListener('input', () => {
        const digits = onlyDigits(phoneInput.value).slice(0, 10);
        if (phoneInput.value !== digits) {
          phoneInput.value = digits;
        }
      });
    }

    if (formSubmitBtn) {
      formSubmitBtn.addEventListener('click', async () => {
        const name = ($('mbName')?.value || '').trim();
        const countryCode = onlyDigits(($('mbCountryCode')?.value || '91').trim()) || '91';
        const phoneRaw = ($('mbPhone')?.value || '').trim();
        const phone = onlyDigits(phoneRaw);
        const dobInput = ($('mbDob')?.value || '').trim();
        const anniversaryInput = ($('mbAnniversary')?.value || '').trim();
        const error = $('mbFormError');
        const status = $('mbFormStatus');

        const dobParsed = parseDateDdMmYyyy(dobInput);
        const anniversaryParsed = parseDateDdMmYyyy(anniversaryInput);

        if (!name) {
          if (error) error.textContent = 'Name is required.';
          return;
        }
        if (!validatePhone(phone, countryCode)) {
          if (error) error.textContent = 'Valid 10-digit mobile number is required.';
          return;
        }
        if (!dobParsed.valid) {
          if (error) error.textContent = 'Date of Birth must be in DD/MM/YYYY format.';
          return;
        }
        if (!anniversaryParsed.valid) {
          if (error) error.textContent = 'Date of Anniversary must be in DD/MM/YYYY format.';
          return;
        }

        if (error) error.textContent = '';
        if (status) status.textContent = 'Submitting your details...';
        formSubmitBtn.disabled = true;

        try {
          leadPayload = {
            name,
            phone,
            countryCode,
            dateOfBirth: dobParsed.display,
            dateOfBirthIso: dobParsed.iso,
            dateOfAnniversary: anniversaryParsed.display,
            dateOfAnniversaryIso: anniversaryParsed.iso
          };

          const server = await submitLeadAndGetPrize(leadPayload);
          targetPrize = server.prize;
          latestCouponCode = server.couponCode || '';

          if (String(leadMeta.result || '').toLowerCase() === 'duplicate') {
            if (isTryAgainPrize(targetPrize)) {
              markCompleted();
              renderResultStep();
              return;
            }
            markCompleted();
            unlockMenu('duplicate-existing');
            return;
          }

          if (String(leadMeta.status || '').toLowerCase() === 'redeemed') {
            markCompleted();
            unlockMenu('already-redeemed');
            return;
          }

          moveToSpinStep();
        } catch (err) {
          if (error) error.textContent = err && err.message ? err.message : 'Submission failed. Please try again.';
          if (status) status.textContent = 'Fill details and submit to continue to spin.';
          formSubmitBtn.disabled = false;
        }
      });
    }

    if (spinBtn) {
      spinBtn.addEventListener('click', async () => {
        if (spinning) return;
        const status = $('mbSpinStatus');

        if (!leadPayload) {
          if (status) status.textContent = 'Please submit your details first.';
          return;
        }

        spinning = true;
        spinBtn.disabled = true;
        if (status) status.textContent = 'Spinning...';

        const from = spinAngle % (Math.PI * 2);
        const to = computeTargetAngle(targetPrize);
        const duration = 4200;
        const startTime = performance.now();

        const animate = (now) => {
          const t = Math.min((now - startTime) / duration, 1);
          const eased = 1 - Math.pow(1 - t, 4);
          spinAngle = from + (to - from) * eased;
          drawWheel(spinAngle);

          if (t < 1) {
            requestAnimationFrame(animate);
            return;
          }

          spinning = false;
          markCompleted();
          renderResultStep();

          if (typeof document !== 'undefined') {
            document.dispatchEvent(new CustomEvent('nk:spin-finished', {
              detail: { prize: targetPrize, couponCode: latestCouponCode || '' }
            }));
          }

        };

        requestAnimationFrame(animate);
      });
    }

    if (continueBtn) {
      continueBtn.addEventListener('click', () => {
        continueAfterSpin();
      });
    }

    if (copyCouponBtn) {
      copyCouponBtn.addEventListener('click', async () => {
        const statusEl = $('mbCouponActionStatus');
        if (!latestCouponCode) {
          if (statusEl) statusEl.textContent = 'No coupon code available.';
          return;
        }

        try {
          if (navigator.clipboard && navigator.clipboard.writeText) {
            await navigator.clipboard.writeText(latestCouponCode);
          } else {
            const temp = document.createElement('textarea');
            temp.value = latestCouponCode;
            document.body.appendChild(temp);
            temp.select();
            document.execCommand('copy');
            document.body.removeChild(temp);
          }
          if (statusEl) statusEl.textContent = 'Coupon code copied.';
        } catch (err) {
          if (statusEl) statusEl.textContent = 'Copy failed. Please copy manually.';
        }
      });
    }

    if (sendCouponBtn) {
      sendCouponBtn.addEventListener('click', () => {
        const statusEl = $('mbCouponActionStatus');
        if (!latestCouponCode) {
          if (statusEl) statusEl.textContent = 'No coupon code available.';
          return;
        }

        const hotelWhatsappNo = getConfiguredHotelWhatsapp();
        if (!hotelWhatsappNo) {
          if (statusEl) statusEl.textContent = 'Admin WhatsApp number is not configured.';
          return;
        }

        const message = buildAdminWhatsappMessage();
        const waUrl = `https://wa.me/${hotelWhatsappNo}?text=${encodeURIComponent(message)}`;
        window.open(waUrl, '_blank', 'noopener');
        if (statusEl) statusEl.textContent = 'Opening WhatsApp with your redemption details.';
      });
    }

    if (tryAgainWhatsappBtn) {
      tryAgainWhatsappBtn.addEventListener('click', () => {
        const statusEl = $('mbCouponActionStatus');
        const hotelWhatsappNo = getConfiguredHotelWhatsapp();
        if (!hotelWhatsappNo) {
          if (statusEl) statusEl.textContent = 'Hotel WhatsApp number is not configured.';
          return;
        }

        const message = buildTryAgainWhatsappMessage();
        if (!message) {
          if (statusEl) statusEl.textContent = 'Unable to prepare WhatsApp draft.';
          return;
        }

        const waUrl = `https://wa.me/${hotelWhatsappNo}?text=${encodeURIComponent(message)}`;
        window.open(waUrl, '_blank', 'noopener');
        if (statusEl) statusEl.textContent = 'Opening WhatsApp so you can ask the captain about your surprise offer.';
      });
    }
  }

  async function init() {
    if (window.NK_APP_SETTINGS_READY && typeof window.NK_APP_SETTINGS_READY.then === 'function') {
      try {
        await window.NK_APP_SETTINGS_READY;
      } catch (err) {
        // Fall back to defaults when settings cannot be loaded.
      }
    }

    const existingOverlay = $('menuBlockerOverlay');

    if (!isBlockerEnabledForCurrentPage()) {
      if (existingOverlay) {
        existingOverlay.remove();
      }
      return;
    }

    const overlay = ensureOverlayMarkup();
    if (!overlay) return;
    overlay.classList.remove('hidden');

    if (isAlreadyCompleted()) {
      unlockMenu('already-completed');
      return;
    }

    drawWheel(spinAngle);
    setCouponActionsVisible(false);
    showStep('mbStepForm');
    setupEvents();
  }

  window.addEventListener('DOMContentLoaded', init);
})();
