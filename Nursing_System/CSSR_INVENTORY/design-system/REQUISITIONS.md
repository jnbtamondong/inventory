# Requisitions

Live route: `CSSR_INVENTORY/pages/requisitions.php`. Sample-only preview: `CSSR_INVENTORY/design-system/requisitions.php`.

Uses the shared shell, navigation adapter, tokens, buttons, forms, badges, and dialogs. Page styles are confined to `assets/css/requisitions.css`. The mobile detail view uses stacked item cards and a fixed action footer; the main table scrolls within its container. Native dialogs provide focus containment, with Escape closing only the top dialog. Printing includes the selected requisition and recorded quantities rather than unsaved approval inputs.

## Data and workflow

- Lists and direct detail URLs enforce the existing department scope. Admin/CSSR management permissions allow the same cross-department access as the existing request workflow. No role permissions are broadened.
- TRN displays the existing `request_no`; historical REQ numbering is preserved. Search, department, request date, status, tab counts, and pagination run on the server.
- Pending displays as **For Approval**. **Approved** includes all approved requests. An approval displays as **For Release** only when all positive approved lines have matching, active, unexpired reservations, eligible CSSR lots, and enough stock for active reservations. Approved requests needing reservation review remain teal.
- **Rejected** is a new stored outcome. The schema initializer appends it to the existing enum without changing historical Cancelled records. Cancelled records remain visible in All. Request history includes Rejected.
- **New Request** opens the existing request creation/cart workflow. Approval, partial approval, rejection, and release submit to the existing authenticated, CSRF-protected transaction handler and return to Requisitions. Approval reserves stock; release updates lots, item balances, issued quantities, reservations, stock-out records, and bin cards.
- Full approval uses requested quantities. Partial approval requires at least one positive quantity and one reduced quantity. Selected lots must satisfy existing FEFO rules. Rejection requires a reason and cancels active reservations. All changes require confirmation. Errors preserve approval inputs and remarks.
- Release from this page requires a ready approval; it cannot release a Pending or already Released request. Lot checks exclude expired/unavailable stock and protect other active reservations with locking reads. The existing single-lot-per-request-line workflow remains in use.

## Validation

```powershell
C:\xampp\php\php.exe CSSR_INVENTORY/tests/requisitions-data.test.php
node CSSR_INVENTORY/tests/requisitions-ui.test.cjs
```

Data checks use SQLite in memory and cover filtering, department isolation, direct detail access, reservation expiry, expired lots, insufficient stock, reservation mismatches, and previously issued lines. Browser checks cover desktop/mobile layouts, tabs, details, confirmation dialogs, quantity validation, focus, printing, and preview feedback. The preview never submits transactions or connects to the hospital database.

Local MySQL port 3306 was unavailable during implementation. Authenticated MySQL approval, rejection, release, and automatic schema migration still need verification against the running service. PHP syntax checks and existing dashboard, inventory, and item-editor data checks passed. No hospital records were modified for testing.
