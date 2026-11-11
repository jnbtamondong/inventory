<?php
require_once __DIR__.'/report-letterhead.php';
function cssrRenderReports(array $d,array $ui,array $options=[]): void
{
    $e='cssrUiEscape';$route=$options['route']??'reports.php';$f=$d['filters'];$print=!empty($options['print']);
    [$description,$emptyMessage]=cssrReportHelp($f['report']);$reportTitle=$d['types'][$f['report']]??'Reports';
    $ui['title']=$reportTitle;
    $reportMenu=array_column($ui['navigation'],null,'id')['reports']??[];
    $reportLinks=$reportMenu['children']??[];
    $allowedIds=array_column($reportLinks,'id');
    $d['types']=array_filter($d['types'],static fn($type)=>in_array(cssrReportNavigationActive($type),$allowedIds,true),ARRAY_FILTER_USE_KEY);
    $url=static fn($extra=[])=>$route.'?'.http_build_query(array_merge($f,$extra));
    $ui['styles'][]='../assets/css/reports.css?v='.filemtime(__DIR__.'/../assets/css/reports.css');$ui['scripts'][]='../assets/js/reports.js?v='.filemtime(__DIR__.'/../assets/js/reports.js');cssrUiStart($ui);
    $field=static function($key,$label,$choices=null)use($d,$f,$e){?><div class="cu-field"><label for="rp-<?=$key?>"><?=$label?></label><?php if($choices===null): ?><input class="cu-input" id="rp-<?=$key?>" name="<?=$key?>" type="date" required value="<?=$e($f[$key])?>" <?=isset($d['errors'][$key])?'aria-invalid="true" aria-describedby="rp-error-'.$key.'"':''?>><?php else: ?><select class="cu-input" id="rp-<?=$key?>" name="<?=$key?>" <?=isset($d['errors'][$key])?'aria-invalid="true" aria-describedby="rp-error-'.$key.'"':''?>><?php if($f[$key]!==''&&!array_key_exists($f[$key],$choices)&&!array_key_exists(ltrim($f[$key],'0'),$choices)): ?><option selected value="<?=$e($f[$key])?>"><?=$e($f[$key])?></option><?php endif; ?><?php foreach($choices as $value=>$text): ?><option value="<?=$e($value)?>" <?=(string)$value===$f[$key]||($key==='month'&&$value!==''&&(int)$value===(int)$f[$key])?'selected':''?>><?=$e($text)?></option><?php endforeach; ?></select><?php endif; ?><?php if(isset($d['errors'][$key])): ?><small class="rp-error" id="rp-error-<?=$key?>"><?=$e($d['errors'][$key])?></small><?php endif; ?></div><?php };
?>
<?php if(!$d['types']): ?><div class="cu-page-heading"><div><h1>Reports</h1><p class="cu-muted">Use Report Tools in the sidebar to open your available stock reports.</p></div></div><?php cssrUiEnd();return;endif; ?>
<div class="rp-report-layout">
<div class="cu-page-heading"><div><p class="cu-eyebrow">Insights &amp; accountability</p><h1><?=$e($reportTitle)?></h1><p class="cu-lead cu-muted"><?=$e($description)?></p></div><span class="cu-badge cu-badge-teal"><?=cssrUiIcon('report')?> Inventory analytics</span></div>
<div class="cu-stack" id="rp-workspace" <?=$print?'data-print-report':''?>>
<?php if(in_array($f['report'],['consumption','department_consumption'],true)): ?>
<nav class="cu-actions rp-usage-tabs" aria-label="Usage report view">
<?php foreach(['consumption'=>'By Date Range','department_consumption'=>'By Department'] as $type=>$label): ?>
    <a class="cu-btn <?=$f['report']===$type?'':'cu-btn-secondary'?>" href="<?=$e($url(['report'=>$type]))?>" <?=$f['report']===$type?'aria-current="page"':''?>><?=$label?></a>
<?php endforeach; ?>
</nav>
<?php endif; ?>
<?php if(in_array($f['report'],['monthly','weekly','annual'],true)): ?>
<nav class="cu-actions rp-period-tabs" aria-label="Report period">
<?php foreach(['monthly'=>'Monthly','weekly'=>'Weekly','annual'=>'Annual'] as $period=>$label):
    $periodUrl=$route.'?'.http_build_query(array_merge(array_intersect_key($f,array_flip(['department','classification','procurement_type'])),['report'=>$period])); ?>
    <a class="cu-btn <?=$f['report']===$period?'':'cu-btn-secondary'?>" href="<?=$e($periodUrl)?>" <?=$f['report']===$period?'aria-current="page"':''?>><?=$label?></a>
<?php endforeach; ?>
</nav>
<?php endif; ?>
<?php if(!empty($options['preview'])): ?><p class="cu-alert rp-preview">Design preview · Sample data only. Downloads contain sample records.</p><?php endif; ?>
<section class="cu-card rp-filter-card" aria-labelledby="rp-filter-title"><div class="cu-card-head"><div><h2 id="rp-filter-title">Report filters</h2><p>Choose a period and scope, then generate your report.</p></div><span class="cu-icon-tile"><?=cssrUiIcon('settings')?></span></div>
<form action="<?=$e($route)?>" class="rp-filter-form" id="rp-filters">
<div class="rp-filter-grid"><?php
    $field('from','From Date');$field('to','To Date');
    $field('department','Department',[''=>'All departments']+array_combine($d['departments'],$d['departments']));
    $field('classification','Classification',[''=>'All classifications']+array_combine($d['classifications'],$d['classifications']));
    $field('procurement_type','Procurement Type',[''=>'All procurement types','Procured'=>'Procured','Donation'=>'Donation','Emergency Purchase'=>'Emergency Purchase']);$field('report','Report Type',$d['types']);
?></div><div class="rp-filter-foot"><p class="cu-muted">From Date and To Date define the reporting period. <span id="rp-pending" hidden>Filters changed. Generate to update results.</span></p><div class="cu-actions"><a href="<?=$e($route.'?'.http_build_query(['report'=>$f['report']]))?>" class="cu-btn cu-btn-ghost">Reset</a><button class="cu-btn" type="submit"><?=cssrUiIcon('report')?>Generate Report</button></div></div></form></section>
<?php if($d['errors']): ?><div class="cu-alert rp-error" role="alert">Correct the highlighted filters to generate this report.</div><?php else: ?>
<div class="rp-result-heading"><div><p class="cu-eyebrow">Generated report</p><h2><?=$e($d['title'])?></h2><p class="cu-muted"><?=$e($d['period'])?> · Generated <?=$e($d['generated_at'])?></p></div><div class="cu-actions rp-exports" aria-label="Export generated report"><a class="cu-btn cu-btn-secondary" href="<?=$e($url(['export'=>'pdf']))?>"><?=cssrUiIcon('download')?>Export PDF</a><a class="cu-btn cu-btn-secondary" href="<?=$e($url(['export'=>'xlsx']))?>"><?=cssrUiIcon('download')?>Export Excel</a><a class="cu-btn cu-btn-secondary" href="<?=$e($url(['print'=>'1']))?>" data-rp-print><?=cssrUiIcon('document')?>Print</a></div></div>
<p class="rp-scope"><?=$e($d['filter_label'])?></p>
<?php if($f['report']==='near_expiry'): ?><p class="cu-alert">Showing usable batches expiring between <strong><?=$e($f['from'])?></strong> and <strong><?=$e($f['to'])?></strong>. Change From Date and To Date to adjust the expiry window. Already expired batches are excluded.</p><?php elseif($d['snapshot']): ?><p class="cu-alert">Balances show usable stock <strong>now</strong> across all storage locations, including CSSR. Selected dates apply to Stock In and Stock Out. A department filter limits the list to items issued to that department in the period.</p><?php endif; ?>
<section class="cu-stats rp-totals" aria-label="Report totals"><?php foreach([
    ['Matching table rows',number_format($d['count']),'report','All results, across every page'],
    ['Items In',number_format($d['totals']['in']),'inbound','Posted received units · chart scope'],
    ['Items Out',number_format($d['totals']['out']),'issue','Gross issued units · chart scope'],
    $d['snapshot']?['Current Balance',number_format($d['totals']['balance']),'box','Usable units · all storage locations']:['Received Value','₱'.number_format($d['totals']['value'],2),'document','Posted receipt value · chart scope'],
] as [$label,$value,$icon,$help]): ?><article class="cu-card cu-stat"><div class="cu-stat-top"><span><?=$label?></span><span class="cu-icon-tile"><?=cssrUiIcon($icon)?></span></div><strong class="cu-stat-value"><?=$value?></strong><small><?=$help?></small></article><?php endforeach; ?></section>
<section class="cu-card rp-chart-card" aria-labelledby="rp-chart-title"><div class="cu-card-head"><div><h2 id="rp-chart-title">Monthly Items In versus Items Out</h2><p>Posted receipts and gross issuance for the selected scope.</p></div><div class="rp-legend"><span><i class="rp-dot-in"></i>Items In</span><span><i class="rp-dot-out"></i>Items Out</span></div></div>
<?php if($d['chart']): $maximum=1;foreach($d['chart'] as $m)$maximum=max($maximum,$m['in'],$m['out']); ?>
<div class="rp-chart-scroll" tabindex="0" role="region" aria-label="Monthly quantities; scroll for additional months"><div class="rp-chart" style="--rp-months:<?=count($d['chart'])?>"><?php foreach($d['chart'] as $m): ?><div class="rp-month"><div class="rp-bars"><?php foreach(['in','out'] as $key): ?><div class="rp-bar-column"><span class="rp-bar-value"><?=number_format($m[$key])?></span><div class="rp-bar rp-bar-<?=$key?>" style="height:<?=round(150*$m[$key]/$maximum,2)?>px" title="<?=$e($m['label'].' · Items '.ucfirst($key).': '.number_format($m[$key]))?>"></div></div><?php endforeach; ?></div><span class="rp-month-label"><?=$e($m['label'])?></span><span class="cu-sr"><?=number_format($m['in'])?> units in, <?=number_format($m['out'])?> units out</span></div><?php endforeach; ?></div></div>
<?php else: ?><div class="cu-empty">No chart data for the selected date range.</div><?php endif; ?></section>
<section class="cu-card" aria-labelledby="rp-table-title"><div class="cu-card-head"><div><h2 id="rp-table-title">Report details</h2><p><?=$e($d['title'])?> · <?=number_format($d['count'])?> matching rows<?php if($f['report']==='fast_moving'): ?> · Highest issued quantity first<?php endif; ?></p></div><span class="cu-badge"><?=$d['totals']['items']?> items in chart scope</span></div>
<div class="cu-table-wrap" tabindex="0" role="region" aria-label="Generated report table"><table class="cu-table rp-table"><colgroup><?php foreach($d['columns'] as $key=>$label): ?><col class="<?=$key==='name'?'rp-col-name':($key==='status'?'rp-col-status':'')?>"><?php endforeach; ?></colgroup><thead><tr class="rp-print-only"><td colspan="<?=count($d['columns'])?>"><?php cssrReportPrintLetterhead($ui['assets']??'../assets'); ?><h2 class="rp-print-title"><?=$e($d['title'])?></h2></td></tr><tr><?php foreach($d['columns'] as $key=>$label): ?><th scope="col" class="<?=in_array($key,['in','out','balance','reorder','value','receipts'],true)?'rp-number':''?>"><?=$label?></th><?php endforeach; ?></tr></thead><tbody><?php foreach($print?$d['rows']:array_slice($d['rows'],($d['page']-1)*25,25) as $r): ?><tr><?php foreach($d['columns'] as $key=>$label): ?><td class="<?=in_array($key,['in','out','balance','reorder','value','receipts'],true)?'rp-number':''?>"><?php if($key==='status'): ?><span class="cu-badge cu-badge-<?=($r[$key]==='Available'?'success':(in_array($r[$key],['Low Stock','Near Expiry'],true)?'warning':'danger'))?>"><?=$e($r[$key])?></span><?php elseif($key==='name'||$key==='balance'): ?><strong><?=$e(cssrReportCell($key,$r[$key]??''))?></strong><?php else: ?><?=$e(cssrReportCell($key,$r[$key]??''))?><?php endif; ?></td><?php endforeach; ?></tr><?php endforeach; ?>
<?php if(!$d['rows']): ?><tr><td colspan="<?=count($d['columns'])?>"><div class="cu-empty"><span class="cu-icon-tile"><?=cssrUiIcon('report')?></span><h3>No matching records</h3><p><?=$e($emptyMessage)?></p></div></td></tr><?php endif; ?></tbody><tfoot class="rp-print-only" aria-hidden="true"><tr><td colspan="<?=count($d['columns'])?>"><div class="rp-footer-space"></div></td></tr></tfoot></table></div>
<footer class="cu-table-foot rp-pagination"><span><?=$d['count']?($d['page']-1)*25+1:0?>–<?=min($d['page']*25,$d['count'])?> of <?=number_format($d['count'])?> rows</span><nav class="cu-actions" aria-label="Report pages"><?php if($d['page']>1): ?><a class="cu-btn cu-btn-secondary cu-btn-sm" href="<?=$e($url(['page'=>$d['page']-1]))?>">Previous</a><?php endif; ?><span>Page <?=$d['page']?> of <?=$d['pages']?></span><?php if($d['page']<$d['pages']): ?><a class="cu-btn cu-btn-secondary cu-btn-sm" href="<?=$e($url(['page'=>$d['page']+1]))?>">Next</a><?php endif; ?></nav></footer></section>
<?php endif; ?></div>
<?php if(!$d['errors']): ?><div class="rp-notes-row"><details class="rp-notes" <?=$print?'open':''?>><summary>How this report is calculated</summary><ul><?php foreach($d['notes'] as $note): ?><li><?=$e($note)?></li><?php endforeach; ?></ul><p>Totals add recorded units without converting packs, boxes or pieces. Exports include every matching row and the generated filter selection.</p></details></div><?php endif; ?>
</div><?php cssrUiEnd(static fn()=>cssrReportPrintLetterhead($ui['assets']??'../assets','footer'));
}
