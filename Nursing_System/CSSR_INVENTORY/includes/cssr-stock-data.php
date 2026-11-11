<?php
// Combine two existing stores for display, without copying a balance between them.
function cssrAvailableStockRows(PDO $db,string $status='AVAILABLE'): array
{
    $sql="SELECT * FROM (
        SELECT id,item_id,item_name,item_description,lot_number,expiry_date,quantity,unit_type,classification,procurement_type,source_location_code,status,'legacy' record_source FROM cssr_available_stock
        UNION ALL
        SELECT l.id,l.item_id,i.item_name,i.item_description,l.lot_number,l.expiry_date,
            CASE WHEN l.status='AVAILABLE' AND l.quantity_available>0 AND (l.expiry_date IS NULL OR l.expiry_date>=CURRENT_DATE)
                THEN l.quantity_available ELSE l.quantity_available+l.quantity_quarantined END AS quantity,
            i.unit AS unit_type,i.category AS classification,i.procurement_type,
            COALESCE((SELECT m.from_location_code FROM stock_location_movements m WHERE m.to_location_id=l.id AND m.movement_type IN ('STORAGE ROOM 1 TRANSFER','STORAGE ROOM 2 TRANSFER','STORAGE ROOM 3 TRANSFER') AND m.status='POSTED' ORDER BY m.id DESC LIMIT 1),'CSSR') AS source_location_code,
            CASE WHEN l.quantity_available+l.quantity_quarantined<=0 THEN 'EMPTY'
                WHEN l.status<>'AVAILABLE' OR (l.expiry_date IS NOT NULL AND l.expiry_date<CURRENT_DATE) OR l.quantity_available<=0 THEN 'HOLD'
                ELSE 'AVAILABLE' END AS status,'lot' record_source
        FROM item_lots l JOIN items i ON i.id=l.item_id WHERE l.storage_location='CSSR' AND l.status<>'DELETED' AND i.status='Active'
    ) stocks";
    $params=[];if($status!==''){$sql.=' WHERE status=?';$params[]=$status;}
    $sql.=' ORDER BY item_name,expiry_date IS NULL,expiry_date,lot_number';
    $statement=$db->prepare($sql);$statement->execute($params);return $statement->fetchAll(PDO::FETCH_ASSOC);
}
