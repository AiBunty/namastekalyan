(function () {
  const configuredApi = (typeof window !== 'undefined' && window.APPS_SCRIPT_URL) ? String(window.APPS_SCRIPT_URL) : '';
  const WEBHOOK_URL = configuredApi ? configuredApi.split('?')[0] : 'https://script.google.com/macros/s/REPLACE_WITH_YOUR_WEBAPP_ID/exec';
  const configuredHotelWa = (typeof window !== 'undefined' && window.NK_DATA_API && window.NK_DATA_API.hotelWhatsappNo)
    ? String(window.NK_DATA_API.hotelWhatsappNo)
    : '';
  const HOTEL_WHATSAPP_NO = onlyDigits(configuredHotelWa) || resolveHotelWhatsappFromFooter() || '919371519999';
  const STAFF_SECRET_CODE = (typeof window !== 'undefined' && window.MENU_BLOCKER_STAFF_CODE)
    ? String(window.MENU_BLOCKER_STAFF_CODE)
    : 'NKSTAFF2026';
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
  const PRIZE_COLORS = ['#c57b57', '#8b5e3b', '#6f8a9b', '#8d6e63', '#b8823a', '#9c6f4b', '#b84b3b', '#a04a3a', '#5b1f1f'];

  let spinAngle = 0;
  let spinning = false;
  let targetPrize = 'Try Again';
  let leadPayload = null;
  let leadMeta = { result: '', status: '' };
  let latestCouponCode = '';

  function $(id) {
    return document.getElementById(id);
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
    const segments = PRIZES.length;
    const arc = (Math.PI * 2) / segments;

    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.save();
    ctx.translate(cx, cy);
    ctx.rotate(angle);

    for (let i = 0; i < segments; i += 1) {
      ctx.beginPath();
      ctx.moveTo(0, 0);
      ctx.fillStyle = PRIZE_COLORS[i];
      ctx.arc(0, 0, radius, i * arc, (i + 1) * arc);
      ctx.closePath();
      ctx.fill();

      ctx.save();
      ctx.rotate(i * arc + arc / 2);
      ctx.fillStyle = '#f8ead2';
      ctx.font = 'bold 13px Manrope';
      ctx.textAlign = 'right';
      ctx.fillText(PRIZES[i], radius - 16, 6);
      ctx.restore();
    }

    ctx.restore();

    ctx.beginPath();
    ctx.arc(cx, cy, 22, 0, Math.PI * 2);
    ctx.fillStyle = '#f0c48f';
    ctx.fill();
    ctx.strokeStyle = '#6a3c2c';
    ctx.lineWidth = 3;
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

  async function postLead(inputPayload) {
    const payload = {
      ...inputPayload,
      timestamp: new Date().toISOString(),
      source: 'menu-blocker-web'
    };

    if (!/^https:\/\/script\.google\.com\//.test(WEBHOOK_URL)) {
      throw new Error('Server endpoint is not configured.');
    }

    // Use simple form POST to avoid CORS preflight on local origins.
    const formBody = new URLSearchParams({ payload: JSON.stringify(payload) });
    const res = await fetch(WEBHOOK_URL, {
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
        const code = String(staffCode.value || '').trim();
        if (code && code === STAFF_SECRET_CODE) {
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
          showStep('mbStepResult');
          const result = $('mbResultText');
          const hint = $('mbResultHint');
          const couponCodeEl = $('mbCouponCode');
          if (result) result.textContent = targetPrize;
          const hasCoupon = targetPrize !== 'Try Again' && !!latestCouponCode;
          setCouponActionsVisible(hasCoupon);
          if (couponCodeEl && hasCoupon) couponCodeEl.textContent = `Coupon Code: ${latestCouponCode}`;
          if (hint) {
            if (String(leadMeta.result || '').toLowerCase() === 'duplicate') {
              hint.textContent = 'This mobile already exists. Showing your previously assigned result.';
            } else {
              hint.textContent = targetPrize === 'Try Again'
                ? 'Thanks for participating. Enjoy your meal.'
                : 'Copy your code or send full details to admin on WhatsApp for redemption.';
            }
          }

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
        unlockMenu('continue-after-spin');
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

        if (!HOTEL_WHATSAPP_NO) {
          if (statusEl) statusEl.textContent = 'Admin WhatsApp number is not configured.';
          return;
        }

        const message = buildAdminWhatsappMessage();
        const waUrl = `https://wa.me/${HOTEL_WHATSAPP_NO}?text=${encodeURIComponent(message)}`;
        window.open(waUrl, '_blank', 'noopener');
        if (statusEl) statusEl.textContent = 'Opening WhatsApp with your redemption details.';
      });
    }
  }

  function init() {
    const overlay = $('menuBlockerOverlay');
    if (!overlay) return;

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
