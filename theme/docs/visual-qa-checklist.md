# Mad Baits Visual QA Checklist

Use this as a lightweight release gate after any visual/theme change.

## Breakpoints

- Mobile: `390x844` (iPhone 12/13/14)
- Tablet: `768x1024` (iPad portrait)
- Desktop: `1440x900`

## Screenshot Baseline Routes

- Homepage: `/`
- Shop archive: `/shop/`
- Single product: open a variable boilie product
- Cart: `/cart/`
- Checkout: `/checkout/`
- My Account dashboard: `/my-account/`

## Global Checks (all pages)

- Typography scale and line-height read cleanly on dark overlays.
- Vertical rhythm is consistent between adjacent sections.
- Buttons and links have visible hover/focus states.
- Native selects and Select2 dropdowns render consistently.
- No clipped content or horizontal page scroll at any breakpoint.

## Homepage Checks

- Hero image stays cinematic; copy remains readable over gradients.
- `Designed For Serious Campaign Anglers` background is visible and correctly cropped.
- `Choose Your Edge` cards keep unique tint identity per range.
- Product cards use consistent image height, spacing, and hover motion.
- `From The Bank` section has no duplicate heading text.

## Shop + Product Checks

- Product archive cards keep aligned media, titles, and action rows.
- Variation dropdowns are readable and arrow alignment is consistent.
- `Complete Your Session` / `Pair It With` layouts stay intact on mobile.
- Sticky add-to-basket bar does not overlap critical controls on mobile.

## Cart + Checkout Checks

- Cart totals panel spacing matches checkout panel spacing.
- Checkout labels are yellow and inputs/selects are high-contrast (`#111` text on white fields).
- Placeholder text and Select2 options are readable.
- Validation errors are visible and easy to interpret.

## Account Checks

- Sidebar navigation touch targets are at least 44px high.
- Account content panels maintain padding and readability at mobile size.
- Tables/forms inside account pages do not overflow.

## Sign-off Template

- Date:
- Reviewer:
- Environment (staging/prod):
- Pass/Fail by page:
  - Home:
  - Shop:
  - Product:
  - Cart:
  - Checkout:
  - Account:
- Notes and follow-ups:
