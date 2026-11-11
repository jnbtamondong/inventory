<?php
// Shared by the authenticated dashboard and its synthetic design preview.
function cssrRenderDashboard(array $data, array $ui, array $links, bool $preview = false): void
{
    $ui['styles'][] = '../assets/css/dashboard.css?v=' . filemtime(__DIR__ . '/../assets/css/dashboard.css');
    $ui['scripts'][] = '../assets/js/dashboard.js?v=' . filemtime(__DIR__ . '/../assets/js/dashboard.js');
    cssrUiStart($ui);
    $format = static fn($value): string => $value === null ? '—' : number_format((int) $value);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $data['date']);
    $total = (int) $data['counts']['total'];
    $monthlyTotal = array_sum($data['monthly']);
    $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
?>
<div class="cu-stack cu-dashboard" data-dashboard>
    <div class="cu-page-heading cu-dashboard-heading">
        <div><p class="cu-eyebrow">CSSR Inventory Management</p><h1>Dashboard</h1><p class="cu-lead cu-muted">Stock visibility and supply activity, in one place.</p></div>
        <div class="cu-dashboard-meta"><strong><?=cssrUiEscape($date ? $date->format('l, F j, Y') : $data['date'])?></strong><span><?=cssrUiEscape($data['scope_label'])?> · Philippine time</span></div>
    </div>
    <?php if ($preview): ?><div class="cu-alert cu-dashboard-sample"><?=cssrUiIcon('box')?><div><strong>Dashboard design preview</strong>Synthetic data for design review. <a href="../pages/dashboard.php">Open the live dashboard</a> or <a href="./">view the design foundation</a>.</div></div><?php endif; ?>
    <section class="cu-stats cu-dashboard-kpis" aria-label="Inventory summary">
        <?php foreach ([
            ['total','Total Inventory Items','box','blue','Active item records'],
            ['available','Available Items','check','green','At or above reorder level'],
            ['low','Low Stock Items','warning','yellow','Below reorder level'],
            ['out','Out of Stock Items','box','red','No stock on hand'],
        ] as [$key,$label,$icon,$tone,$help]): ?>
        <article class="cu-card cu-stat"><div class="cu-stat-top"><span><?=cssrUiEscape($label)?></span><span class="cu-icon-tile cu-tone-<?=$tone?>"><?=cssrUiIcon($icon)?></span></div><strong class="cu-stat-value" data-kpi="<?=$key?>"><?=$format($data['counts'][$key])?></strong><small><?=$data['inventory_visible'] ? cssrUiEscape($help) : 'Inventory access required'?></small></article>
        <?php endforeach; ?>
    </section>
    <section class="cu-dashboard-charts" aria-label="Inventory charts">
        <article class="cu-card" aria-labelledby="issuance-heading"><div class="cu-card-head"><div><h2 id="issuance-heading">Monthly Issuance</h2><p>Total units issued each month</p></div><form class="cu-chart-filter" method="get" data-dashboard-year><label for="issuance-year">Year</label><select class="cu-input" id="issuance-year" name="year"><?php foreach ($data['years'] as $year): ?><option value="<?=(int)$year?>" <?=$year === $data['year'] ? 'selected' : ''?>><?=(int)$year?></option><?php endforeach; ?></select><button type="submit" class="cu-btn cu-btn-secondary cu-btn-sm">Apply</button></form></div>
            <div class="cu-card-body">
                <?php if ($data['inventory_visible']): ?>
                <div class="cu-chart-summary"><strong><?=number_format($monthlyTotal)?></strong><span>units issued in <?=(int)$data['year']?></span></div>
                <div class="cu-chart-frame"><div id="cu-issuance-chart" role="img" aria-label="Monthly issuance for <?=(int)$data['year']?>: <?=number_format($monthlyTotal)?> units. Exact monthly values are in the table below.">Monthly issuance values appear in the data table below.</div></div>
                <?php if ($monthlyTotal === 0): ?><p class="cu-dashboard-note">No issuance recorded for this year.</p><?php endif; ?>
                <details class="cu-chart-data"><summary>View monthly figures</summary><div class="cu-table-wrap" tabindex="0" role="region" aria-label="Monthly issuance values"><table class="cu-table"><caption class="cu-sr">Units issued in <?=(int)$data['year']?></caption><thead><tr><th scope="col">Month</th><th scope="col" class="cu-number">Units issued</th></tr></thead><tbody><?php foreach ($months as $index=>$month): ?><tr><th scope="row"><?=$month?></th><td class="cu-number"><?=number_format($data['monthly'][$index])?></td></tr><?php endforeach; ?></tbody></table></div></details>
                <?php else: ?><div class="cu-empty"><h3>Inventory access required</h3><p>Issuance figures are unavailable for your role.</p></div><?php endif; ?>
            </div>
        </article>
        <article class="cu-card" aria-labelledby="stock-heading"><div class="cu-card-head"><div><h2 id="stock-heading">Stock Status</h2><p>Current active inventory items</p></div><span class="cu-icon-tile"><?=cssrUiIcon('box')?></span></div><div class="cu-card-body">
            <?php if ($data['inventory_visible']): ?>
            <div class="cu-donut"><div id="cu-stock-chart" role="img" aria-label="<?=$format($data['counts']['available'])?> available, <?=$format($data['counts']['low'])?> low stock, <?=$format($data['counts']['out'])?> out of stock.">Stock status totals are listed below.</div><div class="cu-donut-center"><strong><?=number_format($total)?></strong><span>Total items</span></div></div>
            <div class="cu-stock-legend"><?php foreach (['available'=>'Available','low'=>'Low Stock','out'=>'Out of Stock'] as $key=>$label): ?><div><span class="cu-badge cu-badge-<?=$key === 'available' ? 'success' : ($key === 'low' ? 'warning' : 'danger')?>"><?=$label?></span><strong><?=$format($data['counts'][$key])?></strong><small><?=$total ? round($data['counts'][$key] / $total * 100) : 0?>%</small></div><?php endforeach; ?></div>
            <?php if (!$total): ?><p class="cu-dashboard-note">No active inventory items yet.</p><?php endif; ?>
            <?php else: ?><div class="cu-empty"><h3>Inventory access required</h3><p>Stock distribution is unavailable for your role.</p></div><?php endif; ?>
        </div></article>
    </section>
    <section class="cu-card" aria-labelledby="activity-heading"><div class="cu-card-head"><div><h2 id="activity-heading">Today's Activity</h2><p>Requests and returns: <?=cssrUiEscape($data['scope_label'])?>. Inbound deliveries: CSSR.</p></div><span class="cu-badge cu-badge-info"><?=cssrUiEscape($date ? $date->format('M j') : 'Today')?></span></div><div class="cu-activity-grid">
        <?php foreach ([
            ['requests','Department Requests','request','Requested today'],
            ['pending','Pending Approval','clock','Today’s requests awaiting approval'],
            ['released','Released Requests','issue','Released today'],
            ['returns','Items Returned','return','Units received back today'],
            ['inbound','Inbound Deliveries','inbound','Deliveries received today'],
        ] as [$key,$label,$icon,$help]): ?>
        <div class="cu-activity"><div class="cu-activity-top"><?=cssrUiIcon($icon)?><span><?=$label?></span></div><strong><?=$format($data['activity'][$key])?></strong><?php if ($key === 'released' && $data['activity'][$key] !== null): ?><span class="cu-badge cu-badge-success">Released</span><?php endif; ?><small><?=$data['activity'][$key] === null ? 'Access required' : $help?></small></div>
        <?php endforeach; ?>
    </div></section>
    <div class="cu-dashboard-tables">
        <section class="cu-card" aria-labelledby="pending-heading"><div class="cu-card-head"><div><h2 id="pending-heading">Pending Requisitions</h2><p>Awaiting approval or release · Oldest first</p></div><?php if (!empty($links['requests'])): ?><a class="cu-btn cu-btn-ghost cu-btn-sm" href="<?=cssrUiEscape($links['requests'])?>">View all <?=cssrUiIcon('chevron')?></a><?php endif; ?></div>
            <div class="cu-table-wrap" tabindex="0" role="region" aria-label="Pending requisitions; scroll for more columns"><table class="cu-table"><caption class="cu-sr">Pending requisitions for <?=cssrUiEscape($data['scope_label'])?></caption><thead><tr><th scope="col">TRN No.</th><th scope="col">Department</th><th scope="col">Date Requested</th><th scope="col" class="cu-center">Number of Items</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead><tbody>
            <?php foreach ($data['pending'] as $request): $requestDate = DateTimeImmutable::createFromFormat('!Y-m-d', $request['request_date']); $forApproval = $request['status'] === 'Pending'; ?>
            <tr><td><strong><?=cssrUiEscape($request['request_no'])?></strong></td><td><?=cssrUiEscape($request['department_name'])?></td><td><?=cssrUiEscape($requestDate ? $requestDate->format('M j, Y') : $request['request_date'])?></td><td class="cu-center"><?=number_format((int)$request['item_count'])?></td><td><span class="cu-badge cu-badge-<?=$forApproval ? 'warning' : 'info'?>"><?=$forApproval ? 'For Approval' : 'For Release'?></span></td><td><?php if ($preview): ?><button type="button" class="cu-btn cu-btn-ghost cu-btn-sm" data-cu-dialog="request-preview-<?=(int)$request['id']?>" aria-label="View <?=cssrUiEscape($request['request_no'])?>">View</button><?php elseif (!empty($links['request_detail'])): ?><a class="cu-btn cu-btn-ghost cu-btn-sm" href="<?=cssrUiEscape($links['request_detail'] . (int)$request['id'])?>#requestModal" aria-label="View <?=cssrUiEscape($request['request_no'])?>">View</a><?php else: ?><span class="cu-muted">—</span><?php endif; ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$data['pending']): ?><tr><td colspan="6"><div class="cu-empty cu-dashboard-empty"><span class="cu-icon-tile"><?=cssrUiIcon('check')?></span><h3><?=$data['requests_visible'] ? 'No pending requisitions' : 'Request access required'?></h3><p><?=$data['requests_visible'] ? 'Requests awaiting approval or release will appear here.' : 'Requisition details are unavailable for your role.'?></p></div></td></tr><?php endif; ?>
            </tbody></table></div><div class="cu-table-foot"><span>Showing <?=count($data['pending'])?> of <?=number_format($data['pending_total'])?> pending</span><span><?=cssrUiEscape($data['scope_label'])?></span></div>
        </section>
        <section class="cu-card" aria-labelledby="low-heading"><div class="cu-card-head"><div><h2 id="low-heading">Low Stock Items</h2><p>Replenishment priorities, including stockouts</p></div><?php if (!empty($links['inventory'])): ?><a class="cu-btn cu-btn-ghost cu-btn-sm" href="<?=cssrUiEscape($links['inventory'])?>">Inventory <?=cssrUiIcon('chevron')?></a><?php endif; ?></div><div class="cu-table-wrap" tabindex="0" role="region" aria-label="Low stock items; scroll for more columns"><table class="cu-table"><caption class="cu-sr">Items below reorder level, with stockouts first</caption><thead><tr><th scope="col">Item Name</th><th scope="col" class="cu-number">Current Stock</th><th scope="col" class="cu-number">Reorder Level</th><th scope="col">Status</th></tr></thead><tbody>
            <?php foreach ($data['low_items'] as $item): $out = (int)$item['current_stock'] <= 0; ?><tr><td><strong class="cu-item-name"><?=cssrUiEscape($item['item_name'])?></strong><small><?=cssrUiEscape($item['item_code'])?> · <?=cssrUiEscape($item['unit'] ?: 'Units')?></small></td><td class="cu-number"><?=number_format((int)$item['current_stock'])?></td><td class="cu-number"><?=number_format((int)$item['minimum_stock'])?></td><td><span class="cu-badge cu-badge-<?=$out ? 'danger' : 'warning'?>"><?=$out ? 'Out of Stock' : 'Low Stock'?></span></td></tr><?php endforeach; ?>
            <?php if (!$data['low_items']): ?><tr><td colspan="4"><div class="cu-empty cu-dashboard-empty"><span class="cu-icon-tile"><?=cssrUiIcon('check')?></span><h3><?=$data['inventory_visible'] ? 'No stock alerts' : 'Inventory access required'?></h3><p><?=$data['inventory_visible'] ? 'Items below reorder level will appear here.' : 'Stock alerts are unavailable for your role.'?></p></div></td></tr><?php endif; ?>
        </tbody></table></div><div class="cu-table-foot"><span>Showing <?=count($data['low_items'])?> priority items</span><span>Current active inventory</span></div></section>
    </div>
    <p class="cu-dashboard-note">Stock categories: Available = positive stock at or above reorder level; Low Stock = positive stock below reorder level; Out of Stock = zero or negative stock. Quantities reflect the latest recorded balances.</p>
</div>
<?php if ($preview): foreach ($data['pending'] as $request): ?>
<dialog class="cu-dialog" id="request-preview-<?=(int)$request['id']?>" aria-labelledby="request-title-<?=(int)$request['id']?>"><div class="cu-dialog-head"><h2 id="request-title-<?=(int)$request['id']?>"><?=cssrUiEscape($request['request_no'])?></h2><button type="button" class="cu-icon-button" data-cu-dismiss aria-label="Close requisition preview"><?=cssrUiIcon('close')?></button></div><div class="cu-card-body cu-stack"><p><?=cssrUiEscape($request['department_name'])?> · <?=(int)$request['item_count']?> items</p><span class="cu-badge cu-badge-<?=$request['status'] === 'Pending' ? 'warning' : 'info'?>"><?=$request['status'] === 'Pending' ? 'For Approval' : 'For Release'?></span><p class="cu-muted">This is a sample requisition. On the live dashboard, View opens the existing authorized request details.</p></div><div class="cu-dialog-foot"><button type="button" class="cu-btn cu-btn-secondary" data-cu-dismiss>Close</button></div></dialog>
<?php endforeach; endif; ?>
<script type="application/json" id="cu-dashboard-data"><?=json_encode(['monthly'=>$data['monthly'], 'stock'=>array_map('intval', [$data['counts']['available'],$data['counts']['low'],$data['counts']['out']])], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR)?></script>
<?php cssrUiEnd(); }
