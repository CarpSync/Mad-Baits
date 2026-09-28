# MadBaits Figma Asset Pack

Design and brand asset export from the MadBaits WordPress theme repository (`CarpSync/Mad-Baits`). Curated for native iOS/Android app design in Figma.

**Export date:** September 2026  
**Source:** `theme/assets/img/` and related theme CSS

---

## Logos

| File | Location | Dimensions | Recommended use |
|------|----------|------------|-----------------|
| `madbaits-cup-logo.svg` | `01-LOGOS/SVG/` | 1460×1383 viewBox | **Primary brand mark** — cup + "MAD BAITS" wordmark in brand yellow |
| `mad-yellow-logo.png` | `01-LOGOS/PNG/` | 331×164 | Compact horizontal logo for login/email contexts |
| `mad-logo-email-transparent.png` | `01-LOGOS/PNG/` | 1024×969 | High-res transparent PNG logo (email/launch assets) |
| `madbaits-app-icon-1024.png` | `01-LOGOS/PNG/` | 1024×1024 | Square app icon with full brand mark on dark background |

### Recommended logo for the app

- **Primary logo:** `01-LOGOS/SVG/madbaits-cup-logo.svg` — vector, scalable, used site-wide (header, PWA, login fallback)
- **Dark background:** SVG cup logo or `madbaits-app-icon-1024.png` (designed for dark UI)
- **Light background:** `mad-yellow-logo.png` or SVG with dark backing layer added in Figma

The SVG logo uses fill colour `#fff200` / `#fff202` (brand yellow) with embedded raster texture inside the cup silhouette.

---

## Brand colours

Extracted from theme CSS custom properties. **Confirmed brand tokens** are defined in `:root` and reused across the site.

### Primary brand palette (confirmed)

| Name | HEX | RGB | Usage |
|------|-----|-----|-------|
| **Accent / Brand Yellow** | `#FFF202` | 255, 242, 2 | Primary CTA buttons, highlights, PWA theme colour, email accent bars, logo fill |
| **Accent Hover** | `#FFF76A` | 255, 247, 106 | Login page button hover state |
| **Accent Soft** | `rgba(255, 242, 2, 0.12–0.34)` | — | Subtle yellow glow/borders on dark panels |
| **Background** | `#050505` | 5, 5, 5 | Main site body background |
| **Background Soft** | `#111111` | 17, 17, 17 | Secondary dark surfaces |
| **Surface / Panel** | `#171717` | 23, 23, 23 | Cards, elevated panels |
| **Border** | `#2B2B2B` | 43, 43, 43 | Default borders on dark UI |
| **Text Primary** | `#F5F5F5` | 245, 245, 245 | Body copy on dark backgrounds |
| **Text Muted** | `#B2B2B2` | 178, 178, 178 | Secondary/supporting text |

### Extended UI tokens (confirmed, from `visual-consistency.css`)

| Name | HEX | Usage |
|------|-----|-------|
| Success | `#6FDC8C` | Positive states |
| Danger | `#FF7C7C` | Errors, destructive actions |
| Border Accent | `rgba(255, 242, 2, 0.34)` | Highlighted card borders |

### Checkout / form tokens (context-specific)

| Name | HEX | Usage |
|------|-----|-------|
| Form Background | `#111214` | Dark checkout form fields |
| Form Text | `#F2F3F5` | Input text |
| Form Focus | `#FFF202` | Focus ring on inputs |
| Button Text on Accent | `#000000` / `#050505` | Text on yellow CTA buttons |

### Email branding (confirmed)

| Name | HEX | Usage |
|------|-----|-------|
| Email Shell | `#0A0A0A` | Header/footer background |
| Email Accent Bar | `#FFF202` | 4px yellow divider |
| Email Body BG | `#FFFFFF` / `#EAEAEA` | Message content area |

### Incidental / non-core colours

These appear in specific contexts but are not primary brand tokens:

| HEX | Context |
|-----|---------|
| `#040404`, `#0C0C0C`, `#131313` | Premium/dark section variants |
| `#121212`, `#4A4A4A` | Stripe checkout appearance (light payment panel) |
| `#C0392B` | Stripe danger/error state |
| `#F5C518`, `#DCDCDE` | Supplier badge colours (product cards) |
| `#E32228` | PWA splash "BAITS" text accent |

### Border radii

| Token | Value | Usage |
|-------|-------|-------|
| `--mb-radius` | 14px | Default cards/containers |
| `--mb-radius-sm` | 8px | Small elements |
| `--mb-radius-md` | 14px | Medium panels |
| `--mb-radius-lg` | 20px | Large cards |
| `--mb-radius-xl` | 28px | Hero/feature blocks |
| `--mb-radius-pill` | 999px | Buttons (`.mad-button` uses pill shape) |
| `--mb-radius-premium` | 16px | Premium product sections |

---

## Typography

### Inter (primary UI font)

| Property | Value |
|----------|-------|
| **Font name** | Inter |
| **Source/provider** | Referenced in CSS; typically Google Fonts or system install |
| **Weights used** | 400 (body default), 700 (labels/subheads), 800 (headings, buttons, kickers) |
| **Heading usage** | Same family as body — differentiated by weight 700–800 and letter-spacing 0.08–0.18em |
| **Body usage** | `font-family: "Inter", "Segoe UI", Roboto, Helvetica, Arial, sans-serif` |
| **Button typography** | Inter (inherited), weight 800, pill buttons |
| **Local font file** | **No local font file included** |

Stack fallbacks: Segoe UI → Roboto → Helvetica → Arial → sans-serif

### System UI (Stripe checkout only)

| Property | Value |
|----------|-------|
| **Font name** | system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto |
| **Usage** | Stripe Payment Element appearance only |
| **Local font file** | **No local font file included** (OS fonts) |

### Helvetica Neue / Helvetica (email templates)

| Property | Value |
|----------|-------|
| **Font name** | Helvetica Neue, Helvetica, Roboto, Arial |
| **Weights used** | 600, 700, 800 |
| **Usage** | WooCommerce transactional emails, website launch email |
| **Local font file** | **No local font file included** |

### Arial (PWA splash / generated graphics)

| Property | Value |
|----------|-------|
| **Font name** | Arial, Helvetica, sans-serif |
| **Usage** | PWA offline splash SVG generation |
| **Local font file** | **No local font file included** |

### Myriad Pro (inside SVG logo only)

The cup logo SVG embeds `font-family: 'Myriad Pro'` for the "BAITS" text path. This is part of the vector artwork, not a web font dependency.

### Fonts folder (`09-FONTS/`)

**Empty — no `.woff`, `.woff2`, `.ttf`, or `.otf` files exist in this repository.**  
For Figma, license and import Inter separately (Google Fonts or a purchased desktop license).

---

## Range imagery

Ranges with dedicated imagery in this pack:

| Range | Assets included | Notes |
|-------|-----------------|-------|
| **ASBO** | Hero scene, boilie close-up, portrait | 3 files |
| **Pandemic** | Hero scene | 1 file |
| **P-Fish** | Open boilie shot, glug waterline | 2 files |
| **Nutz Plus** | Hero scene | 1 file |
| **Nutz Banana** | — | **No dedicated imagery found in repo** |
| **Wicked Whites** | Hero scene, boilie open shot | 2 files |
| **BBB** | Boilie white product shot | 1 file |
| **Compulsive Angler** | Hero scene, portrait, hookbait/popup product shots | 7 files |
| **Calamino** | — | **No dedicated imagery found in repo** |
| **Other** | Session pack bundle, terminal tackle, hookbaits hero | In `02-PRODUCTS/` |

---

## Recommended Figma assets

### Best primary logo
`01-LOGOS/SVG/madbaits-cup-logo.svg`

### Best dark-background logo
`01-LOGOS/SVG/madbaits-cup-logo.svg` or `01-LOGOS/PNG/madbaits-app-icon-1024.png`

### Best light-background logo
`01-LOGOS/PNG/mad-yellow-logo.png` (add dark text backing if needed)

### Strongest product images
- `02-PRODUCTS/bbb-boilie-white.png` (2048×1536)
- `02-PRODUCTS/session-pack-bundle.png` (1609×978)
- `02-PRODUCTS/compulsive-triple-hookbaits.png`
- `02-PRODUCTS/plum-shell-pops-chubby.jpg` (1875×2560)
- `02-PRODUCTS/product-cutout-photoroom.jpeg` (1800×2400)

### Strongest hero/lifestyle images
- `04-LIFESTYLE/jerry-hammond-englefield-lagoon-016.jpg` (1280×1920)
- `04-LIFESTYLE/img-6276-bank-session.jpeg` (1170×928, high file size = quality)
- `04-LIFESTYLE/img-0820-bank-session.jpeg` (2560×2075)
- `06-HERO-MARKETING/jerry-hammond-lagoon-016-hero-scene.jpg` (1280×1920, pre-cropped for hero)
- `06-HERO-MARKETING/session-pack-hero-scene.jpg` (1920×1167)

---

## Missing assets

The following would improve mobile app design but were **not found** in this repository:

- **Inter font files** — referenced in CSS but not stored locally
- **Nutz Banana range** photography/graphics
- **Calamino range** photography/graphics
- **Individual SKU product packshots** — most product photos are range/hero level, not per-SKU catalog images (those live in WordPress media uploads, not in the theme repo)
- **Range wordmark/logos** — no separate ASBO, BBB, P-Fish etc. logo files; only the master Mad Baits cup logo
- **Brand guidelines PDF** — none in repo
- **Icon set / UI kit** — no custom icon font or SVG icon library beyond app icons
- **Patterns/textures** — no standalone texture files; backgrounds are CSS gradients
- **Video assets** — not included
- **Clothing/merch** product photography
- **High-res transparent product PNGs** — limited; most products are JPG lifestyle shots

---

## Note on file extensions

Several source files in the theme repo use `.png` extensions but contain JPEG data (e.g. `jerry-sunset-cast.png`, `hookbaits-hero.png`). Copies in this export were renamed to `.jpg` where appropriate so they open correctly in design tools.

---

## Exclusions applied

The following were intentionally excluded from this pack:

- Duplicate `CUP-LOGOv2-1 (1).svg` (identical to primary SVG)
- Lower-resolution `session-pack-1200x729.png` (superseded by 1609×978 original)
- `-scene.jpg` variants where only originals were needed (scene copies kept in `06-HERO-MARKETING/`)
- Favicons at 16×16 and 32×32 (too small for design work)
- Duplicate icon sizes (192, 384, etc.) — kept 512px and 1024px only
- Hash-named duplicates (`cc70ab74…` identical to `asbo.jpeg`)
- Mislabeled/corrupt files (`Jerry_Hammond…016.png` is JPEG data)
- WordPress upload directory images (not in theme repo)
- All source code, config, and environment files

---

## File inventory summary

| Category | Count |
|----------|-------|
| Logos | 4 |
| Products | 18 |
| Range images | 16 |
| Lifestyle | 18 |
| Catches | 13 |
| Hero/Marketing | 23 |
| Graphics | 2 |
| Icons | 4 |
| Fonts | 0 |

See `asset-manifest.json` in this folder for the full file list with dimensions.
