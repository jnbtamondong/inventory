# Inventory List

- Live route: `/Nursing_System/CSSR_INVENTORY/pages/items.php`.
- Synthetic preview: `/Nursing_System/CSSR_INVENTORY/design-system/inventory.php`.

The list uses the same `cssrUiStart()` shell, sidebar route adapter, header, tokens, controls, and typography as the Dashboard. The preview also shares its navigation factory with the Dashboard preview. The active Inventory submenu is expanded automatically.

## Behavior

Search matches item name, item code, or classification (`items.category`), including the description fallback for legacy items without a name. Percent and underscore characters are searched literally. Classification, stock status, procurement type, and storage filters combine with AND. Filter options come from recorded values; an unknown filter value produces no matches instead of silently broadening the result.

Procurement Type matches recorded `stock_in.procurement_type` values. Storage Location matches item lots, falling back to `items.bin_location` only when an item has no lots. Filters identify matching items; displayed stock remains the total across locations, as labelled on the page. Lot balances are authoritative, with saved `items.current_stock` used for legacy records without lots. Items appear once even when several receipts or lots match.

The page shows active records. Summary totals apply to the whole filtered result, not just the visible page. Pagination supports 10, 25, or 50 rows, retains filters, resets when searching/changing page size, and clamps stale page numbers. Sorting is stable by item name, code, and ID. Empty states offer a reset action.

## Status rules

| Status | Rule | Badge |
| --- | --- | --- |
| AVAILABLE | Positive stock at or above reorder level | Green |
| LOW STOCK | Positive stock below reorder level | Yellow |
| OUT OF STOCK | Zero balance | Red |
| INVALID | Missing code, name (including legacy fallback), classification, or unit; negative stock or reorder level | Dark red |

INVALID is evaluated first. Its meaning was not specified in the request; the implementation uses incomplete/invalid records rather than inactive accounts or items. The page explains the rule, and invalid records appear as a separate count beside the table heading. Total Items includes them, so the three availability counts plus invalid records equal the total. Reorder equality follows the approved Dashboard convention.

## Existing workflows

The existing `items.php` authentication, page permissions, and legacy form/transaction handlers are retained. Normal GET list requests render the new UI. Add/Edit now open the shared [Item Editor modal](ITEM-EDITOR.md), whose authenticated endpoint validates and saves item metadata. Legacy form, lot, image, and POST routes remain available.

- Add Item: shared modal in create mode.
- View / clickable row: `item_details.php?id=...`.
- Edit: shared modal prefilled with the selected item.
- Stock History: `item_details.php?id=...#stock-history`, targeting the existing bin-card history section.

Add/Edit are shown only when the existing `can_manage()` check permits them. View and Stock History use the existing detail screen. Successful saves refresh the current list and show the shared success toast.

Rows support click, Enter, and Space. Native row links remain available, and interactive controls/text selection do not accidentally activate the row. The preview uses clearly labelled sample modals and never saves changes. Its sample dataset lives in an isolated, request-local SQLite in-memory database; it has no connection to hospital databases.

## Validation

```powershell
C:\xampp\php\php.exe CSSR_INVENTORY/tests/inventory-list-data.test.php
$env:CSSR_BROWSER_NO_SANDBOX = '1' # Only if needed by the restricted local browser environment
node CSSR_INVENTORY/tests/inventory-list-ui.test.cjs
node CSSR_INVENTORY/tests/dashboard-ui.test.cjs
```

29 isolated data checks cover combined filters, literal search characters, invalid inputs, summary counts, pagination, lot aggregation, and legacy records. Browser checks cover row/actions, search/filter/page interactions, navigation consistency, and widths of 1440, 1024, 768, 390, and 320px. The table scrolls locally on smaller screens; the page itself does not overflow horizontally.

Live MySQL and authenticated workflow verification remain pending while the local database connection is unavailable. No hospital records were created or changed to test the design.
