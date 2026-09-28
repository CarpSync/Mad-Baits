# Mad Baits App Installer

This WordPress plugin makes `madbaits.com` installable as a mobile web app by adding:

- A web app manifest at `/mad-baits.webmanifest`
- A root-scoped service worker at `/mad-baits-sw.js`
- Mobile app meta tags for Android and iOS
- Install prompt wiring for buttons or links labelled `Get The Mad Baits App`
- Safe caching that avoids cart, checkout, account, and admin pages

## Install

1. In WordPress admin, go to **Plugins > Add New > Upload Plugin**.
2. Upload `mad-baits-pwa.zip`.
3. Activate **Mad Baits App Installer**.
4. Open the site on Android Chrome or Microsoft Edge, then tap **Get The Mad Baits App**.
5. On iPhone/iPad, use Safari, tap **Share**, then **Add to Home Screen**.

If `/mad-baits.webmanifest` or `/mad-baits-sw.js` gives a 404 after activation, go to **Settings > Permalinks** and click **Save Changes** once.
