<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin_or_cssr();

$pageTitle = 'Inbound History';
$activePage = 'inbound_history';
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
$supplier = trim($_GET['supplier'] ?? '');

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='complete_advance') {
    try {
        verify_csrf_token();
        if(!can_manage()) throw new RuntimeException('Only authorized CSSR personnel can complete an advance delivery.');
        $inboundNo=trim((string)($_POST['inbound_no']??''));
        $orNo=trim((string)($_POST['official_receipt_no']??''));
        $poNo=trim((string)($_POST['po_number']??''));
        $prNo=trim((string)($_POST['pr_number']??''));
        $drNo=trim((string)($_POST['delivery_receipt_no']??''));
        $invoiceNo=trim((string)($_POST['invoice_number']??''));
        $remarks=trim((string)($_POST['completion_remarks']??''));
        if($inboundNo===''||$orNo===''||$remarks==='') throw new RuntimeException('Inbound number, Official Receipt number, and completion remarks are required.');
        db()->beginTransaction();
        $lines=db_all('SELECT s.*,l.storage_location,l.quantity_quarantined FROM stock_in s JOIN item_lots l ON l.id=s.item_lot_id WHERE COALESCE(NULLIF(s.inbound_no,""),s.reference_no)=? AND s.receiving_status="ADVANCE DELIVERY" AND s.record_status="POSTED" FOR UPDATE',[$inboundNo]);
        if(!$lines) throw new RuntimeException('This advance delivery was already completed or was not found.');
        foreach($lines as$line){
            $releaseQty=min((int)$line['quantity_received'],(int)$line['quantity_quarantined']);
            if($releaseQty<=0)continue;
            db_statement('UPDATE item_lots SET quantity_quarantined=quantity_quarantined-?,quantity_available=quantity_available+?,inspection_status=IF(quantity_quarantined-?=0,"CLEARED","PARTIALLY CLEARED"),status="AVAILABLE" WHERE id=?',[$releaseQty,$releaseQty,$releaseQty,$line['item_lot_id']]);
            db_statement('UPDATE items SET current_stock=current_stock+? WHERE id=?',[$releaseQty,$line['item_id']]);
            $balance=(int)db_value('SELECT COALESCE(SUM(quantity_available),0) FROM item_lots WHERE item_id=? AND storage_location=?',[$line['item_id'],$line['storage_location']]);
            $ref=$inboundNo.' / OR '.$orNo;
            if($line['storage_location']==='CSSR')db_statement('INSERT INTO bin_cards(item_id,transaction_date,reference_no,transaction_type,lot_number,expiry_date,received_qty,issued_qty,balance_qty,department,encoded_by,remarks) VALUES(?,CURDATE(),?,"ADVANCE DELIVERY CLEARED",?,?,?,?,?,"ADVANCE DELIVERY",?,?)',[$line['item_id'],$ref,$line['lot_number'],$line['expiry_date'],$releaseQty,0,$balance,current_user()['full_name'],$remarks]);
            else db_statement('INSERT INTO stock_cards(item_id,transaction_date,reference_no,transaction_type,lot_number,expiry_date,received_qty,issued_qty,balance_qty,from_location,to_location,encoded_by,remarks) VALUES(?,NOW(),?,"ADVANCE DELIVERY CLEARED",?,?,?,?,?,"ADVANCE DELIVERY",?,?,?)',[$line['item_id'],$ref,$line['lot_number'],$line['expiry_date'],$releaseQty,0,$balance,$line['storage_location'],current_user()['full_name'],$remarks]);
            db_statement('UPDATE receiving_inspections SET accepted_qty=accepted_qty+?,quarantined_qty=GREATEST(0,quarantined_qty-?),decision="CLEARED",findings=?,inspected_by=?,inspected_at=NOW(),approved_by=?,approved_at=NOW() WHERE stock_in_id=?',[$releaseQty,$releaseQty,$remarks,current_user()['full_name'],current_user()['full_name'],$line['id']]);
        }
        db_statement('UPDATE stock_in SET official_receipt_no=?,po_number=?,pr_number=?,delivery_receipt_no=?,invoice_number=?,receiving_status="INSPECTED",accepted_quantity=accepted_quantity+quarantined_quantity,quarantined_quantity=0,remarks=CONCAT_WS(" | ",NULLIF(remarks,""),?) WHERE COALESCE(NULLIF(inbound_no,""),reference_no)=? AND receiving_status="ADVANCE DELIVERY" AND record_status="POSTED"',[$orNo,$poNo?:null,$prNo?:null,$drNo?:null,$invoiceNo?:null,$remarks,$inboundNo]);
        log_activity('UPDATE','Advance Delivery',$inboundNo.' completed with OR '.$orNo);db()->commit();set_flash('success','Advance delivery completed. The cleared quantity is now available for issuance.');
    }catch(Throwable$e){if(db()->inTransaction())db()->rollBack();set_flash('danger',safe_error_message($e));}
    redirect('inbound_history.php?view='.urlencode((string)($_POST['inbound_no']??'')));
}

if ($_SERVER['REQUEST_METHOD']==='POST' && in_array(($_POST['action']??''),['void_inbound','void_inbound_item'],true)) {
    $voidItem=($_POST['action']??'')==='void_inbound_item';
    try {
        verify_csrf_token();
        if(!can_manage()) throw new RuntimeException('Only authorized CSSR personnel can void an inbound delivery.');
        $stockInId=(int)($_POST['stock_in_id']??0);
        $inboundNo=trim((string)($_POST['inbound_no']??''));$reason=trim((string)($_POST['void_reason']??''));
        if($inboundNo===''||$reason==='')throw new RuntimeException('Inbound number and void reason are required.');
        db()->beginTransaction();
        $lines=$voidItem
            ? db_all('SELECT s.*,l.storage_location,l.quantity_available,l.quantity_quarantined FROM stock_in s JOIN item_lots l ON l.id=s.item_lot_id WHERE s.id=? AND COALESCE(NULLIF(s.inbound_no,""),s.reference_no)=? AND s.record_status="POSTED" FOR UPDATE',[$stockInId,$inboundNo])
            : db_all('SELECT s.*,l.storage_location,l.quantity_available,l.quantity_quarantined FROM stock_in s JOIN item_lots l ON l.id=s.item_lot_id WHERE COALESCE(NULLIF(s.inbound_no,""),s.reference_no)=? AND s.record_status="POSTED" FOR UPDATE',[$inboundNo]);
        if(!$lines)throw new RuntimeException('This inbound delivery was already voided or was not found.');
        foreach($lines as$line){
            $actual=(int)$line['quantity_received'];
            $accepted=(int)($line['accepted_quantity']??0);$quarantined=(int)($line['quarantined_quantity']??0);
            if($accepted===0&&$quarantined===0&&$actual>0){if($line['receiving_status']==='ADVANCE DELIVERY')$quarantined=$actual;else $accepted=$actual;}
            if((int)$line['quantity_available']<$accepted||(int)$line['quantity_quarantined']<$quarantined)throw new RuntimeException('Cannot void '.$line['lot_number'].' because some received quantity was already issued, cleared, transferred, or consumed. Reverse the later transaction first.');
            db_statement('UPDATE item_lots SET quantity_received=GREATEST(0,quantity_received-?),quantity_available=quantity_available-?,quantity_quarantined=quantity_quarantined-?,status=IF(quantity_available-?>0,"AVAILABLE",IF(quantity_quarantined-?>0,"QUARANTINED","DEPLETED")) WHERE id=?',[$accepted+$quarantined,$accepted,$quarantined,$accepted,$quarantined,$line['item_lot_id']]);
            if($accepted>0){
                db_statement('UPDATE items SET current_stock=GREATEST(0,current_stock-?) WHERE id=?',[$accepted,$line['item_id']]);
                $balance=(int)db_value('SELECT COALESCE(SUM(quantity_available),0) FROM item_lots WHERE item_id=? AND storage_location=?',[$line['item_id'],$line['storage_location']]);$ref=$inboundNo.' VOID';
                if($line['storage_location']==='CSSR')db_statement('INSERT INTO bin_cards(item_id,transaction_date,reference_no,transaction_type,lot_number,expiry_date,received_qty,issued_qty,balance_qty,department,encoded_by,remarks) VALUES(?,CURDATE(),?,"VOID INBOUND",?,?,0,?, ?,"INBOUND CORRECTION",?,?)',[$line['item_id'],$ref,$line['lot_number'],$line['expiry_date'],$accepted,$balance,current_user()['full_name'],$reason]);
                else db_statement('INSERT INTO stock_cards(item_id,transaction_date,reference_no,transaction_type,lot_number,expiry_date,received_qty,issued_qty,balance_qty,from_location,to_location,encoded_by,remarks) VALUES(?,NOW(),?,"VOID INBOUND",?,?,0,?,?,?,"VOID",?,?)',[$line['item_id'],$ref,$line['lot_number'],$line['expiry_date'],$accepted,$balance,$line['storage_location'],current_user()['full_name'],$reason]);
            }
            db_statement('UPDATE receiving_inspections SET decision="VOID",findings=CONCAT_WS(" | ",NULLIF(findings,""),?) WHERE stock_in_id=?',[$reason,$line['id']]);
        }
        if($voidItem)db_statement('UPDATE stock_in SET record_status="VOID",voided_by=?,voided_at=NOW(),void_reason=? WHERE id=? AND record_status="POSTED"',[current_user()['full_name'],$reason,$stockInId]);
        else db_statement('UPDATE stock_in SET record_status="VOID",voided_by=?,voided_at=NOW(),void_reason=? WHERE COALESCE(NULLIF(inbound_no,""),reference_no)=? AND record_status="POSTED"',[current_user()['full_name'],$reason,$inboundNo]);
        log_activity('VOID',$voidItem?'Inbound Item':'Inbound Delivery',$inboundNo.' - '.$reason);db()->commit();set_flash('success',($voidItem?'Inbound item':'Inbound delivery').' voided and its remaining stock was reversed.');
    }catch(Throwable$e){if(db()->inTransaction())db()->rollBack();set_flash('danger',safe_error_message($e));}
    redirect($voidItem?'inbound_history.php?view='.urlencode($inboundNo):'inbound_history.php');
}

$where = [];
$params = [];
if ($from !== '') { $where[] = 's.received_date >= ?'; $params[] = $from; }
if ($to !== '') { $where[] = 's.received_date <= ?'; $params[] = $to; }
if ($supplier !== '') { $where[] = 's.supplier = ?'; $params[] = $supplier; }

$sql = 'SELECT COALESCE(NULLIF(s.inbound_no, ""), s.reference_no) AS inbound_no,
               s.reference_no,
               GROUP_CONCAT(DISTINCT s.supplier ORDER BY s.supplier SEPARATOR ", ") AS supplier,
               MIN(s.received_date) AS received_date,
               MAX(s.received_date) AS received_date_to,
               GROUP_CONCAT(DISTINCT s.received_by ORDER BY s.received_by SEPARATOR ", ") AS received_by,
               COUNT(s.id) AS item_count,
               SUM(s.quantity_received) AS total_quantity,
               GROUP_CONCAT(DISTINCT NULLIF(l.storage_location, "") ORDER BY l.storage_location SEPARATOR ", ") AS storage_rooms,
               MIN(s.created_at) AS created_at, GROUP_CONCAT(DISTINCT s.record_status) AS record_status
        FROM stock_in s
        LEFT JOIN item_lots l ON l.id = s.item_lot_id';
if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' GROUP BY COALESCE(NULLIF(s.inbound_no, ""), s.reference_no), s.reference_no
          ORDER BY MIN(s.created_at) DESC
          LIMIT 500';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
$suppliers = db_all('SELECT supplier_name FROM suppliers WHERE status="Active" ORDER BY supplier_name');
$viewInboundNo = trim($_GET['view'] ?? '');
$viewItems = [];
if ($viewInboundNo !== '') {
    $viewItems = db_all(
        'SELECT s.id AS stock_in_id, COALESCE(NULLIF(s.inbound_no, ""), s.reference_no) AS inbound_no,
                s.reference_no, s.pr_number, s.po_number, s.delivery_receipt_no, s.invoice_number, s.official_receipt_no,
                s.procurement_type, s.receiving_status, s.delivery_completion, s.expected_quantity, s.remaining_quantity, s.accepted_quantity, s.quarantined_quantity, s.rejected_quantity, s.partial_reason, s.record_status, s.void_reason, s.remarks, s.supplier, s.received_date, s.received_by, s.quantity_received,
                i.item_description, i.unit, l.lot_number, l.expiry_date, l.storage_location, l.lot_image
         FROM stock_in s
         JOIN items i ON i.id=s.item_id
         LEFT JOIN item_lots l ON l.id=s.item_lot_id
         WHERE COALESCE(NULLIF(s.inbound_no, ""), s.reference_no)=?
         ORDER BY i.item_description, s.id',
        [$viewInboundNo]
    );
}
$historyQuery = http_build_query(array_filter(['from' => $from, 'to' => $to, 'supplier' => $supplier], fn($value) => $value !== ''));

include __DIR__ . '/../includes/header.php';
?>
<section class="page-panel">
    <div class="pos-panel-title">
        <div><span class="eyebrow">History</span><h3>Inbound Deliveries</h3></div>
        <span class="pos-count"><?= count($rows) ?> records</span>
    </div>
    <form class="toolbar no-print" method="get">
        <div class="field"><label class="form-label">From</label><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
        <div class="field"><label class="form-label">To</label><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div>
        <div class="field"><label class="form-label">Supplier</label><select class="form-select" name="supplier"><option value="">All Suppliers</option><?php foreach ($suppliers as $row): ?><option value="<?= e($row['supplier_name']) ?>" <?= $supplier === $row['supplier_name'] ? 'selected' : '' ?>><?= e($row['supplier_name']) ?></option><?php endforeach; ?></select></div>
        <button class="btn btn-primary">Filter</button>
        <a class="btn btn-light" href="inbound_history.php">Reset</a>
    </form>
    <div class="table-responsive">
        <table class="table" data-page-size="25">
            <thead><tr><th>Inbound No.</th><th>PO/PR Number</th><th>Supplier</th><th>Storage Room</th><th>Received Date</th><th>Items</th><th>Total Quantity</th><th>Received By</th><th>Status</th><th class="no-print">Action</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $delivery): ?>
                <tr>
                    <td><strong><?= e($delivery['inbound_no']) ?></strong></td>
                    <td><?= e($delivery['reference_no']) ?></td>
                    <td><?= e($delivery['supplier']) ?></td>
                    <td><?= e($delivery['storage_rooms'] ?: 'N/A') ?></td>
                    <td><?= e($delivery['received_date'] === $delivery['received_date_to'] ? $delivery['received_date'] : $delivery['received_date'] . ' to ' . $delivery['received_date_to']) ?></td>
                    <td><?= (int) $delivery['item_count'] ?></td>
                    <td><?= number_format((int) $delivery['total_quantity']) ?></td>
                    <td><?= e($delivery['received_by']) ?></td>
                    <td><span class="badge <?=str_contains($delivery['record_status'],'VOID')?'bg-danger':'bg-success'?>"><?=e($delivery['record_status'])?></span></td>
                    <td class="no-print"><a class="btn btn-sm btn-light" href="?<?= e(http_build_query(array_filter(['from' => $from, 'to' => $to, 'supplier' => $supplier, 'view' => $delivery['inbound_no']], fn($value) => $value !== ''))) ?>#inboundHistoryDetail">View</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr data-empty-row><td colspan="10" class="text-center">No inbound delivery history found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php if ($viewInboundNo !== ''): ?>
<div id="inboundHistoryDetail" class="app-modal open">
    <div class="app-modal-card inbound-history-detail-card">
        <div class="app-modal-head">
            <div><span class="eyebrow">Inbound Delivery Details</span><h3>Inbound No.: <?= e($viewInboundNo) ?></h3></div>
            <a class="btn btn-light" href="inbound_history.php<?= $historyQuery ? '?' . e($historyQuery) : '' ?>">Close</a>
        </div>
        <div class="app-modal-body">
            <?php $advancePending=(bool)array_filter($viewItems,fn($row)=>$row['receiving_status']==='ADVANCE DELIVERY'&&$row['record_status']==='POSTED'); ?>
            <?php if($advancePending):?><div class="alert alert-warning"><strong>Advance Delivery—On Hold.</strong> Complete the official documents below to release this delivery for issuance.</div><?php endif;?>
            <div class="table-responsive inbound-detail-scroll" tabindex="0" aria-label="Inbound delivery details. Scroll sideways to view all columns.">
                <table class="table">
                    <thead><tr><th>Picture</th><th>Item</th><th>Unit</th><th>Lot Number</th><th>Expiration Date</th><th>Storage Room</th><th>Expected</th><th>Actual</th><th>Remaining</th><th>Accepted</th><th>Quarantined</th><th>Rejected</th><th>Delivery</th><th>Supplier</th><th>PO/PR Number</th><th>Received Date</th><th>Received By</th><th>Status / Correction</th></tr></thead>
                    <tbody>
                    <?php foreach ($viewItems as $item): ?>
                        <tr>
                            <td><?php if ($item['lot_image']): ?><img class="image-thumb" src="../uploads/items/<?= e($item['lot_image']) ?>" alt="<?= e($item['item_description']) ?>" onclick="openImage(this.src)" role="button" tabindex="0"><?php else: ?><span class="mini-thumb empty">IMG</span><?php endif; ?></td>
                            <td><strong><?= e($item['item_description']) ?></strong></td>
                            <td><?= e($item['unit'] ?: 'N/A') ?></td>
                            <td><?= e($item['lot_number'] ?: 'N/A') ?></td>
                            <td><?= e($item['expiry_date'] ?: 'N/A') ?></td>
                            <td><?= e($item['storage_location'] ?: 'N/A') ?></td>
                            <td><?= number_format((int) $item['expected_quantity']) ?></td>
                            <td><?= number_format((int) $item['quantity_received']) ?></td>
                            <td><?= number_format((int) $item['remaining_quantity']) ?></td>
                            <td><?= number_format((int) $item['accepted_quantity']) ?></td>
                            <td><?= number_format((int) $item['quarantined_quantity']) ?></td>
                            <td><?= number_format((int) $item['rejected_quantity']) ?></td>
                            <td><strong><?= e($item['delivery_completion']) ?></strong><br><small><?= e($item['receiving_status']) ?><?= $item['partial_reason'] ? ' · '.e($item['partial_reason']) : '' ?></small></td>
                            <td><?= e($item['supplier']) ?></td>
                            <td><?= e($item['reference_no']) ?></td>
                            <td><?= e($item['received_date']) ?></td>
                            <td><?= e($item['received_by']) ?></td>
                            <td><span class="badge <?=$item['record_status']==='VOID'?'bg-danger':'bg-success'?>"><?=e($item['record_status'])?></span><?php if($item['record_status']==='POSTED'):?><details class="mt-1"><summary class="btn btn-sm btn-light">Wrong Item</summary><form method="post" class="mt-1" data-confirm-message="Void this inbound item and reverse its stock?"><?=csrf_field()?><input type="hidden" name="action" value="void_inbound_item"><input type="hidden" name="inbound_no" value="<?=e($viewInboundNo)?>"><input type="hidden" name="stock_in_id" value="<?=$item['stock_in_id']?>"><input class="form-control form-control-sm mb-1" name="void_reason" required placeholder="Required reason"><button class="btn btn-sm btn-danger">Void Item</button></form></details><?php elseif($item['void_reason']):?><br><small><?=e($item['void_reason'])?></small><?php endif;?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$viewItems): ?><tr><td colspan="18" class="text-center">No items found for this inbound number.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if($advancePending):?><form method="post" class="form-section mt-3" data-confirm-message="Complete this advance delivery and make its held quantity available?"><?=csrf_field()?><input type="hidden" name="action" value="complete_advance"><input type="hidden" name="inbound_no" value="<?=e($viewInboundNo)?>"><div class="form-section-title"><h4>Complete Advance Delivery</h4><span>Document completion and stock release</span></div><div class="row"><div class="col-md-3 mb-2"><label class="form-label required">Official Receipt (OR)</label><input class="form-control" name="official_receipt_no" required></div><div class="col-md-3 mb-2"><label class="form-label">PO Number</label><input class="form-control" name="po_number"></div><div class="col-md-3 mb-2"><label class="form-label">PR Number</label><input class="form-control" name="pr_number"></div><div class="col-md-3 mb-2"><label class="form-label">Delivery Receipt</label><input class="form-control" name="delivery_receipt_no"></div><div class="col-md-3 mb-2"><label class="form-label">Invoice Number</label><input class="form-control" name="invoice_number"></div><div class="col-md-9 mb-2"><label class="form-label required">Completion / Inspection Remarks</label><input class="form-control" name="completion_remarks" required></div></div><button class="btn btn-primary">Complete and Release for Issuance</button></form><?php endif;?>
            <?php $postedInbound=(bool)array_filter($viewItems,fn($row)=>$row['record_status']==='POSTED');if($postedInbound):?><form method="post" class="form-section mt-3" data-confirm-message="Void this entire inbound delivery and reverse its stock?"><?=csrf_field()?><input type="hidden" name="action" value="void_inbound"><input type="hidden" name="inbound_no" value="<?=e($viewInboundNo)?>"><div class="form-section-title"><h4>Wrong Inbound / Wrong Items</h4><span>Controlled correction—record remains in audit history</span></div><label class="form-label required">Void Reason</label><input class="form-control mb-2" name="void_reason" placeholder="Explain what was entered incorrectly" required><button class="btn btn-danger">Void Inbound and Reverse Stock</button></form><?php endif;?>
        </div>
    </div>
</div>
<?php endif; ?>
<div id="imageModal" class="modal-backdrop" onclick="closeImageModal()" aria-hidden="true">
    <div class="modal-box" role="dialog" aria-modal="true" aria-label="Inbound item image" onclick="event.stopPropagation()" style="position:relative;max-width:960px">
        <button type="button" class="btn btn-light" data-image-modal-close aria-label="Close image" style="position:absolute;top:12px;right:12px;z-index:2">&times;</button>
        <img src="" alt="Inbound item image preview" style="max-width:100%;max-height:80vh;object-fit:contain">
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
