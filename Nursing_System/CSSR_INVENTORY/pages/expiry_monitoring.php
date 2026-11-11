<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin_or_cssr();
$pageTitle = 'Expiry Monitoring';
$activePage = 'expiry_monitoring';
$days = in_array(($_GET['days'] ?? '240'), ['30','60','90','120','180','240'], true) ? (int) $_GET['days'] : 240;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove_expiry_lot') {
    try {
        verify_csrf_token();
        if (!can_manage()) throw new RuntimeException('Only authorized CSSR personnel can remove a lot from active inventory.');
        $lotId = (int) ($_POST['lot_id'] ?? 0);
        $disposition = strtoupper(trim((string) ($_POST['disposition'] ?? '')));
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if (!in_array($disposition, ['RETURNED', 'CONDEMNED'], true) || $reason === '') {
            throw new RuntimeException('Select Return to Supplier or Condemn and enter a reason.');
        }
        db()->beginTransaction();
        $lot = db_one('SELECT l.*,i.item_description FROM item_lots l JOIN items i ON i.id=l.item_id WHERE l.id=? FOR UPDATE', [$lotId]);
        if (!$lot || (int) $lot['quantity_available'] <= 0) throw new RuntimeException('This lot has no available quantity to remove.');
        $qty = (int) $lot['quantity_available'];
        $newStatus = $disposition === 'RETURNED' ? 'RETURNED TO SUPPLIER' : 'CONDEMNED';
        db_statement(
            'UPDATE item_lots SET quantity_available=0,quantity_returned=quantity_returned+?,inspection_status=?,status=? WHERE id=?',
            [$disposition === 'RETURNED' ? $qty : 0, $newStatus, $newStatus, $lotId]
        );
        $itemBalance = (int) db_value('SELECT COALESCE(SUM(quantity_available),0) FROM item_lots WHERE item_id=?', [$lot['item_id']]);
        db_statement('UPDATE items SET current_stock=? WHERE id=?', [$itemBalance, $lot['item_id']]);
        $locationBalance = (int) db_value('SELECT COALESCE(SUM(quantity_available),0) FROM item_lots WHERE item_id=? AND storage_location=?', [$lot['item_id'], $lot['storage_location']]);
        $reference = ($disposition === 'RETURNED' ? 'RTS-' : 'CON-') . date('Ymd-His') . '-' . $lotId;
        $remarks = $newStatus . ': ' . $reason;
        if ($lot['storage_location'] === 'CSSR') {
            db_statement('INSERT INTO bin_cards(item_id,transaction_date,reference_no,transaction_type,lot_number,expiry_date,received_qty,issued_qty,balance_qty,department,encoded_by,remarks) VALUES(?,CURDATE(),?,?,?,?,0,?,?,"EXPIRY DISPOSITION",?,?)', [$lot['item_id'],$reference,$newStatus,$lot['lot_number'],$lot['expiry_date'],$qty,$locationBalance,current_user()['full_name'],$remarks]);
        } else {
            db_statement('INSERT INTO stock_cards(item_id,transaction_date,reference_no,transaction_type,lot_number,expiry_date,received_qty,issued_qty,balance_qty,from_location,to_location,encoded_by,remarks) VALUES(?,NOW(),?,?,?,?,0,?,?,?,?,?,?)', [$lot['item_id'],$reference,$newStatus,$lot['lot_number'],$lot['expiry_date'],$qty,$locationBalance,$lot['storage_location'],$disposition === 'RETURNED' ? 'SUPPLIER' : 'CONDEMNED STOCK',current_user()['full_name'],$remarks]);
        }
        db_statement('INSERT INTO stock_adjustments(adjustment_no,location_name,stock_type,source_record_id,item_id,item_name,lot_number,expiry_date,system_quantity,actual_quantity,variance_quantity,reason,adjusted_by,adjusted_at) VALUES(?,?,?,?,?,?,?,?,?,0,?,?,?,NOW())', [$reference,$lot['storage_location'],'EXPIRY DISPOSITION',$lotId,$lot['item_id'],$lot['item_description'],$lot['lot_number'],$lot['expiry_date'],$qty,-$qty,$remarks,current_user()['full_name']]);
        log_activity('UPDATE', 'Expiry Disposition', $reference . ' ' . $remarks);
        db()->commit();
        set_flash('success', 'Lot removed from available inventory and marked ' . $newStatus . '.');
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        set_flash('danger', safe_error_message($e));
    }
    redirect('expiry_monitoring.php?days=' . $days);
}
$stmt = db()->prepare(
    'SELECT i.id, l.id AS lot_id, i.item_code, COALESCE(NULLIF(TRIM(i.item_name),""),NULLIF(TRIM(i.item_description),""),i.item_code) AS item_description, i.item_image,
            l.lot_number, l.expiry_date, l.quantity_available, l.brand,
            l.supplier, l.storage_location,
            DATEDIFF(l.expiry_date, CURDATE()) AS days_left
     FROM item_lots l
     JOIN items i ON i.id=l.item_id
     WHERE i.status = "Active"
       AND l.quantity_available > 0
       AND l.expiry_date IS NOT NULL
       AND l.expiry_date <> "0000-00-00"
       AND l.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
     ORDER BY l.expiry_date ASC, i.item_description ASC, l.lot_number ASC'
);
$stmt->execute([$days]);
$items = $stmt->fetchAll();
include __DIR__ . '/../includes/header.php';
?>
<section class="page-panel cssr-report-document expiry-monitoring-report">
    <div class="report-print-header print-only">
        <div class="bin-card-header">
            <img class="bin-logo" src="../assets/img/doh-logo.png" alt="Department of Health logo">
            <div class="facility-copy">
                <span>Republic of the Philippines</span>
                <strong>DEPARTMENT OF HEALTH</strong>
                <span>Central Luzon Center for Health Development</span>
                <strong>JONI VILLANUEVA GENERAL HOSPITAL</strong>
                <span>National Rd. Igulot, Bocaue, Bulacan</span>
                <small>Contact #: 0960 302 6389 E-mail: jvgh@centralluzon.doh.gov.ph</small>
            </div>
            <img class="bin-logo" src="../assets/img/jvgh-seal.png" alt="Joni Villanueva General Hospital seal">
        </div>
        <h2>Expiry Monitoring Report</h2>
    </div>
    <form class="toolbar" method="get">
        <div class="field report-type-field"><label class="form-label">Near Expiry Window</label><select class="form-select" name="days"><option value="30" <?= $days === 30 ? 'selected' : '' ?>>30 days</option><option value="60" <?= $days === 60 ? 'selected' : '' ?>>60 days</option><option value="90" <?= $days === 90 ? 'selected' : '' ?>>90 days</option><option value="120" <?= $days === 120 ? 'selected' : '' ?>>120 days</option><option value="180" <?= $days === 180 ? 'selected' : '' ?>>6 months</option><option value="240" <?= $days === 240 ? 'selected' : '' ?>>8 months</option></select></div>
        <div class="report-toolbar-actions"><button class="btn btn-primary">Generate</button><button type="button" onclick="printPage()" class="btn btn-light">Print</button><a class="btn btn-light" href="expiry_monitoring.php">Reset</a></div>
    </form>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th class="no-print">Picture</th><th>Code</th><th>Description</th><th>Lot</th><th class="no-print">Location</th><th>Qty</th><th>Expiry Date</th><th>Days Left</th><th>Status</th><th class="no-print">Recommended Action</th><th class="no-print">Action</th></tr></thead>
            <tbody>
            <?php foreach ($items as $item):
                $daysLeft = (int) $item['days_left'];
                $isExpired = $daysLeft < 0;
                if ($isExpired) { $expiryStatus='EXPIRED'; $badgeClass='bg-danger'; $recommendedAction='Quarantine and remove from active issuance.'; }
                elseif ($daysLeft <= 30) { $expiryStatus='EXPIRING WITHIN 30 DAYS'; $badgeClass='bg-danger'; $recommendedAction='Issue immediately using FEFO and notify the Department Head.'; }
                elseif ($daysLeft <= 90) { $expiryStatus='NEAR EXPIRY'; $badgeClass='bg-warning'; $recommendedAction='Prioritize issuance using FEFO.'; }
                elseif ($daysLeft <= 180) { $expiryStatus='EXPIRING IN 6 MONTHS'; $badgeClass='bg-warning'; $recommendedAction='Plan consumption and monitor monthly.'; }
                else { $expiryStatus='EXPIRING IN 8 MONTHS'; $badgeClass='bg-info'; $recommendedAction='Monitor and include in the expiry action plan.'; }
            ?>
                <tr>
                    <td class="no-print"><?php if ($item['item_image']): ?><img class="image-thumb" src="../uploads/items/<?= e($item['item_image']) ?>" alt=""><?php else: ?><span class="mini-thumb empty">IMG</span><?php endif; ?></td>
                    <td><strong><?= e($item['item_code']) ?></strong></td>
                    <td><?= e($item['item_description']) ?></td>
                    <td><?= e($item['lot_number']) ?></td>
                    <td class="no-print"><?= e($item['storage_location'] ?: 'N/A') ?></td>
                    <td><?= (int) $item['quantity_available'] ?></td>
                    <td><?= e(date('Y-m-d', strtotime($item['expiry_date']))) ?></td>
                    <td><?= $isExpired ? abs($daysLeft) . ' day(s) expired' : $daysLeft . ' day(s)' ?></td>
                    <td><span class="expiry-status-text"><?= e($expiryStatus) ?></span></td>
                    <td class="no-print"><?= e($recommendedAction) ?></td>
                    <td class="no-print"><button class="btn btn-sm btn-danger" type="button" onclick='openExpiryRemoval(<?= e(json_encode(["lot_id"=>(int)$item["lot_id"],"item"=>$item["item_description"],"lot"=>$item["lot_number"],"location"=>$item["storage_location"],"qty"=>(int)$item["quantity_available"],"expiry"=>$item["expiry_date"]])) ?>)'>Remove Lot</button></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?><tr><td colspan="11" class="text-center">No stocked lots expire within the selected period.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="bin-card-footer print-only">
        <div class="bin-footer-note">This is a computer-generated form. &nbsp; No<br>stamping is needed.</div>
        <div class="bin-footer-code">
            <strong>JVGH-CSSR-XX-XXX</strong>
            <table>
                <tr><th>Rev Number</th><td></td></tr>
                <tr><th>Eff Date</th><td></td></tr>
            </table>
        </div>
    </div>
</section>
<div class="app-modal" id="expiryRemovalModal" aria-hidden="true">
    <div class="app-modal-card" style="max-width:560px">
        <div class="app-modal-head">
            <div><span class="eyebrow">Expiry Disposition</span><h3>Remove Lot from Active Inventory</h3></div>
            <button class="btn btn-light" type="button" onclick="closeExpiryRemoval()">Close</button>
        </div>
        <div class="app-modal-body">
            <div class="form-section mb-3 expiry-removal-summary">
                <div class="expiry-summary-item expiry-summary-wide"><span>Item</span><strong id="expiryRemovalItem"></strong></div>
                <div class="expiry-summary-item"><span>Lot Number</span><strong id="expiryRemovalLot"></strong></div>
                <div class="expiry-summary-item"><span>Expiration Date</span><strong id="expiryRemovalDate"></strong></div>
                <div class="expiry-summary-item"><span>Location</span><strong id="expiryRemovalLocation"></strong></div>
                <div class="expiry-summary-item"><span>Quantity to Remove</span><strong id="expiryRemovalQty"></strong></div>
            </div>
            <form method="post" id="expiryRemovalForm" data-confirm-message="Remove this lot and its entire available quantity from active inventory?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="remove_expiry_lot">
                <input type="hidden" name="lot_id" id="expiryRemovalLotId">
                <div class="mb-3"><label class="form-label required">Disposition</label><select class="form-select" name="disposition" required><option value="">Select action</option><option value="RETURNED">Return to Supplier</option><option value="CONDEMNED">Condemn</option></select></div>
                <div class="mb-3"><label class="form-label required">Reason / Document Reference</label><textarea class="form-control" name="reason" rows="3" required placeholder="Explain the reason and enter the return, condemnation, or supporting document number"></textarea></div>
                <div class="alert alert-warning"><strong>Important:</strong> The entire available quantity of this lot will be removed from active inventory. The transaction cannot be silently deleted and will remain in the audit history.</div>
                <div class="modal-form-actions"><button class="btn btn-light" type="button" onclick="closeExpiryRemoval()">Cancel</button><button class="btn btn-danger" type="submit">Confirm Removal</button></div>
            </form>
        </div>
    </div>
</div>
<script>
function openExpiryRemoval(data){
    document.getElementById('expiryRemovalLotId').value=Number(data.lot_id||0);
    document.getElementById('expiryRemovalItem').textContent=data.item||'N/A';
    document.getElementById('expiryRemovalLot').textContent=data.lot||'N/A';
    document.getElementById('expiryRemovalDate').textContent=data.expiry||'N/A';
    document.getElementById('expiryRemovalLocation').textContent=data.location||'N/A';
    document.getElementById('expiryRemovalQty').textContent=Number(data.qty||0);
    const modal=document.getElementById('expiryRemovalModal');modal.classList.add('open');modal.setAttribute('aria-hidden','false');
}
function closeExpiryRemoval(){const modal=document.getElementById('expiryRemovalModal');modal.classList.remove('open');modal.setAttribute('aria-hidden','true');}
document.getElementById('expiryRemovalModal').addEventListener('click',function(event){if(event.target===this)closeExpiryRemoval();});
document.addEventListener('keydown',function(event){if(event.key==='Escape')closeExpiryRemoval();});
(function(){
    function buildExpiryPrintPages(){
        const source=document.querySelector('.expiry-monitoring-report:not(.report-print-page)');
        const sourceTable=source?.querySelector(':scope>.table-responsive>.table');
        if(!source||!sourceTable)return;
        document.querySelector('.report-print-pages')?.remove();
        const rows=Array.from(sourceTable.tBodies[0].rows);
        const groups=[];
        const recordsPerPage=32;
        for(let index=0;index<rows.length;index+=recordsPerPage)groups.push(rows.slice(index,index+recordsPerPage));
        if(!groups.length)groups.push([]);
        const pages=document.createElement('div');
        pages.className='report-print-pages';
        groups.forEach(group=>{
            const page=source.cloneNode(true);
            page.classList.add('report-print-page');
            page.querySelector('.toolbar')?.remove();
            const body=page.querySelector('tbody');
            body.innerHTML='';
            group.forEach(row=>body.appendChild(row.cloneNode(true)));
            pages.appendChild(page);
        });
        source.insertAdjacentElement('afterend',pages);
    }
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',buildExpiryPrintPages,{once:true});
    else buildExpiryPrintPages();
    window.addEventListener('beforeprint',buildExpiryPrintPages);
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
