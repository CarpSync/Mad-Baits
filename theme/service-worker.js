// App cache version is injected when served (?mad_baits_pwa=service-worker).
const APP_CACHE_VERSION = "__MAD_BAITS_APP_VERSION__";
const CACHE_NAME = "mad-baits-app-cache-" + APP_CACHE_VERSION;
const OFFLINE_FALLBACK_URL = "/?mad_baits_pwa=offline";
const MANIFEST_URL = "/?mad_baits_pwa=manifest";
const VERSION_PARAM = "mad_baits_app_version=" + encodeURIComponent(APP_CACHE_VERSION);

function withVersion(url) {
  if (typeof url !== "string" || !url) {
    return url;
  }
  return url + (url.includes("?") ? "&" : "?") + VERSION_PARAM;
}

const PRECACHE_URLS = [
  MANIFEST_URL,
  OFFLINE_FALLBACK_URL,
  withVersion("/wp-content/themes/mad-baits/assets/css/main.css"),
  withVersion("/wp-content/themes/mad-baits/assets/css/scoped/mobile-app.css"),
  withVersion("/wp-content/themes/mad-baits/assets/css/scoped/app-mobile-ux.css"),
  withVersion("/wp-content/themes/mad-baits/assets/js/main.js"),
  withVersion("/wp-content/themes/mad-baits/assets/js/app-mobile-ux.js"),
  "/wp-content/themes/mad-baits/assets/img/CUP-LOGOv2-1.svg",
  "/wp-content/themes/mad-baits/assets/img/icons/icon-192.png",
  "/wp-content/themes/mad-baits/assets/img/icons/icon-512.png",
  "/wp-content/themes/mad-baits/assets/img/icons/icon-maskable-192.png",
  "/wp-content/themes/mad-baits/assets/img/icons/icon-maskable-512.png"
];

function isMadBaitsCacheName(name) {
  return (
    name === CACHE_NAME ||
    name.indexOf("mad-baits-app-cache-") === 0 ||
    name.indexOf("mad-baits-v") === 0
  );
}

function isDynamicWooUrl(url) {
  const path = (url.pathname || "").toLowerCase();
  const query = (url.search || "").toLowerCase();
  const href = url.href.toLowerCase();

  if (
    path.includes("/cart") ||
    path.includes("/checkout") ||
    path.includes("/my-account") ||
    path.includes("/wp-json/") ||
    path.includes("/wp-admin") ||
    path.includes("/wp-login.php") ||
    path.includes("/wc-api") ||
    path.includes("/order-pay") ||
    path.includes("/order-received") ||
    path.includes("/admin-ajax.php")
  ) {
    return true;
  }

  if (
    query.includes("wc-ajax=") ||
    query.includes("rest_route=") ||
    query.includes("add-to-cart") ||
    query.includes("wc-api") ||
    query.includes("wc_order") ||
    query.includes("pay_for_order=true") ||
    query.includes("paypal") ||
    query.includes("stripe") ||
    query.includes("payment")
  ) {
    return true;
  }

  if (
    href.includes("/wp-json/") ||
    href.includes("/wp-json/wc") ||
    href.includes("wc-ajax=get_refreshed_fragments") ||
    href.includes("wc-ajax=update_order_review")
  ) {
    return true;
  }

  return false;
}

function isStaticGetRequest(request) {
  if (request.method !== "GET") {
    return false;
  }

  const destination = request.destination || "";
  return ["style", "script", "image", "font"].includes(destination);
}

function shouldCacheStaticRequest(url, request) {
  if (url.origin !== self.location.origin) {
    return false;
  }

  // Bundle builder assets must always be network-fresh (global settings change often).
  if (url.pathname.includes("/mad-baits-bundle-builder/")) {
    return false;
  }

  if (isDynamicWooUrl(url)) {
    return false;
  }

  if (!isStaticGetRequest(request)) {
    return false;
  }

  // Keep cache lightweight. Avoid precaching or runtime-caching full media libraries.
  if (url.pathname.toLowerCase().includes("/wp-content/uploads/")) {
    return false;
  }

  return true;
}

self.addEventListener("install", (event) => {
  console.info("[MadBaitsSW] install", { cache: CACHE_NAME, version: APP_CACHE_VERSION });
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE_URLS)).then(() => self.skipWaiting())
  );
});

self.addEventListener("activate", (event) => {
  console.info("[MadBaitsSW] activate", { cache: CACHE_NAME });
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(
          keys.map((key) => {
            if (isMadBaitsCacheName(key) && key !== CACHE_NAME) {
              return caches.delete(key);
            }
            return Promise.resolve(true);
          })
        )
      )
      .then(() => self.clients.claim())
  );
});

self.addEventListener("message", (event) => {
  if (!event || !event.data) {
    return;
  }

  if (event.data.type === "SKIP_WAITING") {
    console.info("[MadBaitsSW] skip_waiting_requested");
    self.skipWaiting();
  }
});

self.addEventListener("fetch", (event) => {
  const request = event.request;
  const requestUrl = new URL(request.url);

  if (request.method !== "GET") {
    return;
  }

  if (isDynamicWooUrl(requestUrl)) {
    event.respondWith(fetch(request));
    return;
  }

  if (request.mode === "navigate") {
    event.respondWith(
      fetch(request).catch(async () => {
        const cache = await caches.open(CACHE_NAME);
        const fallback = await cache.match(OFFLINE_FALLBACK_URL);
        return fallback || Response.error();
      })
    );
    return;
  }

  if (!shouldCacheStaticRequest(requestUrl, request)) {
    return;
  }

  // Network-first for scripts/styles so installed PWA always picks up deploys.
  event.respondWith(
    fetch(request)
      .then((response) => {
        if (response && response.ok) {
          return caches.open(CACHE_NAME).then((cache) => {
            cache.put(request, response.clone());
            return response;
          });
        }
        return response;
      })
      .catch(() => caches.open(CACHE_NAME).then((cache) => cache.match(request)))
  );
});

function getPushPayload(event) {
  if (!event || !event.data) {
    return {};
  }

  try {
    return event.data.json();
  } catch (error) {
    return {
      title: "Mad Baits",
      body: event.data.text()
    };
  }
}

self.addEventListener("push", (event) => {
  const payload = getPushPayload(event);
  const title = payload.title || "Mad Baits";
  const options = {
    body: payload.body || "Fresh updates from Mad Baits.",
    icon: payload.icon || "/wp-content/themes/mad-baits/assets/img/icons/icon-192.png",
    badge: payload.badge || "/wp-content/themes/mad-baits/assets/img/icons/icon-192.png",
    image: payload.image || "",
    tag: payload.tag || "mad-baits-push",
    requireInteraction: Boolean(payload.requireInteraction),
    data: {
      url: (payload.data && payload.data.url) || "/shop/"
    }
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener("notificationclick", (event) => {
  event.notification.close();

  const targetUrl = (event.notification && event.notification.data && event.notification.data.url) || "/shop/";
  event.waitUntil(
    clients.matchAll({ type: "window", includeUncontrolled: true }).then((windowClients) => {
      for (const client of windowClients) {
        if ("focus" in client && client.url && client.url.includes(targetUrl)) {
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(targetUrl);
      }
      return Promise.resolve();
    })
  );
});
