/**
 * Mad Baits native premium UX — haptics bridge, add-to-cart feedback, mini-cart sheet.
 * Must never crash the app — all init wrapped in try/catch with guards.
 */
(() => {
  const isDebugEnabled = () => {
    if (window.MAD_BAITS_DEBUG === true) {
      return true;
    }
    try {
      const params = new URLSearchParams(window.location.search || "");
      const urlFlag = params.get("mb_debug");
      if (urlFlag === "1" || urlFlag === "true") {
        return true;
      }
      return window.localStorage.getItem("madbaits_debug") === "1";
    } catch (_err) {
      return false;
    }
  };

  const log = (msg, detail) => {
    if (!isDebugEnabled()) {
      return;
    }
    if (detail !== undefined) {
      console.info(`[Mad Baits Premium] ${msg}`, detail);
      return;
    }
    console.info(`[Mad Baits Premium] ${msg}`);
  };

  const logError = (msg, err) => {
    console.warn(`[Mad Baits Premium] ${msg}`, err || "");
  };

  log("native premium script loaded");

  try {
    const params = new URLSearchParams(window.location.search);
    if (params.get("disableNativePremium") === "1") {
      log("native premium enhancements disabled by query parameter");
      return;
    }
  } catch (_err) {
    // Ignore URL parse failures.
  }

  if (window.MadBaitsNativePremiumLoaded === true) {
    log("init skipped — already loaded");
    return;
  }
  window.MadBaitsNativePremiumLoaded = true;

  const body = document.body;
  const root = document.documentElement;
  if (!(body instanceof HTMLElement)) {
    log("init skipped — body not ready");
    return;
  }

  log("native premium init started");

  const config = window.madBaitsMobileUxConfig || window.madBaitsConfig || {};
  const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  const isCheckout =
    body.classList.contains("woocommerce-checkout") ||
    body.classList.contains("woocommerce-cart") ||
    body.classList.contains("woocommerce-order-pay") ||
    body.classList.contains("woocommerce-order-received");

  let hapticDepth = 0;

  const vibrateFallback = (pattern) => {
    if (typeof navigator === "undefined" || typeof navigator.vibrate !== "function") {
      return false;
    }
    try {
      const value = Array.isArray(pattern) ? pattern : [Math.max(8, Math.min(24, Number(pattern) || 12))];
      return navigator.vibrate(value);
    } catch (_err) {
      return false;
    }
  };

  /**
   * Haptics — never call madBaitsMobileUx.haptic (circular with madBaitsTriggerHaptic).
   * @param {'light'|'selection'|'success'|'warning'|'error'} type
   */
  const triggerHaptic = (type = "light") => {
    if (config.enableHaptics === false || reducedMotion || hapticDepth > 0) {
      return;
    }
    hapticDepth += 1;
    try {
      const nativeApi = window.MadBaitsNative;
      if (nativeApi && typeof nativeApi.triggerHaptic === "function") {
        void nativeApi.triggerHaptic(type);
        return;
      }
      const patterns = {
        light: [8],
        selection: [6],
        success: [10, 24, 10],
        warning: [12, 40, 12],
        error: [16, 50, 16, 50],
      };
      vibrateFallback(patterns[type] || patterns.light);
    } finally {
      hapticDepth -= 1;
    }
  };

  const toastMessage =
    config.i18nAddedToBasket || config.i18nAddedToBucket || config.i18nAdded || "Added to your basket";

  window.madBaitsTriggerHaptic = triggerHaptic;

  const pulseCartBadges = () => {
    if (reducedMotion) {
      return;
    }
    document.querySelectorAll("[data-app-cart-count], [data-app-top-cart-count]").forEach((node) => {
      if (!(node instanceof HTMLElement)) {
        return;
      }
      node.classList.remove("is-pulse");
      void node.offsetWidth;
      node.classList.add("is-pulse");
    });
  };

  const setButtonState = (button, state) => {
    if (!(button instanceof HTMLElement)) {
      return;
    }
    button.classList.remove("is-atc-loading", "is-atc-success", "is-atc-error");
    if (state) {
      button.classList.add(`is-atc-${state}`);
    }
    if (state === "loading") {
      button.setAttribute("aria-busy", "true");
      if (button instanceof HTMLButtonElement) {
        button.disabled = true;
      }
    } else {
      button.removeAttribute("aria-busy");
    }
  };

  const runSuccessFeedback = (button) => {
    triggerHaptic("success");
    pulseCartBadges();
    if (!(button instanceof HTMLElement)) {
      return;
    }
    const original = button.getAttribute("data-original-label") || button.textContent || "";
      if (!button.getAttribute("data-original-label")) {
        button.setAttribute("data-original-label", original);
      }
      if (button.dataset.madbaitsEnhanced !== "true") {
        button.dataset.madbaitsEnhanced = "true";
      }
      setButtonState(button, "success");
      if (button instanceof HTMLButtonElement || button instanceof HTMLInputElement) {
        const successLabel = config.i18nAddedShort || "✓ Added";
        if (button.tagName === "INPUT") {
          button.value = successLabel;
        } else {
          button.textContent = successLabel;
        }
      }
      window.setTimeout(() => {
        setButtonState(button, null);
        if (button instanceof HTMLButtonElement) {
          button.disabled = false;
          button.textContent = original;
        } else if (button instanceof HTMLInputElement) {
          button.disabled = false;
          button.value = original;
        }
      }, 1400);
  };

  let addToCartBound = false;
  let miniCartBound = false;
  let catchReportBound = false;
  let appReadySignalled = false;

  const bindAddToCartPremium = () => {
    if (isCheckout || addToCartBound) {
      return;
    }
    addToCartBound = true;

    document.body.addEventListener("mad_baits:item_added_to_cart", () => {
      runSuccessFeedback(null);
    });

    if (typeof window.jQuery !== "undefined") {
      window.jQuery(document.body).on("adding_to_cart.premium", (_e, _fragments, _hash, button) => {
        const btn = button && button[0] ? button[0] : null;
        if (btn instanceof HTMLElement) {
          if (!btn.getAttribute("data-original-label")) {
            btn.setAttribute("data-original-label", btn.textContent || btn.value || "");
          }
          btn.dataset.madbaitsEnhanced = "true";
          setButtonState(btn, "loading");
        }
      });

      window.jQuery(document.body).on("added_to_cart.premium", (_e, _fragments, _hash, button) => {
        const btn = button && button[0] ? button[0] : null;
        runSuccessFeedback(btn);
      });

      window.jQuery(document.body).on("wc_cart_button_updated.premium", (_e, button) => {
        const btn = button instanceof HTMLElement ? button : null;
        if (btn && btn.classList.contains("is-atc-loading")) {
          setButtonState(btn, null);
        }
      });
    }

    document.addEventListener(
      "click",
      (event) => {
        const target = event.target;
        if (!(target instanceof Element) || isCheckout) {
          return;
        }
        if (target.closest(".mad-app-bottom-nav__item")) {
          triggerHaptic("selection");
        }
        if (target.closest("[data-favourite], .mad-favourite, .wishlist-toggle, .yith-wcwl-add-to-wishlist a")) {
          triggerHaptic("light");
        }
      },
      { passive: true }
    );
  };

  const initMiniCartSheet = () => {
    if (miniCartBound) {
      return;
    }
    const shell = document.querySelector("[data-mini-cart-drawer]");
    if (!(shell instanceof HTMLElement)) {
      return;
    }
    const panel = shell.querySelector(".mad-mini-cart-drawer");
    if (!(panel instanceof HTMLElement) || panel.dataset.madbaitsMiniCartBound === "true") {
      return;
    }
    panel.dataset.madbaitsMiniCartBound = "true";
    miniCartBound = true;

    let touchStartY = 0;
    let dragging = false;

    panel.addEventListener(
      "touchstart",
      (event) => {
        if (!(event.touches && event.touches[0]) || panel.scrollTop > 4) {
          return;
        }
        touchStartY = event.touches[0].clientY;
        dragging = true;
      },
      { passive: true }
    );

    panel.addEventListener(
      "touchmove",
      (event) => {
        if (!dragging || !(event.touches && event.touches[0])) {
          return;
        }
        const delta = event.touches[0].clientY - touchStartY;
        if (delta > 0) {
          panel.style.transform = `translateY(${Math.min(delta, 120)}px)`;
        }
      },
      { passive: true }
    );

    panel.addEventListener(
      "touchend",
      (event) => {
        if (!dragging) {
          return;
        }
        dragging = false;
        const endY = event.changedTouches && event.changedTouches[0] ? event.changedTouches[0].clientY : touchStartY;
        const delta = endY - touchStartY;
        panel.style.transform = "";
        if (delta > 72) {
          const closeBtn = shell.querySelector("[data-mini-cart-close]");
          if (closeBtn instanceof HTMLElement) {
            closeBtn.click();
          }
        }
      },
      { passive: true }
    );
  };

  const signalAppReady = () => {
    if (appReadySignalled) {
      return;
    }
    const fire = () => {
      if (appReadySignalled) {
        return;
      }
      appReadySignalled = true;
      document.body.dispatchEvent(new CustomEvent("mad_baits:app_ready"));
    };
    if (document.readyState === "complete") {
      window.requestAnimationFrame(fire);
    } else {
      window.addEventListener("load", () => window.requestAnimationFrame(fire), { once: true });
    }
  };

  const initCatchReportPolish = () => {
    if (catchReportBound) {
      return;
    }
    const wrap = document.querySelector("[data-catch-upload-form-wrap]");
    if (!(wrap instanceof HTMLElement) || wrap.dataset.madbaitsCatchBound === "true") {
      return;
    }
    wrap.dataset.madbaitsCatchBound = "true";
    catchReportBound = true;

    const fileInput = wrap.querySelector("[data-catch-photo-input], input[type=\"file\"][name=\"catch_images[]\"]");
    const submitBtn = wrap.querySelector("[data-catch-report-submit]");
    const form = wrap.querySelector("[data-catch-report-form]");
    const progress = wrap.querySelector("[data-catch-upload-progress]");
    const cameraBtn = wrap.querySelector("[data-catch-take-photo]");
    const libraryBtn = wrap.querySelector("[data-catch-choose-library]");

    const openPickerFallback = (capture) => {
      if (!(fileInput instanceof HTMLInputElement)) {
        return;
      }
      if (capture) {
        fileInput.setAttribute("capture", "environment");
      } else {
        fileInput.removeAttribute("capture");
      }
      fileInput.click();
    };

    const openPicker = async (capture) => {
      if (!(fileInput instanceof HTMLInputElement)) {
        return;
      }
      const nativeApi = window.MadBaitsNative;
      if (nativeApi && typeof nativeApi.pickCatchPhoto === "function") {
        if (progress instanceof HTMLElement) {
          progress.hidden = false;
          progress.setAttribute("aria-hidden", "false");
        }
        try {
          const result = await nativeApi.pickCatchPhoto(capture ? "camera" : "photos", fileInput);
          if (result && result.ok) {
            triggerHaptic("light");
          } else if (result && result.reason && result.reason !== "cancelled") {
            triggerHaptic("warning");
            openPickerFallback(capture);
          }
        } catch (_err) {
          openPickerFallback(capture);
        } finally {
          if (progress instanceof HTMLElement) {
            progress.hidden = true;
          }
        }
        return;
      }
      openPickerFallback(capture);
    };

    if (cameraBtn instanceof HTMLButtonElement) {
      cameraBtn.addEventListener("click", () => {
        void openPicker(true);
      });
    }
    if (libraryBtn instanceof HTMLButtonElement) {
      libraryBtn.addEventListener("click", () => {
        void openPicker(false);
      });
    }

    if (form instanceof HTMLFormElement && submitBtn instanceof HTMLButtonElement) {
      form.addEventListener("submit", () => {
        submitBtn.disabled = true;
        submitBtn.classList.add("is-atc-loading");
        submitBtn.textContent = config.i18nCatchSubmitting || "Submitting…";
        if (progress instanceof HTMLElement) {
          progress.hidden = false;
          progress.setAttribute("aria-hidden", "false");
        }
      });
    }

    document.body.addEventListener("mad_baits:catch_report_submitted", () => {
      triggerHaptic("success");
      if (submitBtn instanceof HTMLButtonElement) {
        submitBtn.classList.remove("is-atc-loading");
        submitBtn.classList.add("is-atc-success");
        submitBtn.textContent = config.i18nCatchSuccess || "Catch submitted!";
      }
      if (progress instanceof HTMLElement) {
        progress.hidden = true;
      }
    });
  };

  try {
    bindAddToCartPremium();
    initMiniCartSheet();
    initCatchReportPolish();
    signalAppReady();

    window.madBaitsPremiumUx = {
      triggerHaptic,
      pulseCartBadges,
      toastMessage,
    };

    log("native premium init complete");
  } catch (err) {
    logError("native premium init failed", err);
  }
})();
