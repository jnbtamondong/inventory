<?php
declare(strict_types=1);

$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=cssr_inventory;charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$month = date('Ym');
$date = date('Y-m-d');
$referenceIn = 'SAMPLE-IN-' . $month;
$referenceOut = 'SAMPLE-OUT-' . $month;
$supplier = 'SAMPLE SUPPLIER';
$department = 'SAMPLE DEPARTMENT';
$location = 'SAMPLE STORAGE';
$user = 'SYSTEM SAMPLE DATA';

if ((int) $pdo->prepare('SELECT COUNT(*) FROM stock_in WHERE reference_no=?')->execute([$referenceIn])) {
    $check = $pdo->prepare('SELECT COUNT(*) FROM stock_in WHERE reference_no=?');
    $check->execute([$referenceIn]);
    if ((int) $check->fetchColumn() > 0) {
        echo "Sample data for {$month} already exists. No duplicate rows were created.\n";
        exit;
    }
}

$items = $pdo->query("SELECT id,item_code,item_description,current_stock FROM items WHERE status='Active' ORDER BY id LIMIT 25")->fetchAll();
if (count($items) < 25) {
    throw new RuntimeException('At least 25 active items are required to create the dashboard sample.');
}

$pdo->beginTransaction();
try {
    $findLot = $pdo->prepare('SELECT id FROM item_lots WHERE item_id=? AND lot_number=? AND expiry_date=? AND supplier=? AND storage_location=? FOR UPDATE');
    $insertLot = $pdo->prepare('INSERT INTO item_lots (item_id,lot_number,expiry_date,supplier,storage_location,quantity_received,quantity_issued,quantity_returned,quantity_available,status) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $updateLot = $pdo->prepare('UPDATE item_lots SET quantity_received=quantity_received+?,quantity_issued=quantity_issued+?,quantity_available=quantity_available+?,status="AVAILABLE" WHERE id=?');
    $updateItem = $pdo->prepare('UPDATE items SET current_stock=current_stock+? WHERE id=?');
    $insertIn = $pdo->prepare('INSERT INTO stock_in (item_id,item_lot_id,reference_no,supplier,quantity_received,lot_number,expiry_date,received_date,received_by,remarks) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $insertOut = $pdo->prepare('INSERT INTO stock_out (item_id,item_lot_id,reference_no,department,quantity_issued,quantity_returned,lot_number,issued_date,issued_by,received_by,remarks) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    $insertBin = $pdo->prepare('INSERT INTO bin_cards (item_id,transaction_date,reference_no,transaction_type,lot_number,expiry_date,received_qty,issued_qty,balance_qty,department,encoded_by,remarks) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
    $insertCard = $pdo->prepare('INSERT INTO stock_cards (item_id,transaction_date,reference_no,transaction_type,lot_number,expiry_date,received_qty,issued_qty,balance_qty,from_location,to_location,encoded_by,remarks) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');

    foreach ($items as $index => $item) {
        $rank = $index + 1;
        $itemId = (int) $item['id'];
        $inbound = 500 + ($rank * 40);
        $issued = 100 + ($rank * 12);
        $net = $inbound - $issued;
        $openingBalance = (int) $item['current_stock'];
        $afterInbound = $openingBalance + $inbound;
        $afterIssued = $afterInbound - $issued;
        $lotNumber = 'SAMPLE-' . $month . '-' . str_pad((string) $itemId, 4, '0', STR_PAD_LEFT);
        $expiry = date('Y-m-d', strtotime('+2 years'));

        $findLot->execute([$itemId, $lotNumber, $expiry, $supplier, $location]);
        $lotId = (int) ($findLot->fetchColumn() ?: 0);
        if ($lotId > 0) {
            $updateLot->execute([$inbound, $issued, $net, $lotId]);
        } else {
            $insertLot->execute([$itemId, $lotNumber, $expiry, $supplier, $location, $inbound, $issued, 0, $net, 'AVAILABLE']);
            $lotId = (int) $pdo->lastInsertId();
        }

        $updateItem->execute([$net, $itemId]);
        $insertIn->execute([$itemId, $lotId, $referenceIn, $supplier, $inbound, $lotNumber, $expiry, $date, $user, 'Dashboard sample data']);
        $insertOut->execute([$itemId, $lotId, $referenceOut, $department, $issued, 0, $lotNumber, $date, $user, $user, 'Dashboard sample data']);
        $insertBin->execute([$itemId, $date, $referenceIn, 'STOCK IN', $lotNumber, $expiry, $inbound, 0, $afterInbound, $supplier, $user, 'Dashboard sample data']);
        $insertBin->execute([$itemId, $date, $referenceOut, 'STOCK OUT', $lotNumber, $expiry, 0, $issued, $afterIssued, $department, $user, 'Dashboard sample data']);
        $insertCard->execute([$itemId, $date, $referenceIn, 'STOCK IN', $lotNumber, $expiry, $inbound, 0, $afterInbound, $supplier, $location, $user, 'Dashboard sample data']);
        $insertCard->execute([$itemId, $date, $referenceOut, 'STOCK OUT', $lotNumber, $expiry, 0, $issued, $afterIssued, $location, $department, $user, 'Dashboard sample data']);
    }

    $pdo->commit();
    echo "Created current-month sample inbound and outbound data for 25 items.\n";
    echo "References: {$referenceIn}, {$referenceOut}\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
