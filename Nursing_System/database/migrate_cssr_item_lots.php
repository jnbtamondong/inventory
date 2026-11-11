<?php
declare(strict_types=1);

$pdo = new PDO('mysql:host=127.0.0.1;dbname=cssr_inventory;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->beginTransaction();
try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS item_lots (id INT AUTO_INCREMENT PRIMARY KEY,item_id INT NOT NULL,lot_number VARCHAR(120) NOT NULL,expiry_date DATE NULL,quantity_available INT NOT NULL DEFAULT 0,status ENUM("AVAILABLE","DEPLETED") NOT NULL DEFAULT "AVAILABLE",created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,CONSTRAINT fk_item_lots_item FOREIGN KEY (item_id) REFERENCES items(id),UNIQUE KEY uq_item_lot_expiry (item_id,lot_number,expiry_date),INDEX idx_item_lots_fefo (item_id,status,expiry_date,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    foreach (['stock_in','stock_out'] as $table) {
        $check=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME="item_lot_id"');
        $check->execute([$table]);
        if (!(int)$check->fetchColumn()) {
            $pdo->exec("ALTER TABLE {$table} ADD item_lot_id INT NULL AFTER item_id, ADD INDEX idx_{$table}_item_lot (item_lot_id), ADD CONSTRAINT fk_{$table}_item_lot FOREIGN KEY (item_lot_id) REFERENCES item_lots(id) ON DELETE SET NULL");
        }
    }
    $columnExists = static function (string $table, string $column) use ($pdo): bool {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    };
    $lotColumns = [
        'supplier' => 'VARCHAR(150) NOT NULL DEFAULT "" AFTER expiry_date',
        'storage_location' => 'VARCHAR(120) NOT NULL DEFAULT "" AFTER supplier',
        'quantity_received' => 'INT NOT NULL DEFAULT 0 AFTER storage_location',
        'quantity_issued' => 'INT NOT NULL DEFAULT 0 AFTER quantity_received',
        'quantity_returned' => 'INT NOT NULL DEFAULT 0 AFTER quantity_issued',
    ];
    foreach ($lotColumns as $column => $definition) {
        if (!$columnExists('item_lots', $column)) {
            $pdo->exec("ALTER TABLE item_lots ADD {$column} {$definition}");
        }
    }
    if (!$columnExists('stock_out', 'quantity_returned')) {
        $pdo->exec('ALTER TABLE stock_out ADD quantity_returned INT NOT NULL DEFAULT 0 AFTER quantity_issued');
    }
    $pdo->exec('ALTER TABLE item_lots MODIFY status VARCHAR(30) NOT NULL DEFAULT "AVAILABLE"');
    if (!(int)$pdo->query('SELECT COUNT(*) FROM item_lots')->fetchColumn()) {
        $items=$pdo->query('SELECT id,lot_number,expiry_date,current_stock FROM items ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $inbound=$pdo->prepare('SELECT lot_number,expiry_date,SUM(quantity_received) quantity FROM stock_in WHERE item_id=? GROUP BY lot_number,expiry_date ORDER BY CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END,expiry_date');
        $insert=$pdo->prepare('INSERT INTO item_lots(item_id,lot_number,expiry_date,quantity_available,status) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE quantity_available=quantity_available+VALUES(quantity_available),status=IF(quantity_available+VALUES(quantity_available)>0,"AVAILABLE","DEPLETED")');
        foreach($items as $item){
            $inbound->execute([(int)$item['id']]); $lots=$inbound->fetchAll(PDO::FETCH_ASSOC);
            $current=max(0,(int)$item['current_stock']);
            $total=array_sum(array_map(static fn($lot)=>(int)$lot['quantity'],$lots));
            $consumed=max(0,$total-$current); $target=$current;
            foreach($lots as $lot){
                $received=(int)$lot['quantity']; $deduct=min($received,$consumed); $available=min($received-$deduct,$target);
                $consumed-=$deduct; $target-=$available;
                $number=trim((string)($lot['lot_number']??''))?:'NO LOT';
                $insert->execute([(int)$item['id'],$number,$lot['expiry_date']?:null,$available,$available>0?'AVAILABLE':'DEPLETED']);
            }
            if($target>0){$number=trim((string)($item['lot_number']??''))?:'LEGACY BALANCE';$insert->execute([(int)$item['id'],$number,$item['expiry_date']?:null,$target,'AVAILABLE']);}
        }
        $pdo->exec('UPDATE stock_in s JOIN item_lots l ON l.item_id=s.item_id AND l.lot_number=COALESCE(NULLIF(TRIM(s.lot_number),""),"NO LOT") AND l.expiry_date <=> s.expiry_date SET s.item_lot_id=l.id');
    }
    $pdo->exec('UPDATE item_lots l JOIN items i ON i.id=l.item_id SET l.storage_location=COALESCE(NULLIF(l.storage_location,""),i.bin_location,"")');
    $pdo->exec('UPDATE item_lots l LEFT JOIN (SELECT item_lot_id,MAX(supplier) supplier,SUM(quantity_received) received FROM stock_in WHERE item_lot_id IS NOT NULL GROUP BY item_lot_id) s ON s.item_lot_id=l.id SET l.supplier=COALESCE(NULLIF(l.supplier,""),s.supplier,""),l.quantity_received=GREATEST(l.quantity_received,COALESCE(s.received,0),l.quantity_available),l.quantity_issued=GREATEST(l.quantity_issued,GREATEST(l.quantity_received,COALESCE(s.received,0),l.quantity_available)+l.quantity_returned-l.quantity_available)');
    $oldIndex = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="item_lots" AND INDEX_NAME="uq_item_lot_expiry"')->fetchColumn();
    if ($oldIndex) {
        $pdo->exec('ALTER TABLE item_lots DROP INDEX uq_item_lot_expiry');
    }
    $newIndex = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="item_lots" AND INDEX_NAME="uq_item_lot_identity"')->fetchColumn();
    if (!$newIndex) {
        $pdo->exec('ALTER TABLE item_lots ADD UNIQUE KEY uq_item_lot_identity (item_id,lot_number,expiry_date,supplier,storage_location)');
    }
    if($pdo->inTransaction())$pdo->commit(); echo "Item-lot migration completed.\n";
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
