(function () {
  "use strict";

  const config = window.madBaitsSocial || {};

  function trackMeta(eventName, params) {
    if (typeof window.fbq !== "function") {
      return;
    }
    window.fbq("track", eventName, params || {});
  }

  function metaSessionKey(payload) {
    if (!payload || !payload.event) {
      return "";
    }
    const ids = Array.isArray(payload.content_ids) ? payload.content_ids.join("-") : "";
    return "mad_meta_" + payload.event + "_" + (payload.order_id || ids || "page");
  }

  function fireMetaOnce(payload) {
    if (!payload || !payload.event) {
      return;
    }
    const key = metaSessionKey(payload);
    try {
      if (key && window.sessionStorage.getItem(key)) {
        return;
      }
      trackMeta(payload.event, payload);
      if (key) {
        window.sessionStorage.setItem(key, "1");
      }
    } catch (error) {
      trackMeta(payload.event, payload);
    }
  }

  function fireQueuedMetaEvents() {
    const events = window.madBaitsMetaEvents;
    if (!Array.isArray(events)) {
      return;
    }
    events.forEach(fireMetaOnce);
  }

  if (window.madBaitsMetaAddToCart) {
    fireMetaOnce(window.madBaitsMetaAddToCart);
  }

  fireQueuedMetaEvents();

  if (typeof jQuery !== "undefined") {
    jQuery(document.body).on("added_to_cart", function (event, fragments, cartHash, $button) {
      const productId = $button && $button.data ? $button.data("product_id") : null;
      if (!productId) {
        return;
      }
      fireMetaOnce({
        event: "AddToCart",
        content_ids: [String(productId)],
        content_type: "product",
      });
    });
  }

  function notifyShare(message) {
    if (!message) {
      return;
    }
    const toast = document.querySelector(".mad-app-toast");
    if (toast) {
      toast.textContent = message;
      toast.hidden = false;
      toast.classList.add("is-visible");
      window.setTimeout(function () {
        toast.classList.remove("is-visible");
        toast.hidden = true;
      }, 2800);
      return;
    }
    window.alert(message);
  }

  function copyShareUrl(url) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(url).then(function () {
        notifyShare(config.i18nShareCopied || "Link copied.");
      });
    }
    return Promise.reject(new Error("clipboard_unavailable"));
  }

  document.addEventListener("click", function (event) {
    const button = event.target.closest("[data-mad-share]");
    if (!button) {
      return;
    }
    event.preventDefault();

    const title = button.getAttribute("data-share-title") || document.title;
    const url = button.getAttribute("data-share-url") || window.location.href;
    const text = button.getAttribute("data-share-text") || title;
    const shareUrl = url || window.location.href;

    if (navigator.share) {
      navigator.share({ title: title, text: text, url: shareUrl }).catch(function () {
        /* user cancelled */
      });
      return;
    }

    copyShareUrl(shareUrl).catch(function () {
      window.prompt(config.i18nShareFailed || "Copy this link:", shareUrl);
    });
  });
})();
