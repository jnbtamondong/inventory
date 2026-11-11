<?php
declare(strict_types=1);
function cssrStorageQuery(PDO $db,string $sql,array $params=[]): array { $s=$db->prepare($sql);$s->execute($params);return $s->fetchAll(PDO::FETCH_ASSOC); }
function cssrStorageLocations(): array { return ['STORAGE ROOM 1'=>'Storage Room 1','STORAGE ROOM 2'=>'Storage Room 2','STORAGE ROOM 3'=>'Storage Room 3','CSSR'=>'CSSR']; }
function cssrStorageRoomData(PDO $db,array $input=[],?string $today=null): array
{
    $today=$today??date('Y-m-d');$soon=date('Y-m-d',strtotime($today.' +90 days'));
    $locations=cssrStorageLocations();$location=is_string($input['location']??null)?strtoupper(trim($input['location'])):'STORAGE ROOM 1';
    if(!isset($locations[$location]))$location='STORAGE ROOM 1';$locationLabel=$locations[$location];
    $lots=cssrStorageQuery($db,"SELECT l.*,i.item_code,i.item_name,i.unit,i.minimum_stock,
        COALESCE((SELECT SUM(s.quantity) FROM stock_reservations s WHERE s.item_lot_id=l.id AND s.status='ACTIVE' AND (s.expires_at IS NULL OR s.expires_at>CURRENT_TIMESTAMP)),0) reserved
        FROM item_lots l JOIN items i ON i.id=l.item_id WHERE l.storage_location=? AND l.status<>'DELETED' AND i.status='Active'
        AND (l.quantity_available>0 OR l.quantity_quarantined>0)
        ORDER BY i.item_name,l.expiry_date IS NULL,l.expiry_date,l.created_at,l.id",[$location]);
    $itemStock=[];$minimum=[];$first=[];$stats=['total_stock'=>0,'available'=>0,'low'=>0,'expiring'=>0];
    foreach($lots as &$lot){
        $lot['stored_quantity']=max(0,(int)$lot['quantity_available'])+max(0,(int)$lot['quantity_quarantined']);
        $lot['expired']=!empty($lot['expiry_date'])&&$lot['expiry_date']<$today;
        $eligible=$lot['status']==='AVAILABLE'&&!$lot['expired']&&trim((string)$lot['lot_number'])!==''&&$lot['lot_number']!=='UNIDENTIFIED / PENDING';
        $lot['free_quantity']=$eligible?max(0,(int)$lot['quantity_available']-(int)$lot['reserved']):0;
        $id=(int)$lot['item_id'];$itemStock[$id]=($itemStock[$id]??0)+$lot['free_quantity'];$minimum[$id]=(int)$lot['minimum_stock'];
        if($lot['free_quantity']>0&&!isset($first[$id]))$first[$id]=(int)$lot['id'];
        $stats['total_stock']+=$lot['stored_quantity'];
        $lot['expiring_soon']=$lot['free_quantity']>0&&!empty($lot['expiry_date'])&&$lot['expiry_date']<=$soon;
        if($lot['expiring_soon'])$stats['expiring']++;
    }unset($lot);
    foreach($itemStock as $id=>$qty){if($qty<=0)continue;if($qty<$minimum[$id])$stats['low']++;else $stats['available']++;}
    foreach($lots as &$lot){
        $lot['transferable']=$location!=='CSSR'&&$lot['free_quantity']>0&&($first[(int)$lot['item_id']]??0)===(int)$lot['id'];
        $lot['low_stock']=$lot['free_quantity']>0&&$itemStock[(int)$lot['item_id']]<(int)$lot['minimum_stock'];
        $lot['stock_status']=$lot['expired']?'Expired':($lot['free_quantity']<=0?((int)$lot['reserved']>0?'Reserved':'On Hold'):($lot['expiring_soon']?'Expiring Soon':($itemStock[(int)$lot['item_id']]<(int)$lot['minimum_stock']?'Low Stock':'Available')));
    }unset($lot);
    $q=is_scalar($input['q']??null)?trim((string)$input['q']):'';$status=is_scalar($input['status']??null)?(string)$input['status']:'';
    $statuses=['Available','Low Stock','Expiring Soon','Expired','Reserved','On Hold'];if(!in_array($status,$statuses,true))$status='';
    $filtered=array_values(array_filter($lots,fn($row)=>($q===''||stripos($row['item_code'].' '.$row['item_name'].' '.$row['lot_number'],$q)!==false)&&($status===''||($status==='Low Stock'?$row['low_stock']:$status===$row['stock_status']))));
    $total=count($filtered);$pages=max(1,(int)ceil($total/10));$page=min($pages,max(1,(int)($input['page']??1)));
    $rows=array_slice($filtered,($page-1)*10,10);$transferLots=array_values(array_filter($lots,fn($lot)=>$lot['transferable']));
    return compact('stats','rows','lots','transferLots','total','pages','page','q','status','statuses','locations','location','locationLabel');
}
function cssrStorageBadge(string $status): string { return ['Available'=>'success','Low Stock'=>'warning','Expiring Soon'=>'warning','Expired'=>'danger','Reserved'=>'info','On Hold'=>'danger'][$status]??''; }
