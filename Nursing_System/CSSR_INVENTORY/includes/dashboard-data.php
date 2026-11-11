<?php
declare(strict_types=1);

// Read-only dashboard queries. Callers supply permissions after authenticating.
function cssrDashboardData(PDO $connection, array $access, $requestedYear = null, ?DateTimeImmutable $now = null): array
{
    $now = ($now ?? new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->setTimezone(new DateTimeZone('Asia/Manila'));
    $today = $now->format('Y-m-d');
    $tomorrow = $now->modify('+1 day')->format('Y-m-d');
    $query = static function (string $sql, array $params = []) use ($connection): array {
        $statement = $connection->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    };
    $inventory = !empty($access['inventory']);
    $requests = !empty($access['requests']);
    $all = !empty($access['all_requests']);
    $departmentId = (int) ($access['department_id'] ?? 0);
    $scope = $all ? '' : ' AND r.department_id = ?';
    $scopeParams = $all ? [] : [$departmentId];
    $years = [(int) $now->format('Y')];
    if ($inventory) {
        foreach ($query('SELECT DISTINCT YEAR(issued_date) year FROM stock_out WHERE issued_date IS NOT NULL ORDER BY year DESC') as $row) {
            if ((int) $row['year'] >= 1900 && (int) $row['year'] <= 9998) $years[] = (int) $row['year'];
        }
    }
    $years = array_values(array_unique($years)); rsort($years);
    $candidate = is_scalar($requestedYear) ? filter_var($requestedYear, FILTER_VALIDATE_INT) : false;
    $year = $candidate !== false && in_array($candidate, $years, true) ? $candidate : (int) $now->format('Y');
    $data = [
        'date'=>$today, 'year'=>$year, 'years'=>$years, 'inventory_visible'=>$inventory, 'requests_visible'=>$requests,
        'counts'=>['total'=>null, 'available'=>null, 'low'=>null, 'out'=>null],
        'monthly'=>array_fill(0, 12, 0), 'low_items'=>[], 'pending'=>[], 'pending_total'=>0,
        'activity'=>['requests'=>null, 'pending'=>null, 'released'=>null, 'returns'=>null, 'inbound'=>null],
        'scope_label'=>$all ? 'All departments' : (($access['department_name'] ?? '') ?: 'Your department'),
    ];
    if ($inventory) {
        $counts = $query("SELECT COUNT(*) total,
            COALESCE(SUM(current_stock > 0 AND current_stock >= minimum_stock),0) available,
            COALESCE(SUM(current_stock > 0 AND current_stock < minimum_stock),0) low,
            COALESCE(SUM(current_stock <= 0),0) `out` FROM items WHERE status='Active'")[0];
        $data['counts'] = array_map('intval', $counts);
        foreach ($query('SELECT MONTH(issued_date) month, SUM(quantity_issued) quantity FROM stock_out WHERE issued_date >= ? AND issued_date < ? GROUP BY MONTH(issued_date)', [sprintf('%04d-01-01', $year), sprintf('%04d-01-01', $year + 1)]) as $row) {
            $data['monthly'][(int) $row['month'] - 1] = (int) $row['quantity'];
        }
        $data['low_items'] = $query("SELECT id, item_code, COALESCE(NULLIF(TRIM(item_name),''), NULLIF(TRIM(item_description),''), item_code) item_name,
            current_stock, minimum_stock, unit FROM items WHERE status='Active' AND (current_stock < minimum_stock OR current_stock <= 0)
            ORDER BY (current_stock <= 0) DESC, current_stock / GREATEST(minimum_stock,1), item_name LIMIT 6");
        // One delivery may have many item lines; group by its inbound identifier.
        $data['activity']['inbound'] = (int) $query("SELECT COUNT(DISTINCT COALESCE(NULLIF(inbound_no,''), NULLIF(reference_no,''), CONCAT('ROW-',id))) count FROM stock_in WHERE received_date = ?", [$today])[0]['count'];
    }
    if ($requests) {
        $activity = $query("SELECT
            COALESCE(SUM(r.request_date = ? AND r.status NOT IN ('Cancelled','Deleted')),0) requests,
            COALESCE(SUM(r.request_date = ? AND r.status = 'Pending'),0) pending,
            COALESCE(SUM(r.status = 'Released' AND r.released_at >= ? AND r.released_at < ?),0) released,
            COALESCE(SUM(r.status IN ('Pending','Approved')),0) pending_total
            FROM item_requests r WHERE 1=1 {$scope}", array_merge([$today, $today, $today, $tomorrow], $scopeParams))[0];
        foreach (['requests','pending','released'] as $key) $data['activity'][$key] = (int) $activity[$key];
        $data['pending_total'] = (int) $activity['pending_total'];
        $data['pending'] = $query("SELECT r.id, r.request_no, r.department_name, r.request_date, r.status,
            (SELECT COUNT(DISTINCT d.item_id) FROM item_request_details d WHERE d.request_id=r.id) item_count
            FROM item_requests r WHERE r.status IN ('Pending','Approved') {$scope}
            ORDER BY r.request_date, r.id LIMIT 6", $scopeParams);
        $data['activity']['returns'] = (int) $query("SELECT COALESCE(SUM(d.quantity_received),0) quantity
            FROM department_returns r JOIN department_return_details d ON d.return_id=r.id
            WHERE r.status='Received' AND r.received_at >= ? AND r.received_at < ? {$scope}", array_merge([$today, $tomorrow], $scopeParams))[0]['quantity'];
    }
    return $data;
}
