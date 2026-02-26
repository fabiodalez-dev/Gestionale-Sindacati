# ADL CRM Modern Minimal Redesign

**Date**: 2026-02-25
**Approach**: B — SCSS variables + rebuild theme + override CSS

## Design Decisions

- **Palette**: Black (#000), white (#fff), grays (#f9fafb → #1f2937). No brand colors except danger/success.
- **Font**: Inter (replaces Roboto/Nunito)
- **Accent**: Pure black for primary actions
- **Cards**: Soft shadow (Apple/Stripe style), 12px radius, no visible border
- **Sidebar**: White background, right border, black active indicator
- **Topbar**: White, bottom border only (no heavy shadow)
- **Buttons**: Black primary, 8px radius, smooth 150ms transitions

## Implementation Steps

1. Update `theme/scss/_variables.scss` — colors, font, shadows, radius
2. Update sidebar SCSS (`theme/scss/navs/_sidebar.scss`) — light variant
3. Rebuild theme with Gulp → `theme/css/sb-admin-2.min.css`
4. Rewrite `styles.css` — app-level overrides for new palette
5. Update `sidebar.php` — change classes from `sidebar-dark bg-gradient-primary` to `sidebar-light`
6. Update `topbar.php` — remove heavy shadow, adjust search input
7. Update Google Fonts link in PHP pages — switch Roboto → Inter
8. Test all pages with Playwright

## Constraints

- Zero functional changes (no HTML structure, no JS, no PHP logic changes)
- All existing features must work identically
- Mobile responsive behavior preserved
