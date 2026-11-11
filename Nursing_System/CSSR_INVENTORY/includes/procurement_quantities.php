<?php
declare(strict_types=1);

function item_procurement_quantities(): array
{
    // Procurement may not have been initialized on a new installation yet.
    if (!column_exists('procurement_request_items', 'procurement_status')
        || !column_exists('procurement_requests', 'procurement_pr_no')) {
        return [];
    }

    $rows = db_all("SELECT ri.item_id,
            SUM(GREATEST(ri.quantity_requested - ri.quantity_delivered, 0)) AS procurement_qty
        FROM procurement_request_items ri
        JOIN procurement_requests r ON r.id = ri.request_id
        WHERE r.status NOT IN ('REJECTED', 'CANCELLED', 'CANCELED')
          AND (
            (ri.procurement_status IN ('PENDING DELIVERY', 'PARTIALLY DELIVERED')
                AND TRIM(COALESCE(ri.winning_supplier, '')) <> '')
            OR (ri.procurement_status = 'AWAITING QUOTATION'
                AND TRIM(COALESCE(r.procurement_pr_no, '')) = ''
                AND r.status IN ('AWAITING QUOTATION', 'AWAITING DELIVERY'))
          )
        GROUP BY ri.item_id");

    return array_column($rows, 'procurement_qty', 'item_id');
}
