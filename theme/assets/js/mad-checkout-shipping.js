(() => {
  let applying = false;
  let lastAppliedAt = 0;

  const findFreeShippingInput = () => {
    const selectors = [
      '#shipping_method input[type="radio"][value*="free_shipping"]',
      '#shipping_method input[type="radio"][value*="mad_baits_bundle_free_shipping"]',
      '.woocommerce-shipping-methods input[type="radio"][value*="free_shipping"]',
      '.woocommerce-shipping-methods input[type="radio"][value*="mad_baits_bundle_free_shipping"]',
      '.wc-block-components-shipping-rates-control input[type="radio"][value*="free_shipping"]',
      '.wc-block-components-shipping-rates-control input[type="radio"][value*="mad_baits_bundle_free_shipping"]',
    ];

    for (const selector of selectors) {
      const input = document.querySelector(selector);
      if (input instanceof HTMLInputElement) {
        return input;
      }
    }

    const radios = document.querySelectorAll(
      '#shipping_method input[type="radio"], .woocommerce-shipping-methods input[type="radio"], .wc-block-components-shipping-rates-control input[type="radio"]'
    );
    for (const input of radios) {
      if (!(input instanceof HTMLInputElement)) {
        continue;
      }
      const label =
        input.closest("label") ||
        (input.id ? document.querySelector(`label[for="${CSS.escape(input.id)}"]`) : null);
      const text = (label?.textContent || "").toLowerCase();
      if (text.includes("free") && !text.includes("jersey")) {
        return input;
      }
    }

    return null;
  };

  const applyFreeShippingSelection = () => {
    const now = Date.now();
    if (applying || now - lastAppliedAt < 450) {
      return;
    }

    const input = findFreeShippingInput();
    if (!input || input.checked) {
      return;
    }

    applying = true;
    lastAppliedAt = now;

    input.checked = true;
    input.dispatchEvent(new Event("input", { bubbles: true }));
    input.dispatchEvent(new Event("change", { bubbles: true }));

    if (window.jQuery) {
      window.jQuery(input).trigger("change");
    }

    const label =
      input.closest("label") ||
      (input.id ? document.querySelector(`label[for="${CSS.escape(input.id)}"]`) : null);
    if (label instanceof HTMLElement) {
      label.click();
    }

    window.setTimeout(() => {
      applying = false;
    }, 900);
  };

  if (window.jQuery) {
    window.jQuery(document.body).on("updated_checkout", applyFreeShippingSelection);
    window.jQuery(document.body).on("updated_cart_totals", applyFreeShippingSelection);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", applyFreeShippingSelection);
  } else {
    applyFreeShippingSelection();
  }

  const host = document.querySelector(".wc-block-checkout") || document.querySelector("form.checkout");
  if (host instanceof HTMLElement && typeof MutationObserver !== "undefined") {
    let debounceTimer = 0;
    const observer = new MutationObserver(() => {
      if (applying) {
        return;
      }
      window.clearTimeout(debounceTimer);
      debounceTimer = window.setTimeout(applyFreeShippingSelection, 280);
    });
    observer.observe(host, { childList: true, subtree: true });
  }
})();
