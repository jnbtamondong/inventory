<?php
// Shared, read-only report model. The screen and downloads use this same result.
function cssrReportTypes(): array
{
    return ['monthly'=>'Monthly Report','weekly'=>'Weekly Report','annual'=>'Annual Report','near_expiry'=>'Near Expiry',
        'consumption'=>'Consumption Report','available'=>'Available Stock','stock_movement'=>'Stock Movement','inbound'=>'Inbound Report','issuance'=>'Issuance Report',
        'department_consumption'=>'Department Consumption','procurement_type'=>'Procurement Type Report',
        'low_stock'=>'Low Stock Report','out_of_stock'=>'Out of Stock Report','fast_moving'=>'Fast-Moving Items','inventory_balance'=>'Inventory Balance'];
}
function cssrReportNavigationActive(string $report): string
{
    return ['monthly'=>'periodic-reports','weekly'=>'periodic-reports','annual'=>'periodic-reports','near_expiry'=>'expiry-reports',
        'consumption'=>'usage-reports','department_consumption'=>'usage-reports','out_of_stock'=>'out-of-stock-reports',
        'low_stock'=>'low-stock-reports','available'=>'available-reports','inventory_balance'=>'balance-reports',
        'inbound'=>'inbound-reports','issuance'=>'issuance-reports','procurement_type'=>'procurement-reports',
        'fast_moving'=>'fast-moving-reports'][$report]??'inventory-reports';
}
function cssrReportHelp(string $report): array
{
    return match($report){
        'monthly'=>['Review supplies received and issued during a selected month.','No deliveries or issues match this month. Choose another date range or complete a delivery or supply release.'],
        'weekly'=>['Review supplies received and issued during a selected week.','No deliveries or issues match this week. Choose another date range or complete a delivery or supply release.'],
        'annual'=>['Review supplies received and issued during a selected year.','No deliveries or issues match this year. Choose another year or complete a delivery or supply release.'],
        'consumption'=>['See the quantity issued for each item between From Date and To Date.','No issued supplies match these dates. Consumption appears after supplies are released to a department.'],
        'department_consumption'=>['Compare issued supplies by department and item within the selected dates.','No issued supplies match this department and period. Select another department or date range.'],
        'near_expiry'=>['Find usable stock batches approaching their expiration date.','No usable batches expire in this window. Adjust From Date and To Date to review a different expiry period.'],
        'available'=>['Review items with usable stock at or above their reorder level.','No items meet the Available status with these filters. Check Stock Balances to review all items.'],
        'low_stock'=>['Find items with positive usable stock below their reorder level.','No items have low stock with these filters. Items with zero stock appear in Out of Stock instead.'],
        'out_of_stock'=>['Find items with no usable stock across storage rooms and CSSR.','No items are out of stock with these filters.'],
        'inventory_balance'=>['Review current usable balances and stock status for each item.','No active items match these filters. Clear the department or classification filter to broaden the list.'],
        'inbound'=>['Review posted deliveries, suppliers, quantities, destinations, and costs.','No posted deliveries match these dates. Complete an inbound delivery or select another period.'],
        'issuance'=>['Review supplies released to departments within the selected dates.','No supplies have been issued in this period. Complete a supply release or select another date range.'],
        'procurement_type'=>['Compare received quantities and values for procured, donated, and emergency supplies.','No posted deliveries match these filters. This report groups completed deliveries by procurement type.'],
        'fast_moving'=>['Rank items by the quantity issued during the selected period.','No issued supplies match these filters. Rankings appear after supplies are released.'],
        default=>['Review posted deliveries and issued supplies within the selected dates.','No posted deliveries or issues match these filters. Existing stock alone does not create transaction history.'],
    };
}
function cssrReportFilters(array $input, ?DateTimeImmutable $now=null): array
{
    $now=$now??new DateTimeImmutable('now',new DateTimeZone('Asia/Manila'));
    $text=static fn($key,$default='')=>isset($input[$key])&&is_scalar($input[$key])?trim((string)$input[$key]):$default;
    $types=cssrReportTypes();$report=$text('report','stock_movement');
    $aliases=['inventory'=>'inventory_balance','stock_in'=>'inbound','stock_out'=>'issuance'];
    $report=$aliases[$report]??$report;
    [$defaultFrom,$defaultTo]=match($report){
        'monthly'=>[$now->format('Y-m-01'),$now->format('Y-m-t')],
        'weekly'=>[$now->modify('monday this week')->format('Y-m-d'),$now->modify('sunday this week')->format('Y-m-d')],
        'annual'=>[$now->format('Y-01-01'),$now->format('Y-12-31')],
        'near_expiry'=>[$now->format('Y-m-d'),$now->modify('+90 days')->format('Y-m-d')],
        default=>[$now->format('Y-01-01'),$now->format('Y-m-d')],
    };
    $f=['from'=>$text('from',$defaultFrom),'to'=>$text('to',$defaultTo),
        'department'=>$text('department'),
        'classification'=>$text('classification'),'procurement_type'=>$text('procurement_type'),'report'=>$report];$errors=[];
    foreach(['from','to'] as $key){$d=DateTimeImmutable::createFromFormat('!Y-m-d',$f[$key]);if(!$d||$d->format('Y-m-d')!==$f[$key])$errors[$key]='Enter a valid date.';}
    if(!$errors){if($f['from']>$f['to'])$errors['to']='To Date must be on or after From Date.';elseif((int)substr($f['to'],0,4)-(int)substr($f['from'],0,4)>10)$errors['to']='Generate up to 10 years at a time.';}
    if(!isset($types[$report]))$errors['report']='Choose a report type.';
    if(!in_array($f['procurement_type'],['','Procured','Donation','Emergency Purchase'],true))$errors['procurement_type']='Choose a procurement type.';
    return ['filters'=>$f,'errors'=>$errors,'types'=>$types,'now'=>$now];
}
function cssrReportProcurementSql(string $field): string
{
    return "CASE WHEN UPPER(TRIM(COALESCE($field,''))) IN ('REGULAR','PROCURED') THEN 'Procured' WHEN UPPER(TRIM(COALESCE($field,'')))='DONATION' THEN 'Donation' WHEN UPPER(TRIM(COALESCE($field,''))) IN ('EMERGENCY PURCHASE','EMERGENCY_PURCHASE') THEN 'Emergency Purchase' ELSE 'Unspecified' END";
}
function cssrReportData(PDO $db,array $input,?DateTimeImmutable $now=null): array
{
    $d=cssrReportFilters($input,$now);$f=$d['filters'];$d['generated_at']=$d['now']->format('Y-m-d H:i').' PHT';unset($d['now']);
    $fetch=static function($sql,$params=[])use($db){$s=$db->prepare($sql);$s->execute($params);return $s->fetchAll(PDO::FETCH_ASSOC);};
    // Legacy tables can use different collations. Merge labels in PHP instead of UNIONing text columns.
    $d['departments']=array_values(array_unique(array_merge(
        array_column($fetch("SELECT department_name FROM departments WHERE status='Active'"),'department_name'),
        array_column($fetch("SELECT DISTINCT department FROM stock_out WHERE department IS NOT NULL AND department<>''"),'department')
    )));
    natcasesort($d['departments']);$d['departments']=array_values($d['departments']);
    $d['classifications']=array_column($fetch("SELECT DISTINCT category FROM items WHERE category IS NOT NULL AND category<>'' ORDER BY category"),'category');
    $d+=['rows'=>[],'chart'=>[],'totals'=>['items'=>0,'in'=>0,'out'=>0,'value'=>0,'balance'=>0],'columns'=>[],'pages'=>1,'page'=>1,'count'=>0];
    if($d['errors'])return $d;
    $start=$f['from'];$end=$f['to'];
    $d['period']=$start<=$end?$start.' to '.$end:'No overlapping dates';
    $predicate=static function(string $date,string $type,bool $department=false)use($f,$start,$end){
        $where="$date >= ? AND $date <= ?";$params=[$start,$end];
        if($f['classification']!==''){$where.=' AND i.category=?';$params[]=$f['classification'];}
        if($f['procurement_type']!==''){$where.=' AND '.cssrReportProcurementSql($type).'=?';$params[]=$f['procurement_type'];}
        if($f['department']!==''){$where.=$department?' AND s.department=?':' AND 1=0';if($department)$params[]=$f['department'];}
        return [$where,$params];
    };
    [$where,$params]=$predicate('s.received_date','s.procurement_type');
    $receipts=$fetch("SELECT s.id,s.item_id,s.received_date AS date,s.reference_no,i.item_code,COALESCE(NULLIF(i.item_name,''),i.item_description) AS item_name,i.category,i.unit,s.supplier AS party,s.quantity_received AS quantity,COALESCE(s.unit_price,0) AS cost,".cssrReportProcurementSql('s.procurement_type')." AS procurement_type,COALESCE(l.storage_location,'Unspecified') AS location FROM stock_in s JOIN items i ON i.id=s.item_id LEFT JOIN item_lots l ON l.id=s.item_lot_id WHERE COALESCE(s.record_status,'POSTED')='POSTED' AND $where ORDER BY s.received_date DESC,s.id DESC",$params);
    [$where,$params]=$predicate('s.issued_date','i.procurement_type',true);
    $issues=$fetch("SELECT s.id,s.item_id,s.issued_date AS date,s.reference_no,i.item_code,COALESCE(NULLIF(i.item_name,''),i.item_description) AS item_name,i.category,i.unit,s.department AS party,s.quantity_issued AS quantity,COALESCE(l.unit_price,0) AS cost,".cssrReportProcurementSql('i.procurement_type')." AS procurement_type,COALESCE(l.storage_location,'Unspecified') AS location FROM stock_out s JOIN items i ON i.id=s.item_id LEFT JOIN item_lots l ON l.id=s.item_lot_id WHERE $where ORDER BY s.issued_date DESC,s.id DESC",$params);
    $byItem=[];$months=[];
    if($start<=$end){$cursor=new DateTimeImmutable(substr($start,0,7).'-01');$last=new DateTimeImmutable($end);while($cursor<=$last){$months[$cursor->format('Y-m')]=['label'=>$cursor->format('M Y'),'in'=>0,'out'=>0];$cursor=$cursor->modify('+1 month');}}
    foreach(['in'=>$receipts,'out'=>$issues] as $direction=>$records)foreach($records as $r){$id=(int)$r['item_id'];$qty=(int)$r['quantity'];$byItem[$id][$direction]=($byItem[$id][$direction]??0)+$qty;$month=substr($r['date'],0,7);if(isset($months[$month]))$months[$month][$direction]+=$qty;$d['totals'][$direction]+=$qty;if($direction==='in')$d['totals']['value']+=$qty*(float)$r['cost'];}
    $d['chart']=array_values($months);$d['totals']['items']=count($byItem);$rows=[];
    $common=['code'=>'Item Code','name'=>'Item Name','unit'=>'Unit'];
    if(in_array($f['report'],['low_stock','out_of_stock','available','inventory_balance','near_expiry'],true)){
        $where="i.status='Active'";$params=[];
        if($f['classification']!==''){$where.=' AND i.category=?';$params[]=$f['classification'];}
        if($f['procurement_type']!==''){$where.=' AND '.cssrReportProcurementSql('i.procurement_type').'=?';$params[]=$f['procurement_type'];}
        // Current usable balances across all locations, never reconstructed from an incomplete ledger.
        $today=substr($d['generated_at'],0,10);
        if($f['report']==='near_expiry'){
            $expiryWhere="b.expiry_date>=? AND b.expiry_date<=? AND b.expiry_date>=?";
            $expiryParams=[$start,$end,$today];
            $stocks=[];
            foreach([
                ['item_lots','b.storage_location','b.quantity_available',"b.quantity_available>0 AND b.status IN ('AVAILABLE','DEPLETED') AND COALESCE(b.recall_status,'CLEAR')='CLEAR'"],
                ['cssr_available_stock',"'CSSR'",'b.quantity',"b.quantity>0 AND b.status='AVAILABLE'"],
            ] as [$table,$location,$quantity,$usable]){
                $stocks=array_merge($stocks,$fetch("SELECT i.id,i.item_code,COALESCE(NULLIF(i.item_name,''),i.item_description) AS item_name,i.unit,i.minimum_stock,
                    b.lot_number,b.expiry_date,$location AS location,$quantity AS balance
                    FROM $table b JOIN items i ON i.id=b.item_id WHERE $where AND $expiryWhere AND $usable",array_merge($params,$expiryParams)));
            }
            usort($stocks,static fn($a,$b)=>strcmp($a['expiry_date'],$b['expiry_date'])?:strcmp($a['item_name'],$b['item_name'])?:($a['id']<=>$b['id']));
        }else $stocks=$fetch("SELECT i.id,i.item_code,COALESCE(NULLIF(i.item_name,''),i.item_description) AS item_name,i.unit,i.minimum_stock,
            COALESCE((SELECT SUM(l.quantity_available) FROM item_lots l WHERE l.item_id=i.id AND l.status IN ('AVAILABLE','DEPLETED') AND COALESCE(l.recall_status,'CLEAR')='CLEAR' AND (l.expiry_date IS NULL OR l.expiry_date='' OR l.expiry_date>=?)),0)
            + COALESCE((SELECT SUM(c.quantity) FROM cssr_available_stock c WHERE c.item_id=i.id AND c.status='AVAILABLE' AND (c.expiry_date IS NULL OR c.expiry_date='' OR c.expiry_date>=?)),0) AS balance
            FROM items i WHERE $where ORDER BY item_name,i.id",array_merge([$today,$today],$params));
        $d['totals']['balance']=0;
        foreach($stocks as $s){$id=(int)$s['id'];if($f['department']!==''&&!isset($byItem[$id]['out']))continue;$balance=(int)$s['balance'];$minimum=(int)$s['minimum_stock'];$status=$balance<=0?'Out of Stock':($balance<$minimum?'Low Stock':'Available');
            if($f['report']==='low_stock'&&$status!=='Low Stock')continue;if($f['report']==='out_of_stock'&&$status!=='Out of Stock')continue;
            if($f['report']==='available'&&$status!=='Available')continue;
            if($f['report']==='near_expiry'){
                $rows[]=['code'=>$s['item_code'],'name'=>$s['item_name'],'unit'=>$s['unit'],'lot'=>$s['lot_number']?:'N/A','expiry'=>$s['expiry_date'],'location'=>$s['location'],'balance'=>$balance,'status'=>'Near Expiry'];
                $d['totals']['balance']+=$balance;continue;
            }
            $rows[]=['code'=>$s['item_code'],'name'=>$s['item_name'],'unit'=>$s['unit'],'in'=>$byItem[$id]['in']??0,'out'=>$byItem[$id]['out']??0,'balance'=>$balance,'reorder'=>$minimum,'status'=>$status];$d['totals']['balance']+=$balance;}
        $d['columns']=$common+['in'=>'Stock In','out'=>'Stock Out','balance'=>'Current Balance','status'=>'Status'];
        if($f['report']==='near_expiry')$d['columns']=$common+['lot'=>'Lot / Batch','expiry'=>'Expiration Date','location'=>'Location','balance'=>'Current Quantity','status'=>'Status'];
        $ids=array_flip(array_column($rows,'code'));$months=array_map(static fn($m)=>['label'=>$m['label'],'in'=>0,'out'=>0],$months);$d['totals']['in']=0;$d['totals']['out']=0;$d['totals']['value']=0;
        foreach(['in'=>$receipts,'out'=>$issues] as $dir=>$records)foreach($records as $r)if(isset($ids[$r['item_code']])){$months[substr($r['date'],0,7)][$dir]+=(int)$r['quantity'];$d['totals'][$dir]+=(int)$r['quantity'];if($dir==='in')$d['totals']['value']+=(int)$r['quantity']*(float)$r['cost'];}
        $d['chart']=array_values($months);$d['totals']['items']=count($ids);
    }elseif(in_array($f['report'],['consumption','department_consumption','fast_moving'],true)){
        $group=[];foreach($issues as $r){$key=($f['report']==='department_consumption'?$r['party'].'|':'').$r['item_id'];if(!isset($group[$key]))$group[$key]=['department'=>$r['party'],'code'=>$r['item_code'],'name'=>$r['item_name'],'unit'=>$r['unit'],'out'=>0];$group[$key]['out']+=(int)$r['quantity'];}
        $rows=array_values($group);usort($rows,static fn($a,$b)=>$b['out']<=>$a['out']?:strcmp($a['name'],$b['name']));
        $d['columns']=($f['report']==='department_consumption'?['department'=>'Department']:[])+$common+['out'=>'Quantity Issued'];
    }elseif($f['report']==='procurement_type'){
        foreach(['Procured','Donation','Emergency Purchase','Unspecified'] as $type){$r=['type'=>$type,'receipts'=>0,'in'=>0,'value'=>0];foreach($receipts as $s)if($s['procurement_type']===$type){$r['receipts']++;$r['in']+=(int)$s['quantity'];$r['value']+=(int)$s['quantity']*(float)$s['cost'];}if($r['receipts'])$rows[]=$r;}
        $d['columns']=['type'=>'Procurement Type','receipts'=>'Receipt Lines','in'=>'Quantity Received','value'=>'Received Value (PHP)'];
    }else{
        foreach(['in'=>$receipts,'out'=>$issues] as $dir=>$records){if($f['report']==='inbound'&&$dir!=='in'||$f['report']==='issuance'&&$dir!=='out')continue;
            foreach($records as $r)$rows[]=['date'=>$r['date'],'reference'=>$r['reference_no'],'code'=>$r['item_code'],'name'=>$r['item_name'],'unit'=>$r['unit'],'party'=>$r['party'],'type'=>$r['procurement_type'],'location'=>$r['location'],'in'=>$dir==='in'?(int)$r['quantity']:0,'out'=>$dir==='out'?(int)$r['quantity']:0,'value'=>(int)$r['quantity']*(float)$r['cost']];}
        usort($rows,static fn($a,$b)=>strcmp($b['date'],$a['date'])?:strcmp($a['reference'],$b['reference']));
        $d['columns']=['date'=>'Date','reference'=>'Reference']+$common;
        if(in_array($f['report'],['stock_movement','monthly','weekly','annual'],true))$d['columns']+=['party'=>'Supplier / Department','in'=>'Items In','out'=>'Items Out'];
        elseif($f['report']==='inbound')$d['columns']+=['party'=>'Supplier','type'=>'Procurement Type','location'=>'Destination','in'=>'Quantity','value'=>'Value (PHP)'];
        else $d['columns']+=['party'=>'Department','out'=>'Quantity Issued'];
    }
    // Chart always compares both directions for the selected scope. Table-specific counts are explicit.
    $d['rows']=$rows;$d['count']=count($rows);$d['pages']=max(1,(int)ceil(count($rows)/25));$d['page']=max(1,min($d['pages'],is_scalar($input['page']??1)?(int)($input['page']??1):1));
    $d['title']=$d['types'][$f['report']];$d['snapshot']=in_array($f['report'],['low_stock','out_of_stock','available','inventory_balance','near_expiry'],true);
    $d['notes']=[
        'From Date and To Date define the reporting period, including both dates. The chart compares posted receipts and gross issues; drafts, void receipts, internal transfers and returns are excluded.',
        'Department limits issues to the selected department. Supplier receipts have no department and are excluded when a department is selected.',
        'Procurement Type uses the receipt type for inbound and the item master classification for issues and current balances. Unclassified receipts appear as Unspecified.',
    ];
    if($d['snapshot'])$d['notes'][]='Current Balance is usable stock now across all storage locations, including CSSR. It excludes expired, recalled and quarantined batches. Dates filter Stock In and Stock Out, not the current balance. Department limits the item list to items issued to that department in the selected period.';
    if($f['report']==='near_expiry')$d['notes'][]='Near Expiry lists positive, usable batches expiring within From Date and To Date (inclusive), excluding already expired batches. The default is today through the next 90 days. The movement chart covers only these items within the selected period.';
    if(in_array($f['report'],['consumption','department_consumption'],true))$d['notes'][]='Consumption is gross issued quantity within the selected From Date and To Date. Returns are not deducted. Consumption Report combines departments per item; Department Consumption groups each item by department.';
    if($f['report']==='fast_moving')$d['notes'][]='Items are ranked by gross issued quantity in the selected period. Quantities use each item’s unit; packs, boxes and pieces are not converted.';
    $d['filter_label']='From: '.$f['from'].' | To: '.$f['to'].' | Department: '.($f['department']?:'All').' | Classification: '.($f['classification']?:'All').' | Procurement: '.($f['procurement_type']?:'All');
    return $d;
}
