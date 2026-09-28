# Mad Baits Mobile Connector

WordPress plugin providing REST API endpoints for the **Mad Baits V2** mobile app.

**Version:** 1.6.0  
**Namespace:** `madbaits/v1`  
**Status:** Built locally — install on madbaits.com when ready (do not deploy until configured)

---

## What is implemented

| Module | Status |
|--------|--------|
| Events API (`GET /events`) | Live — public read-only |
| Device registration + preferences | Live — requires App Token |
| App feedback | Live — requires App Token |
| Team catch reports | Live — requires App Token |
| Auth login / logout / refresh | **Implemented** — JWT + App Token |
| Account profile | **Implemented** — JWT + App Token |
| Order history | **Implemented** — JWT + App Token |
| Order detail (`GET /orders/{id}`) | **Implemented** — JWT + App Token |
| Password reset placeholder | **501** — future WordPress integration |
| Register placeholder | **501** — future WooCommerce registration |
| Push sending (Expo) | **Implemented — off by default** |
| Test push endpoint + admin panel | **Implemented** |
| WooCommerce order status hooks | **Implemented — off by default** |

---

## Requirements

- WordPress 6.0+
- PHP 7.4+
- HTTPS in production

No ACF required. WooCommerce optional (future auth/orders only).

---

## Install later (when ready)

### Option A — Upload zip

```bash
cd /path/to/madbaits-mobile-v2
zip -r mad-baits-mobile-connector.zip mad-baits-mobile-connector/
```

1. WordPress → **Plugins → Add New → Upload Plugin**
2. Upload `mad-baits-mobile-connector.zip`
3. **Activate**

### Option B — SFTP

Copy the folder to:

```
wp-content/plugins/mad-baits-mobile-connector/
```

Then activate under **Plugins**.

### After activation

1. Go to **Mobile App → Settings**
2. Set **Mad Baits App Token** (long random string — do not hardcode in plugin or repo)
3. Configure the same token in the mobile app: `EXPO_PUBLIC_MADBAITS_APP_TOKEN`
4. Permalinks flush automatically; four DB tables are created via `dbDelta`

---

## Configure App Token

Protected endpoints require header:

```
X-Madbaits-App-Token: <your-token>
```

| Setting | Location |
|---------|----------|
| WordPress option | **Mobile App → Settings** (`mbmc_app_token`) |
| Programmatic | `update_option( 'mbmc_app_token', 'your-long-random-token' );` |

**If no token is configured:**

- `GET /wp-json/madbaits/v1/events` — **still works** (public)
- All other routes return **503** with a clear error message

---

## Database tables

Created on activation / upgrade (`MBMC_DB::install()`):

### `wp_madbaits_app_devices`

| Column | Notes |
|--------|-------|
| `id` | Primary key |
| `user_id` | Nullable — set via JWT on device register |
| `customer_id` | Nullable — set via JWT on device register |
| `device_id` | Unique, max 64 chars |
| `push_token` | Expo / FCM token |
| `platform` | `android` or `ios` |
| `app_version` | e.g. `2.0.0` |
| `notification_preferences` | JSON longtext |
| `last_seen_at` | Updated on register/sync |
| `created_at` / `updated_at` | Timestamps |

### `wp_madbaits_app_feedback`

| Column | Notes |
|--------|-------|
| `id` | Primary key |
| `user_id` | Nullable |
| `device_id` | Nullable |
| `feedback_type` | `app`, `product`, `bug`, `feature`, `team`, `other` |
| `title` | Optional |
| `message` | Required |
| `priority` | `low`, `normal`, `high` |
| `app_version` | Optional |
| `platform` | Optional |
| `device_info` | JSON longtext |
| `status` | Default `new` |
| `created_at` | Timestamp |

### `wp_madbaits_team_catch_reports`

| Column | Notes |
|--------|-------|
| `id` | Primary key |
| `user_id` | Nullable |
| `team_role` | e.g. `bait_tester`, `ambassador` |
| `angler_name` | Optional |
| `fish_weight` | Optional |
| `venue` | Required |
| `bait_used` | Required |
| `rig_used` | Optional |
| `notes` | Optional longtext |
| `photo_url` | Nullable — signed upload later |
| `permission_to_use` | Boolean |
| `social_caption` | Required |
| `status` | Default `submitted` |
| `created_at` | Timestamp |

### `wp_madbaits_app_notification_logs`

| Column | Notes |
|--------|-------|
| `id` | Primary key |
| `device_id` | Nullable |
| `push_token` | Target token |
| `notification_type` | e.g. `test_admin`, `test_app` |
| `title` / `message` | Notification content |
| `payload` | JSON data payload |
| `provider` | `expo` or `firebase_future` |
| `status` | `queued`, `sent`, `failed`, `skipped` |
| `provider_response` | Raw Expo API JSON |
| `created_at` | Timestamp |

---

## All endpoints

Base URL: `https://your-site.com/wp-json/madbaits/v1`

| Method | Route | Auth | Status |
|--------|-------|------|--------|
| GET | `/events` | None | Implemented |
| POST | `/device/register` | App Token | Implemented |
| POST | `/device/unregister` | App Token | Implemented |
| POST | `/device/preferences` | App Token | Implemented |
| POST | `/feedback` | App Token | Implemented |
| POST | `/team/catch-report` | App Token | Implemented |
| POST | `/notification/test` | App Token | Implemented (test sends) |
| POST | `/auth/login` | App Token | Implemented |
| POST | `/auth/logout` | App Token (+ optional Bearer) | Implemented |
| POST | `/auth/refresh` | App Token | Implemented |
| GET | `/account` | App Token + Bearer | Implemented |
| GET | `/orders` | App Token + Bearer | Implemented |

Rate limit: **120 requests/hour/IP** on protected routes. Login: **20/hour/IP**. Refresh: **60/hour/IP**.

Authenticated routes use `Authorization: Bearer <accessToken>` plus `X-Madbaits-App-Token`.

---

## JWT authentication (v1.5)

### Strategy

| Token | Lifetime | Storage |
|-------|----------|---------|
| Access | 15 minutes (`MBMC_JWT::ACCESS_TTL`) | Client SecureStore only |
| Refresh | 30 days | Client SecureStore; **hash** stored in user meta `mbmc_refresh_tokens` |

- Signed with **HS256** using secret derived from `wp_salt('auth')` + configured App Token
- Access payload: `sub` (user ID), `cid` (customer ID), `type: access`, `exp`, `jti`
- Refresh payload: `sub`, `type: refresh`, `exp`, `jti`
- Refresh rotation on `/auth/refresh` — old refresh revoked, new pair issued
- Logout revokes refresh token (from body) or all refresh tokens (when Bearer access token supplied)
- Only **customer-facing roles** allowed — administrators/shop managers blocked
- Generic login errors — never reveals whether email exists

### Auth flow

1. App sends `POST /auth/login` with App Token + email/password (+ optional `deviceId`)
2. Server returns `accessToken`, `refreshToken`, `expiresAt`, `user`, `customerId`
3. Authenticated reads send **both** headers:
   - `X-Madbaits-App-Token`
   - `Authorization: Bearer <accessToken>`
4. Before access expiry, `POST /auth/refresh` with `refreshToken`
5. `POST /auth/logout` revokes refresh session

### Auto refresh (mobile app v1.6)

When any authenticated connector request returns **401**:

1. App calls `POST /auth/refresh` once (never retried on 401)
2. New `accessToken` / `refreshToken` saved to **SecureStore only**
3. Original request retried **once** with the new access token
4. If refresh fails → local logout + “session expired” message

Rules: no infinite loops, no token logging, refresh/login/logout/register/password-reset skip auto-refresh.

### Login curl

```bash
curl -s -X POST "https://YOUR_SITE/wp-json/madbaits/v1/auth/login" \
  -H "Content-Type: application/json" \
  -H "X-Madbaits-App-Token: YOUR_TOKEN" \
  -d '{
    "email": "customer@example.com",
    "password": "your-password",
    "deviceId": "mb-device-test-001"
  }' | jq .
```

### Account curl

```bash
curl -s "https://YOUR_SITE/wp-json/madbaits/v1/account" \
  -H "X-Madbaits-App-Token: YOUR_TOKEN" \
  -H "Authorization: Bearer ACCESS_TOKEN" | jq .
```

### Orders curl

```bash
curl -s "https://YOUR_SITE/wp-json/madbaits/v1/orders?page=1&per_page=20" \
  -H "X-Madbaits-App-Token: YOUR_TOKEN" \
  -H "Authorization: Bearer ACCESS_TOKEN" | jq .
```

### Order detail curl

```bash
curl -s "https://YOUR_SITE/wp-json/madbaits/v1/orders/1234" \
  -H "X-Madbaits-App-Token: YOUR_TOKEN" \
  -H "Authorization: Bearer ACCESS_TOKEN" | jq .
```

Returns only the authenticated customer's order. Includes `items`, `billing`, `shipping`, `tracking` (nullable), and `timeline` (`received`, `processing`, `dispatched`, `completed`).

### Password reset placeholder (501)

```bash
curl -s -X POST "https://YOUR_SITE/wp-json/madbaits/v1/auth/password-reset" \
  -H "Content-Type: application/json" \
  -H "X-Madbaits-App-Token: YOUR_TOKEN" \
  -d '{ "email": "customer@example.com" }' | jq .
```

Future: WordPress core `retrieve_password` / WooCommerce lost-password flow. Mobile app shows a friendly “use MadBaits.com for now” message when live connector is enabled.

### Register placeholder (501)

```bash
curl -s -X POST "https://YOUR_SITE/wp-json/madbaits/v1/auth/register" \
  -H "Content-Type: application/json" \
  -H "X-Madbaits-App-Token: YOUR_TOKEN" \
  -d '{
    "email": "new@example.com",
    "password": "secret",
    "displayName": "Angler"
  }' | jq .
```

Future: WooCommerce customer registration with email verification. Mock registration remains in the mobile app when connector is disabled.

### Refresh curl

```bash
curl -s -X POST "https://YOUR_SITE/wp-json/madbaits/v1/auth/refresh" \
  -H "Content-Type: application/json" \
  -H "X-Madbaits-App-Token: YOUR_TOKEN" \
  -d '{ "refreshToken": "REFRESH_TOKEN" }' | jq .
```

### Device register with Bearer

```bash
curl -s -X POST "https://YOUR_SITE/wp-json/madbaits/v1/device/register" \
  -H "Content-Type: application/json" \
  -H "X-Madbaits-App-Token: YOUR_TOKEN" \
  -H "Authorization: Bearer ACCESS_TOKEN" \
  -d '{
    "deviceId": "mb-device-test-001",
    "pushToken": "ExponentPushToken[test]",
    "platform": "android"
  }' | jq .
```

When Bearer token is present, `user_id` and `customer_id` are set on the device row automatically.

### Security notes

- HTTPS required in production (mobile client enforces HTTPS)
- No password hashes or payment tokens in any response
- Orders filtered strictly by authenticated `customer_id` (list + detail)
- Order detail returns 404 if order belongs to another customer
- WooCommerce inactive: login still works for WP users; orders return empty list
- Rate limited login endpoint
- Refresh tokens stored as HMAC hashes — raw refresh never stored server-side

---

## Admin pages

WordPress sidebar: **Mobile App**

| Page | Purpose |
|------|---------|
| Dashboard | Counts + API status |
| Devices | Registered device table |
| Feedback | Submitted feedback table |
| Team Catch Reports | Submitted reports table |
| Events | Links to `madbaits_event` CPT |
| Settings | App Token + push settings + test panel |

---

## Push notification settings (v1.3)

**Mobile App → Settings**

| Setting | Option key | Default |
|---------|------------|---------|
| Push Sending Enabled | `mbmc_push_sending_enabled` | **Off** |
| Push Provider | `mbmc_push_provider` | `expo` |
| Test Mode Enabled | `mbmc_push_test_mode_enabled` | **On** |
| Max Sends Per Batch | `mbmc_push_max_batch` | `10` |

### Safety defaults

- **Push sending is OFF** until explicitly enabled in wp-admin
- **Test mode is ON** — only notification types starting with `test_` are allowed via API
- No mass-send endpoint exists yet — single-token test sends only
- All sends are logged to `wp_madbaits_app_notification_logs`

### Admin test panel

1. Enable **Push Sending Enabled**
2. Scroll to **Send test notification** on **Mobile App → Settings**
3. Enter an `ExponentPushToken[...]` value, title, and message
4. Submit — uses type `test_admin` and logs the result
5. Recent logs appear below the form

### Expo Push API

Provider: `MBMC_Expo_Push_Provider`  
Endpoint: `https://exp.host/--/api/v2/push/send`

```json
{
  "to": "ExponentPushToken[xxxxxxxxxxxxxx]",
  "title": "Mad Baits",
  "body": "Your message",
  "data": { "screen": "home" },
  "sound": "default"
}
```

Uses `wp_remote_post`. Handles invalid tokens, HTTP errors, Expo ticket errors, and success tickets.

Firebase (`firebase_future`) is listed in settings but **not implemented** — logs `skipped`.

### Test notification endpoint

```
POST /wp-json/madbaits/v1/notification/test
```

**Headers:** `X-Madbaits-App-Token`, `Content-Type: application/json`

**Body:**

```json
{
  "deviceId": "mb-device-test-001",
  "pushToken": "ExponentPushToken[optional-if-deviceId-set]",
  "type": "test_api",
  "title": "Mad Baits test",
  "message": "Hello from connector",
  "data": { "screen": "notifications" }
}
```

**Behaviour:**

| Condition | Result |
|-----------|--------|
| Push sending disabled | `success: false`, status `skipped`, log written |
| Test mode on + type not `test_*` | `success: false`, status `skipped` |
| Valid token | Expo send attempt, log `sent` or `failed` |

**curl example:**

```bash
curl -s -X POST "https://YOUR_SITE/wp-json/madbaits/v1/notification/test" \
  -H "Content-Type: application/json" \
  -H "X-Madbaits-App-Token: YOUR_TOKEN" \
  -d '{
    "pushToken": "ExponentPushToken[your-token]",
    "type": "test_api",
    "title": "Mad Baits test",
    "message": "Push layer test from curl"
  }' | jq .
```

**Expected when push sending is disabled:**

```json
{
  "ok": false,
  "success": false,
  "status": "skipped",
  "message": "Push sending is disabled. Enable it under Mobile App → Settings."
}
```

### Mobile app test service

`src/services/notifications/wordpressNotificationTestService.ts`

```typescript
import { sendTestNotification } from '@/services/notifications/wordpressNotificationTestService';

await sendTestNotification({
  type: 'test_app',
  title: 'Mad Baits',
  message: 'Test from app',
});
```

Requires:

```
EXPO_PUBLIC_WORDPRESS_CONNECTOR_ENABLED=true
EXPO_PUBLIC_MADBAITS_APP_TOKEN=<matches wp-admin>
EXPO_PUBLIC_WC_BASE_URL=https://madbaits.com
```

### Production safety checklist

Before enabling push sending on production:

- [ ] App Token set and rotated
- [ ] Push Sending Enabled only on staging first
- [ ] Test Mode Enabled until order hooks are ready
- [ ] Verify logs table after each test send
- [ ] Confirm Expo tokens are valid on real devices
- [ ] Do **not** disable test mode until mass-send architecture is reviewed
- [ ] No WooCommerce order hooks until v1.4+ (separate release)

### Why production mass sends are disabled

v1.3 intentionally limits sending to **single test notifications**:

- No batch broadcast endpoint
- Test mode blocks non-`test_*` types
- Push sending defaults to **off**
- Order hooks (v1.4) also respect test mode and order notification toggles

---

## WooCommerce order notifications (v1.4)

Hooks into WooCommerce order status changes and sends push notifications to registered customer devices via `MBMC_Notification_Service`. **Off by default.**

### WooCommerce hooks registered

| WooCommerce hook | Notification type |
|------------------|-------------------|
| `woocommerce_order_status_pending` | `order_received` |
| `woocommerce_order_status_processing` | `order_processing` |
| `woocommerce_order_status_completed` | `order_completed` |
| `woocommerce_order_status_cancelled` | `order_cancelled` |
| `woocommerce_order_status_refunded` | `order_refunded` |
| `woocommerce_order_status_failed` | `order_failed` |
| `woocommerce_order_status_dispatched` | `order_dispatched` |
| `woocommerce_order_status_wc-dispatched` | `order_dispatched` |

Tracking meta updates (`updated_post_meta` on common tracking keys) can trigger `tracking_added` when tracking notifications are enabled.

If WooCommerce is inactive, hooks are not registered and nothing errors.

### Order notification settings

**Mobile App → Settings → Order notifications**

| Setting | Option key | Default |
|---------|------------|---------|
| Enable order notifications | `mbmc_order_notifications_enabled` | **Off** |
| Enable dispatched notifications | `mbmc_order_dispatched_notifications_enabled` | **Off** |
| Enable tracking notifications | `mbmc_order_tracking_notifications_enabled` | **Off** |
| Require push sending enabled | `mbmc_order_require_push_enabled` | **On** |

### Customer device matching

1. Order fires a WooCommerce status hook
2. Plugin reads `$order->get_customer_id()` (WooCommerce user ID)
3. Guest orders (`customer_id = 0`) → log **skipped** (`guest_order_no_devices`)
4. Query `wp_madbaits_app_devices` where `customer_id` **or** `user_id` matches
5. If no rows → log **skipped** (`no_registered_devices`)
6. Each device is checked individually — never sends to unrelated customers

Devices must be linked to the customer before order pushes work. With v1.5 JWT login + device register (or login `deviceId`), linking is automatic.

### Notification preference keys

Stored in device `notification_preferences` JSON. **Enabled by default** unless explicitly `false`:

| Notification type | Preference key(s) |
|-------------------|-------------------|
| `order_received` | `orderReceived` |
| `order_processing` | `orderProcessing` |
| `order_dispatched` | `orderDispatched`, `orderDispatchedTracking` (legacy) |
| `order_completed` | `orderCompleted`, `orderCompletedCancelled` (legacy) |
| `order_cancelled` | `orderCancelled`, `orderCompletedCancelled` (legacy) |
| `order_refunded` | `orderRefunded` |
| `order_failed` | `orderFailed` |
| `tracking_added` | `trackingAdded`, `orderDispatchedTracking` (legacy) |

If preference is `false` → skip send, log **skipped** (`preference_disabled`).

### Push payload

```json
{
  "type": "order_processing",
  "orderId": "1234",
  "orderNumber": "1234",
  "status": "processing",
  "customerId": "56",
  "deepLink": "madbaits://orders/1234",
  "trackingNumber": "optional",
  "trackingUrl": "optional",
  "courier": "optional"
}
```

Tracking fields included when detected via `MBMC_WooCommerce::get_order_tracking_data()` (common meta keys only — no specific plugin dependency).

### Safety behaviour

Order notifications are blocked when **any** of these apply:

| Guard | Log reason |
|-------|------------|
| Order notifications disabled | `order_notifications_disabled` |
| Dispatched toggle off | `dispatched_notifications_disabled` |
| Tracking toggle off | `tracking_notifications_disabled` |
| Require push + push sending off | `push_sending_disabled` |
| Test mode on | `test_mode_blocks_order_notifications` |
| Guest order | `guest_order_no_devices` |
| No matching devices | `no_registered_devices` |
| User preference off | `preference_disabled` |

All attempts are logged in `wp_madbaits_app_notification_logs` with status `sent`, `skipped`, or `failed`.

### Why test mode blocks real order sends

With **Test Mode Enabled** (default **on**), order status hooks still run but every device attempt is logged as **skipped** with reason `test_mode_blocks_order_notifications`. No Expo API call is made for order types.

Disable test mode only after staging validation — and only together with careful review of push + order toggles.

### How to test safely later (staging)

1. Install plugin on staging with WooCommerce active
2. Set **App Token**, enable **Push Sending**, keep **Test Mode on** first
3. Create a test customer order (logged-in customer, not guest)
4. Register a device via API with matching `customer_id`:

   ```sql
   UPDATE wp_madbaits_app_devices
   SET customer_id = 123, user_id = 123
   WHERE device_id = 'your-test-device';
   ```

5. Change order status in wp-admin (e.g. Pending → Processing)
6. Check **Recent notification logs** — expect `skipped` while test mode is on
7. Enable **Order notifications** + **Push sending**; keep test mode on → still skipped
8. Turn **Test Mode off** on staging only → re-test status change → expect `sent` if Expo token valid
9. Verify push received on device and `deepLink` payload

### Message templates

| Type | Message |
|------|---------|
| `order_received` | Your Mad Baits order #{orderNumber} has been received. |
| `order_processing` | Your Mad Baits order #{orderNumber} is now being prepared. |
| `order_dispatched` | Your Mad Baits order #{orderNumber} has been dispatched. |
| `order_completed` | Your Mad Baits order #{orderNumber} has been completed. |
| `order_cancelled` | Your Mad Baits order #{orderNumber} has been cancelled. |
| `order_refunded` | Your Mad Baits order #{orderNumber} has been refunded. |
| `order_failed` | There was a problem with your Mad Baits order #{orderNumber}. |
| `tracking_added` | Tracking has been added for your Mad Baits order #{orderNumber}. |

---

## curl test examples

Replace `YOUR_SITE` and `YOUR_TOKEN`.

### Events (no token)

```bash
curl -s "https://YOUR_SITE/wp-json/madbaits/v1/events" | jq .
```

### Protected route without token configured

Returns 503 until App Token is set in wp-admin.

### Device register

```bash
curl -s -X POST "https://YOUR_SITE/wp-json/madbaits/v1/device/register" \
  -H "Content-Type: application/json" \
  -H "X-Madbaits-App-Token: YOUR_TOKEN" \
  -d '{
    "deviceId": "mb-device-test-001",
    "pushToken": "ExponentPushToken[test-token]",
    "platform": "android",
    "appVersion": "2.0.0",
    "preferences": {
      "orderReceived": true,
      "biteWindowAlerts": true
    }
  }' | jq .
```

### Device preferences

```bash
curl -s -X POST "https://YOUR_SITE/wp-json/madbaits/v1/device/preferences" \
  -H "Content-Type: application/json" \
  -H "X-Madbaits-App-Token: YOUR_TOKEN" \
  -d '{
    "deviceId": "mb-device-test-001",
    "preferences": { "biteWindowAlerts": false }
  }' | jq .
```

### Device unregister

```bash
curl -s -X POST "https://YOUR_SITE/wp-json/madbaits/v1/device/unregister" \
  -H "Content-Type: application/json" \
  -H "X-Madbaits-App-Token: YOUR_TOKEN" \
  -d '{ "deviceId": "mb-device-test-001" }' | jq .
```

### Feedback

```bash
curl -s -X POST "https://YOUR_SITE/wp-json/madbaits/v1/feedback" \
  -H "Content-Type: application/json" \
  -H "X-Madbaits-App-Token: YOUR_TOKEN" \
  -d '{
    "feedbackType": "app",
    "title": "Beta feedback",
    "message": "Love the bite window card on Home.",
    "priority": "normal",
    "appVersion": "2.0.0",
    "platform": "ios",
    "deviceId": "mb-device-test-001"
  }' | jq .
```

### Team catch report

```bash
curl -s -X POST "https://YOUR_SITE/wp-json/madbaits/v1/team/catch-report" \
  -H "Content-Type: application/json" \
  -H "X-Madbaits-App-Token: YOUR_TOKEN" \
  -d '{
    "teamRole": "bait_tester",
    "species": "Mirror carp",
    "fishWeight": "24lb 8oz",
    "venue": "Mad Lake",
    "baitUsed": "Bloodworm XL 15mm",
    "rigUsed": "Helicopter rig",
    "socialCaption": "Cracking session on @MadBaits Bloodworm XL!",
    "photoPermission": true,
    "notes": "Overnight session, mild wind"
  }' | jq .
```

### Auth / account (live when App Token + JWT configured)

```bash
curl -s -X POST "https://YOUR_SITE/wp-json/madbaits/v1/auth/login" \
  -H "Content-Type: application/json" \
  -H "X-Madbaits-App-Token: YOUR_TOKEN" \
  -d '{ "email": "customer@example.com", "password": "secret" }' | jq .

curl -s "https://YOUR_SITE/wp-json/madbaits/v1/account" \
  -H "X-Madbaits-App-Token: YOUR_TOKEN" \
  -H "Authorization: Bearer ACCESS_TOKEN" | jq .

curl -s "https://YOUR_SITE/wp-json/madbaits/v1/orders/1234" \
  -H "X-Madbaits-App-Token: YOUR_TOKEN" \
  -H "Authorization: Bearer ACCESS_TOKEN" | jq .
```

---

## Mobile app order UI (v1.7)

The Mad Baits V2 app consumes the v1.6 order APIs with read-only screens (no checkout, no order modification).

| App route | Navigation | Deep link (future) |
|-----------|------------|-------------------|
| Order history | Profile → **My orders** → `OrderHistory` | — |
| Order detail | Tap order → `OrderDetail` `{ orderId }` | `madbaits://orders/:orderId` |

Mobile files:

- `src/screens/Orders/OrderHistoryScreen.tsx`
- `src/screens/Orders/OrderDetailScreen.tsx`
- `src/store/orderStore.ts`
- `src/utils/orderNavigation.ts` — route + deep link helpers (linking wired in a later phase)

### Mock mode (connector off or app token unset)

1. Sign in with any valid mock credentials (email + password ≥ 4 chars)
2. Profile → **My orders** → demo orders from `src/data/mockOrders.ts`
3. Tap an order for timeline, items, billing/shipping, and tracking placeholder

### Live connector mode

```bash
EXPO_PUBLIC_WORDPRESS_CONNECTOR_ENABLED=true
EXPO_PUBLIC_MADBAITS_APP_TOKEN=<matches wp-admin>
EXPO_PUBLIC_WC_BASE_URL=https://madbaits.com
```

1. Sign in with a real Mad Baits customer account
2. Profile → **My orders** → `GET /orders`
3. Tap an order → `GET /orders/{id}`

Auto token refresh (v1.6) applies to both list and detail requests.

### Future tracking integration

- Server: `MBMC_WooCommerce::get_order_tracking_data()` reads common WooCommerce tracking meta keys
- App: tracking block opens external URL via `Linking.openURL` when `trackingUrl` is present — no WebView
- Push payloads already include `deepLink: madbaits://orders/{orderId}` — navigation handler pending v1.8+

---

## Future: v1.8+

1. Deep link handler: `madbaits://orders/:orderId` → `OrderDetail`
2. Guest checkout → account linking strategy
3. Live password reset + register (replace 501 placeholders)
4. Team role claims in JWT for server-validated team routes
5. Admin audit log for auth events

**v1.7 adds read-only order UI only — no checkout, payments, or order modification.**

---

## Future: Firebase push (optional)

Documented provider slug: `firebase_future`. Implement `MBMC_Firebase_Push_Provider` extending `MBMC_Push_Provider` when FCM direct sends are needed alongside Expo.

---

## Plugin structure

```
mad-baits-mobile-connector/
├── mad-baits-mobile-connector.php
├── includes/
│   ├── class-mbmc-plugin.php
│   ├── class-mbmc-admin.php
│   ├── class-mbmc-db.php
│   ├── class-mbmc-push-provider.php
│   ├── class-mbmc-expo-push-provider.php
│   ├── class-mbmc-notification-service.php
│   ├── class-mbmc-jwt.php
│   ├── class-mbmc-woocommerce.php
│   ├── class-mbmc-cpt-events.php
│   ├── class-mbmc-rest-events.php
│   ├── class-mbmc-rest-devices.php
│   ├── class-mbmc-rest-feedback.php
│   ├── class-mbmc-rest-team.php
│   ├── class-mbmc-rest-auth.php
│   ├── class-mbmc-rest-account.php
│   ├── class-mbmc-rest-notifications.php
│   └── helpers.php
├── assets/
│   └── admin.css
└── README.md
```

---

## Related documentation

In the mobile repository:

- `docs/MADBAITS_WORDPRESS_CONNECTOR_PLAN.md`
- `docs/MADBAITS_ORDERS_UI.md`
- `docs/DEEP_LINKING_AND_NOTIFICATION_ROUTING.md`
- `docs/MADBAITS_EVENTS_INTEGRATION_PLAN.md`
