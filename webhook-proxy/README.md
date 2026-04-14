# Razorpay Webhook Proxy (Cloudflare Worker)

This proxy validates `x-razorpay-signature` and forwards the raw webhook body to your Apps Script endpoint.

## Files

- cloudflare-worker.js

## Required environment variables

- APPS_SCRIPT_WEBHOOK_URL
- APPS_SCRIPT_WEBHOOK_TOKEN
- RAZORPAY_WEBHOOK_SECRET

## Quick deploy steps

1. Install Wrangler:

```bash
npm i -g wrangler
```

2. Create Worker project folder and copy cloudflare-worker.js.

3. Login and create worker:

```bash
wrangler login
wrangler init nk-razorpay-proxy
```

4. Set secrets:

```bash
wrangler secret put APPS_SCRIPT_WEBHOOK_URL
wrangler secret put APPS_SCRIPT_WEBHOOK_TOKEN
wrangler secret put RAZORPAY_WEBHOOK_SECRET
```

5. Deploy:

```bash
wrangler deploy
```

6. Use deployed Worker URL as Razorpay webhook URL.

## Forward behavior

Proxy forwards request to:

`<APPS_SCRIPT_WEBHOOK_URL>?action=razorpay_webhook&webhookToken=<token>&signature=<x-razorpay-signature>`

with raw original JSON body.
