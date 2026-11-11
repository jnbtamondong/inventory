<?php
declare(strict_types=1);

/** Bounded, read-only catalog. Stock posting continues to use the existing services. */
function cssrRequestCatalog(PDO $db, array $input, bool $storage = false): array
{
    $q = is_scalar($input['q'] ?? '') ? mb_substr(trim((string)($input['q'] ?? '')), 0, 150) : '';
    $offset = max(0, min(1000000, (int)($input['offset'] ?? 0)));
    $room = $storage ? (string)($input['room'] ?? 'STORAGE ROOM 1') : 'CSSR';
    if (!in_array($room, ['CSSR','STORAGE ROOM 1','STORAGE ROOM 2','STORAGE ROOM 3'], true)) throw new DomainException('Choose a valid storage location.');
    $params = [];
    $where = ["i.status='Active'"];
    $ids = array_values(array_unique(array_filter(array_map('intval', array_slice(explode(',', (string)($input['ids'] ?? '')), 0, 50)), static fn($id) => $id > 0)));
    if ($ids) { $where[] = 'i.id IN ('.implode(',', array_fill(0, count($ids), '?')).')'; array_push($params, ...$ids); }
    if ($q !== '') {
        $term = '%'.str_replace(['!','%','_'], ['!!','!%','!_'], $q).'%';
        $where[] = "(i.item_name LIKE ? ESCAPE '!' OR i.item_code LIKE ? ESCAPE '!' OR i.item_description LIKE ? ESCAPE '!' OR i.category LIKE ? ESCAPE '!' OR i.lot_number LIKE ? ESCAPE '!' OR i.bin_location LIKE ? ESCAPE '!')";
        array_push($params, ...array_fill(0, 6, $term));
    }
    $balanceFilter = $storage ? "AND l.quantity_available>0 AND l.lot_number<>'UNIDENTIFIED / PENDING'" : '';
    $whereSql = implode(' AND ', $where);
    $stock = "SELECT l.item_id,SUM(l.quantity_available) quantity FROM item_lots l WHERE l.storage_location=? $balanceFilter GROUP BY l.item_id";
    $join = $storage ? 'JOIN' : 'LEFT JOIN';
    $sql = "SELECT i.id,i.item_code,COALESCE(NULLIF(TRIM(i.item_name),''),NULLIF(TRIM(i.item_description),''),i.item_code) item_name,
        i.item_description,i.unit,i.brand,i.category,i.lot_number,i.expiry_date,i.bin_location,i.item_image,
        COALESCE(b.quantity,0) current_stock,
        (SELECT l.id FROM item_lots l WHERE l.item_id=i.id AND l.storage_location=? AND l.status<>'DELETED'
         AND l.lot_number<>'UNIDENTIFIED / PENDING' AND (NULLIF(TRIM(l.lot_image),'') IS NOT NULL OR EXISTS(SELECT 1 FROM item_lot_images li WHERE li.item_lot_id=l.id))
         ORDER BY (l.status='AVAILABLE' AND l.quantity_available>0) DESC,l.expiry_date IS NULL,l.expiry_date,l.created_at,l.id LIMIT 1) available_lot_id
        FROM items i $join ($stock) b ON b.item_id=i.id WHERE $whereSql ORDER BY item_name,i.id LIMIT 51 OFFSET $offset";
    $stmt = $db->prepare($sql); $stmt->execute(array_merge([$room,$room], $params));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC); $more = count($rows) > 50; $rows = array_slice($rows, 0, 50);
    $lotIds = array_values(array_filter(array_unique(array_column($rows, 'available_lot_id'))));
    $images = []; $lotImages = [];
    if ($lotIds) {
        $marks = implode(',', array_fill(0, count($lotIds), '?'));
        $stmt = $db->prepare("SELECT item_lot_id,image_name FROM item_lot_images WHERE item_lot_id IN ($marks) ORDER BY item_lot_id,sort_order,id");
        $stmt->execute($lotIds);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $images[(int)$row['item_lot_id']][] = $row['image_name'];
        $stmt = $db->prepare("SELECT id,lot_image FROM item_lots WHERE id IN ($marks)"); $stmt->execute($lotIds);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $lotImages[(int)$row['id']] = $row['lot_image'];
    }
    foreach ($rows as &$row) {
        $row['storage_location'] = $room;
        $row['available_lot_images'] = $images[(int)$row['available_lot_id']] ?? [];
        $row['available_lot_image'] = $lotImages[(int)$row['available_lot_id']] ?? null;
    }
    return ['items'=>$rows, 'has_more'=>$more, 'next_offset'=>$offset+count($rows)];
}

function cssrServeRequestCatalog(bool $storage = false): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' || !isset($_GET['catalog'])) return;
    header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: private, no-store');
    session_write_close();
    try { echo json_encode(['ok'=>true] + cssrRequestCatalog(db(), $_GET, $storage), JSON_THROW_ON_ERROR); }
    catch (Throwable $error) { http_response_code(422); echo json_encode(['ok'=>false,'message'=>safe_error_message($error)]); }
    exit;
}
