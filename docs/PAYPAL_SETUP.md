# PayPal sandbox setup

HostelCare bills students through PayPal invoices. Everything here uses the free **sandbox**: fake money, fake accounts.
Menu names on developer.paypal.com change now and then, so treat the wording below as a guide, not gospel.

## 1. Get sandbox credentials (about 10 minutes)

1. Sign in at https://developer.paypal.com with a normal PayPal account.
2. Open **Apps & Credentials** and make sure the **Sandbox** toggle is selected (not Live).
3. Create an app (type: Merchant). Copy its **Client ID** and **Secret**.
4. Open **Sandbox > Accounts**. PayPal creates a business (merchant) account and a personal (buyer) account for you.
   Note the **buyer's email address**. Invoices must be addressed to a sandbox buyer or they cannot be paid.
   Do not use your real email as a student email while testing.

## 2. Configure HostelCare

Credentials go in environment variables, never in a file you commit.

```bash
export HC_PAYPAL_ENABLED=1
export HC_PAYPAL_CLIENT_ID="your sandbox client id"
export HC_PAYPAL_CLIENT_SECRET="your sandbox secret"
export HC_PAYPAL_CURRENCY=USD          # sandbox is most reliable in USD
export HC_PAYPAL_MAX_CENTS=50000       # safety limit per invoice (500.00)
```

## 3. Smoke test against the real sandbox

```bash
php tools/paypal_smoke.php <sandbox-buyer-email>
```

You should see `PASS` for the token, the invoice and the read-back, plus a pay link. Open the link, sign in with the
sandbox buyer's password (shown on the Accounts page) and pay. Then run the smoke test's read-back again, or wait for the webhook below.

If it fails, the message names the step. Typical causes: live credentials used instead of sandbox ones, or a mistyped secret.

## 4. Webhook (so "paid" updates by itself)

PayPal must reach your server over the public internet, so a local site needs a tunnel.

1. Start the site: `php -S 0.0.0.0:8080 -t public`
2. Start a tunnel that gives you an https address (for example `cloudflared tunnel --url http://localhost:8080`).
3. In your PayPal app, open **Webhooks > Add webhook**. URL: `https://<your-tunnel>/paypal/webhook.php`.
   Tick these events: `INVOICING.INVOICE.PAID`, `INVOICING.INVOICE.CANCELLED`, `INVOICING.INVOICE.REFUNDED`, `INVOICING.INVOICE.UPDATED`.
4. Copy the **Webhook ID** and set `export HC_PAYPAL_WEBHOOK_ID=...`, then restart the site.
5. Pay a test invoice. Within a few seconds its status should change to **Paid**.

The tunnel address changes each time it restarts (on free plans), so update the webhook URL when it does.

## How it stays safe

- A webhook never marks anything paid by itself. It only tells HostelCare to ask PayPal for the invoice's real state.
- With `HC_PAYPAL_WEBHOOK_ID` set, PayPal's own signature check runs first.
- Each event is processed once. PayPal retries are ignored.
- Amounts are stored as whole cents and capped by `HC_PAYPAL_MAX_CENTS`.
- Creating an invoice twice after a network error returns the same invoice (the `PayPal-Request-Id` header).

## Tests without any network

```bash
php tests/run_paypal_tests.php
```
