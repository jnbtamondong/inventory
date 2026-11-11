<?php
require_once __DIR__.'/../includes/bootstrap.php';
if(!can_review_procurement_item_requests()){http_response_code(403);exit('Access denied.');}

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf_token();
    $id=(int)($_POST['id']??0);
    $action=(string)($_POST['action']??'');
    try{
        $request=db_one('SELECT * FROM procurement_item_requests WHERE id=? AND status="PENDING"',[$id]);
        if(!$request)throw new RuntimeException('This item request is no longer pending.');
        if($action==='approve'){
            $description=trim((string)($_POST['item_description']??''));
            $unit=trim((string)($_POST['unit']??''));
            $category=trim((string)($_POST['category']??''));
            if($description===''||$unit===''||$category==='')throw new RuntimeException('Description, unit, and category are required.');
            if(db_one('SELECT id,item_code FROM items WHERE UPPER(TRIM(item_description))=UPPER(TRIM(?)) AND status="Active" LIMIT 1',[$description]))throw new RuntimeException('An active masterlist item already has this description. Reject this duplicate request instead.');
            db()->beginTransaction();
            $codes=db_all('SELECT item_code FROM items WHERE item_code LIKE "CSSR-%" FOR UPDATE');
            $highest=0;foreach($codes as$code){if(preg_match('/^CSSR-(\d+)$/',(string)$code['item_code'],$match))$highest=max($highest,(int)$match[1]);}
            do{$itemCode='CSSR-'.str_pad((string)++$highest,6,'0',STR_PAD_LEFT);}while(db_one('SELECT id FROM items WHERE item_code=?',[$itemCode]));
            db_statement('INSERT INTO items(item_code,item_description,category,unit,minimum_stock,current_stock,status) VALUES(?,?,?,?,0,0,"Active")',[$itemCode,$description,$category,$unit]);
            $itemId=(int)db()->lastInsertId();
            db_statement('UPDATE procurement_item_requests SET item_description=?,unit=?,category=?,status="APPROVED",approved_item_id=?,reviewed_by=?,review_remarks=?,reviewed_at=NOW() WHERE id=?',[$description,$unit,$category,$itemId,(int)current_user()['id'],trim((string)($_POST['review_remarks']??'')),$id]);
            db_statement('UPDATE procurement_ppmp_items SET item_id=?,unit=?,item_description=? WHERE item_request_id=? AND item_id IS NULL',[$itemId,$unit,$description,$id]);
            db()->commit();
            set_flash('success',$description.' was approved and added to the Item Masterlist as '.$itemCode.'.');
        }elseif($action==='reject'){
            $remarks=trim((string)($_POST['review_remarks']??''));
            if($remarks==='')throw new RuntimeException('Enter a reason for rejecting the item request.');
            db_statement('UPDATE procurement_item_requests SET status="REJECTED",reviewed_by=?,review_remarks=?,reviewed_at=NOW() WHERE id=?',[(int)current_user()['id'],$remarks,$id]);
            set_flash('success','The item request was rejected.');
        }else throw new RuntimeException('Invalid review action.');
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();set_flash('danger',safe_error_message($e));}
    header('Location: item_requests.php');exit;
}

$status=strtoupper(trim((string)($_GET['status']??'PENDING')));
if(!in_array($status,['PENDING','APPROVED','REJECTED','ALL'],true))$status='PENDING';
$params=[];$where='';if($status!=='ALL'){$where='WHERE r.status=?';$params[]=$status;}
$rows=db_all("SELECT r.*,u.full_name requested_by_name,d.department_name,i.item_code FROM procurement_item_requests r LEFT JOIN users u ON u.id=r.requested_by LEFT JOIN departments d ON d.id=r.department_id LEFT JOIN items i ON i.id=r.approved_item_id $where ORDER BY CASE r.status WHEN 'PENDING' THEN 0 ELSE 1 END,r.created_at DESC",$params);
procurement_header('Item Requests','item-requests','Review proposed items before adding them to the Item Masterlist.');
?>
<section class="panel">
<div class="panel-head"><div><h2>Item Registration Requests</h2><small class="muted">Approve valid items or reject duplicates and incomplete requests.</small></div><form method="get"><select class="search" name="status" onchange="this.form.submit()"><option value="PENDING" <?=$status==='PENDING'?'selected':''?>>Pending</option><option value="APPROVED" <?=$status==='APPROVED'?'selected':''?>>Approved</option><option value="REJECTED" <?=$status==='REJECTED'?'selected':''?>>Rejected</option><option value="ALL" <?=$status==='ALL'?'selected':''?>>All Requests</option></select></form></div>
<div class="table-wrap"><table class="table item-request-table"><thead><tr><th>Item Name</th><th>Unit</th><th>Category</th><th>Qty</th><th>Estimated Cost</th><th>Requested By</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php foreach($rows as$row):?><tr><td><strong><?=e($row['item_description'])?></strong><small><?=e($row['justification'])?></small><?php if($row['item_code']):?><small>Masterlist: <?=e($row['item_code'])?></small><?php endif;?></td><td><?=e($row['unit'])?></td><td><?=e($row['category'])?></td><td><?=number_format((int)$row['requested_quantity'])?></td><td>₱<?=number_format((float)$row['estimated_unit_cost'],2)?></td><td><?=e($row['requested_by_name']??'Unknown')?><small><?=e($row['department_name']??'No department')?></small></td><td><span class="badge <?=strtolower($row['status'])?>"><?=e($row['status'])?></span></td><td><?php if($row['status']==='PENDING'):?><button class="btn light" type="button" data-review-request="<?=$row['id']?>">Review</button><?php else:?><span class="muted"><?=e($row['review_remarks']?:'Reviewed')?></span><?php endif;?></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="8" class="empty-request">No <?=strtolower($status==='ALL'?'item':$status)?> requests found.</td></tr><?php endif;?></tbody></table></div></section>
<?php foreach($rows as$row):if($row['status']!=='PENDING')continue;?><dialog class="item-review-dialog" id="itemRequest<?=$row['id']?>"><form method="post" data-confirm-submit="Approve this item and add it to the Item Masterlist?"><?=csrf_field()?><input type="hidden" name="id" value="<?=$row['id']?>"><header><div><strong>Review New Item Request</strong><small>Requested Item Not in Item Masterlist</small></div><button class="btn light" type="button" data-close-review>Close</button></header><div class="item-review-body"><div class="requester-line">Requested by <?=e($row['requested_by_name']??'Unknown')?> · <?=e($row['department_name']??'No department')?></div><div class="grid"><label class="wide">Item Name<input name="item_description" value="<?=e($row['item_description'])?>" required></label><label>Unit<input name="unit" value="<?=e($row['unit'])?>" required></label><label>Category<select name="category" required><option value="MEDICAL SUPPLIES" <?=$row['category']==='MEDICAL SUPPLIES'?'selected':''?>>Medical Supplies</option><option value="MEDICAL INSTRUMENTS" <?=$row['category']==='MEDICAL INSTRUMENTS'?'selected':''?>>Medical Instruments</option><option value="OTHER" <?=$row['category']==='OTHER'?'selected':''?>>Other</option></select></label><label>Requested Quantity<input value="<?=number_format((int)$row['requested_quantity'])?>" readonly></label><label>Estimated Unit Cost<input value="<?=number_format((float)$row['estimated_unit_cost'],2)?>" readonly></label><label class="wide">Item Description<textarea rows="3" readonly><?=e($row['justification'])?></textarea></label><label class="wide">Review Remarks<textarea name="review_remarks" rows="3" placeholder="Required when rejecting"></textarea></label></div></div><footer><button class="btn red" type="submit" name="action" value="reject" data-reject-request>Reject</button><button class="btn green" type="submit" name="action" value="approve">Approve &amp; Add to Masterlist</button></footer></form></dialog><?php endforeach;?>
<style>.item-request-table td>small{display:block;margin-top:4px}.item-request-table th,.item-request-table td{text-align:center}.item-request-table th:first-child,.item-request-table td:first-child{text-align:left}.empty-request{text-align:center!important;padding:30px!important;color:#738696}.badge.pending{background:#fff0cc;color:#8b5d00}.badge.rejected{background:#ffe2e2;color:#a92121}.item-review-dialog{width:min(720px,94vw);padding:0;border:0;border-radius:8px;box-shadow:0 25px 80px #102b3b66}.item-review-dialog::backdrop{background:#142a397d}.item-review-dialog header,.item-review-dialog footer{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:14px 17px;background:#f0f7fa;border-bottom:1px solid #d8e5ec}.item-review-dialog header strong,.item-review-dialog header small{display:block}.item-review-dialog header small{margin-top:3px;color:#647b8a}.item-review-dialog footer{justify-content:flex-end;border-top:1px solid #d8e5ec;border-bottom:0;background:#fff}.item-review-body{padding:18px}.requester-line{margin-bottom:14px;padding:9px 11px;border-left:3px solid #0b5278;background:#edf4f7;color:#496578;font-size:12px;font-weight:700}.item-review-body input[readonly],.item-review-body textarea[readonly]{background:#edf3f6}</style>
<script>document.querySelectorAll('[data-review-request]').forEach(button=>button.onclick=()=>document.getElementById('itemRequest'+button.dataset.reviewRequest).showModal());document.querySelectorAll('.item-review-dialog').forEach(dialog=>{dialog.querySelector('[data-close-review]').onclick=()=>dialog.close();dialog.addEventListener('click',event=>{if(event.target===dialog)dialog.close()});dialog.querySelector('[data-reject-request]').addEventListener('click',event=>{const remarks=dialog.querySelector('[name="review_remarks"]');if(!remarks.value.trim()){event.preventDefault();remarks.setCustomValidity('Enter a reason for rejecting this request.');remarks.reportValidity();remarks.focus()}else remarks.setCustomValidity('')})});</script>
<?php procurement_footer(); ?>
