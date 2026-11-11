# Add New Item / Edit Item modal

Open the [Inventory preview](http://localhost/Nursing_System/CSSR_INVENTORY/design-system/inventory.php) and choose **Add Item** or **Edit**. Both use the same modal component as the operational inventory list. The workspace, sidebar, and header are unchanged.

## Form behavior

- Fields: Item Name, Item Code, Classification, Unit, Item Description, Reorder Level, Storage Location, Procurement Type.
- Required: all except Item Description; manual Item Code is required when Auto-generate is off. Reorder Level accepts whole numbers from 0 to 2,147,483,647. Descriptions allow up to 1,000 characters.
- Classification: Medical Supplies / Medical Instruments. Unit: common inventory units plus existing unit values, so edits can retain established units.
- Storage Location: Storage Room 1 / CSSR. Procurement Type: Procured / Donation / Emergency Purchase.
- New items start with Auto-generate enabled. Edit preloads the current code and fields; a user can explicitly choose to generate a replacement code. Automatic codes are assigned on save, not reserved when opening the modal.
- Save Item remains disabled until required values are present and valid. Inline errors appear after leaving a field, and update as values are corrected. Server validation messages map to the same fields. Network/session errors preserve the form values.
- Desktop: centered modal with an independently scrolling form body. Mobile below 768px: full-screen sheet with a fixed header and action footer, single-column fields, and 16px input text.
- Cancel and Escape close the dialog and return focus to the trigger. While a save is pending, dismissal and resubmission are disabled. A successful save refreshes the filtered list and displays the shared upper-left success toast.

The public preview simulates a successful save and explicitly states that hospital records were not changed. It never sends a save request to the hospital API.

## Implementation

- `includes/item-editor-view.php`: reusable modal markup.
- `assets/css/item-editor.css`: modal-only layout, validation appearance, mobile sheet.
- `assets/js/item-editor.js`: open/prefill, validation, code mode, save state, and response handling.
- `includes/item-editor.php`: shared server validation and transactional persistence.
- `api/item-editor.php`: authenticated POST endpoint with current permissions and CSRF validation. Requires inventory view permission and the existing `can_manage()` rule.

The existing schema initializer adds nullable `items.procurement_type`, also included in the fresh-install SQL. It stores an item's procurement default. Historical receipt types are not rewritten. Inventory filtering can match either this new default or the existing inbound metadata.

Editing updates only the eight submitted metadata fields. It preserves stock quantities, lot locations, images, and other transaction data. Storage Location is the default receiving location; the modal explains that it does not transfer existing stock. New items start active with zero stock. The save handler validates unique codes and retries automatic allocation after a competing unique-code insert; manual duplicates return an Item Code error. Existing images/lots tools and legacy form routes remain available.

## Verification

```powershell
C:\xampp\php\php.exe CSSR_INVENTORY/tests/item-editor-data.test.php
C:\xampp\php\php.exe CSSR_INVENTORY/tests/inventory-list-data.test.php
$env:CSSR_BROWSER_NO_SANDBOX = '1' # Only when required by the restricted test environment
node CSSR_INVENTORY/tests/item-editor-ui.test.cjs
node CSSR_INVENTORY/tests/inventory-list-ui.test.cjs
```

The isolated data checks cover valid and invalid values, manual/automatic codes, duplicates, insert/update behavior, stock/image preservation, missing IDs, and filtering by newly saved procurement metadata. Browser checks cover focus, Save enablement, field errors, prefilled Edit, success toast, simulated server rejection, and desktop/mobile geometry. All database writes in tests use an in-memory sample database.

Live MySQL, concurrent MySQL writes, and authenticated endpoint execution still need verification when the local database service is available. No hospital data was changed during testing.
