<?php
declare(strict_types=1);

$source = $argv[1] ?? '';
$commit = in_array('--commit', $argv, true);

if ($source === '' || !is_file($source)) {
    fwrite(STDERR, "Usage: php import_cssr_inventory_master_list.php <tab-separated-file> [--commit]\n");
    exit(1);
}

$pdo = new PDO(
    'mysql:host=localhost;dbname=cssr_inventory;charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

function normalized_name(string $value): string
{
    return strtolower((string) preg_replace('/\s+/', ' ', trim($value)));
}

function clean_text(string $value): string
{
    $value = str_replace(["\xEF\xBB\xBF", '`'], '', $value);
    return trim((string) preg_replace('/\s+/', ' ', $value));
}

function price_value(string $value): float
{
    $clean = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', trim($value)));
    return is_numeric($clean) ? round((float) $clean, 2) : 0.0;
}

function expiry_date_or_null(string $value): ?string
{
    $value = strtoupper(clean_text($value));
    if ($value === '' || in_array($value, ['N/A', 'NA', 'TF', 'EP', 'SWAB', 'TO FOLLOW'], true)) {
        return null;
    }

    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $matches)) {
        $month = (int) $matches[1];
        $day = (int) $matches[2];
        $year = (int) $matches[3];
        return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
    }

    if (preg_match('/^(\d{1,2})\/(\d{4})$/', $value, $matches)) {
        $month = (int) $matches[1];
        $year = (int) $matches[2];
        return checkdate($month, 1, $year) ? sprintf('%04d-%02d-01', $year, $month) : null;
    }

    if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $value, $matches)) {
        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];
        return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
    }

    return null;
}

$handle = fopen($source, 'rb');
if (!$handle) {
    throw new RuntimeException('Unable to open import file.');
}

$headerFound = false;
$rows = [];
$skipped = 0;
$invalidDates = 0;
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

    $columns = array_pad($columns, 9, '');
    [$name, $lot, $expiration, $unit, $classification, $unitPrice, $markup, $total, $selling] = array_slice($columns, 0, 9);
    if ($name === '') {
        $skipped++;
        continue;
    }

    $expiryDate = expiry_date_or_null($expiration);
    if (clean_text($expiration) !== '' && $expiryDate === null && !in_array(strtoupper(clean_text($expiration)), ['N/A', 'NA', 'TF', 'EP', 'SWAB', 'TO FOLLOW'], true)) {
        $invalidDates++;
    }

    $rows[] = [
        'name' => $name,
        'lot' => $lot !== '' ? $lot : 'NO-LOT',
        'expiration_text' => $expiration,
        'expiry_date' => $expiryDate,
        'unit' => $unit,
        'classification' => $classification,
        'unit_price' => price_value($unitPrice),
        'markup_cost' => price_value($markup),
        'total_price' => price_value($total),
        'selling_price' => price_value($selling),
        'line' => $lineNumber,
    ];
}
fclose($handle);

if (!$headerFound) {
    throw new RuntimeException('ITEM NAME header was not found.');
}

echo 'valid_rows=' . count($rows) . ' skipped_blank_rows=' . $skipped . ' invalid_or_text_dates=' . $invalidDates . PHP_EOL;
if (!$commit) {
    echo "Dry run only. Add --commit to import.\n";
    exit(0);
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS item_price_list (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_name VARCHAR(255) NOT NULL,
        item_description TEXT NULL,
        lot_number VARCHAR(120) NULL,
        expiration_text VARCHAR(80) NULL,
        unit_type VARCHAR(50) NULL,
        classification VARCHAR(120) NULL,
        unit_price DECIMAL(14,2) NOT NULL DEFAULT 0,
        markup_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
        total_price DECIMAL(14,2) NOT NULL DEFAULT 0,
        selling_price DECIMAL(14,2) NOT NULL DEFAULT 0,
        source_as_of DATE NULL,
        source_hash CHAR(64) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_item_price_source_hash (source_hash),
        INDEX idx_item_price_name (item_name),
        INDEX idx_item_price_lot (lot_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

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
$updateItem = $pdo->prepare(
    'UPDATE items
     SET category=COALESCE(NULLIF(category,""),?), unit=COALESCE(NULLIF(unit,""),?),
         lot_number=COALESCE(NULLIF(lot_number,""),?), expiry_date=COALESCE(expiry_date,?)
     WHERE id=?'
);
$upsertLot = $pdo->prepare(
    'INSERT INTO item_lots
        (item_id,lot_number,expiry_date,supplier,storage_location,unit_price,quantity_received,quantity_issued,quantity_returned,quantity_available,status)
     VALUES (?,?,?,?,?,?,0,0,0,0,"AVAILABLE")
     ON DUPLICATE KEY UPDATE unit_price=VALUES(unit_price), status="AVAILABLE"'
);
$upsertPrice = $pdo->prepare(
    'INSERT INTO item_price_list
        (item_name,item_description,lot_number,expiration_text,unit_type,classification,unit_price,markup_cost,total_price,selling_price,source_as_of,source_hash)
     VALUES (?,?,?,?,?,?,?,?,?,?,CURDATE(),?)
     ON DUPLICATE KEY UPDATE item_name=VALUES(item_name), item_description=VALUES(item_description), lot_number=VALUES(lot_number),
         expiration_text=VALUES(expiration_text), unit_type=VALUES(unit_type), classification=VALUES(classification),
         unit_price=VALUES(unit_price), markup_cost=VALUES(markup_cost), total_price=VALUES(total_price), selling_price=VALUES(selling_price),
         source_as_of=VALUES(source_as_of)'
);

$itemsCreated = 0;
$itemsTouched = 0;
$lotsUpserted = 0;
$pricesUpserted = 0;

$pdo->beginTransaction();
try {
    foreach ($rows as $row) {
        $key = normalized_name($row['name']);
        if (!isset($itemMap[$key])) {
            $maximumCode++;
            $code = sprintf('CSSR-%06d', $maximumCode);
            $insertItem->execute([
                $code,
                $row['name'],
                null,
                $row['unit'],
                '',
                '',
                $row['lot'],
                $row['expiry_date'],
                '',
            ]);
            $itemMap[$key] = (int) $pdo->lastInsertId();
            $itemsCreated++;
        } else {
            $updateItem->execute([null, $row['unit'], $row['lot'], $row['expiry_date'], $itemMap[$key]]);
            $itemsTouched++;
        }

        $itemId = $itemMap[$key];
        $upsertLot->execute([$itemId, $row['lot'], $row['expiry_date'], '', '', $row['unit_price']]);
        $lotsUpserted++;

        $identity = implode('|', array_map(
            static fn($value) => mb_strtoupper(trim((string) $value)),
            [$row['name'], '', $row['lot'], $row['expiration_text'], $row['unit'], $row['classification'], $row['unit_price'], $row['markup_cost'], $row['total_price'], $row['selling_price']]
        ));
        $upsertPrice->execute([
            $row['name'],
            null,
            $row['lot'],
            $row['expiration_text'],
            $row['unit'],
            $row['classification'],
            $row['unit_price'],
            $row['markup_cost'],
            $row['total_price'],
            $row['selling_price'],
            hash('sha256', $identity),
        ]);
        $pricesUpserted++;
    }
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $error;
}

echo "items_created={$itemsCreated} items_updated={$itemsTouched} lots_upserted={$lotsUpserted} prices_upserted={$pricesUpserted}\n";
