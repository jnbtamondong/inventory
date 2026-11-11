<?php
// Read model shared by the authenticated page and the isolated design preview.
function cssrRequisitionReadySql(): string
{
    return "r.status='Approved'
        AND EXISTS(SELECT 1 FROM item_request_details d WHERE d.request_id=r.id AND d.quantity_approved>0)
        AND NOT EXISTS(SELECT 1 FROM item_request_details d
            LEFT JOIN item_lots l ON l.id=d.approved_item_lot_id AND l.item_id=d.item_id
            LEFT JOIN stock_reservations s ON s.request_detail_id=d.id AND s.request_id=r.id
            WHERE d.request_id=r.id AND d.quantity_approved>0 AND (
                l.id IS NULL OR l.storage_location<>'CSSR' OR l.status<>'AVAILABLE'
                OR l.lot_number='UNIDENTIFIED / PENDING'
                OR (l.expiry_date IS NOT NULL AND l.expiry_date<CURRENT_DATE)
                OR d.quantity_released>0 OR s.id IS NULL OR s.status<>'ACTIVE'
                OR s.item_lot_id<>l.id OR s.quantity<>d.quantity_approved
                OR (s.expires_at IS NOT NULL AND s.expires_at<=CURRENT_TIMESTAMP)
                OR l.quantity_available<(SELECT COALESCE(SUM(other.quantity),0) FROM stock_reservations other
                    WHERE other.item_lot_id=l.id AND other.status='ACTIVE'
                    AND (other.expires_at IS NULL OR other.expires_at>CURRENT_TIMESTAMP))))";
}

function cssrRequisitionBadge(string $status): string
{
    return ['For Approval'=>'warning','Approved'=>'teal','For Release'=>'info','Released'=>'success','Rejected'=>'danger'][$status] ?? '';
}

function cssrRequisitionRows(PDO $pdo, string $sql, array $params=[]): array
{
    $statement=$pdo->prepare($sql); $statement->execute($params); return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function cssrRequisitions(PDO $pdo, array $input, bool $viewAll, int $departmentId): array
{
    $statuses=['All','For Approval','Approved','For Release','Released','Rejected'];
    $filters=['q'=>trim((string)($input['q']??'')), 'department'=>(int)($input['department']??0),
        'date'=>trim((string)($input['date']??'')), 'status'=>(string)($input['status']??'All')];
    if (!in_array($filters['status'],$statuses,true)) $filters['status']='All';
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$filters['date']);
    if (!$date || $date->format('Y-m-d')!==$filters['date']) $filters['date']='';
    $scope=$viewAll ? '1=1' : 'r.department_id=?'; $scopeParams=$viewAll ? [] : [$departmentId>0?$departmentId:-1];
    $where=["r.status<>'Deleted'",$scope]; $params=$scopeParams;
    if ($filters['q']!=='') { $where[]='r.request_no LIKE ?'; $params[]='%'.$filters['q'].'%'; }
    if ($filters['department']>0) { $where[]='r.department_id=?'; $params[]=$filters['department']; }
    if ($filters['date']!=='') { $where[]='r.request_date=?'; $params[]=$filters['date']; }
    $ready=cssrRequisitionReadySql();
    $display="CASE WHEN r.status='Pending' THEN 'For Approval' WHEN ($ready) THEN 'For Release' ELSE r.status END";
    $conditions=['All'=>'1=1','For Approval'=>"r.status='Pending'",'Approved'=>"r.status='Approved'",'For Release'=>$ready,'Released'=>"r.status='Released'",'Rejected'=>"r.status='Rejected'"];
    $base=implode(' AND ',$where);
    $countSql=[];
    foreach ($conditions as $name=>$condition) $countSql[]="COALESCE(SUM(CASE WHEN ($condition) THEN 1 ELSE 0 END),0) AS c".count($countSql);
    $counts=array_combine($statuses,array_map('intval',array_values(cssrRequisitionRows($pdo,'SELECT '.implode(',',$countSql).' FROM item_requests r WHERE '.$base,$params)[0])));
    $total=$counts[$filters['status']]; $pages=max(1,(int)ceil($total/10)); $page=min($pages,max(1,(int)($input['page']??1))); $offset=($page-1)*10;
    $select="SELECT r.*, ($display) AS display_status, (SELECT COUNT(*) FROM item_request_details d WHERE d.request_id=r.id) AS item_count FROM item_requests r";
    $rows=cssrRequisitionRows($pdo,$select.' WHERE '.$base.' AND ('.$conditions[$filters['status']].") ORDER BY r.request_date DESC,r.id DESC LIMIT 10 OFFSET $offset",$params);
    $departments=cssrRequisitionRows($pdo,"SELECT DISTINCT r.department_id id,r.department_name FROM item_requests r WHERE $scope AND r.status<>'Deleted' ORDER BY r.department_name",$scopeParams);
    $selected=null; $details=[];
    if ((int)($input['view']??0)>0) {
        $selected=cssrRequisitionRows($pdo,$select." WHERE r.id=? AND r.status<>'Deleted' AND $scope",array_merge([(int)$input['view']],$scopeParams))[0]??null;
        if ($selected) {
            $details=cssrRequisitionRows($pdo,'SELECT d.*,i.item_code,i.item_name,i.unit FROM item_request_details d JOIN items i ON i.id=d.item_id WHERE d.request_id=? ORDER BY d.id',[$selected['id']]);
            foreach ($details as &$line) {
                $line['lots']=cssrRequisitionRows($pdo,"SELECT l.id,l.lot_number,l.expiry_date,
                    l.quantity_available-(SELECT COALESCE(SUM(s.quantity),0) FROM stock_reservations s WHERE s.item_lot_id=l.id AND s.status='ACTIVE' AND (s.expires_at IS NULL OR s.expires_at>CURRENT_TIMESTAMP)) AS free_stock
                    FROM item_lots l WHERE l.item_id=? AND l.storage_location='CSSR' AND l.status='AVAILABLE'
                    AND l.lot_number<>'UNIDENTIFIED / PENDING' AND (l.expiry_date IS NULL OR l.expiry_date>=CURRENT_DATE)
                    ORDER BY l.expiry_date IS NULL,l.expiry_date,l.created_at,l.id",[$line['item_id']]);
                $line['lots']=array_values(array_filter($line['lots'],fn($lot)=>(int)$lot['free_stock']>0));
                $line['available_stock']=array_sum(array_column($line['lots'],'free_stock'));
            }
            unset($line);
        }
    }
    return compact('filters','statuses','counts','total','pages','page','rows','departments','selected','details');
}
