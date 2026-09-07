# Arete UI Design System

This document defines the shared visual language for the authenticated admin, student, and employee workspaces. The approved maroon admin dashboard is the reference implementation.

## Foundations

- Brand: `--primary`, `--primary-dark`, and `--primary-deep` provide hierarchy; `--accent` is reserved for small academic highlights.
- Surfaces: use `--page-bg` for the canvas, `--surface` for working areas, and `--surface-soft` for subtle grouping.
- Typography: page titles are 25–29px/750; section titles 16–18px/700; body copy 13–14px; metadata 10–12px.
- Spacing: use 4, 8, 12, 16, 20, 24, 32, and 40px increments.
- Shape: controls use 9–10px radii; primary surfaces use 14px. Larger radii require a specific visual reason.
- Elevation: use restrained borders and `0 4px 18px rgba(33,26,29,.04)` for primary surfaces.

## Layout

- `.main__inner` is capped at 1480px with 28px desktop and 15px mobile gutters.
- `.module-page` is the standard vertical page flow.
- `.module-page__header` contains breadcrumb, title, description, and a real primary action.
- `.split-layout`, `.metric-grid`, and `.portal-dashboard-grid` provide intentional page composition without nested decorative cards.

## Components

- Cards: `.module-card`, `.settings-card`, and `.dashboard-card` share surface, border, radius, and elevation.
- Forms: `.module-field`, `.module-input`, `.module-select`, and `.module-textarea`; controls are at least 44px high and have a maroon focus ring.
- Actions: `.btn--primary` is used once for the main workflow action; `.btn--secondary` for reversible actions; destructive actions use restrained danger styling.
- Tables: `.table-scroll` contains horizontal overflow, while `.data-table` uses a tinted semantic header, 56px rows, separators, and hover feedback.
- Status: use text plus color. Status styling must never rely on color alone.
- Empty states: `.compact-empty` includes a concise title and explanation, with an action only when a real route exists.
- Identity: `.portal-identity-panel` introduces the signed-in person using persisted name, role/class, identifier, and status.
- Modals: 16px radius, restrained backdrop, concise content, and clearly ordered actions.

## Responsive and accessibility

- At 1023px the sidebar becomes a drawer and a contextual topbar title appears.
- At 760px page headers and form actions stack; tables remain inside their own scroller.
- At 480px metric grids become single-column.
- All icon-only actions require an accessible label. Form controls require visible or screen-reader labels.
- Global `:focus-visible` remains enabled and reduced-motion preferences are honored.
- Body-level horizontal overflow is forbidden.

## Content rules

- Never invent trends, counts, notifications, people, or events.
- Prefer clear empty states when persisted data does not exist.
- Avoid raw `Edit Delete` text, giant CTAs, excessive pills, decorative gradients, and redundant cards.
- Role portals should prioritize their own workflows and must not copy admin-only navigation or actions.
