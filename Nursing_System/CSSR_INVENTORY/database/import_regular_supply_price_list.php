<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$source = $argv[1] ?? '';
if ($source === '' || !is_file($source)) {
    fwrite(STDERR, "Usage: php import_regular_supply_price_list.php <tab-separated-file>\n");
    exit(1);
}

db()->exec(
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

function price_value(string $value): float
{
    $clean = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', trim($value)));
    return is_numeric($clean) ? round((float) $clean, 2) : 0.0;
}

$lines = file($source, FILE_IGNORE_NEW_LINES);
$headerIndex = null;
foreach ($lines as $index => $line) {
    if (str_starts_with(trim($line), 'ITEM NAME')) {
        $headerIndex = $index;
        break;
    }
}
if ($headerIndex === null) {
    throw new RuntimeException('Price-list column header was not found.');
}

$upsert = db()->prepare(
    'INSERT INTO item_price_list
        (item_name,item_description,lot_number,expiration_text,unit_type,classification,unit_price,markup_cost,total_price,selling_price,source_as_of,source_hash)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
     ON DUPLICATE KEY UPDATE item_name=VALUES(item_name),item_description=VALUES(item_description),lot_number=VALUES(lot_number),expiration_text=VALUES(expiration_text),unit_type=VALUES(unit_type),classification=VALUES(classification),unit_price=VALUES(unit_price),markup_cost=VALUES(markup_cost),total_price=VALUES(total_price),selling_price=VALUES(selling_price),source_as_of=VALUES(source_as_of)'
);

$imported = 0;
$skipped = 0;
db()->beginTransaction();
try {
    foreach (array_slice($lines, $headerIndex + 1) as $line) {
        if (trim($line) === '') { continue; }
        $columns = array_map('trim', explode("\t", $line));
        $columns = array_pad($columns, 10, '');
        [$name,$description,$lot,$expiration,$unit,$classification,$unitPrice,$markup,$total,$selling] = array_slice($columns, 0, 10);
        if ($name === '' || strtoupper($name) === 'ITEM NAME') { $skipped++; continue; }
        $identity = implode('|', array_map(fn($value) => mb_strtoupper(trim((string) $value)), [$name,$description,$lot,$expiration,$unit,$classification,$unitPrice,$markup,$total,$selling]));
        $upsert->execute([$name,$description,$lot,$expiration,$unit,$classification,price_value($unitPrice),price_value($markup),price_value($total),price_value($selling),'2026-08-12',hash('sha256',$identity)]);
        $imported++;
    }
    db()->commit();
} catch (Throwable $error) {
    if (db()->inTransaction()) { db()->rollBack(); }
    throw $error;
}

echo "Imported or updated {$imported} price rows; skipped {$skipped}.\n";
