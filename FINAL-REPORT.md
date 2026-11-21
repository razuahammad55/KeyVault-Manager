# KeyVault Manager 6.0.0 — Final Implementation Report

**Date:** 2026-11-21 · **Branch:** `arena/73926360-keyvault-manager` · **Base:** `8138ac2` (v5.7.6)

All work was done by **modifying the existing plugin** — architecture, file structure, table
names, shortcode names, REST response shapes, and all existing data/settings are preserved.
A one-time migration (runs automatically on the first admin request after update) moves any
v5.x slug-prefixed option values into the canonical `kvm_*` keys and backfills license-key
hashes, so **no user data is lost**.

---

## 1. What was implemented

### Batch A — Critical security fixes

| # | Issue (from audit) | Fix |
|---|---|---|
| C1 | Free keys via forged return URLs | Fulfillment now **verifies payment server-side**: Stripe session is retrieved via API (`payment_status=paid` required) and qty/mode/lifetime are read from **session metadata**; PayPal orders are **captured server-side** (`/v2/checkout/orders/{id}/capture`) with amount/payer read from the API response. URL params are never trusted. v5.x-style unverified URLs are ignored. |
| C2 | Unsigned Stripe webhook | `Stripe-Signature` verified (HMAC-SHA256, timestamp freshness ±5 min, constant-time compare) against the new **Webhook Signing Secret** field in Gateways; event-id idempotency; order records inserted; keys no longer leaked in the response. |
| C3 | IDOR on portal unlink | Portal users can only unlink activations whose license belongs to them (`user_id` or `customer_email` ownership check). |
| C4 | Settings never applied | All options canonicalized to `kvm_*`; Settings tab writes what the engine reads; automatic migration copies legacy `{$slug}_*` values. |
| C5 | Wipe-on-uninstall dead | **Requested feature:** saving Settings with “Wipe Data on Uninstall” checked now **requires the installation passkey** (same hash as the gate; wrong passkey = settings rejected with an error). When enabled, `uninstall.php` deletes **everything**: all 5 tables, every `kvm_*` option (incl. gateway secrets), legacy slug-prefixed options, all transients/claims/rate-limit counters, the auto-created portal page, and cron events. |
| C7 | Missing portal handlers | Implemented: forgot-password (hashed token, 1-hour expiry, generic response), reset-password (min 8 chars + confirmation), portal change-password, logout (nonce-protected), copy-key JS. |
| C8 | Dead portal tabs | `$tab`, `$total_licenses`, `$active_activations`, `$total_slots` are now computed; Dashboard/Licenses/Downloads/Profile/Security all render. |
| C9 | Debug “Reset Passkey Lock” notice | Removed from every admin page; replaced by a proper **Lock Hub** control in Settings → Danger Zone (nonce + capability checked). Passkey hash is now overridable via the `kvm_access_hash` filter for resellers. |

### Batch B — Reliability & performance

- **H1** License keys now carry **80 bits** of true randomness (`CDF-A1B2C-D4E5F-60718-293A`); per-IP REST throttle (120 req/5 min → HTTP 429); login throttling (10/IP + 6/email per 10 min); admin-invalid-attempt alerts capped at 5/hour/IP.
- **H2** `find_license()` is now indexed (`key_hash IN (...)` + exact fallback) — no more full-table scans. One-time `key_hash` backfill migration included.
- **H3** Plaintext password storage removed; `temp_password` column dropped by migration; passwords shown **once** via flash or emailed via “Email New Password”; Users table no longer displays or embeds passwords.
- **H5** License search now uses a GET form and actually filters.
- **H6/H7** Orders/Users/Licenses/Logs: pagination + SQL aggregates (`SUM(COUNT)` in SQL, grouped activation counts, batched domain fetch); new indexes on `key_hash`, `user_id`, `status`, `customer_email`, `order_id`; schema check runs **once per DB version** instead of every request.
- **H9** `{license_keys}` now contains **all** generated keys; email portal-URL default points at the real `/{slug}-account` page.
- **H10** Email events wired: `admin_site_activated`, `admin_site_unlinked`, `user_domain_updated` (REST + portal), `admin_invalid_attempt` (REST), `admin_payment_issue` (checkout cancel), `user_license_status` (single revoke/activate), `user_renewal_reminder` + log purge via new **daily `kvm_daily_maintenance` cron** (scheduled on activation, ensured on init, unscheduled on deactivation).
- **H11** Log retention: 30 days (filter `kvm_log_retention_days`).
- **H12/H13** Frontend CSS/JS are registered globally but enqueued **only on shortcode pages**; `nocache_headers()` moved to the `wp` action.
- **H8** Central `KeyVault_Manager_DB::table()` / `::opt()` helpers — single source of truth for tables and options.

### Batch C — UI/UX overhaul (all CSS/JS consolidated)

- **Every** inline style removed from all 11 views; **every** inline `<style>`/`<script>` block removed (gate, gateways, orders, emails all moved to the asset files). Verified programmatically: zero inline style/script blocks remain, and every `kvm-*` class used in views exists in `admin-style.css` / `frontend-style.css`.
- New admin design system: tokens + accent color driven from Settings (single injected CSS variable), redesigned header with version chip, pill-style tab nav, stat cards with hover lift, redesigned tables, badges/pills, focus-ring inputs, custom toggles, **one shared modal system** (replaces up to 200 hidden per-row modal trees), flash notices with one-time key/password display + copy buttons, designed empty states, pagination, accordions in Emails, danger zone, responsive breakpoints, ESC/overlay modal closing.
- License details modal is now populated by JS from row data attributes (domains, revoke/activate/delete URLs, expiry sub-modal).
- Frontend: matching portal design system (sidebar layout → stacked on mobile), order form, auth cards, license cards with copy buttons, chips, alerts, success card.
- Removed the mixed line endings and duplicate JS blocks.

### Batch D — Sessions & passwords (approved)

- **Sessions → signed cookie** (`kvm_session`): HMAC-SHA256 over `user_id|expiry` with the per-install WP salt, HttpOnly + Secure (on SSL) + SameSite=Lax, 14-day expiry, stateless (cache/LB friendly). PHP `session_start()` fully removed.
- **Password policy**: hashes only; one-time temp passwords; “Email New Password” action; admin edit modal no longer pre-fills passwords.

### Batch E — Updater (approved)

- `update-check` route: license-gated, compares client version, returns package URL.
- `download-update` route: license-gated ZIP streaming from the configured path (absolute path or proxied URL).
- Portal **Plugin Downloads** tab now works (uses the customer’s first active key).
- New Settings fields: client product slug, client product version; copy-paste **client updater snippet** (`includes/class-updater.php` → `KeyVault_Manager_Updater::get_client_snippet()`).

---

## 2. Files changed

30 files modified + 2 added (`includes/class-updater.php`, `AUDIT-REPORT.md`), README written.
All 27 PHP files pass syntax checks (php-parser); both JS files pass `node --check`; CSS brace-balanced; class coverage verified programmatically.

---

## 3. Verified vs. not verified

**Verified in this environment:** PHP/JS syntax; option-key consistency (grep-audited: no legacy `{$slug}_*` reads remain); no `session_start`/`temp_password` storage remnants; no debug notices; no inline style/script blocks; no references to removed JS globals; nonce/capability presence on every handler; prepared statements on all dynamic SQL; JS/CSS parity with views.

**Requires a live WordPress install to verify (honest limitations):**
1. Email delivery through a real mail transport (templates/mails are coded, not sent here).
2. Live Stripe/PayPal sandbox end-to-end purchase (API calls are implemented per current Stripe/PayPal v1/v2 APIs but not executed here — no PHP runtime available in this sandbox; test one sandbox purchase for each gateway before going live).
3. Browser console behavior on admin pages (jQuery logic is standard, but click through each tab once).
4. Multisite: uninstall wipes the **current site** only (as before); sessions/cookies are per-site.

**Behavior changes to communicate to customers:**
- Logged-in portal sessions end after this upgrade (cookie auth replaces PHP sessions) — one re-login.
- Legacy v5.x checkout-return URLs that relied on unverified URL parameters will no longer fulfill; any in-flight purchase at upgrade time must be re-run.
- The Users table no longer shows stored passwords (they are no longer stored) — use “Email New Password”.
- New license keys use the higher-entropy format; all existing keys continue to work unchanged.

## 4. Suggested follow-ups (optional, not implemented without approval)

- Multi-currency support (checkout is USD today).
- Per-product ZIP/version table for hubs selling several client products.
- Two-factor login for the portal; WebAuthn for the admin gate.
- Export licenses/orders to CSV; REST admin endpoints for external dashboards.
