/**
 * Mad Baits — mobile/PWA app update prompts (admin-controlled app version).
 */
(function () {
  "use strict";

  const config = window.madBaitsConfig || {};
  const appVersion = String(config.appVersion || "").trim();
  const storageKey = config.appVersionStorageKey || "madBaitsAppVersion";
  const pendingKey = config.appUpdatePendingKey || "madBaitsAppUpdatePending";
  const dismissedUntilKey = `${pendingKey}Until`;
  const shownSessionKey = `${pendingKey}Shown:${appVersion}`;
  const snoozeMs = Number(config.appUpdateSnoozeMs || 6 * 60 * 60 * 1000);
  const serverUnsafe = Boolean(config.appUpdateUnsafe);

  if (!appVersion) {
    return;
  }

  const isStandalone = () =>
    window.matchMedia("(display-mode: standalone)").matches || window.navigator.standalone === true;

  const isAppShellUser = () =>
    isStandalone() ||
    document.body.classList.contains("is-mobile-app-shell") ||
    document.body.classList.contains("is-pwa") ||
    document.documentElement.classList.contains("is-standalone-app");

  const isIosDevice = () => {
    const ua = window.navigator.userAgent || "";
    return (
      /iPad|iPhone|iPod/.test(ua) ||
      (window.navigator.platform === "MacIntel" && window.navigator.maxTouchPoints > 1)
    );
  };

  const safeGet = (key) => {
    try {
      return window.localStorage.getItem(key);
    } catch (_err) {
      return null;
    }
  };

  const safeSet = (key, value) => {
    try {
      window.localStorage.setItem(key, value);
    } catch (_err) {
      // no-op
    }
  };

  const safeRemove = (key) => {
    try {
      window.localStorage.removeItem(key);
    } catch (_err) {
      // no-op
    }
  };

  const safeSessionGet = (key) => {
    try {
      return window.sessionStorage.getItem(key);
    } catch (_err) {
      return null;
    }
  };

  const safeSessionSet = (key, value) => {
    try {
      window.sessionStorage.setItem(key, value);
    } catch (_err) {
      // no-op
    }
  };

  const safeSessionRemove = (key) => {
    try {
      window.sessionStorage.removeItem(key);
    } catch (_err) {
      // no-op
    }
  };

  const isPayPalActive = () =>
    Boolean(
      document.querySelector(
        '.paypal-buttons, .ppcp-button, .ppcp-button-wrapper, iframe[name*="paypal"], iframe[src*="paypal"]'
      )
    );

  const isBundleAdding = () => {
    const stickySubmit = document.querySelector("#mbbb-sticky-submit");
    if (stickySubmit instanceof HTMLElement && stickySubmit.getAttribute("aria-busy") === "true") {
      return true;
    }
    const drawerCta = document.querySelector("#mbbb-summary-drawer-cta");
    if (drawerCta instanceof HTMLElement && drawerCta.getAttribute("aria-busy") === "true") {
      return true;
    }
    return false;
  };

  const isUnsafeForRefresh = () => {
    if (serverUnsafe) {
      return true;
    }

    const path = (window.location.pathname || "").toLowerCase();
    if (/\/checkout\/?$/.test(path) || path.includes("/order-pay") || path.includes("/order-received")) {
      return true;
    }

    if (/\/product-category\//.test(path) || /\/product-tag\//.test(path) || /\/shop\/?$/.test(path)) {
      return true;
    }

    if (document.body.classList.contains("woocommerce-checkout")) {
      return true;
    }

    if (document.body.classList.contains("woocommerce-cart")) {
      return true;
    }

    if (document.querySelector("form.checkout, form.woocommerce-checkout")) {
      return true;
    }

    if (isBundleAdding()) {
      return true;
    }

    if (isPayPalActive()) {
      return true;
    }

    return false;
  };

  const shouldOfferUpdate = () => {
    if (!isAppShellUser()) {
      return false;
    }

    const dismissedUntil = Number(safeGet(dismissedUntilKey) || "0");
    if (dismissedUntil > Date.now()) {
      return false;
    }

    if (safeSessionGet(shownSessionKey) === "1") {
      return false;
    }

    const stored = safeGet(storageKey);
    const pending = safeGet(pendingKey);
    if (pending && pending === appVersion) {
      return true;
    }
    return Boolean(stored && stored !== appVersion);
  };

  const markPending = () => {
    safeSet(pendingKey, appVersion);
  };

  const markDismissedForSnoozeWindow = () => {
    safeSet(dismissedUntilKey, String(Date.now() + Math.max(0, snoozeMs)));
  };

  const clearPending = () => {
    safeRemove(pendingKey);
  };

  const clearDismissed = () => {
    safeRemove(dismissedUntilKey);
    safeSessionRemove(shownSessionKey);
  };

  const clearMadBaitsCaches = async () => {
    if (!("caches" in window)) {
      return;
    }
    const keys = await caches.keys();
    await Promise.all(
      keys.filter((name) => name.indexOf("mad-baits") === 0).map((name) => caches.delete(name))
    );
  };

  const skipWaitingWorker = async () => {
    if (!("serviceWorker" in navigator)) {
      return;
    }
    try {
      const registration = await navigator.serviceWorker.getRegistration();
      if (registration && registration.waiting) {
        registration.waiting.postMessage({ type: "SKIP_WAITING" });
      }
    } catch (_err) {
      // no-op
    }
  };

  const applyRefresh = async () => {
    await clearMadBaitsCaches();
    await skipWaitingWorker();
    safeSet(storageKey, appVersion);
    clearPending();
    clearDismissed();
    window.location.reload();
  };

  let modalEl = null;

  const getStoredVersion = () => {
    const stored = safeGet(storageKey);
    return stored && stored !== appVersion ? stored : "";
  };

  const buildModalCopy = () => {
    const previousVersion = getStoredVersion();
    const versionLine = previousVersion
      ? String(config.i18nAppUpdateVersion || "Updating from %1$s to %2$s")
          .replace("%1$s", previousVersion)
          .replace("%2$s", appVersion)
      : String(config.i18nAppUpdateVersionSingle || "New version: %s").replace("%s", appVersion);

    if (isStandalone()) {
      const steps = isIosDevice()
        ? config.i18nAppUpdateStepsIos ||
          "1. Swipe the Mad Baits app fully off the screen (App Switcher).\n2. Open Mad Baits again from your Home Screen.\n3. If anything still looks old, tap Refresh below."
        : config.i18nAppUpdateSteps ||
          "1. Close the Mad Baits app completely.\n2. Open it again from your home screen or app list.\n3. If anything still looks old, tap Refresh below.";

      return {
        title: config.i18nAppUpdateTitle || "App update required",
        lead:
          config.i18nAppUpdateLeadStandalone ||
          "A new version of the Mad Baits app is ready. Please fully close the app, then open it again.",
        steps,
        versionLine,
      };
    }

    return {
      title: config.i18nAppUpdateTitleBrowser || "Update available",
      lead:
        config.i18nAppUpdateLeadBrowser ||
        "A new version of Mad Baits is ready. Refresh to load the latest shop and bundle builder.",
      steps: config.i18nAppUpdateStepsBrowser || "Tap Refresh now. If the page still looks old, close the tab and open Mad Baits again.",
      versionLine,
    };
  };

  const hideUpdateModal = () => {
    if (!(modalEl instanceof HTMLElement)) {
      return;
    }
    modalEl.classList.remove("is-visible");
    modalEl.hidden = true;
    document.body.classList.remove("mad-pwa-update-open");
  };

  const showUpdateModal = (onRefresh) => {
    if (modalEl instanceof HTMLElement) {
      modalEl.hidden = false;
      modalEl.classList.add("is-visible");
      document.body.classList.add("mad-pwa-update-open");
      safeSessionSet(shownSessionKey, "1");
      return modalEl;
    }

    const copy = buildModalCopy();
    const stepsHtml = String(copy.steps)
      .split("\n")
      .filter(Boolean)
      .map((line) => `<li>${line.replace(/^\d+\.\s*/, "")}</li>`)
      .join("");

    modalEl = document.createElement("div");
    modalEl.className = "mad-pwa-update-modal mad-app-update-modal";
    modalEl.setAttribute("data-pwa-update-prompt", "true");
    modalEl.setAttribute("role", "dialog");
    modalEl.setAttribute("aria-modal", "true");
    modalEl.setAttribute("aria-labelledby", "mad-pwa-update-title");
    modalEl.innerHTML = `
      <button type="button" class="mad-pwa-update-modal__backdrop" data-pwa-update-backdrop tabindex="-1" aria-hidden="true"></button>
      <div class="mad-pwa-update-modal__panel" role="document">
        <p class="mad-pwa-update-modal__eyebrow">${config.i18nAppUpdateEyebrow || "Mad Baits app"}</p>
        <h2 class="mad-pwa-update-modal__title" id="mad-pwa-update-title">${copy.title}</h2>
        <p class="mad-pwa-update-modal__lead">${copy.lead}</p>
        <ol class="mad-pwa-update-modal__steps">${stepsHtml}</ol>
        <p class="mad-pwa-update-modal__version">${copy.versionLine}</p>
        <div class="mad-pwa-update__actions">
          <button type="button" class="mad-button mad-button--small" data-pwa-update-now>${config.i18nAppUpdateRefresh || "Refresh now"}</button>
          <button type="button" class="mad-button mad-button--ghost mad-button--small" data-pwa-update-later>${config.i18nAppUpdateLater || "Remind me later"}</button>
        </div>
      </div>
    `;

    const refreshBtn = modalEl.querySelector("[data-pwa-update-now]");
    const laterBtn = modalEl.querySelector("[data-pwa-update-later]");
    const backdrop = modalEl.querySelector("[data-pwa-update-backdrop]");

    if (refreshBtn instanceof HTMLButtonElement) {
      refreshBtn.addEventListener("click", () => {
        onRefresh();
      });
    }

    if (laterBtn instanceof HTMLButtonElement) {
      laterBtn.addEventListener("click", () => {
        markPending();
        markDismissedForSnoozeWindow();
        hideUpdateModal();
      });
    }

    if (backdrop instanceof HTMLButtonElement) {
      backdrop.addEventListener("click", () => {
        markPending();
        markDismissedForSnoozeWindow();
        hideUpdateModal();
      });
    }

    document.body.appendChild(modalEl);
    document.body.classList.add("mad-pwa-update-open");

    window.requestAnimationFrame(() => {
      modalEl?.classList.add("is-visible");
      safeSessionSet(shownSessionKey, "1");
      refreshBtn?.focus();
    });

    return modalEl;
  };

  const tryPromptUpdate = () => {
    if (modalEl instanceof HTMLElement && !modalEl.hidden) {
      return true;
    }

    if (!shouldOfferUpdate()) {
      if (!safeGet(storageKey)) {
        safeSet(storageKey, appVersion);
      }
      return false;
    }

    if (isUnsafeForRefresh()) {
      markPending();
      return false;
    }

    showUpdateModal(() => {
      applyRefresh();
    });
    return true;
  };

  const init = () => {
    if (!isAppShellUser()) {
      if (!safeGet(storageKey)) {
        safeSet(storageKey, appVersion);
      }
      return;
    }

    if (!safeGet(storageKey)) {
      safeSet(storageKey, appVersion);
      clearPending();
      clearDismissed();
      return;
    }

    tryPromptUpdate();
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }

  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "visible") {
      tryPromptUpdate();
    }
  });

  window.madBaitsAppUpdate = {
    appVersion,
    isStandalone: isStandalone(),
    isAppShellUser,
    isUnsafeForRefresh,
    shouldOfferUpdate,
    tryPromptUpdate,
    applyRefresh,
    showUpdateModal,
    hideUpdateModal,
  };
})();
