<?php
declare(strict_types=1);

$source = $argv[1] ?? '';
$commit = in_array('--commit', $argv, true);
$batch = '2026-STOCK-LIST-20260730';

if ($source === '' || !is_file($source)) {
    fwrite(STDERR, "Usage: php import_cssr_stock_in_2026.php <stock-list.txt> [--commit]\n");
    exit(1);
}

$pdo = new PDO(
    'mysql:host=localhost;dbname=cssr_inventory;charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

function normalized_name(string $value): string
{
    return strtolower((string) preg_replace('/\s+/', ' ', trim($value)));
}

$rows = [];
$skipped = [];
$handle = fopen($source, 'rb');
$header = fgetcsv($handle, 0, "\t");
$lineNumber = 1;
while (($columns = fgetcsv($handle, 0, "\t")) !== false) {
    $lineNumber++;
    $name = trim((string) ($columns[0] ?? ''));
    $lot = trim((string) ($columns[1] ?? ''));
    $quantityText = str_replace(',', '', trim((string) ($columns[2] ?? '')));
    if ($name === '' || $quantityText === '' || !ctype_digit($quantityText) || (int) $quantityText <= 0) {
        $skipped[] = ['line' => $lineNumber, 'name' => $name, 'quantity' => $quantityText];
        continue;
    }
    $rows[] = ['name' => $name, 'lot' => $lot, 'quantity' => (int) $quantityText];
}
fclose($handle);

$items = $pdo->query('SELECT id, item_description FROM items')->fetchAll(PDO::FETCH_ASSOC);
$itemMap = [];
foreach ($items as $item) {
    $itemMap[normalized_name($item['item_description'])] = (int) $item['id'];
}

$missing = [];
foreach ($rows as $row) {
    $key = normalized_name($row['name']);
    if (!isset($itemMap[$key])) {
        $missing[$key] = $row['name'];
    }
}

$totalQuantity = array_sum(array_column($rows, 'quantity'));
echo 'valid_rows=' . count($rows) . ' skipped_rows=' . count($skipped)
    . ' missing_items=' . count($missing) . ' total_quantity=' . $totalQuantity . PHP_EOL;
foreach ($skipped as $row) {
    echo "SKIP line {$row['line']}: {$row['name']} (quantity missing/invalid)" . PHP_EOL;
}
foreach ($missing as $name) {
    echo "CREATE ITEM: {$name}" . PHP_EOL;
}

if (!$commit) {
    echo "Dry run only. Add --commit to import.\n";
    exit(0);
}

$alreadyImported = $pdo->prepare('SELECT COUNT(*) FROM stock_in WHERE remarks = ?');
$alreadyImported->execute([$batch]);
if ((int) $alreadyImported->fetchColumn() > 0) {
    fwrite(STDERR, "Batch {$batch} was already imported; no changes made.\n");
    exit(2);
}

$pdo->beginTransaction();
try {
    $maximumCode = (int) $pdo->query(
        "SELECT COALESCE(MAX(CAST(SUBSTRING(item_code, 6) AS UNSIGNED)), 0)
         FROM items WHERE item_code REGEXP '^CSSR-[0-9]+$'"
    )->fetchColumn();
    $insertItem = $pdo->prepare(
        "INSERT INTO items
            (item_code, item_description, category, unit, brand, supplier,
             lot_number, expiry_date, bin_location, minimum_stock, current_stock, status)
         VALUES (?, ?, 'Medical Supplies', 'piece', '', '', '', NULL, '', 0, 0, 'Active')"
    );
    foreach ($missing as $key => $name) {
        $maximumCode++;
        $code = sprintf('CSSR-%06d', $maximumCode);
        $insertItem->execute([$code, $name]);
        $itemMap[$key] = (int) $pdo->lastInsertId();
    }

    $selectItem = $pdo->prepare('SELECT current_stock FROM items WHERE id = ? FOR UPDATE');
    $updateItem = $pdo->prepare('UPDATE items SET current_stock = ? WHERE id = ?');
    $insertStock = $pdo->prepare(
        'INSERT INTO stock_in
            (item_id, reference_no, supplier, quantity_received, lot_number, expiry_date,
             received_date, received_by, remarks)
         VALUES (?, ?, ?, ?, ?, NULL, ?, ?, ?)'
    );
    $insertBinCard = $pdo->prepare(
        'INSERT INTO bin_cards
            (item_id, transaction_date, reference_no, transaction_type, lot_number,
             expiry_date, received_qty, issued_qty, balance_qty, department, encoded_by, remarks)
         VALUES (?, ?, ?, ?, ?, NULL, ?, 0, ?, ?, ?, ?)'
    );

    foreach ($rows as $index => $row) {
        $itemId = $itemMap[normalized_name($row['name'])];
        $reference = sprintf('STOCKIN-2026-%04d', $index + 1);
        $selectItem->execute([$itemId]);
        $balance = (int) $selectItem->fetchColumn() + $row['quantity'];
        $updateItem->execute([$balance, $itemId]);
        $insertStock->execute([
            $itemId, $reference, 'JVGH 2026 STOCK IMPORT', $row['quantity'], $row['lot'],
            '2026-07-30', 'System Administrator', $batch,
        ]);
        $insertBinCard->execute([
            $itemId, '2026-07-30', $reference, 'STOCK IN', $row['lot'],
            $row['quantity'], $balance, 'JVGH 2026 STOCK IMPORT', 'System Administrator', $batch,
        ]);
    }

    $pdo->commit();
    echo "Imported batch {$batch}.\n";
} catch (Throwable $error) {
    $pdo->rollBack();
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
