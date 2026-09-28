(function () {
  const config = window.mbtaPortalConfig;
  if (!config) return;

  const statusEl = document.getElementById("mbta-push-status");
  const enableBtn = document.getElementById("mbta-enable-push");
  const disableBtn = document.getElementById("mbta-disable-push");

  function setStatus(message, isError) {
    if (!statusEl) return;
    statusEl.textContent = message || "";
    statusEl.classList.toggle("is-error", Boolean(isError));
  }

  async function postJson(path, payload) {
    const headers = { "Content-Type": "application/json" };
    if (config.restNonce) headers["X-WP-Nonce"] = config.restNonce;
    const res = await fetch(config.restRoot + path, {
      method: "POST",
      credentials: "same-origin",
      headers,
      body: JSON.stringify(payload),
    });
    return res.json().catch(() => ({}));
  }

  function urlBase64ToUint8Array(base64String) {
    const padding = "=".repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, "+").replace(/_/g, "/");
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; i += 1) outputArray[i] = rawData.charCodeAt(i);
    return outputArray;
  }

  async function subscribePrivatePush() {
    if (!("serviceWorker" in navigator) || !("PushManager" in window)) {
      setStatus("Push is not supported in this browser.", true);
      return;
    }
    try {
      const statusRes = await fetch(config.restRoot + "status", { credentials: "same-origin" });
      const status = await statusRes.json();
      const publicKey = (status && status.public_key) || config.publicVapidKey;
      if (!publicKey) {
        setStatus("VAPID keys are not configured. Ask admin to set up push.", true);
        return;
      }

      const permission = await Notification.requestPermission();
      if (permission !== "granted") {
        setStatus("Notification permission was denied.", true);
        return;
      }

      const registration = await navigator.serviceWorker.register(config.serviceWorkerUrl, { scope: "/" });
      await navigator.serviceWorker.ready;
      let subscription = await registration.pushManager.getSubscription();
      if (!subscription) {
        subscription = await registration.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: urlBase64ToUint8Array(publicKey),
        });
      }

      const json = subscription.toJSON();
      const result = await postJson("subscribe", {
        endpoint: subscription.endpoint,
        keys: json.keys || {},
        permission,
        is_pwa: window.matchMedia("(display-mode: standalone)").matches,
        device_label: "Team Portal",
        user_agent: navigator.userAgent || "",
      });

      if (result && result.success) {
        setStatus("Private notifications enabled on this device.");
      } else {
        setStatus((result && result.message) || "Could not save subscription.", true);
      }
    } catch (err) {
      setStatus(err && err.message ? err.message : "Subscription failed.", true);
    }
  }

  async function unsubscribePrivatePush() {
    if (!("serviceWorker" in navigator)) return;
    try {
      const registration = await navigator.serviceWorker.getRegistration("/");
      if (!registration) return;
      const subscription = await registration.pushManager.getSubscription();
      if (!subscription) {
        setStatus("No active subscription on this device.");
        return;
      }
      const endpoint = subscription.endpoint;
      await subscription.unsubscribe();
      await postJson("unsubscribe", { endpoint });
      setStatus("Notifications turned off on this device.");
    } catch (err) {
      setStatus("Could not unsubscribe.", true);
    }
  }

  enableBtn?.addEventListener("click", subscribePrivatePush);
  disableBtn?.addEventListener("click", unsubscribePrivatePush);
})();
