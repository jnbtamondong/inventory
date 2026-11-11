<?php
function cssrRenderRequisitions(array $data, array $ui, array $options): void
{
    $e='cssrUiEscape'; $f=$data['filters']; $preview=!empty($options['preview']); $manage=!empty($options['manage']);
    $url=fn(array $change=[])=>'requisitions.php?'.http_build_query(array_merge($f,['page'=>$data['page']],$change));
    $ui['styles'][]='../assets/css/requisitions.css?v='.filemtime(__DIR__.'/../assets/css/requisitions.css');
    $ui['scripts'][]='../assets/js/requisitions.js?v='.filemtime(__DIR__.'/../assets/js/requisitions.js');
    cssrUiStart($ui);
    ?>
<div class="cu-page-heading"><div><p class="cu-eyebrow">Supply operations</p><h1>Supply Requests</h1><p class="cu-lead cu-muted">Review department requests, approve quantities, and coordinate supply release.</p></div>
<?php if (!empty($options['create'])): ?><a class="cu-btn" href="<?=$preview?'../pages/':''?>item_requests.php"><?=cssrUiIcon('plus')?>Create Supply Request</a><?php endif; ?></div>
<div class="cu-stack">
<?php if ($preview): ?><div class="cu-alert">Design preview · Sample requests only. Approval and release demonstrations do not change hospital records.</div><?php endif; ?>
<?php if (!empty($options['error'])): ?><div class="cu-alert rq-error" role="alert"><?=$e($options['error'])?></div><?php endif; ?>
<?php if (!empty($options['notice'])): ?><div class="cu-alert cu-alert-success" role="status" data-rq-notice><?=$e($options['notice'])?></div><?php endif; ?>
<?php if (!empty($_GET['view']) && !$data['selected']): ?><div class="cu-alert" role="alert">The requisition was not found or is outside your department access.</div><?php endif; ?>
<section class="cu-card" aria-label="Requisition list">
<nav class="rq-tabs" aria-label="Filter requisitions by status"><?php foreach ($data['statuses'] as $status): ?>
<a href="<?=$e($url(['status'=>$status,'page'=>1]))?>" <?=$f['status']===$status?'aria-current="page"':''?>><?=$e($status)?><span><?=$data['counts'][$status]?></span></a>
<?php endforeach; ?></nav>
<form class="rq-filters" action="requisitions.php" role="search" aria-label="Filter requisitions">
<div class="cu-field"><label for="rq-search">Request Number</label><input class="cu-input" id="rq-search" name="q" type="search" placeholder="Search request number" value="<?=$e($f['q'])?>"></div>
<div class="cu-field"><label for="rq-department">Department</label><select class="cu-input" id="rq-department" name="department"><option value="0">All departments</option><?php foreach ($data['departments'] as $department): ?><option value="<?=(int)$department['id']?>" <?=$f['department']==$department['id']?'selected':''?>><?=$e($department['department_name'])?></option><?php endforeach; ?></select></div>
<div class="cu-field"><label for="rq-date">Date requested</label><input class="cu-input" id="rq-date" name="date" type="date" value="<?=$e($f['date'])?>"></div>
<div class="cu-field"><label for="rq-status">Status</label><select class="cu-input" id="rq-status" name="status"><?php foreach ($data['statuses'] as $status): ?><option <?=$f['status']===$status?'selected':''?>><?=$e($status)?></option><?php endforeach; ?></select></div>
<div class="cu-actions"><button class="cu-btn" type="submit">Apply filters</button><a class="cu-btn cu-btn-ghost" href="requisitions.php">Reset</a></div></form>
<div class="rq-list-heading"><div><h2>Department requests</h2><p class="cu-muted">Approved includes requests ready for release. Select a request to review its details.</p></div><span class="cu-badge"><?=$data['total']?> requests</span></div>
<div class="cu-table-wrap" tabindex="0" role="region" aria-label="Requisitions table, scroll horizontally on small screens"><table class="cu-table rq-table"><thead><tr><th scope="col">Request No.</th><th scope="col">Department</th><th scope="col">Date Requested</th><th scope="col">Requested By</th><th scope="col" class="cu-center">Number of Items</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead><tbody>
<?php foreach ($data['rows'] as $row): ?><tr data-rq-row="<?=(int)$row['id']?>"><td><a class="rq-number" href="<?=$e($url(['view'=>$row['id']]))?>"><?=$e($row['request_no'])?></a></td><td><?=$e($row['department_name'])?></td><td><time datetime="<?=$e($row['request_date'])?>"><?=$e(date('M d, Y',strtotime($row['request_date'])))?></time></td><td><?=$e($row['requested_by'])?></td><td class="cu-center"><?=(int)$row['item_count']?></td><td><span class="cu-badge cu-badge-<?=cssrRequisitionBadge($row['display_status'])?>"><?=$e($row['display_status'])?></span></td><td><a class="cu-btn cu-btn-secondary cu-btn-sm" aria-label="View <?=$e($row['request_no'])?>" href="<?=$e($url(['view'=>$row['id']]))?>">View <?=cssrUiIcon('chevron')?></a></td></tr><?php endforeach; ?>
<?php if (!$data['rows']): ?><tr><td colspan="7"><div class="cu-empty"><span class="cu-icon-tile"><?=cssrUiIcon('request')?></span><h3>No requisitions found</h3><p>Try another request number or adjust your filters.</p><a href="requisitions.php" class="cu-btn cu-btn-secondary">Clear filters</a></div></td></tr><?php endif; ?>
</tbody></table></div>
<footer class="cu-table-foot"><span><?=$data['total']?($data['page']-1)*10+1:0?>–<?=min($data['page']*10,$data['total'])?> of <?=$data['total']?> requests</span><nav class="cu-actions" aria-label="Requisition pages"><?php if ($data['page']>1): ?><a class="cu-btn cu-btn-secondary cu-btn-sm" href="<?=$e($url(['page'=>$data['page']-1]))?>">Previous</a><?php endif; ?><span>Page <?=$data['page']?> of <?=$data['pages']?></span><?php if ($data['page']<$data['pages']): ?><a class="cu-btn cu-btn-secondary cu-btn-sm" href="<?=$e($url(['page'=>$data['page']+1]))?>">Next</a><?php endif; ?></nav></footer>
</section></div>
<?php if ($request=$data['selected']): $editable=$manage&&$request['status']==='Pending'; $state=$options['form_state']??[]; ?>
<dialog class="cu-dialog rq-detail" id="rq-detail" aria-labelledby="rq-title" data-request-id="<?=(int)$request['id']?>" data-preview="<?=$preview?'true':'false'?>">
<header class="cu-dialog-head"><div><p class="cu-eyebrow">Requisition details</p><h2 id="rq-title"><?=$e($request['request_no'])?></h2></div><button class="cu-icon-button" type="button" data-cu-dismiss aria-label="Close requisition details" autofocus><?=cssrUiIcon('close')?></button></header>
<div class="rq-detail-body">
<?php nursingRenderWorkflow('requisition', (string)$request['display_status']); ?>
<div class="rq-detail-status"><span class="cu-badge cu-badge-<?=cssrRequisitionBadge($request['display_status'])?>"><?=$e($request['display_status'])?></span><span class="cu-muted"><?=(int)$request['item_count']?> requested items</span></div>
<?php if (!empty($options['error'])): ?><p class="cu-alert rq-error" role="alert"><?=$e($options['error'])?></p><?php endif; ?>
<dl class="rq-metadata"><?php foreach (['request number'=>$request['request_no'],'Department'=>$request['department_name'],'Requested by'=>$request['requested_by'],'Request date'=>date('M d, Y',strtotime($request['request_date']))] as $label=>$value): ?><div><dt><?=$e($label)?></dt><dd><?=$e($value)?></dd></div><?php endforeach; ?></dl>
<?php if (trim($request['purpose']??'')!==''): ?><p class="rq-purpose"><strong>Purpose</strong><br><?=$e($request['purpose'])?></p><?php endif; ?>
<div class="rq-items-heading"><h3>Requested items</h3><p class="cu-muted">Available stock excludes active reservations and expired lots.<?php if ($editable): ?> Select the earliest-expiring available lot for each approved item.<?php endif; ?></p></div>
<form method="post" action="<?=$preview?'../pages/':''?>item_requests.php" id="rq-action-form">
<input type="hidden" name="csrf_token" value="<?=$e($options['csrf']??'')?>"><input type="hidden" name="request_id" value="<?=(int)$request['id']?>"><input type="hidden" name="return_to" value="requisitions"><input type="hidden" name="action" id="rq-action"><input type="hidden" name="approval_mode" id="rq-approval-mode">
<div class="cu-table-wrap" tabindex="0" role="region" aria-label="Requested item quantities"><table class="cu-table rq-item-table"><thead><tr><th scope="col">Item</th><th scope="col" class="cu-center">Requested</th><th scope="col" class="cu-center">Available stock</th><th scope="col" class="cu-center">Approved</th><th scope="col" class="cu-center">Released</th><th scope="col">Remarks</th></tr></thead><tbody>
<?php foreach ($data['details'] as $line): $id=(int)$line['id']; $lotId=$state['approved_lot_id'][$id]??($line['lots'][0]['id']??0); $qty=$state['approved_qty'][$id]??min((int)$line['quantity_requested'],(int)($line['lots'][0]['free_stock']??0)); ?>
<tr><td><strong><?=$e($line['item_name'])?></strong><small><?=$e($line['item_code'])?> · <?=$e($line['unit'])?></small>
<?php if ($editable): ?><label class="cu-sr" for="rq-lot-<?=$id?>">Lot for <?=$e($line['item_name'])?></label><select class="cu-input rq-lot" name="approved_lot_id[<?=$id?>]" id="rq-lot-<?=$id?>"><option value="0">No lot selected</option><?php foreach ($line['lots'] as $lot): ?><option value="<?=(int)$lot['id']?>" data-stock="<?=(int)$lot['free_stock']?>" <?=$lotId==$lot['id']?'selected':''?>><?=$e($lot['lot_number'])?> · <?=(int)$lot['free_stock']?> free · <?=$e($lot['expiry_date']?:'No expiry')?></option><?php endforeach; ?></select><?php endif; ?></td>
<td class="cu-center" data-label="Requested"><?=(int)$line['quantity_requested']?></td><td class="cu-center" data-label="Available stock"><?=number_format((int)$line['available_stock'])?></td><td class="cu-center" data-label="Approved"><?php if ($editable): ?><input class="cu-input rq-quantity" aria-label="Approved quantity for <?=$e($line['item_name'])?>" type="number" name="approved_qty[<?=$id?>]" value="<?=(int)$qty?>" min="0" max="<?=(int)$line['quantity_requested']?>" step="1" required data-requested="<?=(int)$line['quantity_requested']?>" data-lot="rq-lot-<?=$id?>"><span class="rq-print-value"><?=(int)$line['quantity_approved']?></span><?php else: ?><?=(int)$line['quantity_approved']?><?php endif; ?></td><td class="cu-center" data-label="Released"><?=(int)$line['quantity_released']?></td><td data-label="Remarks"><?=$e($line['remarks']?:'—')?></td></tr>
<?php endforeach; ?></tbody></table></div>
<div class="cu-field rq-remarks"><label for="rq-remarks">Remarks<?=$manage&&in_array($request['status'],['Pending','Approved'],true)?' / reason for rejection':''?></label><textarea id="rq-remarks" class="cu-input" name="status_remarks" maxlength="1000" <?=$manage&&in_array($request['status'],['Pending','Approved'],true)?'':'readonly'?>><?=$e($state['status_remarks']??$request['remarks'])?></textarea><div class="rq-print-value"><?=$e($request['remarks'])?></div></div>
<p class="cu-alert rq-error" id="rq-action-error" role="alert" hidden></p>
<?php if ($manage && $request['status']==='Approved' && $request['display_status']!=='For Release'): ?><p class="cu-alert">This approval needs stock reservation review before release. <a href="<?=$preview?'../pages/':''?>item_requests.php?view=<?=(int)$request['id']?>">Open request workflow</a></p><?php endif; ?>
</form></div>
<footer class="cu-dialog-foot rq-detail-foot"><button class="cu-btn cu-btn-secondary" type="button" id="rq-print"><?=cssrUiIcon('document')?>Print</button><div class="cu-actions">
<?php if ($editable): ?><button class="cu-btn cu-btn-secondary" type="button" data-rq-action="partial">Partial Approve</button><button class="cu-btn" type="button" data-rq-action="approve">Approve</button><?php endif; ?>
<?php if ($manage && in_array($request['status'],['Pending','Approved'],true)): ?><button class="cu-btn cu-btn-danger" type="button" data-rq-action="reject">Reject</button><?php endif; ?>
<?php if ($manage && $request['display_status']==='For Release'): ?><button class="cu-btn" type="button" data-rq-action="release"><?=cssrUiIcon('issue')?>Release</button><?php endif; ?>
</div></footer></dialog>
<dialog class="cu-dialog" id="rq-confirm" aria-labelledby="rq-confirm-title" aria-describedby="rq-confirm-message"><header class="cu-dialog-head"><h2 id="rq-confirm-title">Confirm action</h2><button class="cu-icon-button" type="button" data-cu-dismiss aria-label="Close confirmation"><?=cssrUiIcon('close')?></button></header><div class="cu-card-body"><p id="rq-confirm-message"></p></div><footer class="cu-dialog-foot"><button class="cu-btn cu-btn-secondary" type="button" data-cu-dismiss autofocus>Cancel</button><button class="cu-btn" type="button" id="rq-confirm-submit">Confirm</button></footer></dialog>
<?php endif; cssrUiEnd();
}
