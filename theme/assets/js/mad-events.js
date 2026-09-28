(() => {
  const config = window.madBaitsEvents || {};

  const rsvpForms = document.querySelectorAll("[data-event-rsvp-form]");
  rsvpForms.forEach((form) => {
    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    const statusEl = form.querySelector("[data-event-rsvp-status]");
    const eventId = form.getAttribute("data-event-id");

    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      if (!config.ajaxUrl || !config.rsvpAction) {
        return;
      }

      const formData = new FormData(form);
      formData.append("action", config.rsvpAction);
      formData.append("nonce", config.rsvpNonce || "");
      formData.append("event_id", eventId || "");

      if (statusEl instanceof HTMLElement) {
        statusEl.textContent = config.i18nSubmitting || "Sending...";
        statusEl.classList.remove("is-success", "is-error");
      }

      try {
        const response = await fetch(config.ajaxUrl, {
          method: "POST",
          credentials: "same-origin",
          body: formData,
        });
        const payload = await response.json();
        if (!response.ok || !payload?.success) {
          throw new Error(payload?.data?.message || config.i18nError || "Error");
        }
        if (statusEl instanceof HTMLElement) {
          statusEl.textContent = payload.data?.message || config.i18nSuccess || "Success";
          statusEl.classList.add("is-success");
        }
        form.reset();
      } catch (error) {
        if (statusEl instanceof HTMLElement) {
          statusEl.textContent = error instanceof Error ? error.message : config.i18nError || "Error";
          statusEl.classList.add("is-error");
        }
      }
    });
  });

  document.querySelectorAll("[data-event-share]").forEach((button) => {
    button.addEventListener("click", async () => {
      const url = window.location.href;
      const title = document.querySelector(".mad-event-single__title")?.textContent?.trim() || document.title;
      if (navigator.share) {
        try {
          await navigator.share({ title, url });
        } catch (_err) {
          // User cancelled or share failed silently.
        }
        return;
      }
      try {
        await navigator.clipboard.writeText(url);
        button.textContent = "Link copied";
      } catch (_err) {
        window.prompt("Copy event link:", url);
      }
    });
  });
})();
