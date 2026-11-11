# JVGH CSSR visual foundation

Open `http://localhost/Nursing_System/CSSR_INVENTORY/design-system/` with Apache running.

This is a standalone component reference using synthetic data, not an operational dashboard. It needs no database, login, external fonts, libraries, or network services. Business screens are adopting this foundation individually; screens not yet redesigned keep their existing layouts. All succeeding CSSR screens should adopt this foundation instead of duplicating theme rules.

The [Dashboard](DASHBOARD.md) is the first screen implemented with this foundation. Review its synthetic preview at `http://localhost/Nursing_System/CSSR_INVENTORY/design-system/dashboard.php`; the operational version uses the existing `pages/dashboard.php` route.

The [Inventory List](INVENTORY.md) uses the same shell and adds search, filters, item actions, stock statuses, and pagination. Preview it at `http://localhost/Nursing_System/CSSR_INVENTORY/design-system/inventory.php`; the operational list uses `pages/items.php`.

## Shared assets

- `includes/design-system.php`: escaped PHP shell, inline SVG icons, module metadata, header, footer.
- `assets/css/design-system.css`: design tokens, layout, reusable `cu-*` components, responsive rules.
- `assets/js/design-system.js`: sidebar, mobile drawer, dropdowns, Manila clock, native dialogs and notifications. Demo interactions require explicit `data-cu-demo-*` attributes.
- `design-system/index.php`: reference and interaction examples.

Load this shell on its own. Do not also load Bootstrap, `app.css`, `module-theme.css`, `reference-theme.css`, or the old header/footer: they impose conflicting layout and control rules.

## Visual contract

| Token | Value | Purpose |
| --- | --- | --- |
| `--cu-navy` | `#153D56` | Headings and identity |
| `--cu-navy-deep` | `#102F44` | Sidebar |
| `--cu-teal` | `#087F83` | Primary actions and links |
| `--cu-green` | `#167449` | Success |
| `--cu-blue` | `#EAF4FB` | Informational surfaces |
| `--cu-canvas` | `#F4F7FA` | Workspace background |
| `--cu-white` | `#FFFFFF` | Cards and controls |
| `--cu-text` / `--cu-muted` | `#18394B` / `#596D7C` | Body / secondary text |
| `--cu-line` | `#DCE5EB` | Surface dividers |
| `--cu-danger` / `--cu-warning` | `#B23440` / `#885B0B` | Semantic actions and states |

System font: Segoe UI, Arial, sans-serif. Body 15px; tables/actions 13px; field labels 12px; captions 11–12px; section headings 19px; page titles 26–34px. Primary content never relies on uppercase microcopy.

Spacing scale: 4, 8, 12, 16, 20, 24, 32, 40, 48px. Cards use 16px corners and a subtle shadow; controls use 9px corners. Keep surfaces flat, icons simple, and decoration subordinate to data. Use one primary action per task.

## Layout and navigation

| Width | Behavior |
| --- | --- |
| 1200px and above | 248px sidebar, 80px collapsed rail; 32px workspace padding; two-column component/form groups; four summary cards |
| 768–1199px | Collapsible sidebar; 24px padding; single-column content groups; two summary cards per row |
| Below 768px | Sidebar becomes a modal drawer; 16px padding; full-width search on header's second row; single-column forms |

The sidebar scrolls vertically when needed and never horizontally. Desktop collapse is stored as `cssr.ui.sidebar.collapsed`. Activating a group from the collapsed rail expands the sidebar and opens its submenu. Mobile uses an overlay, inert background, focus containment, Escape to close, and focus return. Inventory and Reports demonstrate expandable groups; any module may supply children. Child routes automatically open the corresponding active group.

The top bar contains search, a Philippine date/time display, notifications, and a user profile. The desktop profile shows the current role; compact layouts expose it in the profile dropdown. Search submits to the caller's route unless the reference-only marker is present. Notifications and profile details must be supplied from authenticated data on real pages.

## Component contracts

- Buttons: `.cu-btn`, with `.cu-btn-secondary`, `.cu-btn-ghost`, `.cu-btn-danger`, `.cu-btn-sm`. Use native `disabled` and `type="button"` for non-submit controls. `.cu-icon-button` always needs an accessible label.
- Cards: `.cu-card`, `.cu-card-head`, `.cu-card-body`. Use `.cu-stack`, `.cu-grid`, `.cu-stats`, `.cu-actions` for consistent spacing and alignment.
- Tables: `.cu-table` within `.cu-table-wrap`. Give the wrapper `tabindex="0"`, `role="region"`, and an accessible label. Include a caption and column scopes. Quantities use `.cu-number`; procurement quantities use `.cu-center`. Wide tables scroll locally on mobile, never the whole page. Do not truncate clinical item names or hide important columns.
- Forms: `.cu-field` wraps a persistent label and `.cu-input`. Use `.cu-form-grid` and `.cu-span-2`. Associate help/error text with `aria-describedby`; use `aria-invalid="true"` only for actual validation errors. Validate again on the server. Focus the first invalid field, preserve values, and remove error state after correction. Item descriptions allow 1,000 characters; enforce the limit server-side when integrated.
- Badges: `.cu-badge` plus `-success`, `-warning`, `-danger`, `-info`, `-teal`. Always include a meaningful text label. Examples: In stock / Low stock / Out of stock / Pending / Approved / Draft.
- Feedback: `.cu-alert`, `.cu-alert-success`, `.cu-empty`. `window.cssrUI.toast(message)` creates a six-second success notification at the upper left of the workspace. Use persistent inline errors for failures or instructions that require action. Notifications are announced through a polite live region.
- Modals: native `<dialog class="cu-dialog">` with an `aria-labelledby` heading and optional `aria-describedby`. Trigger using `data-cu-dialog="dialog-id"`; close buttons use `data-cu-dismiss`. Use `.cu-dialog-head`, `.cu-card-body`, `.cu-dialog-foot`. Escape and focus return are native. Do not attach duplicate popup handlers. Destructive confirmations focus the safe action first.
- Icons: `cssrUiIcon('box')` returns a decorative SVG; put accessible names on its containing control. Use existing icon vocabulary before adding new symbols.

Visible keyboard focus, a skip link, reduced-motion overrides, print rules, native form controls, semantic tables, and dialog keyboard behavior are part of the foundation. Test each real workflow with a keyboard and screen reader as it is integrated; this reference is not an accessibility certification.

## Integrating a succeeding screen

Authenticate and authorize in the page controller **before any output**. Navigation visibility does not grant access. Existing route/API authorization, CSRF protection, database validation, and audit rules still apply.

Example structure for a page one directory below the CSSR root (replace routes and identity with your actual controller values):

```php
<?php
// Run the existing authentication and page-authorization guards here first.
require_once __DIR__ . '/../includes/design-system.php';

// Include only modules the signed-in user may access. No wildcard permissions.
$navigation = [
    ['id'=>'dashboard', 'label'=>'Dashboard', 'icon'=>'dashboard',
     'group'=>'Overview', 'href'=>$dashboardUrl],
    ['id'=>'inventory', 'label'=>'Inventory', 'icon'=>'box',
     'group'=>'Supply operations', 'children'=>[
         ['id'=>'items', 'label'=>'Item masterlist', 'href'=>$itemsUrl],
     ]],
];

cssrUiStart([
    'title'=>'Item masterlist', 'home'=>$homeUrl, 'assets'=>'../assets',
    'navigation'=>$navigation, 'active'=>'items', 'searchAction'=>$searchUrl,
    'initials'=>$userInitials, 'user'=>$displayName, 'role'=>$roleLabel,
    'settings'=>$settingsUrl,
]);
?>
<div class="cu-stack">
    <div class="cu-page-heading"><div><h1>Item masterlist</h1></div></div>
    <section class="cu-card"><div class="cu-card-body">Page content</div></section>
</div>
<?php cssrUiEnd(); ?>
```

`cssrUiModules()` supplies the requested module labels, icons, and grouping metadata. Map each allowed module to an authorized route. Convert child label strings to arrays containing `id`, `label`, and `href` before rendering. Never pass user-controlled URLs directly to shell options; use trusted internal routes. All displayed strings are escaped by the renderer.

Keep page-specific business scripts separate. Do not add `data-cu-reference`, `data-cu-demo-form`, or `data-cu-demo-toast` to production workflows; they intentionally prevent persistence in the reference. The sample profile and all module links in the reference are clearly marked as preview content.

## Validation

```powershell
C:\xampp\php\php.exe -l CSSR_INVENTORY/includes/design-system.php
C:\xampp\php\php.exe -l CSSR_INVENTORY/design-system/index.php
node --check CSSR_INVENTORY/assets/js/design-system.js
node CSSR_INVENTORY/tests/design-system.test.cjs
```

The browser check requires local Apache, Node 22+ and Chrome or Edge. It prefers Chrome when installed; set `CSSR_BROWSER_PATH` to use another Chromium executable, or `CSSR_DESIGN_URL` to override the local URL. It exercises desktop/mobile navigation, modal interaction, form validation, sample search, reload persistence, viewport overflow, and browser exceptions. Screenshots are written to the operating system's temporary directory. The initial visual verification used Chrome at 1440, 1024, 768, 390, and 320px widths; real mobile-device and assistive-technology checks remain part of screen integration.

In this restricted Windows test environment, Chromium's renderer sandbox intermittently stalled CDP startup. The completed verification used `$env:CSSR_BROWSER_NO_SANDBOX = '1'` before the test command. This opt-in flag affects only the disposable browser used to test the trusted local reference; it is off by default and does not change application behavior.
