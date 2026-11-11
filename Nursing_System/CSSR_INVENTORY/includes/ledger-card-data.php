<?php
// Shared card data for single-item and bulk printing.
function cssrLedgerSelectedItems(array $items,$requested): array
{
    $ids=is_array($requested)?array_map('intval',array_filter($requested,'is_scalar')):[];
    return array_values(array_filter($items,static fn($item)=>in_array((int)$item['id'],$ids,true)));
}
function cssrLedgerPrintError(string $from,string $to,array $selected): string
{
    if(!$selected)return 'Select at least one item to print.';
    foreach([$from,$to] as $date){if($date==='')continue;$parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$parsed||$parsed->format('Y-m-d')!==$date)return 'Enter valid filter dates.';}
    return $from!==''&&$to!==''&&$from>$to?'The From date must be on or before the To date.':'';
}

function cssrBinCardData(array $items,int $itemId,string $fromDate,string $toDate): array
{
$isBlankForm=$itemId===0;
$rows = [];
if (!$isBlankForm && !empty($itemId)) {
    // Always load the complete ledger first. The running balance must include
    // transactions before the selected date range.
    $sql = 'SELECT b.*, i.item_code, i.item_description, i.supplier, i.unit, i.category, i.bin_location
            FROM bin_cards b
            JOIN items i ON i.id=b.item_id
            WHERE b.item_id=?
            ORDER BY b.transaction_date ASC, b.id ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute([(int) $itemId]);
    $rows = $stmt->fetchAll();
    // Show deliveries physically received by CSSR but still held from usable
    // stock. Equal received/held quantities keep the available balance at zero.
    $heldRows = db_all(
        'SELECT s.id, s.received_date AS transaction_date,
                CONCAT(s.reference_no, " - ", s.receiving_status) AS reference_no,
                s.receiving_status AS transaction_type, s.lot_number,
                s.expiry_date, s.quantity_received AS received_qty,
                s.quantity_received AS issued_qty, 0 AS balance_qty,
                s.supplier AS department, s.received_by AS encoded_by,
                CONCAT("Inbound delivery held from issuance: ", s.receiving_status) AS remarks,
                1 AS is_inspection_hold
         FROM stock_in s
         JOIN item_lots l ON l.id=s.item_lot_id
         WHERE s.item_id=? AND l.storage_location="CSSR"
           AND s.receiving_status IN ("FOR INSPECTION","ADVANCE DELIVERY")
         ORDER BY s.received_date,s.id',
        [(int) $itemId]
    );
    foreach ($heldRows as $heldRow) {
        $heldRow['id'] = -1 * (int) $heldRow['id'];
        $rows[] = $heldRow;
    }
}
$selectedItem = null;
if (!$isBlankForm && !empty($itemId)) {
    foreach ($items as $item) {
        if ((int) $item['id'] === (int) $itemId) {
            $selectedItem = $item;
            break;
        }
    }
}
$displayItem = $selectedItem;
$ledgerRowTarget = 48;
$cssrLotRows = [];
if (!$isBlankForm && $selectedItem) {
    $cssrLotRows = db_all(
        'SELECT lot_number, expiry_date, storage_location, quantity_available
         FROM item_lots
         WHERE item_id=?
           AND storage_location="CSSR"
           AND quantity_available>0
         ORDER BY CASE WHEN lot_number="UNIDENTIFIED / PENDING" THEN 1 ELSE 0 END, expiry_date IS NULL, expiry_date, id',
        [(int) $selectedItem['id']]
    );
}
foreach ($cssrLotRows as $cssrLot) {
    $hasDisplayedLot = false;
    foreach ($rows as $row) {
        if (($row['lot_number'] ?? '') === ($cssrLot['lot_number'] ?? '')) {
            $hasDisplayedLot = true;
            break;
        }
    }
    if (!$hasDisplayedLot) {
        $rows[] = [
            'transaction_date' => date('Y-m-d'),
            'transaction_type' => 'CSSR OPENING BALANCE',
            'reference_no' => 'CSSR STORAGE BALANCE',
            'department' => '',
            'lot_number' => $cssrLot['lot_number'],
            'received_qty' => (int) $cssrLot['quantity_available'],
            'issued_qty' => 0,
            'balance_qty' => (int) $cssrLot['quantity_available'],
            'is_pending_balance' => true,
        ];
    }
}

// Rebuild every displayed balance using the ledger rule:
// previous balance + received - issued.
usort($rows, static function (array $left, array $right): int {
    $dateComparison = strcmp((string) ($left['transaction_date'] ?? ''), (string) ($right['transaction_date'] ?? ''));
    if ($dateComparison !== 0) {
        return $dateComparison;
    }
    return ((int) ($left['id'] ?? PHP_INT_MAX)) <=> ((int) ($right['id'] ?? PHP_INT_MAX));
});
$runningBalance = 0;
$filteredRows = [];
foreach ($rows as $row) {
    $runningBalance += (int) ($row['received_qty'] ?? 0);
    $runningBalance -= (int) ($row['issued_qty'] ?? 0);
    $row['balance_qty'] = $runningBalance;

    $transactionDate = substr((string) ($row['transaction_date'] ?? ''), 0, 10);
    if ($fromDate !== '' && $transactionDate < $fromDate) {
        continue;
    }
    if ($toDate !== '' && $transactionDate > $toDate) {
        continue;
    }
    $filteredRows[] = $row;
}
$rows = $filteredRows;

$rowPages = array_chunk($rows, $ledgerRowTarget);
if (!$rowPages) {
    $rowPages = [[]];
}
return compact('selectedItem','displayItem','ledgerRowTarget','rowPages');
}

function cssrStockCardData(array $items,int $itemId,string $from,string $to): array
{
$isBlankForm=$itemId===0;
$ledgerScopeLabel = '';
$selectedItem = null;
if (!$isBlankForm && !empty($itemId)) {
    foreach ($items as $item) {
        if ((int) $item['id'] === (int) $itemId) {
            $selectedItem = $item;
            break;
        }
    }
}

$rows = [];

if (!$isBlankForm && $selectedItem) {
    $itemRooms = db_all(
        'SELECT storage_location, SUM(quantity_available) AS quantity_available
         FROM item_lots
         WHERE item_id=? AND storage_location IN ("STORAGE ROOM 1","STORAGE ROOM 2","STORAGE ROOM 3")
         GROUP BY storage_location
         ORDER BY storage_location',
        [(int) $selectedItem['id']]
    );
    $stockedRooms = array_filter($itemRooms, static fn($room) => (int) $room['quantity_available'] > 0);
    // Depleted items retain their recorded rooms; an unselected blank card stays empty.
    $ledgerScopeLabel = implode(' / ', array_column($stockedRooms ?: $itemRooms, 'storage_location'));
    if ($ledgerScopeLabel === '' && in_array($selectedItem['bin_location'] ?? '', ['STORAGE ROOM 1','STORAGE ROOM 2','STORAGE ROOM 3'], true)) {
        $ledgerScopeLabel = $selectedItem['bin_location'];
    }
    $where = ['s.item_id=?', '(s.from_location IN ("STORAGE ROOM 1","STORAGE ROOM 2","STORAGE ROOM 3") OR s.to_location IN ("STORAGE ROOM 1","STORAGE ROOM 2","STORAGE ROOM 3") OR (s.from_location IS NULL AND s.to_location IS NULL))'];
    $params = [(int) $selectedItem['id']];
    $historyRows = db_all(
        'SELECT s.*
         FROM stock_cards s
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY s.transaction_date ASC, s.id ASC',
        $params
    );

    // Calculate the Storage Room ledger from its actual inbound/outbound movement.
    // Process rows before the requested date too, so the first displayed balance is correct.
    $runningBalance = 0;
    foreach ($historyRows as $historyRow) {
        $runningBalance += (int) ($historyRow['received_qty'] ?? 0);
        $runningBalance -= (int) ($historyRow['issued_qty'] ?? 0);
        $historyRow['balance_qty'] = $runningBalance;

        $transactionDate = substr((string) ($historyRow['transaction_date'] ?? ''), 0, 10);
        if ($from !== '' && $transactionDate < $from) {
            continue;
        }
        if ($to !== '' && $transactionDate > $to) {
            continue;
        }
        $rows[] = $historyRow;
    }

}

$ledgerRowTarget = 35;
$rowPages = array_chunk($rows, $ledgerRowTarget);
if (!$rowPages) {
    $rowPages = [[]];
}

return compact('selectedItem','ledgerRowTarget','rowPages','ledgerScopeLabel');
}
