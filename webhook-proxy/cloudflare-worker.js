export default {
  async fetch(request, env) {
    if (request.method === 'GET') {
      return new Response('ok', { status: 200 });
    }

    if (request.method !== 'POST') {
      return new Response('Method Not Allowed', { status: 405 });
    }

    const appsScriptUrl = String(env.APPS_SCRIPT_WEBHOOK_URL || '').trim();
    const webhookToken = String(env.APPS_SCRIPT_WEBHOOK_TOKEN || '').trim();
    const webhookSecret = String(env.RAZORPAY_WEBHOOK_SECRET || '').trim();

    if (!appsScriptUrl || !webhookToken || !webhookSecret) {
      return new Response('Missing proxy configuration', { status: 500 });
    }

    const signature = request.headers.get('x-razorpay-signature') || '';
    if (!signature) {
      return new Response('Missing Razorpay signature header', { status: 401 });
    }

    const bodyText = await request.text();
    const valid = await verifyRazorpaySignature(bodyText, signature, webhookSecret);
    if (!valid) {
      return new Response('Invalid signature', { status: 401 });
    }

    const forwardUrl = new URL(appsScriptUrl);
    forwardUrl.searchParams.set('action', 'razorpay_webhook');
    forwardUrl.searchParams.set('webhookToken', webhookToken);
    forwardUrl.searchParams.set('signature', signature);

    const upstream = await fetch(forwardUrl.toString(), {
      method: 'POST',
      headers: {
        'content-type': 'application/json; charset=utf-8'
      },
      body: bodyText
    });

    const responseText = await upstream.text();
    return new Response(responseText, {
      status: upstream.status,
      headers: {
        'content-type': upstream.headers.get('content-type') || 'application/json; charset=utf-8'
      }
    });
  }
};

async function verifyRazorpaySignature(payload, signature, secret) {
  const key = await crypto.subtle.importKey(
    'raw',
    new TextEncoder().encode(secret),
    { name: 'HMAC', hash: 'SHA-256' },
    false,
    ['sign']
  );

  const digest = await crypto.subtle.sign('HMAC', key, new TextEncoder().encode(payload || ''));
  const expected = toHex(new Uint8Array(digest));
  return timingSafeEqual(expected, signature || '');
}

function toHex(bytes) {
  let out = '';
  for (let i = 0; i < bytes.length; i += 1) {
    out += bytes[i].toString(16).padStart(2, '0');
  }
  return out;
}

function timingSafeEqual(a, b) {
  const x = String(a || '');
  const y = String(b || '');
  if (x.length !== y.length) return false;
  let mismatch = 0;
  for (let i = 0; i < x.length; i += 1) {
    mismatch |= x.charCodeAt(i) ^ y.charCodeAt(i);
  }
  return mismatch === 0;
}
