# Mad Baits Team Access

Invite-only private access for **Team**, **RNT**, **Ambassador** and **Tester** users on WordPress + WooCommerce + Mad Baits PWA.

## Requirements

- WordPress 6+
- WooCommerce
- [Mad Baits Push Notifications](../mad-baits-push-notifications/) (VAPID + Web Push)
- Discount Rules Pro (optional — role pricing is **not** duplicated here)

## Installation

1. Upload `mad-baits-team-access` to `wp-content/plugins/`
2. Activate **Mad Baits Team Access**
3. Activate **Mad Baits Push Notifications** and configure VAPID keys (Settings → Push Notifications)
4. Visit **Settings → Permalinks** and save once (flush rewrites for `/team-invite/`)
5. Confirm pages exist: **Team Invite** (`/team-invite/`) and **Team Portal** (`/team-portal/`)

## Roles

| Role | Slug | Can view |
|------|------|----------|
| Team | `mad_team` | All private groups |
| RNT | `mad_rnt` | RNT Specials, Test Baits, Ambassador Access |
| Ambassador | `mad_ambassador` | Ambassador Access |
| Tester | `mad_tester` | Tester Access, Test Baits |
| Media Team | `mad_media` | Public catalogue only (no private product groups) |

On registration, matching theme roles (`team`, `rnt`, `ambassador`, `tester`) are added for **Discount Rules Pro** targeting.

## Product categories

Assign WooCommerce categories (slugs are flexible; defaults include):

- `test-baits` — Test Baits
- `team-only` — Team Only
- `rnt-specials` — RNT Specials
- `ambassador-access` — Ambassador Access
- `tester-access` — Tester Access

Override per product under **Team Access** meta box on the product edit screen.

## Admin: invites

**Team Access → Invites**

- Create codes with role, expiry, usage limit, notes, and optional invitee email
- **Create & send email** — emails the personal invite link to the invitee (uses WordPress `wp_mail`)
- **Send email** on an existing row — resend or send after adding an email address
- Manual link: `https://yoursite.com/team-invite/?code=YOURCODE`
- Toggle active/inactive

## Admin: push

**Team Access → Push notifications**

Send title, message, target role, optional URL and image to subscribed private members.

**Test bait publish:** enable **Notify eligible private members when published** on a product in Test Baits category (first publish only; logged in notification history table).

## Team Portal

Shortcode: `[mbta_team_portal]` on `/team-portal/`

- Role-specific blocks
- Private product grid
- Enable / disable private push

## Discount Rules Pro

Target using body classes:

- `mbta-role-mad_team` (and `mad-baits-discount-role-mad_team`)
- `mad-baits-role-team` (legacy mirror)
- Product cards: `mbta-access-{group}`, `mbta-viewer-{role}`

## Security

- Nonces on admin actions and registration
- Private products hidden from shop, related products, REST (403), sitemaps
- `noindex` on private single products for authorised viewers
- Logged-out / wrong-role direct URLs → login or branded denied page

## Uninstall

Deactivating keeps roles and tables. To remove DB tables on uninstall, set option `mbta_drop_tables_on_uninstall` to `1` before deleting the plugin (advanced).

## Files

- `includes/class-mbta-roles.php` — roles & access matrix
- `includes/class-mbta-invites.php` — invite CRUD
- `includes/class-mbta-registration.php` — `/team-invite/` flow
- `includes/class-mbta-products.php` — visibility & publish notifications
- `includes/class-mbta-portal.php` — portal shortcode & nav
- `includes/class-mbta-push.php` — role-targeted push
- `includes/class-mbta-discount-bridge.php` — Discount Rules hooks
- `includes/class-mbta-admin.php` — admin UI
