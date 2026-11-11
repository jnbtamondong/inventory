<?php
$active=$active??'';
$pendingPpmpApprovals=can_approve_ppmp()?(int)db_value('SELECT COUNT(*) FROM procurement_ppmp WHERE status="FOR APPROVAL"'):0;
$myPpmpRevisions=(int)db_value('SELECT COUNT(*) FROM procurement_ppmp WHERE created_by=? AND status="FOR REVISION"',[(int)(current_user()['id']??0)]);
$pendingItemRequests=can_review_procurement_item_requests()?(int)db_value('SELECT COUNT(*) FROM procurement_item_requests WHERE status="PENDING"'):0;
function procurement_sidebar_icon(string $key):string{
    $icons=['settings'=>'<path d="M4 7h16M4 17h16"/><circle cx="9" cy="7" r="3"/><circle cx="15" cy="17" r="3"/>','create'=>'<path d="M12 5v14M5 12h14"/>','masterlist'=>'<path d="M4 5h16v14H4z"/><path d="M4 10h16M9 10v9"/>','canvass'=>'<path d="M5 3h14v18H5z"/><path d="M8 8h8M8 12h8M8 16h5"/>','submissions'=>'<path d="M4 4h16v16H4z"/><path d="m8 12 3 3 5-6"/>','approval'=>'<path d="M12 3 4 7v5c0 5 3.4 8 8 9 4.6-1 8-4 8-9V7z"/><path d="m8 12 3 3 5-6"/>','requests'=>'<path d="M4 4h16v16H4z"/><path d="M8 9h8M8 13h5"/><path d="M15 17h3M16.5 15.5v3"/>','deliveries'=>'<path d="M3 7h11v10H3zM14 10h4l3 3v4h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>','menu'=>'<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>','logout'=>'<path d="M10 17l5-5-5-5M15 12H3"/><path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/>'];
    return '<svg viewBox="0 0 24 24" aria-hidden="true">'.($icons[$key]??'<circle cx="12" cy="12" r="8"/>').'</svg>';
}
function procurement_nav_link(string $key,string $label,string $href,bool $isActive,int $count=0,string $class=''):void{
    ?><a class="<?=$isActive?'active ':''?><?=e($class)?>" href="<?=e($href)?>" title="<?=e($label)?>" <?=$isActive?'aria-current="page"':''?>><span class="proc-nav-icon"><?=procurement_sidebar_icon($key)?></span><em><?=e($label)?></em><?php if($count>0):?><b><?=$count>99?'99+':$count?></b><?php endif;?></a><?php
}
?>
<link rel="stylesheet" href="../assets/procurement-responsive.css?v=<?=filemtime(__DIR__.'/../assets/procurement-responsive.css')?>">
<link rel="stylesheet" href="../../public/css/workflow-ui.css?v=<?=filemtime(__DIR__.'/../../public/css/workflow-ui.css')?>">
<link rel="stylesheet" href="../assets/procurement-design.css?v=<?=filemtime(__DIR__.'/../assets/procurement-design.css')?>">
<script defer src="../../public/js/workflow-ui.js?v=1"></script>
<script defer src="../assets/procurement-layout.js?v=<?=filemtime(__DIR__.'/../assets/procurement-layout.js')?>"></script>
<a class="proc-skip" href="#procWorkspace">Skip to workspace</a>
<aside class="proc-sidebar" id="procSidebar" aria-label="Procurement navigation">
    <div class="proc-brand"><img class="proc-brand-mark" src="/Nursing_System/public/images/branding/jvgh_seal.png" alt="JVGH seal" width="38" height="38"><div class="proc-brand-copy"><strong>JVGH</strong><small>Procurement</small></div><button class="proc-sidebar-close" type="button" data-proc-close aria-label="Close navigation"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button></div>
    <div class="proc-sidebar-context">Joni Villanueva<br>General Hospital</div>
    <nav aria-label="Procurement modules">
        <div class="proc-nav-section">Workspace</div>
        <?php procurement_nav_link('create','Create Plan or Request','ppmp_create.php',in_array($active,['ppmp-create','pr'],true));?>
        <div class="proc-nav-section">Procurement Records</div>
        <?php procurement_nav_link('masterlist','Procurement Records','ppmp.php',$active==='ppmp');?>
        <?php procurement_nav_link('canvass','Abstract of Canvass','quoted_items.php',$active==='abstract-canvass');?>
        <?php procurement_nav_link('submissions','My PPMP Submissions','ppmp_submissions.php',$active==='ppmp-submissions',$myPpmpRevisions);?>
        <?php if(can_approve_ppmp())procurement_nav_link('approval','PPMP for Approval','ppmp_approvals.php',$active==='ppmp-approval',$pendingPpmpApprovals);?>
        <?php if(can_review_procurement_item_requests())procurement_nav_link('requests','Review New Item Requests','item_requests.php',$active==='item-requests',$pendingItemRequests);?>
        <div class="proc-nav-section">Delivery Monitoring</div>
        <?php procurement_nav_link('deliveries','Deliveries','pending_deliveries.php',$active==='deliveries');?>
        <div class="proc-nav-spacer"></div>
    </nav>
</aside>
<div class="proc-sidebar-scrim" data-proc-close></div>
