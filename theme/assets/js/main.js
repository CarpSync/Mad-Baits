(() => {
  window.__madBaitsMainInitCount = (window.__madBaitsMainInitCount || 0) + 1;
  const mainInitInstance = window.__madBaitsMainInitCount;
  if (mainInitInstance > 1) {
    console.warn("[Mad Baits] Duplicate main.js init blocked", { count: mainInitInstance });
    return;
  }

  const resolveMadBaitsDebug = () => {
    try {
      const params = new URLSearchParams(window.location.search || "");
      const urlFlag = params.get("mb_debug");
      if (urlFlag === "1" || urlFlag === "true") {
        try {
          window.localStorage.setItem("madbaits_debug", "1");
        } catch (_err) {
          // no-op
        }
        return true;
      }
      if (urlFlag === "0" || urlFlag === "false") {
        try {
          window.localStorage.removeItem("madbaits_debug");
        } catch (_err) {
          // no-op
        }
        return false;
      }
      return window.localStorage.getItem("madbaits_debug") === "1";
    } catch (_err) {
      return false;
    }
  };

  const madBaitsDebugEnabled = resolveMadBaitsDebug();
  window.MAD_BAITS_DEBUG = madBaitsDebugEnabled;

  const standaloneMode =
    window.matchMedia("(display-mode: standalone)").matches || window.navigator.standalone === true;
  const isIOSDevice = /iPad|iPhone|iPod/.test(window.navigator.userAgent || "") ||
    (window.navigator.platform === "MacIntel" && window.navigator.maxTouchPoints > 1);
  const isIOSPwaSafeMode = Boolean(isIOSDevice && standaloneMode);
  window.__madBaitsIosPwaSafeMode = isIOSPwaSafeMode;

  if (!window.__madBaitsDiag) {
    window.__madBaitsDiag = {
      startedAt: Date.now(),
      loopState: {},
      counters: {},
      enabled: madBaitsDebugEnabled,
      log(type, payload = {}) {
        if (!this.enabled && !window.MAD_BAITS_DEBUG) {
          return;
        }
        try {
          console.info("[MadBaitsDiag]", type, payload);
        } catch (_err) {
          // no-op
        }
      },
      bump(key) {
        this.counters[key] = (this.counters[key] || 0) + 1;
        return this.counters[key];
      },
      isLooping(key, threshold = 20, windowMs = 3000) {
        const now = Date.now();
        const state = this.loopState[key] || { count: 0, start: now };
        if (now - state.start > windowMs) {
          state.count = 0;
          state.start = now;
        }
        state.count += 1;
        this.loopState[key] = state;
        return state.count > threshold;
      }
    };
  } else {
    window.__madBaitsDiag.enabled = madBaitsDebugEnabled;
  }
  const diag = window.__madBaitsDiag;
  diag.log("page_init", {
    path: window.location.pathname,
    mainInitInstance,
    isIOSPwaSafeMode
  });

  if (!window.__madBaitsFetchInstrumented && typeof window.fetch === "function") {
    window.__madBaitsFetchInstrumented = true;
    const nativeFetch = window.fetch.bind(window);
    window.fetch = (...args) => {
      const input = args[0];
      const rawUrl = typeof input === "string" ? input : (input && input.url) || "";
      const url = String(rawUrl || "");
      const isAjaxLike = /admin-ajax\.php|wc-ajax|rest_route=|\/wp-json\//i.test(url);
      if (isAjaxLike) {
        const recursiveAjax = diag.isLooping("ajax_fetch_loop", 30, 2500);
        diag.log("ajax_fetch", { url, recursiveAjax });
      }
      return nativeFetch(...args);
    };
  }

  if (!window.__madBaitsGlobalErrorHandlersBound) {
    window.__madBaitsGlobalErrorHandlersBound = true;
    window.onerror = function (message, source, lineno, colno, error) {
      diag.log("global_error", {
        message: String(message || ""),
        source: String(source || ""),
        lineno: Number(lineno || 0),
        colno: Number(colno || 0),
        stack: error && error.stack ? String(error.stack) : ""
      });
      return false;
    };
    window.onunhandledrejection = function (event) {
      const reason = event && "reason" in event ? event.reason : null;
      diag.log("unhandled_rejection", {
        reason: reason && reason.message ? String(reason.message) : String(reason || "")
      });
    };
  }

  if (isIOSPwaSafeMode) {
    document.documentElement.classList.add("mad-ios-pwa-safe-mode");
    document.body?.classList.add("mad-ios-pwa-safe-mode");
    if (!document.getElementById("mad-ios-pwa-safe-style")) {
      const style = document.createElement("style");
      style.id = "mad-ios-pwa-safe-style";
      style.textContent = `
        .mad-ios-pwa-safe-mode *, .mad-ios-pwa-safe-mode *::before, .mad-ios-pwa-safe-mode *::after {
          animation: none !important;
          transition: none !important;
          scroll-behavior: auto !important;
        }
      `;
      document.head.appendChild(style);
    }
    document.querySelectorAll('img[loading="lazy"]').forEach((img) => {
      if (img instanceof HTMLImageElement) {
        img.loading = "eager";
      }
    });
    if ("IntersectionObserver" in window && !window.__madBaitsOriginalIntersectionObserver) {
      window.__madBaitsOriginalIntersectionObserver = window.IntersectionObserver;
      window.IntersectionObserver = function MadBaitsDisabledIntersectionObserver() {
        diag.log("ios_safe_mode_intersection_observer_disabled");
        return {
          observe() {},
          unobserve() {},
          disconnect() {},
          takeRecords() {
            return [];
          }
        };
      };
    }
    if ("MutationObserver" in window && !window.__madBaitsOriginalMutationObserver) {
      window.__madBaitsOriginalMutationObserver = window.MutationObserver;
      window.MutationObserver = function MadBaitsDisabledMutationObserver() {
        diag.log("ios_safe_mode_mutation_observer_disabled");
        return {
          observe() {},
          disconnect() {},
          takeRecords() {
            return [];
          }
        };
      };
    }
    diag.log("ios_safe_mode_enabled", { path: window.location.pathname });
  }

  const siteHeader = document.querySelector("[data-site-header]");
  if (siteHeader instanceof HTMLElement) {
    const syncHeaderState = () => {
      siteHeader.classList.toggle("is-scrolled", window.scrollY > 18);
    };

    syncHeaderState();
    window.addEventListener("scroll", syncHeaderState, { passive: true });
  }

  const toggleButton = document.querySelector(".mobile-nav-toggle");
  const mobileMenu = document.getElementById("mobile-menu");
  const mobileMenuBackdrop = document.getElementById("mobile-menu-backdrop");
  const mobileMenuCloseButton = mobileMenu?.querySelector("[data-mobile-nav-close]") || null;

  if (toggleButton && mobileMenu) {
    const MENU_LOCK_CLASSES = ["mad-menu-open", "mobile-menu-open", "menu-open"];
    const focusableSelector =
      'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    let menuOpenFrame = 0;
    let menuCloseTimer = 0;
    let menuHistoryActive = false;

    const isMenuOpen = () =>
      toggleButton.getAttribute("aria-expanded") === "true" &&
      mobileMenu.classList.contains("is-active") &&
      !mobileMenu.hidden;

    const releaseMenuScrollLock = () => {
      MENU_LOCK_CLASSES.forEach((className) => {
        document.body.classList.remove(className);
        document.documentElement.classList.remove(className);
      });
      document.body.style.removeProperty("overflow");
      document.body.style.removeProperty("touch-action");
      document.documentElement.style.removeProperty("overflow");
    };

    const applyMenuScrollLock = () => {
      if (!isMenuOpen()) {
        releaseMenuScrollLock();
        return;
      }
      MENU_LOCK_CLASSES.forEach((className) => {
        document.body.classList.add(className);
        document.documentElement.classList.add(className);
      });
    };

    const syncMenuLayers = () => {
      const open = isMenuOpen();
      if (mobileMenuBackdrop instanceof HTMLElement) {
        mobileMenuBackdrop.hidden = !open;
        mobileMenuBackdrop.classList.toggle("is-active", open);
        mobileMenuBackdrop.setAttribute("aria-hidden", open ? "false" : "true");
      }
      mobileMenu.setAttribute("aria-hidden", open ? "false" : "true");
      applyMenuScrollLock();
    };

    const settleMenuHistory = () => {
      if (!menuHistoryActive || !window.history || typeof window.history.back !== "function") {
        menuHistoryActive = false;
        return;
      }
      menuHistoryActive = false;
      try {
        window.history.back();
      } catch (_err) {
        // Ignore history failures.
      }
    };

    const resetMobileSubmenus = () => {
      mobileMenu.querySelectorAll(".menu-item-has-children.is-open-mobile").forEach((menuItem) => {
        if (!(menuItem instanceof HTMLElement)) {
          return;
        }
        menuItem.classList.remove("is-open-mobile");
        const toggle = menuItem.querySelector(".mobile-submenu-toggle");
        if (toggle instanceof HTMLButtonElement) {
          toggle.setAttribute("aria-expanded", "false");
          toggle.classList.remove("is-expanded");
        }
      });
    };

    const forceCloseMobileMenu = (options = {}) => {
      const restoreHistory = options.restoreHistory !== false;
      window.cancelAnimationFrame(menuOpenFrame);
      menuOpenFrame = 0;
      window.clearTimeout(menuCloseTimer);
      menuCloseTimer = 0;
      toggleButton.setAttribute("aria-expanded", "false");
      mobileMenu.classList.remove("is-active");
      mobileMenu.hidden = true;
      resetMobileSubmenus();
      syncMenuLayers();
      releaseMenuScrollLock();
      if (restoreHistory) {
        settleMenuHistory();
      } else {
        menuHistoryActive = false;
      }
      document.dispatchEvent(new CustomEvent("mad:mobile-menu-close"));
    };

    const closeMobileMenu = (options = {}) => {
      if (!isMenuOpen() && mobileMenu.hidden) {
        releaseMenuScrollLock();
        return;
      }
      window.cancelAnimationFrame(menuOpenFrame);
      menuOpenFrame = 0;
      toggleButton.setAttribute("aria-expanded", "false");
      mobileMenu.classList.remove("is-active");
      resetMobileSubmenus();
      syncMenuLayers();
      window.clearTimeout(menuCloseTimer);
      menuCloseTimer = window.setTimeout(() => {
        if (toggleButton.getAttribute("aria-expanded") === "false") {
          mobileMenu.hidden = true;
          syncMenuLayers();
          releaseMenuScrollLock();
        }
      }, 240);
      if (options.restoreHistory !== false) {
        settleMenuHistory();
      } else {
        menuHistoryActive = false;
      }
      document.dispatchEvent(new CustomEvent("mad:mobile-menu-close"));
    };

    const openMobileMenu = () => {
      try {
        window.clearTimeout(menuCloseTimer);
        menuCloseTimer = 0;
        document.dispatchEvent(new CustomEvent("mad:mobile-menu-open"));
        toggleButton.setAttribute("aria-expanded", "true");
        mobileMenu.hidden = false;
        menuOpenFrame = window.requestAnimationFrame(() => {
          if (toggleButton.getAttribute("aria-expanded") !== "true") {
            return;
          }
          mobileMenu.classList.add("is-active");
          syncMenuLayers();
          const firstFocusable = mobileMenu.querySelector(focusableSelector);
          if (firstFocusable instanceof HTMLElement) {
            firstFocusable.focus();
          }
        });
        syncMenuLayers();
        if (
          !menuHistoryActive &&
          window.history &&
          typeof window.history.pushState === "function"
        ) {
          try {
            window.history.pushState({ madMobileMenu: true }, "");
            menuHistoryActive = true;
            diag.log("navigation_pushstate", { source: "mobile_menu_open" });
          } catch (_err) {
            menuHistoryActive = false;
          }
        }
      } catch (_err) {
        forceCloseMobileMenu({ restoreHistory: false });
      }
    };

    const toggleMobileMenu = () => {
      if (isMenuOpen()) {
        closeMobileMenu();
      } else {
        openMobileMenu();
      }
    };

    toggleButton.addEventListener("click", (event) => {
      event.preventDefault();
      toggleMobileMenu();
    });

    const focusMobileMenuTrigger = () => {
      const appMenuTrigger = document.querySelector("[data-app-top-open-menu]");
      if (appMenuTrigger instanceof HTMLElement) {
        appMenuTrigger.focus();
        return;
      }
      toggleButton.focus();
    };

    if (mobileMenuCloseButton instanceof HTMLButtonElement) {
      mobileMenuCloseButton.addEventListener("click", (event) => {
        event.preventDefault();
        closeMobileMenu();
        focusMobileMenuTrigger();
      });
    }

    if (mobileMenuBackdrop instanceof HTMLElement) {
      mobileMenuBackdrop.addEventListener("click", () => {
        closeMobileMenu();
        focusMobileMenuTrigger();
      });
    }

    mobileMenu.addEventListener("click", (event) => {
      const clickedLink = event.target instanceof Element ? event.target.closest("a") : null;
      if (clickedLink instanceof HTMLAnchorElement && mobileMenu.contains(clickedLink)) {
        closeMobileMenu({ restoreHistory: false });
      }
    });

    mobileMenu.addEventListener("transitionend", () => {
      syncMenuLayers();
    });

    window.addEventListener("resize", () => {
      if (window.innerWidth > 860) {
        forceCloseMobileMenu({ restoreHistory: false });
      } else {
        syncMenuLayers();
      }
    });

    window.addEventListener("pageshow", () => {
      if (window.innerWidth > 860) {
        forceCloseMobileMenu({ restoreHistory: false });
        return;
      }
      if (!isMenuOpen()) {
        releaseMenuScrollLock();
      }
    });

    window.addEventListener("pagehide", () => {
      forceCloseMobileMenu({ restoreHistory: false });
    });

    window.addEventListener("popstate", () => {
      const recursivePopstate = diag.isLooping("mobile_menu_popstate", 8, 2000);
      diag.log("navigation_popstate", {
        menuHistoryActive,
        menuOpen: isMenuOpen(),
        recursivePopstate
      });
      if (recursivePopstate) {
        return;
      }
      if (menuHistoryActive || isMenuOpen()) {
        forceCloseMobileMenu({ restoreHistory: false });
      }
    });

    document.addEventListener("visibilitychange", () => {
      if (document.visibilityState === "visible" && !isMenuOpen()) {
        releaseMenuScrollLock();
      }
    });

    document.addEventListener("mad:open-mobile-menu", () => {
      if (!isMenuOpen()) {
        openMobileMenu();
      }
    });

    document.addEventListener("mad:close-mobile-menu", () => {
      if (isMenuOpen()) {
        closeMobileMenu();
      }
    });

    document.addEventListener("mad:toggle-mobile-menu", () => {
      toggleMobileMenu();
    });

    document.addEventListener("keydown", (event) => {
      if (!isMenuOpen()) {
        return;
      }

      if (event.key === "Escape") {
        event.preventDefault();
        closeMobileMenu();
        const menuTrigger = document.querySelector("[data-app-top-open-menu]");
        if (menuTrigger instanceof HTMLElement) {
          menuTrigger.focus();
        } else {
          toggleButton.focus();
        }
        return;
      }

      if (event.key === "Tab") {
        const focusableElements = mobileMenu.querySelectorAll(focusableSelector);
        if (!focusableElements.length) {
          return;
        }

        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];
        if (!(firstElement instanceof HTMLElement) || !(lastElement instanceof HTMLElement)) {
          return;
        }

        if (event.shiftKey && document.activeElement === firstElement) {
          event.preventDefault();
          lastElement.focus();
        } else if (!event.shiftKey && document.activeElement === lastElement) {
          event.preventDefault();
          firstElement.focus();
        }
      }
    });

    const mobileParentItems = mobileMenu.querySelectorAll(".menu-item-has-children");
    mobileParentItems.forEach((menuItem) => {
      if (!(menuItem instanceof HTMLElement)) {
        return;
      }

      const anchor = menuItem.querySelector(":scope > a");
      const subMenu = menuItem.querySelector(":scope > .sub-menu");
      if (!(anchor instanceof HTMLAnchorElement) || !(subMenu instanceof HTMLElement)) {
        return;
      }

      const toggle = document.createElement("button");
      toggle.type = "button";
      toggle.className = "mobile-submenu-toggle";
      toggle.setAttribute("aria-expanded", "false");
      toggle.setAttribute("aria-label", `Toggle ${anchor.textContent?.trim() || "submenu"}`);
      toggle.innerHTML = '<span aria-hidden="true"></span>';

      anchor.insertAdjacentElement("afterend", toggle);

      toggle.addEventListener("click", () => {
        const isOpen = menuItem.classList.toggle("is-open-mobile");
        toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
        toggle.classList.toggle("is-expanded", isOpen);
      });
    });

    // Safety cleanup for stale lock classes from interrupted sessions.
    if (!isMenuOpen()) {
      releaseMenuScrollLock();
      if (mobileMenuBackdrop instanceof HTMLElement) {
        mobileMenuBackdrop.hidden = true;
        mobileMenuBackdrop.classList.remove("is-active");
      }
    }
  }

  const heroMedia = document.querySelector(".hero__media");
  const heroSection = document.querySelector(".hero--immersive");
  const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  if (heroMedia instanceof HTMLElement && !prefersReducedMotion && window.innerWidth > 860) {
    const updateHeroParallax = () => {
      const y = Math.min(window.scrollY * 0.06, 22);
      heroMedia.style.transform = `scale(1.045) translateY(${y}px)`;
    };

    updateHeroParallax();
    window.addEventListener("scroll", updateHeroParallax, { passive: true });
  }

  if (heroSection instanceof HTMLElement && !prefersReducedMotion && window.innerWidth > 860) {
    heroSection.addEventListener("pointermove", (event) => {
      const rect = heroSection.getBoundingClientRect();
      if (!rect.width || !rect.height) {
        return;
      }
      const x = ((event.clientX - rect.left) / rect.width) * 100;
      const y = ((event.clientY - rect.top) / rect.height) * 100;
      heroSection.style.setProperty("--hero-pointer-x", `${x}%`);
      heroSection.style.setProperty("--hero-pointer-y", `${y}%`);
    });

    heroSection.addEventListener("pointerleave", () => {
      heroSection.style.removeProperty("--hero-pointer-x");
      heroSection.style.removeProperty("--hero-pointer-y");
    });
  }

  const navDropdownCloseDelayMs = 480;
  const navDropdownParents = document.querySelectorAll(".primary-nav .menu-item-has-children");

  navDropdownParents.forEach((menuItem) => {
    if (!(menuItem instanceof HTMLElement)) {
      return;
    }

    const trigger = menuItem.querySelector(":scope > a");
    const subMenu = menuItem.querySelector(":scope > .sub-menu");
    if (!(trigger instanceof HTMLElement) || !(subMenu instanceof HTMLElement)) {
      return;
    }

    let closeTimer = null;
    let interactionLockUntil = 0;
    let pointerInside = false;

    trigger.setAttribute("aria-haspopup", "true");
    trigger.setAttribute("aria-expanded", "false");

    const openDropdown = () => {
      if (closeTimer !== null) {
        window.clearTimeout(closeTimer);
        closeTimer = null;
      }
      trigger.setAttribute("aria-expanded", "true");
      menuItem.classList.add("is-open");
      subMenu.setAttribute("data-nav-dropdown-open", "true");
    };

    const closeDropdown = () => {
      trigger.setAttribute("aria-expanded", "false");
      menuItem.classList.remove("is-open");
      subMenu.removeAttribute("data-nav-dropdown-open");
    };

    const lockDropdownInteraction = () => {
      interactionLockUntil = Date.now() + 900;
      openDropdown();
    };

    const isRelatedInside = (relatedTarget) => {
      if (!(relatedTarget instanceof Node)) {
        return false;
      }
      return menuItem.contains(relatedTarget);
    };

    const canCloseDropdown = () => {
      if (pointerInside || Date.now() < interactionLockUntil) {
        return false;
      }
      if (menuItem.matches(":hover") || subMenu.matches(":hover")) {
        return false;
      }
      if (menuItem.contains(document.activeElement)) {
        return false;
      }
      return true;
    };

    const scheduleCloseDropdown = () => {
      if (closeTimer !== null) {
        window.clearTimeout(closeTimer);
      }
      closeTimer = window.setTimeout(() => {
        if (!canCloseDropdown()) {
          scheduleCloseDropdown();
          return;
        }
        closeDropdown();
        closeTimer = null;
      }, navDropdownCloseDelayMs);
    };

    const handlePointerEnter = () => {
      pointerInside = true;
      openDropdown();
    };

    const handlePointerLeave = (event) => {
      if (isRelatedInside(event.relatedTarget)) {
        return;
      }
      pointerInside = false;
      scheduleCloseDropdown();
    };

    menuItem.addEventListener("pointerenter", handlePointerEnter);
    menuItem.addEventListener("pointerleave", handlePointerLeave);
    subMenu.addEventListener("wheel", lockDropdownInteraction, { passive: true });
    subMenu.addEventListener("scroll", lockDropdownInteraction, { passive: true });
    subMenu.addEventListener("pointerdown", lockDropdownInteraction);
    menuItem.addEventListener("focusin", openDropdown);
    menuItem.addEventListener("focusout", (event) => {
      if (!isRelatedInside(event.relatedTarget)) {
        scheduleCloseDropdown();
      }
    });
  });

  const inPageAnchors = document.querySelectorAll('a[href^="#"]');
  inPageAnchors.forEach((anchor) => {
    anchor.addEventListener("click", (event) => {
      const targetId = anchor.getAttribute("href");
      if (!targetId || targetId === "#") {
        return;
      }

      const target = document.querySelector(targetId);
      if (!target) {
        return;
      }

      event.preventDefault();
      target.scrollIntoView({ behavior: "smooth", block: "start" });
      if (target instanceof HTMLElement) {
        target.setAttribute("tabindex", "-1");
        target.focus({ preventScroll: true });
      }
    });
  });

  const productSliders = document.querySelectorAll("[data-product-slider]");
  productSliders.forEach((slider) => {
    const track = slider.querySelector("[data-slider-track]");
    const prevButton = slider.querySelector("[data-slider-prev]");
    const nextButton = slider.querySelector("[data-slider-next]");

    if (!(track instanceof HTMLElement)) {
      return;
    }

    const scrollAmount = () => Math.max(track.clientWidth * 0.8, 280);

    if (prevButton instanceof HTMLButtonElement) {
      prevButton.addEventListener("click", () => {
        track.scrollBy({ left: -scrollAmount(), behavior: "smooth" });
      });
    }

    if (nextButton instanceof HTMLButtonElement) {
      nextButton.addEventListener("click", () => {
        track.scrollBy({ left: scrollAmount(), behavior: "smooth" });
      });
    }
  });

  const homepageCards = document.querySelectorAll(".mad-product-card");
  const setCardFeedback = (card, message, isError = false) => {
    if (!(card instanceof HTMLElement)) {
      return;
    }
    const feedback = card.querySelector("[data-card-feedback]");
    const feedbackText = card.querySelector("[data-card-feedback-text]");
    if (!(feedback instanceof HTMLElement) || !(feedbackText instanceof HTMLElement)) {
      return;
    }
    feedback.hidden = false;
    feedbackText.textContent = message || "";
    feedback.classList.toggle("is-error", Boolean(isError));
    feedback.classList.toggle("is-success", !isError && Boolean(message));
  };

  homepageCards.forEach((card) => {
    const ajaxButton = card.querySelector("a.add_to_cart_button.ajax_add_to_cart");
    if (!(ajaxButton instanceof HTMLAnchorElement)) {
      return;
    }

    ajaxButton.addEventListener("click", () => {
      const originalText = ajaxButton.textContent || "";
      ajaxButton.setAttribute("data-original-label", originalText);
    });
  });

  if (typeof window.jQuery !== "undefined" && window.jQuery(document.body)) {
    window.jQuery(document.body).on("added_to_cart", (_event, _fragments, _hash, button) => {
      const buttonEl = button && button[0] ? button[0] : null;
      if (!(buttonEl instanceof HTMLElement)) {
        return;
      }
      const card = buttonEl.closest(".mad-product-card");
      if (!(card instanceof HTMLElement)) {
        return;
      }
      const originalText = buttonEl.getAttribute("data-original-label") || buttonEl.textContent || "";
      buttonEl.textContent = madBaitsConfig?.i18nAdded || "Added";
      window.setTimeout(() => {
        buttonEl.textContent = originalText;
      }, 900);
      setCardFeedback(card, madBaitsConfig?.i18nAdded || "Added");
    });
  }

  const variationForms = document.querySelectorAll("[data-variation-form]");
  variationForms.forEach((form) => {
    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    const card = form.closest(".mad-product-card");
    const selects = Array.from(form.querySelectorAll(".mad-product-card__variation-select"));
    const submitButton = form.querySelector("[data-variation-submit]");
    const priceTarget = form.querySelector("[data-variation-price]");
    const messageTarget = form.querySelector("[data-inline-message]");
    const productId = Number(form.getAttribute("data-product-id")) || 0;
    const defaultPriceHtml = priceTarget instanceof HTMLElement ? priceTarget.innerHTML : "";
    let variations = [];

    try {
      variations = JSON.parse(form.getAttribute("data-variations") || "[]");
    } catch (_error) {
      variations = [];
    }

    const decodeHtml = (value) => {
      const parser = document.createElement("textarea");
      parser.innerHTML = value;
      return parser.value;
    };

    const normalizeAttributeKey = (name) => {
      if (typeof name !== "string") {
        return "";
      }
      const cleaned = name.trim().toLowerCase();
      if (!cleaned) {
        return "";
      }
      if (cleaned.startsWith("attribute_")) {
        return cleaned;
      }
      return `attribute_${cleaned}`;
    };

    const getAttributeKeyVariants = (name) => {
      const normalized = normalizeAttributeKey(name);
      if (!normalized) {
        return [];
      }
      const variants = new Set([normalized]);
      if (normalized.startsWith("attribute_pa_")) {
        variants.add(`attribute_${normalized.replace(/^attribute_pa_/, "")}`);
      } else if (normalized.startsWith("attribute_")) {
        const suffix = normalized.replace(/^attribute_/, "");
        if (suffix && !suffix.startsWith("pa_")) {
          variants.add(`attribute_pa_${suffix}`);
        }
      }
      return Array.from(variants);
    };

    const normalizeAttributeValue = (value) => {
      if (typeof value !== "string") {
        return "";
      }
      const decoded = decodeHtml(value).trim().toLowerCase();
      if (!decoded) {
        return "";
      }
      return decoded
        .replace(/[_\s]+/g, "-")
        .replace(/[^a-z0-9-]+/g, "")
        .replace(/-{2,}/g, "-")
        .replace(/^-|-$/g, "");
    };

    const parseBoolean = (value) => value === true || value === "true" || value === 1 || value === "1";

    const parsedVariations = Array.isArray(variations)
      ? variations
          .map((variation) => {
            const rawAttributes = variation && typeof variation === "object" ? variation.attributes || {} : {};
            const normalizedAttributes = {};

            Object.entries(rawAttributes).forEach(([name, value]) => {
              const canonicalKey = normalizeAttributeKey(name);
              if (!canonicalKey) {
                return;
              }
              normalizedAttributes[canonicalKey] = normalizeAttributeValue(String(value || ""));
            });

            return {
              variation_id: Number(variation?.variation_id) || 0,
              attributes: normalizedAttributes,
              price_html: typeof variation?.price_html === "string" ? variation.price_html : "",
              is_purchasable: parseBoolean(variation?.is_purchasable),
              is_in_stock: parseBoolean(variation?.is_in_stock),
            };
          })
          .filter((variation) => variation.variation_id > 0)
      : [];

    const getSelectedAttributes = () => {
      const selectedRaw = {};
      const selectedNormalized = {};

      selects.forEach((select) => {
        if (!(select instanceof HTMLSelectElement)) {
          return;
        }
        const canonicalKey = normalizeAttributeKey(select.name);
        if (!canonicalKey) {
          return;
        }
        selectedRaw[canonicalKey] = select.value;
        selectedNormalized[canonicalKey] = normalizeAttributeValue(select.value);
      });

      return {
        raw: selectedRaw,
        normalized: selectedNormalized,
      };
    };

    const allSelected = () => selects.every((select) => select instanceof HTMLSelectElement && Boolean(select.value));

    const getSelectedValueForAttribute = (selected, attributeName) => {
      const variants = getAttributeKeyVariants(attributeName);
      for (const key of variants) {
        if (selected[key]) {
          return selected[key];
        }
      }
      return "";
    };

    const findMatchingVariation = (selected, requireComplete = true) => {
      if (!parsedVariations.length) {
        return null;
      }
      if (requireComplete && !allSelected()) {
        return null;
      }

      return (
        parsedVariations.find((variation) => {
          const attrs = variation?.attributes || {};
          return Object.entries(attrs).every(([attributeName, expectedValue]) => {
            const selectedValue = getSelectedValueForAttribute(selected, attributeName);
            if (!selectedValue) {
              return !requireComplete;
            }
            if (!expectedValue) {
              return true;
            }
            return selectedValue === expectedValue || selectedValue === normalizeAttributeValue(expectedValue);
          });
        }) || null
      );
    };

    const setMessage = (text, isError = false) => {
      if (!(messageTarget instanceof HTMLElement)) {
        return;
      }
      messageTarget.textContent = text;
      messageTarget.classList.toggle("is-error", Boolean(isError));
      messageTarget.classList.toggle("is-success", !isError && Boolean(text));
    };

    const updateFormState = () => {
      const selected = getSelectedAttributes();
      const hasAllSelections = allSelected();
      const match = findMatchingVariation(selected.normalized, true);
      const purchasableMatch =
        match && match.variation_id && match.is_purchasable !== false && match.is_in_stock !== false ? match : null;

      if (submitButton instanceof HTMLButtonElement) {
        submitButton.disabled = !hasAllSelections || !purchasableMatch;
      }

      if (priceTarget instanceof HTMLElement) {
        priceTarget.innerHTML = match?.price_html || defaultPriceHtml;
      }

      if (!hasAllSelections) {
        return;
      }

      if (!match) {
        setMessage(madBaitsConfig?.i18nInvalidCombo || "That option combination is unavailable.", true);
        return;
      }

      if (!purchasableMatch) {
        setMessage(madBaitsConfig?.i18nVariationUnavailable || "This variation is currently out of stock.", true);
      }
    };

    selects.forEach((select) => {
      select.addEventListener("change", () => {
        setMessage("");
        updateFormState();
      });
    });

    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const selected = getSelectedAttributes();
      const match = findMatchingVariation(selected.normalized, true);
      const purchasableMatch =
        match && match.variation_id && match.is_purchasable !== false && match.is_in_stock !== false ? match : null;

      if (!allSelected()) {
        setMessage(madBaitsConfig?.i18nChooseOptions || "Choose options first", true);
        return;
      }
      if (!match) {
        setMessage(madBaitsConfig?.i18nInvalidCombo || "That option combination is unavailable.", true);
        return;
      }
      if (!purchasableMatch || !productId) {
        setMessage(madBaitsConfig?.i18nVariationUnavailable || "This variation is currently out of stock.", true);
        return;
      }

      if (submitButton instanceof HTMLButtonElement) {
        submitButton.disabled = true;
        submitButton.setAttribute("aria-busy", "true");
      }
      form.classList.add("is-submitting");

      try {
        const payload = new URLSearchParams();
        payload.set("action", "mad_baits_add_variation_to_cart");
        payload.set("nonce", madBaitsConfig?.addVariationNonce || "");
        payload.set("product_id", String(productId));
        payload.set("variation_id", String(purchasableMatch.variation_id));
        payload.set("quantity", "1");

        Object.entries(selected.raw).forEach(([name, value]) => {
          if (!value) {
            return;
          }
          payload.append(`attributes[${name}]`, value);
        });

        const response = await fetch(madBaitsConfig?.ajaxUrl || "", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          },
          body: payload.toString(),
          credentials: "same-origin",
        });

        const result = await response.json();
        if (!result?.success) {
          setMessage(result?.data?.message || madBaitsConfig?.i18nAddFailed || "Could not add to basket. Try again.", true);
          updateFormState();
          return;
        }

        const cartCountEls = document.querySelectorAll(".site-header__cart-count");
        cartCountEls.forEach((countEl) => {
          countEl.textContent = String(result?.data?.cart_count ?? countEl.textContent ?? "0");
        });

        if (typeof window.jQuery !== "undefined" && window.jQuery(document.body)) {
          window.jQuery(document.body).trigger("wc_fragment_refresh");
        }

        if (card instanceof HTMLElement) {
          setCardFeedback(card, result?.data?.message || madBaitsConfig?.i18nAdded || "Added");
          const viewBasketLink = card.querySelector("[data-card-view-basket]");
          if (viewBasketLink instanceof HTMLAnchorElement && result?.data?.cart_url) {
            viewBasketLink.href = result.data.cart_url;
          }
        }
        document.body.dispatchEvent(new CustomEvent("mad_baits:item_added_to_cart"));
        setMessage(result?.data?.message || madBaitsConfig?.i18nAdded || "Added", false);
      } catch (_error) {
        setMessage(madBaitsConfig?.i18nAddFailed || "Could not add to basket. Try again.", true);
      } finally {
        if (submitButton instanceof HTMLButtonElement) {
          submitButton.removeAttribute("aria-busy");
        }
        form.classList.remove("is-submitting");
        updateFormState();
      }
    });

    updateFormState();
  });

  const miniCartShell = document.querySelector("[data-mini-cart-drawer]");
  if (miniCartShell instanceof HTMLElement) {
    const closeTriggers = miniCartShell.querySelectorAll("[data-mini-cart-close]");
    const drawerPanel = miniCartShell.querySelector(".mad-mini-cart-drawer");
    const drawerFocusTarget = miniCartShell.querySelector(".mad-mini-cart-drawer__close");
    let closeTimer = null;
    let previouslyFocused = null;

    const getFocusable = () => {
      if (!(drawerPanel instanceof HTMLElement)) {
        return [];
      }
      return Array.from(
        drawerPanel.querySelectorAll(
          'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])'
        )
      ).filter((el) => el instanceof HTMLElement && !el.hasAttribute("disabled") && el.offsetParent !== null);
    };

    const clearCloseTimer = () => {
      if (closeTimer) {
        window.clearTimeout(closeTimer);
        closeTimer = null;
      }
    };

    const openMiniCart = () => {
      clearCloseTimer();
      previouslyFocused = document.activeElement instanceof HTMLElement ? document.activeElement : null;
      miniCartShell.hidden = false;
      miniCartShell.setAttribute("aria-hidden", "false");
      document.body.classList.add("mad-mini-cart-open");
      window.requestAnimationFrame(() => {
        miniCartShell.classList.add("is-open");
      });
      if (drawerFocusTarget instanceof HTMLElement) {
        window.setTimeout(() => drawerFocusTarget.focus({ preventScroll: true }), 100);
      }
    };

    const closeMiniCart = () => {
      clearCloseTimer();
      miniCartShell.classList.remove("is-open");
      miniCartShell.setAttribute("aria-hidden", "true");
      document.body.classList.remove("mad-mini-cart-open");
      closeTimer = window.setTimeout(() => {
        miniCartShell.hidden = true;
        if (previouslyFocused instanceof HTMLElement) {
          previouslyFocused.focus({ preventScroll: true });
        }
        previouslyFocused = null;
      }, 220);
    };

    closeTriggers.forEach((trigger) => {
      trigger.addEventListener("click", (event) => {
        event.preventDefault();
        closeMiniCart();
      });
    });

    document.addEventListener("keydown", (event) => {
      if (!miniCartShell.classList.contains("is-open")) {
        return;
      }
      if (event.key === "Escape") {
        event.preventDefault();
        closeMiniCart();
        return;
      }
      if (event.key !== "Tab") {
        return;
      }
      const focusable = getFocusable();
      if (!focusable.length) {
        event.preventDefault();
        return;
      }
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      const active = document.activeElement;
      if (event.shiftKey && active === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && active === last) {
        event.preventDefault();
        first.focus();
      }
    });

    document.body.addEventListener("mad_baits:item_added_to_cart", openMiniCart);

    if (typeof window.jQuery !== "undefined" && window.jQuery(document.body)) {
      window.jQuery(document.body).on("added_to_cart", () => {
        openMiniCart();
      });
    }

    if (drawerPanel instanceof HTMLElement) {
      drawerPanel.addEventListener("click", (event) => event.stopPropagation());
    }
  }

  const syncVariationSummaryVisibility = (form) => {
    if (!(form instanceof HTMLFormElement)) {
      return;
    }
    const summary = form.querySelector(".single_variation");
    if (!(summary instanceof HTMLElement)) {
      return;
    }
    const priceText = summary.querySelector(".price")?.textContent?.replace(/\s+/g, " ").trim() || "";
    const descriptionText =
      summary.querySelector(".woocommerce-variation-description")?.textContent?.replace(/\s+/g, " ").trim() || "";
    const availabilityText =
      summary.querySelector(".woocommerce-variation-availability")?.textContent?.replace(/\s+/g, " ").trim() || "";
    const hasContent = Boolean(priceText || descriptionText || availabilityText);
    summary.classList.toggle("is-empty", !hasContent);
    summary.hidden = !hasContent;
  };

  const cleanupSingleProductNotices = () => {
    const wrappers = document.querySelectorAll(".single-product .mad-pdp-notices, .single-product .woocommerce-notices-wrapper");
    wrappers.forEach((wrapper) => {
      if (!(wrapper instanceof HTMLElement)) {
        return;
      }
      const errors = Array.from(wrapper.querySelectorAll(".woocommerce-error"));
      const hasSuccess = wrapper.querySelector(".woocommerce-message") instanceof HTMLElement;
      if (hasSuccess) {
        errors.forEach((node) => node.remove());
        return;
      }
      const seen = new Set();
      errors.forEach((node) => {
        const text = node.textContent?.replace(/\s+/g, " ").trim() || "";
        if (!text || seen.has(text)) {
          node.remove();
          return;
        }
        seen.add(text);
      });
    });
  };

  cleanupSingleProductNotices();

  if (typeof window.jQuery !== "undefined" && window.jQuery(document.body).length) {
    window.jQuery(document.body).on("added_to_cart", cleanupSingleProductNotices);
  }

  document.querySelectorAll(".single-product form.variations_form").forEach((form) => {
    if (!(form instanceof HTMLFormElement)) {
      return;
    }
    const isBundleBuilderForm = Boolean(form.querySelector(".mbbb-choice-input")) || Boolean(form.closest(".mbbb-product"));
    syncVariationSummaryVisibility(form);
    form.addEventListener("change", () => syncVariationSummaryVisibility(form));

    const variationIdInput = form.querySelector('input[name="variation_id"]');
    const attributeSelects = Array.from(form.querySelectorAll('select[name^="attribute_"]'));
    const addToCartButton = form.querySelector(".single_add_to_cart_button");

    form.addEventListener("submit", (event) => {
      if (isBundleBuilderForm) {
        return;
      }
      if (attributeSelects.length < 1) {
        return;
      }
      const hasAllSelections = attributeSelects.every(
        (select) => select instanceof HTMLSelectElement && Boolean(select.value),
      );
      const variationId =
        variationIdInput instanceof HTMLInputElement ? Number.parseInt(variationIdInput.value || "0", 10) : 0;
      if (!hasAllSelections || variationId <= 0) {
        event.preventDefault();
        event.stopPropagation();
        if (addToCartButton instanceof HTMLButtonElement) {
          addToCartButton.classList.add("disabled");
        }
        return;
      }
      if (addToCartButton instanceof HTMLButtonElement) {
        addToCartButton.disabled = true;
        window.setTimeout(() => {
          addToCartButton.disabled = false;
        }, 2500);
      }
    });

    if (typeof window.jQuery !== "undefined" && window.jQuery(form).length) {
      window.jQuery(form).on("found_variation reset_data show_variation hide_variation", () => {
        syncVariationSummaryVisibility(form);
        if (addToCartButton instanceof HTMLButtonElement) {
          addToCartButton.classList.remove("disabled");
          addToCartButton.disabled = false;
        }
      });
    }
  });

  const stickyAddToCart = document.querySelector("[data-mobile-sticky-atc]");
  const cartForm = document.querySelector(".single-product form.cart");

  if (stickyAddToCart instanceof HTMLElement && cartForm instanceof HTMLFormElement) {
    const isBundleBuilderCartForm =
      cartForm.classList.contains("variations_form") &&
      (Boolean(cartForm.querySelector(".mbbb-choice-input")) || Boolean(document.getElementById("mbbb-builder")));
    const stickyBreakpoint = window.matchMedia("(max-width: 1100px)");
    const stickyQtyInput = stickyAddToCart.querySelector("[data-sticky-qty]");
    const decreaseQtyButton = stickyAddToCart.querySelector("[data-sticky-qty-decrease]");
    const increaseQtyButton = stickyAddToCart.querySelector("[data-sticky-qty-increase]");
    const stickyAddButton = stickyAddToCart.querySelector("[data-sticky-add-to-cart]");
    const stickyMessage = stickyAddToCart.querySelector("[data-sticky-atc-message]");
    const stickyVariationStatus = stickyAddToCart.querySelector("[data-sticky-variation-status]");
    const mainQtyInput = cartForm.querySelector("input.qty");
    const variationSelects = Array.from(cartForm.querySelectorAll('select[name^="attribute_"]'));
    const variationIdInput = cartForm.querySelector('input[name="variation_id"]');
    const isVariableProduct =
      !isBundleBuilderCartForm &&
      (stickyAddToCart.getAttribute("data-product-type") === "variable" || variationSelects.length > 0);

    const parseNumberOrFallback = (value, fallback) => {
      const parsed = Number.parseFloat(String(value || ""));
      return Number.isFinite(parsed) ? parsed : fallback;
    };

    const getEffectiveQtyConfig = () => {
      const source = mainQtyInput instanceof HTMLInputElement ? mainQtyInput : stickyQtyInput;
      const step = parseNumberOrFallback(source?.getAttribute("step"), 1);
      const min = parseNumberOrFallback(source?.getAttribute("min"), 1);
      const maxAttr = parseNumberOrFallback(source?.getAttribute("max"), Number.POSITIVE_INFINITY);
      const max = Number.isFinite(maxAttr) && maxAttr > 0 ? maxAttr : Number.POSITIVE_INFINITY;
      return { step: step > 0 ? step : 1, min: min > 0 ? min : 1, max };
    };

    const setStickyMessage = (text, isError = false) => {
      if (!(stickyMessage instanceof HTMLElement)) {
        return;
      }
      stickyMessage.textContent = text || "";
      stickyMessage.classList.toggle("is-error", Boolean(isError));
      stickyMessage.classList.toggle("is-success", !isError && Boolean(text));
    };

    const setVariationStatus = () => {
      if (!(stickyVariationStatus instanceof HTMLElement)) {
        return;
      }
      if (!isVariableProduct) {
        stickyVariationStatus.textContent = madBaitsConfig?.i18nReadyToAdd || "Ready to add";
        stickyVariationStatus.classList.remove("is-warning");
        return;
      }
      const hasAllSelections = variationSelects.every((select) => select instanceof HTMLSelectElement && Boolean(select.value));
      const variationId = variationIdInput instanceof HTMLInputElement ? Number.parseInt(variationIdInput.value || "0", 10) : 0;
      const hasResolvedVariation = variationId > 0;
      if (hasAllSelections && hasResolvedVariation) {
        stickyVariationStatus.textContent = madBaitsConfig?.i18nVariationSelected || "Variation selected";
        stickyVariationStatus.classList.remove("is-warning");
      } else {
        stickyVariationStatus.textContent = madBaitsConfig?.i18nChooseOptions || "Choose options first";
        stickyVariationStatus.classList.add("is-warning");
      }
    };

    const syncStickyQtyFromMain = () => {
      if (!(stickyQtyInput instanceof HTMLInputElement)) {
        return;
      }
      const { min, max } = getEffectiveQtyConfig();
      let qty = parseNumberOrFallback(mainQtyInput instanceof HTMLInputElement ? mainQtyInput.value : stickyQtyInput.value, min);
      qty = Math.max(min, Math.min(max, qty));
      stickyQtyInput.value = String(qty);
    };

    const syncMainQtyFromSticky = () => {
      if (!(stickyQtyInput instanceof HTMLInputElement) || !(mainQtyInput instanceof HTMLInputElement)) {
        return;
      }
      const { min, max } = getEffectiveQtyConfig();
      let qty = parseNumberOrFallback(stickyQtyInput.value, min);
      qty = Math.max(min, Math.min(max, qty));
      stickyQtyInput.value = String(qty);
      mainQtyInput.value = String(qty);
      mainQtyInput.dispatchEvent(new Event("input", { bubbles: true }));
      mainQtyInput.dispatchEvent(new Event("change", { bubbles: true }));
    };

    const updateStickyVisibility = () => {
      if (!stickyBreakpoint.matches) {
        stickyAddToCart.classList.remove("is-visible");
        return;
      }

      const cartRect = cartForm.getBoundingClientRect();
      const hasScrolledPastCart = cartRect.bottom < 0;
      stickyAddToCart.classList.toggle("is-visible", hasScrolledPastCart);
    };

    if (mainQtyInput instanceof HTMLInputElement) {
      mainQtyInput.addEventListener("input", syncStickyQtyFromMain);
      mainQtyInput.addEventListener("change", syncStickyQtyFromMain);
    }

    if (stickyQtyInput instanceof HTMLInputElement) {
      stickyQtyInput.addEventListener("input", () => {
        setStickyMessage("");
        syncMainQtyFromSticky();
      });
      stickyQtyInput.addEventListener("change", () => {
        setStickyMessage("");
        syncMainQtyFromSticky();
      });
    }

    if (decreaseQtyButton instanceof HTMLButtonElement) {
      decreaseQtyButton.addEventListener("click", () => {
        if (!(stickyQtyInput instanceof HTMLInputElement)) {
          return;
        }
        const { step, min, max } = getEffectiveQtyConfig();
        const current = parseNumberOrFallback(stickyQtyInput.value, min);
        stickyQtyInput.value = String(Math.max(min, Math.min(max, current - step)));
        syncMainQtyFromSticky();
      });
    }

    if (increaseQtyButton instanceof HTMLButtonElement) {
      increaseQtyButton.addEventListener("click", () => {
        if (!(stickyQtyInput instanceof HTMLInputElement)) {
          return;
        }
        const { step, min, max } = getEffectiveQtyConfig();
        const current = parseNumberOrFallback(stickyQtyInput.value, min);
        stickyQtyInput.value = String(Math.max(min, Math.min(max, current + step)));
        syncMainQtyFromSticky();
      });
    }

    variationSelects.forEach((select) => {
      select.addEventListener("change", () => {
        setStickyMessage("");
        setVariationStatus();
      });
    });

    if (variationIdInput instanceof HTMLInputElement) {
      variationIdInput.addEventListener("change", setVariationStatus);
    }

    if (typeof window.jQuery !== "undefined" && window.jQuery(cartForm)) {
      window.jQuery(cartForm).on("found_variation reset_data show_variation hide_variation", () => {
        setVariationStatus();
        syncVariationSummaryVisibility(cartForm);
      });
    }

    if (stickyAddButton instanceof HTMLButtonElement) {
      stickyAddButton.addEventListener("click", () => {
        syncMainQtyFromSticky();
        if (isVariableProduct) {
          const hasAllSelections = variationSelects.every(
            (select) => select instanceof HTMLSelectElement && Boolean(select.value),
          );
          const variationId = variationIdInput instanceof HTMLInputElement ? Number.parseInt(variationIdInput.value || "0", 10) : 0;
          if (!hasAllSelections || variationId <= 0) {
            setStickyMessage(madBaitsConfig?.i18nChooseOptions || "Choose options first", true);
            setVariationStatus();
            return;
          }
        }

        setStickyMessage("");
        const addToCartButton = cartForm.querySelector(".single_add_to_cart_button");
        if (addToCartButton instanceof HTMLButtonElement || addToCartButton instanceof HTMLInputElement) {
          addToCartButton.click();
          return;
        }
        if (typeof cartForm.requestSubmit === "function") {
          cartForm.requestSubmit();
        } else {
          cartForm.submit();
        }
      });
    }

    cartForm.addEventListener("submit", () => {
      setStickyMessage("");
    });

    if (typeof window.jQuery !== "undefined" && window.jQuery(document.body)) {
      window.jQuery(document.body).on("added_to_cart", () => {
        if (stickyAddToCart.classList.contains("is-visible")) {
          setStickyMessage(madBaitsConfig?.i18nAdded || "Added");
        }
      });
      window.jQuery(document.body).on("wc_fragments_refreshed", () => {
        setStickyMessage("");
      });
    }

    syncStickyQtyFromMain();
    setVariationStatus();
    updateStickyVisibility();
    window.addEventListener("scroll", updateStickyVisibility, { passive: true });
    window.addEventListener("resize", () => {
      syncStickyQtyFromMain();
      setVariationStatus();
      updateStickyVisibility();
    });
  }

  const cardCheckoutLinks = document.querySelectorAll("[data-card-checkout]");
  cardCheckoutLinks.forEach((link) => {
    if (!(link instanceof HTMLAnchorElement) || !madBaitsConfig?.checkoutUrl) {
      return;
    }
    link.href = madBaitsConfig.checkoutUrl;
  });

  const cardBasketLinks = document.querySelectorAll("[data-card-view-basket]");
  cardBasketLinks.forEach((link) => {
    if (!(link instanceof HTMLAnchorElement) || !madBaitsConfig?.cartUrl) {
      return;
    }
    link.href = madBaitsConfig.cartUrl;
  });

  if (typeof window.jQuery !== "undefined" && window.jQuery(document.body)) {
    window.jQuery(document.body).on("wc_fragments_refreshed", () => {
      cardCheckoutLinks.forEach((link) => {
        if (!(link instanceof HTMLAnchorElement) || !madBaitsConfig?.checkoutUrl) {
          return;
        }
        link.href = madBaitsConfig.checkoutUrl;
      });
      cardBasketLinks.forEach((link) => {
        if (!(link instanceof HTMLAnchorElement) || !madBaitsConfig?.cartUrl) {
          return;
        }
        link.href = madBaitsConfig.cartUrl;
      });
    });
  }

  const sessionBuilders = document.querySelectorAll("[data-session-builder]");
  sessionBuilders.forEach((builder) => {
    if (!(builder instanceof HTMLElement)) {
      return;
    }

    let currentStep = 1;
    const maxStep = 6;
    const selected = {
      sessionType: "",
      rangeKey: "",
      rangeLabel: "",
    };

    const stepPanels = Array.from(builder.querySelectorAll("[data-session-step-panel]"));
    const stepIndicators = Array.from(builder.querySelectorAll("[data-session-step-indicator]"));
    const nextButtons = Array.from(builder.querySelectorAll("[data-session-next]"));
    const prevButtons = Array.from(builder.querySelectorAll("[data-session-prev]"));
    const submitButton = builder.querySelector("[data-session-submit]");
    const statusEl = builder.querySelector("[data-session-status]");
    const reviewTypeEl = builder.querySelector("[data-session-review-type]");
    const reviewRangeEl = builder.querySelector("[data-session-review-range]");
    const reviewListEl = builder.querySelector("[data-session-review-list]");
    const reviewTotalEl = builder.querySelector("[data-session-review-total]");
    const choiceButtons = Array.from(builder.querySelectorAll("[data-session-choice]"));
    const productInputs = Array.from(builder.querySelectorAll("[data-session-product-input]"));

    const setStatus = (text, isError = false) => {
      if (!(statusEl instanceof HTMLElement)) {
        return;
      }
      statusEl.textContent = text || "";
      statusEl.classList.toggle("is-error", Boolean(isError && text));
      statusEl.classList.toggle("is-success", Boolean(!isError && text));
    };

    const formatCurrency = (value) => {
      const numericValue = Number.isFinite(value) ? value : 0;
      return new Intl.NumberFormat("en-GB", {
        style: "currency",
        currency: "GBP",
      }).format(numericValue);
    };

    const getSelectedProducts = () =>
      productInputs
        .filter((input) => input instanceof HTMLInputElement && input.checked)
        .map((input) => ({
          id: Number.parseInt(String(input.getAttribute("data-session-product-id") || "0"), 10),
          name: String(input.getAttribute("data-session-product-name") || "Product"),
          price: Number.parseFloat(String(input.getAttribute("data-session-product-price") || "0")),
        }))
        .filter((item) => Number.isFinite(item.id) && item.id > 0);

    const updateReview = () => {
      const products = getSelectedProducts();
      const total = products.reduce((sum, item) => sum + (Number.isFinite(item.price) ? item.price : 0), 0);

      if (reviewTypeEl instanceof HTMLElement) {
        reviewTypeEl.textContent = selected.sessionType || "Not selected";
      }
      if (reviewRangeEl instanceof HTMLElement) {
        reviewRangeEl.textContent = selected.rangeLabel || "Not selected";
      }
      if (reviewTotalEl instanceof HTMLElement) {
        reviewTotalEl.textContent = formatCurrency(total);
      }
      if (reviewListEl instanceof HTMLElement) {
        if (!products.length) {
          reviewListEl.innerHTML = "<li>No products selected yet.</li>";
        } else {
          reviewListEl.innerHTML = "";
          products.forEach((item) => {
            const row = document.createElement("li");
            row.textContent = `${item.name} — ${formatCurrency(Number.isFinite(item.price) ? item.price : 0)}`;
            reviewListEl.appendChild(row);
          });
        }
      }
    };

    const setStep = (step) => {
      const nextStep = Math.max(1, Math.min(maxStep, step));
      currentStep = nextStep;
      stepPanels.forEach((panel) => {
        if (!(panel instanceof HTMLElement)) {
          return;
        }
        const panelStep = Number.parseInt(panel.getAttribute("data-session-step-panel") || "1", 10);
        const isActive = panelStep === nextStep;
        panel.classList.toggle("is-active", isActive);
      });
      stepIndicators.forEach((indicator) => {
        if (!(indicator instanceof HTMLElement)) {
          return;
        }
        const indicatorStep = Number.parseInt(indicator.getAttribute("data-session-step-indicator") || "1", 10);
        indicator.classList.toggle("is-active", indicatorStep <= nextStep);
      });
      updateReview();
    };

    const updateRangeProductGroups = () => {
      const groups = builder.querySelectorAll("[data-session-range-products]");
      groups.forEach((group) => {
        if (!(group instanceof HTMLElement)) {
          return;
        }
        const groupRange = group.getAttribute("data-session-range-products") || "";
        const isMatch = selected.rangeKey && groupRange === selected.rangeKey;
        group.hidden = !isMatch;
      });
    };

    choiceButtons.forEach((button) => {
      if (!(button instanceof HTMLButtonElement)) {
        return;
      }

      button.addEventListener("click", () => {
        const type = button.getAttribute("data-session-choice-type") || "";
        const value = button.getAttribute("data-session-choice-value") || "";
        if (!type || !value) {
          return;
        }

        const scopedChoices = choiceButtons.filter((candidate) => candidate.getAttribute("data-session-choice-type") === type);
        scopedChoices.forEach((candidate) => candidate.classList.remove("is-selected"));
        button.classList.add("is-selected");

        if (type === "session") {
          selected.sessionType = button.textContent?.trim() || value;
        }
        if (type === "range") {
          selected.rangeKey = value;
          selected.rangeLabel = button.textContent?.trim() || value;
          updateRangeProductGroups();
        }
        setStatus("");
        updateReview();
      });
    });

    productInputs.forEach((input) => {
      if (!(input instanceof HTMLInputElement)) {
        return;
      }
      input.addEventListener("change", updateReview);
    });

    nextButtons.forEach((button) => {
      if (!(button instanceof HTMLButtonElement)) {
        return;
      }
      button.addEventListener("click", () => {
        setStatus("");
        setStep(currentStep + 1);
      });
    });

    prevButtons.forEach((button) => {
      if (!(button instanceof HTMLButtonElement)) {
        return;
      }
      button.addEventListener("click", () => {
        setStatus("");
        setStep(currentStep - 1);
      });
    });

    if (submitButton instanceof HTMLButtonElement) {
      submitButton.addEventListener("click", async () => {
        setStatus("");
        const products = getSelectedProducts();
        if (!products.length) {
          setStatus("Please select at least one product to add.", true);
          setStep(5);
          return;
        }

        const action = builder.getAttribute("data-session-builder-action") || "mad_baits_session_builder_add_all";
        const nonce = builder.getAttribute("data-session-builder-nonce") || "";
        if (!madBaitsConfig?.ajaxUrl || !nonce) {
          setStatus("Session builder configuration is missing.", true);
          return;
        }

        submitButton.disabled = true;
        setStatus("Adding your session kit...");

        try {
          const payload = new URLSearchParams();
          payload.set("action", action);
          payload.set("nonce", nonce);
          products.forEach((item) => {
            payload.append("product_ids[]", String(item.id));
          });

          const response = await fetch(madBaitsConfig.ajaxUrl, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
            body: payload.toString(),
            credentials: "same-origin",
          });

          const result = await response.json();
          if (!result?.success) {
            throw new Error(result?.data?.message || "Could not add your kit.");
          }

          setStatus(result?.data?.message || "Session kit added.");
          const cartUrl = result?.data?.cart_url || madBaitsConfig?.cartUrl;
          if (cartUrl) {
            window.setTimeout(() => {
              window.location.href = String(cartUrl);
            }, 550);
          }
        } catch (error) {
          setStatus(error instanceof Error ? error.message : "Could not add your kit.", true);
        } finally {
          submitButton.disabled = false;
        }
      });
    }

    updateRangeProductGroups();
    updateReview();
    setStep(1);
  });

  const baitComparisons = document.querySelectorAll("[data-bait-comparison]");
  baitComparisons.forEach((comparison) => {
    if (!(comparison instanceof HTMLElement)) {
      return;
    }

    const checkboxes = Array.from(comparison.querySelectorAll("[data-bait-compare-toggle]"));
    const status = comparison.querySelector("[data-bait-compare-status]");
    const maxSelected = 3;

    const updateVisibility = () => {
      const selected = checkboxes
        .filter((box) => box instanceof HTMLInputElement && box.checked)
        .map((box) => (box instanceof HTMLInputElement ? box.value : ""))
        .filter(Boolean);

      const columns = comparison.querySelectorAll("[data-bait-column]");
      columns.forEach((column) => {
        if (!(column instanceof HTMLElement)) {
          return;
        }
        const slug = column.getAttribute("data-bait-column") || "";
        column.classList.toggle("is-compare-hidden", selected.length > 0 && !selected.includes(slug));
      });

      if (status instanceof HTMLElement) {
        status.textContent = selected.length
          ? `Comparing ${selected.length} range${selected.length === 1 ? "" : "s"}`
          : "Select at least one bait range to compare.";
      }
    };

    checkboxes.forEach((box) => {
      if (!(box instanceof HTMLInputElement)) {
        return;
      }
      box.addEventListener("change", () => {
        const selectedCount = checkboxes.filter((candidate) => candidate instanceof HTMLInputElement && candidate.checked).length;
        if (selectedCount > maxSelected) {
          box.checked = false;
          if (status instanceof HTMLElement) {
            status.textContent = "You can compare up to 3 ranges at once.";
          }
        }
        updateVisibility();
      });
    });

    updateVisibility();
  });

  const updateHeaderCartCount = (count) => {
    document.querySelectorAll(".site-header__cart-count, [data-mb-cart-count], .mb-cart-count").forEach((el) => {
      if (el instanceof HTMLElement) {
        el.textContent = String(count);
      }
    });
    if (typeof window.jQuery !== "undefined" && window.jQuery(document.body)) {
      window.jQuery(document.body).trigger("wc_fragment_refresh");
    }
    document.body.dispatchEvent(new CustomEvent("mad_baits:item_added_to_cart"));
  };

  const addAiProductsToCart = async (productIds) => {
    const ids = (Array.isArray(productIds) ? productIds : [])
      .map((id) => Number.parseInt(String(id), 10))
      .filter((id) => id > 0);

    if (!ids.length || !madBaitsConfig?.ajaxUrl) {
      return {
        success: false,
        message: madBaitsConfig?.i18nAiSessionAddFailed || "Could not add products.",
      };
    }

    const payload = new URLSearchParams();
    payload.set("action", madBaitsConfig?.aiAddSessionAction || "mad_baits_ai_add_session_to_cart");
    payload.set("nonce", madBaitsConfig?.aiFinderNonce || "");
    ids.forEach((id) => {
      payload.append("product_ids[]", String(id));
    });

    const response = await fetch(madBaitsConfig.ajaxUrl, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
      body: payload.toString(),
      credentials: "same-origin",
    });
    const data = await response.json();
    if (!data?.success) {
      return {
        success: false,
        message: data?.data?.message || madBaitsConfig?.i18nAiSessionAddFailed || "Could not add products.",
      };
    }

    if (typeof data.data?.cart_count !== "undefined") {
      updateHeaderCartCount(data.data.cart_count);
    }

    return {
      success: true,
      message: data?.data?.message || madBaitsConfig?.i18nAiSessionAdded || "Added to basket",
      cartUrl: data?.data?.cart_url || madBaitsConfig?.cartUrl || "",
    };
  };

  const aiFinderForm = document.querySelector("[data-ai-bait-finder-form]");
  const aiResultPanel = document.querySelector("[data-ai-result]");
  if (aiFinderForm instanceof HTMLFormElement && aiResultPanel instanceof HTMLElement) {
    const statusEl = aiFinderForm.querySelector("[data-ai-status]");
    const noticeEl = aiResultPanel.querySelector("[data-ai-notice]");
    const boilieEl = aiResultPanel.querySelector("[data-ai-boilie]");
    const hookbaitEl = aiResultPanel.querySelector("[data-ai-hookbait]");
    const addonEl = aiResultPanel.querySelector("[data-ai-addon]");
    const pelletsEl = aiResultPanel.querySelector("[data-ai-pellets]");
    const bundleEl = aiResultPanel.querySelector("[data-ai-bundle]");
    const planEl = aiResultPanel.querySelector("[data-ai-plan]");
    const whyEl = aiResultPanel.querySelector("[data-ai-why]");
    const productsEl = aiResultPanel.querySelector("[data-ai-products]");
    const categoriesEl = aiResultPanel.querySelector("[data-ai-categories]");
    const disclaimerEl = aiResultPanel.querySelector("[data-ai-disclaimer]");
    const resultActions = aiResultPanel.querySelector("[data-ai-result-actions]");
    const fallbackEl = aiResultPanel.querySelector("[data-ai-fallback]");

    const setText = (node, value) => {
      if (node instanceof HTMLElement) {
        node.textContent = value || "";
      }
    };

    aiFinderForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      const formData = new FormData(aiFinderForm);
      const payload = new URLSearchParams();
      payload.set("action", madBaitsConfig?.aiFinderAction || "mad_baits_ai_bait_finder");
      payload.set("nonce", madBaitsConfig?.aiFinderNonce || "");

      formData.forEach((value, key) => {
        payload.append(key, String(value));
      });

      if (statusEl instanceof HTMLElement) {
        statusEl.textContent = madBaitsConfig?.i18nBuildingPlan || "Building your session plan...";
      }

      try {
        const response = await fetch(madBaitsConfig?.ajaxUrl || "", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
          body: payload.toString(),
          credentials: "same-origin",
        });

        const data = await response.json();
        if (!data?.success || !data?.data) {
          throw new Error(data?.data?.message || "Could not build plan");
        }

        const result = data.data;
        aiResultPanel.hidden = false;
        setText(noticeEl, result.notice || "");
        setText(boilieEl, result.recommendations?.boilie_range || "");
        setText(hookbaitEl, result.recommendations?.hookbait || "");
        setText(addonEl, result.recommendations?.addon || "");
        setText(pelletsEl, result.recommendations?.pellets || result.recommendations?.addon || "");
        setText(bundleEl, result.recommendations?.suggested_bundle || "");
        setText(planEl, result.recommendations?.baiting_plan || "");
        setText(whyEl, result.recommendations?.why_suits_lake || "");
        setText(disclaimerEl, result.disclaimer || "");

        if (productsEl instanceof HTMLElement) {
          productsEl.innerHTML = "";
          const products = Array.isArray(result.products) ? result.products : [];
          const productIds = [];

          products.forEach((product) => {
            const productId = Number.parseInt(String(product?.id || ""), 10);
            if (productId > 0) {
              productIds.push(productId);
            }

            const card = document.createElement("article");
            card.className = "mad-ai-product";

            const inner = document.createElement("div");
            inner.className = "mad-ai-product__inner";

            const body = document.createElement("div");
            body.className = "mad-ai-product__body";

            const title = document.createElement("h3");
            title.textContent = product?.name || "Product";
            body.appendChild(title);

            if (product?.price_html) {
              const price = document.createElement("p");
              price.className = "mad-ai-product__price";
              price.innerHTML = product.price_html;
              body.appendChild(price);
            }

            const actions = document.createElement("div");
            actions.className = "mad-ai-product__actions";

            if (productId > 0 && (product?.can_ajax || product?.type === "variable")) {
              const addBtn = document.createElement("button");
              addBtn.type = "button";
              addBtn.className = "mad-button mad-button--small";
              addBtn.textContent = product?.add_to_cart_text || "Add To Basket";
              addBtn.setAttribute("data-ai-add-product", String(productId));
              actions.appendChild(addBtn);
            } else {
              const viewBtn = document.createElement("a");
              viewBtn.href = product?.url || "#";
              viewBtn.className = "mad-button mad-button--small";
              viewBtn.textContent = "View Product";
              actions.appendChild(viewBtn);
            }

            const linkBtn = document.createElement("a");
            linkBtn.href = product?.url || "#";
            linkBtn.className = "mad-button mad-button--small mad-button--ghost";
            linkBtn.textContent = "Details";
            actions.appendChild(linkBtn);

            body.appendChild(actions);
            inner.appendChild(body);

            if (product?.image_url) {
              const media = document.createElement("div");
              media.className = "mad-ai-product__media";
              const image = document.createElement("img");
              image.src = String(product.image_url);
              image.alt = product?.name ? String(product.name) : "";
              image.loading = window.__madBaitsIosPwaSafeMode ? "eager" : "lazy";
              image.decoding = "async";
              image.width = 88;
              image.height = 88;
              media.appendChild(image);
              inner.appendChild(media);
            }

            card.appendChild(inner);
            productsEl.appendChild(card);
          });

          aiResultPanel.dataset.recommendedProductIds = JSON.stringify(productIds);
          const hasProducts = productIds.length > 0;
          if (resultActions instanceof HTMLElement) {
            resultActions.hidden = !hasProducts;
          }
          if (fallbackEl instanceof HTMLElement) {
            fallbackEl.hidden = hasProducts;
          }
        }

        if (categoriesEl instanceof HTMLElement) {
          categoriesEl.innerHTML = "";
          const cats = Array.isArray(result.categories) ? result.categories : [];
          cats.forEach((cat) => {
            const link = document.createElement("a");
            link.href = cat?.url || "#";
            link.className = "text-link";
            link.textContent = cat?.name || "Category";
            categoriesEl.appendChild(link);
          });
        }

        if (statusEl instanceof HTMLElement) {
          statusEl.textContent = "";
        }
        aiResultPanel.scrollIntoView({ behavior: "smooth", block: "start" });
      } catch (error) {
        if (statusEl instanceof HTMLElement) {
          statusEl.textContent = error instanceof Error ? error.message : "Could not build plan";
        }
        if (resultActions instanceof HTMLElement) {
          resultActions.hidden = true;
        }
        if (fallbackEl instanceof HTMLElement) {
          fallbackEl.hidden = false;
        }
      }
    });
  }

  const bundleFaq = document.querySelector("[data-bundle-faq]");
  if (bundleFaq instanceof HTMLElement) {
    const faqItems = bundleFaq.querySelectorAll(".bundle-page__faq-item");
    faqItems.forEach((item) => {
      if (!(item instanceof HTMLElement)) {
        return;
      }

      const toggle = item.querySelector(".bundle-page__faq-toggle");
      const panel = item.querySelector(".bundle-page__faq-panel");
      if (!(toggle instanceof HTMLButtonElement) || !(panel instanceof HTMLElement)) {
        return;
      }

      toggle.addEventListener("click", () => {
        const isOpen = item.classList.contains("is-open");
        faqItems.forEach((otherItem) => {
          if (!(otherItem instanceof HTMLElement)) {
            return;
          }
          const otherToggle = otherItem.querySelector(".bundle-page__faq-toggle");
          const otherPanel = otherItem.querySelector(".bundle-page__faq-panel");
          if (!(otherToggle instanceof HTMLButtonElement) || !(otherPanel instanceof HTMLElement)) {
            return;
          }

          otherItem.classList.remove("is-open");
          otherToggle.setAttribute("aria-expanded", "false");
          otherPanel.hidden = true;
        });

        if (!isOpen) {
          item.classList.add("is-open");
          toggle.setAttribute("aria-expanded", "true");
          panel.hidden = false;
        }
      });
    });
  }

  const tvFilterWrappers = document.querySelectorAll("[data-tv-filter-wrap]");
  tvFilterWrappers.forEach((wrapper) => {
    if (!(wrapper instanceof HTMLElement)) {
      return;
    }

    const section = wrapper.closest(".mad-baits-tv");
    if (!(section instanceof HTMLElement)) {
      return;
    }

    const filterButtons = Array.from(wrapper.querySelectorAll("[data-tv-filter]"));
    const cards = Array.from(section.querySelectorAll("[data-tv-card]"));
    if (!filterButtons.length || !cards.length) {
      return;
    }

    const setFilter = (value) => {
      const targetValue = typeof value === "string" ? value : "all";

      filterButtons.forEach((button) => {
        if (!(button instanceof HTMLButtonElement)) {
          return;
        }
        const isActive = button.getAttribute("data-tv-filter") === targetValue;
        button.classList.toggle("is-active", isActive);
        button.setAttribute("aria-pressed", isActive ? "true" : "false");
      });

      cards.forEach((card) => {
        if (!(card instanceof HTMLElement)) {
          return;
        }
        const cardCategory = card.getAttribute("data-tv-category") || "";
        const shouldShow = targetValue === "all" || cardCategory === targetValue;
        card.hidden = !shouldShow;
      });
    };

    filterButtons.forEach((button) => {
      if (!(button instanceof HTMLButtonElement)) {
        return;
      }

      button.addEventListener("click", () => {
        setFilter(button.getAttribute("data-tv-filter") || "all");
      });
    });

    setFilter("all");
  });
})();

(() => {
  window.__madBaitsPwaInitCount = (window.__madBaitsPwaInitCount || 0) + 1;
  if (window.__madBaitsPwaInitCount > 1) {
    console.warn("[Mad Baits PWA] Duplicate PWA init blocked", { count: window.__madBaitsPwaInitCount });
    return;
  }
  const config = window.madBaitsConfig || {};
  const diag = window.__madBaitsDiag;
  const installDismissKey = config.pwaInstallDismissKey || "madBaitsPwaInstallDismissed";
  const installInstalledKey = "madBaitsPwaInstalled";
  const isDev = Boolean(config.isDev);
  const debugEnabled = Boolean(window.MAD_BAITS_DEBUG) || isDev;

  const logDev = (...args) => {
    if (!debugEnabled) {
      return;
    }
    console.info("[Mad Baits PWA]", ...args);
    if (diag && typeof diag.log === "function") {
      diag.log("pwa_debug", { args });
    }
  };

  const isStandaloneMode = () =>
    window.matchMedia("(display-mode: standalone)").matches || window.navigator.standalone === true;

  const isMobileLikeViewport = () => window.matchMedia("(max-width: 768px)").matches;
  const isIosSafari = () => {
    const ua = window.navigator.userAgent || "";
    const isIOSDevice =
      /iPad|iPhone|iPod/.test(ua) || (window.navigator.platform === "MacIntel" && window.navigator.maxTouchPoints > 1);
    const isSafariEngine = /Safari/i.test(ua) && !/CriOS|FxiOS|EdgiOS|OPiOS/i.test(ua);
    return isIOSDevice && isSafariEngine;
  };

  const safeGetLocalStorage = (key) => {
    try {
      return window.localStorage.getItem(key);
    } catch (_error) {
      return null;
    }
  };

  const safeSetLocalStorage = (key, value) => {
    try {
      window.localStorage.setItem(key, value);
    } catch (_error) {
      // no-op
    }
  };

  const createUpdatePrompt = (onUpdate) => {
    let promptEl = document.querySelector("[data-pwa-update-prompt]");
    if (promptEl instanceof HTMLElement) {
      return promptEl;
    }

    promptEl = document.createElement("aside");
    promptEl.className = "mad-pwa-update mad-app-update-toast";
    promptEl.setAttribute("data-pwa-update-prompt", "true");
    promptEl.innerHTML = `
      <p>${config.i18nAppUpdateTitle || "New Mad Baits app update available"}</p>
      <div class="mad-pwa-update__actions">
        <button type="button" class="mad-button mad-button--small" data-pwa-update-now>${config.i18nAppUpdateRefresh || "Refresh now"}</button>
        <button type="button" class="mad-button mad-button--ghost mad-button--small" data-pwa-update-later>${config.i18nAppUpdateLater || "Later"}</button>
      </div>
    `;

    const updateBtn = promptEl.querySelector("[data-pwa-update-now]");
    const laterBtn = promptEl.querySelector("[data-pwa-update-later]");

    if (updateBtn instanceof HTMLButtonElement) {
      updateBtn.addEventListener("click", () => {
        onUpdate();
      });
    }

    if (laterBtn instanceof HTMLButtonElement) {
      laterBtn.addEventListener("click", () => {
        promptEl?.remove();
      });
    }

    document.body.appendChild(promptEl);
    return promptEl;
  };

  const initInstallPrompt = () => {
    let deferredPrompt = null;
    let bannerEl = null;
    let hasNativePromptEvent = false;
    let promptInFlight = false;
    const manualInstallMode = isIosSafari();

    const logInstall = (...args) => {
      if (!debugEnabled) {
        return;
      }
      console.info("[Mad Baits PWA]", ...args);
    };

    const getInstallMessages = () =>
      window.madBaitsPwaInstall && typeof window.madBaitsPwaInstall.getMessages === "function"
        ? window.madBaitsPwaInstall.getMessages()
        : null;

    const getManualInstallHint = () => {
      const platformMessages = getInstallMessages();
      if (platformMessages?.hint) {
        return platformMessages.hint;
      }
      if (manualInstallMode) {
        return "On iPhone/iPad: tap Share, then Add to Home Screen.";
      }
      return "Open your browser menu and tap Install App or Add to Home Screen.";
    };

    const clearDeferredPrompt = () => {
      deferredPrompt = null;
      window.dispatchEvent(new CustomEvent("mad:pwa-install-prompt-cleared"));
    };

    const isPromptAvailable = () =>
      Boolean(deferredPrompt && typeof deferredPrompt.prompt === "function");

    const updateBannerInstallButton = () => {
      if (!(bannerEl instanceof HTMLElement)) {
        return;
      }
      const installBtn = bannerEl.querySelector("[data-pwa-install]");
      if (!(installBtn instanceof HTMLButtonElement)) {
        return;
      }
      const platformMessages = getInstallMessages();
      if (platformMessages?.needsChromeForInstall) {
        installBtn.textContent = platformMessages.openChromeLabel || "Open in Chrome";
        return;
      }
      if (isPromptAvailable() && isMobileLikeViewport()) {
        installBtn.textContent = "Install App";
      } else if (isMobileLikeViewport()) {
        installBtn.textContent = "How To Install";
      }
    };

    const invokeInstallPrompt = async (source = "unknown") => {
      if (!isPromptAvailable()) {
        logInstall("Prompt unavailable.", { source });
        return { outcome: "unavailable" };
      }
      if (promptInFlight) {
        logInstall("Prompt already in flight; skipped duplicate call.", { source });
        return { outcome: "skipped" };
      }

      promptInFlight = true;
      logInstall("Install button clicked.", { source });

      const promptEvent = deferredPrompt;
      try {
        await promptEvent.prompt();
        const choice = await promptEvent.userChoice;
        if (choice?.outcome === "accepted") {
          logInstall("Prompt accepted.", { source });
        } else {
          logInstall("Prompt dismissed.", { source, outcome: choice?.outcome || "dismissed" });
        }
        clearDeferredPrompt();
        hideBanner();
        return choice;
      } catch (error) {
        const errorName = error && typeof error === "object" && "name" in error ? String(error.name) : "Error";
        logInstall("Prompt failed.", { source, error: errorName });
        if (errorName !== "AbortError") {
          clearDeferredPrompt();
        }
        return { outcome: "error", error };
      } finally {
        promptInFlight = false;
      }
    };

    window.madBaitsInstallPrompt = {
      isAvailable: isPromptAvailable,
      invoke: invokeInstallPrompt,
    };

    const renderInstallSteps = (stepsNode, messages, forceOpen = false) => {
      if (!(stepsNode instanceof HTMLElement)) {
        return;
      }
      const steps = Array.isArray(messages?.installSteps)
        ? messages.installSteps.filter((step) => typeof step === "string" && step.trim().length > 0)
        : [];
      const browserLabel = typeof messages?.browserLabel === "string" ? messages.browserLabel.trim() : "";
      if (!steps.length) {
        stepsNode.hidden = true;
        stepsNode.innerHTML = "";
        return;
      }
      const badgeHtml = browserLabel ? `<span class="mad-pwa-install__step-badge">${browserLabel}</span>` : "";
      stepsNode.innerHTML = steps.map((step) => `<li>${badgeHtml}<span>${step}</span></li>`).join("");
      if (forceOpen) {
        stepsNode.hidden = false;
      }
    };

    const ensureFooterInstallLink = () => {
      const footerMenu = document.querySelector(".site-footer__menu");
      if (!(footerMenu instanceof HTMLElement)) {
        return;
      }

      if (footerMenu.querySelector("[data-pwa-footer-install]")) {
        return;
      }

      const item = document.createElement("li");
      item.innerHTML =
        '<a href="#" data-pwa-footer-install class="mad-pwa-footer-install-link">Install Mad Baits App</a>';
      footerMenu.appendChild(item);

      const anchor = item.querySelector("[data-pwa-footer-install]");
      if (anchor instanceof HTMLAnchorElement) {
        anchor.addEventListener("click", (event) => {
          event.preventDefault();
          showBanner({ force: true });
          const installButton = bannerEl?.querySelector("[data-pwa-install]");
          if (installButton instanceof HTMLButtonElement) {
            installButton.focus();
          }
        });
      }
    };

    const hideBanner = (dismissed = false) => {
      if (!(bannerEl instanceof HTMLElement)) {
        return;
      }
      bannerEl.classList.remove("is-force-visible");
      bannerEl.classList.add("is-hidden");
      if (dismissed) {
        safeSetLocalStorage(installDismissKey, "1");
      }
    };

    const showBanner = (options = {}) => {
      const force = options.force === true;
      const mobileViewport = isMobileLikeViewport();
      const installLinks = {
        shop: typeof config.shopUrl === "string" && config.shopUrl ? config.shopUrl : "/shop/",
        bundleDeals:
          typeof config.bundleDealsUrl === "string" && config.bundleDealsUrl
            ? config.bundleDealsUrl
            : "/product-category/bundles-deals/",
        buildSession:
          typeof config.buildSessionUrl === "string" && config.buildSessionUrl
            ? config.buildSessionUrl
            : "/whats-in-the-water/",
        aiFinder:
          typeof config.aiFinderUrl === "string" && config.aiFinderUrl
            ? config.aiFinderUrl
            : "/ai-bait-finder/",
        account:
          typeof config.myAccountUrl === "string" && config.myAccountUrl
            ? config.myAccountUrl
            : "/my-account/",
      };

      if (isStandaloneMode()) {
        return;
      }

      // Auto surfaces stay mobile-only, but allow explicit desktop CTA clicks
      // to open the install guidance panel instead of a blocked alert dialog.
      if (!mobileViewport && !force) {
        return;
      }

      if (!force && safeGetLocalStorage(installDismissKey) === "1") {
        return;
      }

      if (bannerEl instanceof HTMLElement && !bannerEl.classList.contains("is-hidden") && !force) {
        logInstall("Banner already visible; skipped duplicate show.");
        updateBannerInstallButton();
        return;
      }

      if (!(bannerEl instanceof HTMLElement)) {
        const platformMessages = getInstallMessages();
        const hasInstallButton = isPromptAvailable() && !platformMessages?.needsChromeForInstall;
        const installButtonLabel = platformMessages?.needsChromeForInstall
          ? platformMessages.openChromeLabel || "Open in Chrome"
          : hasInstallButton && mobileViewport
            ? "Install App"
            : "How To Install";
        const desktopFallbackCopy =
          "Install works on mobile devices. Android: use Google Chrome. iPhone: Share, then Add to Home Screen.";
        const supportCopy = platformMessages?.needsChromeForInstall
          ? platformMessages.hint || getManualInstallHint()
          : hasInstallButton
            ? "Install the Mad Baits app for faster shopping, drops, bundles and account access."
            : mobileViewport
              ? getManualInstallHint()
              : desktopFallbackCopy;
        const warningCopy = platformMessages?.showWarning ? platformMessages.warning : "";

        bannerEl = document.createElement("aside");
        bannerEl.className = "mad-pwa-install is-hidden";
        bannerEl.innerHTML = `
          <div class="mad-pwa-install__copy">
            <strong>Add Mad Baits To Your Phone</strong>
            <p>${supportCopy}</p>
            <p class="mad-pwa-install__warning" data-pwa-install-warning${warningCopy ? "" : ' hidden'}>${warningCopy}</p>
          </div>
          <div class="mad-pwa-install__helper">
            <button type="button" class="mad-pwa-install__helper-toggle" data-pwa-install-help-toggle>Can’t install?</button>
            <ol class="mad-pwa-install__steps" data-pwa-install-steps hidden></ol>
          </div>
          <div class="mad-pwa-install__quick-links">
            <a href="${installLinks.shop}">Shop</a>
            <a href="${installLinks.bundleDeals}">Bundle Deals</a>
            <a href="${installLinks.buildSession}">Build Your Session</a>
            <a href="${installLinks.aiFinder}">AI Bait Finder</a>
            <a href="${installLinks.account}">My Account</a>
          </div>
          <div class="mad-pwa-install__actions">
            <button type="button" class="mad-button mad-button--small" data-pwa-install>${installButtonLabel}</button>
            <button type="button" class="mad-button mad-button--ghost mad-button--small" data-pwa-dismiss>Not Now</button>
          </div>
        `;
        document.body.appendChild(bannerEl);

        const installBtn = bannerEl.querySelector("[data-pwa-install]");
        const dismissBtn = bannerEl.querySelector("[data-pwa-dismiss]");
        const helpToggleBtn = bannerEl.querySelector("[data-pwa-install-help-toggle]");
        const helpSteps = bannerEl.querySelector("[data-pwa-install-steps]");

        if (helpSteps instanceof HTMLElement) {
          renderInstallSteps(helpSteps, platformMessages, false);
        }
        if (helpToggleBtn instanceof HTMLButtonElement && helpSteps instanceof HTMLElement) {
          helpToggleBtn.addEventListener("click", () => {
            const willOpen = helpSteps.hidden;
            helpSteps.hidden = !willOpen;
            helpToggleBtn.textContent = willOpen ? "Hide install steps" : "Can’t install?";
          });
        }

        if (installBtn instanceof HTMLButtonElement) {
          installBtn.addEventListener("click", async () => {
            const clickMessages = getInstallMessages();
            if (clickMessages?.needsChromeForInstall && clickMessages.openChromeUrl) {
              window.location.assign(clickMessages.openChromeUrl);
              return;
            }
            if (isPromptAvailable() && mobileViewport) {
              await invokeInstallPrompt("install-banner");
              return;
            }

            const copy = bannerEl?.querySelector(".mad-pwa-install__copy > p:not([data-pwa-install-warning])");
            const warning = bannerEl?.querySelector("[data-pwa-install-warning]");
            if (copy instanceof HTMLElement) {
              copy.textContent = mobileViewport
                ? getManualInstallHint()
                : "Open madbaits.com on your phone. Android: use Google Chrome. iPhone: Share, then Add to Home Screen.";
            }
            if (
              warning instanceof HTMLElement &&
              clickMessages &&
              window.madBaitsPwaInstall &&
              typeof window.madBaitsPwaInstall.setWarningOnElement === "function"
            ) {
              window.madBaitsPwaInstall.setWarningOnElement(warning, clickMessages);
            }
            if (helpSteps instanceof HTMLElement) {
              renderInstallSteps(helpSteps, clickMessages, true);
            }
            if (helpToggleBtn instanceof HTMLButtonElement && helpSteps instanceof HTMLElement && !helpSteps.hidden) {
              helpToggleBtn.textContent = "Hide install steps";
            }
          });
        }

        if (dismissBtn instanceof HTMLButtonElement) {
          dismissBtn.addEventListener("click", () => {
            hideBanner(true);
          });
        }
      }

      if (force && !mobileViewport) {
        bannerEl.classList.add("is-force-visible");
      } else {
        bannerEl.classList.remove("is-force-visible");
      }
      updateBannerInstallButton();
      bannerEl.classList.remove("is-hidden");
      logInstall("Banner shown.", { force, mobileViewport });
      ensureFooterInstallLink();
    };

    window.addEventListener("beforeinstallprompt", (event) => {
      event.preventDefault();
      deferredPrompt = event;
      hasNativePromptEvent = true;
      logInstall("Prompt captured; waiting for user tap before prompt().");
      updateBannerInstallButton();
      if (safeGetLocalStorage(installDismissKey) !== "1" && isMobileLikeViewport()) {
        showBanner();
      }
      window.dispatchEvent(new CustomEvent("mad:pwa-install-prompt-captured"));
    });

    window.addEventListener("appinstalled", () => {
      clearDeferredPrompt();
      hideBanner();
      safeSetLocalStorage(installDismissKey, "1");
      safeSetLocalStorage(installInstalledKey, "1");
      logInstall("PWA installed.");
    });

    if (isStandaloneMode()) {
      safeSetLocalStorage(installInstalledKey, "1");
      safeSetLocalStorage(installDismissKey, "1");
    }

    // Install UI is user-initiated only (footer link, home promo, or post-capture banner).
    // Do not auto-open banners or call prompt() on page load or timers.

    const openInstallHelpFromPromo = () => {
      showBanner({ force: true });
      const helpSteps = bannerEl?.querySelector("[data-pwa-install-steps]");
      const helpToggleBtn = bannerEl?.querySelector("[data-pwa-install-help-toggle]");
      if (helpSteps instanceof HTMLElement) {
        if (helpSteps.hidden) {
          helpSteps.hidden = false;
        }
      }
      if (helpToggleBtn instanceof HTMLButtonElement) {
        helpToggleBtn.textContent = "Hide install steps";
        helpToggleBtn.focus();
      }
    };

    document.addEventListener("click", (event) => {
      const rawTarget = event.target;
      const clickTarget =
        rawTarget instanceof Element
          ? rawTarget
          : rawTarget && rawTarget.parentElement instanceof Element
            ? rawTarget.parentElement
            : null;
      if (!(clickTarget instanceof Element)) {
        return;
      }

      const helpTrigger = clickTarget.closest("[data-pwa-promo-help]");
      if (helpTrigger instanceof HTMLElement) {
        event.preventDefault();
        openInstallHelpFromPromo();
        return;
      }

      const installTrigger = clickTarget.closest("[data-pwa-promo-install]");
      if (!(installTrigger instanceof HTMLElement)) {
        return;
      }

      event.preventDefault();
      if (isPromptAvailable()) {
        void invokeInstallPrompt("home-promo-install");
        return;
      }

      showBanner({ force: true });
      const installButton = bannerEl?.querySelector("[data-pwa-install]");
      if (installButton instanceof HTMLButtonElement) {
        installButton.focus();
      }
    });

    if (
      window.madBaitsPwaInstall &&
      typeof window.madBaitsPwaInstall.applyHomePromoHint === "function"
    ) {
      window.madBaitsPwaInstall.applyHomePromoHint();
    }
  };

  const initServiceWorker = () => {
    if (window.__madBaitsServiceWorkerInitDone) {
      return;
    }
    window.__madBaitsServiceWorkerInitDone = true;

    if (!("serviceWorker" in navigator)) {
      diag?.log("sw_unavailable");
      return;
    }

    if (!config.pwaServiceWorkerUrl) {
      diag?.log("sw_missing_url");
      return;
    }

    const isLocalhost =
      window.location.hostname === "localhost" ||
      window.location.hostname === "127.0.0.1" ||
      window.location.hostname === "[::1]";
    const isSecure = window.location.protocol === "https:" || isLocalhost;
    if (!isSecure) {
      logDev("Skipped service worker on insecure origin.");
      diag?.log("sw_insecure_origin_skip", { protocol: window.location.protocol });
      return;
    }

    diag?.log("sw_init_start", {
      path: window.location.pathname,
      buildId: String(config.pwaBuildId || ""),
      appVersion: String(config.appVersion || "")
    });

    const isUnsafeSwRefreshContext = () => {
      if (config.appUpdateUnsafe) {
        return true;
      }
      if (window.madBaitsAppUpdate && typeof window.madBaitsAppUpdate.isUnsafeForRefresh === "function") {
        return window.madBaitsAppUpdate.isUnsafeForRefresh();
      }
      const path = (window.location.pathname || "").toLowerCase();
      if (/\/product-category\//.test(path) || /\/product-tag\//.test(path) || /\/shop\/?$/.test(path)) {
        return true;
      }
      if (document.body?.classList.contains("woocommerce-cart")) {
        return true;
      }
      return false;
    };

    let hasRefreshed = false;
    navigator.serviceWorker.addEventListener("controllerchange", () => {
      diag?.log("sw_controllerchange", {
        hasRefreshed,
        unsafe: isUnsafeSwRefreshContext()
      });
      if (hasRefreshed) {
        return;
      }
      if (isUnsafeSwRefreshContext()) {
        return;
      }
      hasRefreshed = true;
      window.location.reload();
    });

    const isStandaloneApp = () =>
      window.matchMedia("(display-mode: standalone)").matches || window.navigator.standalone === true;

    const swUrlBase = String(config.pwaServiceWorkerUrl || "");
    const swVersion = encodeURIComponent(String(config.pwaBuildId || config.appVersion || "1"));
    const swUrl =
      swUrlBase +
      (swUrlBase.includes("?") ? "&" : "?") +
      "mad_baits_app_version=" +
      swVersion;

    navigator.serviceWorker
      .register(swUrl, { scope: "/" })
      .then((registration) => {
        logDev("Service worker registered.", registration.scope);
        diag?.log("sw_registered", {
          scope: registration.scope,
          scriptURL: registration.active?.scriptURL || registration.installing?.scriptURL || ""
        });

        const requestUpdate = () => {
          if (!registration.waiting) {
            return;
          }
          registration.waiting.postMessage({ type: "SKIP_WAITING" });
        };

        const runSafeRefresh = () => {
          if (window.madBaitsAppUpdate && typeof window.madBaitsAppUpdate.applyRefresh === "function") {
            window.madBaitsAppUpdate.applyRefresh();
            return;
          }
          requestUpdate();
        };

        const checkForWaitingUpdate = () => {
          if (!registration.waiting) {
            return;
          }
          if (isUnsafeSwRefreshContext()) {
            return;
          }
          if (window.madBaitsAppUpdate && typeof window.madBaitsAppUpdate.isUnsafeForRefresh === "function") {
            if (window.madBaitsAppUpdate.isUnsafeForRefresh()) {
              return;
            }
          }
          if (window.madBaitsAppUpdate) {
            if (typeof window.madBaitsAppUpdate.tryPromptUpdate === "function" && window.madBaitsAppUpdate.tryPromptUpdate()) {
              return;
            }
            if (typeof window.madBaitsAppUpdate.showUpdateModal === "function") {
              window.madBaitsAppUpdate.showUpdateModal(runSafeRefresh);
              return;
            }
          }
          createUpdatePrompt(runSafeRefresh);
        };

        if (!isUnsafeSwRefreshContext()) {
          checkForWaitingUpdate();

          const updateIntervalMs = window.__madBaitsIosPwaSafeMode ? 180000 : 60000;
          const updateTimerId = window.setInterval(() => {
            registration.update().catch(() => {});
            checkForWaitingUpdate();
          }, updateIntervalMs);
          const onVisibilityChange = () => {
            if (document.visibilityState === "visible") {
              registration.update().catch(() => {});
              checkForWaitingUpdate();
            }
          };
          document.addEventListener("visibilitychange", onVisibilityChange);
          window.addEventListener(
            "pagehide",
            () => {
              window.clearInterval(updateTimerId);
              document.removeEventListener("visibilitychange", onVisibilityChange);
              diag?.log("sw_listener_cleanup", { reason: "pagehide" });
            },
            { once: true }
          );

          registration.addEventListener("updatefound", () => {
            diag?.log("sw_updatefound");
            const installingWorker = registration.installing;
            if (!installingWorker) {
              return;
            }

            installingWorker.addEventListener("statechange", () => {
              diag?.log("sw_statechange", { state: installingWorker.state });
              if (installingWorker.state === "installed" && navigator.serviceWorker.controller) {
                checkForWaitingUpdate();
              }
            });
          });
        } else {
          diag?.log("sw_update_polling_skipped", { reason: "unsafe_refresh_context" });
        }
      })
      .catch((error) => {
        logDev("Service worker registration failed.", error);
        diag?.log("sw_registration_failed", { message: error?.message || String(error || "") });
      });
  };

  async function requestNotificationPermission() {
    if (!("Notification" in window)) {
      return "unsupported";
    }
    return Notification.requestPermission();
  }

  async function subscribeToPush() {
    // Placeholder for future push provider integration:
    // - drops/restocks
    // - bundle offers
    // - fresh bait announcements
    // Keep this manual-trigger only; do not auto-subscribe on page load.
    return {
      success: false,
      reason: "Push setup not configured yet."
    };
  }

  function handlePushMessage(payload) {
    // Placeholder event handler for future payload rendering and deep-link routing.
    logDev("Push payload received.", payload);
  }

  window.madBaitsPwa = {
    requestNotificationPermission,
    subscribeToPush,
    handlePushMessage
  };

  initServiceWorker();
  initInstallPrompt();
})();

(() => {
  window.__madBaitsAppShellInitCount = (window.__madBaitsAppShellInitCount || 0) + 1;
  if (window.__madBaitsAppShellInitCount > 1) {
    console.warn("[Mad Baits AppShell] Duplicate app-shell init blocked", {
      count: window.__madBaitsAppShellInitCount
    });
    return;
  }
  const config = window.madBaitsConfig || {};
  const diag = window.__madBaitsDiag;
  const isStandalone =
    window.matchMedia("(display-mode: standalone)").matches || window.navigator.standalone === true;
  const isMobile = window.matchMedia("(max-width: 768px)").matches;
  const root = document.documentElement;
  const body = document.body;

  const favouritesStorageKey = "madBaitsGuestFavourites";
  const viewedStorageKey = "madBaitsRecentlyViewed";
  const journalStorageKey = "madBaitsSessionJournal";

  const safeParse = (value, fallback = []) => {
    try {
      const parsed = JSON.parse(String(value || ""));
      return Array.isArray(parsed) ? parsed : fallback;
    } catch (_err) {
      return fallback;
    }
  };

  const safeGet = (key, fallback = []) => {
    try {
      return safeParse(localStorage.getItem(key), fallback);
    } catch (_error) {
      return fallback;
    }
  };

  const safeSet = (key, value) => {
    try {
      localStorage.setItem(key, JSON.stringify(value));
    } catch (_error) {
      // no-op
    }
  };

  if (body instanceof HTMLElement) {
    body.classList.toggle("is-standalone-app", Boolean(isStandalone));
    body.classList.toggle("is-pwa", Boolean(isStandalone));
    body.classList.toggle("is-mobile-app-shell", Boolean(isMobile));
    if (window.__madBaitsIosPwaSafeMode) {
      body.classList.add("mad-ios-pwa-safe-mode");
    }
  }
  if (root instanceof HTMLElement) {
    root.classList.toggle("is-standalone-app", Boolean(isStandalone));
  }

  if (isMobile && body instanceof HTMLElement && body.classList.contains("home")) {
    const hero = document.querySelector(".hero--immersive");
    const heroTitle = hero?.querySelector(".hero__content h1");
    const heroActions = hero?.querySelectorAll(".hero__actions .mad-button");
    const trustItems = hero?.querySelectorAll(".hero__meta li");

    if (heroTitle instanceof HTMLElement) {
      heroTitle.textContent = "Serious Carp Bait. Session Ready.";
    }

    if (heroActions && heroActions.length >= 2) {
      const first = heroActions[0];
      const second = heroActions[1];
      if (first instanceof HTMLElement) {
        first.textContent = "Shop Baits";
      }
      if (second instanceof HTMLElement) {
        second.textContent = "Build Your Session";
      }
    }

    if (trustItems && trustItems.length >= 3) {
      const trustCopy = ["Fresh bait", "Proven mixes", "Fast dispatch"];
      trustItems.forEach((item, idx) => {
        if (item instanceof HTMLElement && trustCopy[idx]) {
          item.textContent = trustCopy[idx];
        }
      });
    }
  }

  const normalizePath = (path) => String(path || "/").replace(/\/+$/, "") || "/";

  const isNavKeyActive = (key, currentPath) => {
    if (key === "home") {
      return currentPath === "/" || currentPath === "/index.php";
    }
    if (key === "shop") {
      return (
        currentPath.includes("/shop") ||
        currentPath.includes("/product-category") ||
        currentPath.includes("/product-tag") ||
        (currentPath.includes("/product/") && !currentPath.includes("/product-category"))
      );
    }
    if (key === "deals") {
      return (
        currentPath.includes("bundles-deals") ||
        currentPath.includes("bundle-deals") ||
        currentPath.includes("/product-category/bundles") ||
        currentPath.includes("/product-category/deals")
      );
    }
    if (key === "cart") {
      return currentPath.includes("/cart") || currentPath.includes("/basket");
    }
    if (key === "session") {
      return (
        currentPath.includes("/session") ||
        currentPath.includes("whats-in-the-water") ||
        currentPath.includes("build-my-session") ||
        currentPath.includes("build-your-session")
      );
    }
    return false;
  };

  const syncAppCartBadges = (count, pulse = false) => {
    const safeCount = Math.max(0, Number(count) || 0);
    document.querySelectorAll("[data-app-cart-count], [data-app-top-cart-count]").forEach((node) => {
      if (!(node instanceof HTMLElement)) {
        return;
      }
      node.textContent = String(safeCount);
      node.hidden = safeCount < 1;
      if (pulse && safeCount > 0) {
        node.classList.remove("is-pulse");
        void node.offsetWidth;
        node.classList.add("is-pulse");
      }
    });
  };

  let appToastTimer = null;
  const showAppToast = (message, isError = false) => {
    const text =
      message ||
      (isError
        ? config.i18nAddFailed || "Could not add to basket. Try again."
        : config.i18nAddedToBasket || config.i18nAddedToBucket || config.i18nAdded || "Added to your basket");
    if (!text || !isMobile) {
      return;
    }
    let toast = document.querySelector("[data-app-toast]");
    if (!(toast instanceof HTMLElement)) {
      toast = document.createElement("div");
      toast.dataset.appToast = "true";
      toast.className = "mad-app-toast";
      toast.setAttribute("role", "status");
      toast.setAttribute("aria-live", "polite");
      document.body.appendChild(toast);
    }
    toast.textContent = text;
    toast.classList.toggle("is-error", Boolean(isError));
    toast.classList.add("is-visible");
    if (appToastTimer) {
      window.clearTimeout(appToastTimer);
    }
    appToastTimer = window.setTimeout(() => {
      toast.classList.remove("is-visible", "is-error");
    }, 2200);
    if (isError && typeof window.madBaitsTriggerHaptic === "function") {
      window.madBaitsTriggerHaptic("warning");
    }
  };

  window.madBaitsShowAppToast = showAppToast;

  if (typeof config.cartCount === "number") {
    syncAppCartBadges(config.cartCount);
  }

  const appTopBar = document.querySelector("[data-mad-app-top-bar]");
  if (appTopBar instanceof HTMLElement) {
    const topFallback =
      appTopBar.getAttribute("data-fallback-url") ||
      (typeof config.shopUrl === "string" ? config.shopUrl : "") ||
      "/";
    const fallbackUrls = [];
    try {
      const fromAttr = JSON.parse(appTopBar.getAttribute("data-fallback-urls") || "[]");
      if (Array.isArray(fromAttr)) {
        fromAttr.forEach((url) => {
          if (typeof url === "string" && url) {
            fallbackUrls.push(url);
          }
        });
      }
    } catch (_err) {
      // Ignore malformed fallback json.
    }
    if (typeof config.shopUrl === "string" && config.shopUrl) {
      fallbackUrls.push(config.shopUrl);
    }
    if (typeof config.bundleDealsUrl === "string" && config.bundleDealsUrl) {
      fallbackUrls.push(config.bundleDealsUrl);
    }
    fallbackUrls.push("/");
    const uniqueFallbacks = [...new Set(fallbackUrls)].filter(Boolean);
    const finalFallback = topFallback || uniqueFallbacks[0] || "/";

    const navigateAppBack = () => {
      const isRecursiveBack = diag?.isLooping("app_back_navigation", 6, 2000);
      diag?.log("app_back_navigation", {
        path: window.location.pathname,
        isRecursiveBack
      });
      if (isRecursiveBack) {
        window.location.assign(finalFallback);
        return;
      }
      const currentHref = window.location.href;
      try {
        const ref = document.referrer;
        if (ref) {
          const sameOrigin = new URL(ref).origin === window.location.origin;
          if (sameOrigin) {
            window.history.back();
            window.setTimeout(() => {
              if (window.location.href === currentHref) {
                window.location.assign(finalFallback);
              }
            }, 280);
            return;
          }
          window.location.assign(finalFallback);
          return;
        }
        if (typeof window.history.length === "number" && window.history.length > 1) {
          window.history.back();
          window.setTimeout(() => {
            if (window.location.href === currentHref) {
              window.location.assign(finalFallback);
            }
          }, 280);
          return;
        }
      } catch (_err) {
        // ignore
      }
      const nextFallback = uniqueFallbacks.find((url) => url && url !== currentHref) || finalFallback;
      window.location.assign(nextFallback);
    };

    const backTrigger = appTopBar.querySelector("[data-app-back]");
    if (backTrigger instanceof HTMLButtonElement) {
      backTrigger.addEventListener("click", (event) => {
        event.preventDefault();
        navigateAppBack();
      });
    }

    const menuTrigger = appTopBar.querySelector("[data-app-top-open-menu]");
    if (menuTrigger instanceof HTMLButtonElement) {
      const openAppMobileMenu = (event) => {
        event.preventDefault();
        event.stopPropagation();
        document.dispatchEvent(new CustomEvent("mad:open-mobile-menu"));
      };
      menuTrigger.addEventListener("click", openAppMobileMenu);
    }

    let topBarScrollQueued = false;
    const syncTopBarScroll = () => {
      appTopBar.classList.toggle("mad-app-top-bar--compact", window.scrollY > 10);
    };

    const onTopBarScroll = () => {
        if (topBarScrollQueued) {
          return;
        }
        topBarScrollQueued = true;
        window.requestAnimationFrame(() => {
          topBarScrollQueued = false;
          syncTopBarScroll();
        });
      };
    window.addEventListener("scroll", onTopBarScroll, { passive: true });
    window.addEventListener(
      "pagehide",
      () => {
        window.removeEventListener("scroll", onTopBarScroll, { passive: true });
      },
      { once: true }
    );
    syncTopBarScroll();
  }

  const appNav = document.querySelector("[data-app-bottom-nav]");
  if (appNav instanceof HTMLElement) {
    const hideBottomNav =
      Boolean(config.isMobileBottomNavHidden) ||
      document.body.classList.contains("mad-app-nav-hidden-context") ||
      document.body.matches(
        ".woocommerce-cart, .woocommerce-checkout, .woocommerce-order-pay, .woocommerce-order-received"
      );
    if (hideBottomNav) {
      appNav.hidden = true;
      appNav.setAttribute("aria-hidden", "true");
      appNav.style.display = "none";
      appNav.style.pointerEvents = "none";
    } else {
      const currentPath = normalizePath(window.location.pathname);
      const navItems = appNav.querySelectorAll("[data-app-nav-item]");
      navItems.forEach((item) => {
        const navKey = item.getAttribute("data-app-nav-key") || "";
        let isActive = isNavKeyActive(navKey, currentPath);

        if (!isActive && item instanceof HTMLAnchorElement) {
          const targetPath = normalizePath(new URL(item.href, window.location.origin).pathname);
          isActive =
            currentPath === targetPath ||
            (targetPath !== "/" && currentPath.startsWith(`${targetPath}/`));
        }

        item.classList.toggle("is-active", isActive);
      });
    }
  }

  const sessionSheetShell = document.querySelector("[data-app-session-sheet]");
  const sessionOpenTriggers = document.querySelectorAll("[data-app-session-open]");
  if (sessionSheetShell instanceof HTMLElement && sessionOpenTriggers.length) {
    const closeTriggers = sessionSheetShell.querySelectorAll("[data-app-session-close]");
    const sheetPanel = sessionSheetShell.querySelector(".mad-app-session-sheet");
    const sheetFocusTarget = sessionSheetShell.querySelector(".mad-app-session-sheet__close");
    let sessionCloseTimer = null;

    const clearSessionCloseTimer = () => {
      if (sessionCloseTimer) {
        window.clearTimeout(sessionCloseTimer);
        sessionCloseTimer = null;
      }
    };

    const openSessionSheet = () => {
      clearSessionCloseTimer();
      sessionSheetShell.hidden = false;
      sessionSheetShell.setAttribute("aria-hidden", "false");
      document.body.classList.add("mad-app-session-sheet-open");
      window.requestAnimationFrame(() => {
        sessionSheetShell.classList.add("is-open");
      });
      if (sheetFocusTarget instanceof HTMLElement) {
        window.setTimeout(() => sheetFocusTarget.focus({ preventScroll: true }), 100);
      }
    };

    const closeSessionSheet = () => {
      clearSessionCloseTimer();
      sessionSheetShell.classList.remove("is-open");
      sessionSheetShell.setAttribute("aria-hidden", "true");
      document.body.classList.remove("mad-app-session-sheet-open");
      sessionCloseTimer = window.setTimeout(() => {
        sessionSheetShell.hidden = true;
      }, 220);
    };

    sessionOpenTriggers.forEach((trigger) => {
      trigger.addEventListener("click", (event) => {
        event.preventDefault();
        openSessionSheet();
      });
    });

    closeTriggers.forEach((trigger) => {
      trigger.addEventListener("click", (event) => {
        event.preventDefault();
        closeSessionSheet();
      });
    });

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && sessionSheetShell.classList.contains("is-open")) {
        closeSessionSheet();
      }
    });

    if (sheetPanel instanceof HTMLElement) {
      sheetPanel.addEventListener("click", (event) => event.stopPropagation());
    }
  }

  const normaliseProduct = (payload) => {
    const id = Number.parseInt(String(payload?.id || 0), 10);
    if (!Number.isFinite(id) || id < 1) {
      return null;
    }
    return {
      id,
      name: String(payload?.name || "Mad Baits Product"),
      url: String(payload?.url || window.location.href),
      price: String(payload?.price || ""),
      ts: Date.now(),
    };
  };

  const getGuestFavourites = () =>
    safeGet(favouritesStorageKey, [])
      .map((value) => Number.parseInt(String(value), 10))
      .filter((value) => Number.isFinite(value) && value > 0);

  const setGuestFavourites = (ids) => {
    const normalized = Array.from(
      new Set(
        (Array.isArray(ids) ? ids : [])
          .map((value) => Number.parseInt(String(value), 10))
          .filter((value) => Number.isFinite(value) && value > 0)
      )
    );
    safeSet(favouritesStorageKey, normalized);
    return normalized;
  };

  const getViewedProducts = () => safeGet(viewedStorageKey, []).filter((entry) => entry && Number(entry.id) > 0).slice(0, 18);
  const setViewedProducts = (entries) => {
    const cleaned = (Array.isArray(entries) ? entries : []).filter((entry) => entry && Number(entry.id) > 0).slice(0, 18);
    safeSet(viewedStorageKey, cleaned);
    return cleaned;
  };

  const addRecentlyViewed = (productPayload) => {
    const normalized = normaliseProduct(productPayload);
    if (!normalized) {
      return;
    }
    const current = getViewedProducts().filter((item) => Number(item.id) !== normalized.id);
    current.unshift(normalized);
    setViewedProducts(current);
  };

  if (config.isProduct && Number(config.currentProductId) > 0) {
    const titleEl = document.querySelector(".product_title");
    const priceEl = document.querySelector(".summary .price");
    addRecentlyViewed({
      id: Number(config.currentProductId),
      name: titleEl instanceof HTMLElement ? titleEl.textContent?.trim() : document.title,
      url: window.location.href,
      price: priceEl instanceof HTMLElement ? priceEl.textContent?.trim() : "",
    });
  }

  const favouriteButtons = document.querySelectorAll("[data-favourite-toggle]");
  const favouriteState = {
    ids: getGuestFavourites(),
  };

  const applyFavouriteUi = () => {
    favouriteButtons.forEach((btn) => {
      if (!(btn instanceof HTMLButtonElement)) {
        return;
      }
      const productId = Number.parseInt(String(btn.getAttribute("data-product-id") || "0"), 10);
      const isSaved = favouriteState.ids.includes(productId);
      btn.classList.toggle("is-favourite", isSaved);
      btn.setAttribute("aria-pressed", isSaved ? "true" : "false");
      btn.setAttribute("aria-label", isSaved ? "Remove from favourites" : "Save to favourites");
    });
  };

  const syncFavouritesFromServer = async () => {
    if (!config.isLoggedIn || !config.ajaxUrl || !config.favouritesNonce) {
      applyFavouriteUi();
      return;
    }

    const payload = new URLSearchParams();
    payload.set("action", config.favouritesGetAction || "mad_baits_favourites_get");
    payload.set("nonce", config.favouritesNonce);
    try {
      const response = await fetch(config.ajaxUrl, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        credentials: "same-origin",
        body: payload.toString(),
      });
      const data = await response.json();
      if (data?.success && data?.data?.logged_in && Array.isArray(data?.data?.ids)) {
        favouriteState.ids = data.data.ids
          .map((id) => Number.parseInt(String(id), 10))
          .filter((id) => Number.isFinite(id) && id > 0);
      }
    } catch (_err) {
      // fallback to local
    }
    applyFavouriteUi();
  };

  favouriteButtons.forEach((btn) => {
    if (!(btn instanceof HTMLButtonElement)) {
      return;
    }

    btn.addEventListener("click", async () => {
      const productId = Number.parseInt(String(btn.getAttribute("data-product-id") || "0"), 10);
      if (!Number.isFinite(productId) || productId < 1) {
        return;
      }

      if (config.isLoggedIn && config.ajaxUrl && config.favouritesNonce) {
        const payload = new URLSearchParams();
        payload.set("action", config.favouritesToggleAction || "mad_baits_favourites_toggle");
        payload.set("nonce", config.favouritesNonce);
        payload.set("product_id", String(productId));
        try {
          const response = await fetch(config.ajaxUrl, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
            credentials: "same-origin",
            body: payload.toString(),
          });
          const data = await response.json();
          if (data?.success && data?.data?.logged_in && Array.isArray(data?.data?.ids)) {
            favouriteState.ids = data.data.ids
              .map((id) => Number.parseInt(String(id), 10))
              .filter((id) => Number.isFinite(id) && id > 0);
            applyFavouriteUi();
            hydrateAppLists();
            return;
          }
        } catch (_err) {
          // local fallback below
        }
      }

      const ids = getGuestFavourites();
      const next = ids.includes(productId) ? ids.filter((id) => id !== productId) : [...ids, productId];
      favouriteState.ids = setGuestFavourites(next);
      applyFavouriteUi();
      hydrateAppLists();
    });
  });

  const makeSimpleListItem = (label, href = "#", sub = "") => {
    const node = document.createElement("a");
    node.className = "mad-app-list-item";
    node.href = href;
    node.innerHTML = `<strong>${label}</strong>${sub ? `<span>${sub}</span>` : ""}`;
    return node;
  };

  const fetchProductPreviewItems = async (ids) => {
    const cleaned = (Array.isArray(ids) ? ids : [])
      .map((id) => Number.parseInt(String(id), 10))
      .filter((id) => Number.isFinite(id) && id > 0)
      .slice(0, 12);

    if (!cleaned.length || !config.ajaxUrl || !config.favouritesNonce) {
      return [];
    }

    const payload = new URLSearchParams();
    payload.set("action", config.favouritesPreviewAction || "mad_baits_get_product_previews");
    payload.set("nonce", config.favouritesNonce);
    cleaned.forEach((id) => payload.append("product_ids[]", String(id)));

    try {
      const response = await fetch(config.ajaxUrl, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        credentials: "same-origin",
        body: payload.toString(),
      });
      const data = await response.json();
      if (data?.success && Array.isArray(data?.data?.items)) {
        return data.data.items;
      }
    } catch (_err) {
      // no-op
    }

    return [];
  };

  const hydrateAppLists = async () => {
    const wrappers = document.querySelectorAll("[data-app-list]");
    wrappers.forEach((wrapper) => {
      if (!(wrapper instanceof HTMLElement)) {
        return;
      }
      const kind = wrapper.getAttribute("data-app-list") || "";
      const target = wrapper.querySelector("[data-app-list-items]");
      if (!(target instanceof HTMLElement)) {
        return;
      }
      target.innerHTML = "";
      target.setAttribute("data-list-kind", kind);
    });

    const recentlyViewedIds = getViewedProducts()
      .slice(0, 4)
      .map((item) => Number.parseInt(String(item?.id || 0), 10))
      .filter((id) => Number.isFinite(id) && id > 0);
    const favouriteIds = favouriteState.ids.slice(0, 8);

    const [recentPreviews, favouritePreviews] = await Promise.all([
      fetchProductPreviewItems(recentlyViewedIds),
      fetchProductPreviewItems(favouriteIds),
    ]);

    wrappers.forEach((wrapper) => {
      if (!(wrapper instanceof HTMLElement)) {
        return;
      }
      const kind = wrapper.getAttribute("data-app-list") || "";
      const target = wrapper.querySelector("[data-app-list-items]");
      if (!(target instanceof HTMLElement)) {
        return;
      }
      if (kind === "recently-viewed") {
        if (!recentPreviews.length) {
          target.appendChild(makeSimpleListItem("No recently viewed products yet", config.siteUrl || "/"));
          return;
        }
        recentPreviews.forEach((item) => {
          if (!item?.html) {
            return;
          }
          target.insertAdjacentHTML("beforeend", String(item.html));
        });
        return;
      }

      if (kind === "favourites") {
        if (!favouritePreviews.length) {
          target.appendChild(makeSimpleListItem("No favourites saved yet", config.siteUrl || "/shop/"));
          return;
        }
        favouritePreviews.slice(0, 4).forEach((item) => {
          if (!item?.html) {
            return;
          }
          target.insertAdjacentHTML("beforeend", String(item.html));
        });
        return;
      }

      if (kind === "trending") {
        target.appendChild(makeSimpleListItem("Trending bait this week", "/shop/"));
        target.appendChild(makeSimpleListItem("Most viewed range", "/boilie-range/"));
        target.appendChild(makeSimpleListItem("Popular session combination", "/whats-in-the-water/"));
      }
    });

    const favouritesList = document.querySelector("[data-favourites-list]");
    if (favouritesList instanceof HTMLElement) {
      favouritesList.innerHTML = "";
      if (!favouritePreviews.length) {
        favouritesList.appendChild(makeSimpleListItem("No favourites yet", "/shop/"));
      } else {
        favouritePreviews.forEach((item) => {
          if (!item?.html) {
            return;
          }
          favouritesList.insertAdjacentHTML("beforeend", String(item.html));
        });
      }
    }
  };

  const addAllFavouritesButton = document.querySelector("[data-favourites-add-all]");
  if (addAllFavouritesButton instanceof HTMLButtonElement) {
    addAllFavouritesButton.addEventListener("click", async () => {
      if (!config.isLoggedIn || !config.ajaxUrl || !config.favouritesNonce) {
        window.location.href = "/my-account/";
        return;
      }
      const payload = new URLSearchParams();
      payload.set("action", config.favouritesAddAllAction || "mad_baits_favourites_add_all_to_cart");
      payload.set("nonce", config.favouritesNonce);
      try {
        const response = await fetch(config.ajaxUrl, {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
          credentials: "same-origin",
          body: payload.toString(),
        });
        const data = await response.json();
        if (data?.success && data?.data?.cart_url) {
          window.location.href = String(data.data.cart_url);
        }
      } catch (_err) {
        // no-op
      }
    });
  }

  const journalEntries = safeGet(journalStorageKey, []);
  const updateJournalStats = () => {
    const sessions = journalEntries.length;
    const ranges = new Set(journalEntries.map((entry) => String(entry?.range || "").trim()).filter(Boolean)).size;
    const baits = new Set(journalEntries.map((entry) => String(entry?.bait || "").trim()).filter(Boolean)).size;
    const map = { sessions, ranges, baits };
    Object.keys(map).forEach((key) => {
      const el = document.querySelector(`[data-journal-stat="${key}"]`);
      if (el instanceof HTMLElement) {
        el.textContent = String(map[key]);
      }
    });

    const journalList = document.querySelector("[data-journal-list]");
    if (journalList instanceof HTMLElement) {
      journalList.innerHTML = "";
      if (!journalEntries.length) {
        journalList.appendChild(makeSimpleListItem("No sessions logged yet", "/whats-in-the-water/"));
      } else {
        journalEntries.slice(0, 6).forEach((entry) => {
          const row = document.createElement("article");
          row.className = "mad-journal-entry";
          const date = new Date(Number(entry?.ts || Date.now()));
          row.innerHTML = `
            <strong>${String(entry?.range || "Session entry")}</strong>
            <span>${String(entry?.bait || "Bait selection")}</span>
            <span>${date.toLocaleDateString("en-GB")}</span>
          `;
          journalList.appendChild(row);
        });
      }
    }
  };

  const quickLogBtn = document.querySelector("[data-journal-log-quick]");
  if (quickLogBtn instanceof HTMLButtonElement) {
    quickLogBtn.addEventListener("click", () => {
      const range = window.prompt("Range used?", "ASBO");
      if (!range) {
        return;
      }
      const bait = window.prompt("Primary bait/hookbait?", "15mm + matching popup");
      if (!bait) {
        return;
      }
      const next = {
        ts: Date.now(),
        range: String(range),
        bait: String(bait),
      };
      journalEntries.unshift(next);
      safeSet(journalStorageKey, journalEntries.slice(0, 40));
      updateJournalStats();
    });
  }

  const aiForm = document.querySelector("[data-ai-bait-finder-form]");
  if (aiForm instanceof HTMLFormElement) {
    const fields = Array.from(aiForm.querySelectorAll("[data-ai-step]"));
    const maxStep = 6;
    let currentStep = 1;
    const nextBtn = aiForm.querySelector("[data-ai-next]");
    const prevBtn = aiForm.querySelector("[data-ai-prev]");
    const submitBtn = aiForm.querySelector('button[type="submit"]');
    const progressLabel = aiForm.querySelector("[data-ai-progress-label]");
    const progressBar = aiForm.querySelector("[data-ai-progress-bar]");
    const answersWrap = aiForm.querySelector("[data-ai-answers]");
    const answersList = aiForm.querySelector("[data-ai-answers-list]");
    const statusEl = aiForm.querySelector("[data-ai-status]");

    const getFieldValue = (field) => {
      const control = field.querySelector("input, select, textarea");
      if (!(control instanceof HTMLInputElement || control instanceof HTMLSelectElement || control instanceof HTMLTextAreaElement)) {
        return "";
      }
      if (control instanceof HTMLSelectElement) {
        const opt = control.options[control.selectedIndex];
        return opt ? String(opt.textContent || control.value || "").trim() : String(control.value || "").trim();
      }
      return String(control.value || "").trim();
    };

    const validateStep = (step) => {
      const stepFields = fields.filter((field) => {
        if (!(field instanceof HTMLElement)) {
          return false;
        }
        return Number.parseInt(String(field.getAttribute("data-ai-step") || "1"), 10) === step;
      });
      for (const field of stepFields) {
        const control = field.querySelector("input, select, textarea");
        if (!(control instanceof HTMLInputElement || control instanceof HTMLSelectElement || control instanceof HTMLTextAreaElement)) {
          continue;
        }
        if (control.required && !String(control.value || "").trim()) {
          control.focus({ preventScroll: true });
          if (statusEl instanceof HTMLElement) {
            statusEl.textContent = "Please complete this step before continuing.";
          }
          return false;
        }
      }
      if (statusEl instanceof HTMLElement) {
        statusEl.textContent = "";
      }
      return true;
    };

    const updateAnswersSummary = () => {
      if (!(answersList instanceof HTMLElement) || !(answersWrap instanceof HTMLElement)) {
        return;
      }
      answersList.innerHTML = "";
      let count = 0;
      fields.forEach((field) => {
        if (!(field instanceof HTMLElement)) {
          return;
        }
        const step = Number.parseInt(String(field.getAttribute("data-ai-step") || "1"), 10);
        if (step >= currentStep) {
          return;
        }
        const label = field.querySelector("span")?.textContent?.trim() || "Answer";
        const value = getFieldValue(field);
        if (!value) {
          return;
        }
        count += 1;
        const li = document.createElement("li");
        li.innerHTML = `<strong>${label}:</strong> ${value}`;
        answersList.appendChild(li);
      });
      answersWrap.hidden = count < 1;
    };

    const setStep = (step) => {
      currentStep = Math.max(1, Math.min(maxStep, step));
      fields.forEach((field) => {
        if (!(field instanceof HTMLElement)) {
          return;
        }
        const fieldStep = Number.parseInt(String(field.getAttribute("data-ai-step") || "1"), 10);
        field.hidden = fieldStep !== currentStep;
      });
      if (prevBtn instanceof HTMLButtonElement) {
        prevBtn.hidden = currentStep <= 1;
      }
      if (nextBtn instanceof HTMLButtonElement) {
        nextBtn.hidden = currentStep >= maxStep;
      }
      if (submitBtn instanceof HTMLButtonElement) {
        submitBtn.hidden = currentStep !== maxStep;
      }
      if (progressLabel instanceof HTMLElement) {
        progressLabel.textContent = `Step ${currentStep} of ${maxStep}`;
      }
      if (progressBar instanceof HTMLElement) {
        progressBar.style.width = `${(currentStep / maxStep) * 100}%`;
      }
      updateAnswersSummary();
      const firstVisible = fields.find((field) => {
        if (!(field instanceof HTMLElement) || field.hidden) {
          return false;
        }
        return Number.parseInt(String(field.getAttribute("data-ai-step") || "1"), 10) === currentStep;
      });
      const focusTarget = firstVisible instanceof HTMLElement
        ? firstVisible.querySelector("input, select, textarea")
        : null;
      if (focusTarget instanceof HTMLElement) {
        window.setTimeout(() => focusTarget.focus({ preventScroll: true }), 50);
      }
    };

    if (nextBtn instanceof HTMLButtonElement) {
      nextBtn.addEventListener("click", () => {
        if (!validateStep(currentStep)) {
          return;
        }
        setStep(currentStep + 1);
      });
    }
    if (prevBtn instanceof HTMLButtonElement) {
      prevBtn.addEventListener("click", () => setStep(currentStep - 1));
    }
    aiForm.addEventListener("submit", (event) => {
      if (!validateStep(currentStep)) {
        event.preventDefault();
        event.stopImmediatePropagation();
      }
    }, true);
    setStep(1);
  }

  const aiResult = document.querySelector("[data-ai-result]");
  if (aiResult instanceof HTMLElement) {
    const addSessionBtn = aiResult.querySelector("[data-ai-add-session]");
    if (addSessionBtn instanceof HTMLButtonElement) {
      addSessionBtn.addEventListener("click", async () => {
        let productIds = [];
        try {
          productIds = JSON.parse(aiResult.dataset.recommendedProductIds || "[]");
        } catch (_error) {
          productIds = [];
        }

        if (!Array.isArray(productIds) || !productIds.length) {
          return;
        }

        const originalLabel = addSessionBtn.textContent || "";
        addSessionBtn.disabled = true;
        addSessionBtn.textContent = madBaitsConfig?.i18nAiSessionAdding || "Adding recommended session to basket...";

        try {
          const outcome = await addAiProductsToCart(productIds);
          if (!outcome.success) {
            window.alert(outcome.message);
            return;
          }
          addSessionBtn.textContent = outcome.message || madBaitsConfig?.i18nAiSessionAdded || "Added";
        } catch (_error) {
          window.alert(madBaitsConfig?.i18nAiSessionAddFailed || "Could not add recommended products.");
        } finally {
          window.setTimeout(() => {
            addSessionBtn.disabled = false;
            addSessionBtn.textContent = originalLabel;
          }, 1400);
        }
      });
    }

    aiResult.addEventListener("click", async (event) => {
      const trigger = event.target.closest("[data-ai-add-product]");
      if (!(trigger instanceof HTMLButtonElement)) {
        return;
      }

      const productId = Number.parseInt(String(trigger.getAttribute("data-ai-add-product") || ""), 10);
      if (!productId) {
        return;
      }

      event.preventDefault();
      const originalLabel = trigger.textContent || "";
      trigger.disabled = true;

      try {
        const outcome = await addAiProductsToCart([productId]);
        if (!outcome.success) {
          window.alert(outcome.message);
          return;
        }
        trigger.textContent = madBaitsConfig?.i18nAdded || "Added";
      } catch (_error) {
        window.alert(madBaitsConfig?.i18nAddFailed || "Could not add to basket. Try again.");
      } finally {
        window.setTimeout(() => {
          trigger.disabled = false;
          trigger.textContent = originalLabel;
        }, 900);
      }
    });
  }

  const sessionBuilder = document.querySelector("[data-session-builder]");
  if (sessionBuilder instanceof HTMLElement) {
    const reviewTotal = sessionBuilder.querySelector("[data-session-review-total]");
    if (reviewTotal instanceof HTMLElement && !sessionBuilder.querySelector("[data-session-savings]")) {
      const savings = document.createElement("p");
      savings.className = "mad-session-builder__savings";
      savings.setAttribute("data-session-savings", "true");
      savings.textContent = "Estimated savings: --";
      reviewTotal.insertAdjacentElement("afterend", savings);

      if (!window.__madBaitsIosPwaSafeMode) {
        const observer = new MutationObserver(() => {
          const numeric = Number.parseFloat((reviewTotal.textContent || "").replace(/[^0-9.]/g, ""));
          if (Number.isFinite(numeric) && numeric > 0) {
            const est = numeric * 0.08;
            savings.textContent = `Estimated savings: £${est.toFixed(2)} with bundle-style session setup`;
          }
        });
        observer.observe(reviewTotal, { childList: true, subtree: true, characterData: true });
      } else {
        diag.log("ios_safe_mode_observer_disabled", { area: "session_builder_savings" });
      }
    }
  }

  const catchUploadWrap = document.querySelector("[data-catch-upload-form-wrap]");
  if (catchUploadWrap instanceof HTMLElement) {
    const fileInput = catchUploadWrap.querySelector("[data-catch-photo-input], input[type=\"file\"][name=\"catch_images[]\"]");
    const preview = catchUploadWrap.querySelector("[data-catch-photo-preview]");
    const submitBtn = catchUploadWrap.querySelector("[data-catch-report-submit]");
    const reportForm = catchUploadWrap.querySelector("[data-catch-report-form]");

    const renderCatchPhotoPreview = () => {
      if (!(preview instanceof HTMLElement) || !(fileInput instanceof HTMLInputElement)) {
        return;
      }
      preview.innerHTML = "";
      const files = fileInput.files;
      if (!files || !files.length) {
        preview.hidden = true;
        return;
      }
      preview.hidden = false;
      Array.from(files).forEach((file, index) => {
        if (!file.type.startsWith("image/")) {
          return;
        }
        const item = document.createElement("div");
        item.className = "mad-catch-submit__preview-item";
        const img = document.createElement("img");
        img.src = URL.createObjectURL(file);
        img.alt = file.name;
        const meta = document.createElement("p");
        meta.textContent = file.name;
        const remove = document.createElement("button");
        remove.type = "button";
        remove.className = "mad-catch-submit__preview-remove";
        remove.textContent = "Remove";
        remove.addEventListener("click", () => {
          const dt = new DataTransfer();
          Array.from(files).forEach((f, i) => {
            if (i !== index) {
              dt.items.add(f);
            }
          });
          fileInput.files = dt.files;
          renderCatchPhotoPreview();
        });
        item.append(img, meta, remove);
        preview.append(item);
      });
    };

    if (fileInput instanceof HTMLInputElement) {
      fileInput.addEventListener("change", renderCatchPhotoPreview);
      ["dragenter", "dragover"].forEach((eventName) => {
        catchUploadWrap.addEventListener(eventName, (event) => {
          event.preventDefault();
          catchUploadWrap.classList.add("is-dragging");
        });
      });
      ["dragleave", "drop"].forEach((eventName) => {
        catchUploadWrap.addEventListener(eventName, (event) => {
          event.preventDefault();
          catchUploadWrap.classList.remove("is-dragging");
        });
      });
      catchUploadWrap.addEventListener("drop", (event) => {
        const dropped = event?.dataTransfer?.files;
        if (dropped && dropped.length) {
          fileInput.files = dropped;
          renderCatchPhotoPreview();
        }
      });
    }

    if (reportForm instanceof HTMLFormElement && submitBtn instanceof HTMLButtonElement) {
      reportForm.addEventListener("submit", () => {
        submitBtn.disabled = true;
        submitBtn.textContent = "Submitting…";
      });
    }
  }

  const productGrids = document.querySelectorAll(".product-grid, .woocommerce ul.products");
  productGrids.forEach((grid) => {
    if (!(grid instanceof HTMLElement)) {
      return;
    }
    if (window.__madBaitsIosPwaSafeMode) {
      return;
    }
    grid.classList.add("is-loading-skeleton");
    window.setTimeout(() => {
      grid.classList.remove("is-loading-skeleton");
    }, 520);
  });

  if (typeof window.jQuery !== "undefined" && window.jQuery(document.body)) {
    window.jQuery(document.body).on("added_to_cart", (_event, fragments) => {
      showAppToast(config.i18nAddedToBasket || config.i18nAddedToBucket || config.i18nAdded || "Added to your basket");
      if (fragments && typeof fragments === "object") {
        const fragMarkup = fragments["[data-app-cart-count]"] || fragments["[data-app-top-cart-count]"];
        if (typeof fragMarkup === "string" && fragMarkup) {
          const wrapper = document.createElement("div");
          wrapper.innerHTML = fragMarkup;
          const badge =
            wrapper.querySelector("[data-app-cart-count]") || wrapper.querySelector("[data-app-top-cart-count]");
          if (badge instanceof HTMLElement) {
            syncAppCartBadges(Number.parseInt(badge.textContent || "0", 10) || 0, true);
          }
        } else {
          syncAppCartBadges((config.cartCount || 0) + 1, true);
        }
      }
    });
  }

  document.body.addEventListener("mad_baits:item_added_to_cart", () => {
    showAppToast(config.i18nAddedToBasket || config.i18nAddedToBucket || config.i18nAdded || "Added to your basket");
    if (body instanceof HTMLElement && body.classList.contains("mbbb-product")) {
      document.body.dispatchEvent(new CustomEvent("mad_baits:bundle_added"));
    }
  });

  if (window.MAD_BAITS_DEBUG && window.location.pathname.toLowerCase().includes("/boilies")) {
    window.setTimeout(() => {
      const productNodeCount = document.querySelectorAll(".product-grid .product, .woocommerce ul.products > li").length;
      const diagnostics = window.__madBaitsDiag || { counters: {}, loopState: {} };
      const likelySource =
        window.__madBaitsIosPwaSafeMode
          ? "iOS WebView memory pressure + duplicate listeners/observer churn on archive scripts"
          : "listener accumulation or archive render loop";
      console.group("[Mad Baits] iOS crash diagnostics: /boilies/");
      console.info("Likely crash source:", likelySource);
      console.info("mainInitCount:", window.__madBaitsMainInitCount || 0);
      console.info("appShellInitCount:", window.__madBaitsAppShellInitCount || 0);
      console.info("pwaInitCount:", window.__madBaitsPwaInitCount || 0);
      console.info("iosPwaSafeMode:", Boolean(window.__madBaitsIosPwaSafeMode));
      console.info("productNodeCount:", productNodeCount);
      console.info("counters:", diagnostics.counters || {});
      console.info("loopState:", diagnostics.loopState || {});
      console.groupEnd();
    }, 1800);
  }

  syncFavouritesFromServer();
  hydrateAppLists();
  updateJournalStats();
})();

