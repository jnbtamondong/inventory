<?php
declare(strict_types=1);

$source = $argv[1] ?? '';
$commit = in_array('--commit', $argv, true);

if ($source === '' || !is_file($source)) {
    fwrite(STDERR, "Usage: php classify_cssr_items.php <master-list-tab-file> [--commit]\n");
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

function normalized_classification(string $value): ?string
{
    $value = strtoupper(clean_text($value));
    return match ($value) {
        'MEDICAL SUPPLY', 'MEDICAL SUPPLIES' => 'MEDICAL SUPPLIES',
        'MEDICAL INSTRUMENT', 'MEDICAL INSTRUMENTS' => 'MEDICAL INSTRUMENTS',
        default => null,
    };
}

function heuristic_classification(string $name): string
{
    $name = strtoupper($name);
    $instrumentPatterns = [
        'ADSON',
        'BACKHAUS',
        'BASIN',
        'BLADE HANDLE',
        'BLADE HOLDER',
        'BOWL',
        'BP CUFF WITH BLADDER',
        'FORCEP',
        'FORCEPS',
        'FORCEP JAR',
        'FRAZER SUCTION',
        'INSTRUMENT TRAY',
        'IRIS SCISSOR',
        'KELLY CURVED',
        'KELLY FORCEPS',
        'KELLY STRAIGHT',
        'KIDNEY BASIN',
        'LARYNGOSCOPE',
        'LONG THUMB FORCEPS',
        'LONG TISSUE FORCEPS',
        'MALLEABLE',
        'MAYO SCISSORS',
        'MAYO TABLE',
        'METZENBAUM SCISSORS',
        'MIXTER',
        'MORTAR AND PESTLE',
        'MOSQUITO',
        'OCHSNER',
        'OVUM FORCEP',
        'PENNINGTON FORCEP',
        'PIPER OBSTETRICAL FORCEP',
        'RANDAL',
        'RECTAL RETRACTOR',
        'RETRACTOR',
        'RUSSIAN TISSUE FORCEP',
        'SENN RETRACTOR',
        'SIMPSON OBSTETRICAL FORCEP',
        'STETHOSCOPE',
    ];

    foreach ($instrumentPatterns as $pattern) {
        if (str_contains($name, $pattern)) {
            return 'MEDICAL INSTRUMENTS';
        }
    }

    return 'MEDICAL SUPPLIES';
}

$counts = [];
$handle = fopen($source, 'rb');
if (!$handle) {
    throw new RuntimeException('Unable to open classification source file.');
}

$headerFound = false;
while (($columns = fgetcsv($handle, 0, "\t")) !== false) {
    $columns = array_map(static fn($value) => clean_text((string) $value), $columns);
    if (!$headerFound) {
        if (isset($columns[0]) && strtoupper($columns[0]) === 'ITEM NAME') {
            $headerFound = true;
        }
        continue;
    }

    $columns = array_pad($columns, 5, '');
    $name = $columns[0];
    $classification = normalized_classification($columns[4]);
    if ($name === '' || $classification === null) {
        continue;
    }
    $key = normalized_name($name);
    $counts[$key][$classification] = ($counts[$key][$classification] ?? 0) + 1;
}
fclose($handle);

if (!$headerFound) {
    throw new RuntimeException('ITEM NAME header was not found.');
}

$items = $pdo->query('SELECT id,item_description FROM items ORDER BY item_description')->fetchAll();
$updates = [];
$sourceMatches = 0;
$heuristicMatches = 0;
foreach ($items as $item) {
    $key = normalized_name($item['item_description']);
    if (isset($counts[$key])) {
        arsort($counts[$key]);
        $classification = (string) array_key_first($counts[$key]);
        $sourceMatches++;
    } else {
        $classification = heuristic_classification($item['item_description']);
        $heuristicMatches++;
    }
    $updates[] = [(int) $item['id'], $item['item_description'], $classification];
}

$summary = ['MEDICAL INSTRUMENTS' => 0, 'MEDICAL SUPPLIES' => 0];
foreach ($updates as $update) {
    $summary[$update[2]]++;
}

echo 'items=' . count($updates) . ' source_matches=' . $sourceMatches . ' heuristic_matches=' . $heuristicMatches . PHP_EOL;
echo 'MEDICAL INSTRUMENTS=' . $summary['MEDICAL INSTRUMENTS'] . ' MEDICAL SUPPLIES=' . $summary['MEDICAL SUPPLIES'] . PHP_EOL;

if (!$commit) {
    foreach ($updates as $update) {
        if ($update[2] === 'MEDICAL INSTRUMENTS') {
            echo "INSTRUMENT: {$update[1]}\n";
        }
    }
    echo "Dry run only. Add --commit to classify items.\n";
    exit(0);
}

$stmt = $pdo->prepare('UPDATE items SET category=? WHERE id=?');
$pdo->beginTransaction();
try {
    foreach ($updates as $update) {
        $stmt->execute([$update[2], $update[0]]);
    }
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $error;
}

echo "Classified " . count($updates) . " items.\n";
