# Website launch email

Campaign email announcing the new Mad Baits website, password reset, and PWA install instructions.

## Files

| File | Purpose |
|------|---------|
| `website-launch.html` | HTML version (table layout, inline styles, 680px max width) |
| `website-launch.txt` | Plain-text fallback |

## Images (bundled in theme)

After deploy, these URLs are live on production:

| Asset | Path |
|-------|------|
| Logo (circular MAD) | `https://madbaits.com/wp-content/themes/mad-baits/assets/img/email/website-launch/mad-logo.png` |
| Product hero (session pack) | `https://madbaits.com/wp-content/themes/mad-baits/assets/img/email/website-launch/product-hero.png` |
| App screenshot (PWA) | `https://madbaits.com/wp-content/themes/mad-baits/assets/img/email/website-launch/app-screenshot.png` |

Source files live in `assets/img/email/website-launch/` in the theme.

### Local preview

Open `website-launch.html` in a browser from the repo (double-click or **Open with Live Server**). Image `src` values use **relative paths** so they load without deploy.

### Before you send the campaign

Email clients require **absolute HTTPS** image URLs. Find-and-replace in the HTML:

| Find | Replace with |
|------|----------------|
| `../../assets/img/email/website-launch/` | `https://madbaits.com/wp-content/themes/mad-baits/assets/img/email/website-launch/` |

Deploy the theme first so those URLs return 200 on production.

## Suggested subject line

**The new Mad Baits website is live — reset your password**

Alternates:

- Your Mad Baits account — new site + password reset
- Mad Baits has a new website (faster shop + mobile app)

## Suggested preview text

**Faster shopping, bundle builder, and mobile app — reset your password to log in.**

(Also embedded in the HTML preheader block.)

## Brand colours (reference)

- Background: `#050505`, shell `#0a0a0a`, cards `#171717`
- Accent / CTAs: `#fff202`
- Text: `#f5f5f5`, muted `#b2b2b2`, borders `#2b2b2b`

## Sending notes

- Test in Gmail, Apple Mail, and Outlook (desktop + mobile).
- Host images on HTTPS (madbaits.com or CDN); avoid relative paths in production sends.
- Pair HTML + plain text in your provider (Klaviyo, Mailchimp, Brevo, etc.).
