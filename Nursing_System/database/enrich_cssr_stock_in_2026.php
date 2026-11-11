<?php
declare(strict_types=1);

$source = $argv[1] ?? '';
$commit = in_array('--commit', $argv, true);
$batch = '2026-STOCK-LIST-20260730';

if ($source === '' || !is_file($source)) {
    fwrite(STDERR, "Usage: php enrich_cssr_stock_in_2026.php <enriched-list.txt> [--commit]\n");
    exit(1);
}

$pdo = new PDO(
    'mysql:host=localhost;dbname=cssr_inventory;charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

function normalize_value(string $value): string
{
    return strtolower((string) preg_replace('/\s+/', ' ', trim($value)));
}

function match_key(string $name, string $lot, int $quantity): string
{
    return normalize_value($name) . "\x1f" . normalize_value($lot) . "\x1f" . $quantity;
}

function parse_date(string $value): ?string
{
    $date = DateTimeImmutable::createFromFormat('!n/j/Y', trim($value));
    return $date instanceof DateTimeImmutable ? $date->format('Y-m-d') : null;
}

$sourceRows = [];
$skippedRows = [];
$handle = fopen($source, 'rb');
$line = 0;
while (($columns = fgetcsv($handle, 0, "\t")) !== false) {
    $line++;
    $name = trim((string) ($columns[0] ?? ''));
    $lot = trim((string) ($columns[1] ?? ''));
    $quantityText = str_replace(',', '', trim((string) ($columns[2] ?? '')));
    $delivered = parse_date((string) ($columns[7] ?? ''));
    if ($name === '' || !ctype_digit($quantityText) || (int) $quantityText <= 0) {
        $skippedRows[] = ['line' => $line, 'name' => $name, 'reason' => 'missing/invalid quantity'];
        continue;
    }
    if ($delivered === null) {
        fwrite(STDERR, "Invalid delivery date on source line {$line}.\n");
        exit(1);
    }
    $procurement = trim((string) ($columns[9] ?? ''));
    $classification = trim((string) ($columns[10] ?? ''));
    $remarks = implode(' - ', array_values(array_filter([$procurement, $classification], fn ($v) => $v !== '')));
    $sourceRows[] = [
        'line' => $line,
        'name' => $name,
        'lot' => $lot,
        'quantity' => (int) $quantityText,
        'delivered' => $delivered,
        'reference' => trim((string) ($columns[8] ?? '')) ?: 'TO FOLLOW',
        'supplier' => trim((string) ($columns[11] ?? '')),
        'remarks' => $remarks,
    ];
}
fclose($handle);

$statement = $pdo->prepare(
    'SELECT s.id, s.item_id, s.reference_no, s.quantity_received, s.lot_number,
            i.item_description
     FROM stock_in s JOIN items i ON i.id = s.item_id
     WHERE s.remarks = ? ORDER BY s.id'
);
$statement->execute([$batch]);
$databaseRows = $statement->fetchAll(PDO::FETCH_ASSOC);

$queues = [];
foreach ($databaseRows as $row) {
    $key = match_key($row['item_description'], (string) $row['lot_number'], (int) $row['quantity_received']);
    $queues[$key][] = $row;
}

$matches = [];
$unmatchedSource = [];
foreach ($sourceRows as $row) {
    $key = match_key($row['name'], $row['lot'], $row['quantity']);
    if (empty($queues[$key])) {
        $unmatchedSource[] = $row;
        continue;
    }
    $matches[] = ['source' => $row, 'database' => array_shift($queues[$key])];
}
$unmatchedDatabase = array_merge(...array_values(array_filter($queues)) ?: [[]]);

echo 'source_rows=' . count($sourceRows) . ' matched=' . count($matches)
    . ' skipped=' . count($skippedRows)
    . ' unmatched_source=' . count($unmatchedSource)
    . ' unmatched_database=' . count($unmatchedDatabase) . PHP_EOL;
foreach ($skippedRows as $row) {
    echo "SKIPPED SOURCE line {$row['line']}: {$row['name']} ({$row['reason']})\n";
}
foreach ($unmatchedSource as $row) {
    echo "UNMATCHED SOURCE line {$row['line']}: {$row['name']} | {$row['lot']} | {$row['quantity']}\n";
}
foreach ($unmatchedDatabase as $row) {
    echo "UNMATCHED DATABASE: {$row['item_description']} | {$row['lot_number']} | {$row['quantity_received']}\n";
}

if ($unmatchedSource || !$commit) {
    echo $commit ? "No changes made.\n" : "Dry run only. Add --commit to enrich matching rows.\n";
    exit($unmatchedSource ? 1 : 0);
}

$pdo->beginTransaction();
try {
    $updateStock = $pdo->prepare(
        'UPDATE stock_in
         SET reference_no = ?, supplier = ?, received_date = ?, remarks = ?
         WHERE id = ? AND remarks = ?'
    );
    $updateBin = $pdo->prepare(
        'UPDATE bin_cards
         SET reference_no = ?, transaction_date = ?, department = ?, remarks = ?
         WHERE item_id = ? AND reference_no = ? AND remarks = ? AND transaction_type = "STOCK IN"'
    );
    foreach ($matches as $match) {
        $sourceRow = $match['source'];
        $databaseRow = $match['database'];
        $updateBin->execute([
            $sourceRow['reference'], $sourceRow['delivered'], $sourceRow['supplier'], $sourceRow['remarks'],
            $databaseRow['item_id'], $databaseRow['reference_no'], $batch,
        ]);
        $updateStock->execute([
            $sourceRow['reference'], $sourceRow['supplier'], $sourceRow['delivered'],
            $sourceRow['remarks'], $databaseRow['id'], $batch,
        ]);
    }
    $pdo->commit();
    echo 'Enriched ' . count($matches) . " existing stock-in rows; quantities were not changed.\n";
} catch (Throwable $error) {
    $pdo->rollBack();
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
