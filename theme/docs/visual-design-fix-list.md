# Visual design fix list — production audit (Sep 2026)

Prioritized fixes from the [madbaits.com](https://madbaits.com) visual audit, mapped to theme/plugin files.

**Legend:** P0 = do first · P1 = high · P2 = medium · P3 = polish

**Implementation status (Sep 2026):** Code fixes below are implemented in this repo. Product photography still requires WP admin uploads (featured images per SKU).

---

## P0 — Product photography (biggest visual gap)

**Problem:** Many cards and PDPs show the branded logo placeholder instead of real product shots (BBB Paste, bundles, tackle).

| File | Role |
|------|------|
| `wp-content/themes/mad-baits/inc/product-card-images.php` | Placeholder logic (`mad_baits_get_branded_placeholder_image_url`, category/range fallback) |
| `wp-content/themes/mad-baits/assets/css/main.css` | `.mad-product-card__image--placeholder` styling |
| WooCommerce admin | Upload featured images per product; regen thumbnails after `mad_product_card` size |

**Fix approach (content + code):**
1. **Content:** Bulk-upload featured images for top sellers, all bundle deals, and tackle SKUs without photos.
2. **Code (optional):** Improve `mad_baits_get_product_fallback_image_id()` to prefer range hero images from theme assets when no product photo exists.
3. Run thumbnail regen (`mad_product_card` 800×800) after uploads.

**Not a theme bug** — placeholder system works; catalogue is under-photographed.

---

## P0 — Bundle PDP: hide internal “Additional information” tab

**Problem:** Tab shows raw attribute labels (`Boilie 1`, `Hook bait 1`) as comma-separated walls of text. Customers already configure choices in the builder.

| File | Role |
|------|------|
| `wp-content/plugins/mad-baits-bundle-builder/includes/class-mbbb-frontend.php` | Front-end hooks (add tab filter here) |
| `wp-content/plugins/mad-baits-bundle-builder/assets/css/mbbb-frontend.css` | Tab layout (~L2208+) |

**Status:** Done — `class-mbbb-frontend.php` → `hide_additional_information_tab()`.

---

## P1 — Branded 404 page

**Problem:** Missing URLs render plain “No content found.” on black — off-brand.

| File | Role |
|------|------|
| `wp-content/themes/mad-baits/index.php` | Fallback template (L26: message only) |
| **New:** `wp-content/themes/mad-baits/404.php` | Dedicated 404 (does not exist today) |

**Status:** Done — `404.php` created.

**Fix approach (reference):** Create `404.php` matching shop hero pattern:
- Mad Baits eyebrow + “Page not found”
- Short copy + CTAs: Shop, Bundles & Deals, AI Bait Finder
- Reuse `.mad-button`, breadcrumb pills from `template-shop-archive.php`

---

## P1 — First-visit mobile modals stack

**Problem:** Install App sheet + Enable alerts prompt both appear early and block taps.

| File | Role |
|------|------|
| `wp-content/themes/mad-baits/assets/js/app-enhancements.js` | Install sheet: shows on page 2 or after 20s (L316–321) |
| `wp-content/plugins/mad-baits-push-notifications/assets/js/mad-push.js` | Alerts prompt: `initFrontendPrompt()` (L395+), 7-day dismiss TTL |

**Status:** Done — `app-enhancements.js` (3 page views / 30s, skip cart/checkout, `mad:install-sheet-closed` event) + `mad-push.js` (waits for install sheet).

---

## P1 — Hide app version stamp from customers

**Problem:** `2026.08.04.001` visible on mobile bottom nav — looks like a debug artifact.

| File | Role |
|------|------|
| `wp-content/themes/mad-baits/inc/pwa-version.php` | `mad_baits_render_app_version_label()` (L275+) |
| `wp-content/themes/mad-baits/inc/pwa-app.php` | Renders in bottom nav (L209–210) |
| `wp-content/themes/mad-baits/footer.php` | Renders in footer (L203–204) |
| `wp-content/themes/mad-baits/assets/css/scoped/mobile-app.css` | Shows in PWA/standalone only (L510–513) |

**Status:** Done — `mad_baits_should_show_app_version_label()` in `pwa-version.php` (admin + `WP_DEBUG` only).

---

## P2 — Empty cart visual polish

**Problem:** Generic Woo empty state (emoji + “Your cart is currently empty!”) despite custom CTA panel existing.

| File | Role |
|------|------|
| `wp-content/themes/mad-baits/functions.php` | `mad_baits_render_empty_cart_cta()` (L4516+) — custom panel exists |
| `wp-content/themes/mad-baits/assets/css/main.css` | `.mad-empty-cart-panel` (L7294+) |

**Fix approach:**
1. Hide default Woo empty message via `woocommerce_cart_is_empty` priority or CSS.
2. Style empty cart hero to match shop page (breadcrumb pills, dark card).
3. Remove/replace emoji icon with Mad Baits illustration or logo mark.

---

## P2 — Copy / naming inconsistencies (product content)

**Problem:** “Nutz Plus18mm” (missing space), “Abso” in range list, ALL CAPS tackle names.

| Location | Fix |
|----------|-----|
| WooCommerce product descriptions | Edit “Mix & Match 5kg deal” range line: `Abso` → `ASBO` |
| Product attributes / variation labels | Fix spacing in variation names (`Nutz Plus 18mm`) |
| Tackle products | Consider title case in admin for new SKUs; bulk rename optional |

No code change required — content pass in WP admin.

---

## P2 — Bundle PDP desktop: white block / layout glitch

**Problem:** Occasional empty white rectangle above bundle tags on desktop PDP (screenshot during audit).

| File | Role |
|------|------|
| `wp-content/themes/mad-baits/assets/css/scoped/product-pdp-layout-fixes.css` | PDP grid layout |
| `wp-content/plugins/mad-baits-bundle-builder/assets/css/mbbb-frontend.css` | Bundle builder slots |
| `wp-content/plugins/mad-baits-bundle-builder/assets/css/mbbb-bundle-desktop.css` | Desktop bundle layout |

**Fix approach:**
1. Reproduce on production with DevTools — likely unstyled PayPal/express checkout iframe or slot accordion flash before JS init.
2. Add `min-height: 0` / background on `.mbbb-builder` container; hide express checkout until loaded.
3. Add to visual QA checklist (`docs/visual-qa-checklist.md` § Bundle PDP).

---

## P2 — Shop card CTA clutter

**Problem:** Bundle listing cards show both “Build Bundle” and “View Basket” — visually noisy.

| File | Role |
|------|------|
| `wp-content/themes/mad-baits/inc/shop-archive-layout.php` | Product card markup |
| `wp-content/themes/mad-baits/assets/css/scoped/shop-archive-grid.css` | Card layout |

**Fix approach:** Show “View Basket” only when that product is already in cart; otherwise single primary “Build Bundle” CTA.

---

## P3 — reCAPTCHA badge on cart

**Problem:** Google reCAPTCHA badge visible bottom-right on cart page.

| File | Role |
|------|------|
| Plugin providing reCAPTCHA (Contact Form 7 / Woo / custom) | Identify via `grep recaptcha` on production |

**Fix approach:** CSS hide on non-form pages, or load reCAPTCHA only on pages with forms.

---

## P3 — Tackle vs bait card consistency

**Problem:** Tackle cards feel more “default WooCommerce” than bait cards.

| File | Role |
|------|------|
| `wp-content/themes/mad-baits/assets/css/scoped/shop-archive-grid.css` | Grid + card styles |
| `wp-content/themes/mad-baits/assets/css/scoped/product-card-images.css` | Image treatment |

**Fix approach:** Ensure “Choose Options” buttons use same `.mad-button` styling as “Add to cart”; unify card min-heights.

---

## Production vs app-staging (design-relevant)

| Area | Production | App-staging | Visual impact |
|------|------------|-------------|---------------|
| MBBB plugin | **1.7.1** | **1.9.3** | Staging has newer deal admin + pool options — **not visible to shoppers** until promoted |
| Storefront theme | Same repo target | Same theme | Should match if both deployed from same branch |
| Test products | Clean catalogue | STP Test Boilie, STP Liquid in “New This Week” | Staging looks less polished in product grids |
| Bundle builder UI | Production v1.7.1 frontend | v1.9.3 frontend | Slot/range grouping improvements on staging only |
| Database | Live orders/products | Isolated DB | Staging may have incomplete images vs prod |

**Recommendation:** Visual fixes above apply to **both** environments via theme deploy. MBBB **1.9.x promotion to production** is a separate release (functional + minor builder UI polish), not a full redesign.

---

## Suggested implementation order

1. **404.php** + hide bundle Additional Information tab (quick code wins)
2. **Modal staggering** + hide version label (mobile UX)
3. **Product photography** bulk upload (biggest customer-facing improvement)
4. **Empty cart** polish
5. **Content pass** (typos, range names)
6. **MBBB 1.9.x** production promotion (after QA on app-staging)

---

## QA after fixes

Re-run screenshots at 390 / 768 / 1440px on:
- `/`
- `/shop/`
- `/product-category/bundles-deals/`
- `/product/mix-match-5kg-deal/`
- `/product/bbb-paste-500gm/`
- `/cart/`
- A deliberate 404 URL

Use `docs/visual-qa-checklist.md` for sign-off.
