<?php
declare(strict_types=1);

$source = $argv[1] ?? '';
$commit = in_array('--commit', $argv, true);
$receivedBy = 'System Administrator';
$storageLocation = 'CSSR';
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--location=')) {
        $storageLocation = strtoupper(trim((string) preg_replace('/\s+/', ' ', substr($argument, 11))));
    }
}
$batch = $storageLocation . ' STOCK IMPORT';
$defaultCategory = null;
$defaultUnit = 'piece';

if ($source === '' || !is_file($source)) {
    fwrite(STDERR, "Usage: php import_cssr_storage_stock.php <tab-separated-file> [--location=\"CSSR\"] [--commit]\n");
    exit(1);
}

$pdo = new PDO(
    'mysql:host=localhost;dbname=cssr_inventory;charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

function clean_text(string $value): string
{
    $value = str_replace(["\xEF\xBB\xBF", '`'], '', $value);
    return trim((string) preg_replace('/\s+/', ' ', $value));
}

function normalized_name(string $value): string
{
    return strtolower(clean_text($value));
}

function imported_date_or_null(string $value): ?string
{
    $value = clean_text($value);
    if ($value === '') {
        return null;
    }
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $matches)) {
        $month = (int) $matches[1];
        $day = (int) $matches[2];
        $year = (int) $matches[3];
        return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)) {
        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];
        return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
    }
    return null;
}

function quantity_value(string $value): int
{
    $clean = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', trim($value)));
    if (!is_numeric($clean)) {
        return 0;
    }
    return (int) round((float) $clean);
}

$rows = [];
$skipped = [];
$headerFound = false;
$handle = fopen($source, 'rb');
if (!$handle) {
    throw new RuntimeException('Unable to open import file.');
}

$lineNumber = 0;
while (($columns = fgetcsv($handle, 0, "\t")) !== false) {
    $lineNumber++;
    $columns = array_map(static fn($value) => clean_text((string) $value), $columns);
    if (!$headerFound) {
        if (isset($columns[0]) && strtoupper($columns[0]) === 'ITEM NAME') {
            $headerFound = true;
        }
        continue;
    }

    $columns = array_pad($columns, 4, '');
    [$name, $lot, $quantityText, $dateText] = array_slice($columns, 0, 4);
    $quantity = quantity_value($quantityText);
    $receivedDate = imported_date_or_null($dateText);

    if ($name === '' && $quantityText === '') {
        continue;
    }
    if ($name === '' || $quantity <= 0 || $receivedDate === null) {
        $skipped[] = [
            'line' => $lineNumber,
            'name' => $name,
            'quantity' => $quantityText,
            'date' => $dateText,
        ];
        continue;
    }

    $rows[] = [
        'name' => $name,
        'lot' => $lot !== '' ? $lot : 'NO-LOT',
        'quantity' => $quantity,
        'received_date' => $receivedDate,
        'line' => $lineNumber,
    ];
}
fclose($handle);

if (!$headerFound) {
    throw new RuntimeException('ITEM NAME header was not found.');
}

$totalQuantity = array_sum(array_column($rows, 'quantity'));
$uniqueItems = count(array_unique(array_map(static fn($row) => normalized_name($row['name']), $rows)));
echo "valid_rows=" . count($rows) . " unique_items={$uniqueItems} skipped_rows=" . count($skipped) . " total_quantity={$totalQuantity}\n";
foreach (array_slice($skipped, 0, 30) as $row) {
    echo "SKIP line {$row['line']}: {$row['name']} qty={$row['quantity']} date={$row['date']}\n";
}
if (count($skipped) > 30) {
    echo '... ' . (count($skipped) - 30) . " more skipped rows\n";
}
if (!$commit) {
    echo "Dry run only. Add --commit to import.\n";
    exit(0);
}

$alreadyImported = $pdo->prepare('SELECT COUNT(*) FROM stock_in WHERE remarks LIKE ?');
$alreadyImported->execute([$batch . '%']);
if ((int) $alreadyImported->fetchColumn() > 0) {
    fwrite(STDERR, "Batch already imported; no changes made.\n");
    exit(2);
}

$pdo->beginTransaction();
try {
    $itemMap = [];
    foreach ($pdo->query('SELECT id,item_description FROM items') as $item) {
        $itemMap[normalized_name($item['item_description'])] = (int) $item['id'];
    }

    $maximumCode = (int) $pdo->query(
        "SELECT COALESCE(MAX(CAST(SUBSTRING(item_code, 6) AS UNSIGNED)), 0)
         FROM items WHERE item_code REGEXP '^CSSR-[0-9]+$'"
    )->fetchColumn();

    $insertItem = $pdo->prepare(
        'INSERT INTO items
            (item_code,item_description,category,unit,brand,supplier,lot_number,expiry_date,bin_location,minimum_stock,current_stock,status)
         VALUES (?,?,?,?,?,?,?,?,?,0,0,"Active")'
    );
    $selectItemStock = $pdo->prepare('SELECT current_stock FROM items WHERE id=? FOR UPDATE');
    $updateItemStock = $pdo->prepare(
        'UPDATE items SET current_stock=?, lot_number=COALESCE(NULLIF(lot_number,""),?), bin_location=COALESCE(NULLIF(bin_location,""),?) WHERE id=?'
    );
    $findLot = $pdo->prepare(
        'SELECT id FROM item_lots
         WHERE item_id=? AND lot_number=? AND expiry_date IS NULL AND supplier="" AND storage_location=?
         FOR UPDATE'
    );
    $insertLot = $pdo->prepare(
        'INSERT INTO item_lots
            (item_id,lot_number,expiry_date,supplier,storage_location,unit_price,quantity_received,quantity_issued,quantity_returned,quantity_available,status)
         VALUES (?, ?, NULL, "", ?, 0, ?, 0, 0, ?, "AVAILABLE")'
    );
    $updateLot = $pdo->prepare(
        'UPDATE item_lots
         SET quantity_received=quantity_received+?, quantity_available=quantity_available+?, status="AVAILABLE"
         WHERE id=?'
    );
    $insertStockIn = $pdo->prepare(
        'INSERT INTO stock_in
            (item_id,item_lot_id,reference_no,supplier,quantity_received,unit_price,lot_number,expiry_date,received_date,received_by,remarks)
         VALUES (?,?,?,?,?,0,?,NULL,?,?,?)'
    );
    $insertBinCard = $pdo->prepare(
        'INSERT INTO bin_cards
            (item_id,transaction_date,reference_no,transaction_type,lot_number,expiry_date,received_qty,issued_qty,balance_qty,department,encoded_by,remarks)
         VALUES (?,?,?,?,?,NULL,?,0,?,?,?,?)'
    );
    $insertStockCard = $pdo->prepare(
        'INSERT INTO stock_cards
            (item_id,transaction_date,reference_no,transaction_type,lot_number,expiry_date,received_qty,issued_qty,balance_qty,from_location,to_location,encoded_by,remarks)
         VALUES (?,?,?,?,?,NULL,?,0,?,"",?,?,?)'
    );

    $itemsCreated = 0;
    $lotsCreated = 0;
    $lotsUpdated = 0;
    $stockRows = 0;
    foreach ($rows as $row) {
        $key = normalized_name($row['name']);
        if (!isset($itemMap[$key])) {
            $maximumCode++;
            $code = sprintf('CSSR-%06d', $maximumCode);
            $insertItem->execute([$code, $row['name'], $defaultCategory, $defaultUnit, '', '', $row['lot'], null, $storageLocation]);
            $itemMap[$key] = (int) $pdo->lastInsertId();
            $itemsCreated++;
        }
        $itemId = $itemMap[$key];

        $selectItemStock->execute([$itemId]);
        $newBalance = (int) $selectItemStock->fetchColumn() + $row['quantity'];
        $updateItemStock->execute([$newBalance, $row['lot'], $storageLocation, $itemId]);

        $findLot->execute([$itemId, $row['lot'], $storageLocation]);
        $lot = $findLot->fetch();
        if ($lot) {
            $lotId = (int) $lot['id'];
            $updateLot->execute([$row['quantity'], $row['quantity'], $lotId]);
            $lotsUpdated++;
        } else {
            $insertLot->execute([$itemId, $row['lot'], $storageLocation, $row['quantity'], $row['quantity']]);
            $lotId = (int) $pdo->lastInsertId();
            $lotsCreated++;
        }

        $reference = 'CSSR-STORAGE-' . str_replace('-', '', $row['received_date']) . '-' . str_pad((string) $row['line'], 5, '0', STR_PAD_LEFT);
        $remarks = $batch . ' | source line ' . $row['line'];

        $insertStockIn->execute([
            $itemId,
            $lotId,
            $reference,
            '',
            $row['quantity'],
            $row['lot'],
            $row['received_date'],
            $receivedBy,
            $remarks,
        ]);
        $insertBinCard->execute([
            $itemId,
            $row['received_date'],
            $reference,
            'STOCK IN',
            $row['lot'],
            $row['quantity'],
            $newBalance,
            $storageLocation,
            $receivedBy,
            $remarks,
        ]);
        $insertStockCard->execute([
            $itemId,
            $row['received_date'] . ' 00:00:00',
            $reference,
            'STOCK IN',
            $row['lot'],
            $row['quantity'],
            $newBalance,
            $storageLocation,
            $receivedBy,
            $remarks,
        ]);
        $stockRows++;
    }

    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $error;
}

echo "items_created={$itemsCreated} lots_created={$lotsCreated} lots_updated={$lotsUpdated} stock_in_rows={$stockRows}\n";
