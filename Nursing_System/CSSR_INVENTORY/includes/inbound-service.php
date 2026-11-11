<?php
declare(strict_types=1);
require_once __DIR__.'/inbound-data.php';

function cssrValidateInbound(array $input,bool $complete): array
{
    $data=[];$errors=[];$length=fn($v)=>function_exists('mb_strlen')?mb_strlen($v):strlen($v);
    foreach(['received_date','supplier','delivery_receipt_no','po_number','procurement_type','storage_location','remarks'] as $key) $data[$key]=is_scalar($input[$key]??null)?trim((string)$input[$key]):'';
    foreach(['supplier'=>150,'delivery_receipt_no'=>120,'po_number'=>120,'remarks'=>1000] as $key=>$max) if($length($data[$key])>$max)$errors[$key]="Use $max characters or fewer.";
    $validDate=static function($v){$d=DateTimeImmutable::createFromFormat('!Y-m-d',$v);return $d && $d->format('Y-m-d')===$v;};
    if(($complete||$data['received_date']!=='')&&!$validDate($data['received_date']))$errors['received_date']='Enter a valid received date.';
    elseif($data['received_date']>date('Y-m-d'))$errors['received_date']='The received date cannot be in the future.';
    if($complete&&$data['supplier']==='')$errors['supplier']='Enter the supplier or donor.';
    if(($complete||$data['procurement_type']!=='')&&!isset(cssrInboundTypes()[$data['procurement_type']]))$errors['procurement_type']='Select a procurement type.';
    if(!isset(cssrInboundDestinations()[$data['storage_location']]))$errors['storage_location']='Select a storage destination.';
    $lines=is_array($input['lines']??null)?array_values($input['lines']):[];
    if(count($lines)>100)$errors['_form']='Use no more than 100 item lines per inbound.';
    if($complete&&!$lines)$errors['_form']='Add at least one item.';
    $data['lines']=[];$total=0;$units=0;$unique=[];
    foreach(array_slice($lines,0,100) as $index=>$line){
        $v=[];foreach(['item_id','quantity','unit_cost','expiry_date','lot_number'] as $key)$v[$key]=is_scalar($line[$key]??null)?trim((string)$line[$key]):'';
        $prefix="lines.$index.";
        if(($complete||$v['item_id']!=='')&&(!ctype_digit($v['item_id'])||(int)$v['item_id']<=0))$errors[$prefix.'item_id']='Select an inventory item.';
        if(($complete||$v['quantity']!=='')&&(!ctype_digit($v['quantity'])||(float)$v['quantity']<1||(float)$v['quantity']>2147483647))$errors[$prefix.'quantity']='Enter a positive whole quantity.';
        if(($complete||$v['unit_cost']!=='')&&(!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D',$v['unit_cost'])||(float)$v['unit_cost']>9999999999.99))$errors[$prefix.'unit_cost']='Enter a non-negative cost with up to two decimals.';
        if($complete&&$v['lot_number']==='')$errors[$prefix.'lot_number']='Enter the lot or batch number.';
        elseif($length($v['lot_number'])>120)$errors[$prefix.'lot_number']='Use 120 characters or fewer.';
        elseif(strtoupper($v['lot_number'])==='UNIDENTIFIED / PENDING')$errors[$prefix.'lot_number']='Enter the actual lot or batch number.';
        if($v['expiry_date']!==''&&(!$validDate($v['expiry_date'])||($complete&&$v['expiry_date']<max(date('Y-m-d'),$data['received_date']))))$errors[$prefix.'expiry_date']='Use a valid, unexpired date; leave blank for non-expiring items.';
        if(!isset($errors[$prefix.'quantity'])&&!isset($errors[$prefix.'unit_cost'])&&ctype_digit($v['quantity'])&&preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D',$v['unit_cost'])){
            $parts=explode('.',$v['unit_cost']);$cents=(int)$parts[0]*100+(int)str_pad($parts[1]??'',2,'0');
            $lineTotal=$cents*(int)$v['quantity'];
            if($lineTotal>9007199254740991-$total)$errors['_form']='The total value is too large. Split this delivery into smaller transactions.';
            else $total+=(int)$lineTotal;
        }
        $units+=(int)$v['quantity'];if((int)$v['item_id']>0)$unique[(int)$v['item_id']]=true;
        $data['lines'][]=$v;
    }
    return ['values'=>$data,'errors'=>$errors,'total_cents'=>$total,'units'=>$units,'item_count'=>count($unique)];
}

function cssrSaveInbound(PDO $db,array $input,int $userId,string $userName,bool $admin=false): array
{
    $complete=($input['action']??'')==='complete';
    if(!in_array($input['action']??'',['draft','complete'],true))throw new DomainException('Choose Save Draft or Complete Inbound.');
    $validation=cssrValidateInbound($input,$complete);
    if($validation['errors'])return ['ok'=>false,'errors'=>$validation['errors']];
    $data=$validation['values'];$id=(int)($input['id']??0);$key=is_string($input['request_key']??null)?$input['request_key']:'';
    if(!preg_match('/^[a-f0-9]{32}$/D',$key))throw new DomainException('Reopen the form to obtain a valid transaction reference.');
    $mysql=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';$lock=$mysql?' FOR UPDATE':'';
    $run=static function($sql,$params=[])use($db){$s=$db->prepare($sql);$s->execute($params);return $s;};
    $db->beginTransaction();
    try{
        $existing=cssrInboundQuery($db,'SELECT * FROM inbound_documents WHERE '.($id>0?'id=?':'request_key=?').$lock,[$id>0?$id:$key])[0]??null;
        if($id>0&&!$existing)throw new DomainException('This draft no longer exists.');
        if($existing){
            if(!$admin&&(int)$existing['created_by']!==$userId)throw new DomainException('You cannot change another user\'s draft.');
            if($existing['request_key']!==$key)throw new DomainException('The transaction reference does not match this draft.');
            if($existing['status']==='COMPLETED'){$db->commit();return ['ok'=>true,'id'=>(int)$existing['id'],'version'=>(int)$existing['version'],'inbound_no'=>$existing['inbound_no'],'completed'=>true,'already_completed'=>true];}
            if((int)($input['version']??0)!==(int)$existing['version'])throw new DomainException('This draft changed in another window. Reopen it before saving.');
            $id=(int)$existing['id'];
        }
        // Lock items in a stable order to serialize stock changes across multi-item receipts.
        $itemIds=array_unique(array_map(fn($line)=>(int)$line['item_id'],$data['lines']));sort($itemIds,SORT_NUMERIC);$items=[];
        foreach($itemIds as $itemId){if($itemId<=0)continue;$item=cssrInboundQuery($db,'SELECT * FROM items WHERE id=?'.$lock,[$itemId])[0]??null;if(!$item||$item['status']!=='Active')throw new DomainException('A selected item is no longer active. Reopen the form and select an active item.');$items[$itemId]=$item;}
        $payload=json_encode($data,JSON_THROW_ON_ERROR);$now=date('Y-m-d H:i:s');
        $totalValue=intdiv($validation['total_cents'],100).'.'.str_pad((string)($validation['total_cents']%100),2,'0',STR_PAD_LEFT);
        if(!$existing){
            $run("INSERT INTO inbound_documents(request_key,status,payload,received_date,supplier,item_count,total_value,created_by,encoded_by,version,created_at,updated_at) VALUES(?,'DRAFT',?,?,?,?,?,?,?,1,?,?)",[$key,$payload,$data['received_date']?:null,$data['supplier'],$validation['item_count'],$totalValue,$userId,$userName,$now,$now]);
            $id=(int)$db->lastInsertId();$version=1;
        }else{
            $version=(int)$existing['version']+1;
            $run('UPDATE inbound_documents SET payload=?,received_date=?,supplier=?,item_count=?,total_value=?,version=?,updated_at=? WHERE id=?',[$payload,$data['received_date']?:null,$data['supplier'],$validation['item_count'],$totalValue,$version,$now,$id]);
        }
        $number=null;
        if($complete){
            // Separate compact-month namespace avoids collisions with the legacy INB-YYYY-MM sequence.
            $number='INB-'.str_replace('-','',substr($data['received_date'],0,7)).'-'.str_pad((string)$id,6,'0',STR_PAD_LEFT);
            foreach($data['lines'] as $line){
                $itemId=(int)$line['item_id'];$item=$items[$itemId];$qty=(int)$line['quantity'];$cost=number_format((float)$line['unit_cost'],2,'.','');$expiry=$line['expiry_date']?:null;
                $brand=(string)($item['brand']??'');
                $lot=cssrInboundQuery($db,'SELECT * FROM item_lots WHERE item_id=? AND lot_number=? AND (expiry_date=? OR (expiry_date IS NULL AND ? IS NULL)) AND brand=? AND supplier=? AND storage_location=? AND unit_price=?'.$lock,[$itemId,$line['lot_number'],$expiry,$expiry,$brand,$data['supplier'],$data['storage_location'],$cost])[0]??null;
                if($lot&&!in_array($lot['status'],['AVAILABLE','DEPLETED'],true))throw new DomainException('This lot is held or unavailable. Use the receiving inspection workflow to review it.');
                if((int)$items[$itemId]['current_stock']+$qty>2147483647||($lot&&(int)$lot['quantity_received']+$qty>2147483647))throw new DomainException('The resulting stock exceeds the supported quantity.');
                if($lot){$lotId=(int)$lot['id'];$run("UPDATE item_lots SET quantity_received=quantity_received+?,quantity_available=quantity_available+?,status='AVAILABLE' WHERE id=?",[$qty,$qty,$lotId]);}
                else{$run("INSERT INTO item_lots(item_id,lot_number,expiry_date,brand,supplier,storage_location,unit_price,quantity_received,quantity_available,quantity_quarantined,inspection_status,status) VALUES(?,?,?,?,?,?,?,?,?,0,'CLEARED','AVAILABLE')",[$itemId,$line['lot_number'],$expiry,$brand,$data['supplier'],$data['storage_location'],$cost,$qty,$qty]);$lotId=(int)$db->lastInsertId();}
                $run('UPDATE items SET current_stock=current_stock+? WHERE id=?',[$qty,$itemId]);$items[$itemId]['current_stock']=(int)$items[$itemId]['current_stock']+$qty;
                $run("INSERT INTO stock_in(item_id,item_lot_id,inbound_no,reference_no,po_number,delivery_receipt_no,procurement_type,receiving_status,record_status,delivery_completion,supplier,quantity_received,expected_quantity,remaining_quantity,accepted_quantity,quarantined_quantity,rejected_quantity,unit_price,lot_number,expiry_date,received_date,received_by,remarks) VALUES(?,?,?,?,?,?,?,'INSPECTED','POSTED','COMPLETE',?,?,?,0,?,0,0,?,?,?,?,?,?)",[$itemId,$lotId,$number,$number,$data['po_number']?:null,$data['delivery_receipt_no']?:null,$data['procurement_type'],$data['supplier'],$qty,$qty,$qty,$cost,$line['lot_number'],$expiry,$data['received_date'],$userName,$data['remarks']]);
                $stockId=(int)$db->lastInsertId();
                $run("INSERT INTO receiving_inspections(item_lot_id,stock_in_id,inspection_no,delivered_qty,accepted_qty,quarantined_qty,rejected_qty,findings,decision,inspected_by,inspected_at) VALUES(?,?,?,?,?,0,0,?,'ACCEPTED',?,?)",[$lotId,$stockId,'INS-'.$number.'-'.$stockId,$qty,$qty,'Accepted when inbound was completed.',$userName,$now]);
                $balance=(int)cssrInboundQuery($db,'SELECT COALESCE(SUM(quantity_available),0) balance FROM item_lots WHERE item_id=? AND storage_location=?',[$itemId,$data['storage_location']])[0]['balance'];
                if($data['storage_location']==='CSSR')$run("INSERT INTO bin_cards(item_id,transaction_date,reference_no,transaction_type,lot_number,expiry_date,received_qty,issued_qty,balance_qty,department,encoded_by,remarks) VALUES(?,?,?,'STOCK IN',?,?,?,0,?,?,?,?)",[$itemId,$data['received_date'],$number,$line['lot_number'],$expiry,$qty,$balance,$data['supplier'],$userName,$data['remarks']]);
                else $run("INSERT INTO stock_cards(item_id,transaction_date,reference_no,transaction_type,lot_number,expiry_date,received_qty,issued_qty,balance_qty,from_location,to_location,encoded_by,remarks) VALUES(?,?,?,'STOCK IN',?,?,?,0,?,?,?,?,?)",[$itemId,$data['received_date'],$number,$line['lot_number'],$expiry,$qty,$balance,$data['supplier'],$data['storage_location'],$userName,$data['remarks']]);
                // Preserve the established inbound price-list convention (100% markup).
                $run('INSERT INTO item_price_list(item_name,item_description,lot_number,expiration_text,unit_type,classification,unit_price,markup_cost,total_price,selling_price,source_as_of,source_hash) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',[$item['item_name'],$item['item_description'],$line['lot_number'],$expiry,$item['unit'],$item['category'],$cost,$cost,(float)$cost*2,(float)$cost*2,$data['received_date'],hash('sha256',$number.'|'.$stockId)]);
            }
            $run("UPDATE inbound_documents SET status='COMPLETED',inbound_no=?,encoded_by=?,completed_at=? WHERE id=?",[$number,$userName,$now,$id]);
        }
        $db->commit();return ['ok'=>true,'id'=>$id,'version'=>$version,'inbound_no'=>$number,'completed'=>$complete];
    }catch(Throwable $error){if($db->inTransaction())$db->rollBack();throw $error;}
}
