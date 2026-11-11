<?php
declare(strict_types=1);
function cssrStockPageQuery(PDO $db,string $sql,array $params=[]): array { $s=$db->prepare($sql);$s->execute($params);return $s->fetchAll(PDO::FETCH_ASSOC); }

function cssrStockPageData(PDO $db,array $input=[],?DateTimeImmutable $now=null): array
{
    $now=$now??new DateTimeImmutable('now',new DateTimeZone('Asia/Manila'));
    $today=$now->format('Y-m-d');$timestamp=$now->format('Y-m-d H:i:s');
    $month=is_scalar($input['month']??null)?(string)$input['month']:$now->format('Y-m');
    $start=DateTimeImmutable::createFromFormat('!Y-m',$month);
    if(!$start||$start->format('Y-m')!==$month||$month>$now->format('Y-m')||$month<'1900-01'){$month=$now->format('Y-m');$start=DateTimeImmutable::createFromFormat('!Y-m',$month);}
    $end=$start->modify('+1 month')->format('Y-m-d');$startDate=$start->format('Y-m-d');
    // Never use items.current_stock or any storage-room balance for this page.
    // quantity_available is the posted remainder after receipts/transfers, issues,
    // returns, reversals and adjustments. Subtracting those events again would double-count them.
    $items=cssrStockPageQuery($db,"SELECT i.id,i.item_code,i.item_name,i.unit,i.minimum_stock FROM items i WHERE i.status='Active' AND (
        EXISTS(SELECT 1 FROM item_lots l WHERE l.item_id=i.id AND l.storage_location='CSSR' AND l.status<>'DELETED')
        OR EXISTS(SELECT 1 FROM cssr_available_stock s WHERE s.item_id=i.id AND s.status<>'DELETED')) ORDER BY i.item_name,i.id");
    $rows=[];foreach($items as $item){$item['current_cssr_stock']=0;$item['physical_stock']=0;$item['ready_quantity']=0;$item['reserved']=0;$item['held']=0;$item['legacy_stock']=0;$item['monthly_issued']=0;$item['fast_moving']=false;$item['lots']=[];$rows[(int)$item['id']]=$item;}
    $lots=cssrStockPageQuery($db,"SELECT l.id,l.item_id,l.lot_number,l.expiry_date,l.quantity_available,l.quantity_quarantined,l.status,l.recall_status,
        COALESCE((SELECT SUM(r.quantity) FROM stock_reservations r WHERE r.item_lot_id=l.id AND r.status='ACTIVE' AND (r.expires_at IS NULL OR r.expires_at>?)),0) reserved
        FROM item_lots l JOIN items i ON i.id=l.item_id WHERE l.storage_location='CSSR' AND l.status<>'DELETED' AND i.status='Active'
        ORDER BY l.expiry_date IS NULL,l.expiry_date,l.id",[$timestamp]);
    foreach($lots as $lot){
        $id=(int)$lot['item_id'];if(!isset($rows[$id]))continue;
        $qty=max(0,(int)$lot['quantity_available']);$quarantined=max(0,(int)$lot['quantity_quarantined']);
        $expired=!empty($lot['expiry_date'])&&$lot['expiry_date']<$today;
        $eligible=$lot['status']==='AVAILABLE'&&!$expired&&($lot['recall_status']??'CLEAR')==='CLEAR'&&trim((string)$lot['lot_number'])!==''&&$lot['lot_number']!=='UNIDENTIFIED / PENDING';
        $usable=$eligible?$qty:0;$reserved=$eligible?min($qty,max(0,(int)$lot['reserved'])):0;
        $rows[$id]['physical_stock']+=$qty+$quarantined;$rows[$id]['current_cssr_stock']+=$usable;
        $rows[$id]['reserved']+=$reserved;$rows[$id]['ready_quantity']+=$usable-$reserved;$rows[$id]['held']+=$quarantined+($eligible?0:$qty);
        $rows[$id]['lots'][]=['lot_number'=>$lot['lot_number'],'expiry_date'=>$lot['expiry_date'],'physical'=>$qty+$quarantined,'usable'=>$usable,'reserved'=>$reserved,'ready'=>$usable-$reserved,
            'status'=>$qty+$quarantined===0?'Empty':($expired?'Expired':(!$eligible?'On Hold':($reserved===$usable&&$reserved>0?'Reserved':'Available'))),'legacy'=>false];
    }
    // Legacy balances already reside inside CSSR. They are not stock in their source room.
    // Retain them visibly, but do not claim they are backed by a releasable lot.
    foreach(cssrStockPageQuery($db,"SELECT s.* FROM cssr_available_stock s JOIN items i ON i.id=s.item_id WHERE s.status<>'DELETED' AND i.status='Active'") as $lot){
        $id=(int)$lot['item_id'];if(!isset($rows[$id]))continue;$qty=max(0,(int)$lot['quantity']);
        $expired=!empty($lot['expiry_date'])&&$lot['expiry_date']<$today;
        $eligible=$lot['status']==='AVAILABLE'&&!$expired&&trim((string)$lot['lot_number'])!==''&&$lot['lot_number']!=='UNIDENTIFIED / PENDING';$usable=$eligible?$qty:0;
        $rows[$id]['physical_stock']+=$qty;$rows[$id]['current_cssr_stock']+=$usable;$rows[$id]['legacy_stock']+=$usable;$rows[$id]['held']+=$qty-$usable;
        $rows[$id]['lots'][]=['lot_number'=>$lot['lot_number'],'expiry_date'=>$lot['expiry_date'],'physical'=>$qty,'usable'=>$usable,'reserved'=>0,'ready'=>0,'status'=>$qty===0?'Empty':($expired?'Expired':($eligible?'Verify batch':'On Hold')),'legacy'=>true];
    }
    // stock_out is the canonical posted issuance ledger: do not also sum request details.
    // Returns are separate movements, so this is gross issued quantity in the selected month.
    foreach(cssrStockPageQuery($db,"SELECT s.item_id,SUM(s.quantity_issued) issued FROM stock_out s
        JOIN item_lots l ON l.id=s.item_lot_id AND l.item_id=s.item_id WHERE l.storage_location='CSSR'
        AND s.issued_date>=? AND s.issued_date<? GROUP BY s.item_id",[$startDate,$end]) as $issued){if(isset($rows[(int)$issued['item_id']]))$rows[(int)$issued['item_id']]['monthly_issued']=(int)$issued['issued'];}
    $positive=array_values(array_filter(array_column($rows,'monthly_issued'),fn($qty)=>$qty>0));rsort($positive,SORT_NUMERIC);
    $cutoff=$positive?$positive[max(0,(int)ceil(count($positive)*.2)-1)]:null;
    $stats=['total'=>0,'low'=>0,'out'=>0,'fast'=>0,'ready'=>0,'reserved'=>0,'held'=>0,'legacy'=>0];
    foreach($rows as &$row){$qty=$row['current_cssr_stock'];$row['stock_status']=$qty===0?'Out of Stock':($qty<(int)$row['minimum_stock']?'Low Stock':'Available');
        $row['fast_moving']=$cutoff!==null&&$row['monthly_issued']>=$cutoff;
        $stats['total']+=$qty;$stats['low']+=(int)($row['stock_status']==='Low Stock');$stats['out']+=(int)($row['stock_status']==='Out of Stock');$stats['fast']+=(int)$row['fast_moving'];
        $stats['ready']+=$row['ready_quantity'];$stats['reserved']+=$row['reserved'];$stats['held']+=$row['held'];$stats['legacy']+=$row['legacy_stock'];
    }unset($row);
    $q=is_scalar($input['q']??null)?trim((string)$input['q']):'';$status=is_scalar($input['status']??null)?(string)$input['status']:'';
    $statuses=['Available','Low Stock','Out of Stock','Fast-Moving'];if(!in_array($status,$statuses,true))$status='';
    $filtered=array_values(array_filter($rows,fn($row)=>($q===''||stripos($row['item_code'].' '.$row['item_name'],$q)!==false)&&($status===''||($status==='Fast-Moving'?$row['fast_moving']:$status===$row['stock_status']))));
    usort($filtered,fn($a,$b)=>($b['current_cssr_stock']===0)<=>($a['current_cssr_stock']===0)?:strcasecmp($a['item_name'],$b['item_name']));
    $total=count($filtered);$pages=max(1,(int)ceil($total/10));$page=min($pages,max(1,(int)($input['page']??1)));$pageRows=array_slice($filtered,($page-1)*10,10);
    $detail=$rows[(int)($input['view']??0)]??null;$issues=[];
    if($detail)$issues=cssrStockPageQuery($db,"SELECT s.reference_no,s.department,s.quantity_issued,s.issued_date,l.lot_number FROM stock_out s JOIN item_lots l ON l.id=s.item_lot_id AND l.item_id=s.item_id
        WHERE l.storage_location='CSSR' AND s.item_id=? AND s.issued_date>=? AND s.issued_date<? ORDER BY s.issued_date DESC,s.id DESC LIMIT 25",[$detail['id'],$startDate,$end]);
    return ['stats'=>$stats,'rows'=>$pageRows,'total'=>$total,'pages'=>$pages,'page'=>$page,'q'=>$q,'status'=>$status,'statuses'=>$statuses,'month'=>$month,'month_label'=>$start->format('F Y'),'today'=>$today,'detail'=>$detail,'issues'=>$issues,'fast_cutoff'=>$cutoff];
}
