<?php
require_once __DIR__.'/../includes/inbound-data.php';
$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE procurement_requests(id INTEGER,procurement_pr_no TEXT,pr_no TEXT,ppmp_id INTEGER);
CREATE TABLE procurement_ppmp(id INTEGER,ppmp_no TEXT);
CREATE TABLE procurement_request_items(id INTEGER,request_id INTEGER,winning_supplier TEXT,quantity_requested INTEGER,quantity_delivered INTEGER,procurement_status TEXT);
INSERT INTO procurement_requests VALUES(1,'PROC-1','CSSR-1',1),(2,'','CSSR-2',1);
INSERT INTO procurement_ppmp VALUES(1,'PPMP-1');
INSERT INTO procurement_request_items VALUES
(1,1,'Supplier A',10,0,'PENDING DELIVERY'),
(2,1,'Supplier A',8,3,'PARTIALLY DELIVERED'),
(3,1,'Supplier B',4,0,'PENDING DELIVERY'),
(4,1,'Supplier A',9,0,'AWAITING QUOTATION'),
(5,1,'Supplier A',5,5,'DELIVERED'),
(6,2,'Supplier C',2,0,'PENDING DELIVERY');");
$rows=cssrInboundProcurementQueue($db);
if(count($rows)!==3 || $rows[0]['pr_number']!=='CSSR-2' || $rows[1]['pr_number']!=='PROC-1' || (int)$rows[1]['item_count']!==2 || (int)$rows[1]['remaining_qty']!==15 || $rows[2]['winning_supplier']!=='Supplier B')throw new RuntimeException('Queue grouping, remaining quantities, or status filtering failed.');
$db->exec("UPDATE procurement_request_items SET quantity_delivered=quantity_requested,procurement_status='DELIVERED' WHERE id IN(1,2)");
if(count(cssrInboundProcurementQueue($db))!==2)throw new RuntimeException('Fully received supplier group remains in queue.');
echo "Passed procurement queue grouping, PR fallback, partial balances, status filtering, and completed delivery removal.\n";
