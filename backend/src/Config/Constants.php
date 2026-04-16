<?php

declare(strict_types=1);

namespace NK\Config;

class Constants
{
    // ── Auth ───────────────────────────────────────────────────────────────────
    public const AUTH_TOKEN_HOURS             = 8;
    public const AUTH_LOCKOUT_MAX_ATTEMPTS    = 5;
    public const AUTH_LOCKOUT_MINUTES         = 15;
    public const AUTH_PASSWORD_MIN_LENGTH     = 8;
    /** Iterations for the legacy SHA-256 chaining hash (matches Apps Script). */
    public const AUTH_LEGACY_HASH_ITERATIONS  = 1200;

    // ── Roles / Permissions ────────────────────────────────────────────────────
    public const ROLES = ['admin', 'superadmin'];
    public const DEFAULT_ROLE = 'admin';

    /** Must match the permission keys used in the admin frontend. */
    public const ADMIN_PERMISSION_KEYS = [
        'dashboard',
        'cashier',
        'verification',
        'eventGuests',
        'eventScanner',
        'eventManagement',
        'menuEditor',
        'cashApprovals',
        'userManagement',
    ];

    // ── Spin & Win ─────────────────────────────────────────────────────────────
    public const SPIN_COOLDOWN_HOURS = 24;

    // ── Timezone ───────────────────────────────────────────────────────────────
    public const TIMEZONE = 'Asia/Kolkata';

    // ── Events ─────────────────────────────────────────────────────────────────
    public const EVENTS_LIST_DEFAULT_LIMIT = 6;
    public const EVENTS_LIST_MAX_LIMIT     = 20;

    // ── Menu sheets ────────────────────────────────────────────────────────────
    /** Internal sheet identifier for the food menu (AWGNK MENU). */
    public const MENU_SHEET_FOOD = 'food';
    /** Internal sheet identifier for the bar menu (BAR MENU NK). */
    public const MENU_SHEET_BAR  = 'bar';

    // ── QR / Email ─────────────────────────────────────────────────────────────
    public const EMAIL_SCAN_INTERVAL = 100;

    // ── Allowed API setting keys (managed via setApiSettings) ─────────────────
    public const MANAGED_SETTING_KEYS = [
        'RAZORPAY_KEY_ID',
        'RAZORPAY_KEY_SECRET',
        'RAZORPAY_WEBHOOK_SECRET',
        'CRM_API_TOKEN',
        'EVENT_QR_SIGNING_SECRET',
    ];
}
