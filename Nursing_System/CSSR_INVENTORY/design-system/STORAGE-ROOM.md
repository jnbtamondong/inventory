# Storage Room 1

Live page: `pages/storage_room_1.php`. Sample preview: `design-system/storage_room_1.php`.

The page uses the shared CSSR shell, sidebar, header, typography, palette, cards, forms, buttons, and badges. It shows item codes/names, batches, expiry dates, quantities, units, statuses, and transfer actions. Search, status filters, pagination, and an empty state are included. The transfer form is centered on desktop and a full-screen sheet on mobile, with its actions fixed below the scrolling form.

## Stock definitions

- Total Stock is the sum of positive available and quarantined quantities in current Storage Room 1 lots for active items. Depleted and deleted lots are excluded.
- Available Items counts distinct items whose transferable room stock is positive and meets the item's reorder level. Low Stock counts distinct items with positive transferable stock below that level.
- Expiring Soon counts transferable batches expiring from today through 90 days ahead. The window is displayed on the card.
- Available quantity excludes expired, held, unidentified, and actively reserved stock. Quantities in the table include held stock, with the transferable balance shown separately where different.
- Transfer choices follow the existing earliest-expiry-first-out rule. Later batches remain visible with an explanation beside their disabled action. Batch and expiry are retained during transfer.

## Transfer behavior

The API requires an authenticated session, CSRF token, and inventory view/edit permission. The destination is enforced as CSSR on the server. The server validates integer quantities, dates, and remarks, locks the item and source lot, then rechecks live reservations and available stock. Expired lots and held destinations cannot be transferred. A stale quantity response updates the form's displayed availability without losing the user's entries.

Both location updates, the transfer document, movement record, source stock card, and destination bin card commit together. A failure rolls them back. Matching CSSR lots are merged using item, batch, expiry, brand, supplier, and unit cost; otherwise a CSSR lot is created. Internal movement leaves the hospital's aggregate `items.current_stock` unchanged. The `storage_transfers` table stores a unique request key so retrying a completed transfer does not move stock twice.

The existing application has both CSSR `item_lots` and separate legacy `cssr_available_stock` records. This module writes CSSR lots, which are used by requisitions. The existing CSSR Stock page now reads both stores without creating a second copy of transferred balances, and includes the new movement records. Existing legacy records are not migrated. The transfer table is included in runtime schema setup, fresh-install SQL, and the existing backup table list; no backup is created by this change.

## Validation

```powershell
C:\xampp\php\php.exe CSSR_INVENTORY/tests/storage-room.test.php
node CSSR_INVENTORY/tests/storage-room-ui.test.cjs
```

The 43 isolated SQLite checks cover room totals, availability, expiry, filtering, source/destination updates, conserved totals, lot identity, ledger entries, duplicate submissions, cross-user replay, insufficient stock, reservations, FEFO, expiry/holds, destination enforcement, and rollback after a ledger failure. Browser checks cover summaries, filtering, preselected lots, invalid/excess quantities, confirmation, stale-stock errors, keyboard focus, and layouts at 1440, 1024, 768, 390, and 320 pixels. Preview transfers never mutate hospital records.

PHP syntax checks and existing inbound, requisition, and inventory data checks passed. Local MySQL port 3306 was unavailable, so authenticated MySQL posting, schema migration, and concurrent database execution remain unverified. No hospital records were changed during testing.
