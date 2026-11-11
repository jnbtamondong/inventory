<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/database.php';

function count_expiry(string $value): ?string
{
    if ($value === '' || strtoupper($value) === 'N/A') return null;
    if (preg_match('~^(\d{1,2})/(\d{1,2})/(\d{4})$~', $value, $parts)) {
        [$month, $day, $year] = [(int) $parts[1], (int) $parts[2], (int) $parts[3]];
    } elseif (preg_match('~^(\d{1,2})/{1,2}(\d{4})$~', $value, $parts)) {
        [$month, $year] = [(int) $parts[1], (int) $parts[2]];
        if (!checkdate($month, 1, $year)) throw new RuntimeException('Invalid expiry: ' . $value);
        $day = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('t');
    } else {
        throw new RuntimeException('Invalid expiry: ' . $value);
    }
    if (!checkdate($month, $day, $year)) throw new RuntimeException('Invalid expiry: ' . $value);
    return sprintf('%04d-%02d-%02d', $year, $month, $day);
}

$source = $argv[1] ?? '';
if (!is_file($source)) throw new RuntimeException('Provide the tab-separated inventory file.');
$apply = in_array('--apply', $argv, true);
$items = db_all('SELECT * FROM items');
$byName = [];
foreach ($items as $item) {
    $name = strtoupper(trim((string) ($item['item_name'] ?: $item['item_description'])));
    $byName[$name][] = $item;
}
$lots = [];
$groups = [];
$handle = fopen($source, 'r');
if ($handle === false) throw new RuntimeException('Unable to read source file.');
while (($columns = fgetcsv($handle, 0, "\t", '"', '\\')) !== false) {
    if (count($columns) === 1 && trim((string) $columns[0]) === '') continue;
    if (count($columns) !== 7) throw new RuntimeException('Expected seven columns on row ' . (count($lots) + 1));
    [$name, $lotNumber, $expiration, $unit, $price, $brand, $quantity] = array_map('trim', $columns);
    $name = strtoupper($name);
    $matches = $byName[$name] ?? [];
    if (count($matches) !== 1) throw new RuntimeException('Expected one existing item for ' . $name);
    $itemId = (int) $matches[0]['id'];
    $total = 0;
    foreach (explode('+', $quantity) as $part) {
        if (!ctype_digit(trim($part))) throw new RuntimeException('Invalid quantity for ' . $name);
        $total += (int) trim($part);
    }
    $price = str_replace(',', '', $price);
    if ($price !== '' && !preg_match('/^\d+(?:\.\d{1,2})?$/', $price)) throw new RuntimeException('Invalid price for ' . $name);
    $unit = strtoupper($unit) === 'PEICE' ? 'PIECE' : strtoupper($unit);
    if ($unit === '') throw new RuntimeException('Missing unit for ' . $name);
    $brand = strtoupper($brand);
    if ($name === 'ADHESIVE PLASTER 1 INCH X 10 YARDS' && $brand === '3240') $brand = '3M';
    // Remove the spreadsheet text-prefix marker without changing the lot itself.
    $lotNumber = strtoupper(ltrim($lotNumber, '`'));
    $lots[] = [$itemId, $lotNumber, count_expiry($expiration), $brand, '', 'STORAGE ROOM 1', $price === '' ? null : $price, $total, $total, $total > 0 ? 'AVAILABLE' : 'DEPLETED'];
    if (isset($groups[$itemId]) && $groups[$itemId]['unit'] !== $unit) throw new RuntimeException('Conflicting units for ' . $name);
    $groups[$itemId] = ['unit' => $unit, 'quantity' => ($groups[$itemId]['quantity'] ?? 0) + $total];
}
fclose($handle);
if (!$lots) throw new RuntimeException('No inventory rows found.');
$ids = array_keys($groups);
$placeholders = implode(',', array_fill(0, count($ids), '?'));
if ((int) db_value("SELECT COUNT(*) FROM item_lots WHERE item_id IN ($placeholders)", $ids) !== 0) {
    throw new RuntimeException('These items already have lot records. Import stopped to prevent duplicate or overwritten lots.');
}
$summary = ['source_sha256' => hash_file('sha256', $source), 'items' => count($groups), 'lots' => count($lots), 'quantity' => array_sum(array_column($groups, 'quantity')), 'blank_prices' => count(array_filter($lots, static fn($lot) => $lot[6] === null)), 'storage' => 'STORAGE ROOM 1', 'mode' => 'replace stock counts'];
echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
if (!$apply) { echo "Dry run only. Add --apply to import.\n"; exit; }

$backup = __DIR__ . '/../logs/storage_count_backup_' . date('Ymd_His') . '.json';
$snapshot = ['summary' => $summary, 'items' => array_values(array_filter($items, static fn($item) => isset($groups[(int) $item['id']]))), 'lots' => []];
if (file_put_contents($backup, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX) === false) throw new RuntimeException('Unable to save pre-import backup.');
if (db_value("SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='item_lots' AND COLUMN_NAME='unit_price'") === 'NO') {
    db_statement('ALTER TABLE item_lots MODIFY unit_price DECIMAL(12,2) NULL DEFAULT 0');
}
db()->beginTransaction();
try {
    db_all("SELECT id FROM items WHERE id IN ($placeholders) FOR UPDATE", $ids);
    if ((int) db_value("SELECT COUNT(*) FROM item_lots WHERE item_id IN ($placeholders)", $ids) !== 0) throw new RuntimeException('Lot records changed during preparation.');
    $insert = db()->prepare('INSERT INTO item_lots (item_id,lot_number,expiry_date,brand,supplier,storage_location,unit_price,quantity_received,quantity_available,status) VALUES (?,?,?,?,?,?,?,?,?,?)');
    foreach ($lots as $lot) $insert->execute($lot);
    foreach ($groups as $id => $group) {
        db_statement('UPDATE items SET current_stock=?,unit=?,bin_location=? WHERE id=?', [$group['quantity'], $group['unit'], 'STORAGE ROOM 1', $id]);
        $balance = (int) db_value('SELECT COALESCE(SUM(quantity_available),0) FROM item_lots WHERE item_id=?', [$id]);
        if ($balance !== $group['quantity']) throw new RuntimeException('Stock balance verification failed for item ' . $id);
    }
    if ((int) db_value("SELECT COUNT(*) FROM item_lots WHERE item_id IN ($placeholders)", $ids) !== count($lots)) throw new RuntimeException('Lot count verification failed.');
    db()->commit();
    echo 'Imported and verified. Backup: ' . $backup . PHP_EOL;
} catch (Throwable $error) {
    if (db()->inTransaction()) db()->rollBack();
    throw $error;
}
