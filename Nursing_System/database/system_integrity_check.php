<?php
declare(strict_types=1);

require __DIR__ . '/../CSSR_INVENTORY/config/database.php';

$checks = [
    'Negative lot quantities' => 'SELECT COUNT(*) FROM item_lots WHERE quantity_received<0 OR quantity_issued<0 OR quantity_returned<0 OR quantity_available<0',
    'Returned quantity above issued quantity' => 'SELECT COUNT(*) FROM item_lots WHERE quantity_returned>quantity_issued',
    'Item balance mismatches' => 'SELECT COUNT(*) FROM items i WHERE i.current_stock<>(SELECT COALESCE(SUM(l.quantity_available),0) FROM item_lots l WHERE l.item_id=i.id)',
    'Orphan item lots' => 'SELECT COUNT(*) FROM item_lots l LEFT JOIN items i ON i.id=l.item_id WHERE i.id IS NULL',
    'Orphan inbound rows' => 'SELECT COUNT(*) FROM stock_in s LEFT JOIN items i ON i.id=s.item_id LEFT JOIN item_lots l ON l.id=s.item_lot_id WHERE i.id IS NULL OR (s.item_lot_id IS NOT NULL AND l.id IS NULL)',
    'Orphan outbound rows' => 'SELECT COUNT(*) FROM stock_out s LEFT JOIN items i ON i.id=s.item_id LEFT JOIN item_lots l ON l.id=s.item_lot_id WHERE i.id IS NULL OR (s.item_lot_id IS NOT NULL AND l.id IS NULL)',
    'Duplicate lot identities' => "SELECT COUNT(*) FROM (SELECT item_id,UPPER(TRIM(lot_number)),COALESCE(expiry_date,'1000-01-01'),UPPER(TRIM(COALESCE(brand,''))),UPPER(TRIM(COALESCE(supplier,''))),UPPER(TRIM(storage_location)),unit_price,COUNT(*) c FROM item_lots GROUP BY item_id,UPPER(TRIM(lot_number)),COALESCE(expiry_date,'1000-01-01'),UPPER(TRIM(COALESCE(brand,''))),UPPER(TRIM(COALESCE(supplier,''))),UPPER(TRIM(storage_location)),unit_price HAVING c>1) duplicates",
];

$failures = 0;
foreach ($checks as $label => $sql) {
    $count = (int) db_value($sql);
    echo ($count === 0 ? '[OK] ' : '[FAIL] ') . $label . ': ' . $count . PHP_EOL;
    if ($count !== 0) {
        $failures++;
    }
}

exit($failures === 0 ? 0 : 2);
