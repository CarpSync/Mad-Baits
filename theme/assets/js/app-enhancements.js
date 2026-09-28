(() => {
  "use strict";

  const cfg = window.madBaitsEnhancements || {};
  const settings = cfg.settings || {};
  const shopUrl = typeof cfg.shopUrl === "string" && cfg.shopUrl ? cfg.shopUrl : "/shop/";
  const bundleDealsUrl = typeof cfg.bundleDealsUrl === "string" && cfg.bundleDealsUrl
    ? cfg.bundleDealsUrl
    : "/product-category/bundles-deals/";

  const isEnabled = (key) => Boolean(settings && settings[key]);
  const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  const isMobileViewport = () => window.matchMedia("(max-width: 768px)").matches;

  const vibrate = (pattern, featureKey = "enable_haptics") => {
    if (!isEnabled(featureKey)) {
      return;
    }
    if (!("vibrate" in navigator) || typeof navigator.vibrate !== "function") {
      return;
    }
    if (!isMobileViewport()) {
      return;
    }
    navigator.vibrate(pattern);
  };

  const bindTapAnimations = () => {
    document.addEventListener("click", (event) => {
      const btn = event.target instanceof Element
        ? event.target.closest(".mad-button, .mbbb-cta, .single_add_to_cart_button, [data-variation-submit]")
        : null;
      if (!(btn instanceof HTMLElement)) {
        return;
      }
      btn.classList.add("is-tapped");
      window.setTimeout(() => btn.classList.remove("is-tapped"), 180);
    }, true);
  };

  const bindMotionReveal = () => {
    if (!isEnabled("enable_motion_effects") || prefersReducedMotion || typeof IntersectionObserver === "undefined") {
      return;
    }
    const targets = document.querySelectorAll(".mad-product-card, .mbbb-slot, .mad-perfect-match__header, .mbbb-progress-dock");
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-revealed");
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });
    targets.forEach((node) => observer.observe(node));
  };

  const bindHapticFeedback = () => {
    document.addEventListener("click", (event) => {
      if (!(event.target instanceof Element)) {
        return;
      }
      if (event.target.closest(".mbbb-option, .mbbb-filter-chip, .mad-water-quiz__option")) {
        vibrate(12);
      }
    });
  };

  const bindCartPulse = () => {
    const pulseNodes = () => {
      document.querySelectorAll("[data-app-cart-count], [data-app-top-cart-count], .site-header__cart-count").forEach((el) => {
        if (el instanceof HTMLElement) {
          el.classList.add("is-pulse");
          window.setTimeout(() => el.classList.remove("is-pulse"), 700);
        }
      });
    };

    if (window.jQuery && window.jQuery(document.body).on) {
      window.jQuery(document.body).on("added_to_cart", () => {
        pulseNodes();
        vibrate([18, 30, 18]);
      });
    }
  };

  const bindBundleCelebration = () => {
    const body = document.body;
    if (!body || typeof MutationObserver === "undefined") {
      return;
    }

    let celebrated = false;
    const showCelebration = () => {
      if (celebrated || !body.classList.contains("mbbb-bundle-complete")) {
        return;
      }
      celebrated = true;

      const dock = document.getElementById("mbbb-progress-dock");
      if (dock) {
        dock.classList.add("mbbb-celebrate-once");
        const existing = dock.querySelector(".mbbb-celebrate-note");
        if (!existing) {
          const note = document.createElement("p");
          note.className = "mbbb-celebrate-note";
          note.textContent = "Session built — ready to add to basket";
          dock.appendChild(note);
        }
        if (isEnabled("enable_motion_effects") && !prefersReducedMotion) {
          dock.classList.add("mbbb-confetti-pop");
          window.setTimeout(() => dock.classList.remove("mbbb-confetti-pop"), 1600);
        }
      }

      const rail = document.getElementById("mad-bundle-match-rail");
      if (rail) {
        rail.hidden = false;
      }

      vibrate([25, 35, 25, 35, 35]);
    };

    const observer = new MutationObserver(showCelebration);
    observer.observe(body, { attributes: true, attributeFilter: ["class"] });
    showCelebration();
  };

  const bindPremiumLoadingCopy = () => {
    if (!isEnabled("enable_premium_loading")) {
      return;
    }
    document.querySelectorAll(".product-grid, .woocommerce ul.products").forEach((grid) => {
      if (!(grid instanceof HTMLElement)) {
        return;
      }
      const msg = document.createElement("div");
      msg.className = "mad-loading-copy";
      msg.textContent = "Loading bait...";
      grid.parentElement?.insertBefore(msg, grid);

      const obs = new MutationObserver(() => {
        const isLoading = grid.classList.contains("is-loading-skeleton");
        msg.classList.toggle("is-visible", isLoading);
      });
      obs.observe(grid, { attributes: true, attributeFilter: ["class"] });
    });
  };

  const isStandaloneApp = () =>
    window.matchMedia("(display-mode: standalone)").matches || window.navigator.standalone === true;

  const markPwaInstalled = () => {
    try {
      localStorage.setItem("madBaitsPwaInstalled", "1");
      localStorage.setItem("madBaitsPwaInstallDismissed", "1");
    } catch (error) {
      // Ignore storage failures.
    }
  };

  const getInstallPlatform = () =>
    window.madBaitsPwaInstall && typeof window.madBaitsPwaInstall.getMessages === "function"
      ? window.madBaitsPwaInstall.getMessages()
      : null;

  const isCartOrCheckoutPage = () =>
    document.body.classList.contains("woocommerce-cart")
    || document.body.classList.contains("woocommerce-checkout");

  const notifyInstallSheetClosed = () => {
    document.dispatchEvent(new CustomEvent("mad:install-sheet-closed"));
  };

  const bindInstallBottomSheet = () => {
    if (!isEnabled("enable_install_prompt")) {
      return;
    }
    if (!isMobileViewport()) {
      return;
    }
    if (isCartOrCheckoutPage()) {
      return;
    }
    if (isStandaloneApp()) {
      markPwaInstalled();
      return;
    }

    const installMessages = getInstallPlatform();
    const installCtx =
      window.madBaitsPwaInstall && typeof window.madBaitsPwaInstall.detect === "function"
        ? window.madBaitsPwaInstall.detect()
        : null;
    const preferNativeAndroidPrompt = Boolean(
      installCtx?.isAndroid && installCtx?.isChromeAndroid && !installCtx?.isInAppBrowser && !installCtx?.isStandalone
    );
    if (preferNativeAndroidPrompt) {
      return;
    }

    const installedKey = "madBaitsPwaInstalled";
    try {
      if (localStorage.getItem(installedKey) === "1") {
        return;
      }
    } catch (error) {
      // Ignore storage failures.
    }

    const dismissKey = "madBaitsInstallSheetDismissUntil";
    const pageCountKey = "madBaitsPageViews";
    const dismissedUntil = Number(localStorage.getItem(dismissKey) || "0");
    if (dismissedUntil > Date.now()) {
      return;
    }

    let pageViews = Number(sessionStorage.getItem(pageCountKey) || "0");
    pageViews += 1;
    sessionStorage.setItem(pageCountKey, String(pageViews));

    window.addEventListener("appinstalled", () => {
      markPwaInstalled();
    });

    const ensureSheet = () => {
      let sheet = document.querySelector("[data-mad-install-sheet]");
      if (sheet instanceof HTMLElement) {
        return sheet;
      }

      sheet = document.createElement("div");
      sheet.className = "mad-install-sheet";
      sheet.setAttribute("data-mad-install-sheet", "true");
      sheet.innerHTML = `
        <div class="mad-install-sheet__backdrop" data-install-close></div>
        <div class="mad-install-sheet__panel">
          <span class="mad-install-sheet__logo">MAD</span>
          <h3>Install the Mad Baits App</h3>
          <ul>
            <li>Faster shopping</li>
            <li>Exclusive app deals</li>
            <li>Quick re-order</li>
            <li>Session builder</li>
          </ul>
          <p class="mad-install-sheet__warning" data-install-warning hidden></p>
          <ol class="mad-install-sheet__steps" data-install-steps hidden></ol>
          <p class="mad-install-sheet__hint" data-install-hint hidden></p>
          <div class="mad-install-sheet__actions">
            <button type="button" class="mad-button" data-install-accept>Install App</button>
            <button type="button" class="mad-button mad-button--ghost" data-install-close>Maybe later</button>
          </div>
        </div>
      `;
      document.body.appendChild(sheet);

      const warning = sheet.querySelector("[data-install-warning]");
      const steps = sheet.querySelector("[data-install-steps]");
      if (installMessages) {
        if (
          window.madBaitsPwaInstall &&
          typeof window.madBaitsPwaInstall.setWarningOnElement === "function"
        ) {
          window.madBaitsPwaInstall.setWarningOnElement(warning, installMessages);
        }
        if (steps instanceof HTMLElement && Array.isArray(installMessages.installSteps)) {
          if (installMessages.installSteps.length) {
            steps.hidden = false;
            steps.innerHTML = installMessages.installSteps
              .map((step) => `<li>${step}</li>`)
              .join("");
          } else {
            steps.hidden = true;
          }
        }
      }

      const close = () => {
        sheet.classList.remove("is-open");
        sheet.setAttribute("hidden", "hidden");
        notifyInstallSheetClosed();
      };
      sheet.querySelectorAll("[data-install-close]").forEach((node) => {
        node.addEventListener("click", () => {
          localStorage.setItem(dismissKey, String(Date.now() + 7 * 24 * 60 * 60 * 1000));
          close();
        });
      });

      const acceptBtn = sheet.querySelector("[data-install-accept]");
      const hint = sheet.querySelector("[data-install-hint]");
      if (acceptBtn instanceof HTMLButtonElement && installMessages?.needsChromeForInstall) {
        acceptBtn.textContent = installMessages.openChromeLabel || "Open in Chrome";
      }
      if (acceptBtn instanceof HTMLButtonElement) {
        acceptBtn.addEventListener("click", async () => {
          if (installMessages?.needsChromeForInstall && installMessages.openChromeUrl) {
            window.location.assign(installMessages.openChromeUrl);
            return;
          }
          if (
            window.madBaitsInstallPrompt &&
            typeof window.madBaitsInstallPrompt.isAvailable === "function" &&
            window.madBaitsInstallPrompt.isAvailable()
          ) {
            await window.madBaitsInstallPrompt.invoke("install-bottom-sheet");
            close();
            return;
          }
          if (hint instanceof HTMLElement) {
            hint.hidden = false;
            hint.textContent = installMessages?.hint || "On iPhone: tap Share, then Add to Home Screen.";
          }
        });
      }

      return sheet;
    };

    const show = () => {
      if (isCartOrCheckoutPage()) {
        return;
      }
      const sheet = ensureSheet();
      if (!(sheet instanceof HTMLElement)) {
        return;
      }
      sheet.removeAttribute("hidden");
      sheet.classList.add("is-open");
      document.dispatchEvent(new CustomEvent("mad:install-sheet-open"));
    };

    if (pageViews >= 3) {
      show();
      return;
    }

    window.setTimeout(show, 30000);
  };

  const waterQuizQuestions = [
    {
      key: "waterType",
      title: "Water type?",
      options: ["Lake", "River", "Canal", "Commercial", "French venue"],
    },
    {
      key: "condition",
      title: "Water condition?",
      options: ["Clear", "Coloured", "Weedy", "Silty", "Pressured"],
    },
    {
      key: "sessionLength",
      title: "Session length?",
      options: ["Day session", "Overnight", "Weekend", "Week trip"],
    },
    {
      key: "season",
      title: "Season?",
      options: ["Spring", "Summer", "Autumn", "Winter"],
    },
    {
      key: "style",
      title: "Style?",
      options: ["Instant bite", "Big hit feeding", "Match the hatch", "High attraction"],
    },
  ];

  const bindWaterQuiz = () => {
    if (!isEnabled("enable_recommender")) {
      return;
    }
    if (!isMobileViewport()) {
      return;
    }

    const shell = document.querySelector("[data-water-quiz]");
    const body = document.querySelector("[data-water-quiz-body]");
    const progress = document.querySelector("[data-water-quiz-progress]");
    if (!(shell instanceof HTMLElement) || !(body instanceof HTMLElement)) {
      return;
    }

    let step = 0;
    const answers = {};

    const close = () => {
      shell.hidden = true;
      shell.setAttribute("hidden", "");
      shell.setAttribute("aria-hidden", "true");
      shell.classList.remove("is-open");
    };
    const open = (event) => {
      if (event instanceof Event) {
        event.preventDefault();
      }
      shell.hidden = false;
      shell.removeAttribute("hidden");
      shell.setAttribute("aria-hidden", "false");
      shell.classList.add("is-open");
      step = 0;
      Object.keys(answers).forEach((k) => delete answers[k]);
      renderStep();
    };

    const renderStep = () => {
      const q = waterQuizQuestions[step];
      if (!q) {
        renderResults();
        return;
      }
      const percent = Math.round((step / waterQuizQuestions.length) * 100);
      if (progress instanceof HTMLElement) {
        progress.style.width = `${percent}%`;
      }
      body.innerHTML = `
        <h3 class="mad-water-quiz__question">${q.title}</h3>
        <div class="mad-water-quiz__options">
          ${q.options.map((opt) => `<button type="button" class="mad-water-quiz__option" data-quiz-option="${opt}">${opt}</button>`).join("")}
        </div>
      `;

      body.querySelectorAll("[data-quiz-option]").forEach((btn) => {
        btn.addEventListener("click", () => {
          answers[q.key] = btn.getAttribute("data-quiz-option") || "";
          step += 1;
          vibrate(10);
          renderStep();
        });
      });
    };

    const renderResults = () => {
      if (progress instanceof HTMLElement) {
        progress.style.width = "100%";
      }
      body.innerHTML = `<p class="mad-water-quiz__loading">Building your session...</p>`;
      const form = new FormData();
      form.append("action", "mad_baits_water_recommender");
      form.append("nonce", cfg.nonce || "");
      Object.keys(answers).forEach((key) => form.append(`answers[${key}]`, answers[key]));

      fetch(cfg.ajaxUrl || "", {
        method: "POST",
        credentials: "same-origin",
        body: form,
      })
        .then((res) => res.json())
        .then((json) => {
          const products = json && json.success && json.data && Array.isArray(json.data.products)
            ? json.data.products
            : [];
          body.innerHTML = `
            <h3 class="mad-water-quiz__question">Recommended setup</h3>
            <div class="mad-water-quiz__results">
              ${products.slice(0, 5).map((p) => `
                <a class="mad-water-quiz__result-card" href="${p.url}">
                  <span class="mad-water-quiz__result-media">${p.image ? `<img src="${p.image}" alt="">` : ""}</span>
                  <span class="mad-water-quiz__result-copy">
                    <strong>${p.name}</strong>
                    <em>${p.price || ""}</em>
                  </span>
                </a>
              `).join("")}
            </div>
            <div class="mad-water-quiz__footer-actions">
              <a class="mad-button" href="${bundleDealsUrl}">Add recommended bundle</a>
              <a class="mad-button mad-button--ghost" href="${shopUrl}">Shop recommendations</a>
            </div>
          `;
          vibrate([18, 24, 18]);
        })
        .catch(() => {
          body.innerHTML = `<p class="mad-water-quiz__loading">Could not load recommendations. Please try again.</p>`;
        });
    };

    document.addEventListener(
      "click",
      (event) => {
        const trigger = event.target instanceof Element ? event.target.closest("[data-water-quiz-open]") : null;
        if (!(trigger instanceof HTMLElement)) {
          return;
        }
        open(event);
      },
      true,
    );

    shell.querySelectorAll("[data-water-quiz-close]").forEach((node) => node.addEventListener("click", close));

    document.addEventListener("keydown", (event) => {
      if ("Escape" === event.key && shell.classList.contains("is-open")) {
        close();
      }
    });
  };

  bindTapAnimations();
  bindMotionReveal();
  bindHapticFeedback();
  bindCartPulse();
  bindBundleCelebration();
  bindPremiumLoadingCopy();
  const closeOverlayPanelsForMenu = () => {
    document.querySelectorAll("[data-mad-install-sheet].is-open").forEach((node) => {
      if (node instanceof HTMLElement) {
        node.classList.remove("is-open");
        node.setAttribute("hidden", "hidden");
      }
    });
    const waterQuiz = document.querySelector("[data-water-quiz]");
    if (waterQuiz instanceof HTMLElement && waterQuiz.classList.contains("is-open")) {
      waterQuiz.hidden = true;
      waterQuiz.setAttribute("hidden", "");
      waterQuiz.setAttribute("aria-hidden", "true");
      waterQuiz.classList.remove("is-open");
    }
    document.querySelectorAll(".mbbb-summary-drawer.is-open").forEach((drawer) => {
      if (!(drawer instanceof HTMLElement)) {
        return;
      }
      drawer.hidden = true;
      drawer.setAttribute("hidden", "");
      drawer.setAttribute("aria-hidden", "true");
      drawer.classList.remove("is-open");
    });
    document.body.classList.remove("mbbb-drawer-open");
    const summaryToggle = document.getElementById("mbbb-sticky-summary");
    if (summaryToggle instanceof HTMLButtonElement) {
      summaryToggle.setAttribute("aria-expanded", "false");
    }
    if (window.madBaitsAppUpdate && typeof window.madBaitsAppUpdate.hideUpdateModal === "function") {
      window.madBaitsAppUpdate.hideUpdateModal();
    }
  };

  document.addEventListener("mad:mobile-menu-open", closeOverlayPanelsForMenu);

  bindInstallBottomSheet();
  bindWaterQuiz();
})();
