<?php
declare(strict_types=1);

require __DIR__ . '/../CSSR_INVENTORY/config/database.php';

$inputFile = $argv[1] ?? '';
$commit = in_array('--commit', $argv, true);
if ($inputFile === '' || !is_file($inputFile)) {
    fwrite(STDERR, "Usage: php import_minimum_stock.php <tab-separated-file> [--commit]\n");
    exit(1);
}

$handle = fopen($inputFile, 'rb');
if ($handle === false) {
    throw new RuntimeException('Unable to open input file.');
}

$rows = [];
$blankValues = [];
$lineNumber = 0;
while (($columns = fgetcsv($handle, 0, "\t")) !== false) {
    $lineNumber++;
    $name = trim((string) ($columns[0] ?? ''));
    $minimum = trim((string) ($columns[1] ?? ''));
    if ($name === '') {
        continue;
    }
    if ($minimum === '') {
        $blankValues[] = ['line' => $lineNumber, 'item' => $name];
        continue;
    }
    if (!ctype_digit($minimum)) {
        throw new RuntimeException("Invalid minimum stock on line {$lineNumber}: {$minimum}");
    }
    $rows[] = ['line' => $lineNumber, 'item' => $name, 'minimum' => (int) $minimum];
}
fclose($handle);

$pdo = db();
$find = $pdo->prepare('SELECT id, item_code, item_description, minimum_stock FROM items WHERE UPPER(TRIM(item_description))=UPPER(TRIM(?)) AND status="Active"');
$matched = [];
$errors = [];
$seen = [];
foreach ($rows as $row) {
    $key = strtoupper($row['item']);
    if (isset($seen[$key])) {
        $errors[] = ['line' => $row['line'], 'item' => $row['item'], 'reason' => 'duplicate input name'];
        continue;
    }
    $seen[$key] = true;
    $find->execute([$row['item']]);
    $items = $find->fetchAll();
    if (count($items) !== 1) {
        $errors[] = ['line' => $row['line'], 'item' => $row['item'], 'reason' => 'active matches=' . count($items)];
        continue;
    }
    $matched[] = ['row' => $row, 'item' => $items[0]];
}

echo 'Valid values: ' . count($rows) . PHP_EOL;
echo 'Matched: ' . count($matched) . PHP_EOL;
echo 'Blank values preserved: ' . count($blankValues) . PHP_EOL;
echo 'Errors: ' . count($errors) . PHP_EOL;
foreach ($blankValues as $blank) {
    echo "BLANK line {$blank['line']} | {$blank['item']}\n";
}
foreach ($errors as $error) {
    echo "ERROR line {$error['line']} | {$error['item']} | {$error['reason']}\n";
}

if (!$commit) {
    exit($errors ? 2 : 0);
}
if ($errors) {
    echo "WARNING: Unmatched entries will be skipped.\n";
}

$pdo->exec('CREATE TABLE IF NOT EXISTS minimum_stock_backup_20260915 AS SELECT id, item_code, item_description, minimum_stock, NOW() AS backed_up_at FROM items WHERE 1=0');
$pdo->beginTransaction();
try {
    $backup = $pdo->prepare('INSERT INTO minimum_stock_backup_20260915 (id,item_code,item_description,minimum_stock,backed_up_at) VALUES (?,?,?,?,NOW())');
    $update = $pdo->prepare('UPDATE items SET minimum_stock=? WHERE id=?');
    $changed = 0;
    foreach ($matched as $match) {
        $item = $match['item'];
        $minimum = $match['row']['minimum'];
        if ((int) $item['minimum_stock'] === $minimum) {
            continue;
        }
        $backup->execute([(int) $item['id'], $item['item_code'], $item['item_description'], (int) $item['minimum_stock']]);
        $update->execute([$minimum, (int) $item['id']]);
        $changed++;
    }
    $pdo->commit();
    echo "COMMITTED: {$changed} minimum stock values changed.\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}
