<?php
declare(strict_types=1);

$source = $argv[1] ?? '';
$commit = in_array('--commit', $argv, true);
$skipShort = in_array('--skip-short', $argv, true);
$issueLocation = 'CSSR';
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--location=')) {
        $issueLocation = strtoupper(trim((string) preg_replace('/\s+/', ' ', substr($argument, 11))));
    }
}
$allLocations = $issueLocation === 'ALL';
$preferredLocation = 'CSSR';
$locationLabel = $allLocations ? 'ALL LOCATIONS' : $issueLocation;
$batch = $locationLabel . ' OUTBOUND IMPORT';
$issuedBy = 'System Administrator';
$receivedBy = 'Department Representative';

if ($source === '' || !is_file($source)) {
    fwrite(STDERR, "Usage: php import_cssr_outbound.php <tab-separated-file> [--location=CSSR|ALL] [--skip-short] [--commit]\n");
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
$hasDepartmentColumn = true;
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
            $hasDepartmentColumn = in_array('DEPARTMENT', array_map('strtoupper', $columns), true);
        }
        continue;
    }

    $columns = array_pad($columns, 5, '');
    if ($hasDepartmentColumn) {
        [$name, $lot, $quantityText, $department, $dateText] = array_slice($columns, 0, 5);
    } else {
        [$name, $lot, $quantityText, $dateText] = array_slice($columns, 0, 4);
        $department = $locationLabel;
    }
    $quantity = quantity_value($quantityText);
    $issuedDate = imported_date_or_null($dateText);

    if ($name === '' && $quantityText === '') {
        continue;
    }
    if ($name === '' || $lot === '' || $quantity <= 0 || $department === '' || $issuedDate === null) {
        $skipped[] = [
            'line' => $lineNumber,
            'name' => $name,
            'lot' => $lot,
            'quantity' => $quantityText,
            'department' => $department,
            'date' => $dateText,
            'reason' => 'missing/invalid required value',
        ];
        continue;
    }

    $rows[] = [
        'name' => $name,
        'lot' => $lot,
        'quantity' => $quantity,
        'department' => strtoupper($department),
        'issued_date' => $issuedDate,
        'line' => $lineNumber,
    ];
}
fclose($handle);

if (!$headerFound) {
    throw new RuntimeException('ITEM NAME header was not found.');
}

$itemMap = [];
foreach ($pdo->query('SELECT id,item_description FROM items') as $item) {
    $itemMap[normalized_name($item['item_description'])] = (int) $item['id'];
}

$availability = [];
$importRows = [];
$lotLookup = $allLocations
    ? $pdo->prepare(
        'SELECT id,quantity_available
         FROM item_lots
         WHERE item_id=? AND lot_number=?
         ORDER BY CASE WHEN storage_location=? THEN 0 WHEN storage_location="" THEN 1 ELSE 2 END, id'
    )
    : $pdo->prepare(
        'SELECT id,quantity_available
         FROM item_lots
         WHERE item_id=? AND lot_number=? AND storage_location=?
         ORDER BY id'
    );
$missingOrShort = [];
foreach ($rows as $row) {
    $key = normalized_name($row['name']);
    if (!isset($itemMap[$key])) {
        $missingOrShort[] = $row + ['available' => 0, 'reason' => 'item not found'];
        continue;
    }
    $availabilityKey = $itemMap[$key] . '|' . strtoupper($row['lot']);
    if (!array_key_exists($availabilityKey, $availability)) {
        $lotLookup->execute($allLocations ? [$itemMap[$key], $row['lot'], $preferredLocation] : [$itemMap[$key], $row['lot'], $issueLocation]);
        $availability[$availabilityKey] = array_sum(array_map(static fn($lot) => (int) $lot['quantity_available'], $lotLookup->fetchAll()));
    }
    if ($availability[$availabilityKey] < $row['quantity']) {
        $missingOrShort[] = $row + ['available' => $availability[$availabilityKey], 'reason' => 'insufficient lot stock'];
        continue;
    }
    $availability[$availabilityKey] -= $row['quantity'];
    $importRows[] = $row;
}

$totalQuantity = array_sum(array_column($rows, 'quantity'));
$importQuantity = array_sum(array_column($importRows, 'quantity'));
$uniqueItems = count(array_unique(array_map(static fn($row) => normalized_name($row['name']), $rows)));
echo 'valid_rows=' . count($rows) . " unique_items={$uniqueItems} skipped_rows=" . count($skipped)
    . " total_quantity={$totalQuantity} importable_rows=" . count($importRows)
    . " importable_quantity={$importQuantity} missing_or_short=" . count($missingOrShort) . PHP_EOL;
foreach (array_slice($skipped, 0, 20) as $row) {
    echo "SKIP line {$row['line']}: {$row['name']} lot={$row['lot']} qty={$row['quantity']} dept={$row['department']} date={$row['date']} ({$row['reason']})\n";
}
if (count($skipped) > 20) {
    echo '... ' . (count($skipped) - 20) . " more skipped rows\n";
}
foreach (array_slice($missingOrShort, 0, 40) as $row) {
    echo "SHORT line {$row['line']}: {$row['name']} lot={$row['lot']} qty={$row['quantity']} available={$row['available']} ({$row['reason']})\n";
}
if (count($missingOrShort) > 40) {
    echo '... ' . (count($missingOrShort) - 40) . " more missing/short rows\n";
}
if (!$commit) {
    echo "Dry run only. Add --commit to import.\n";
    exit(0);
}
if ($missingOrShort && !$skipShort) {
    fwrite(STDERR, "Import blocked: resolve missing/short CSSR lot stock rows first.\n");
    exit(2);
}

$alreadyImported = $pdo->prepare('SELECT COUNT(*) FROM stock_out WHERE remarks LIKE ?');
$alreadyImported->execute([$batch . '%']);
if ((int) $alreadyImported->fetchColumn() > 0) {
    fwrite(STDERR, "Batch already imported; no changes made.\n");
    exit(3);
}

$selectItemStock = $pdo->prepare('SELECT current_stock FROM items WHERE id=? FOR UPDATE');
$updateItemStock = $pdo->prepare('UPDATE items SET current_stock=? WHERE id=?');
$selectLots = $allLocations
    ? $pdo->prepare(
        'SELECT id,quantity_available,storage_location
         FROM item_lots
         WHERE item_id=? AND lot_number=? AND quantity_available>0
         ORDER BY CASE WHEN storage_location=? THEN 0 WHEN storage_location="" THEN 1 ELSE 2 END, id
         FOR UPDATE'
    )
    : $pdo->prepare(
        'SELECT id,quantity_available,storage_location
         FROM item_lots
         WHERE item_id=? AND lot_number=? AND storage_location=? AND quantity_available>0
         ORDER BY id
         FOR UPDATE'
    );
$updateLot = $pdo->prepare(
    'UPDATE item_lots
     SET quantity_issued=quantity_issued+?, quantity_available=?, status=?
     WHERE id=?'
);
$insertStockOut = $pdo->prepare(
    'INSERT INTO stock_out
        (item_id,item_lot_id,reference_no,department,quantity_issued,quantity_returned,lot_number,issued_date,issued_by,received_by,remarks)
     VALUES (?,?,?,?,?,0,?,?,?,?,?)'
);
$insertBinCard = $pdo->prepare(
    'INSERT INTO bin_cards
        (item_id,transaction_date,reference_no,transaction_type,lot_number,expiry_date,received_qty,issued_qty,balance_qty,department,encoded_by,remarks)
     VALUES (?,?,?,?,?,NULL,0,?,?,?,?,?)'
);
$insertStockCard = $pdo->prepare(
    'INSERT INTO stock_cards
        (item_id,transaction_date,reference_no,transaction_type,lot_number,expiry_date,received_qty,issued_qty,balance_qty,from_location,to_location,encoded_by,remarks)
     VALUES (?,?,?,?,?,NULL,0,?,?,?,?,?,?)'
);

$pdo->beginTransaction();
try {
    $stockOutRows = 0;
    foreach ($importRows as $row) {
        $itemId = $itemMap[normalized_name($row['name'])];
        $selectItemStock->execute([$itemId]);
        $newBalance = (int) $selectItemStock->fetchColumn() - $row['quantity'];
        $updateItemStock->execute([$newBalance, $itemId]);

        $remaining = $row['quantity'];
        $selectLots->execute($allLocations ? [$itemId, $row['lot'], $preferredLocation] : [$itemId, $row['lot'], $issueLocation]);
        $lots = $selectLots->fetchAll();
        $reference = 'CSSR-OUT-' . str_replace('-', '', $row['issued_date']) . '-' . str_pad((string) $row['line'], 5, '0', STR_PAD_LEFT);
        $remarks = $batch . ' | source line ' . $row['line'];

        foreach ($lots as $lot) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($remaining, (int) $lot['quantity_available']);
            $lotBalance = (int) $lot['quantity_available'] - $take;
            $updateLot->execute([$take, $lotBalance, $lotBalance > 0 ? 'AVAILABLE' : 'DEPLETED', (int) $lot['id']]);
            $insertStockOut->execute([
                $itemId,
                (int) $lot['id'],
                $reference,
                $row['department'],
                $take,
                $row['lot'],
                $row['issued_date'],
                $issuedBy,
                $receivedBy,
                $remarks,
            ]);
            $insertBinCard->execute([
                $itemId,
                $row['issued_date'],
                $reference,
                'STOCK OUT',
                $row['lot'],
                $take,
                $newBalance,
                $row['department'],
                $issuedBy,
                $remarks,
            ]);
            $insertStockCard->execute([
                $itemId,
                $row['issued_date'] . ' 00:00:00',
                $reference,
                'STOCK OUT',
                $row['lot'],
                $take,
                $newBalance,
                $lot['storage_location'] !== '' ? $lot['storage_location'] : 'DEFAULT STORAGE',
                $row['department'],
                $issuedBy,
                $remarks,
            ]);
            $stockOutRows++;
            $remaining -= $take;
        }
        if ($remaining > 0) {
            throw new RuntimeException("Line {$row['line']} was short during import.");
        }
    }
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $error;
}

echo "stock_out_rows={$stockOutRows} total_issued={$importQuantity} skipped_invalid=" . count($skipped) . " skipped_short=" . count($missingOrShort) . "\n";
