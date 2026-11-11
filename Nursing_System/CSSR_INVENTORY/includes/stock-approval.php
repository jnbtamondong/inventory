<?php
declare(strict_types=1);

/** Keep the lot lock until the caller commits its reservation or rolls back. */
function cssrLockApprovalStock(PDO $connection, int $lotId, int $itemId, int $quantity, string $itemName): array
{
    if (!$connection->inTransaction()) {
        throw new LogicException('Stock approval requires an active transaction.');
    }
    if ($quantity <= 0) {
        throw new InvalidArgumentException('Approval quantity must be positive.');
    }
    $statement = $connection->prepare('SELECT id, lot_number, quantity_available FROM item_lots WHERE id=? AND item_id=? AND storage_location="CSSR" AND status="AVAILABLE" AND lot_number<>"UNIDENTIFIED / PENDING" AND (expiry_date IS NULL OR expiry_date>=CURDATE()) FOR UPDATE');
    $statement->execute([$lotId, $itemId]);
    $lot = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$lot) {
        throw new RuntimeException('The selected lot for ' . $itemName . ' is not available.');
    }
    // A normal SELECT may use a snapshot from before a competing approval committed.
    // This locking read sees the latest reservations after waiting for the lot lock.
    $statement = $connection->prepare('SELECT quantity FROM stock_reservations WHERE item_lot_id=? AND status="ACTIVE" AND (expires_at IS NULL OR expires_at>NOW()) FOR UPDATE');
    $statement->execute([$lotId]);
    $reserved = array_sum(array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN)));
    if ($quantity > (int) $lot['quantity_available'] - $reserved) {
        throw new RuntimeException('Cannot approve ' . $itemName . ': insufficient unreserved stock in the selected lot. Another request may have reserved the available stock.');
    }
    return $lot;
}
