/**
 * Capacitor native shell helpers for Mad Baits (iOS/Android WebView).
 * Payment redirects open via Capacitor Browser; haptics via @capacitor/haptics.
 */
(() => {
  const cap = window.Capacitor;
  if (!cap || typeof cap.isNativePlatform !== "function" || !cap.isNativePlatform()) {
    return;
  }

  const platform = cap.getPlatform();
  const body = document.body;
  const root = document.documentElement;
  const plugins = cap.Plugins || {};
  const StatusBar = plugins.StatusBar;
  const SplashScreen = plugins.SplashScreen;
  const Browser = plugins.Browser;
  const App = plugins.App;
  const Haptics = plugins.Haptics;

  const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  root.classList.add("is-capacitor-app");
  body.classList.add("is-capacitor-app");
  if (platform === "ios") {
    root.classList.add("is-capacitor-ios");
    body.classList.add("is-capacitor-ios");
    body.classList.add("is-mobile-app-shell");
  }

  const PAYMENT_HOST_PATTERN =
    /(stripe\.com|stripe\.network|paypal\.com|paypalobjects\.com|sandbox\.paypal\.com|klarna\.com|klarnacdn\.net|pay\.google\.com|accounts\.google\.com)/i;

  const APP_HOST_PATTERN = /(^|\.)madbaits\.com$/i;

  const CHECKOUT_PATH_PATTERN = /\/(checkout|cart|order-pay|order-received)(\/|$)/i;

  const isAppOrigin = (hostname) => APP_HOST_PATTERN.test(String(hostname || "").replace(/^www\./, ""));

  const isCheckoutPage = () => CHECKOUT_PATH_PATTERN.test(window.location.pathname);

  const shouldOpenInBrowser = (url) => {
    if (!url || typeof url !== "string") {
      return false;
    }
    try {
      const parsed = new URL(url, window.location.href);
      if (parsed.protocol !== "http:" && parsed.protocol !== "https:") {
        return true;
      }
      const host = parsed.hostname.replace(/^www\./, "");
      if (isAppOrigin(host)) {
        return PAYMENT_HOST_PATTERN.test(parsed.href);
      }
      return true;
    } catch (_err) {
      return false;
    }
  };

  const openInBrowser = async (url) => {
    if (Browser && typeof Browser.open === "function") {
      await Browser.open({ url, presentationStyle: "fullscreen" });
      return;
    }
    window.location.assign(url);
  };

  const vibrateFallback = (pattern) => {
    if (typeof navigator === "undefined" || typeof navigator.vibrate !== "function") {
      return false;
    }
    try {
      return navigator.vibrate(pattern);
    } catch (_err) {
      return false;
    }
  };

  /**
   * Central haptic helper — Capacitor Haptics with vibrate fallback.
   * @param {'light'|'selection'|'success'|'warning'|'error'} type
   */
  const triggerHaptic = async (type = "light") => {
    if (reducedMotion) {
      return;
    }
    try {
      if (Haptics && typeof Haptics.impact === "function") {
        switch (type) {
          case "light":
            await Haptics.impact({ style: "LIGHT" });
            return;
          case "selection":
            if (typeof Haptics.selectionStart === "function") {
              await Haptics.selectionStart();
              await Haptics.selectionEnd();
              return;
            }
            await Haptics.impact({ style: "LIGHT" });
            return;
          case "success":
            if (typeof Haptics.notification === "function") {
              await Haptics.notification({ type: "SUCCESS" });
              return;
            }
            vibrateFallback([10, 24, 10]);
            return;
          case "warning":
            if (typeof Haptics.notification === "function") {
              await Haptics.notification({ type: "WARNING" });
              return;
            }
            vibrateFallback([12, 40, 12]);
            return;
          case "error":
            if (typeof Haptics.notification === "function") {
              await Haptics.notification({ type: "ERROR" });
              return;
            }
            vibrateFallback([16, 50, 16, 50]);
            return;
          default:
            await Haptics.impact({ style: "LIGHT" });
        }
        return;
      }
    } catch (_err) {
      // Fall through to vibrate.
    }
    const patterns = {
      light: [8],
      selection: [6],
      success: [10, 24, 10],
      warning: [12, 40, 12],
      error: [16, 50, 16, 50],
    };
    vibrateFallback(patterns[type] || patterns.light);
  };

  let splashHidden = false;
  const hideSplash = async () => {
    if (splashHidden || !SplashScreen || typeof SplashScreen.hide !== "function") {
      return;
    }
    splashHidden = true;
    body.classList.add("is-capacitor-splash-fading");
    try {
      if (SplashScreen.hide && SplashScreen.hide.length > 0) {
        await SplashScreen.hide({ fadeOutDuration: reducedMotion ? 0 : 280 });
      } else {
        await SplashScreen.hide();
      }
    } catch (_err) {
      splashHidden = false;
      body.classList.remove("is-capacitor-splash-fading");
    }
  };

  const hideSplashWhenReady = () => {
    let resolved = false;
    const runHide = () => {
      if (resolved) {
        return;
      }
      resolved = true;
      window.requestAnimationFrame(() => {
        window.setTimeout(() => {
          void hideSplash();
          document.body.dispatchEvent(new CustomEvent("mad_baits:app_ready"));
        }, reducedMotion ? 0 : 120);
      });
    };

    document.body.addEventListener("mad_baits:app_ready", runHide, { once: true });

    if (document.readyState === "complete") {
      window.setTimeout(runHide, 400);
    } else {
      window.addEventListener("load", () => window.setTimeout(runHide, 400), { once: true });
    }

    window.setTimeout(() => {
      void hideSplash();
    }, 10000);
  };

  const initChrome = async () => {
    try {
      if (StatusBar) {
        if (typeof StatusBar.setOverlaysWebView === "function") {
          await StatusBar.setOverlaysWebView({ overlay: false });
        }
        if (typeof StatusBar.setStyle === "function") {
          await StatusBar.setStyle({ style: "DARK" });
        }
        if (platform === "android" && typeof StatusBar.setBackgroundColor === "function") {
          await StatusBar.setBackgroundColor({ color: "#000000" });
        }
      }
    } catch (_err) {
      // Ignore status bar failures.
    }
    hideSplashWhenReady();
  };

  document.addEventListener(
    "click",
    (event) => {
      const target = event.target;
      if (!(target instanceof Element)) {
        return;
      }
      const anchor = target.closest("a[href]");
      if (!(anchor instanceof HTMLAnchorElement)) {
        return;
      }
      const href = anchor.getAttribute("href");
      if (!href || href.startsWith("#") || href.startsWith("javascript:") || href.startsWith("mailto:") || href.startsWith("tel:")) {
        return;
      }
      if (anchor.target === "_blank" || shouldOpenInBrowser(anchor.href)) {
        event.preventDefault();
        event.stopPropagation();
        void openInBrowser(anchor.href);
      }
    },
    true
  );

  document.addEventListener(
    "submit",
    (event) => {
      const form = event.target;
      if (!(form instanceof HTMLFormElement) || !form.action) {
        return;
      }
      if (shouldOpenInBrowser(form.action)) {
        event.preventDefault();
        void openInBrowser(form.action);
      }
    },
    true
  );

  const originalOpen = window.open.bind(window);
  window.open = (url, target, features) => {
    if (typeof url === "string" && shouldOpenInBrowser(url)) {
      void openInBrowser(url);
      return null;
    }
    return originalOpen(url, target, features);
  };

  if (App && typeof App.addListener === "function") {
    App.addListener("appStateChange", ({ isActive }) => {
      body.classList.toggle("is-capacitor-backgrounded", !isActive);
      if (isActive) {
        void hideSplash();
      }
    });

    App.addListener("appUrlOpen", (event) => {
      const url = event && event.url;
      if (typeof url === "string" && url.length > 0) {
        window.location.assign(url);
      }
    });
  }

  window.MadBaitsNative = {
    isNative: true,
    platform,
    triggerHaptic,
    hideSplash,
    isCheckoutPage,
  };

  void initChrome();
})();
