/**
 * App-shell placeholders: catch card (coming soon), product quick view (coming soon).
 * Progressive enhancement — product links unchanged; quick view is opt-in via long-press.
 */
(() => {
  const body = document.body;
  if (!(body instanceof HTMLElement)) {
    return;
  }

  const isAppShell =
    body.classList.contains("is-mobile-app-shell") ||
    body.classList.contains("is-capacitor-app") ||
    body.classList.contains("is-pwa") ||
    body.classList.contains("is-standalone-app");

  if (!isAppShell) {
    return;
  }

  const config = window.madBaitsMobileUxConfig || window.madBaitsConfig || {};
  const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  const injectCatchPhotoActions = () => {
    const wrap = document.querySelector("[data-catch-upload-form-wrap]");
    if (!(wrap instanceof HTMLElement) || wrap.querySelector("[data-catch-take-photo]")) {
      return;
    }
    const fileInput = wrap.querySelector("[data-catch-photo-input], input[type=\"file\"][name=\"catch_images[]\"]");
    if (!(fileInput instanceof HTMLInputElement)) {
      return;
    }
    fileInput.classList.add("mad-catch-submit__file-input--hidden");
    const actionsEl = document.createElement("div");
    actionsEl.className = "mad-catch-submit__photo-actions";
    actionsEl.innerHTML = `
      <button type="button" class="mad-button mad-catch-submit__photo-btn" data-catch-take-photo>${config.i18nCatchTakePhoto || "Take Photo"}</button>
      <button type="button" class="mad-button mad-button--ghost mad-catch-submit__photo-btn" data-catch-choose-library>${config.i18nCatchChooseLibrary || "Choose from Library"}</button>
    `;
    fileInput.insertAdjacentElement("afterend", actionsEl);
    if (!wrap.querySelector("[data-catch-upload-progress]")) {
      const progress = document.createElement("p");
      progress.className = "mad-catch-submit__upload-progress";
      progress.dataset.catchUploadProgress = "true";
      progress.hidden = true;
      progress.textContent = config.i18nCatchUploading || "Uploading photo…";
      actionsEl.insertAdjacentElement("afterend", progress);
    }
  };

  const injectCatchCardPlaceholder = () => {
    const success = document.querySelector(
      ".mad-catch-submit__notice--success, .mad-catch-submit__success, [data-catch-report-success]"
    );
    if (!(success instanceof HTMLElement) || document.querySelector("[data-catch-card-soon]")) {
      return;
    }
    success.setAttribute("data-catch-report-success", "true");
    const card = document.createElement("aside");
    card.className = "mad-catch-submit__share-soon";
    card.dataset.catchCardSoon = "true";
    card.innerHTML = `
      <strong>${config.i18nCatchCardSoonTitle || "Catch card — coming soon"}</strong>
      <p>${config.i18nCatchCardSoonCopy || "Share a branded Mad Baits catch card with photo, weight, venue and bait. For now your catch is submitted for review."}</p>
    `;
    success.insertAdjacentElement("afterend", card);
    document.body.dispatchEvent(new CustomEvent("mad_baits:catch_report_submitted"));
  };

  let quickViewShell = null;

  const ensureQuickViewShell = () => {
    if (quickViewShell instanceof HTMLElement) {
      return quickViewShell;
    }
    quickViewShell = document.createElement("div");
    quickViewShell.className = "mad-product-quick-view-shell";
    quickViewShell.dataset.productQuickViewShell = "true";
    quickViewShell.hidden = true;
    quickViewShell.setAttribute("aria-hidden", "true");
    quickViewShell.innerHTML = `
      <div class="mad-product-quick-view__backdrop" data-product-quick-view-close tabindex="-1"></div>
      <aside class="mad-product-quick-view" role="dialog" aria-modal="true" aria-labelledby="mad-product-quick-view-title">
        <div class="mad-product-quick-view__handle" aria-hidden="true"></div>
        <header class="mad-product-quick-view__header">
          <h2 id="mad-product-quick-view-title">${config.i18nQuickViewSoonTitle || "Quick view"}</h2>
          <button type="button" class="mad-product-quick-view__close" data-product-quick-view-close aria-label="Close">&times;</button>
        </header>
        <div class="mad-product-quick-view__body" data-product-quick-view-body>
          <p class="mad-product-quick-view__soon">${config.i18nQuickViewSoonCopy || "Product quick view is coming soon. Tap through to the full product page for now."}</p>
        </div>
        <footer class="mad-product-quick-view__footer">
          <a class="mad-button mad-button--ghost" data-product-quick-view-link href="#">${config.i18nQuickViewFullProduct || "View full product"}</a>
          <button type="button" class="mad-button" data-product-quick-view-close>${config.i18nQuickViewClose || "Close"}</button>
        </footer>
      </aside>
    `;
    document.body.appendChild(quickViewShell);
    quickViewShell.querySelectorAll("[data-product-quick-view-close]").forEach((node) => {
      node.addEventListener("click", () => closeQuickView());
    });
    return quickViewShell;
  };

  const closeQuickView = () => {
    if (!(quickViewShell instanceof HTMLElement)) {
      return;
    }
    quickViewShell.classList.remove("is-open");
    quickViewShell.setAttribute("aria-hidden", "true");
    window.setTimeout(() => {
      quickViewShell.hidden = true;
    }, reducedMotion ? 0 : 220);
    body.classList.remove("mad-product-quick-view-open");
  };

  const openQuickViewSoon = (card) => {
    const shell = ensureQuickViewShell();
    const link = shell.querySelector("[data-product-quick-view-link]");
    const title = shell.querySelector("#mad-product-quick-view-title");
    const productLink = card instanceof HTMLElement ? card.querySelector("a[href*='/product/']") : null;
    if (link instanceof HTMLAnchorElement && productLink instanceof HTMLAnchorElement) {
      link.href = productLink.href;
    }
    if (title instanceof HTMLElement && productLink instanceof HTMLAnchorElement) {
      const name = card.querySelector(".mad-product-card__title")?.textContent?.trim();
      title.textContent = name || config.i18nQuickViewSoonTitle || "Quick view";
    }
    shell.hidden = false;
    shell.setAttribute("aria-hidden", "false");
    body.classList.add("mad-product-quick-view-open");
    window.requestAnimationFrame(() => shell.classList.add("is-open"));
  };

  const bindProductQuickViewPlaceholder = () => {
    let pressTimer = null;
    document.querySelectorAll(".mad-product-card").forEach((card) => {
      if (!(card instanceof HTMLElement) || card.dataset.quickViewBound === "true") {
        return;
      }
      card.dataset.quickViewBound = "true";
      card.addEventListener(
        "touchstart",
        () => {
          pressTimer = window.setTimeout(() => {
            if (window.MadBaitsNative && typeof window.MadBaitsNative.triggerHaptic === "function") {
              void window.MadBaitsNative.triggerHaptic("selection");
            }
            openQuickViewSoon(card);
          }, 520);
        },
        { passive: true }
      );
      ["touchend", "touchmove", "touchcancel"].forEach((ev) => {
        card.addEventListener(
          ev,
          () => {
            if (pressTimer) {
              window.clearTimeout(pressTimer);
              pressTimer = null;
            }
          },
          { passive: true }
        );
      });
    });
  };

  injectCatchPhotoActions();
  injectCatchCardPlaceholder();
  ensureQuickViewShell();
  bindProductQuickViewPlaceholder();

  const observer = new MutationObserver(() => {
    injectCatchPhotoActions();
    injectCatchCardPlaceholder();
    bindProductQuickViewPlaceholder();
  });
  observer.observe(body, { childList: true, subtree: true });
})();
