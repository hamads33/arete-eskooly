# Arete UI Design System

This document defines the shared visual language for admin, teacher, student and parent workspaces. Login/signup and the approved admin dashboard are the reference implementations. Internal screens translate their restraint and hierarchy into operational UI; they do not copy marketing composition.

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

### Complete token reference

| Family | Tokens and values |
|---|---|
| Brand | primary #7A1632; primary-dark #541022; primary-deep #3B0B18; primary-light #A93A59 |
| Accent | accent #D9A441; accent-soft #F3E4C4 |
| Surfaces | page-bg #F7F7F8; surface #FFFFFF; surface-soft #FAF7F8 |
| Text | primary #211A1D; secondary #776D71; muted #9B9195 |
| Borders | default #ECE5E8; strong #DED3D8 |
| Semantic | success #198754; warning #B7791F; danger #D84242 |
| Space | 4, 8, 12, 16, 20, 24, 32, 40px |
| Radius | controls 9–10px; cards 14px; modal 16px |
| Shadow | default 0 4px 18px rgba(33,26,29,.04) |

### Workflow patterns

- Messaging: 280–330px conversation list, flexible thread, sender/timestamp bubbles and an anchored composer. It stacks vertically below 760px.
- Fees: show total, paid, outstanding, prior balance, due date and status before transaction details. Use tabular numeric figures.
- Results: show exam, class/subject, obtained/total, grade, ABS and publish state. Charts never replace exact values.
- Profiles: start with identity, role/class, identifier and status; follow with grouped read-only details and explicit edit mode.
- Documents: use icon, title, metadata and one view/download affordance.
- Reports: consistent masthead, entity/period filters, summary metrics and export group.
- Template editor: document-type rail, settings panel, branding controls and real preview; never expose raw JSON or CSS.
- Loading: only existing asynchronous workflows may use skeletons. Never simulate async behavior.

### Buttons and states

Use primary for the main commit, secondary for reversible alternatives, ghost for quiet navigation, danger for destructive operations, and 36–42px icon buttons with accessible names. Supported status vocabulary is Active, Inactive, Draft, Published, Paid, Partial, Unpaid, Present, Absent, Late, Leave, Upcoming, Live, Completed, Cancelled and Testing. Every status includes text; color is supplementary.

## Responsive and accessibility

- At 1023px the sidebar becomes a drawer, contextual topbar title appears, and optional fullscreen action hides.
- At 760px page headers and form actions stack; forms become one column; messaging stacks; tables remain inside their own scroller; modals fit 100dvh.
- At 430px gutters reduce to 12px, titles become 24px, the user trigger compacts and metrics become one column.
- Validate logically at 1920, 1440, 1366, 1024, 768, 390 and 360px.
- All icon-only actions require an accessible label. Form controls require visible or screen-reader labels.
- Global `:focus-visible` remains enabled and reduced-motion preferences are honored.
- Increased-contrast preferences receive stronger working borders.
- Body-level horizontal overflow is forbidden.

## Content rules

- Never invent trends, counts, notifications, people, or events.
- Prefer clear empty states when persisted data does not exist.
- Avoid raw `Edit Delete` text, giant CTAs, excessive pills, decorative gradients, and redundant cards.
- Role portals should prioritize their own workflows and must not copy admin-only navigation or actions.
