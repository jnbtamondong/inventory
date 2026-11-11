<?php
declare(strict_types=1);

function cssrInventoryFilters(array $input): array
{
    $filters = [];
    foreach (['q','classification','stock_status','procurement_type','storage_location'] as $key) {
        $value = $input[$key] ?? '';
        $filters[$key] = is_scalar($value) ? trim((string)$value) : '';
    }
    if (strtoupper($filters['storage_location']) === 'CSSR STORAGE') $filters['storage_location'] = 'STORAGE ROOM 1';
    $size = is_scalar($input['per_page'] ?? null) ? (int)$input['per_page'] : 10;
    $filters['per_page'] = in_array($size, [10,25,50], true) ? $size : 10;
    $page = is_scalar($input['page'] ?? null) ? filter_var($input['page'], FILTER_VALIDATE_INT) : false;
    $filters['page'] = $page !== false && $page > 0 ? $page : 1;
    return $filters;
}

function cssrInventoryUrl(string $route, array $filters, array $changes = []): string
{
    $query = array_filter(array_replace($filters, $changes), static fn($value) => $value !== '' && $value !== null);
    return $route . ($query ? '?' . http_build_query($query) : '');
}

function cssrInventoryList(PDO $connection, array $input): array
{
    $filters = cssrInventoryFilters($input);
    $query = static function (string $sql, array $params = []) use ($connection): array {
        $statement = $connection->prepare($sql); $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    };
    // Scope balances before deriving stock status, summaries and pagination.
    $lotLocation = "CASE WHEN UPPER(TRIM(storage_location))='CSSR STORAGE' THEN 'STORAGE ROOM 1' ELSE TRIM(storage_location) END";
    $lotScope = $filters['storage_location'] !== '' ? ' WHERE '.$lotLocation.'='.$connection->quote($filters['storage_location']) : '';
    // Lot balances are authoritative; legacy items without lots use their saved balance.
    $records = "SELECT i.id, i.item_code,
        COALESCE(NULLIF(TRIM(i.item_name),''),NULLIF(TRIM(i.item_description),''),'') item_name,
        TRIM(COALESCE(i.category,'')) classification, TRIM(COALESCE(i.unit,'')) unit,
        COALESCE(l.balance,i.current_stock,0) current_stock, COALESCE(i.minimum_stock,0) minimum_stock,
        i.bin_location, i.item_description, i.procurement_type FROM items i
        LEFT JOIN (SELECT item_id,SUM(quantity_available) balance FROM item_lots{$lotScope} GROUP BY item_id) l ON l.item_id=i.id
        WHERE i.status='Active'";
    $base = "SELECT s.*, CASE
        WHEN s.current_stock < 0 THEN 'INVALID'
        WHEN s.current_stock=0 THEN 'OUT OF STOCK'
        WHEN s.current_stock < s.minimum_stock THEN 'LOW STOCK'
        ELSE 'AVAILABLE' END stock_status FROM ({$records}) s";
    $where = []; $params = [];
    if ($filters['q'] !== '') {
        $literal = '%' . str_replace(['!','%','_'], ['!!','!%','!_'], $filters['q']) . '%';
        $where[] = "(v.item_code LIKE ? ESCAPE '!' OR v.item_name LIKE ? ESCAPE '!' OR v.classification LIKE ? ESCAPE '!')";
        array_push($params, $literal, $literal, $literal);
    }
    if ($filters['classification'] !== '') { $where[] = 'v.classification=?'; $params[] = $filters['classification']; }
    if ($filters['stock_status'] !== '') { $where[] = 'v.stock_status=?'; $params[] = $filters['stock_status']; }
    if ($filters['procurement_type'] !== '') {
        $where[] = '(TRIM(v.procurement_type)=? OR EXISTS (SELECT 1 FROM stock_in p WHERE p.item_id=v.id AND TRIM(p.procurement_type)=?))';
        array_push($params,$filters['procurement_type'],$filters['procurement_type']);
    }
    if ($filters['storage_location'] !== '') {
        $where[] = "(EXISTS (SELECT 1 FROM item_lots loc WHERE loc.item_id=v.id AND CASE WHEN UPPER(TRIM(loc.storage_location))='CSSR STORAGE' THEN 'STORAGE ROOM 1' ELSE TRIM(loc.storage_location) END=?) OR (NOT EXISTS (SELECT 1 FROM item_lots loc WHERE loc.item_id=v.id) AND CASE WHEN UPPER(TRIM(v.bin_location))='CSSR STORAGE' THEN 'STORAGE ROOM 1' ELSE TRIM(v.bin_location) END=?))";
        array_push($params, $filters['storage_location'], $filters['storage_location']);
    }
    $filtered = "FROM ({$base}) v" . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
    $summary = $query("SELECT COUNT(*) total,
        COALESCE(SUM(CASE WHEN stock_status='AVAILABLE' THEN 1 ELSE 0 END),0) available,
        COALESCE(SUM(CASE WHEN stock_status='LOW STOCK' THEN 1 ELSE 0 END),0) low,
        COALESCE(SUM(CASE WHEN stock_status='OUT OF STOCK' THEN 1 ELSE 0 END),0) `out`,
        COALESCE(SUM(CASE WHEN stock_status='INVALID' THEN 1 ELSE 0 END),0) invalid {$filtered}", $params)[0];
    $summary = array_map('intval', $summary);
    $pages = max(1, (int)ceil($summary['total'] / $filters['per_page']));
    $filters['page'] = min($filters['page'], $pages);
    $offset = ($filters['page'] - 1) * $filters['per_page'];
    $rows = $query("SELECT v.* {$filtered} ORDER BY v.item_name,v.item_code,v.id LIMIT {$filters['per_page']} OFFSET {$offset}", $params);
    $options = [
        'classification'=>array_column($query("SELECT DISTINCT TRIM(category) value FROM items WHERE status='Active' AND TRIM(COALESCE(category,''))<>'' ORDER BY value"),'value'),
        'procurement_type'=>array_column($query("SELECT DISTINCT TRIM(p.procurement_type) value FROM stock_in p JOIN items i ON i.id=p.item_id WHERE i.status='Active' AND TRIM(COALESCE(p.procurement_type,''))<>'' UNION SELECT DISTINCT TRIM(i.procurement_type) value FROM items i WHERE i.status='Active' AND TRIM(COALESCE(i.procurement_type,''))<>'' ORDER BY value"),'value'),
        'storage_location'=>array_column($query("SELECT DISTINCT TRIM(l.storage_location) value FROM item_lots l JOIN items i ON i.id=l.item_id WHERE i.status='Active' AND TRIM(COALESCE(l.storage_location,''))<>''
            UNION SELECT DISTINCT TRIM(i.bin_location) value FROM items i WHERE i.status='Active' AND TRIM(COALESCE(i.bin_location,''))<>'' AND NOT EXISTS(SELECT 1 FROM item_lots l WHERE l.item_id=i.id) ORDER BY value"),'value'),
        'stock_status'=>['AVAILABLE','LOW STOCK','OUT OF STOCK','INVALID'],
    ];
    $options['storage_location'] = array_values(array_unique(array_map(static fn($location) => strtoupper($location)==='CSSR STORAGE' ? 'STORAGE ROOM 1' : $location, $options['storage_location'])));
    sort($options['storage_location']);
    return ['rows'=>$rows,'summary'=>$summary,'filters'=>$filters,'options'=>$options,'pages'=>$pages,'first'=>$summary['total'] ? $offset+1 : 0,'last'=>$offset+count($rows)];
}
