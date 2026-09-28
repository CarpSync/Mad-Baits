/**
 * Capacitor Camera helpers for catch reports (native iOS/Android only).
 * Browser/PWA keeps standard file input — no errors if Camera unavailable.
 */
(() => {
  const cap = window.Capacitor;
  if (!cap || typeof cap.isNativePlatform !== "function" || !cap.isNativePlatform()) {
    return;
  }

  const Camera = cap.Plugins && cap.Plugins.Camera;
  if (!Camera || typeof Camera.getPhoto !== "function") {
    return;
  }

  const dataUrlToFile = (dataUrl, filename) => {
    const parts = String(dataUrl).split(",");
    if (parts.length < 2) {
      throw new Error("Invalid image data");
    }
    const mimeMatch = parts[0].match(/:(.*?);/);
    const mime = mimeMatch ? mimeMatch[1] : "image/jpeg";
    const binary = atob(parts[1]);
    const len = binary.length;
    const bytes = new Uint8Array(len);
    for (let i = 0; i < len; i += 1) {
      bytes[i] = binary.charCodeAt(i);
    }
    return new File([bytes], filename, { type: mime, lastModified: Date.now() });
  };

  const appendFileToInput = (fileInput, file) => {
    if (!(fileInput instanceof HTMLInputElement)) {
      return false;
    }
    const dt = new DataTransfer();
    const existing = fileInput.files ? Array.from(fileInput.files) : [];
    const max = 3;
    existing.slice(0, max - 1).forEach((f) => dt.items.add(f));
    dt.items.add(file);
    fileInput.files = dt.files;
    fileInput.dispatchEvent(new Event("change", { bubbles: true }));
    return true;
  };

  /**
   * @param {'camera'|'photos'} source
   * @param {HTMLInputElement} fileInput
   */
  const pickCatchPhoto = async (source, fileInput) => {
    if (!(fileInput instanceof HTMLInputElement)) {
      return { ok: false, reason: "no-input" };
    }
    try {
      const photo = await Camera.getPhoto({
        quality: 88,
        allowEditing: false,
        resultType: "dataUrl",
        source: source === "camera" ? "CAMERA" : "PHOTOS",
        correctOrientation: true,
        saveToGallery: false,
      });
      const dataUrl = photo && (photo.dataUrl || photo.base64String);
      if (!dataUrl) {
        return { ok: false, reason: "cancelled" };
      }
      const normalized = String(dataUrl).startsWith("data:") ? dataUrl : `data:image/jpeg;base64,${dataUrl}`;
      const ext = normalized.includes("image/png") ? "png" : "jpg";
      const file = dataUrlToFile(normalized, `catch-${Date.now()}.${ext}`);
      appendFileToInput(fileInput, file);
      return { ok: true, file };
    } catch (err) {
      const message = err && err.message ? String(err.message) : "camera-error";
      if (/cancel|user/i.test(message)) {
        return { ok: false, reason: "cancelled" };
      }
      return { ok: false, reason: message };
    }
  };

  window.MadBaitsNative = window.MadBaitsNative || {};
  window.MadBaitsNative.pickCatchPhoto = pickCatchPhoto;
  window.MadBaitsNative.hasNativeCamera = true;
})();
