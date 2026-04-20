# Razorpay Setup And Webhook Checklist

This checklist is aligned to the current Google Apps Script code in appscript/Code.gs.

## 1) Required Script Properties (exact keys)

In Apps Script: Project Settings -> Script properties, add these keys:

- RAZORPAY_KEY_ID
  - Value: Razorpay API Key ID from Dashboard -> Settings -> API Keys
- RAZORPAY_KEY_SECRET
  - Value: Razorpay API Key Secret (keep private)
- RAZORPAY_WEBHOOK_TOKEN
  - Value: random long token (example 48+ chars). Used by Code.gs for webhook route protection.
- RAZORPAY_WEBHOOK_SECRET
  - Value: Razorpay webhook secret. Must match the webhook secret configured in Razorpay dashboard.
- EVENT_QR_SIGNING_SECRET
  - Value: random secret for QR signing (recommended if not already set)

Recommended token/secret generation (PowerShell):

```powershell
[Convert]::ToBase64String((1..48 | ForEach-Object { Get-Random -Minimum 0 -Maximum 256 }))
```

## 2) Deploy latest Apps Script

After script property updates and Code.gs changes:

1. Deploy -> Manage deployments
2. Edit existing Web App deployment (or create new)
3. Execute as: Me
4. Who has access: Anyone (or Anyone with link, as used currently)
5. Deploy

Keep the final /exec URL and ensure it matches data-config.js.

## 3) Configure Razorpay payment keys test

Run this POST test (replace URL if needed):

```powershell
$body = @{ action='create_event_order'; eventId='paid-test-2026'; customerName='Test User'; customerEmail='test@example.com'; customerPhone='9876543210'; qty=1; attendeeNames=@('Test User') } | ConvertTo-Json -Depth 5
Invoke-RestMethod -Uri 'https://script.google.com/macros/s/AKfycbycDXiXAgZf5l-V4v8cbu8DQEPh8QFYuyYx9XogEpEtiVx6IWXe3_xHmhA-vvQYuZ2E/exec' -Method POST -ContentType 'application/json' -Body $body -TimeoutSec 60 | ConvertTo-Json -Depth 8
```

Expected:
- ok = true
- order.id present
- keyId present

If you still get RAZORPAY_CONFIG_MISSING, script properties were not set in the deployed script project.

## 4) Webhook topology (recommended)

Use this flow:

Razorpay -> Cloudflare Worker proxy -> Apps Script /exec?action=razorpay_webhook

Why:
- Apps Script web apps cannot reliably access custom headers like x-razorpay-signature.
- The proxy validates signature and forwards signature as query parameter for server-side verification in Code.gs.

## 5) Configure proxy env vars

In Cloudflare Worker environment, set:

- APPS_SCRIPT_WEBHOOK_URL
  - Example: https://script.google.com/macros/s/AKfycbycDXiXAgZf5l-V4v8cbu8DQEPh8QFYuyYx9XogEpEtiVx6IWXe3_xHmhA-vvQYuZ2E/exec
- APPS_SCRIPT_WEBHOOK_TOKEN
  - Must equal Script Property RAZORPAY_WEBHOOK_TOKEN
- RAZORPAY_WEBHOOK_SECRET
  - Must equal both Razorpay dashboard webhook secret and Script Property RAZORPAY_WEBHOOK_SECRET

## 6) Razorpay webhook settings

In Razorpay Dashboard -> Settings -> Webhooks:

- Webhook URL: your Worker URL, for example:
  - https://nk-razorpay-proxy.your-subdomain.workers.dev/
- Secret: same value as RAZORPAY_WEBHOOK_SECRET
- Enable events:
  - payment.captured (required)
  - order.paid (recommended)
  - payment.authorized (optional; currently supported by backend)

## 7) End-to-end webhook validation

1. Create a paid order from event page.
2. Complete a test payment in Razorpay.
3. Confirm Worker logs show 200 forward to Apps Script.
4. Confirm Apps Script response has:
   - ok = true
   - action = razorpay_webhook
   - status = Paid
5. Confirm EVENT_TRANSACTIONS sheet row updates:
   - Status = Paid
   - Payment ID filled
   - QR URL / QR Payload filled
   - Email Status updated

## 8) Fallback and safety checks

- Browser callback still calls confirm_event_payment.
- Webhook path is idempotent: repeated events should return idempotent true after payment is already finalized.
- If webhook reaches Apps Script without signature param, backend still verifies payment via Razorpay API before finalization.

## 9) Troubleshooting quick map

- RAZORPAY_CONFIG_MISSING
  - Missing RAZORPAY_KEY_ID or RAZORPAY_KEY_SECRET in Script Properties.
- INVALID_WEBHOOK_TOKEN
  - Proxy token and Script Property RAZORPAY_WEBHOOK_TOKEN mismatch.
- INVALID_WEBHOOK_SIGNATURE
  - Wrong RAZORPAY_WEBHOOK_SECRET in any of: Razorpay dashboard, Worker env, Script Properties.
- PAYMENT_NOT_CAPTURED
  - Payment is not captured yet or wrong event/order mapping.
- ORDER_NOT_FOUND
  - Order ID not present in EVENT_TRANSACTIONS sheet (create_event_order did not persist or wrong deployment URL).
