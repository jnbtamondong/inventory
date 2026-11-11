# CSSR Stock

Live page: `pages/cssr_available_stock.php`. Sample preview: `design-system/cssr_stock.php`.

Reuses the shared CSSR navigation, header, colors, typography, cards, forms, buttons, and dialogs. Current CSSR Stock is emphasized with large numbers and a tinted table column. The page includes total CSSR stock, low stock, out of stock, fast-moving items, search/status filters, an issuance-month selector, pagination, and CSSR batch/issuance details. The details dialog becomes a full-screen sheet on mobile.

## Balance and scope

- Reads only `item_lots.storage_location='CSSR'` and the existing `cssr_available_stock` records, which already represent stock inside CSSR. It never reads storage-room quantities into this balance and never uses the hospital-wide `items.current_stock` value.
- Current balances are the posted remainder after receipts/transfers into CSSR, issues, returns out, accepted returns into CSSR, opening balances, reversals, and corrections. The page reads those remainders; it does not subtract issuance or returns a second time. Historical transaction coverage is incomplete, so rebuilding stock from transfer history alone would discard direct receipts, openings, and corrections.
- Returns leaving CSSR reduce the balance. Accepted department returns into CSSR increase it, matching the existing Department Returns workflow. Pending returns do not change stock. This is the implementation assumption for the user's unspecified return direction.
- Usable stock excludes held, expired, recalled, and unidentified lots. Reserved quantities remain physically in CSSR until released and stay in Current CSSR Stock. The separate quantity available for new requests subtracts active reservations.
- Earlier CSSR records remain visible once in Current CSSR Stock. Because the requisition release workflow uses lot IDs, those older balances are separately marked as needing batch verification and excluded from the quantity available for new requests. No migration or balance duplication is performed.
- Items with a CSSR stock record remain listed at zero for out-of-stock monitoring. Items recorded only in Storage Room 1 or other rooms are excluded. Low stock compares current CSSR stock against the existing item reorder level.

## Monthly issuance

Uses `stock_out` entries joined to their CSSR source lot and filtered by the selected month. It does not also sum requisition details, which would duplicate posted releases. Monthly Issued Quantity is gross issued quantity; returns are separate movements. Selecting a past month changes issuance figures only, never the current stock balance.

Fast-moving items are the top 20% of items with positive issued quantities in the selected month, with ties included. The definition is shown on the card. No recorded issuance means no fast-moving items.

## Verification

```powershell
C:\xampp\php\php.exe CSSR_INVENTORY/tests/cssr-stock.test.php
node CSSR_INVENTORY/tests/cssr-stock-ui.test.cjs
```

Thirty isolated data checks cover CSSR-only totals, independence from storage-room/hospital totals, low/out/fast counts, monthly boundaries, reservations, expiry, recall, legacy records, filtering, and posted transfer/issue/return changes. Browser checks cover prominent quantities, filters, month selection, CSSR-only batch and issuance details, keyboard focus, and layouts at 1440, 1024, 768, 390, and 320 pixels. The existing 43 storage transfer checks still pass.

PHP and JavaScript syntax checks passed. MySQL port 3306 was unavailable, so live authenticated database verification remains outstanding. Preview data is isolated in memory; no hospital records were changed.
