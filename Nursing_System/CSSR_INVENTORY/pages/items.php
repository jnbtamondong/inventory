<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin_or_cssr();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && !array_intersect(['new','edit','view_lots','edit_lot','lot_images'], array_keys($_GET))) {
    require __DIR__ . '/../includes/inventory-list-page.php';
    return;
}
require_once __DIR__ . '/../includes/procurement_quantities.php';
$pageTitle = 'Item Masterlist';
$activePage = 'items';

$editItem = null;
$viewLotsItem = null;
$viewLots = [];
$isAddingItem = isset($_GET['new']) && can_manage();
if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM items WHERE id=?');
    $stmt->execute([(int) $_GET['edit']]);
    $editItem = $stmt->fetch();
}
$imageOnly = $editItem && isset($_GET['image_only']);
if (isset($_GET['view_lots'])) {
    $stmt = db()->prepare('SELECT * FROM items WHERE id=?');
    $stmt->execute([(int) $_GET['view_lots']]);
    $viewLotsItem = $stmt->fetch();
    if ($viewLotsItem) {
        $stmt = db()->prepare('SELECT * FROM item_lots WHERE item_id=? ORDER BY CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END, expiry_date, created_at, id');
        $stmt->execute([(int) $viewLotsItem['id']]);
        $viewLots = $stmt->fetchAll();
    }
}
$lotImageGalleries = [];
if ($viewLots) {
    $lotIds = array_map(static fn($lot) => (int) $lot['id'], $viewLots);
    $placeholders = implode(',', array_fill(0, count($lotIds), '?'));
    foreach (db_all("SELECT item_lot_id,image_name FROM item_lot_images WHERE item_lot_id IN ($placeholders) ORDER BY item_lot_id,sort_order,id", $lotIds) as $lotImageRow) {
        $lotImageGalleries[(int) $lotImageRow['item_lot_id']][] = '../uploads/items/' . $lotImageRow['image_name'];
    }
}
$manageLot = null;
$manageLotImages = [];
$editLot = null;
if (isset($_GET['edit_lot']) && can_manage()) {
    $editLot = db_one('SELECT l.*,i.item_code,i.item_description FROM item_lots l JOIN items i ON i.id=l.item_id WHERE l.id=?', [(int) $_GET['edit_lot']]);
}
if (isset($_GET['lot_images']) && can_manage()) {
    $manageLot = db_one('SELECT l.*,i.item_code,i.item_description FROM item_lots l JOIN items i ON i.id=l.item_id WHERE l.id=?', [(int) $_GET['lot_images']]);
    if ($manageLot) $manageLotImages = db_all('SELECT id,image_name,sort_order FROM item_lot_images WHERE item_lot_id=? ORDER BY sort_order,id', [(int) $manageLot['id']]);
}

function generate_item_code(): string
{
    $latest = db_value("SELECT item_code FROM items WHERE item_code REGEXP '^CSSR-[0-9]+$' ORDER BY CAST(SUBSTRING(item_code, 6) AS UNSIGNED) DESC LIMIT 1");
    $next = 1;
    if ($latest) {
        $next = ((int) substr((string) $latest, 5)) + 1;
    } else {
        $maxId = (int) db_value('SELECT COALESCE(MAX(id),0) FROM items');
        $next = $maxId + 1;
    }
    return 'CSSR-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
}

function item_search_text(string $value): string
{
    $value = strtoupper($value);
    $value = preg_replace('/[^A-Z0-9]+/', ' ', $value) ?? $value;
    $value = preg_replace('/\bCATH\b/', ' CATHETER ', $value) ?? $value;
    $value = preg_replace('/\bCAN\b/', ' CATHETER ', $value) ?? $value;
    $value = preg_replace('/\bG\s*(\d+)\b/', ' GAUGE $1 ', $value) ?? $value;
    return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
}

function item_matches_search(array $item, string $query): bool
{
    $queryText = item_search_text($query);
    if ($queryText === '') {
        return true;
    }

    $haystack = item_search_text(implode(' ', [
        $item['item_code'] ?? '',
        $item['item_name'] ?? '',
        $item['item_description'] ?? '',
        $item['lot_number'] ?? '',
        $item['lot_numbers'] ?? '',
        $item['category'] ?? '',
        $item['supplier'] ?? '',
        $item['lot_suppliers'] ?? '',
        $item['bin_location'] ?? '',
        $item['storage_locations'] ?? '',
    ]));

    $haystackTokens = explode(' ', $haystack);
    foreach (explode(' ', $queryText) as $token) {
        $matched = $token === '' || (bool) array_filter($haystackTokens, fn($haystackToken) => str_starts_with($haystackToken, $token));
        if (!$matched) {
            return false;
        }
    }
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && can_manage()) {
    try {
        if (($_POST['action'] ?? '') === 'update_lot') {
            $lotId = (int) ($_POST['lot_id'] ?? 0);
            $lot = $lotId ? db_one('SELECT * FROM item_lots WHERE id=?', [$lotId]) : null;
            if (!$lot) throw new RuntimeException('Lot record not found.');
            $lotNumber = strtoupper(trim((string) ($_POST['lot_number'] ?? '')));
            $expiryDate = trim((string) ($_POST['expiry_date'] ?? ''));
            $brand = strtoupper(trim((string) ($_POST['brand'] ?? '')));
            $supplier = strtoupper(trim((string) ($_POST['supplier'] ?? '')));
            $storageLocation = strtoupper(trim((string) ($_POST['storage_location'] ?? '')));
            $unitPriceInput = trim((string) ($_POST['unit_price'] ?? ''));
            if ($unitPriceInput !== '' && !is_numeric($unitPriceInput)) throw new RuntimeException('Enter a valid unit price.');
            $unitPrice = $unitPriceInput === '' ? null : (float) $unitPriceInput;
            $status = strtoupper(trim((string) ($_POST['status'] ?? 'AVAILABLE')));
            if ($lotNumber === '') throw new RuntimeException('Enter the lot number.');
            if ($expiryDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiryDate)) throw new RuntimeException('Enter a valid expiration date.');
            if (!in_array($storageLocation, ['CSSR','STORAGE ROOM 1','STORAGE ROOM 2','STORAGE ROOM 3'], true)) throw new RuntimeException('Choose a valid storage location.');
            if ($unitPrice !== null && $unitPrice < 0) throw new RuntimeException('Unit price cannot be negative.');
            if (!in_array($status, ['AVAILABLE','DEPLETED','QUARANTINED','EXPIRED'], true)) throw new RuntimeException('Choose a valid lot status.');
            if ($lotNumber !== (string) $lot['lot_number']) {
                $used = (int) db_value('SELECT (SELECT COUNT(*) FROM stock_in WHERE item_lot_id=?)+(SELECT COUNT(*) FROM stock_out WHERE item_lot_id=?)+(SELECT COUNT(*) FROM item_request_details WHERE approved_item_lot_id=?)', [$lotId,$lotId,$lotId]);
                if ($used > 0) throw new RuntimeException('The lot number cannot be changed because this lot already has transaction history. Other lot details can still be edited.');
            }
            db_statement('UPDATE item_lots SET lot_number=?,expiry_date=?,brand=?,supplier=?,storage_location=?,unit_price=?,status=? WHERE id=?', [$lotNumber,$expiryDate ?: null,$brand ?: null,$supplier ?: null,$storageLocation,$unitPrice,$status,$lotId]);
            log_activity('UPDATE', 'Item Lots', 'Updated lot ' . $lotNumber);
            set_flash('success', 'Lot details updated.');
            redirect('items.php?view_lots=' . (int) $lot['item_id']);
        }

        if (($_POST['action'] ?? '') === 'deactivate_item') {
            $id = (int) ($_POST['id'] ?? 0);
            $item = $id ? db_one('SELECT * FROM items WHERE id=?', [$id]) : null;
            if (!$item) {
                throw new RuntimeException('Item record not found.');
            }
            db_statement("UPDATE items SET status='Inactive' WHERE id=?", [$id]);
            log_activity('DEACTIVATE', 'Items', 'Deactivated item ' . $item['item_code']);
            set_flash('success', 'Item deactivated.');
            redirect('items.php');
        }

        if (($_POST['action'] ?? '') === 'reactivate_item') {
            $id = (int) ($_POST['id'] ?? 0);
            $item = $id ? db_one('SELECT * FROM items WHERE id=?', [$id]) : null;
            if (!$item) {
                throw new RuntimeException('Item record not found.');
            }
            db_statement("UPDATE items SET status='Active' WHERE id=?", [$id]);
            log_activity('REACTIVATE', 'Items', 'Reactivated item ' . $item['item_code']);
            set_flash('success', 'Item reactivated.');
            redirect('items.php');
        }

        if (($_POST['action'] ?? '') === 'delete_item') {
            $deleteId = (int) ($_POST['id'] ?? 0);
            db()->beginTransaction();
            $item = db_one('SELECT * FROM items WHERE id=? FOR UPDATE', [$deleteId]);
            if (!$item) {
                throw new RuntimeException('Item record not found.');
            }
            $historyChecks = [
                'stock_in' => 'stock receiving',
                'stock_out' => 'stock issuance',
                'item_request_details' => 'RIS request',
                'storage_request_details' => 'storage request',
                'bin_cards' => 'bin card',
                'stock_cards' => 'stock card',
                'stock_adjustments' => 'stock adjustment',
                'cssr_available_stock' => 'CSSR available stock',
                'stock_withdrawals' => 'stock withdrawal',
                'stock_location_movements' => 'location movement',
            ];
            $usedIn = [];
            foreach ($historyChecks as $table => $label) {
                $count = (int) db_value("SELECT COUNT(*) FROM {$table} WHERE item_id=?", [$deleteId]);
                if ($count > 0) {
                    $usedIn[] = $label;
                }
            }
            if ($usedIn) {
                throw new RuntimeException('This item has transaction history (' . implode(', ', $usedIn) . '). Deactivate it instead of deleting.');
            }
            $lotImages = db_all('SELECT lot_image FROM item_lots WHERE item_id=? AND lot_image IS NOT NULL AND lot_image<>""', [$deleteId]);
            $galleryImages = db_all('SELECT image_name FROM item_images WHERE item_id=?', [$deleteId]);
            db_statement('DELETE FROM item_lots WHERE item_id=?', [$deleteId]);
            db_statement('DELETE FROM items WHERE id=?', [$deleteId]);
            db()->commit();
            if (!empty($item['item_image']) && is_file(__DIR__ . '/../uploads/items/' . $item['item_image'])) {
                @unlink(__DIR__ . '/../uploads/items/' . $item['item_image']);
            }
            foreach ($lotImages as $lotImage) {
                if (!empty($lotImage['lot_image']) && is_file(__DIR__ . '/../uploads/items/' . $lotImage['lot_image'])) {
                    @unlink(__DIR__ . '/../uploads/items/' . $lotImage['lot_image']);
                }
            }
            foreach ($galleryImages as $galleryImage) {
                if (!empty($galleryImage['image_name']) && is_file(__DIR__ . '/../uploads/items/' . $galleryImage['image_name'])) {
                    @unlink(__DIR__ . '/../uploads/items/' . $galleryImage['image_name']);
                }
            }
            log_activity('DELETE', 'Items', 'Deleted test item ' . $item['item_code']);
            set_flash('success', 'Item deleted.');
            redirect('items.php');
        }

        if (($_POST['action'] ?? '') === 'upload_lot_image') {
            $lotId = (int) ($_POST['lot_id'] ?? 0);
            $lot = $lotId ? db_one('SELECT l.*, i.item_code FROM item_lots l JOIN items i ON i.id=l.item_id WHERE l.id=?', [$lotId]) : null;
            if (!$lot) {
                throw new RuntimeException('Lot record not found.');
            }
            $uploads = $_FILES['lot_images'] ?? [];
            $names = $uploads['name'] ?? [];
            if (!is_array($names) || count(array_filter($names)) === 0) throw new RuntimeException('Choose one or more pictures for this lot.');
            $existingCount = (int) db_value('SELECT COUNT(*) FROM item_lot_images WHERE item_lot_id=?', [$lotId]);
            $selectedCount = count(array_filter($names));
            if ($existingCount + $selectedCount > 5) throw new RuntimeException('A lot can have a maximum of 5 pictures.');
            $nextOrder = (int) db_value('SELECT COALESCE(MAX(sort_order),-1)+1 FROM item_lot_images WHERE item_lot_id=?', [$lotId]);
            $added = 0;
            foreach ($names as $index => $name) {
                if ($name === '') continue;
                $file = ['name'=>$name,'type'=>$uploads['type'][$index] ?? '','tmp_name'=>$uploads['tmp_name'][$index] ?? '','error'=>$uploads['error'][$index] ?? UPLOAD_ERR_NO_FILE,'size'=>$uploads['size'][$index] ?? 0];
                $lotImage = upload_item_image($file);
                db_statement('INSERT INTO item_lot_images(item_lot_id,image_name,sort_order) VALUES(?,?,?)', [$lotId,$lotImage,$nextOrder++]);
                if (empty($lot['lot_image'])) {
                    db_statement('UPDATE item_lots SET lot_image=? WHERE id=?', [$lotImage,$lotId]);
                    $lot['lot_image'] = $lotImage;
                }
                $added++;
            }
            log_activity('UPDATE', 'Item Lots', 'Uploaded ' . $added . ' picture(s) for ' . $lot['item_code'] . ' lot ' . $lot['lot_number']);
            set_flash('success', $added . ' lot picture(s) uploaded.');
            redirect('items.php?lot_images=' . $lotId);
        }
        if (($_POST['action'] ?? '') === 'set_primary_lot_image') {
            $lotId = (int) ($_POST['lot_id'] ?? 0);$imageId = (int) ($_POST['image_id'] ?? 0);
            $imageRow = db_one('SELECT g.*,l.item_id FROM item_lot_images g JOIN item_lots l ON l.id=g.item_lot_id WHERE g.id=? AND g.item_lot_id=?', [$imageId,$lotId]);
            if (!$imageRow) throw new RuntimeException('Lot picture not found.');
            db()->beginTransaction();
            db_statement('UPDATE item_lot_images SET sort_order=sort_order+1 WHERE item_lot_id=?', [$lotId]);
            db_statement('UPDATE item_lot_images SET sort_order=0 WHERE id=?', [$imageId]);
            db_statement('UPDATE item_lots SET lot_image=? WHERE id=?', [$imageRow['image_name'],$lotId]);
            db()->commit();
            set_flash('success','Primary lot picture updated.');
            redirect('items.php?lot_images='.$lotId);
        }
        if (($_POST['action'] ?? '') === 'remove_lot_image') {
            $lotId = (int) ($_POST['lot_id'] ?? 0);$imageId = (int) ($_POST['image_id'] ?? 0);
            $imageRow = db_one('SELECT g.*,l.lot_image,l.item_id FROM item_lot_images g JOIN item_lots l ON l.id=g.item_lot_id WHERE g.id=? AND g.item_lot_id=?', [$imageId,$lotId]);
            if (!$imageRow) throw new RuntimeException('Lot picture not found.');
            db()->beginTransaction();db_statement('DELETE FROM item_lot_images WHERE id=?',[$imageId]);
            if ($imageRow['lot_image'] === $imageRow['image_name']) {
                $replacement=db_one('SELECT image_name FROM item_lot_images WHERE item_lot_id=? ORDER BY sort_order,id LIMIT 1',[$lotId]);
                db_statement('UPDATE item_lots SET lot_image=? WHERE id=?',[$replacement['image_name']??null,$lotId]);
            }
            db()->commit();$path=__DIR__.'/../uploads/items/'.$imageRow['image_name'];if(is_file($path))@unlink($path);
            set_flash('success','Lot picture removed.');redirect('items.php?lot_images='.$lotId);
        }
        if (($_POST['action'] ?? '') === 'set_primary_item_image') {
            $id = (int) ($_POST['id'] ?? 0);
            $imageId = (int) ($_POST['image_id'] ?? 0);
            $imageRow = db_one('SELECT * FROM item_images WHERE id=? AND item_id=?', [$imageId, $id]);
            if (!$imageRow) throw new RuntimeException('Item picture not found.');
            db()->beginTransaction();
            db_statement('UPDATE item_images SET sort_order=sort_order+1 WHERE item_id=?', [$id]);
            db_statement('UPDATE item_images SET sort_order=0 WHERE id=?', [$imageId]);
            db_statement('UPDATE items SET item_image=? WHERE id=?', [$imageRow['image_name'], $id]);
            db()->commit();
            log_activity('UPDATE', 'Items', 'Changed primary picture for item ID ' . $id);
            set_flash('success', 'Primary item picture updated.');
            redirect('items.php?edit=' . $id . '&image_only=1');
        }
        if (($_POST['action'] ?? '') === 'remove_item_image') {
            $id = (int) ($_POST['id'] ?? 0);
            $imageId = (int) ($_POST['image_id'] ?? 0);
            $imageRow = db_one('SELECT g.*,i.item_image FROM item_images g JOIN items i ON i.id=g.item_id WHERE g.id=? AND g.item_id=?', [$imageId, $id]);
            if (!$imageRow) throw new RuntimeException('Item picture not found.');
            db()->beginTransaction();
            db_statement('DELETE FROM item_images WHERE id=?', [$imageId]);
            if ($imageRow['item_image'] === $imageRow['image_name']) {
                $replacement = db_one('SELECT image_name FROM item_images WHERE item_id=? ORDER BY sort_order,id LIMIT 1', [$id]);
                db_statement('UPDATE items SET item_image=? WHERE id=?', [$replacement['image_name'] ?? null, $id]);
            }
            db()->commit();
            $path = __DIR__ . '/../uploads/items/' . $imageRow['image_name'];
            if (is_file($path)) @unlink($path);
            log_activity('UPDATE', 'Items', 'Removed a picture from item ID ' . $id);
            set_flash('success', 'Item picture removed.');
            redirect('items.php?edit=' . $id . '&image_only=1');
        }
        if (($_POST['action'] ?? '') === 'upload_item_image') {
            $id = (int) ($_POST['id'] ?? 0);
            $existingItem = $id ? db_one('SELECT * FROM items WHERE id=?', [$id]) : null;
            if (!$existingItem) {
                throw new RuntimeException('Item record not found.');
            }
            $uploads = $_FILES['item_images'] ?? [];
            $names = $uploads['name'] ?? [];
            if (!is_array($names) || count(array_filter($names)) === 0) {
                throw new RuntimeException('Choose one or more item pictures.');
            }
            $existingCount = (int) db_value('SELECT COUNT(*) FROM item_images WHERE item_id=?', [$id]);
            $selectedCount = count(array_filter($names));
            if ($existingCount + $selectedCount > 5) {
                throw new RuntimeException('An item can have a maximum of 5 pictures. Remove an existing picture or select fewer files.');
            }
            $nextOrder = (int) db_value('SELECT COALESCE(MAX(sort_order),-1)+1 FROM item_images WHERE item_id=?', [$id]);
            $added = 0;
            foreach ($names as $index => $name) {
                if ($name === '') continue;
                $file = ['name'=>$name, 'type'=>$uploads['type'][$index] ?? '', 'tmp_name'=>$uploads['tmp_name'][$index] ?? '', 'error'=>$uploads['error'][$index] ?? UPLOAD_ERR_NO_FILE, 'size'=>$uploads['size'][$index] ?? 0];
                $image = upload_item_image($file);
                db_statement('INSERT INTO item_images (item_id,image_name,sort_order) VALUES (?,?,?)', [$id,$image,$nextOrder++]);
                if (empty($existingItem['item_image'])) {
                    db_statement('UPDATE items SET item_image=? WHERE id=?', [$image,$id]);
                    $existingItem['item_image'] = $image;
                }
                $added++;
            }
            log_activity('UPDATE', 'Items', 'Added ' . $added . ' picture(s) for ' . $existingItem['item_code']);
            set_flash('success', $added . ' item picture(s) added.');
            redirect('items.php');
        }

        $id = (int) ($_POST['id'] ?? 0);
        $oldImage = null;
        $existingItem = null;
        if ($id) {
            $stmt = db()->prepare('SELECT * FROM items WHERE id=?');
            $stmt->execute([$id]);
            $existingItem = $stmt->fetch();
            $oldImage = $existingItem['item_image'] ?? null;
        }
        $pictureNotApplicable = ($_POST['item_image_na'] ?? '') === '1';
        $itemCode = $id ? trim($_POST['item_code'] ?? '') : generate_item_code();
        $barcode = trim((string) ($_POST['barcode'] ?? '')) ?: null;
        $classification = strtoupper(trim($_POST['category'] ?? ''));
        if (!in_array($classification, ['MEDICAL SUPPLIES', 'MEDICAL INSTRUMENTS'], true)) {
            throw new RuntimeException('Select either Medical Supplies or Medical Instruments for the classification.');
        }
        $storageLocation = $id
            ? strtoupper((string) ($existingItem['bin_location'] ?? 'CSSR'))
            : strtoupper(trim($_POST['bin_location'] ?? ''));
        $allowedStorageLocations = ['CSSR', 'STORAGE ROOM 1', 'STORAGE ROOM 2', 'STORAGE ROOM 3'];
        if ($storageLocation === '') {
            $storageLocation = 'CSSR';
        }
        if (!in_array($storageLocation, $allowedStorageLocations, true)) {
            throw new RuntimeException('Select a valid storage location.');
        }
        $itemDescription = trim($_POST['item_description'] ?? '');
        if (mb_strlen($itemDescription) > 1000) {
            throw new RuntimeException('Item description must be 1,000 characters or fewer.');
        }
        $itemName = trim($_POST['item_name'] ?? '');
        if ($itemName === '' || mb_strlen($itemName) > 255) {
            throw new RuntimeException('Enter an item name of up to 255 characters.');
        }
        $unit = trim($_POST['unit'] ?? '');
        $brand = $id ? (string) ($existingItem['brand'] ?? '') : '';
        $supplier = $id ? (string) ($existingItem['supplier'] ?? '') : '';
        $lotNumber = $id ? (string) ($existingItem['lot_number'] ?? '') : '';
        $expiryDate = $id ? ($existingItem['expiry_date'] ?? null) : null;
        $minimumStock = (int) ($_POST['minimum_stock'] ?? 0);
        $currentStock = $id ? (int) ($existingItem['current_stock'] ?? 0) : 0;
        $status = $id ? (string) ($existingItem['status'] ?? 'Active') : 'Active';
        if ($itemDescription === '' || $unit === '') {
            throw new RuntimeException('Complete all required item fields before saving.');
        }
        $newImages = [];
        if (!$pictureNotApplicable) {
            $uploads = $_FILES['item_images'] ?? [];
            $names = $uploads['name'] ?? [];
            if (!is_array($names)) $names = $names !== '' ? [$names] : [];
            $selectedCount = count(array_filter($names));
            $existingCount = $id ? (int) db_value('SELECT COUNT(*) FROM item_images WHERE item_id=?', [$id]) : 0;
            if ($existingCount + $selectedCount > 5) {
                throw new RuntimeException('An item can have a maximum of 5 pictures. Remove an existing picture or select fewer files.');
            }
            foreach ($names as $index => $name) {
                if ($name === '') continue;
                $file = [
                    'name' => $name,
                    'type' => is_array($uploads['type'] ?? null) ? ($uploads['type'][$index] ?? '') : ($uploads['type'] ?? ''),
                    'tmp_name' => is_array($uploads['tmp_name'] ?? null) ? ($uploads['tmp_name'][$index] ?? '') : ($uploads['tmp_name'] ?? ''),
                    'error' => is_array($uploads['error'] ?? null) ? ($uploads['error'][$index] ?? UPLOAD_ERR_NO_FILE) : ($uploads['error'] ?? UPLOAD_ERR_NO_FILE),
                    'size' => is_array($uploads['size'] ?? null) ? ($uploads['size'][$index] ?? 0) : ($uploads['size'] ?? 0),
                ];
                $newImages[] = upload_item_image($file);
            }
        }
        $image = $pictureNotApplicable ? null : ($oldImage ?: ($newImages[0] ?? null));
        $data = [
            $itemCode,
            $itemDescription,
            $classification,
            $unit,
            $brand,
            $supplier,
            $lotNumber,
            $expiryDate,
            $storageLocation,
            $minimumStock,
            $currentStock,
            $image,
            $status,
        ];
        if ($id) {
            $stmt = db()->prepare('UPDATE items SET barcode=?, item_code=?, item_description=?, category=?, unit=?, brand=?, supplier=?, lot_number=?, expiry_date=?, bin_location=?, minimum_stock=?, current_stock=?, item_image=?, status=?, item_name=? WHERE id=?');
            $stmt->execute([$barcode, ...$data, $itemName, $id]);
            if ($newImages) {
                $nextOrder = (int) db_value('SELECT COALESCE(MAX(sort_order),-1)+1 FROM item_images WHERE item_id=?', [$id]);
                foreach ($newImages as $newImage) {
                    db_statement('INSERT INTO item_images (item_id,image_name,sort_order) VALUES (?,?,?)', [$id,$newImage,$nextOrder++]);
                }
            }
            log_activity('UPDATE', 'Items', 'Updated item ' . $data[0]);
            set_flash('success', 'Item updated.');
        } else {
            if ($data[10] > 0 && ($data[6] === '' || $data[8] === '')) {
                throw new RuntimeException('A lot number and storage location are required when creating an item with opening stock.');
            }
            $stmt = db()->prepare('INSERT INTO items (barcode,item_code,item_description,category,unit,brand,supplier,lot_number,expiry_date,bin_location,minimum_stock,current_stock,item_image,status,item_name) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$barcode, ...$data, $itemName]);
            $newItemId = (int) db()->lastInsertId();
            foreach ($newImages as $sortOrder => $newImage) {
                db_statement('INSERT INTO item_images (item_id,image_name,sort_order) VALUES (?,?,?)', [$newItemId,$newImage,$sortOrder]);
            }
            if ($data[10] > 0) {
                db()->prepare('INSERT INTO item_lots (item_id,lot_number,expiry_date,supplier,storage_location,quantity_received,quantity_available,status) VALUES (?,?,?,?,?,?,?,"AVAILABLE")')->execute([$newItemId, strtoupper($data[6]), $data[7], strtoupper($data[5]), strtoupper($data[8]), $data[10], $data[10]]);
            }
            log_activity('CREATE', 'Items', 'Added item ' . $data[0]);
            set_flash('success', 'Item added.');
        }
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        set_flash('danger', safe_error_message($e));
    }
    redirect('items.php');
}

$q = trim($_GET['q'] ?? '');
$sql = 'SELECT i.*,
               COALESCE((SELECT SUM(l.quantity_available) FROM item_lots l WHERE l.item_id=i.id),0) AS current_stock,
               COALESCE((SELECT GROUP_CONCAT(DISTINCT l.storage_location ORDER BY l.storage_location SEPARATOR " ") FROM item_lots l WHERE l.item_id=i.id),"") AS storage_locations,
               COALESCE((SELECT GROUP_CONCAT(DISTINCT l.lot_number ORDER BY l.lot_number SEPARATOR " ") FROM item_lots l WHERE l.item_id=i.id),"") AS lot_numbers,
               COALESCE((SELECT GROUP_CONCAT(DISTINCT l.supplier ORDER BY l.supplier SEPARATOR " ") FROM item_lots l WHERE l.item_id=i.id),"") AS lot_suppliers
        FROM items i
        ORDER BY i.item_description';
$stmt = db()->prepare($sql);
$stmt->execute();
$items = $stmt->fetchAll();
$procurementQuantities = item_procurement_quantities();
$itemImageRows = db_all('SELECT item_id,image_name FROM item_images ORDER BY item_id,sort_order,id');
$itemGalleries = [];
foreach ($itemImageRows as $imageRow) {
    $itemGalleries[(int) $imageRow['item_id']][] = '../uploads/items/' . $imageRow['image_name'];
}
$manageImages = $editItem ? db_all('SELECT id,image_name,sort_order FROM item_images WHERE item_id=? ORDER BY sort_order,id', [(int) $editItem['id']]) : [];
if ($q !== '') {
    $items = array_values(array_filter($items, fn($item) => item_matches_search($item, $q)));
}
$suppliers = db_all('SELECT supplier_name, status FROM suppliers ORDER BY supplier_name');
$storageLocations = ['CSSR', 'STORAGE ROOM 1', 'STORAGE ROOM 2', 'STORAGE ROOM 3'];
$lowStockCount = count(array_filter($items, fn($item) => (int) ($item['current_stock'] ?? 0) <= (int) ($item['minimum_stock'] ?? 0)));
$activeCount = count(array_filter($items, fn($item) => ($item['status'] ?? '') === 'Active'));
$inactiveCount = count(array_filter($items, fn($item) => ($item['status'] ?? '') !== 'Active'));
include __DIR__ . '/../includes/header.php';
?>
<section class="metric-row">
    <div class="metric-card"><span>Total Records</span><strong><?= count($items) ?></strong></div>
    <div class="metric-card"><span>Low Stock</span><strong><?= $lowStockCount ?></strong></div>
    <div class="metric-card"><span>Active</span><strong><?= $activeCount ?></strong></div>
    <div class="metric-card"><span>Inactive</span><strong><?= $inactiveCount ?></strong></div>
</section>

<section class="page-panel">
    <?php if (can_manage()): ?>
        <div class="module-actions no-print">
            <a class="btn btn-primary" href="items.php?new=1">Add Item</a>
        </div>
    <?php endif; ?>
    <form class="toolbar no-print" method="get">
        <div class="field"><label class="form-label">Manual Search</label><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Code, item name, description, lot, category, supplier, storage location"></div>
        <button class="btn btn-primary">Search</button><a class="btn btn-light" href="items.php">Reset</a>
    </form>
    <div class="table-responsive item-masterlist-scroll" tabindex="0" aria-label="Item Masterlist. Swipe or scroll sideways to view all columns.">
        <table class="table" id="itemsTable">
            <thead><tr><th>Image</th><th>Code</th><th>Item Name</th><th>Description</th><th>Category</th><th>Total Stock</th><th style="text-align:center" title="Outstanding PR quantity; excludes no-bid items after abstract encoding and quantities already delivered.">Procurement Qty</th><th>Status</th><th class="no-print">Action</th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): $status = stock_status($item); ?>
                <tr>
                    <?php $primaryImage = !empty($item['item_image']) ? '../uploads/items/' . $item['item_image'] : ''; ?>
                    <td><?php if ($primaryImage): ?><img class="image-thumb" onclick="openImage('<?= e($primaryImage) ?>')" src="<?= e($primaryImage) ?>" alt="Primary item picture"><?php else: ?><span class="mini-thumb empty">IMG</span><?php endif; ?></td>
                    <td><a class="table-link" href="item_details.php?id=<?= (int) $item['id'] ?>"><strong><?= e($item['item_code']) ?></strong></a></td>
                    <td><?= e($item['item_name'] ?? '') ?></td>
                    <td><?= e($item['item_description']) ?></td>
                    <td><?= e($item['category']) ?></td>
                    <td><span class="badge <?= $status === 'OUT OF STOCK' ? 'status-out' : ($status === 'CRITICAL' ? 'status-critical' : ($status === 'LOW STOCK' ? 'status-low' : 'status-normal')) ?>"><?= (int) $item['current_stock'] ?></span></td>
                    <td style="text-align:center"><?= (int) ($procurementQuantities[(int) $item['id']] ?? 0) > 0 ? number_format((int) $procurementQuantities[(int) $item['id']]) : '—' ?></td>
                    <td><span class="badge <?= $item['status'] === 'Active' ? 'bg-success' : 'bg-secondary' ?>"><?= e($item['status']) ?></span></td>
                    <td class="no-print">
                        <details class="row-action-menu">
                            <summary class="btn btn-sm btn-primary">Actions</summary>
                            <div class="row-action-list">
                                <a href="?view_lots=<?= (int) $item['id'] ?>">View Lots</a>
                                <?php if (can_manage()): ?>
                                    <a href="?edit=<?= (int) $item['id'] ?>">Edit Item</a>
                                    <a href="?edit=<?= (int) $item['id'] ?>&image_only=1">Add Item Images</a>
                                    <?php if ($item['status'] === 'Active'): ?>
                                        <form method="post" class="m-0" data-confirm-message="Deactivate this item? It will be hidden from current inventory reports and item selection.">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="deactivate_item">
                                            <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                            <button class="danger row-action-button" type="submit">Deactivate</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" class="m-0" data-confirm-message="Reactivate this item? It will return to current inventory reports and item selection.">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="reactivate_item">
                                            <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                            <button class="row-action-button" type="submit">Reactivate</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" class="m-0" data-confirm-message="Delete this item permanently? Use this only for test items with no transactions.">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_item">
                                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                        <button class="danger row-action-button" type="submit">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($viewLotsItem && !$manageLot && !$editLot): ?>
<div class="app-modal open">
    <div class="app-modal-card" style="max-width:1200px">
        <div class="app-modal-head">
            <div><span class="eyebrow">Lot-level Inventory</span><h3><?= e($viewLotsItem['item_code'] . ' - ' . $viewLotsItem['item_description']) ?></h3></div>
            <a class="btn btn-light btn-sm" href="items.php">Close</a>
        </div>
        <div class="app-modal-body">
            <p><strong>Total item balance:</strong> <?= (int) $viewLotsItem['current_stock'] ?>. Zero-balance lots remain visible for transaction history.</p>
            <div class="table-responsive">
                <table class="table lot-inventory-table">
                    <thead><tr><th>Lot Picture</th><th>Lot Number</th><th>Expiration</th><th>Brand</th><th>Supplier</th><th>Unit Price</th><th>Received</th><th>Issued</th><th>Returned</th><th>Remaining</th><th>Storage Location</th><th>Status</th><th class="no-print">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($viewLots as $lot):
                        $lotStatus = (!empty($lot['expiry_date']) && $lot['expiry_date'] < date('Y-m-d'))
                            ? 'EXPIRED'
                            : ((int) $lot['quantity_available'] > 0 ? 'AVAILABLE' : 'OUT OF STOCK');
                    ?>
                        <tr>
                            <td><?php $lotGallery=$lotImageGalleries[(int)$lot['id']]??($lot['lot_image']?['../uploads/items/'.$lot['lot_image']]:[]); ?><?php if ($lotGallery): ?><img class="image-thumb" onclick='openImageGallery(<?= e(json_encode($lotGallery)) ?>,0)' src="<?= e($lotGallery[0]) ?>" alt="<?= e($lot['lot_number']) ?>"><?php else: ?><span class="mini-thumb empty">IMG</span><?php endif; ?></td>
                            <td><strong><?= e($lot['lot_number']) ?></strong></td>
                            <td><?= e($lot['expiry_date'] ?: 'N/A') ?></td>
                            <td><?= e($lot['brand'] ?: 'N/A') ?></td>
                            <td><?= e($lot['supplier'] ?: 'N/A') ?></td>
                            <td><?= $lot['unit_price'] === null ? '' : number_format((float) $lot['unit_price'], 2) ?></td>
                            <td><?= (int) $lot['quantity_received'] ?></td>
                            <td><?= (int) $lot['quantity_issued'] ?></td>
                            <td><?= (int) $lot['quantity_returned'] ?></td>
                            <td><strong><?= (int) $lot['quantity_available'] ?></strong></td>
                            <td><?= e($lot['storage_location'] ?: 'N/A') ?></td>
                            <td><span class="badge <?= $lotStatus === 'AVAILABLE' ? 'bg-success' : ($lotStatus === 'EXPIRED' ? 'bg-danger' : 'bg-secondary') ?>"><?= e($lotStatus) ?></span></td>
                            <td class="no-print">
                                <?php if (can_manage()): ?>
                                    <a class="btn btn-sm btn-light" href="items.php?edit_lot=<?= (int) $lot['id'] ?>">Edit</a>
                                    <a class="btn btn-sm btn-primary" href="items.php?lot_images=<?= (int) $lot['id'] ?>">Add Lot Images</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$viewLots): ?><tr><td colspan="13">No lot records found for this item.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($editLot): ?>
<div class="app-modal open">
    <div class="app-modal-card">
        <div class="app-modal-head"><div><span class="eyebrow">Lot-level Inventory</span><h3>Edit Lot</h3></div><a class="btn btn-light btn-sm" href="items.php?view_lots=<?= (int)$editLot['item_id'] ?>">Close</a></div>
        <form method="post" class="app-modal-body">
            <?= csrf_field() ?><input type="hidden" name="action" value="update_lot"><input type="hidden" name="lot_id" value="<?= (int)$editLot['id'] ?>">
            <div class="form-section"><div class="form-section-title"><h4>Item and Lot Identity</h4><span>Stock quantities are transaction-controlled</span></div><div class="row">
                <div class="col-md-4 mb-2"><label class="form-label">Item Code</label><input class="form-control" value="<?= e($editLot['item_code']) ?>" readonly></div>
                <div class="col-md-4 mb-2"><label class="form-label">Item Description</label><input class="form-control" value="<?= e($editLot['item_description']) ?>" readonly></div>
                <div class="col-md-4 mb-2"><label class="form-label required">Lot Number</label><input class="form-control text-uppercase" name="lot_number" value="<?= e($editLot['lot_number']) ?>" required></div>
            </div></div>
            <div class="form-section"><div class="form-section-title"><h4>Lot Details</h4><span>Edit identification and storage information</span></div><div class="row">
                <div class="col-md-3 mb-2"><label class="form-label">Expiration Date</label><input class="form-control" type="date" name="expiry_date" value="<?= e($editLot['expiry_date'] ?? '') ?>"></div>
                <div class="col-md-3 mb-2"><label class="form-label">Brand</label><input class="form-control text-uppercase" name="brand" value="<?= e($editLot['brand'] ?? '') ?>"></div>
                <div class="col-md-3 mb-2"><label class="form-label">Supplier</label><select class="form-select" name="supplier"><option value="">Select supplier</option><?php foreach($suppliers as $supplier): $supplierName=(string)$supplier['supplier_name']; ?><option value="<?= e($supplierName) ?>" <?= strcasecmp((string)($editLot['supplier']??''),$supplierName)===0?'selected':'' ?>><?= e($supplierName) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3 mb-2"><label class="form-label required">Storage Location</label><select class="form-select" name="storage_location" required><?php foreach($storageLocations as $location): ?><option value="<?= e($location) ?>" <?= strcasecmp((string)($editLot['storage_location']??''),$location)===0?'selected':'' ?>><?= e($location) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3 mb-2"><label class="form-label">Unit Price</label><input class="form-control" type="number" min="0" step="0.01" name="unit_price" value="<?= e($editLot['unit_price'] ?? '') ?>"></div>
                <div class="col-md-3 mb-2"><label class="form-label required">Status</label><select class="form-select" name="status" required><?php foreach(['AVAILABLE'=>'AVAILABLE','DEPLETED'=>'OUT OF STOCK','QUARANTINED'=>'QUARANTINED','EXPIRED'=>'EXPIRED'] as $statusValue=>$statusLabel): ?><option value="<?= e($statusValue) ?>" <?= strtoupper((string)$editLot['status'])===$statusValue?'selected':'' ?>><?= e($statusLabel) ?></option><?php endforeach; ?></select></div>
            </div></div>
            <div class="modal-form-actions"><a class="btn btn-light" href="items.php?view_lots=<?= (int)$editLot['item_id'] ?>">Cancel</a><button class="btn btn-primary" type="submit">Save Lot Changes</button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($manageLot): ?>
<div class="app-modal open">
    <div class="app-modal-card">
        <div class="app-modal-head"><div><span class="eyebrow">Lot Image Gallery</span><h3>Add Lot Images</h3></div><a class="btn btn-light btn-sm" href="items.php?view_lots=<?= (int)$manageLot['item_id'] ?>">Close</a></div>
        <div class="app-modal-body item-gallery-manager">
            <div class="form-section-title"><h4>Current Lot Pictures</h4><span><?= count($manageLotImages) ?> of 5 pictures</span></div>
            <div class="item-gallery-manage-grid">
                <?php foreach($manageLotImages as $lotManagedImage):$isLotPrimary=($manageLot['lot_image']??'')===$lotManagedImage['image_name']; ?>
                <article class="item-gallery-manage-card"><img src="../uploads/items/<?= e($lotManagedImage['image_name']) ?>" alt="Lot picture"><strong><?= $isLotPrimary?'Primary Lot Image':'Additional Lot Image' ?></strong><div class="actions">
                    <?php if(!$isLotPrimary): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="set_primary_lot_image"><input type="hidden" name="lot_id" value="<?= (int)$manageLot['id'] ?>"><input type="hidden" name="image_id" value="<?= (int)$lotManagedImage['id'] ?>"><button class="btn btn-sm btn-light">Set as Primary</button></form><?php endif; ?>
                    <form method="post" data-confirm-message="Remove this lot picture?"><?= csrf_field() ?><input type="hidden" name="action" value="remove_lot_image"><input type="hidden" name="lot_id" value="<?= (int)$manageLot['id'] ?>"><input type="hidden" name="image_id" value="<?= (int)$lotManagedImage['id'] ?>"><button class="btn btn-sm btn-danger">Remove</button></form>
                </div></article>
                <?php endforeach; ?>
                <?php if(!$manageLotImages): ?><p class="text-muted">No lot pictures uploaded.</p><?php endif; ?>
            </div>
        </div>
        <form method="post" enctype="multipart/form-data" class="app-modal-body">
            <?= csrf_field() ?><input type="hidden" name="action" value="upload_lot_image"><input type="hidden" name="lot_id" value="<?= (int)$manageLot['id'] ?>">
            <div class="form-section"><div class="form-section-title"><h4>Lot Identity</h4><span>Pictures apply only to this exact lot</span></div><div class="row"><div class="col-md-4 mb-2"><label class="form-label">Item Code</label><input class="form-control" value="<?= e($manageLot['item_code']) ?>" readonly></div><div class="col-md-4 mb-2"><label class="form-label">Item Description</label><input class="form-control" value="<?= e($manageLot['item_description']) ?>" readonly></div><div class="col-md-4 mb-2"><label class="form-label">Lot Number</label><input class="form-control" value="<?= e($manageLot['lot_number']) ?>" readonly></div></div></div>
            <div class="form-section"><div class="form-section-title"><h4>Actual Lot Pictures</h4><span>Maximum 5 pictures; JPG, PNG, or WEBP up to 2MB each</span></div><div class="row"><div class="col-md-6 mb-2"><label class="form-label required">Add Lot Images</label><input class="form-control" id="lotImageInput" type="file" name="lot_images[]" accept=".jpg,.jpeg,.png,.webp" multiple required></div><div class="col-md-6 mb-2"><label class="form-label">Preview</label><div class="item-image-selection-preview" id="lotImageSelectionPreview"></div></div></div></div>
            <div class="modal-form-actions"><a class="btn btn-light" href="items.php?view_lots=<?= (int)$manageLot['item_id'] ?>">Cancel</a><button class="btn btn-primary">Add Selected Images</button></div>
        </form>
    </div>
</div>
<script>(function(){const input=document.getElementById('lotImageInput'),preview=document.getElementById('lotImageSelectionPreview'),remaining=<?= max(0,5-count($manageLotImages)) ?>;if(!input||!preview)return;input.addEventListener('change',function(){preview.innerHTML='';if(this.files.length>remaining){alert('You can add only '+remaining+' more lot picture(s).');this.value='';return;}Array.from(this.files).forEach((file,index)=>{const card=document.createElement('div'),img=document.createElement('img'),label=document.createElement('small');img.src=URL.createObjectURL(file);label.textContent=(<?= empty($manageLotImages)?'true':'false' ?>&&index===0?'Primary: ':'Additional: ')+file.name;card.append(img,label);preview.appendChild(card);});});})();</script>
<?php endif; ?>

<?php if (can_manage()): ?>
<div id="itemModal" class="app-modal <?= ($editItem || $isAddingItem) ? 'open' : '' ?>">
    <div class="app-modal-card">
        <div class="app-modal-head">
            <div><span class="eyebrow">Inventory Master Data</span><h3><?= $imageOnly ? 'Upload Item Image' : ($editItem ? 'Edit Item' : 'Add Item') ?></h3></div>
            <a class="btn btn-light btn-sm" href="items.php">Close</a>
        </div>
        <?php if ($imageOnly): ?>
        <div class="app-modal-body item-gallery-manager">
            <div class="form-section-title"><h4>Current Item Pictures</h4><span><?= count($manageImages) ?> of 5 pictures</span></div>
            <div class="item-gallery-manage-grid">
                <?php foreach ($manageImages as $managedImage): $isPrimary = ($editItem['item_image'] ?? '') === $managedImage['image_name']; ?>
                    <article class="item-gallery-manage-card">
                        <img src="../uploads/items/<?= e($managedImage['image_name']) ?>" alt="Item picture">
                        <strong><?= $isPrimary ? 'Primary Image' : 'Additional Image' ?></strong>
                        <div class="actions">
                            <?php if (!$isPrimary): ?>
                            <form method="post">
                                <?= csrf_field() ?><input type="hidden" name="action" value="set_primary_item_image"><input type="hidden" name="id" value="<?= (int) $editItem['id'] ?>"><input type="hidden" name="image_id" value="<?= (int) $managedImage['id'] ?>">
                                <button class="btn btn-sm btn-light" type="submit">Set as Primary</button>
                            </form>
                            <?php endif; ?>
                            <form method="post" data-confirm-message="Remove this item picture?">
                                <?= csrf_field() ?><input type="hidden" name="action" value="remove_item_image"><input type="hidden" name="id" value="<?= (int) $editItem['id'] ?>"><input type="hidden" name="image_id" value="<?= (int) $managedImage['id'] ?>">
                                <button class="btn btn-sm btn-danger" type="submit">Remove</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
                <?php if (!$manageImages): ?><p class="text-muted">No item pictures uploaded.</p><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="app-modal-body">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= e($editItem['id'] ?? '') ?>">
            <?php if ($imageOnly): ?>
                <input type="hidden" name="action" value="upload_item_image">
                <div class="form-section">
                    <div class="form-section-title"><h4>Item Identity</h4><span>Add optional gallery pictures</span></div>
                    <div class="row">
                        <div class="col-md-4 mb-2"><label class="form-label">Item Code</label><input class="form-control" value="<?= e($editItem['item_code']) ?>" readonly></div>
                        <div class="col-md-8 mb-2"><label class="form-label">Item Description</label><input class="form-control" value="<?= e($editItem['item_description']) ?>" readonly></div>
                    </div>
                </div>
            <?php else: ?>
            <div class="form-section">
                <div class="form-section-title"><h4>Item Identity</h4><span>Required master data</span></div>
                <div class="row">
                    <?php if ($editItem): ?><div class="col-md-3 mb-2"><label class="form-label">Item Code</label><input class="form-control" name="item_code" value="<?= e($editItem['item_code']) ?>" readonly></div><?php else: ?><div class="col-md-3 mb-2"><label class="form-label">Item Code</label><input class="form-control" value="Auto-generated after saving" readonly></div><?php endif; ?>
                    <div class="col-md-3 mb-2"><label class="form-label">Barcode / QR Value</label><input class="form-control" name="barcode" value="<?= e($editItem['barcode'] ?? '') ?>" placeholder="Scan or enter unique code"></div>
                    <div class="col-md-3 mb-2"><label class="form-label required">Item Name</label><input class="form-control" name="item_name" value="<?= e($editItem['item_name'] ?? '') ?>" maxlength="255" required></div>
                    <div class="col-md-6 mb-2"><label class="form-label required" for="item-description">Item Description</label><textarea class="form-control" id="item-description" name="item_description" rows="3" maxlength="1000" aria-describedby="item-description-help" required><?= e($editItem['item_description'] ?? '') ?></textarea><small id="item-description-help" class="text-muted">Maximum 1,000 characters.</small></div>
                    <div class="col-md-3 mb-2"><label class="form-label required">Category</label><select class="form-select" name="category" required><option value="">Select category</option><option value="MEDICAL SUPPLIES" <?= (strtoupper($editItem['category'] ?? '') === 'MEDICAL SUPPLIES') ? 'selected' : '' ?>>MEDICAL SUPPLIES</option><option value="MEDICAL INSTRUMENTS" <?= (strtoupper($editItem['category'] ?? '') === 'MEDICAL INSTRUMENTS') ? 'selected' : '' ?>>MEDICAL INSTRUMENTS</option></select></div>
                    <div class="col-md-3 mb-2"><label class="form-label required">Unit</label><select class="form-select" name="unit" required><?php foreach (['piece','bottle','gallon','pack','pair','box','roll','canister'] as $unit): ?><option value="<?= e($unit) ?>" <?= (($editItem['unit'] ?? '') === $unit) ? 'selected' : '' ?>><?= e(ucfirst($unit)) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3 mb-2"><label class="form-label required">Minimum Stock</label><input class="form-control" type="number" min="0" name="minimum_stock" value="<?= e($editItem['minimum_stock'] ?? '0') ?>" required></div>
                    <div class="col-md-3 mb-2"><label class="form-label required">Current Stock</label><input class="form-control" type="number" min="0" name="current_stock" value="<?= e($editItem['current_stock'] ?? '0') ?>" readonly required></div>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($imageOnly): ?>
            <?php if (count($manageImages) >= 5): ?>
            <div class="alert alert-info"><strong>Maximum of 5 pictures reached.</strong> Remove an existing picture before adding another.</div>
            <?php else: ?>
            <div class="form-section">
                <div class="form-section-title"><h4>Actual Item Pictures</h4><span>Maximum 5 pictures; JPG, PNG, or WEBP up to 2MB each</span></div>
                <div class="row align-items-start">
                    <div class="col-md-6 mb-2">
                        <label class="form-label required"><?= $imageOnly ? 'Add Additional Pictures' : 'Primary and Additional Pictures' ?></label>
                        <input id="itemImageInput" class="form-control" type="file" name="item_images[]" accept=".jpg,.jpeg,.png,.webp" title="Upload item pictures" multiple <?= $imageOnly ? 'required' : (empty($editItem['item_image']) ? 'required' : '') ?>>
                        <small class="form-text">The first picture becomes the primary image when the item has no existing picture.</small>
                        <label class="form-check mt-2" <?= $imageOnly ? 'hidden' : '' ?>>
                            <input class="form-check-input" id="itemImageNa" type="checkbox" name="item_image_na" value="1">
                            <span class="form-check-label">N/A</span>
                        </label>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Preview</label>
                        <div class="item-picture-preview">
                            <img id="itemImagePreview" style="<?= empty($editItem['item_image']) ? 'display:none' : '' ?>" src="../uploads/items/<?= e($editItem['item_image'] ?? '') ?>" alt="Selected item preview">
                            <span id="itemImagePreviewEmpty" style="<?= empty($editItem['item_image']) ? '' : 'display:none' ?>">No picture selected</span>
                        </div>
                        <div class="item-image-selection-preview" id="itemImageSelectionPreview"></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            <div class="modal-form-actions"><a class="btn btn-light" href="items.php"><?= $imageOnly && count($manageImages) >= 5 ? 'Close' : 'Cancel' ?></a><?php if (!$imageOnly || count($manageImages) < 5): ?><button class="btn btn-primary"><?= $imageOnly ? 'Add Selected Images' : ($editItem ? 'Save Changes' : 'Add Item') ?></button><?php endif; ?></div>
        </form>
    </div>
</div>
<?php endif; ?>
<div id="imageModal" class="modal-backdrop" aria-hidden="true"><div class="modal-box image-gallery-modal" onclick="event.stopPropagation()"><div class="image-gallery-heading"><span>CSSR ITEM MEDIA</span><strong>Image Preview</strong></div><button type="button" class="btn btn-light image-gallery-close" data-image-modal-close onclick="closeImageModal()" aria-label="Close image preview">&times;</button><button type="button" class="image-gallery-nav prev" data-gallery-prev aria-label="Previous image">&#8249;</button><img class="detail-img" src="" alt=""><button type="button" class="image-gallery-nav next" data-gallery-next aria-label="Next image">&#8250;</button><div class="image-gallery-counter" data-gallery-counter></div></div></div>
<script>
(function(){
    var input=document.getElementById('itemImageInput');
    var na=document.getElementById('itemImageNa');
    var preview=document.getElementById('itemImagePreview');
    var emptyPreview=document.getElementById('itemImagePreviewEmpty');
    var selectionPreview=document.getElementById('itemImageSelectionPreview');
    if(!input||!na)return;
    var originalPreviewSrc=preview?preview.getAttribute('src'):'';
    var hasExistingImage=<?= empty($editItem['item_image']) ? 'false' : 'true' ?>;
    var existingImageCount=<?= (int) count($manageImages) ?>;
    function showSelectionThumbnails(){
        if(!selectionPreview)return;
        selectionPreview.innerHTML='';
        Array.from(input.files||[]).forEach(function(file,index){
            var card=document.createElement('div');
            var image=document.createElement('img');
            var label=document.createElement('small');
            image.src=URL.createObjectURL(file);
            image.alt=file.name;
            label.textContent=(!hasExistingImage&&index===0?'Primary: ':'Additional: ')+file.name;
            card.append(image,label);selectionPreview.appendChild(card);
        });
    }
    function showSelectedPreview(){
        if(!preview||!emptyPreview)return;
        if(input.files&&input.files[0]){
            var reader=new FileReader();
            reader.onload=function(event){
                preview.src=event.target.result;
                preview.style.display='block';
                emptyPreview.style.display='none';
            };
            reader.onerror=function(){
                preview.removeAttribute('src');
                preview.style.display='none';
                emptyPreview.textContent='Unable to preview this picture';
                emptyPreview.style.display='block';
            };
            reader.readAsDataURL(input.files[0]);
            return;
        }
        if(hasExistingImage){
            preview.src=originalPreviewSrc;
            preview.style.display='block';
            emptyPreview.style.display='none';
        }else{
            preview.removeAttribute('src');
            preview.style.display='none';
            emptyPreview.style.display='block';
        }
    }
    input.addEventListener('change',function(){
        var maximumNewImages=Math.max(0,5-existingImageCount);
        if(this.files.length>maximumNewImages){
            alert('You can add only '+maximumNewImages+' more picture(s). An item can have a maximum of 5 pictures.');
            this.value='';
        }
        showSelectedPreview();
        showSelectionThumbnails();
    });
    function syncItemImageNa(){
        if(na.checked){
            input.value='';
            input.disabled=true;
            input.title='N/A is selected';
            input.setAttribute('aria-disabled','true');
            input.required=false;
            if(preview)preview.style.display='none';
            if(selectionPreview)selectionPreview.innerHTML='';
            if(emptyPreview)emptyPreview.textContent='Picture marked N/A';
            if(emptyPreview)emptyPreview.style.display='block';
            return;
        }
        input.disabled=false;
        input.title='Upload item picture';
        input.removeAttribute('aria-disabled');
        input.required=!hasExistingImage;
        if(emptyPreview)emptyPreview.textContent='No picture selected';
        showSelectedPreview();
    }
    na.addEventListener('change',syncItemImageNa);
    syncItemImageNa();
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
