/**
 * Mad Baits mobile UX — haptics, offline polish, microinteractions.
 * Checkout/cart pages are excluded via PHP enqueue guard.
 */
(() => {
  const config = window.madBaitsMobileUxConfig || {};
  const body = document.body;
  const root = document.documentElement;

  if (!(body instanceof HTMLElement)) {
    return;
  }

  const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  const isMobileShell = body.classList.contains("is-mobile-app-shell");
  const isStandalone =
    root.classList.contains("is-standalone-app") ||
    body.classList.contains("is-standalone-app") ||
    body.classList.contains("is-pwa") ||
    window.matchMedia("(display-mode: standalone)").matches ||
    window.navigator.standalone === true;
  const isCheckoutContext = body.classList.contains("woocommerce-checkout") || body.classList.contains("woocommerce-cart");
  const hapticsEnabled = config.enableHaptics !== false && !reducedMotion;

  let lastNetworkState = navigator.onLine;
  let offlineBarEl = null;
  let pullState = null;
  let hapticDepth = 0;

  const logUx = (...args) => {
    if (window.MAD_BAITS_DEBUG === true) {
      console.info("[Mad Baits UX]", ...args);
    }
  };

  const haptic = (pattern = 12) => {
    if (!hapticsEnabled || hapticDepth > 0) {
      return false;
    }
    hapticDepth += 1;
    try {
      const nativeApi = window.MadBaitsNative;
      if (nativeApi && typeof nativeApi.triggerHaptic === "function") {
        const map = {
          8: "light",
          10: "light",
          12: "selection",
        };
        const type = map[pattern] || (Array.isArray(pattern) ? "success" : "selection");
        void nativeApi.triggerHaptic(type);
        return true;
      }
      if (typeof navigator === "undefined" || typeof navigator.vibrate !== "function") {
        return false;
      }
      const value = Array.isArray(pattern) ? pattern : [Math.max(8, Math.min(24, Number(pattern) || 12))];
      return navigator.vibrate(value);
    } catch (_err) {
      return false;
    } finally {
      hapticDepth -= 1;
    }
  };

  const isSafeInteractiveTarget = (target) => {
    if (!(target instanceof Element)) {
      return false;
    }
    return !target.closest(".woocommerce-checkout, .woocommerce-cart, .woocommerce-order-pay, .woocommerce-order-received");
  };

  const bindHaptics = () => {
    if (isCheckoutContext) {
      return;
    }

    document.body.addEventListener("mad_baits:item_added_to_cart", () => {
      haptic([10, 24, 10]);
    });

    if (typeof window.jQuery !== "undefined") {
      window.jQuery(document.body).on("added_to_cart", () => {
        haptic([10, 24, 10]);
      });
    }

    window.addEventListener("appinstalled", () => {
      haptic([12, 36, 12, 36]);
      logUx("Install success haptic.");
    });

    document.body.addEventListener("mad_baits:catch_report_submitted", () => {
      haptic([14, 28, 14]);
    });

    document.body.addEventListener("mad_baits:bundle_added", () => {
      haptic([10, 20, 10]);
    });

  document.addEventListener(
    "click",
    (event) => {
      const target = event.target;
      if (!(target instanceof Element) || !isSafeInteractiveTarget(target)) {
        return;
      }

      if (target.closest(".mbbb-product .single_add_to_cart_button, [data-mbbb-add], .mbbb-add-to-cart, .mbbb-choice-input + label")) {
        haptic(10);
      }

      if (target.closest("[data-catch-report-submit]")) {
        haptic(8);
      }
    },
    { passive: true }
  );
  };

  const ensureOfflineBar = () => {
    if (offlineBarEl instanceof HTMLElement) {
      return offlineBarEl;
    }

    offlineBarEl = document.createElement("aside");
    offlineBarEl.className = "mad-app-offline-bar is-hidden";
    offlineBarEl.setAttribute("role", "status");
    offlineBarEl.setAttribute("aria-live", "polite");
    offlineBarEl.innerHTML = `
      <div class="mad-app-offline-bar__inner">
        <span class="mad-app-offline-bar__icon" aria-hidden="true"></span>
        <div class="mad-app-offline-bar__copy">
          <strong>${config.i18nOffline || "You're off the grid"}</strong>
          <p>${config.i18nOfflineDetail || "Some features may be unavailable while signal is weak."}</p>
        </div>
        <button type="button" class="mad-app-offline-bar__retry mad-button" data-offline-retry>${config.i18nRetry || "Try again"}</button>
      </div>
    `;
    document.body.appendChild(offlineBarEl);

    const retryBtn = offlineBarEl.querySelector("[data-offline-retry]");
    if (retryBtn instanceof HTMLButtonElement) {
      retryBtn.addEventListener("click", () => {
        haptic(8);
        if (navigator.onLine) {
          window.location.reload();
          return;
        }
        retryBtn.textContent = config.i18nRetry || "Try again";
        retryBtn.disabled = false;
      });
    }

    return offlineBarEl;
  };

  const setOfflineBarVisible = (visible) => {
    const bar = ensureOfflineBar();
    bar.classList.toggle("is-hidden", !visible);
    bar.classList.toggle("is-visible", visible);
    body.classList.toggle("mad-app-is-offline", visible);
  };

  const initNetworkStatus = () => {
    const update = () => {
      const online = navigator.onLine;
      if (online === lastNetworkState) {
        return;
      }
      lastNetworkState = online;
      setOfflineBarVisible(!online);
      if (online) {
        haptic([8, 16]);
        if (typeof window.madBaitsShowAppToast === "function") {
          window.madBaitsShowAppToast(config.i18nReconnect || "Back online");
        }
        logUx("Back online.");
      } else {
        logUx("Offline.");
      }
    };

    window.addEventListener("online", update);
    window.addEventListener("offline", update);
    setOfflineBarVisible(!navigator.onLine);
  };

  const initPullToRefresh = () => {
    if (config.enablePullRefresh === false || reducedMotion || isCheckoutContext) {
      return;
    }
    if (!isMobileShell && !isStandalone) {
      return;
    }

    let startY = 0;
    let pulling = false;
    let indicator = document.querySelector("[data-app-pull-indicator]");
    if (!(indicator instanceof HTMLElement)) {
      indicator = document.createElement("div");
      indicator.dataset.appPullIndicator = "true";
      indicator.className = "mad-app-pull-indicator";
      indicator.setAttribute("aria-hidden", "true");
      indicator.innerHTML = `<span class="mad-app-pull-indicator__label">${config.i18nPullRefresh || "Refreshing latest bait drops…"}</span>`;
      document.body.prepend(indicator);
    }

    const resetPull = () => {
      pulling = false;
      pullState = null;
      indicator.classList.remove("is-active", "is-ready");
      indicator.style.setProperty("--mad-pull-progress", "0");
    };

    document.addEventListener(
      "touchstart",
      (event) => {
        if (window.scrollY > 4 || isCheckoutContext) {
          return;
        }
        if (!(event.touches && event.touches[0])) {
          return;
        }
        startY = event.touches[0].clientY;
        pulling = true;
      },
      { passive: true }
    );

    document.addEventListener(
      "touchmove",
      (event) => {
        if (!pulling || !(event.touches && event.touches[0])) {
          return;
        }
        const delta = event.touches[0].clientY - startY;
        if (delta <= 0) {
          resetPull();
          return;
        }
        const progress = Math.min(1, delta / 92);
        indicator.classList.add("is-active");
        indicator.style.setProperty("--mad-pull-progress", String(progress));
        indicator.classList.toggle("is-ready", progress >= 0.85);
      },
      { passive: true }
    );

    document.addEventListener(
      "touchend",
      () => {
        if (!pulling) {
          return;
        }
        const ready = indicator.classList.contains("is-ready");
        resetPull();
        if (ready) {
          if (window.MadBaitsNative && typeof window.MadBaitsNative.triggerHaptic === "function") {
            void window.MadBaitsNative.triggerHaptic("selection");
          } else {
            haptic(12);
          }
          window.location.reload();
        }
      },
      { passive: true }
    );
  };

  const initImageFallbacks = () => {
    const placeholderSvg =
      "data:image/svg+xml," +
      encodeURIComponent(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><rect width="120" height="120" fill="#0a0a0a"/><path d="M28 78l18-22 16 18 12-14 18 18H28z" fill="#222"/><circle cx="44" cy="42" r="8" fill="#333"/></svg>'
      );

    const attach = (img) => {
      if (!(img instanceof HTMLImageElement) || img.dataset.madImgFallback === "true") {
        return;
      }
      img.dataset.madImgFallback = "true";
      img.addEventListener("error", () => {
        img.classList.add("is-image-fallback");
        if (!img.getAttribute("src") || img.src !== placeholderSvg) {
          img.src = placeholderSvg;
        }
      });
    };

    document.querySelectorAll(".mad-product-card__image, .mad-catch-card__image img, .woocommerce-product-gallery__image img").forEach(attach);

    const observer = new MutationObserver((records) => {
      records.forEach((record) => {
        record.addedNodes.forEach((node) => {
          if (!(node instanceof Element)) {
            return;
          }
          if (node instanceof HTMLImageElement) {
            attach(node);
          }
          node.querySelectorAll("img").forEach(attach);
        });
      });
    });
    observer.observe(document.body, { childList: true, subtree: true });
  };

  const initLoadingSkeletons = () => {
    const regions = [
      { selector: ".product-grid, .woocommerce ul.products", delay: 520 },
      { selector: ".catch-reports-page__grid, [data-catch-reports-grid]", delay: 640 },
      { selector: ".woocommerce-account .woocommerce-MyAccount-content", delay: 480 },
      { selector: ".mbbb-product .summary.entry-summary, .mad-bundle-builder-pdp .summary", delay: 560 },
    ];

    regions.forEach(({ selector, delay }) => {
      document.querySelectorAll(selector).forEach((node) => {
        if (!(node instanceof HTMLElement)) {
          return;
        }
        node.classList.add("mad-skeleton-host");
        window.setTimeout(() => {
          node.classList.remove("mad-skeleton-host");
          node.classList.add("mad-content-ready");
        }, delay);
      });
    });
  };

  const initShellTransitions = () => {
    const content = document.querySelector("#content.site-main, main.site-main, .site-main");
    if (content instanceof HTMLElement) {
      content.classList.add("mad-app-shell-content");
      window.requestAnimationFrame(() => {
        content.classList.add("is-visible");
      });
    }
  };

  const initBottomNavActive = () => {
    const nav = document.querySelector("[data-app-bottom-nav]");
    if (!(nav instanceof HTMLElement)) {
      return;
    }
    const current = window.location.pathname.replace(/\/+$/, "") || "/";
    nav.querySelectorAll("[data-app-nav-item]").forEach((item) => {
      if (!(item instanceof HTMLElement)) {
        return;
      }
      const href = item.getAttribute("href");
      if (!href) {
        return;
      }
      let path = "";
      try {
        path = new URL(href, window.location.origin).pathname.replace(/\/+$/, "") || "/";
      } catch (_err) {
        return;
      }
      const active = path === current || (path !== "/" && current.startsWith(path));
      item.classList.toggle("is-active", active);
      if (active) {
        item.setAttribute("aria-current", "page");
      } else {
        item.removeAttribute("aria-current");
      }
    });
  };

  const initMicroInteractions = () => {
    if (reducedMotion) {
      return;
    }

    document.addEventListener(
      "pointerdown",
      (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
          return;
        }
        const interactive = target.closest(".mad-button, .button, .mad-app-bottom-nav__item, .mad-product-card, .mad-app-home__quick-card, .mad-app-exclusive-card");
        if (interactive instanceof HTMLElement) {
          interactive.classList.add("is-pressed");
        }
      },
      { passive: true }
    );

    const release = (event) => {
      const target = event.target;
      if (!(target instanceof Element)) {
        return;
      }
      target.querySelectorAll(".is-pressed").forEach((node) => node.classList.remove("is-pressed"));
      if (target.closest) {
        const pressed = target.closest(".is-pressed");
        if (pressed instanceof HTMLElement) {
          pressed.classList.remove("is-pressed");
        }
      }
      document.querySelectorAll(".is-pressed").forEach((node) => node.classList.remove("is-pressed"));
    };

    document.addEventListener("pointerup", release, { passive: true });
    document.addEventListener("pointercancel", release, { passive: true });
  };

  const initCatchReportSuccess = () => {
    if (document.querySelector(".mad-catch-submit__success, .catch-report-success, [data-catch-report-success]")) {
      document.body.dispatchEvent(new CustomEvent("mad_baits:catch_report_submitted"));
    }

    const form = document.querySelector("[data-catch-report-form]");
    if (form instanceof HTMLFormElement) {
      form.addEventListener(
        "submit",
        () => {
          window.setTimeout(() => {
            if (document.querySelector(".mad-catch-submit__success, [data-catch-report-success]")) {
              document.body.dispatchEvent(new CustomEvent("mad_baits:catch_report_submitted"));
            }
          }, 1200);
        },
        { passive: true }
      );
    }
  };

  const initAppExclusiveHomeCard = () => {
    const card = document.querySelector("[data-app-exclusive-home-card]");
    if (!(card instanceof HTMLElement)) {
      return;
    }

    const dismissKey = card.getAttribute("data-dismiss-key") || "madBaitsAppExclusiveHomeDismiss";
    try {
      if (localStorage.getItem(dismissKey) === "1") {
        return;
      }
    } catch (_err) {
      // Ignore storage failures.
    }

    card.hidden = false;
    card.classList.add("is-visible");

    const dismissBtn = card.querySelector("[data-app-exclusive-dismiss]");
    if (dismissBtn instanceof HTMLButtonElement) {
      dismissBtn.addEventListener("click", () => {
        card.classList.remove("is-visible");
        card.hidden = true;
        try {
          localStorage.setItem(dismissKey, "1");
        } catch (_err) {
          // Ignore storage failures.
        }
      });
    }
  };

  bindHaptics();
  initNetworkStatus();
  initPullToRefresh();
  initImageFallbacks();
  initLoadingSkeletons();
  initShellTransitions();
  initBottomNavActive();
  initMicroInteractions();
  initCatchReportSuccess();
  initAppExclusiveHomeCard();

  window.madBaitsMobileUx = {
    haptic,
    isStandalone,
    isMobileShell,
  };

  logUx("Mobile UX module ready.");
})();
