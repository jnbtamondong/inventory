<?php
require_once __DIR__.'/../includes/bootstrap.php';
$active='ppmp-approved';
$rows=db_all('SELECT p.*,d.department_name,(SELECT COUNT(*) FROM procurement_ppmp_items i WHERE i.ppmp_id=p.id) item_count,(SELECT COALESCE(SUM(i.planned_quantity*i.unit_cost),0) FROM procurement_ppmp_items i WHERE i.ppmp_id=p.id) total_cost FROM procurement_ppmp p LEFT JOIN departments d ON d.id=p.department_id WHERE p.status="APPROVED" ORDER BY p.approved_at DESC,p.id DESC');
procurement_header('Approved PPMPs','ppmp-approved','Preview and print approved Project Procurement Management Plans.');
?>
<link rel="stylesheet" href="../assets/ppmp.css">
<section class="panel">
    <div class="panel-head"><h2>Approved PPMPs</h2><input class="search" id="search" placeholder="Search PPMP, year, fund source, or unit..."></div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>PPMP No.</th><th>Year</th><th>End-User or Implementing Unit</th><th>Fund Source</th><th>Items</th><th>Total Cost</th><th>Approved By</th><th>Actions</th></tr></thead>
        <tbody id="rows"><?php foreach($rows as$row):?><tr><td><strong><?=e($row['ppmp_no'])?></strong></td><td><?=e((string)$row['fiscal_year'])?></td><td><?=e($row['department_name']??'No department assigned')?></td><td><?=e($row['fund_source']??'')?></td><td><?=e((string)$row['item_count'])?></td><td>₱<?=number_format((float)$row['total_cost'],2)?></td><td><?=e($row['approved_by']??'')?></td><td><div class="actions ppmp-row-actions"><a class="btn light icon-only action-preview" target="_blank" rel="noopener" href="ppmp_print.php?id=<?=(int)$row['id']?>" data-tooltip="Preview and print" aria-label="Preview and print <?=e($row['ppmp_no'])?>"><svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></a></div></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="8" class="muted">No approved PPMPs are available for printing.</td></tr><?php endif;?></tbody>
    </table></div>
</section>
<script>document.getElementById('search').oninput=function(){const query=this.value.trim().toLowerCase();document.querySelectorAll('#rows tr').forEach(row=>row.hidden=!row.textContent.toLowerCase().includes(query))};</script>
<?php procurement_footer();
