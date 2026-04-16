const revealElements = document.querySelectorAll('.reveal');
const lightbox = document.querySelector('.lightbox');
const lightboxImage = document.querySelector('.lightbox-image');
const lightboxClose = document.querySelector('.lightbox-close');
const galleryItems = document.querySelectorAll('.gallery-item');

const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
        if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            revealObserver.unobserve(entry.target);
        }
    });
}, {
    threshold: 0.2,
});

revealElements.forEach((element) => revealObserver.observe(element));

const observeDynamicReveals = (root) => {
    if (!root) return;
    const nodes = root.querySelectorAll('.reveal:not(.is-visible)');
    nodes.forEach((node) => {
        if (typeof revealObserver !== 'undefined' && revealObserver && typeof revealObserver.observe === 'function') {
            revealObserver.observe(node);
        } else {
            node.classList.add('is-visible');
        }
    });
};

const closeLightbox = () => {
    if (!lightbox || !lightboxImage) {
        return;
    }

    lightbox.classList.remove('is-open');
    lightbox.setAttribute('aria-hidden', 'true');
    lightboxImage.src = '';
};

galleryItems.forEach((item) => {
    item.addEventListener('click', () => {
        const fullImage = item.dataset.full;

        if (!fullImage) {
            return;
        }

        if (!lightbox || !lightboxImage) {
            return;
        }

        lightboxImage.src = fullImage;
        lightbox.classList.add('is-open');
        lightbox.setAttribute('aria-hidden', 'false');
    });
});

const foodGallerySlots = document.querySelectorAll('[data-food-gallery-slot]');

if (foodGallerySlots.length) {
    const foodGalleryImages = [
        {
            src: 'assets/food-gallery/food-01.jpg',
            alt: 'Signature plated dish from Namaste Kalyan food gallery',
            tag: "Chef's Pick",
            title: 'Signature Plate',
        },
        {
            src: 'assets/food-gallery/food-02.jpg',
            alt: 'Fresh plated meal from Namaste Kalyan food gallery',
            tag: 'Fresh Drop',
            title: 'Kitchen Showcase',
        },
        {
            src: 'assets/food-gallery/food-03.webp',
            alt: 'Close-up plating detail from Namaste Kalyan food gallery',
            tag: 'Table Star',
            title: 'Plating Detail',
        },
        {
            src: 'assets/food-gallery/food-04.jpg',
            alt: 'Restaurant food presentation from Namaste Kalyan food gallery',
            tag: "Chef's Pick",
            title: 'Dining Moment',
        },
        {
            src: 'assets/food-gallery/food-05.jpg',
            alt: 'Premium dish styling from Namaste Kalyan food gallery',
            tag: 'Fresh Drop',
            title: 'Premium Bite',
        },
        {
            src: 'assets/food-gallery/food-06.jpg',
            alt: 'Curated dish selection from Namaste Kalyan food gallery',
            tag: 'Table Star',
            title: 'Curated Course',
        },
        {
            src: 'assets/food-gallery/food-07.jpg',
            alt: 'Rich entree plating from Namaste Kalyan food gallery',
            tag: 'Chef Special',
            title: 'Bold Entree',
        },
        {
            src: 'assets/food-gallery/food-08.jpg',
            alt: 'Hand-finished dish detail from Namaste Kalyan food gallery',
            tag: 'Fresh Drop',
            title: 'Hand Finished',
        },
        {
            src: 'assets/food-gallery/food-09.webp',
            alt: 'Textured plate composition from Namaste Kalyan food gallery',
            tag: 'Table Star',
            title: 'Textured Plating',
        },
        {
            src: 'assets/food-gallery/food-10.jpg',
            alt: 'Celebration-style food presentation from Namaste Kalyan food gallery',
            tag: 'Chef Special',
            title: 'Celebration Plate',
        },
        {
            src: 'assets/food-gallery/food-11.jpeg',
            alt: 'Close-up gourmet serving from Namaste Kalyan food gallery',
            tag: 'Fresh Drop',
            title: 'Gourmet Finish',
        },
    ];

    const activeIndexes = foodGalleryImages.slice(0, foodGallerySlots.length).map((_, index) => index);
    let replacementCursor = foodGallerySlots.length % foodGalleryImages.length;
    let slotCursor = 0;

    const renderFoodGallerySlot = (slot, imageIndex) => {
        const image = foodGalleryImages[imageIndex];
        const slotImage = slot.querySelector('img');
        const slotTag = slot.querySelector('.food-gallery-card-tag');
        const slotTitle = slot.querySelector('.food-gallery-card-title');

        if (!image || !slotImage) {
            return;
        }

        slot.dataset.full = image.src;
        slot.setAttribute('aria-label', `Open food gallery image ${imageIndex + 1}`);
        slotImage.src = image.src;
        slotImage.alt = image.alt;

        if (slotTag) {
            slotTag.textContent = image.tag;
        }

        if (slotTitle) {
            slotTitle.textContent = image.title;
        }
    };

    const getNextFoodGalleryIndex = () => {
        for (let attempt = 0; attempt < foodGalleryImages.length; attempt += 1) {
            const candidateIndex = (replacementCursor + attempt) % foodGalleryImages.length;

            if (!activeIndexes.includes(candidateIndex)) {
                replacementCursor = (candidateIndex + 1) % foodGalleryImages.length;
                return candidateIndex;
            }
        }

        const fallbackIndex = replacementCursor;
        replacementCursor = (replacementCursor + 1) % foodGalleryImages.length;
        return fallbackIndex;
    };

    foodGallerySlots.forEach((slot, index) => {
        renderFoodGallerySlot(slot, activeIndexes[index]);
    });

    window.setInterval(() => {
        const slotIndex = slotCursor % foodGallerySlots.length;
        const slot = foodGallerySlots[slotIndex];
        const nextImageIndex = getNextFoodGalleryIndex();

        slot.classList.add('is-swapping');

        window.setTimeout(() => {
            activeIndexes[slotIndex] = nextImageIndex;
            renderFoodGallerySlot(slot, nextImageIndex);
        }, 220);

        window.setTimeout(() => {
            slot.classList.remove('is-swapping');
        }, 700);

        slotCursor += 1;
    }, 2600);
}

if (lightboxClose) {
    lightboxClose.addEventListener('click', closeLightbox);
}

if (lightbox) {
    lightbox.addEventListener('click', (event) => {
        if (event.target === lightbox) {
            closeLightbox();
        }
    });
}

document.addEventListener('keydown', (event) => {
    if (lightbox && event.key === 'Escape' && lightbox.classList.contains('is-open')) {
        closeLightbox();
    }
});

// Hero video playlist
const heroMainVideo = document.getElementById('heroMainVideo');
if (heroMainVideo) {
    const heroPlaylist = [
        'assets/01 (1).mp4',
        'assets/45.mp4',
    ];
    let heroPlaylistIndex = 0;

    heroMainVideo.addEventListener('ended', () => {
        heroPlaylistIndex = (heroPlaylistIndex + 1) % heroPlaylist.length;
        heroMainVideo.src = heroPlaylist[heroPlaylistIndex];
        heroMainVideo.play();
    });
}

// Scroll to top button
const scrollTopBtn = document.getElementById('scrollTopBtn');
if (scrollTopBtn) {
    window.addEventListener('scroll', () => {
        if (window.scrollY > 300) {
            scrollTopBtn.classList.add('visible');
        } else {
            scrollTopBtn.classList.remove('visible');
        }
    }, { passive: true });

    scrollTopBtn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

// Order Online dropdown
const orderWrap = document.querySelector('.order-online-wrap');
const orderBtn = document.querySelector('.order-online-btn');

if (orderWrap && orderBtn) {
    orderBtn.addEventListener('click', (event) => {
        event.stopPropagation();
        const isOpen = orderWrap.classList.toggle('open');
        orderBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    document.addEventListener('click', () => {
        if (orderWrap.classList.contains('open')) {
            orderWrap.classList.remove('open');
            orderBtn.setAttribute('aria-expanded', 'false');
        }
    });

    orderWrap.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            orderWrap.classList.remove('open');
            orderBtn.setAttribute('aria-expanded', 'false');
            orderBtn.focus();
        }
    });
}

// ── Events Popup + Home Events Feed ─────────────────────────────────────────
const nkEventsSection = document.querySelector('#events');
const nkEventsLiveStrip = document.querySelector('#events-live-strip');
const nkEventsLiveGrid = document.querySelector('#events-live-grid');
const nkEventsHomeLoading = document.querySelector('#events-home-loading');
const nkEventsLiveLoading = document.querySelector('#events-live-loading');
const nkEventsApiBase = (window.APPS_SCRIPT_URL || '').split('?')[0];
const NK_EVENT_TIMEZONE = 'Asia/Kolkata';
const nkEventsSessionPrefix = 'nk_event_seen_session_v1_';
const nkEventsLastShownPrefix = 'nk_event_last_shown_v1_';
const NK_EVENT_FALLBACK_IMAGE = 'assets/food-gallery/food-01.jpg';
let nkEventCarouselTimer = null;
let nkEventCountdownTimer = null;
let nkEventPopupTimer = null;
let nkEventPopupInFlight = false;
let nkLastSpinFinishedAt = 0;
let nkBackgroundWarmupStarted = false;
const NK_DISABLE_EVENT_POPUP = true;

// ── EVENT CACHING SYSTEM (Multi-layer for speed) ──────────────────────────
const NK_EVENTS_CACHE_KEY = 'nk_events_list_cache_v1';
const NK_EVENTS_CACHE_TTL = 30 * 60 * 1000; // 30 minutes
const NK_SELECTED_EVENT_KEY = 'nk_selected_event_v1';

const nkEventsCache = {
  get: () => {
    try {
      const cached = sessionStorage.getItem(NK_EVENTS_CACHE_KEY);
      if (!cached) return null;
      const { timestamp, data } = JSON.parse(cached);
      if (Date.now() - timestamp > NK_EVENTS_CACHE_TTL) {
        sessionStorage.removeItem(NK_EVENTS_CACHE_KEY);
        return null;
      }
      return data;
    } catch (err) {
      return null;
    }
  },
  set: (data) => {
    try {
      sessionStorage.setItem(NK_EVENTS_CACHE_KEY, JSON.stringify({
        timestamp: Date.now(),
        data: data
      }));
    } catch (err) {
      // Storage quota exceeded, silently fail
    }
  },
  clear: () => {
    try {
      sessionStorage.removeItem(NK_EVENTS_CACHE_KEY);
    } catch (err) {
      // Ignore
    }
  }
};

const nkSelectedEvent = {
  get: () => {
    try {
      const stored = sessionStorage.getItem(NK_SELECTED_EVENT_KEY);
      return stored ? JSON.parse(stored) : null;
    } catch (err) {
      return null;
    }
  },
  set: (eventData) => {
    try {
      sessionStorage.setItem(NK_SELECTED_EVENT_KEY, JSON.stringify(eventData));
    } catch (err) {
      // Storage quota exceeded, silently fail
    }
  },
  clear: () => {
    try {
      sessionStorage.removeItem(NK_SELECTED_EVENT_KEY);
    } catch (err) {
      // Ignore
    }
  }
};

const buildEventDetailUrl = (eventId, eventData) => {
    const id = String(eventId || '').trim();
    if (!id) return '#events';
    
    // Store full event data in session to avoid re-fetching
    if (eventData) {
      nkSelectedEvent.set(eventData);
    }
    
    return `/event.html?eventId=${encodeURIComponent(id)}`;
};

const buildEventsListingUrl = (eventId) => {
    const id = String(eventId || '').trim();
    return id ? `/events.html?eventId=${encodeURIComponent(id)}` : '/events.html';
};

const escapeHtml = (value) => String(value || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

const safeUrl = (value) => {
    const text = String(value || '').trim();
    if (!text) return '';
    if (text.startsWith('http://') || text.startsWith('https://') || text.startsWith('/')) {
        return text;
    }
    return '';
};

const normalizeEventTimeDisplayFormat = (value) => {
    const text = String(value || '').trim().toLowerCase();
    return text === '24h' ? '24h' : '12h';
};

const getEventBoardTimeParts = (parsed, timeDisplayFormat) => {
    const format = normalizeEventTimeDisplayFormat(timeDisplayFormat);

    if (format === '24h') {
        return {
            hour: parsed.toLocaleTimeString('en-GB', { hour: '2-digit', hour12: false, timeZone: NK_EVENT_TIMEZONE }),
            minute: parsed.toLocaleTimeString('en-GB', { minute: '2-digit', hour12: false, timeZone: NK_EVENT_TIMEZONE }),
            period: ''
        };
    }

    const formatted = parsed.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
        timeZone: NK_EVENT_TIMEZONE
    });
    const match = formatted.match(/^(\d{2}):(\d{2})\s*(AM|PM)$/i);

    return {
        hour: match ? match[1] : formatted,
        minute: match ? match[2] : '00',
        period: match ? String(match[3] || '').toUpperCase() : ''
    };
};

const formatEventDateDisplay = (iso, timeDisplayFormat) => {
    const value = String(iso || '').trim();
    if (!value) return '';

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return '';

    return parsed.toLocaleString('en-IN', {
        dateStyle: 'medium',
        timeStyle: 'short',
        hour12: normalizeEventTimeDisplayFormat(timeDisplayFormat) !== '24h',
        timeZone: NK_EVENT_TIMEZONE
    });
};

const buildEventTimeBoardHtml = (startAtIso, timeDisplayFormat) => {
    const raw = String(startAtIso || '').trim();
    if (!raw) return '';

    const parsed = new Date(raw);
    if (Number.isNaN(parsed.getTime())) return '';

    const boardTime = getEventBoardTimeParts(parsed, timeDisplayFormat);
    const dayName = parsed.toLocaleDateString('en-US', { weekday: 'long', timeZone: NK_EVENT_TIMEZONE }).toUpperCase();
    const dayNumber = parsed.toLocaleDateString('en-GB', { day: '2-digit', timeZone: NK_EVENT_TIMEZONE });
    const monthName = parsed.toLocaleDateString('en-US', { month: 'long', timeZone: NK_EVENT_TIMEZONE }).toUpperCase();
    const dateLabel = `${dayNumber}. ${monthName}`;
    const meridiemHtml = boardTime.period ? `<span class="event-timeboard-meridiem">${escapeHtml(boardTime.period)}</span>` : '';

    return `
        <div class="event-timeboard" aria-label="Starts ${escapeHtml(formatEventDateDisplay(parsed.toISOString(), timeDisplayFormat))}">
            <div class="event-timeboard-head">
                <span class="event-timeboard-day">${escapeHtml(dayName)}</span>
                <span class="event-timeboard-date">${escapeHtml(dateLabel)}</span>
            </div>
            <div class="event-timeboard-flip" aria-hidden="true">
                <span>${escapeHtml(boardTime.hour)}</span>
                <span>${escapeHtml(boardTime.minute)}</span>
                ${meridiemHtml}
            </div>
        </div>
    `;
};

const pad2 = (value) => String(Math.max(0, Number(value) || 0)).padStart(2, '0');

const getEventCountdownTarget = (startAtIso, endAtIso) => {
    const nowMs = Date.now();
    const start = String(startAtIso || '').trim() ? new Date(startAtIso) : null;
    const end = String(endAtIso || '').trim() ? new Date(endAtIso) : null;

    if (start && !Number.isNaN(start.getTime()) && start.getTime() > nowMs) {
        return { target: start.getTime(), heading: 'Starts In' };
    }

    if (end && !Number.isNaN(end.getTime()) && end.getTime() > nowMs) {
        return { target: end.getTime(), heading: 'Ends In' };
    }

    if (end && !Number.isNaN(end.getTime()) && end.getTime() <= nowMs) {
        return { target: 0, heading: 'Event Ended' };
    }

    return null;
};

const isLiveEventNow = (event) => {
    const nowMs = Date.now();
    const start = String(event?.startAtIso || '').trim() ? new Date(event.startAtIso) : null;
    const end = String(event?.endAtIso || '').trim() ? new Date(event.endAtIso) : null;
    const startMs = start && !Number.isNaN(start.getTime()) ? start.getTime() : 0;
    const endMs = end && !Number.isNaN(end.getTime()) ? end.getTime() : 0;

    if (startMs > 0 && endMs > 0) {
        return nowMs >= startMs && nowMs <= endMs;
    }

    if (startMs > 0 && endMs === 0) {
        return nowMs >= startMs;
    }

    return false;
};

const sortEventsByPriority = (items) => {
    const list = Array.isArray(items) ? [...items] : [];
    return list.sort((a, b) => {
        const aLive = isLiveEventNow(a) ? 1 : 0;
        const bLive = isLiveEventNow(b) ? 1 : 0;
        if (aLive !== bLive) return bLive - aLive;

        const aStart = String(a?.startAtIso || '').trim() ? new Date(a.startAtIso).getTime() : Number.MAX_SAFE_INTEGER;
        const bStart = String(b?.startAtIso || '').trim() ? new Date(b.startAtIso).getTime() : Number.MAX_SAFE_INTEGER;
        return aStart - bStart;
    });
};

const buildEventCountdownHtml = (startAtIso, endAtIso) => {
    return `
        <div class="event-mini-countdown" data-event-countdown data-start="${escapeHtml(startAtIso || '')}" data-end="${escapeHtml(endAtIso || '')}">
            <div class="event-mini-countdown-head" data-countdown-head>Starts In</div>
            <div class="event-mini-countdown-grid">
                <div class="event-mini-countdown-box">
                    <span class="event-mini-countdown-value" data-countdown-days>00</span>
                    <span class="event-mini-countdown-label">Days</span>
                </div>
                <div class="event-mini-countdown-box">
                    <span class="event-mini-countdown-value" data-countdown-hours>00</span>
                    <span class="event-mini-countdown-label">Hours</span>
                </div>
                <div class="event-mini-countdown-box">
                    <span class="event-mini-countdown-value" data-countdown-minutes>00</span>
                    <span class="event-mini-countdown-label">Minutes</span>
                </div>
                <div class="event-mini-countdown-box">
                    <span class="event-mini-countdown-value" data-countdown-seconds>00</span>
                    <span class="event-mini-countdown-label">Seconds</span>
                </div>
            </div>
        </div>
    `;
};

const updateEventCountdownNode = (node) => {
    if (!node) return;
    const startAtIso = node.getAttribute('data-start') || '';
    const endAtIso = node.getAttribute('data-end') || '';
    const targetInfo = getEventCountdownTarget(startAtIso, endAtIso);

    const headEl = node.querySelector('[data-countdown-head]');
    const daysEl = node.querySelector('[data-countdown-days]');
    const hoursEl = node.querySelector('[data-countdown-hours]');
    const minsEl = node.querySelector('[data-countdown-minutes]');
    const secsEl = node.querySelector('[data-countdown-seconds]');
    if (!headEl || !daysEl || !hoursEl || !minsEl || !secsEl) return;

    if (!targetInfo) {
        node.hidden = true;
        return;
    }

    node.hidden = false;
    headEl.textContent = targetInfo.heading;

    const diffMs = targetInfo.target > 0 ? Math.max(0, targetInfo.target - Date.now()) : 0;
    const totalSeconds = Math.floor(diffMs / 1000);
    const days = Math.floor(totalSeconds / 86400);
    const hours = Math.floor((totalSeconds % 86400) / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    daysEl.textContent = pad2(days);
    hoursEl.textContent = pad2(hours);
    minsEl.textContent = pad2(minutes);
    secsEl.textContent = pad2(seconds);
};

const refreshAllEventCountdowns = () => {
    document.querySelectorAll('[data-event-countdown]').forEach((node) => {
        updateEventCountdownNode(node);
    });
};

const ensureEventCountdownTimer = () => {
    if (nkEventCountdownTimer) {
        window.clearInterval(nkEventCountdownTimer);
        nkEventCountdownTimer = null;
    }
    refreshAllEventCountdowns();
    nkEventCountdownTimer = window.setInterval(refreshAllEventCountdowns, 1000);
};

const setEventsLoadingState = (isLoading) => {
    if (nkEventsHomeLoading) {
        nkEventsHomeLoading.hidden = !isLoading;
    }

    if (nkEventsLiveLoading) {
        nkEventsLiveLoading.hidden = !isLoading;
    }

    if (nkEventsSection) {
        nkEventsSection.setAttribute('aria-busy', isLoading ? 'true' : 'false');
    }

    if (nkEventsLiveStrip && isLoading) {
        nkEventsLiveStrip.hidden = false;
    }
};

const fetchEventsApi = async (action, params = {}) => {
    const resolvedBase = (window.NK_DATA_API && typeof window.NK_DATA_API.resolveApiBaseForAction === 'function')
        ? String(window.NK_DATA_API.resolveApiBaseForAction(action) || '').split('?')[0]
        : nkEventsApiBase;
    const fallbackBase = (window.NK_DATA_API && window.NK_DATA_API.appsScriptUrl)
        ? String(window.NK_DATA_API.appsScriptUrl).split('?')[0].trim()
        : nkEventsApiBase;

    if (!resolvedBase && !fallbackBase) return null;
    
    // Check cache for events_list requests
    if (action === 'events_list') {
      const cached = nkEventsCache.get();
      if (cached) {
        // Simulate small delay to feel natural, return from cache
        return new Promise((resolve) => {
          setTimeout(() => resolve(cached), 50);
        });
      }
    }
    
    const query = new URLSearchParams({ action });
    Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && String(value).trim() !== '') {
            query.set(key, String(value));
        }
    });

        const tryBases = [resolvedBase];
        if (fallbackBase && fallbackBase !== resolvedBase) {
            tryBases.push(fallbackBase);
        }

        for (let i = 0; i < tryBases.length; i += 1) {
            const base = tryBases[i];
            if (!base) continue;

            try {
                // Set an 8-second timeout for each API attempt.
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 8000);

                const response = await fetch(`${base}?${query.toString()}`, {
                    cache: 'no-store',
                    signal: controller.signal
                });
                clearTimeout(timeoutId);

                if (!response.ok) continue;
                const data = await response.json();
                if (!data || !data.ok) continue;

                // Cache events_list responses
                if (action === 'events_list') {
                    nkEventsCache.set(data);
                }

                return data;
            } catch (error) {
                // Try next base.
            }
        }

        return null;
};

const renderHomeEvents = (items) => {
    if (!nkEventsSection || !Array.isArray(items) || !items.length) return;
    const eventGrid = nkEventsSection.querySelector('.event-grid');
    if (!eventGrid) return;

    // Keep the curated static "Plan your events" cards unless explicitly enabled for dynamic replacement.
    if (!eventGrid.hasAttribute('data-allow-dynamic')) {
        if (nkEventsHomeLoading) {
            nkEventsHomeLoading.hidden = true;
        }
        return;
    }

    const cards = items.map((item) => {
        const title = escapeHtml(item.title || 'Upcoming Event');
        const isPaid = !!item.paymentEnabled || String(item.eventType || '').toLowerCase() === 'paid';
        const baseDescription = item.description || item.subtitle || 'Celebrate with us at Namaste Kalyan.';
        const description = escapeHtml(isPaid ? `${baseDescription} No refund once pass is purchased.` : baseDescription);
        const imageUrl = safeUrl(item.imageUrl) || NK_EVENT_FALLBACK_IMAGE;
        const imageAttr = escapeHtml(imageUrl);
        const fallbackAttr = escapeHtml(NK_EVENT_FALLBACK_IMAGE);
        const badge = escapeHtml(item.badgeText || (isPaid ? 'Paid Pass' : 'Upcoming'));
        const ctaUrl = buildEventDetailUrl(item.id, item);
        const ctaLabel = 'Choose Event';
        const timeBoard = buildEventTimeBoardHtml(item.startAtIso, item.timeDisplayFormat);
        const countdown = buildEventCountdownHtml(item.startAtIso, item.endAtIso);

        const cardInner = `
            <img src="${imageAttr}" alt="${title}" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="if(!this.dataset.fallback){this.dataset.fallback='1';this.src='${fallbackAttr}';}else{this.onerror=null;}">
            <div class="event-card-copy">
                <span class="event-card-badge">${badge}</span>
                <h3>${title}</h3>
                ${timeBoard}
                ${countdown}
                <p>${description}</p>
                <span class="event-card-cta-inline">${ctaLabel}</span>
            </div>
        `;

        return `<a class="event-card reveal" href="${ctaUrl}">${cardInner}</a>`;
    }).join('');

    eventGrid.innerHTML = cards;
    observeDynamicReveals(eventGrid);
    ensureEventCountdownTimer();
    if (nkEventsHomeLoading) {
        nkEventsHomeLoading.hidden = true;
    }
};

const renderLiveEventsStrip = (items) => {
    if (!nkEventsLiveStrip || !nkEventsLiveGrid) return;

    if (!Array.isArray(items) || !items.length) {
        nkEventsLiveGrid.innerHTML = '';
        nkEventsLiveStrip.hidden = true;
        return;
    }

    const orderedItems = sortEventsByPriority(items);

    const cards = orderedItems.map((item) => {
        const title = escapeHtml(item.title || 'Upcoming Event');
        const isPaid = !!item.paymentEnabled || String(item.eventType || '').toLowerCase() === 'paid';
        const baseSubtitle = item.subtitle || item.description || 'Celebrate with us at Namaste Kalyan.';
        const subtitle = escapeHtml(isPaid ? `${baseSubtitle} No refund once pass is purchased.` : baseSubtitle);
        const imageUrl = safeUrl(item.imageUrl) || NK_EVENT_FALLBACK_IMAGE;
        const imageAttr = escapeHtml(imageUrl);
        const fallbackAttr = escapeHtml(NK_EVENT_FALLBACK_IMAGE);
        const badge = escapeHtml(item.badgeText || (isPaid ? 'Paid Pass' : 'Live Event'));
        const ctaUrl = buildEventDetailUrl(item.id, item);
        const ctaLabel = 'Choose Event';
        const timeBoard = buildEventTimeBoardHtml(item.startAtIso, item.timeDisplayFormat);
        const countdown = buildEventCountdownHtml(item.startAtIso, item.endAtIso);
        const price = Number(item.ticketPrice || item.price || 0);
        const priceHtml = isPaid && price > 0 ? `<span class="events-live-price">₹${Math.floor(price)}</span>` : '';

        return `
            <a class="events-live-card" href="${ctaUrl}">
                <img src="${imageAttr}" alt="${title}" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="if(!this.dataset.fallback){this.dataset.fallback='1';this.src='${fallbackAttr}';}else{this.onerror=null;}">
                <div class="events-live-copy">
                    <span class="events-live-badge">${isLiveEventNow(item) ? 'Live Now' : badge}</span>
                    <h3>${title}</h3>
                    ${timeBoard}
                    ${priceHtml}
                    ${countdown}
                    <p>${subtitle}</p>
                    <span class="events-live-cta-inline">${ctaLabel}</span>
                </div>
            </a>
        `;
    }).join('');

    nkEventsLiveGrid.innerHTML = cards;
    if (nkEventsLiveLoading) {
        nkEventsLiveLoading.hidden = true;
    }
    nkEventsLiveStrip.hidden = false;
    ensureEventCountdownTimer();
    
    // Initialize carousel if more than 2 events
    initLiveEventsCarousel(orderedItems.length);
};

const initLiveEventsCarousel = (itemCount) => {
    if (!nkEventsLiveGrid) return;
    
    // Remove any existing carousel wrapper
    const existing = nkEventsLiveGrid.parentElement.querySelector('.carousel-wrapper');
    if (existing) existing.remove();
    
    if (itemCount <= 2) {
        // Show grid normally, no carousel needed
        nkEventsLiveGrid.classList.remove('carousel-viewport');
        return;
    }
    
    // Create carousel wrapper
    const wrapper = document.createElement('div');
    wrapper.className = 'carousel-wrapper';
    
    const carouselViewport = document.createElement('div');
    carouselViewport.className = 'carousel-viewport';
    
    // Move grid into carousel
    nkEventsLiveGrid.parentElement.insertBefore(wrapper, nkEventsLiveGrid);
    wrapper.appendChild(carouselViewport);
    carouselViewport.appendChild(nkEventsLiveGrid);
    
    // Convert grid to carousel layout
    nkEventsLiveGrid.classList.add('carousel-grid');
    
    // Add nav buttons
    const prevBtn = document.createElement('button');
    prevBtn.className = 'carousel-nav carousel-nav-prev';
    prevBtn.type = 'button';
    prevBtn.setAttribute('aria-label', 'Previous event');
    prevBtn.innerHTML = '&#10094;';
    
    const nextBtn = document.createElement('button');
    nextBtn.className = 'carousel-nav carousel-nav-next';
    nextBtn.type = 'button';
    nextBtn.setAttribute('aria-label', 'Next event');
    nextBtn.innerHTML = '&#10095;';
    
    wrapper.appendChild(prevBtn);
    wrapper.appendChild(nextBtn);
    
    // Set up carousel card dimensions and snap behavior
    const updateCarouselLayout = () => {
        const viewportWidth = carouselViewport.clientWidth;
        const isMobile = window.innerWidth < 768;
        const cardsPerView = isMobile ? 1 : 2;
        const cardWidth = viewportWidth / cardsPerView;
        
        // Apply card width via CSS variable
        wrapper.style.setProperty('--carousel-card-width', `${cardWidth}px`);
    };
    
    // Update layout on init and resize
    updateCarouselLayout();
    window.addEventListener('resize', updateCarouselLayout);
    
    // Scroll to specific card index
    const scrollToCard = (index) => {
        const isMobile = window.innerWidth < 768;
        const cardsPerView = isMobile ? 1 : 2;
        const scrollLeft = Math.max(0, (index - 0) * (carouselViewport.clientWidth / cardsPerView));
        carouselViewport.scrollLeft = scrollLeft;
    };
    
    // Button handlers
    let currentIndex = 0;
    prevBtn.addEventListener('click', () => {
        currentIndex = Math.max(0, currentIndex - 1);
        scrollToCard(currentIndex);
    });
    
    nextBtn.addEventListener('click', () => {
        const isMobile = window.innerWidth < 768;
        const cardsPerView = isMobile ? 1 : 2;
        const maxIndex = Math.max(0, itemCount - cardsPerView);
        currentIndex = Math.min(maxIndex, currentIndex + 1);
        scrollToCard(currentIndex);
    });
    
    // Track scroll position
    carouselViewport.addEventListener('scroll', () => {
        const isMobile = window.innerWidth < 768;
        const cardsPerView = isMobile ? 1 : 2;
        const cardWidth = carouselViewport.clientWidth / cardsPerView;
        currentIndex = Math.round(carouselViewport.scrollLeft / cardWidth);
    });
};

const ensureEventPopupShell = () => {
    let modal = document.getElementById('nkEventPopup');
    if (modal) return modal;

    modal = document.createElement('div');
    modal.id = 'nkEventPopup';
    modal.className = 'nk-event-popup';
    modal.setAttribute('aria-hidden', 'true');
    modal.innerHTML = `
        <div class="nk-event-popup-dialog" role="dialog" aria-modal="true" aria-labelledby="nkEventTitle">
            <button id="nkEventClose" class="nk-event-close" type="button" aria-label="Close event popup">&times;</button>
            <button id="nkEventPrev" class="nk-event-nav nk-event-nav-prev" type="button" aria-label="Previous event">&#10094;</button>
            <button id="nkEventNext" class="nk-event-nav nk-event-nav-next" type="button" aria-label="Next event">&#10095;</button>
            <div id="nkEventMedia" class="nk-event-media"></div>
            <div class="nk-event-body">
                <p id="nkEventBadge" class="nk-event-badge"></p>
                <h3 id="nkEventTitle" class="nk-event-title"></h3>
                <p id="nkEventSubtitle" class="nk-event-subtitle"></p>
                <p id="nkEventDescription" class="nk-event-description"></p>
                <a id="nkEventCta" class="nk-event-cta" href="#" target="_blank" rel="noopener noreferrer">I'm Interested</a>
                <div class="nk-event-carousel-meta">
                    <span id="nkEventCounter" class="nk-event-counter"></span>
                    <div id="nkEventDots" class="nk-event-dots"></div>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);

    const closeModal = () => {
        if (nkEventCarouselTimer) {
            window.clearInterval(nkEventCarouselTimer);
            nkEventCarouselTimer = null;
        }
        modal.classList.remove('active');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    };

    const closeBtn = modal.querySelector('#nkEventClose');
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', (event) => {
        if (event.target === modal) closeModal();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('active')) {
            closeModal();
        }
    });

    return modal;
};

const getCooldownHours = (event) => {
    const cooldown = Number(event.popupCooldownHours || 0);
    if (Number.isFinite(cooldown) && cooldown > 0) return cooldown;
    const fallbackDelay = Number(event.popupDelayHours || 0);
    if (Number.isFinite(fallbackDelay) && fallbackDelay > 0) return fallbackDelay;
    return 0;
};

const canShowEventPopup = (event) => {
    if (!event || !event.id) return false;

    if (event.showOncePerSession) {
        const sessionKey = `${nkEventsSessionPrefix}${event.id}`;
        if (sessionStorage.getItem(sessionKey) === '1') return false;
    }

    const cooldownHours = getCooldownHours(event);
    if (cooldownHours > 0) {
        const localKey = `${nkEventsLastShownPrefix}${event.id}`;
        const lastShownAt = Number(localStorage.getItem(localKey) || 0);
        if (Number.isFinite(lastShownAt) && lastShownAt > 0) {
            const elapsedHours = (Date.now() - lastShownAt) / (1000 * 60 * 60);
            if (elapsedHours < cooldownHours) return false;
        }
    }

    return true;
};

const markEventPopupShown = (event) => {
    if (!event || !event.id) return;
    localStorage.setItem(`${nkEventsLastShownPrefix}${event.id}`, String(Date.now()));
    if (event.showOncePerSession) {
        sessionStorage.setItem(`${nkEventsSessionPrefix}${event.id}`, '1');
    }
};

const markEventPopupBatchShown = (events) => {
    (Array.isArray(events) ? events : []).forEach((event) => {
        markEventPopupShown(event);
    });
};

const showEventPopupCarousel = (events) => {
    if (!Array.isArray(events) || !events.length) return;
    const modal = ensureEventPopupShell();
    if (!modal) return;

    const badgeEl = modal.querySelector('#nkEventBadge');
    const titleEl = modal.querySelector('#nkEventTitle');
    const subtitleEl = modal.querySelector('#nkEventSubtitle');
    const descriptionEl = modal.querySelector('#nkEventDescription');
    const ctaEl = modal.querySelector('#nkEventCta');
    const mediaEl = modal.querySelector('#nkEventMedia');
    const prevBtn = modal.querySelector('#nkEventPrev');
    const nextBtn = modal.querySelector('#nkEventNext');
    const dotsEl = modal.querySelector('#nkEventDots');
    const counterEl = modal.querySelector('#nkEventCounter');

    let index = 0;

    const render = () => {
        const event = events[index];
        const title = String(event.title || 'Upcoming Event');
        const subtitle = String(event.subtitle || 'Celebrate at Namaste Kalyan');
        const description = String(event.description || 'Join us for a special event at Namaste Kalyan.');
        const isPaid = !!event.paymentEnabled || String(event.eventType || '').toLowerCase() === 'paid';
        const ctaText = String(event.ctaText || (isPaid ? 'Buy Pass' : 'Register'));
        const ctaUrl = buildEventDetailUrl(event.id, event);
        const badge = String(event.badgeText || '').trim();

        if (badgeEl) {
            badgeEl.textContent = badge;
            badgeEl.style.display = badge ? 'inline-flex' : 'none';
        }
        if (titleEl) titleEl.textContent = title;
        if (subtitleEl) subtitleEl.textContent = subtitle;
        if (descriptionEl) descriptionEl.textContent = description;
        if (ctaEl) {
            ctaEl.textContent = ctaText;
            ctaEl.href = ctaUrl;
            ctaEl.target = '_self';
        }

        if (mediaEl) {
            const useVideo = !!event.showVideo && !!safeUrl(event.videoUrl);
            if (useVideo) {
                mediaEl.innerHTML = `<video src="${safeUrl(event.videoUrl)}" autoplay muted loop playsinline></video>`;
            } else {
                const imageUrl = safeUrl(event.imageUrl) || NK_EVENT_FALLBACK_IMAGE;
                mediaEl.innerHTML = `<img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(title)}" loading="eager" decoding="async" referrerpolicy="no-referrer" onerror="if(!this.dataset.fallback){this.dataset.fallback='1';this.src='${escapeHtml(NK_EVENT_FALLBACK_IMAGE)}';}else{this.onerror=null;}">`;
            }
        }

        if (counterEl) counterEl.textContent = `${index + 1} / ${events.length}`;
        if (dotsEl) {
            dotsEl.innerHTML = events.map((_, i) => `
                <button type="button" class="nk-event-dot ${i === index ? 'active' : ''}" data-index="${i}" aria-label="View event ${i + 1}"></button>
            `).join('');

            dotsEl.querySelectorAll('.nk-event-dot').forEach((dot) => {
                dot.addEventListener('click', () => {
                    index = Number(dot.dataset.index || 0);
                    render();
                });
            });
        }
    };

    const next = () => {
        index = (index + 1) % events.length;
        render();
    };
    const prev = () => {
        index = (index - 1 + events.length) % events.length;
        render();
    };

    if (prevBtn) prevBtn.onclick = prev;
    if (nextBtn) nextBtn.onclick = next;

    if (nkEventCarouselTimer) {
        window.clearInterval(nkEventCarouselTimer);
        nkEventCarouselTimer = null;
    }
    nkEventCarouselTimer = window.setInterval(next, 6000);

    render();

    modal.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    markEventPopupBatchShown(events);
};

const isMenuBlockerActive = () => {
        const overlay = document.getElementById('menuBlockerOverlay');
        if (!overlay) return false;
        return !overlay.classList.contains('hidden');
};

const isEventPopupOpen = () => {
        const modal = document.getElementById('nkEventPopup');
        return !!(modal && modal.classList.contains('active'));
};

const preloadImageUrl = (url) => new Promise((resolve) => {
    const src = safeUrl(url);
    if (!src) {
        resolve(false);
        return;
    }

    const img = new Image();
    img.decoding = 'async';
    img.referrerPolicy = 'no-referrer';
    img.onload = () => resolve(true);
    img.onerror = () => resolve(false);
    img.src = src;
});

const preloadImageQueue = async (urls) => {
    const unique = Array.from(new Set((Array.isArray(urls) ? urls : []).map((u) => String(u || '').trim()).filter(Boolean)));
    if (!unique.length) return;

    const chunkSize = 4;
    for (let i = 0; i < unique.length; i += chunkSize) {
        const chunk = unique.slice(i, i + chunkSize);
        await Promise.all(chunk.map((src) => preloadImageUrl(src)));
        await new Promise((resolve) => window.setTimeout(resolve, 40));
    }
};

const collectStaticImageUrls = () => {
    const staticUrls = [];
    document.querySelectorAll('#events .event-grid img, #events-live-strip img').forEach((img) => {
        const src = String(img.getAttribute('src') || '').trim();
        if (src) staticUrls.push(src);
    });
    staticUrls.push(NK_EVENT_FALLBACK_IMAGE);
    return staticUrls;
};

const startBackgroundImageWarmup = () => {
    if (nkBackgroundWarmupStarted) return;
    nkBackgroundWarmupStarted = true;

    const runWarmup = async () => {
        const urls = collectStaticImageUrls();

        try {
            const response = await fetchEventsApi('events_list', { limit: 24 });
            if (response && response.ok && Array.isArray(response.items)) {
                response.items.forEach((item) => {
                    const imageUrl = safeUrl(item && item.imageUrl ? item.imageUrl : '');
                    if (imageUrl) urls.push(imageUrl);
                });
            }
        } catch (err) {
            // Ignore warmup fetch issues.
        }

        await preloadImageQueue(urls);
    };

    if (typeof window.requestIdleCallback === 'function') {
        window.requestIdleCallback(() => {
            runWarmup();
        }, { timeout: 1600 });
        return;
    }

    window.setTimeout(() => {
        runWarmup();
    }, 250);
};

const scheduleEventPopup = (delayMs, reason, options = {}) => {
        if (nkEventPopupTimer) {
                window.clearTimeout(nkEventPopupTimer);
                nkEventPopupTimer = null;
        }
        nkEventPopupTimer = window.setTimeout(() => {
        maybeShowEventPopup({ reason, ...options });
        }, Math.max(0, Number(delayMs) || 0));
};

const selectPopupCandidates = (items, options = {}) => {
    const list = Array.isArray(items) ? items : [];
    const force = !!options.force;
    const liveOnly = !!options.liveOnly;
    const forceLive = !!options.forceLive;

    const liveEvents = sortEventsByPriority(list.filter((item) => isLiveEventNow(item)));
    const popupEnabled = sortEventsByPriority(list.filter((item) => !!item && !!item.popupEnabled));

    if (liveOnly) {
        return forceLive ? liveEvents : liveEvents.filter((item) => canShowEventPopup(item));
    }

    if (forceLive && liveEvents.length) {
        return liveEvents;
    }

    const base = sortEventsByPriority(list.filter((item) => {
        if (!item) return false;
        return !!item.popupEnabled || isLiveEventNow(item);
    }));

    if (force) return base;
    return base.filter((item) => canShowEventPopup(item));
};

const maybeShowEventPopup = async (options = {}) => {
    if (NK_DISABLE_EVENT_POPUP) {
        if (nkEventPopupTimer) {
            window.clearTimeout(nkEventPopupTimer);
            nkEventPopupTimer = null;
        }
        document.body.style.overflow = '';
        return;
    }

        if (nkEventPopupInFlight) return;
        if (isMenuBlockerActive() || isEventPopupOpen()) return;

    // Safety: if popup is not open, ensure scrolling is not accidentally locked.
    if (!isEventPopupOpen()) {
        document.body.style.overflow = '';
    }

        nkEventPopupInFlight = true;
        let response = null;

        try {
            response = await Promise.race([
                fetchEventsApi('events_list', { limit: 20 }),
                new Promise((resolve) => window.setTimeout(() => resolve(null), 5000))
            ]);
        } catch (err) {
            response = null;
        } finally {
            nkEventPopupInFlight = false;
        }

        if (!response || !response.ok || !Array.isArray(response.items) || !response.items.length) return;

    const eligible = selectPopupCandidates(response.items, options);
    if (!eligible.length) return;

    showEventPopupCarousel(eligible);
};

const loadHomeEvents = async () => {
    setEventsLoadingState(true);
    try {
        const response = await Promise.race([
            fetchEventsApi('events_list', { limit: 20 }),
            new Promise((_, reject) => setTimeout(() => reject(new Error('TIMEOUT')), 7000))
        ]);
        
        if (!response || !response.ok || !Array.isArray(response.items)) {
            setEventsLoadingState(false);
            renderLiveEventsStrip([]);
            return;
        }
        const ordered = sortEventsByPriority(response.items);
        // Priority render: live strip first for faster visible event feedback.
        renderLiveEventsStrip(ordered);
        renderHomeEvents(ordered);
    } catch (err) {
        // Silently fail - events section just won't show
        renderLiveEventsStrip([]);
    } finally {
        setEventsLoadingState(false);
    }
};

const initEventsExperience = () => {
    if (!nkEventsSection || !nkEventsApiBase) return;

    // On landing page load, move user directly to live events section.
    const landingTarget = nkEventsLiveStrip || nkEventsSection;
    if (landingTarget) {
        window.setTimeout(() => {
            landingTarget.scrollIntoView({ behavior: 'auto', block: 'start' });
        }, 100);
    }

    loadHomeEvents();

    if (isMenuBlockerActive()) {
        startBackgroundImageWarmup();
    }

    document.addEventListener('nk:spin-finished', () => {
        nkLastSpinFinishedAt = Date.now();
        if (isMenuBlockerActive()) {
            startBackgroundImageWarmup();
        }
    });

    document.addEventListener('nk:menu-blocker-closed', (event) => {
        if (!isMenuBlockerActive()) {
            startBackgroundImageWarmup();
        }
    });
};

window.NKEvents = {
    refreshHomeEvents: loadHomeEvents,
    maybeShowPopup: function () {
        // Intentionally disabled as per UX requirement.
    }
};

initEventsExperience();
