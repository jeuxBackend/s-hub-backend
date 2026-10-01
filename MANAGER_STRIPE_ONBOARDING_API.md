# Manager Stripe Connect onboarding API

Base URL: `v1/manager/stripe`
Auth: `Authorization: Bearer <token>` (Sanctum). Routes live inside the `role:manager` group — only an authenticated Manager can call these.
Controller: `App\Http\Controllers\Api\Manager\StripeConnectController`

## Background

When an admin creates a manager account (`App\Actions\Admin\CreateManagerAction`), a Stripe Express account is auto-created immediately and its id is saved as `stripe_connect_account_id` on the manager's own `admins` row. If that Stripe call fails at creation time (network issue, Stripe-side error), the manager is still created, just with `stripe_connect_account_id` left `null`.

Either way, the manager completes onboarding themselves through the two endpoints below. `connect` is self-healing: if the account doesn't exist yet for any reason, it creates one — tied to that manager's own email and `admins.id` — before generating the onboarding link, so nothing is ever created twice and nothing ends up misattributed to the wrong manager.

---

## 1. POST `v1/manager/stripe/connect`

Generates (or re-generates) the onboarding link. Idempotent — if the Stripe account already exists (which it will, since admin creates it upfront), this skips account creation and just issues a fresh onboarding URL; if somehow missing, it creates the account first.

**Request body**

| Field | Type | Required | Notes |
|---|---|---|---|
| `return_url` | string (URL) | **required** | Where Stripe redirects after the manager finishes onboarding. |
| `refresh_url` | string (URL) | **required** | Where Stripe redirects if the onboarding link expires/needs to be regenerated. |

**Example**

```json
POST v1/manager/stripe/connect
{
  "return_url": "https://app.example.com/manager/stripe/return",
  "refresh_url": "https://app.example.com/manager/stripe/refresh"
}
```

**Response `200`**

```json
{
  "success": true,
  "message": "Stripe onboarding URL generated successfully.",
  "data": {
    "onboarding_url": "https://connect.stripe.com/express/onboarding/..."
  }
}
```

The manager's client opens `onboarding_url` (typically in a webview/browser) to complete Stripe's hosted onboarding form.

**Error responses**

- `422` — missing/invalid `return_url` or `refresh_url`.
- `500` — Stripe API error (e.g. invalid secret key, Stripe outage).

---

## 2. GET `v1/manager/stripe/status`

Checks/syncs onboarding completion. Safe to poll after the manager returns from the Stripe onboarding flow.

**No request body or query params.**

**Response `200`** — account exists:

```json
{
  "success": true,
  "message": "Stripe status retrieved successfully.",
  "data": {
    "stripe_connect_account_id": "acct_xxx",
    "stripe_onboarding_completed": true,
    "payouts_enabled": true,
    "charges_enabled": true
  }
}
```

**Response `200`** — no Stripe account yet (only possible if the admin-side auto-creation failed and the manager hasn't called `connect` yet):

```json
{
  "success": true,
  "message": "Stripe Connect Account not created yet.",
  "data": {
    "stripe_connect_account_id": null,
    "stripe_onboarding_completed": false
  }
}
```

---

## Typical flow

1. `POST /manager/stripe/connect` with `return_url`/`refresh_url`.
2. Redirect the manager to `data.onboarding_url` to complete Stripe's hosted onboarding form.
3. Once they're back on `return_url`, call `GET /manager/stripe/status` to confirm `stripe_onboarding_completed` has flipped to `true` (and that `charges_enabled`/`payouts_enabled` are `true`).
4. If the onboarding link expired or the manager needs to resume later, call `connect` again — it reuses the existing Stripe account and just issues a new link.
