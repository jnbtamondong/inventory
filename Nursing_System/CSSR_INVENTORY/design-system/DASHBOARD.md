# CSSR Dashboard

The first operational screen using the shared CSSR design foundation.

- Live page: `/Nursing_System/CSSR_INVENTORY/pages/dashboard.php` (existing authentication required).
- Design preview: `/Nursing_System/CSSR_INVENTORY/design-system/dashboard.php` (synthetic data only, no database connection).

Both render `includes/dashboard-view.php` through the existing `cssrUiStart()` / `cssrUiEnd()` shell. `assets/css/dashboard.css` only adds dashboard composition; it does not redefine the sidebar, palette, or typography. The shared shell now accepts optional `styles` and `scripts` arrays for page assets. `assets/js/dashboard.js` renders local SVG charts; no CDN or chart library is required.

## Metric definitions

Only active inventory records count toward the KPIs. Available = positive stock at or above `minimum_stock`; Low Stock = positive stock below it; Out of Stock = zero or negative stock. These three exclusive categories sum to Total Inventory Items. A reorder level is the existing `minimum_stock` field, not a new database field.

Monthly Issuance sums `stock_out.quantity_issued` by `issued_date` for the selected year, with all twelve months present and missing months shown as zero. The year selector includes the current year and years found in issuance records. Invalid/unavailable years fall back to the current year. Changing year refreshes only the selected issuance period; inventory totals and today's activity remain current. The chart also has a readable monthly data table.

Today's Activity uses Philippine dates:

| Activity | Definition |
| --- | --- |
| Department Requests | Requests dated today, excluding Cancelled/Deleted |
| Pending Approval | Today's requests still Pending; older pending requests remain in the requisition table |
| Released Requests | Requests with status Released and a release timestamp today |
| Items Returned | Sum of units received back today on Received department returns |
| Inbound Deliveries | Distinct inbound numbers dated today, falling back to reference number for legacy records; item lines do not inflate the delivery count |

Pending Requisitions includes Pending (yellow For Approval) and Approved (blue For Release), oldest first, up to six rows. Number of Items counts distinct item IDs. View opens the existing request-detail page; it does not add new approval or release behavior. Low Stock Items shows up to six priorities, with stockouts first. Item name falls back to the description only for legacy items with no name.

## Access and responsive behavior

The existing dashboard authorization guard is retained. Inventory figures require `cssr_inventory.view`. Requisition figures require `ris_requests.view`. Users who can edit inventory or approve requests can see all departments; other permitted viewers are scoped to their department, matching the existing request-detail rule. Missing department IDs never grant wider access. Unavailable metrics display a dash and an access explanation rather than a fabricated zero. The route adapter keeps the shared sidebar labels, icons, ordering, and submenu styles, and filters links to existing permissions.

Four KPI cards are shown in a row on wide desktop layouts and two columns on tablet/mobile. Charts stack below 1200px. Tables stack below 1600px so status columns stay readable; narrower screens use local table scrolling without page overflow. SVG charts resize with their containers, expose descriptive labels, and require no animation. All exact chart values are also available as text.

## Checks

```powershell
C:\xampp\php\php.exe CSSR_INVENTORY/tests/dashboard-data.test.php
$env:CSSR_BROWSER_NO_SANDBOX = '1' # Only if needed in the restricted local test environment
node CSSR_INVENTORY/tests/dashboard-ui.test.cjs
node CSSR_INVENTORY/tests/design-system.test.cjs
```

The data test uses an isolated SQLite in-memory database, with compatibility functions for MySQL's YEAR, MONTH, GREATEST and CONCAT. It checks 26 cases including stock boundaries, year selection, delivery grouping, department scoping, and empty data. It never changes hospital records. Browser checks use the synthetic preview at 1440, 1024, 768, 390 and 320px, including year selection, charts, modals, navigation, and mobile layout.

At implementation time, the local MySQL connection was unavailable. The production query path is wired but still needs verification against the running hospital database and authenticated accounts when the database service is available.
