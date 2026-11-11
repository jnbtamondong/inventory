<?php
declare(strict_types=1);

$source = $argv[1] ?? '';
if ($source === '' || !is_file($source)) {
    fwrite(STDERR, "Usage: php import_cssr_supplies.php <supply-list.txt>\n");
    exit(1);
}

$lines = file($source, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$pdo = new PDO(
    'mysql:host=localhost;dbname=cssr_inventory;charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$pdo->beginTransaction();
try {
    $existing = $pdo->query('SELECT LOWER(TRIM(item_description)) FROM items')
        ->fetchAll(PDO::FETCH_COLUMN);
    $seen = array_fill_keys($existing, true);
    $maximumCode = (int) $pdo->query(
        "SELECT COALESCE(MAX(CAST(SUBSTRING(item_code, 6) AS UNSIGNED)), 0)
         FROM items
         WHERE item_code REGEXP '^CSSR-[0-9]+$'"
    )->fetchColumn();

    $insert = $pdo->prepare(
        "INSERT INTO items
            (item_code, item_description, category, unit, brand, supplier,
             lot_number, expiry_date, bin_location, minimum_stock, current_stock, status)
         VALUES (?, ?, 'Medical Supplies', 'piece', '', '', '', NULL, '', 0, 0, 'Active')"
    );
    $codeExists = $pdo->prepare('SELECT COUNT(*) FROM items WHERE item_code = ?');

    $added = 0;
    $skipped = 0;
    foreach ($lines as $line) {
        $name = trim($line);
        if ($name === '') {
            continue;
        }

        $key = strtolower($name);
        if (isset($seen[$key])) {
            $skipped++;
            continue;
        }

        do {
            $maximumCode++;
            $itemCode = sprintf('CSSR-%06d', $maximumCode);
            $codeExists->execute([$itemCode]);
        } while ((int) $codeExists->fetchColumn() > 0);

        $insert->execute([$itemCode, $name]);
        $seen[$key] = true;
        $added++;
    }

    $pdo->commit();
    echo "added={$added} skipped={$skipped} total_lines=" . count($lines) . PHP_EOL;
} catch (Throwable $error) {
    $pdo->rollBack();
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
