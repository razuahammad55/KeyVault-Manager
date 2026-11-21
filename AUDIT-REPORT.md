# KeyVault Manager — Complete Audit Report (Phase 1 & 2)

**Scope:** Full review of all 26 PHP files, admin/public CSS & JS, views, DB schema, uninstaller.
**Verification method:** Every file read line-by-line; all PHP files parse-checked (0 syntax errors); cross-references of every option key, REST route, AJAX/POST handler, and shortcode traced end-to-end. Items marked **[UNTESTED-LIVE]** require a real WordPress install to confirm at runtime.

---

## 1. Executive Summary

The plugin has a solid feature set and a decent visual direction, but the audit found **4 critical security vulnerabilities**, **1 broken core admin feature set (settings never applied to the licensing engine)**, a **completely dead customer-portal tab system**, **two dead public workflows (password reset, portal password change/logout)**, and a **wipe-on-uninstall feature that can never trigger** (the exact issue you suspected).

The codebase is very salvageable — the recommended plan below fixes everything by **modifying existing files**, not rewriting the plugin.

---

## 2. CRITICAL — Security vulnerabilities & serious functional breakage

### C1. Free license keys: checkout fulfillment trusts client-controlled GET data
`public/class-frontend.php` — Gateway Return & License Fulfillment (§2 of `handle_form_submissions()`).

After Stripe/PayPal redirect back, fulfillment reads `qty`, `amount`, `lifetime`, `mode`, `email` **directly from the URL** and issues licenses. The `session_id` / `token` param is used **only as a dedupe transient key — it is never verified against Stripe or PayPal**.

Attack: visit
`/cdf-account?cdf_ordered=1&cdf_gateway=Stripe&cdf_session_id=x&cdf_email=attacker@evil.com&cdf_qty=1000&cdf_lifetime=1`
→ instant portal account + 1000 lifetime Enterprise keys, recorded as a $0 order.

**Fix:** verify server-side — Stripe: retrieve the Checkout Session via API with the secret key and require `payment_status === 'paid'`, then read qty/mode/lifetime from the **session metadata** (not the URL); PayPal: capture the order server-side (`/v2/checkout/orders/{id}/capture`) and read the amount/custom data from the API response. URL params become display-only.

### C2. Stripe webhook accepts unsigned requests (free keys, again)
`includes/class-webhooks.php` — `handle_stripe_webhook()` performs **no signature verification**, has **no event-id idempotency** (Stripe retries → duplicate keys), returns the generated keys in the HTTP response, and never inserts an order record.

**Fix:** verify the `Stripe-Signature` header against the webhook signing secret (HMAC-SHA256, constant-time compare), dedupe on `event.id`, store an order record, don't leak keys in the response.

### C3. IDOR — any portal user can unlink any other customer's domain
`public/class-frontend.php` — `handle_unlink_action()` verifies the nonce but **never verifies that the activation belongs to the logged-in customer**. Any portal user can craft `?cdf_action=unlink_user&act_id=N&..._wpnonce=<own valid nonce>` for incremental IDs and detach competitors' sites.

**Fix:** fetch the activation joined with its license and require `license.user_id === current_user->id` (or matching `customer_email`) before deleting.

### C4. Settings save ↔ engine read mismatch — configured settings never apply
The Settings tab saves **slug-prefixed** options (`{$slug}_grace_period_days`, `{$slug}_primary_color`, `{$slug}_custom_login_url`, `{$slug}_delete_data_on_uninstall`), but the consumers read **kvm_-prefixed** ones:

| Setting | Saved as | Read by | Read as |
|---|---|---|---|
| Grace period | `cdf_grace_period_days` | REST API + licenses list | `kvm_grace_period_days` |
| Accent color | `cdf_primary_color` | Email templates | `kvm_primary_color` |
| Portal login URL | `cdf_custom_login_url` | Emails | `kvm_custom_login_url` |
| Wipe on uninstall | `cdf_delete_data_on_uninstall` | uninstall.php | `kvm_delete_data_on_uninstall` |

Consequences: the grace-period engine always uses the default 7 days; email branding never changes; **and the "Wipe Data on Uninstall" checkbox has no effect whatsoever** (exactly the suspicion in your request).

**Fix:** canonicalize all plugin options on the `kvm_` prefix (with a one-time migration copying any existing `{$slug}_*` values into `kvm_*` so current data is preserved), and centralize access through a small options helper so this can never diverge again.

### C5. Uninstaller deletes almost nothing and can't know the slug
`uninstall.php`:
- `KVM_SLUG` is **not defined** during uninstall (plugin file isn't loaded), so it falls back to a hardcoded `'cdf'` and ignores the `kvm_plugin_slug` option it never reads.
- Drops only 4 of 5 tables — **`_orders` survives**.
- Deletes only 4 of ~30 options (`kvm_secret_salt`, `kvm_price_*`, `kvm_api_settings` **with live payment secrets**, `kvm_email_templates`, `kvm_environment`, etc. all survive).
- Leaves the auto-created portal page and the `{$slug}_processed_*` transients behind.

**Fix:** full wipe — all 5 tables, every `kvm_*` option (including `kvm_api_settings`), transients, the auto-created `{$slug}-account` page, and any scheduled events. Slug resolved from `get_option('kvm_plugin_slug', 'cdf')`.

### C6. Stripe webhook + updater routes referenced but never registered
- Portal “Plugin Downloads” links to `/wp-json/cdf/v1/download-update?key=…` → **no route exists → 404**.
- `class-updater-server.php` POSTs to `/update-check` on **its own site** — route never registered, and it also matches the wrong plugin basename (`keyvault-manager/keyvault-manager.php` vs the real `cdf-manager.php`), so the whole self-updater is dead code that fires an HTTP request to itself on every update check.

**Fix options (needs your approval):** implement the routes properly (`update-check` + `download-update` gated by license key, serving the ZIP from the configured path), or remove the dead updater. See decision D3.

### C7. Password-reset & portal account workflows don't exist
- `forgot-password.php` posts `{$slug}_submit_forgot_pass` — **no handler anywhere**; no reset link can ever be sent; `reset-password.php` can never be reached with a valid token.
- Portal “Security” tab posts `{$slug}_submit_change_pass` — **no handler**.
- Sidebar “Sign Out” links to `?cdf_action=logout` — **no handler**.
- Portal “Copy Key” button — **no JS handler**.

### C8. Portal tabs are dead — undefined variables on every portal render
`client-portal.php` uses `$tab`, `$total_licenses`, `$active_activations`, `$total_slots` — **none are defined** by `render_customer_portal()`. Every render takes the fallback branch (license list), PHP 8 emits undefined-variable warnings, and Dashboard/Profile/Downloads/Security are unreachable.

### C9. Leftover debug notice exposes a gate-reset control to every user
`includes/class-keyvault-gate.php` bottom: a “Temporary test reset notice” `admin_notices` block renders a **“Reset Passkey Lock”** button on **every admin screen, for every user** (admins, editors, subscribers). `check_admin_referer()` validates only the nonce — **any logged-in user can re-lock the plugin**, and the URL pattern is exposed. This is clearly debug leftovers.

---

## 3. HIGH — Important security, reliability & performance issues

### H1. No rate limiting anywhere
- REST `/activate`, `/check-license` are brute-forceable. Token entropy is only ~32 bits: `generate_token()` embeds **base64 of the hub site URL** (public knowledge) plus 2+2 random bytes. With no throttle, key enumeration is feasible, and each attempt triggers a full-table scan (H2) + a log insert → DB-filling DoS.
- Customer portal login has no throttling/delay either.

**Fix:** ~48–64 bits of true random entropy in keys; per-IP transient-based throttling on REST and login; exponential lockout.

### H2. `find_license()` loads the entire licenses table on every API call
`SELECT * FROM {licenses}` then a PHP loop comparing sanitized keys. This runs on **every** REST validation from every client site. **Fix:** normalize the input once, compute its HMAC, and query `WHERE key_hash = %s` on an indexed column; backfill `key_hash` for legacy rows in a migration.

### H3. Plaintext customer passwords stored & displayed
- `temp_password` column stores the **current** plaintext password (even after the customer changes it — `change_password()` writes the new password to it).
- `admin/views/users.php` shows a “Password (Admin View)” column and embeds passwords in `data-pass` DOM attributes.

This is a serious liability (DB breach = every portal credential leaked). Decision D2 covers the acceptable policy; minimum fix: blank the temp password after first successful login and stop updating it on password change.

### H4. PHP sessions on the frontend
`session_start()` on every request (admin, REST, cron included). Breaks under page caching, load balancers, some hosts, and conflicts with other plugins; also session-fixation-prone and loses login when the browser closes. **Fix:** replace with a signed, expiring `kvm_session` cookie (HMAC of user-id + expiry with the existing `kvm_secret_salt`), stateless and cache-compatible.

### H5. Search in License Control Hub is broken
The search field sits inside the **POST** bulk-actions form, but the view reads `$_GET['s']` → submitting a search silently does nothing. Convert to a GET form (same pattern the Users tab already uses correctly).

### H6. Orders & Users views: no pagination, unbounded queries, N+1s
- Orders: `SELECT *` of the entire table forever; revenue computed in PHP instead of SQL `SUM()`.
- Users list: per-user `COUNT(*)` query (N+1), no pagination.
- Licenses list: one activations query **per row** (up to 101 queries/page), full `SELECT *` of all users for the dropdown.

### H7. Missing database indexes & per-request schema checks
No index on `licenses.key_hash` (lookup column), `licenses.user_id`, `licenses.status`, `orders.order_id`; `maybe_upgrade_schema()` runs `SHOW TABLES` + up to 4 `SHOW COLUMNS` **on every page load**. **Fix:** add indexes via dbDelta for new installs + one-time guarded migration for existing installs; gate the schema check behind a stored schema-version option.

### H8. `kvm_plugin_slug` option is a phantom
Every table name resolves through `get_option('kvm_plugin_slug', KVM_SLUG)`, but nothing ever sets that option, and views disagree with each other (`orders.php` uses `KVM_SLUG` directly). **Fix:** one central `KVM()->table('licenses')`-style helper resolving slug once; removes the divergence risk (and is where the white-label slug plug-in point lives).

### H9. Emails: `{license_keys}` only ever contains the first key
`generate_license_keys()` passes `$generated_keys[0]`; in “individual key per site” mode a 50-key order delivers **1 key by email** (the rest only in the DB). Also the default portal URL in emails is `site_url('/keyvault-login')` — a page that is never created (real page is `/cdf-account`), so welcome emails link to a 404 by default.

### H10. Renewal reminders: template exists, nothing ever sends it
No `wp_schedule_event` anywhere in the plugin. `user_renewal_reminder`, `admin_payment_issue`, `admin_site_activated/unlinked`, `admin_invalid_attempt`, `user_domain_updated`, `user_license_status` are configured in the UI but **never dispatched by any code path**. Either wire the events (cron for reminders; hook calls at the right moments) or the Email tab is misleading.

### H11. Logs grow unbounded; no retention
Every REST attempt (including brute-force noise) inserts a row; no cap, no purge. **Fix:** daily cron purge (e.g., keep 30 days) + don't log when rate-limited.

### H12. Frontend assets load on every page of the site
`enqueue_public_assets()` runs site-wide even when no shortcode is on the page. **Fix:** `wp_register_` always, `wp_enqueue_` only inside shortcode rendering.

### H13. `nocache_headers()` / `header()` called inside a shortcode
Headers are already sent when the shortcode renders → PHP warnings, no effect. Move to the `wp_loaded`/`send_headers` stage.

---

## 4. MEDIUM — UI/UX, code quality & consistency

1. **Inline everything:** hundreds of inline styles across all views; `<style>` blocks in `gateways.php` + the gate screen; `<script>` blocks in `orders.php`, `gateways.php`, `emails.php`. You asked for *all admin CSS in admin-style.css and all admin JS in admin-script.js* (and same for frontend) — currently violated everywhere. This will be the bulk of the UI/UX phase.
2. **Modals duplicated per row:** the license list renders 2 full modal trees **per license row** (up to 200 hidden modals in the DOM). Replace with one modal instance populated by JS from data attributes.
3. **No feedback after issuing keys:** `&created=1` is never displayed; the admin can’t see the generated key without hunting the table. Add a success panel showing the new key(s) (copy button included).
4. **Issue form gaps:** label says “Websites / Quota” but the value is used as quantity; no 6-months/3-years choices though the DB layer supports them; no “notify customer by email” choice.
5. **Hardcoded branding:** menu title “KeyVault Manager for CDF”, header “⚡ … v5.7.6 Enterprise”, gate copy — version is hardcoded and the branding isn’t driven by one place, which matters for your white-label model (see §6).
6. **Inconsistent gateways:** the Admin-issued keys email uses empty order id/amount/gateway.
7. **`json_encode($order)` inside an `onclick` attribute** (orders view) — works by luck via `esc_attr`; switch to `wp_json_encode` with hex flags in a data attribute (hardening, not an active exploit).
8. **Line endings mixed** (`\r\n` in several files) and duplicate dead JS (user-modal open functions defined twice).
9. **`current_time('timestamp')`** used in several places (discouraged pattern; timezone drift). Standardize on `current_time('mysql')` / `strtotime(current_time('mysql'))`.
10. **Redirect-after-action inside a rendered view** (clear-logs handler in `logs.php`) — runs after admin headers are sent → broken redirect + PHP warning. Move to `admin_init`.
11. **i18n:** text domain `cdf` matches `languages/cdf.pot`, but almost no strings are wrapped in `__()/esc_html__()`; `load_plugin_textdomain()` is never called. Low-priority but listed for completeness.
12. **Plugin header** lacks `Requires at least`, `Requires PHP`, `License` headers.
13. **Frontend login redirect** uses an inline `<script>window.location.href=…</script>` instead of rendering the portal or a proper redirect.
14. **`render_login_form`** short-circuits with JS; after session replacement this should just render the dashboard.

---

## 5. LOW — Polish items (folded into the UI/UX phase)

- Bulk actions loop row-by-row → single `WHERE id IN (...)` queries.
- Success/error notices: standardize on one `.kvm-notice` component with icons (currently 3 variants).
- Empty states for all tables (“No orders recorded yet.” is plain text) → designed empty states with icon + guidance.
- Pagination on Orders/Users tabs + responsive table wrappers (horizontal scroll on mobile).
- Consistent currency formatting helper (several hardcoded `$` prefixes).
- The gate screen + login/portal styling share tokens with the admin CSS (one design system).

---

## 6. White-label / business-model notes (your screenshot)

The white-label story is mostly carried by `KVM_SLUG`/`KVM_KEY_PREFIX` constants — good. Gaps found:

1. **`KVM_ACCESS_HASH` is hardcoded in the plugin source** (and duplicated as a fallback inside the gate class). Every copy you sell ships the same gate passkey; resellers can’t set their own without editing code. Recommendation: keep the constant (so a build step or `wp-config.php` override works — the gate already falls back correctly), **add a `kvm_access_hash` filter + optional `KVM_ACCESS_HASH` define documented for resellers**, and consider a build-time re-hash note. Also add a proper “Lock Hub” button in Settings to replace the debug reset URL (C9).
2. Menu/title strings and the gate copy are hardcoded “KeyVault Manager for CDF” — centralize into a `kvm_brand_name()` helper fed by one constant (e.g. `KVM_BRAND_NAME`), so each build rebrands in one line.
3. The spec’s `{$slug}_order_form` / `{$slug}_login_form` / `{$slug}_client_dashboard` / `{$slug}_customer_portal` shortcodes all exist. The Settings tab only advertises 2 of them.
4. Missing spec file: `includes/class-updater.php` (the **client-side** updater snippet that ships inside the products whose keys you sell) — tied to decision D3.

---

## 7. Proposed implementation plan (Phase 3 — pending your approval)

**Batch A — Critical security & the wipe/uninstall feature**
1. C1 server-side payment verification (Stripe session retrieve; PayPal capture), metadata as source of truth.
2. C2 webhook signature verification + idempotency + order records.
3. C3 ownership check on portal unlink.
4. C4 option-key canonicalization + migration (fixes grace period, colors, portal URL, and makes the wipe flag real).
5. C5 complete uninstall wipe + slug resolution.
6. **Your requested feature:** passkey confirmation when saving Settings with “Wipe Data on Uninstall” checked (verified against the same `KVM_ACCESS_HASH` used by the gate; wrong passkey = save rejected with an error notice).
7. C9 remove debug reset notice; add a proper “Lock Hub” control in Settings.
8. C7 + C8 implement password reset (hashed token, expiry), portal change-password, logout, portal tabs, copy-key JS.

**Batch B — Reliability & performance**
9. H1 key entropy + REST/login rate limiting; H2 indexed license lookup + backfill migration; H7 indexes + one-time schema upgrade; H6/H3 pagination + N+1 fixes + SQL revenue; H11 log retention cron; H12/H13 asset + header fixes; H8 central table/option helpers; H9 all-keys email + correct default portal URL; H5 working search.

**Batch C — UI/UX overhaul (your top priority)**
10. Consolidate every inline style into `admin-style.css` / `frontend-style.css`, every inline script into `admin-script.js` / `frontend-script.js`; single reusable modal; polished stats cards, tables, forms, pills, notices, empty states; responsive; subtle transitions; keep WP admin conventions (form-table rows, `.button` styles) where natural.

**Batch D — Sessions & passwords (decision-gated)**
11. D2 plaintext-password policy; D4 signed-cookie auth replacing PHP sessions.

**Batch E — Optional feature work (decision-gated)**
12. D3 updater/download routes + `class-updater.php` client snippet; wiring the dormant email events (H10) incl. renewal-reminder cron; “Lock Hub” control; per-build brand helper.

Everything is implemented as **edits to existing files** — no architecture rewrite; data preserved via the migration in A4/B9 (option values + `key_hash` backfill; both idempotent).

---

## 8. Decisions needed from you (D1–D5)

- **D1 — Batch scope:** approve A + B + C now (security, reliability, UI/UX)? A alone is strongly recommended as the minimum.
- **D2 — Plaintext password policy:** (a) *Recommended:* remove the admin password column, stop persisting plaintext (temp password shown once at creation, blanked after first login), admins get a “Send new temp password” action instead. (b) Keep the column but blank after first login (compromise). (c) Keep exactly as-is (not recommended; flagged as a security liability).
- **D3 — Updater/download feature:** (a) implement properly (portal Downloads works, `update-check` + key-gated `download-update` routes, `class-updater.php` snippet for client products), or (b) remove the dead updater code and the Downloads tab.
- **D4 — Session mechanism:** approve replacing PHP sessions with a signed cookie? (Recommended; touches login/logout/portal only.)
- **D5 — Key format:** approve raising license-key entropy (new keys get the new format; existing keys keep working via the hash backfill)?

## 9. What I could NOT test here (requires a live WP install)

Actual email delivery, live Stripe/PayPal sandbox charges, browser console behavior on the admin pages, and multisite (uninstall is per-site by design; sessions/cookies are per-site). Everything above marked from code reading; runtime behaviors marked **[UNTESTED-LIVE]** in the final report after implementation.
