<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function e($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function db_value($query, $args = []) { return 2; }
function current_user() { return ['id' => 1]; }
function can_approve_ppmp() { return true; }
function can_review_procurement_item_requests() { return true; }
$active = 'ppmp';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Procurement Preview</title><link rel="stylesheet" href="../assets/procurement.css"><link rel="stylesheet" href="../assets/ppmp.css"></head>
<body class="module-ui proc-app"><div class="proc-shell">
<?php include __DIR__ . '/../PROCUREMENT/includes/sidebar.php'; ?>
<main id="procWorkspace" tabindex="-1"><header class="proc-top"><div><strong>Procurement Masterlist</strong><small>Project Procurement Management Plans</small></div><div class="proc-user">Preview User</div></header>
<section class="metric-row"><div class="metric"><strong>12</strong><small>Plans</small></div><div class="metric"><strong>4</strong><small>For approval</small></div><div class="metric"><strong>8</strong><small>Approved</small></div><div class="metric"><strong>2</strong><small>Pending deliveries</small></div></section>
<section class="panel"><div class="toolbar"><button type="button" class="btn blue" id="openProcPreview"><i class="bi bi-plus-lg" aria-hidden="true"></i> Create Plan</button><input class="search" aria-label="Search plans" placeholder="Search plans"></div><div class="table-wrap"><table class="table"><thead><tr><th>Plan number</th><th>Department</th><th>Year</th><th>Status</th></tr></thead><tbody><tr><td>PPMP-2026-001</td><td>ICU</td><td>2026</td><td><span class="badge approved">Approved</span></td></tr></tbody></table></div></section>
<dialog id="procPreview" aria-labelledby="procPreviewTitle"><h2 id="procPreviewTitle">Create Procurement Plan</h2><form method="dialog"><div class="grid"><label>Department<select><option>ICU</option><option>NSO</option></select></label><label>Fiscal year<input type="number" value="2026"></label></div><div class="form-actions"><button type="button" class="btn light" id="closeProcPreview">Cancel</button><button type="submit" class="btn blue">Continue</button></div></form></dialog>
<button type="button" id="openPrForm" class="btn light">Create Purchase Request</button><dialog id="prPreview"><h2>Create Purchase Request</h2><button type="button" class="btn light" id="closePrPreview">Close</button></dialog>
<?php
// Real PPMP form markup, rendered without its controller or database queries.
function csrf_field(){return '<input type="hidden" name="csrf_token" value="synthetic">';}
$canSelectDepartment=true;$departments=[['id'=>1,'department_name'=>'CSSR']];$userDepartmentId=1;$userDepartment=$departments[0];
$projectTypes=['Goods'];$procurementModes=['Public Bidding'];$catalog=[];
$source=file_get_contents(__DIR__.'/../PROCUREMENT/pages/ppmp.php');$start=strpos($source,'<dialog id="ppmpDialog"');
$form=substr($source,$start,strpos($source,'</dialog>',$start)+9-$start);
eval('?>'.$form);
?>
</main></div><script>const dialog=document.getElementById('procPreview');document.getElementById('openProcPreview').onclick=()=>dialog.showModal();document.getElementById('closeProcPreview').onclick=()=>dialog.close();document.getElementById('openPrForm').onclick=()=>document.getElementById('prPreview').showModal();document.getElementById('closePrPreview').onclick=()=>document.getElementById('prPreview').close();</script></body></html>
