# KeyVault Manager

Premium self-hosted SaaS licensing hub for WordPress: sell and manage license keys for your
products, run a customer portal, accept Stripe/PayPal payments, and distribute plugin updates
from your own hub — all under your own brand (white-label ready).

**Version:** 6.1.0 · **Requires:** WordPress 5.8+, PHP 7.4+ · **License:** GPL-2.0-or-later

## What it does

- **License Control Hub** — issue keys (multi-site or per-site architecture), revoke/activate,
  edit expirations, manage linked domains, bulk actions, search and pagination.
- **Orders** — Stripe Checkout + PayPal Orders API with server-side payment verification;
  sequential order IDs (`CDF-JFHV001`).
- **Customer Portal** — shortcodes render login, password recovery, dashboard, license
  portfolio, downloads, profile and security. Signed-cookie sessions (no PHP sessions).
- **Email engine** — 13 notification templates with merge tags, per-event toggles and
  recipients; renewal reminders run on a daily cron.
- **Update distribution** — `/update-check` + key-gated `/download-update` REST routes plus a
  copy-paste client updater snippet for the products you sell.
- **Grace period engine** — configurable days of operation after license expiration.
- **Audit logging** with automatic 30-day retention.

## Shortcodes

`[cdf_client_dashboard]` `[cdf_customer_portal]` `[cdf_order_form]` `[cdf_login_form]`
(shortcode names follow your `KVM_SLUG` build constant).

## REST API (namespace `cdf/v1`)

| Endpoint | Purpose |
|---|---|
| `POST/GET /activate` | Activate a key on a domain |
| `POST/GET /deactivate` | Deactivate a domain |
| `POST/GET /check-license` | Validate a key (grace-period aware) |
| `POST /webhook/stripe` | Signed Stripe webhook (signature required) |
| `POST/GET /update-check` | Client products check for updates (license key required) |
| `GET /download-update` | Key-gated ZIP download |

## White-labeling

Edit the constants at the top of `cdf-manager.php` (or define them earlier in
`wp-config.php`):

- `KVM_SLUG` — used for DB tables, shortcodes, query args, REST namespace
- `KVM_KEY_PREFIX` — license key prefix
- `KVM_BRAND_NAME` — admin UI and gate branding
- `KVM_ACCESS_HASH` — passkey hash protecting the hub (override per build, or the
  `kvm_access_hash` filter)

## Security notes

- Checkout fulfillment verifies payment server-side with Stripe/PayPal before issuing keys.
- Customer passwords are bcrypt-hashed and never stored in plaintext; admins get one-time
  temp-password flashes or an "Email New Password" action.
- REST endpoints are rate-limited (120 req / 5 min / IP); portal logins are throttled.
- "Wipe Data on Uninstall" requires the installation passkey to enable; when enabled,
  uninstalling removes all tables, options, transients and the portal page.
