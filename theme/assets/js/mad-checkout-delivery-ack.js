(() => {
  "use strict";

  const config = window.madBaitsDeliveryAck || {};
  const fieldId = String(config.fieldId || "mad-baits/delivery-safe-place-ack");
  const classicFieldId = String(config.classicFieldId || "mad_delivery_safe_place_ack");
  const legalHtml = String(config.legalHtml || "");
  const errorMessage = String(
    config.errorMessage ||
      "Please confirm delivery responsibility for neighbour or safe-place deliveries before paying."
  );
  const expressNotice = String(
    config.expressNotice ||
      "By using Apple Pay, Google Pay, Link or PayPal Express you accept delivery responsibility for neighbour or safe-place deliveries."
  );

  /**
   * Standard PayPal only (payment methods accordion). Never target express wallets.
   */
  const PAYPAL_SELECTORS = [
    "#payment .payment_method_ppcp-gateway",
    "#payment #ppc-button-ppcp-gateway",
    ".wc-block-checkout__payment-method .ppcp-button-wrapper",
    ".wc-block-checkout__payment-method #ppc-button-ppcp-gateway",
    ".wc-block-components-radio-control-accordion-content .ppcp-button-wrapper",
    ".wc-block-components-radio-control-accordion-content #ppc-button-ppcp-gateway",
  ].join(",");

  const EXPRESS_ROOT_SELECTOR = [
    ".wc-block-components-express-payment",
    ".wp-block-woocommerce-checkout-express-payment-block",
    ".wp-block-woocommerce-cart-express-payment-block",
  ].join(",");

  const findBlocksCheckbox = () => {
    const selectors = [
      `input[name="${fieldId}"]`,
      `input[name="contact_${fieldId}"]`,
      `input[name="order_${fieldId}"]`,
      "#contact-mad-baits-delivery-safe-place-ack",
      "#order-mad-baits-delivery-safe-place-ack",
      'input[id*="delivery-safe-place-ack"]',
      'input[name*="delivery-safe-place-ack"]',
      ".wc-block-components-address-form__mad-baits-delivery-safe-place-ack input[type=\"checkbox\"]",
    ];

    for (const selector of selectors) {
      const candidate = document.querySelector(selector);
      if (candidate instanceof HTMLInputElement && candidate.type === "checkbox") {
        return candidate;
      }
    }

    return null;
  };

  const findClassicCheckbox = () =>
    document.getElementById(classicFieldId) instanceof HTMLInputElement
      ? document.getElementById(classicFieldId)
      : null;

  const getAckCheckbox = () => findBlocksCheckbox() || findClassicCheckbox();

  const isAckChecked = () => {
    const checkbox = getAckCheckbox();
    return checkbox instanceof HTMLInputElement && checkbox.checked;
  };

  const shouldAssistPayPal = () => getAckCheckbox() instanceof HTMLInputElement;

  const isInsideExpressCheckout = (node) =>
    node instanceof Element && Boolean(node.closest(EXPRESS_ROOT_SELECTOR));

  const showInlineNotice = (visible) => {
    let notice = document.getElementById("mad-checkout-delivery-ack-notice");
    if (!visible) {
      if (notice) {
        notice.hidden = true;
      }
      return;
    }

    if (!notice) {
      notice = document.createElement("p");
      notice.id = "mad-checkout-delivery-ack-notice";
      notice.className = "mad-checkout-delivery-ack-notice wc-block-components-notice-banner is-error";
      notice.setAttribute("role", "alert");
    }

    notice.textContent = errorMessage;

    const checkbox = getAckCheckbox();
    const host =
      document.querySelector(".mad-checkout-delivery-ack--blocks") ||
      (checkbox && checkbox.closest(".wc-block-components-checkbox, .mad-checkout-delivery-ack")) ||
      document.querySelector(".wc-block-checkout__additional-fields, .wc-block-checkout__contact-fields") ||
      document.querySelector("form.checkout");

    if (host instanceof HTMLElement && notice.parentElement !== host) {
      host.prepend(notice);
    }

    notice.hidden = false;
  };

  const injectExpressNotice = () => {
    const expressRoot = document.querySelector(EXPRESS_ROOT_SELECTOR);
    if (!(expressRoot instanceof HTMLElement)) {
      return;
    }
    if (expressRoot.querySelector(".mad-checkout-delivery-ack-express-note")) {
      return;
    }
    const note = document.createElement("p");
    note.className = "mad-checkout-delivery-ack-express-note";
    note.textContent = expressNotice;
    expressRoot.appendChild(note);
  };

  const buildLegalBox = (extraClass) => {
    const box = document.createElement("div");
    box.className = `mad-checkout-delivery-ack mad-checkout-delivery-ack--blocks ${extraClass}`.trim();
    box.innerHTML =
      '<p class="mad-checkout-delivery-ack__title">Delivery responsibility</p>' + legalHtml;
    return box;
  };

  const injectLegalIntro = () => {
    if (!legalHtml) {
      return;
    }

    const checkbox = getAckCheckbox();
    if (!(checkbox instanceof HTMLInputElement)) {
      return;
    }

    const row = checkbox.closest(".wc-block-components-checkbox, .mad-checkout-delivery-ack");
    if (!(row instanceof HTMLElement) || !(row.parentElement instanceof HTMLElement)) {
      return;
    }

    if (row.parentElement.querySelector(".mad-checkout-delivery-ack--contact")) {
      return;
    }

    if (row.classList.contains("mad-checkout-delivery-ack")) {
      return;
    }

    row.parentElement.insertBefore(buildLegalBox("mad-checkout-delivery-ack--contact"), row);
  };

  const injectActionsLegal = () => {
    if (!legalHtml || document.querySelector(".mad-checkout-delivery-ack--actions")) {
      return;
    }

    const actions =
      document.querySelector(".wc-block-checkout__actions") ||
      document.querySelector(".wp-block-woocommerce-checkout-actions-block");

    if (!(actions instanceof HTMLElement) || !(actions.parentElement instanceof HTMLElement)) {
      return;
    }

    actions.parentElement.insertBefore(buildLegalBox("mad-checkout-delivery-ack--actions"), actions);
  };

  /**
   * Soft guide only — never set pointer-events/opacity and never stopImmediatePropagation.
   * Server-side Store API validation still enforces the checkbox for standard place-order.
   */
  const onStandardPayPalClick = (event) => {
    if (!shouldAssistPayPal() || isAckChecked()) {
      return;
    }

    const target = event.target;
    if (!(target instanceof Element) || isInsideExpressCheckout(target)) {
      return;
    }

    if (!target.closest(PAYPAL_SELECTORS)) {
      return;
    }

    showInlineNotice(true);
    const checkbox = getAckCheckbox();
    if (checkbox instanceof HTMLInputElement) {
      checkbox.focus({ preventScroll: true });
      checkbox.closest(".wc-block-components-checkbox, .mad-checkout-delivery-ack")?.scrollIntoView({
        behavior: "smooth",
        block: "center",
      });
    }
  };

  const bindCheckbox = () => {
    const checkbox = getAckCheckbox();
    if (!(checkbox instanceof HTMLInputElement)) {
      return false;
    }

    if (checkbox.dataset.madDeliveryAckBound !== "1") {
      checkbox.dataset.madDeliveryAckBound = "1";
      checkbox.addEventListener("change", () => {
        if (checkbox.checked) {
          showInlineNotice(false);
        }
      });
    }

    injectLegalIntro();
    injectActionsLegal();
    return true;
  };

  const init = () => {
    bindCheckbox();
    injectExpressNotice();
    injectActionsLegal();
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }

  // Bubble phase only — never capture/stop wallet or cart clicks.
  document.addEventListener("click", onStandardPayPalClick, false);

  const observeRoot =
    document.querySelector(".wc-block-checkout") ||
    document.querySelector("form.checkout") ||
    document.body;

  if (observeRoot instanceof HTMLElement && typeof MutationObserver !== "undefined") {
    let timer = 0;
    const observer = new MutationObserver(() => {
      window.clearTimeout(timer);
      timer = window.setTimeout(() => {
        bindCheckbox();
        injectExpressNotice();
        injectActionsLegal();
      }, 160);
    });
    observer.observe(observeRoot, { childList: true, subtree: true });
  }

  window.setTimeout(init, 400);
  window.setTimeout(init, 1200);
})();
