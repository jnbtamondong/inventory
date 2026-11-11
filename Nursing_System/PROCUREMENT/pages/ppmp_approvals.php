<?php
require_once __DIR__.'/../includes/bootstrap.php';
if(!can_approve_ppmp()){http_response_code(403);exit('Access denied.');}
$active='ppmp-approval';
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf_token();
    $id=(int)($_POST['id']??0);$action=$_POST['action']??'approve';
    if($action==='return'){
        if(!(int)db_value('SELECT (SELECT COUNT(*) FROM procurement_ppmp_review_comments WHERE ppmp_id=? AND resolution_status<>"RESOLVED")+(SELECT COUNT(*) FROM procurement_ppmp_general_comments WHERE ppmp_id=? AND resolution_status<>"RESOLVED")',[$id,$id]))set_flash('danger','Add at least one item or general comment before returning the PPMP.');
        else{db()->beginTransaction();try{db_statement('DELETE FROM procurement_ppmp_revision_baselines WHERE ppmp_id=?',[$id]);db_statement('INSERT INTO procurement_ppmp_revision_baselines(ppmp_id,item_id,item_description,unit,planned_quantity,unit_cost) SELECT ppmp_id,item_id,item_description,unit,planned_quantity,unit_cost FROM procurement_ppmp_items WHERE ppmp_id=?',[$id]);db_statement('UPDATE procurement_ppmp_review_comments SET resolution_status="PENDING REVISION" WHERE ppmp_id=? AND resolution_status<>"RESOLVED"',[$id]);db_statement('UPDATE procurement_ppmp_general_comments SET resolution_status="PENDING REVISION" WHERE ppmp_id=? AND resolution_status<>"RESOLVED"',[$id]);$statement=db_statement('UPDATE procurement_ppmp SET status="FOR REVISION",revision_round=revision_round+1 WHERE id=? AND status="FOR APPROVAL"',[$id]);db()->commit();set_flash($statement->rowCount()?'success':'danger',$statement->rowCount()?'PPMP returned to the end-user for revision.':'This PPMP is no longer awaiting approval.');}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}}
    }else{
        if((int)db_value('SELECT COUNT(*) FROM procurement_ppmp_review_comments WHERE ppmp_id=? AND resolution_status<>"RESOLVED"',[$id])){set_flash('danger','This PPMP cannot be approved while item comments are pending revision or approval.');redirect('ppmp_approvals.php');}
        $statement=db_statement("UPDATE procurement_ppmp SET status='APPROVED',approved_by=?,approved_at=NOW() WHERE id=? AND status='FOR APPROVAL'",[current_user()['full_name'],$id]);
        set_flash($statement->rowCount()?'success':'danger',$statement->rowCount()?'PPMP approved and is now available for Purchase Requests.':'This PPMP is no longer awaiting approval.');
    }
    redirect('ppmp_approvals.php');
}
$rows=db_all('SELECT p.*,d.department_name,(SELECT COUNT(*) FROM procurement_ppmp_items i WHERE i.ppmp_id=p.id) item_count,((SELECT COUNT(*) FROM procurement_ppmp_review_comments c WHERE c.ppmp_id=p.id AND c.resolution_status<>"RESOLVED")+(SELECT COUNT(*) FROM procurement_ppmp_general_comments g WHERE g.ppmp_id=p.id AND g.resolution_status<>"RESOLVED")) comment_count,(SELECT COALESCE(SUM(i.planned_quantity*i.unit_cost),0) FROM procurement_ppmp_items i WHERE i.ppmp_id=p.id) total_cost FROM procurement_ppmp p LEFT JOIN departments d ON d.id=p.department_id WHERE p.status="FOR APPROVAL" ORDER BY p.created_at,p.id');
procurement_header('PPMP for Approval','ppmp-approval','Review and approve submitted Project Procurement Management Plans.');
?>
<style>
.panel>.table-wrap{border:1px solid #dce6ec;border-radius:8px;background:#fff}
.panel .table{table-layout:fixed;min-width:1000px}
.panel .table th,.panel .table td{text-align:center;vertical-align:middle}
.panel .table th{height:46px;padding:11px 14px;background:#f2f6f8;color:#3d5b6e;font-size:11px;font-weight:800;text-transform:uppercase}
.panel .table td{height:62px;padding:12px 14px;color:#29495d;font-size:13px}
.panel .table tbody tr{transition:background-color .15s ease}
.panel .table tbody tr:hover{background:#f8fbfc}
.panel .table th:nth-child(1){width:15%}
.panel .table th:nth-child(2){width:8%}
.panel .table th:nth-child(3){width:20%}
.panel .table th:nth-child(4){width:8%}
.panel .table th:nth-child(5){width:13%}
.panel .table th:nth-child(6){width:14%}
.panel .table th:nth-child(7){width:22%}
.panel .table td:first-child strong{color:#123f5a;font-weight:800}
.panel .table td:nth-child(2),.panel .table td:nth-child(4),.panel .table td:nth-child(5){font-variant-numeric:tabular-nums}
.panel .table td:last-child .actions{display:inline-flex;align-items:center;justify-content:center;flex-wrap:nowrap;gap:8px;width:auto}
.panel .table td:last-child form{display:inline-flex;margin:0}
.panel .table td:last-child .btn{height:36px;min-height:36px;border-radius:6px;padding:8px 14px;font-size:12px;line-height:1;box-shadow:none;transition:background-color .15s ease,border-color .15s ease,transform .15s ease}
.panel .table td:last-child .btn svg{display:block;flex:0 0 auto}
.panel .table td:last-child .btn.light{border:1px solid #cbdbe4;background:#fff;color:#174d6b}
.panel .table td:last-child a.btn.light{width:36px;min-width:36px;padding:8px}
.panel .table td:last-child .btn.light:hover{border-color:#7fa6ba;background:#f2f8fa}
.panel .table td:last-child .btn.red{width:36px;padding:8px;background:#fff1f0;color:#ad2922;border:1px solid #efc6c2}
.panel .table td:last-child .btn.red:hover{background:#ffe4e1;border-color:#d66a63}
.panel .table td:last-child .btn.green{width:36px;min-width:36px;padding:8px;background:#fff;border:1px solid #abd5c5;color:#087f5b}
.panel .table td:last-child .btn.green:hover{background:#e7f5ef;border-color:#b7ddcf;color:#066c4d}
.panel .table td:last-child .btn:hover{transform:translateY(-1px)}
.panel .table td:last-child .btn:focus-visible{outline:3px solid #4d9ec455;outline-offset:2px}
</style>
<section class="panel"><div class="panel-head"><h2>For Approval</h2><span class="badge"><?=count($rows)?> pending</span></div><div class="table-wrap"><table class="table"><thead><tr><th>PPMP No.</th><th>Year</th><th>End-User or Implementing Unit</th><th>Items</th><th>Total Cost</th><th>Submitted By</th><th>Actions</th></tr></thead><tbody><?php foreach($rows as$row):?><tr><td><strong><?=e($row['ppmp_no'])?></strong></td><td><?=e((string)$row['fiscal_year'])?></td><td><?=e($row['department_name']??'No department assigned')?></td><td><?=e((string)$row['item_count'])?></td><td>₱<?=number_format((float)$row['total_cost'],2)?></td><td><?=e($row['prepared_by'])?></td><td><div class="actions"><a class="btn light" target="_blank" rel="noopener" href="ppmp_print.php?id=<?=$row['id']?>">Preview</a><?php if((int)$row['comment_count']>0):?><form method="post" data-confirm="Return <?=e($row['ppmp_no'])?> to the end-user for revision?"><?=csrf_field()?><input type="hidden" name="action" value="return"><input type="hidden" name="id" value="<?=$row['id']?>"><button class="btn red" type="submit" title="Return" aria-label="Return"><svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 14-5-5 5-5"/><path d="M4 9h10a6 6 0 0 1 6 6v5"/></svg></button></form><?php endif;?><form method="post" data-confirm="Approve <?=e($row['ppmp_no'])?>?"><?=csrf_field()?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?=$row['id']?>"><button class="btn green" type="submit">Approve</button></form></div></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="7" class="muted">No PPMP is waiting for approval.</td></tr><?php endif;?></tbody></table></div></section>
<script>
document.querySelectorAll('.table .actions a[href^="ppmp_print.php"]').forEach(button=>{button.innerHTML='<svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';button.title='Preview';button.setAttribute('aria-label','Preview PPMP')});
document.querySelectorAll('.table .actions form').forEach(form=>{if(form.querySelector('input[name="action"]')?.value==='approve'){const button=form.querySelector('button');if(!button)return;button.innerHTML='<svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>';button.title='Approve';button.setAttribute('aria-label','Approve PPMP')}});
</script>
<?php procurement_footer(); ?>
