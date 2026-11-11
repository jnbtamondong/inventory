<?php
declare(strict_types=1);
require_once __DIR__.'/storage-room-data.php';
function cssrTransferToCssr(PDO $db,array $input,int $userId,string $userName): array
{
    $errors=[];$values=[];
    $location=is_string($input['source_location']??null)?strtoupper(trim($input['source_location'])):'STORAGE ROOM 1';
    if(!isset(cssrStorageLocations()[$location])||$location==='CSSR')$errors['_form']='Select Storage Room 1, 2, or 3 as the source.';
    foreach(['lot_id','quantity','date','remarks','request_key'] as $key)$values[$key]=is_scalar($input[$key]??null)?trim((string)$input[$key]):'';
    if(!ctype_digit($values['lot_id'])||(int)$values['lot_id']<=0)$errors['lot_id']='Select an item and batch.';
    if(!ctype_digit($values['quantity'])||(float)$values['quantity']<1||(float)$values['quantity']>2147483647)$errors['quantity']='Enter a positive whole quantity.';
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$values['date']);
    if(!$date||$date->format('Y-m-d')!==$values['date']||$values['date']>date('Y-m-d'))$errors['date']='Enter a valid transfer date, no later than today.';
    if((function_exists('mb_strlen')?mb_strlen($values['remarks']):strlen($values['remarks']))>1000)$errors['remarks']='Use 1,000 characters or fewer.';
    if(!preg_match('/^[a-f0-9]{32}$/D',$values['request_key']))$errors['_form']='Reopen the form to obtain a valid transfer reference.';
    if(isset($input['destination'])&&$input['destination']!=='CSSR')$errors['_form']='Transfers from this page must go to CSSR.';
    if($errors)return ['ok'=>false,'errors'=>$errors];
    $lock=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
    $run=static function($sql,$params=[])use($db){$s=$db->prepare($sql);$s->execute($params);return $s;};
    $db->beginTransaction();
    try{
        $existing=cssrStorageQuery($db,'SELECT * FROM storage_transfers WHERE request_key=?'.$lock,[$values['request_key']])[0]??null;
        if($existing){
            $existingSource=cssrStorageQuery($db,'SELECT storage_location FROM item_lots WHERE id=?',[$existing['source_lot_id']])[0]['storage_location']??null;
            if($existingSource!==$location)throw new DomainException('This reference has already been used for another storage location.');
            if((int)$existing['created_by']!==$userId)throw new DomainException('This transfer reference belongs to another user.');
            if((int)$existing['source_lot_id']!==(int)$values['lot_id']||(int)$existing['quantity']!==(int)$values['quantity']||$existing['transfer_date']!==$values['date']||$existing['remarks']!==$values['remarks'])throw new DomainException('This reference has already been used. Reload the page before making a different transfer.');
            $db->commit();return ['ok'=>true,'reference'=>$existing['reference_no'],'source_location'=>$location,'replayed'=>true];
        }
        $identity=cssrStorageQuery($db,"SELECT item_id FROM item_lots WHERE id=? AND storage_location=?",[(int)$values['lot_id'],$location])[0]??null;
        if(!$identity)throw new DomainException('The selected lot was not found in '.cssrStorageLocations()[$location].'.');
        $item=cssrStorageQuery($db,"SELECT * FROM items WHERE id=? AND status='Active'".$lock,[$identity['item_id']])[0]??null;
        if(!$item)throw new DomainException('This item is no longer active.');
        $source=cssrStorageQuery($db,"SELECT * FROM item_lots WHERE id=? AND item_id=? AND storage_location=?".$lock,[(int)$values['lot_id'],$item['id'],$location])[0]??null;
        if(!$source||$source['status']!=='AVAILABLE'||trim((string)$source['lot_number'])===''||$source['lot_number']==='UNIDENTIFIED / PENDING'||(!empty($source['expiry_date'])&&$source['expiry_date']<date('Y-m-d')))throw new DomainException('This batch is expired, held, or no longer available for transfer.');
        if(!empty($source['created_at'])&&$values['date']<substr($source['created_at'],0,10))throw new DomainException('The transfer date cannot precede the source lot record.');
        $reservationRows=cssrStorageQuery($db,"SELECT quantity FROM stock_reservations WHERE item_lot_id=? AND status='ACTIVE' AND (expires_at IS NULL OR expires_at>CURRENT_TIMESTAMP)".$lock,[$source['id']]);
        $available=max(0,(int)$source['quantity_available']-array_sum(array_column($reservationRows,'quantity')));$qty=(int)$values['quantity'];
        if($qty>$available){$db->rollBack();return ['ok'=>false,'errors'=>['quantity'=>"Only $available units are currently available from this batch."],'available'=>$available];}
        // Follow the existing first-expiry-first-out rule for storage replenishment.
        $first=cssrStorageQuery($db,"SELECT l.id FROM item_lots l WHERE l.item_id=? AND l.storage_location=? AND l.status='AVAILABLE'
            AND l.lot_number<>'' AND l.lot_number<>'UNIDENTIFIED / PENDING' AND (l.expiry_date IS NULL OR l.expiry_date>=CURRENT_DATE)
            AND l.quantity_available>(SELECT COALESCE(SUM(s.quantity),0) FROM stock_reservations s WHERE s.item_lot_id=l.id AND s.status='ACTIVE' AND (s.expires_at IS NULL OR s.expires_at>CURRENT_TIMESTAMP))
            ORDER BY l.expiry_date IS NULL,l.expiry_date,l.created_at,l.id LIMIT 1".$lock,[$item['id'],$location])[0]??null;
        if(!$first||(int)$first['id']!==(int)$source['id'])throw new DomainException('Transfer the earliest-expiring available batch of this item first. Reload the page to see its current balance.');
        $destination=cssrStorageQuery($db,"SELECT * FROM item_lots WHERE item_id=? AND storage_location='CSSR' AND lot_number=?
            AND (expiry_date=? OR (expiry_date IS NULL AND ? IS NULL)) AND brand=? AND supplier=? AND (unit_price=? OR (unit_price IS NULL AND ? IS NULL)) ORDER BY id LIMIT 1".$lock,
            [$item['id'],$source['lot_number'],$source['expiry_date'],$source['expiry_date'],(string)($source['brand']??''),(string)($source['supplier']??''),$source['unit_price'],$source['unit_price']])[0]??null;
        if($destination&&!in_array($destination['status'],['AVAILABLE','DEPLETED'],true))throw new DomainException('The matching CSSR batch is on hold. Resolve its inspection status before transferring.');
        if((int)$source['quantity_issued']+$qty>2147483647||($destination&&((int)$destination['quantity_received']+$qty>2147483647||(int)$destination['quantity_available']+$qty>2147483647)))throw new DomainException('This quantity exceeds the supported stock balance.');
        $sourceBalance=(int)$source['quantity_available']-$qty;
        $run('UPDATE item_lots SET quantity_available=?,quantity_issued=quantity_issued+?,status=? WHERE id=?',[$sourceBalance,$qty,$sourceBalance>0?'AVAILABLE':((int)$source['quantity_quarantined']>0?'QUARANTINED':'DEPLETED'),$source['id']]);
        if($destination){$destinationId=(int)$destination['id'];$run("UPDATE item_lots SET quantity_available=quantity_available+?,quantity_received=quantity_received+?,status='AVAILABLE' WHERE id=?",[$qty,$qty,$destinationId]);}
        else{$run("INSERT INTO item_lots(item_id,lot_number,expiry_date,brand,supplier,storage_location,unit_price,quantity_received,quantity_available,quantity_quarantined,inspection_status,status,lot_image) VALUES(?,?,?,?,?,'CSSR',?,?,?,0,'CLEARED','AVAILABLE',?)",[$item['id'],$source['lot_number'],$source['expiry_date'],(string)($source['brand']??''),(string)($source['supplier']??''),$source['unit_price'],$qty,$qty,$source['lot_image']??null]);$destinationId=(int)$db->lastInsertId();}
        $now=date('Y-m-d H:i:s');$reference='TRF-'.str_replace('-','',$values['date']).'-'.strtoupper($values['request_key']);
        $run('INSERT INTO storage_transfers(request_key,reference_no,item_id,source_lot_id,destination_lot_id,quantity,transfer_date,remarks,created_by,encoded_by,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)',[$values['request_key'],$reference,$item['id'],$source['id'],$destinationId,$qty,$values['date'],$values['remarks'],$userId,$userName,$now]);
        $storageBalance=(int)cssrStorageQuery($db,"SELECT COALESCE(SUM(quantity_available),0) n FROM item_lots WHERE item_id=? AND storage_location=?",[$item['id'],$location])[0]['n'];
        $cssrBalance=(int)cssrStorageQuery($db,"SELECT COALESCE(SUM(quantity_available),0) n FROM item_lots WHERE item_id=? AND storage_location='CSSR'",[$item['id']])[0]['n'];
        // An internal move conserves items.current_stock; only location balances change.
        $run("INSERT INTO stock_location_movements(item_id,movement_type,reference_no,lot_number,expiry_date,quantity,from_location_id,from_location_code,to_location_id,to_location_code,processed_by,processed_at,remarks,status) VALUES(?,?,?,?,?,?,?,?,?,'CSSR',?,?,?,'POSTED')",[$item['id'],$location.' TRANSFER',$reference,$source['lot_number'],$source['expiry_date'],$qty,$source['id'],$location,$destinationId,$userName,$values['date'].' '.date('H:i:s'),$values['remarks']]);
        $run("INSERT INTO stock_cards(item_id,transaction_date,reference_no,transaction_type,lot_number,expiry_date,received_qty,issued_qty,balance_qty,from_location,to_location,encoded_by,remarks) VALUES(?,?,?,'TRANSFER TO CSSR',?,?,0,?,?,?,'CSSR',?,?)",[$item['id'],$values['date'].' '.date('H:i:s'),$reference,$source['lot_number'],$source['expiry_date'],$qty,$storageBalance,$location,$userName,$values['remarks']]);
        $run("INSERT INTO bin_cards(item_id,transaction_date,reference_no,transaction_type,lot_number,expiry_date,received_qty,issued_qty,balance_qty,department,encoded_by,remarks) VALUES(?,?,?,?,?,?,?,0,?,?,?,?)",[$item['id'],$values['date'],$reference,'TRANSFER FROM '.$location,$source['lot_number'],$source['expiry_date'],$qty,$cssrBalance,$location,$userName,$values['remarks']]);
        $db->commit();return ['ok'=>true,'reference'=>$reference,'source_location'=>$location,'source_balance'=>$sourceBalance,'cssr_balance'=>$cssrBalance,'destination_lot_id'=>$destinationId,'replayed'=>false];
    }catch(Throwable $error){if($db->inTransaction())$db->rollBack();throw $error;}
}
