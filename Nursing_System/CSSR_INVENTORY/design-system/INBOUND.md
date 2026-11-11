# Inbound Supplies

Live route: `pages/inbound.php`. Isolated sample preview: `design-system/inbound.php`.

Uses the shared CSSR shell, navigation, typography, palette, cards, badges, forms, and buttons. The list has date, supplier, procurement type, item, and reference filters, server pagination, and complete delivery details. Each table row represents one receipt item; View opens all items from the same inbound. Existing voids remain labeled Voided, and advance deliveries are labeled For inspection.

The centered desktop form becomes a full-screen mobile sheet. Delivery fields include received date, supplier/donor, DR, PO, procurement type, destination, and remarks. Item lines contain item, quantity, unit cost, expiry, and lot/batch. Storage Room 1 is the default. Multiple batches of one item use separate lines. Totals show distinct items, aggregate quantity, and value before saving. Required and server validation errors appear beside the fields; failed saves retain input. Completion requires confirmation of receipt and acceptance. Unsaved changes are protected on close.

## Persistence

- `inbound_documents` stores draft payloads, owner, version, total, and a unique submission key. Drafts do not write stock records. Staff see their own drafts; Super Admin may access all. Version checks prevent overwriting a draft from a stale window.
- The authenticated API requires inventory view/create and the existing stock management permission, with session CSRF validation. The encoder comes from the session, never from a client-supplied name.
- Completion validates the entire transaction, locks the document and item rows, then posts lots, item balances, receipt lines, receiving inspections, location stock/bin cards, and the established inbound price-list entries in one transaction. Failure rolls back all lines. A retry of a completed submission returns the existing receipt without adding stock again.
- Header references are optional, allowing donations and emergency purchases. Expiry is optional for non-expiring supplies; expired supplies cannot be completed as accepted stock. Quantities must be positive integers, unit cost may be zero, and lot/batch is required for completion. Held lots must be reviewed through the existing inspection workflow.
- Procured displays the established `REGULAR` database value. Donation and Emergency Purchase keep their existing values. The compact-month `INB-YYYYMM-000001` numbering uses the document ID and does not collide with the legacy `INB-YYYY-MM-0001` sequence.
- The manual inbound form does not automatically match or close procurement requests by PO text. The separate procurement-linked receiving workflow retains its locked prices and delivery balances.
- Runtime schema setup and fresh-install SQL include the document table. The existing inventory backup table list includes it; no backup is created by this change.

## Validation

```powershell
C:\xampp\php\php.exe CSSR_INVENTORY/tests/inbound.test.php
node CSSR_INVENTORY/tests/inbound-ui.test.cjs
```

The 39 isolated SQLite checks cover draft persistence, ownership, version conflicts, multiple-item completion, totals, lot merging, ledger routing, non-expiring supplies, validation, rollback on a second-line failure, repeat submission, and list filtering. Browser checks cover the form, drafts, live totals, confirmation, server errors, unsaved changes, keyboard focus, and layouts at 1440, 1024, 768, 390, and 320 pixels. The preview database exists in memory only and does not access hospital records.

PHP syntax and existing dashboard, inventory, item editor, and requisition data checks pass. MySQL port 3306 was unavailable during verification, so live MySQL schema migration, authenticated posting, and concurrent transaction behavior remain unverified. No hospital records were modified for testing.
