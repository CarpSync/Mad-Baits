(() => {
  "use strict";

  const config = window.madBaitsConfig || {};

  const copy = {
    ios: config.i18nPwaInstallIos || "On iPhone/iPad: tap Share, then Add to Home Screen.",
    androidChrome:
      config.i18nPwaInstallAndroidChrome ||
      "On Android: open madbaits.com in Google Chrome, tap the menu, then Install app or Add to Home screen.",
    samsungWarning:
      config.i18nPwaInstallSamsungWarning ||
      "Samsung Internet can block installs on newer Android phones. Open this page in Google Chrome to install safely.",
    inAppWarning:
      config.i18nPwaInstallInAppWarning ||
      "In-app browsers cannot install the app. Open madbaits.com in Chrome or Safari first.",
    generic:
      config.i18nPwaInstallGeneric ||
      "Open your browser menu and tap Install app or Add to Home screen.",
    homeHintAndroid:
      config.i18nPwaInstallHomeHintAndroid ||
      "Android: install with Google Chrome (menu, then Install app). iPhone: Share, then Add to Home Screen.",
    homeHintIos:
      config.i18nPwaInstallHomeHintIos ||
      "Free to install. On iPhone: Share, then Add to Home Screen.",
    openChrome: config.i18nPwaInstallOpenChrome || "Open in Chrome",
  };

  const detect = () => {
    const ua = window.navigator.userAgent || "";
    const isAndroid = /Android/i.test(ua);
    const isIOS =
      /iPad|iPhone|iPod/i.test(ua) ||
      (window.navigator.platform === "MacIntel" && window.navigator.maxTouchPoints > 1);
    const isSamsungBrowser = /SamsungBrowser/i.test(ua);
    const isInAppBrowser = /FB_IAB|FBAN|Instagram|Line\//i.test(ua);
    const isChromeAndroid =
      isAndroid &&
      /Chrome/i.test(ua) &&
      !/EdgA|OPR|SamsungBrowser|wv/i.test(ua) &&
      !isInAppBrowser;
    const isIosSafari =
      isIOS && /Safari/i.test(ua) && !/CriOS|FxiOS|EdgiOS|OPiOS/i.test(ua);
    const isStandalone =
      window.matchMedia("(display-mode: standalone)").matches ||
      window.navigator.standalone === true;
    const needsChromeForInstall = isAndroid && isInAppBrowser;
    const browserLabel = isInAppBrowser
      ? "In-app Browser"
      : isSamsungBrowser
        ? "Samsung Internet"
        : isChromeAndroid
          ? "Chrome"
          : isIosSafari
            ? "Safari"
            : isAndroid
              ? "Android Browser"
              : "Browser";

    return {
      isAndroid,
      isIOS,
      isSamsungBrowser,
      isInAppBrowser,
      isChromeAndroid,
      isIosSafari,
      isStandalone,
      needsChromeForInstall,
      browserLabel,
    };
  };

  const getChromeIntentUrl = () => {
    const href = window.location.href;
    try {
      const url = new URL(href);
      const hostPath = `${url.host}${url.pathname}${url.search}`;
      const fallback = encodeURIComponent(href);
      return `intent://${hostPath}#Intent;scheme=https;package=com.android.chrome;S.browser_fallback_url=${fallback};end`;
    } catch (error) {
      return href;
    }
  };

  const getMessages = () => {
    const ctx = detect();

    if (ctx.isStandalone) {
      return {
        hint: "",
        homeHint: "",
        warning: "",
        showWarning: false,
        needsChromeForInstall: false,
        openChromeUrl: "",
        openChromeLabel: copy.openChrome,
        installSteps: [],
      };
    }

    if (ctx.isIOS) {
      return {
        hint: copy.ios,
        homeHint: copy.homeHintIos,
        warning: ctx.isInAppBrowser ? copy.inAppWarning : "",
        showWarning: ctx.isInAppBrowser,
        needsChromeForInstall: false,
        openChromeUrl: "",
        openChromeLabel: copy.openChrome,
        installSteps: ["Tap Share", "Tap Add to Home Screen"],
        browserLabel: ctx.browserLabel,
      };
    }

    if (ctx.isAndroid) {
      const warning = ctx.isSamsungBrowser
        ? copy.samsungWarning
        : ctx.isInAppBrowser
          ? copy.inAppWarning
          : "";
      const androidInstallSteps = ctx.isChromeAndroid
        ? ["Tap the menu", "Tap Install app or Add to Home screen"]
        : ctx.isSamsungBrowser
          ? ["Tap the menu", "Tap Add page to Home screen"]
          : ["Tap the browser menu", "Tap Add to Home screen"];

      return {
        hint: ctx.isChromeAndroid ? copy.androidChrome : copy.generic,
        homeHint: copy.homeHintAndroid,
        warning,
        showWarning: Boolean(warning) && ctx.needsChromeForInstall,
        needsChromeForInstall: ctx.needsChromeForInstall,
        openChromeUrl: ctx.needsChromeForInstall ? getChromeIntentUrl() : "",
        openChromeLabel: copy.openChrome,
        installSteps: androidInstallSteps,
        browserLabel: ctx.browserLabel,
      };
    }

    return {
      hint: copy.generic,
      homeHint: copy.generic,
      warning: "",
      showWarning: false,
      needsChromeForInstall: false,
      openChromeUrl: "",
      openChromeLabel: copy.openChrome,
      installSteps: [],
      browserLabel: ctx.browserLabel,
    };
  };

  const applyHomePromoHint = () => {
    const hint = document.querySelector("[data-home-app-install-hint]");
    if (!(hint instanceof HTMLElement)) {
      return;
    }
    const messages = getMessages();
    if (messages.homeHint) {
      hint.textContent = messages.homeHint;
    }
  };

  const setWarningOnElement = (element, messages) => {
    if (!(element instanceof HTMLElement)) {
      return;
    }
    if (!messages.showWarning || !messages.warning) {
      element.hidden = true;
      element.textContent = "";
      return;
    }
    element.hidden = false;
    element.textContent = messages.warning;
  };

  window.madBaitsPwaInstall = {
    detect,
    getMessages,
    getChromeIntentUrl,
    applyHomePromoHint,
    setWarningOnElement,
  };
})();
