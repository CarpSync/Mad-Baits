(function () {
  const config = window.madBaitsPushConfig || window.madBaitsPushAdmin;
  if (!config) return;
  if (!config.serviceWorkerUrl && window.madBaitsConfig && window.madBaitsConfig.pwaServiceWorkerUrl) {
    config.serviceWorkerUrl = window.madBaitsConfig.pwaServiceWorkerUrl;
  }

  const SUPPORTS_PUSH = "serviceWorker" in navigator && "PushManager" in window && "Notification" in window;
  const DISMISS_TTL_MS = 7 * 24 * 60 * 60 * 1000;
  const LAST_ERROR_KEY = "madBaitsPushLastSubscribeError";
  const REQUEST_TIMEOUT_MS = 8000;
  const ACTION_TIMEOUT_MS = 12000;
  async function fetchJsonWithTimeout(url, options = {}, timeoutMs = REQUEST_TIMEOUT_MS) {
    const controller = typeof AbortController !== "undefined" ? new AbortController() : null;
    const timer = controller ? window.setTimeout(() => controller.abort(), timeoutMs) : null;
    try {
      const response = await fetch(url, {
        ...options,
        ...(controller ? { signal: controller.signal } : {}),
      });
      const data = await response.json().catch(() => null);
      return { ok: response.ok, data, status: response.status };
    } finally {
      if (timer) {
        window.clearTimeout(timer);
      }
    }
  }


  function logPush(label, value) {
    if (typeof value === "undefined") {
      console.info(`[PWA Push] ${label}`);
      return;
    }
    console.info(`[PWA Push] ${label}`, value);
  }

  function isPwaDisplayMode() {
    const mqStandalone = window.matchMedia && window.matchMedia("(display-mode: standalone)").matches;
    const mqFullscreen = window.matchMedia && window.matchMedia("(display-mode: fullscreen)").matches;
    const navStandalone = window.navigator.standalone === true;
    const androidTwa = (document.referrer || "").startsWith("android-app://");
    return (
      mqStandalone || mqFullscreen || navStandalone || androidTwa
    );
  }

  function urlBase64ToUint8Array(base64String) {
    const padding = "=".repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, "+").replace(/_/g, "/");
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; i += 1) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
  }

  function isIos() {
    const ua = navigator.userAgent || "";
    return /iPad|iPhone|iPod/.test(ua) || (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1);
  }

  function hasActiveServiceWorker(registration) {
    return Boolean(registration && registration.active);
  }

  function waitForUsableServiceWorker(registration, timeoutMs = ACTION_TIMEOUT_MS) {
    if (!registration) {
      return Promise.reject(new Error("No service worker registration available."));
    }
    if (hasActiveServiceWorker(registration)) {
      return Promise.resolve(registration);
    }

    return new Promise((resolve, reject) => {
      const startedAt = Date.now();

      const check = () => {
        if (hasActiveServiceWorker(registration)) {
          cleanup();
          resolve(registration);
          return true;
        }
        if (Date.now() - startedAt >= timeoutMs) {
          cleanup();
          reject(new Error("Service worker setup timed out."));
          return true;
        }
        return false;
      };

      const onUpdateFound = () => {
        const worker = registration.installing;
        if (!worker) return;
        worker.addEventListener("statechange", check);
      };

      const interval = window.setInterval(check, 250);
      registration.addEventListener("updatefound", onUpdateFound);
      onUpdateFound();
      check();

      function cleanup() {
        window.clearInterval(interval);
        registration.removeEventListener("updatefound", onUpdateFound);
      }
    });
  }

  async function getServiceWorkerRegistration() {
    const existingScoped = await navigator.serviceWorker.getRegistration("/");
    const registration = existingScoped || (await navigator.serviceWorker.register(config.serviceWorkerUrl, { scope: "/" }));
    registration.update().catch(() => {});

    const activeRegistration = await waitForUsableServiceWorker(registration, ACTION_TIMEOUT_MS * 2);
    try {
      const readyRegistration = await withTimeout(
        navigator.serviceWorker.ready,
        ACTION_TIMEOUT_MS * 2,
        "Service worker ready timed out."
      );
      return readyRegistration || activeRegistration;
    } catch (_error) {
      return activeRegistration;
    }
  }

  async function getPushStatus() {
    try {
      const response = await fetchJsonWithTimeout(config.restRoot + "status", { credentials: "same-origin" });
      if (!response.ok) return null;
      return response.data;
    } catch (error) {
      return null;
    }
  }

  async function postJson(url, payload, useNonce) {
    const headers = { "Content-Type": "application/json" };
    if (useNonce && config.restNonce) {
      headers["X-WP-Nonce"] = config.restNonce;
    }
    const response = await fetchJsonWithTimeout(url, {
      method: "POST",
      credentials: "same-origin",
      headers,
      body: JSON.stringify(payload),
    });
    if (response && response.data) {
      return response.data;
    }
    return { success: false, message: "Network timeout while contacting push API." };
  }

  async function getExistingSubscription() {
    if (!SUPPORTS_PUSH) {
      return null;
    }
    try {
      const registration = await withTimeout(
        getServiceWorkerRegistration(),
        ACTION_TIMEOUT_MS,
        "Service worker check timed out."
      );
      return await withTimeout(
        registration.pushManager.getSubscription(),
        ACTION_TIMEOUT_MS,
        "Subscription check timed out."
      );
    } catch (_error) {
      return null;
    }
  }

  function setPromptMessage(prompt, message, isError) {
    const statusEl = prompt.querySelector(".mad-push-prompt__status");
    if (!statusEl) return;
    statusEl.textContent = message || "";
    statusEl.classList.toggle("is-error", Boolean(isError));
    statusEl.classList.toggle("is-success", !isError && Boolean(message));
  }

  function getDiagnosticsSnapshot(subscription, statusResponse) {
    const standalone = isPwaDisplayMode();
    const permission = "Notification" in window ? Notification.permission : "unsupported";
    return {
      ios: isIos(),
      standalone,
      secureContext: window.isSecureContext,
      supportsServiceWorker: "serviceWorker" in navigator,
      supportsNotification: "Notification" in window,
      supportsPushManager: "PushManager" in window,
      permission,
      hasSubscription: Boolean(subscription && subscription.endpoint),
      hasStatusEndpoint: Boolean(statusResponse && statusResponse.success),
      lastSubscribeError: localStorage.getItem(LAST_ERROR_KEY) || "",
    };
  }

  async function logDiagnostics(statusResponse, subscribeResponse) {
    const subscription = await getExistingSubscription();
    const swReady = await navigator.serviceWorker.ready.then(() => true).catch(() => false);
    logPush("running in standalone:", isPwaDisplayMode());
    logPush("notification permission:", "Notification" in window ? Notification.permission : "unsupported");
    logPush("service worker ready:", swReady);
    logPush("existing subscription:", Boolean(subscription && subscription.endpoint));
    if (subscribeResponse) {
      logPush("subscribe response:", subscribeResponse);
    }
    if (statusResponse) {
      const snapshot = getDiagnosticsSnapshot(subscription, statusResponse);
      logPush("diagnostics snapshot:", snapshot);
    }
  }

  async function withTimeout(promise, timeoutMs, timeoutMessage) {
    let timerId = null;
    const timeoutPromise = new Promise((_, reject) => {
      timerId = window.setTimeout(() => {
        reject(new Error(timeoutMessage || "Operation timed out."));
      }, timeoutMs);
    });
    try {
      return await Promise.race([promise, timeoutPromise]);
    } finally {
      if (timerId) {
        window.clearTimeout(timerId);
      }
    }
  }

  async function subscribeToPush() {
    if (!SUPPORTS_PUSH) {
      const response = {
        success: false,
        message:
          "Push is not available in this context. Open the installed Mad Baits app and allow notifications when prompted.",
      };
      localStorage.setItem(LAST_ERROR_KEY, response.message);
      return response;
    }

    try {
      const status = await getPushStatus();
      const publicKey = (status && status.public_key) || config.publicVapidKey || "";
      if (!publicKey) {
        return { success: false, message: "Push key not configured in admin settings." };
      }

      const iOSDevice = isIos();
      const standalone = isPwaDisplayMode();
      if (iOSDevice && !standalone) {
        const response = {
          success: false,
          message: "Install Mad Baits to your Home Screen and open the app to enable notifications.",
        };
        localStorage.setItem(LAST_ERROR_KEY, response.message);
        await logDiagnostics(status, response);
        return response;
      }

      const registration = await withTimeout(
        getServiceWorkerRegistration(),
        ACTION_TIMEOUT_MS,
        "Service worker setup timed out."
      );

      const permission =
        Notification.permission === "granted"
          ? "granted"
          : await withTimeout(
              Notification.requestPermission(),
              ACTION_TIMEOUT_MS,
              "Permission request timed out."
            );
      if (permission !== "granted") {
        const response = iOSDevice
          ? {
              success: false,
              message:
                "Notifications are blocked. Enable them in iPhone Settings > Notifications > Mad Baits.",
            }
          : { success: false, message: "Notifications are not allowed on this browser." };
        localStorage.setItem(LAST_ERROR_KEY, response.message);
        await logDiagnostics(status, response);
        return response;
      }

      let subscription = null;
      try {
        subscription = await withTimeout(
          registration.pushManager.getSubscription(),
          ACTION_TIMEOUT_MS,
          "Subscription check timed out."
        );
      } catch (error) {
        const needsReadyRegistration =
          error &&
          typeof error.message === "string" &&
          error.message.toLowerCase().includes("service worker");
        if (!needsReadyRegistration) {
          throw error;
        }
        const readyRegistration = await withTimeout(
          navigator.serviceWorker.ready,
          ACTION_TIMEOUT_MS * 2,
          "Service worker ready timed out."
        );
        subscription = await withTimeout(
          readyRegistration.pushManager.getSubscription(),
          ACTION_TIMEOUT_MS,
          "Subscription check timed out."
        );
      }
      if (!subscription) {
        const registrationForSubscribe =
          (await withTimeout(
            navigator.serviceWorker.ready,
            ACTION_TIMEOUT_MS * 2,
            "Service worker ready timed out."
          ).catch(() => registration)) || registration;
        subscription = await withTimeout(
          registrationForSubscribe.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(publicKey),
          }),
          ACTION_TIMEOUT_MS,
          "Subscription creation timed out."
        );
      }

      const subscriptionJson = subscription.toJSON();
      const payload = {
        endpoint: subscription.endpoint,
        keys: subscriptionJson.keys || {},
        permission,
        is_pwa: isPwaDisplayMode(),
        device_label: navigator.platform || "Unknown device",
        user_agent: navigator.userAgent || "",
      };
      const response = await postJson(config.restRoot + "subscribe", payload, Boolean(config.isLoggedIn));
      logPush("subscribe response:", response);
      if (response && response.success) {
        localStorage.removeItem(LAST_ERROR_KEY);
      } else {
        localStorage.setItem(LAST_ERROR_KEY, String((response && response.message) || "Unknown subscribe error"));
      }
      await logDiagnostics(status, response);
      return response;
    } catch (error) {
      const message = error && error.message ? String(error.message) : "Subscription failed unexpectedly.";
      const response = { success: false, message };
      localStorage.setItem(LAST_ERROR_KEY, message);
      return response;
    }
  }

  async function shouldShowPrompt(options = {}) {
    const forceStandalonePrompt = Boolean(options.forceStandalonePrompt);
    const dismissedAt = Number(localStorage.getItem(config.dismissKey || "madBaitsPushPromptDismissedAt") || 0);
    if (dismissedAt > 0 && Date.now() - dismissedAt < DISMISS_TTL_MS) return false;

    if (forceStandalonePrompt) return true;
    if (!SUPPORTS_PUSH) return false;
    if (document.body.classList.contains("woocommerce-cart") || document.body.classList.contains("woocommerce-checkout")) return false;
    const subscription = await getExistingSubscription();
    if (subscription && subscription.endpoint) return false;
    return true;
  }

  function buildPromptMarkup(options = {}) {
    const isStandalone = Boolean(options.isStandalone);
    const wrapper = document.createElement("aside");
    wrapper.className = "mad-push-prompt";
    wrapper.innerHTML = `
      <p class="mad-push-prompt__eyebrow">Mad Baits Alerts</p>
      <h3>Get Mad Baits alerts</h3>
      <p class="mad-push-prompt__message">New drops, restocks, deals and bait updates straight to your phone.</p>
      <div class="mad-push-prompt__actions">
        <button type="button" class="mad-push-prompt__enable">Enable alerts</button>
        <button type="button" class="mad-push-prompt__dismiss">Not now</button>
      </div>
      ${
        isStandalone
          ? '<button type="button" class="mad-push-prompt__force">Enable Mad Baits notifications</button>'
          : ""
      }
      <p class="mad-push-prompt__status" aria-live="polite"></p>
    `;
    return wrapper;
  }

  function installSheetIsOpen() {
    const sheet = document.querySelector("[data-mad-install-sheet]");
    return sheet instanceof HTMLElement && sheet.classList.contains("is-open");
  }

  function waitForInstallSheetToClose() {
    return new Promise((resolve) => {
      if (!installSheetIsOpen()) {
        resolve();
        return;
      }
      const onClosed = () => {
        document.removeEventListener("mad:install-sheet-closed", onClosed);
        window.setTimeout(resolve, 5000);
      };
      document.addEventListener("mad:install-sheet-closed", onClosed);
    });
  }

  async function initFrontendPrompt() {
    if (window.madBaitsPushAdmin) return;
    if (document.body.classList.contains("woocommerce-cart") || document.body.classList.contains("woocommerce-checkout")) {
      return;
    }

    await waitForInstallSheetToClose();

    const standalone = isPwaDisplayMode();
    const forceIosPrompt = isIos();
    const forceStandalonePrompt = standalone || forceIosPrompt;
    const status = await getPushStatus();
    if (!(await shouldShowPrompt({ forceStandalonePrompt }))) return;
    if ((!status || !status.push_enabled) && !standalone) return;
    if (installSheetIsOpen()) return;
    await logDiagnostics(status);

    const prompt = buildPromptMarkup({ isStandalone: standalone });
    document.body.appendChild(prompt);
    if (!SUPPORTS_PUSH) {
      setPromptMessage(
        prompt,
        "Push APIs are unavailable right now. Confirm you opened the Home Screen app, not a Safari tab.",
        true
      );
    } else if (isIos() && !standalone) {
      setPromptMessage(
        prompt,
        "Open Mad Baits from your Home Screen icon to trigger iPhone notification permission.",
        true
      );
    }

    const dismiss = () => {
      localStorage.setItem(config.dismissKey || "madBaitsPushPromptDismissedAt", String(Date.now()));
      prompt.remove();
    };

    prompt.querySelector(".mad-push-prompt__dismiss")?.addEventListener("click", dismiss);
    prompt.querySelector(".mad-push-prompt__enable")?.addEventListener("click", async () => {
      prompt.classList.add("is-loading");
      setPromptMessage(prompt, "", false);
      let shouldClosePrompt = false;
      try {
        const result = await subscribeToPush();
        if (result && result.success) {
          setPromptMessage(prompt, "Alerts enabled on this device.", false);
          prompt.classList.add("is-success");
          localStorage.setItem(config.dismissKey || "madBaitsPushPromptDismissedAt", String(Date.now()));
          shouldClosePrompt = true;
        } else if (result && result.message) {
          setPromptMessage(prompt, String(result.message), true);
        }
      } catch (error) {
        const message = error && error.message ? String(error.message) : "Could not enable alerts. Please try again.";
        setPromptMessage(prompt, message, true);
      } finally {
        prompt.classList.remove("is-loading");
        if (shouldClosePrompt) {
          setTimeout(() => prompt.remove(), 950);
        }
      }
    });

    prompt.querySelector(".mad-push-prompt__force")?.addEventListener("click", async () => {
      prompt.classList.add("is-loading");
      setPromptMessage(prompt, "", false);
      localStorage.removeItem(config.dismissKey || "madBaitsPushPromptDismissedAt");
      try {
        const result = await subscribeToPush();
        if (result && result.success) {
          setPromptMessage(prompt, "Alerts enabled on this device.", false);
          prompt.classList.add("is-success");
          localStorage.setItem(config.dismissKey || "madBaitsPushPromptDismissedAt", String(Date.now()));
          setTimeout(() => prompt.remove(), 950);
        } else if (result && result.message) {
          setPromptMessage(prompt, String(result.message), true);
        }
      } catch (error) {
        const message = error && error.message ? String(error.message) : "Could not enable alerts. Please try again.";
        setPromptMessage(prompt, message, true);
      }
      prompt.classList.remove("is-loading");
    });
  }

  function applyTemplate(form, key) {
    const templates = {
      drop: {
        title: "New Mad Baits drop",
        message: "Fresh bait has landed. Tap to shop the latest range.",
        url: "/shop/",
      },
      weekend: {
        title: "Weekend deal live",
        message: "Limited-time Mad Baits offer now live. Don't miss it.",
        url: "/shop/?orderby=popularity",
      },
      restock: {
        title: "Restock alert",
        message: "A top-selling range is back in stock. Tap to secure yours.",
        url: "/shop/",
      },
      last_chance: {
        title: "Last chance offer",
        message: "Final hours on this Mad Baits offer. Shop before it ends.",
        url: "/shop/",
      },
    };
    const template = templates[key];
    if (!template) return;

    form.querySelector('input[name="title"]').value = template.title;
    form.querySelector('textarea[name="message"]').value = template.message;
    form.querySelector('input[name="url"]').value = template.url.startsWith("http")
      ? template.url
      : window.location.origin + template.url;
  }

  async function getCurrentEndpoint() {
    if (!SUPPORTS_PUSH) return "";
    const registration = await getServiceWorkerRegistration();
    const subscription = await registration.pushManager.getSubscription();
    return subscription && subscription.endpoint ? subscription.endpoint : "";
  }

  function initAdminPortal() {
    if (!window.madBaitsPushAdmin) return;
    const form = document.getElementById("mad-push-send-form");
    const statusEl = document.getElementById("mad-push-send-status");
    if (!form || !statusEl) return;

    document.querySelectorAll("[data-mad-push-template]").forEach((button) => {
      button.addEventListener("click", () => applyTemplate(form, button.getAttribute("data-mad-push-template")));
    });

    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const formData = new FormData(form);
      const audience = String(formData.get("audience") || "all");
      if (audience === "all" && !window.confirm("Send to all active subscribers?")) {
        return;
      }

      statusEl.textContent = "Sending...";
      const payload = {
        title: String(formData.get("title") || ""),
        message: String(formData.get("message") || ""),
        url: String(formData.get("url") || ""),
        image: String(formData.get("image") || ""),
        audience,
        force: false,
      };

      try {
        let result = await postJson(config.restRoot + "send", payload, true);
        if (result && result.success === false && String(result.message || "").toLowerCase().includes("rate limited")) {
          if (window.confirm("A send happened recently. Send again anyway?")) {
            payload.force = true;
            result = await postJson(config.restRoot + "send", payload, true);
          }
        }
        if (result && result.success) {
          const info = result.result || {};
          statusEl.textContent = `Sent: ${info.sent || 0}, failed: ${info.failed || 0}, attempted: ${info.attempted || 0}`;
        } else {
          statusEl.textContent = result && result.message ? result.message : "Send failed.";
        }
      } catch (error) {
        statusEl.textContent = "Send failed due to a network/server error.";
      }
    });

    const testButton = document.getElementById("mad-push-send-test");
    testButton?.addEventListener("click", async () => {
      statusEl.textContent = "Preparing browser test endpoint...";
      const endpoint = await getCurrentEndpoint();
      if (!endpoint) {
        statusEl.textContent = "No browser subscription found. Enable alerts first on this browser.";
        return;
      }

      const formData = new FormData(form);
      const payload = {
        title: String(formData.get("title") || "Mad Baits test notification"),
        message: String(formData.get("message") || "Push test from Mad Baits admin portal."),
        url: String(formData.get("url") || window.location.origin + "/shop/"),
        image: String(formData.get("image") || ""),
        audience: "test",
        test_endpoint: endpoint,
        force: false,
      };
      const result = await postJson(config.restRoot + "send", payload, true);
      statusEl.textContent = result && result.success ? "Test notification sent." : "Test send failed.";
    });
  }

  document.addEventListener("DOMContentLoaded", () => {
    initFrontendPrompt();
    initAdminPortal();
  });
})();
