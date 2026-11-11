<?php
declare(strict_types=1);
function cssrInboundQuery(PDO $db,string $sql,array $params=[]): array { $s=$db->prepare($sql);$s->execute($params);return $s->fetchAll(PDO::FETCH_ASSOC); }
function cssrInboundTypes(): array { return ['REGULAR'=>'Procured','DONATION'=>'Donation','EMERGENCY PURCHASE'=>'Emergency Purchase']; }
function cssrInboundDestinations(): array { return ['STORAGE ROOM 1'=>'Storage Room 1','CSSR'=>'CSSR','STORAGE ROOM 2'=>'Storage Room 2','STORAGE ROOM 3'=>'Storage Room 3']; }
function cssrInboundProcurementQueue(PDO $db): array
{
    return cssrInboundQuery($db, 'SELECT r.id request_id,COALESCE(NULLIF(r.procurement_pr_no,\'\'),r.pr_no) pr_number,p.ppmp_no,i.winning_supplier,COUNT(*) item_count,SUM(i.quantity_requested-i.quantity_delivered) remaining_qty FROM procurement_requests r JOIN procurement_ppmp p ON p.id=r.ppmp_id JOIN procurement_request_items i ON i.request_id=r.id WHERE i.procurement_status IN(\'PENDING DELIVERY\',\'PARTIALLY DELIVERED\') AND i.quantity_delivered<i.quantity_requested GROUP BY r.id,COALESCE(NULLIF(r.procurement_pr_no,\'\'),r.pr_no),p.ppmp_no,i.winning_supplier ORDER BY r.id DESC,i.winning_supplier');
}
function cssrInboundList(PDO $db,array $input,int $userId,bool $admin): array
{
    $filters=[];foreach(['date','supplier','procurement_type','item','q'] as $key) $filters[$key]=is_scalar($input[$key]??null)?trim((string)$input[$key]):'';
    $where=[];$params=[];
    foreach(['date'=>'s.received_date','supplier'=>'s.supplier','procurement_type'=>'s.procurement_type','item'=>'s.item_id'] as $key=>$column) if($filters[$key]!=='') {$where[]="$column=?";$params[]=$filters[$key];}
    if($filters['q']!=='') {$where[]='(s.inbound_no LIKE ? OR s.reference_no LIKE ? OR s.delivery_receipt_no LIKE ? OR s.po_number LIKE ?)';for($i=0;$i<4;$i++)$params[]='%'.$filters['q'].'%';}
    $base=' FROM stock_in s JOIN items i ON i.id=s.item_id LEFT JOIN item_lots l ON l.id=s.item_lot_id';
    $condition=$where?' WHERE '.implode(' AND ',$where):'';
    $total=(int)cssrInboundQuery($db,'SELECT COUNT(*) n'.$base.$condition,$params)[0]['n'];
    $pages=max(1,(int)ceil($total/10));$page=min($pages,max(1,(int)($input['page']??1)));$offset=($page-1)*10;
    $rows=cssrInboundQuery($db,'SELECT s.*,COALESCE(NULLIF(s.inbound_no,\'\'),s.reference_no) display_no,i.item_name,i.item_code,i.unit,l.storage_location'.$base.$condition." ORDER BY s.received_date DESC,s.id DESC LIMIT 10 OFFSET $offset",$params);
    $items=cssrInboundQuery($db,"SELECT id,item_code,item_name,unit,status FROM items ORDER BY item_name");
    $suppliers=cssrInboundQuery($db,"SELECT supplier_name FROM suppliers WHERE status='Active' UNION SELECT DISTINCT supplier supplier_name FROM stock_in ORDER BY supplier_name");
    $drafts=cssrInboundQuery($db,"SELECT id,received_date,supplier,item_count,total_value,encoded_by,updated_at FROM inbound_documents WHERE status='DRAFT'".($admin?'':' AND created_by=?').' ORDER BY updated_at DESC,id DESC',$admin?[]:[$userId]);
    $draft=null;$detail=[];
    if((int)($input['draft']??0)>0) $draft=cssrInboundQuery($db,"SELECT * FROM inbound_documents WHERE id=? AND status='DRAFT'".($admin?'':' AND created_by=?'),$admin?[(int)$input['draft']]:[(int)$input['draft'],$userId])[0]??null;
    if(is_scalar($input['view']??null) && trim((string)$input['view'])!=='') $detail=cssrInboundQuery($db,'SELECT s.*,i.item_name,i.item_code,i.unit,l.storage_location'.$base.' WHERE COALESCE(NULLIF(s.inbound_no,\'\'),s.reference_no)=? ORDER BY s.id',[(string)$input['view']]);
    return compact('filters','total','pages','page','rows','items','suppliers','drafts','draft','detail');
}
