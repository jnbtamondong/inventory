<?php require_once __DIR__.'/schedule_management.php'; require_once __DIR__.'/nursing_requests.php'; $schedulePage=basename($_SERVER['PHP_SELF']??'');$scheduleRequestKind=$kind??($_GET['kind']??'OFF');$scheduleExchangePage=$schedulePage==='exchange_application.php';$scheduleExchangeView=$scheduleExchangePage?($view??($_GET['view']??'dashboard')):''; ?>
<aside class="sidebar nursing-sidebar schedule-sidebar" aria-label="Schedule navigation">
    <div class="sidebar-brand">
        <img class="nursing-sidebar-logo" src="../public/images/branding/jvgh_seal.png" alt="JVGH seal">
        <div class="nursing-sidebar-brand-copy"><strong>NURSYNC</strong><small>Schedule Management</small></div>
        <button class="sidebar-toggle" type="button" title="Open / close sidebar" aria-label="Open or close sidebar">
            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
        </button>
    </div>
    <div class="nursing-sidebar-status"><span></span><div><strong>Nursing Scheduling</strong><small>JVGH Nursing Service</small></div></div>
    <nav aria-label="Nursing Schedule Management">
        <ul class="menu" data-schedule-modules>
            <?php $navActor=$actor??$_SESSION;$navSchedule=nsScheduleAccess($navActor);if($navSchedule['view']): ?>
            <li class="rt-nav-heading">SCHEDULING</li>
            <li><a data-schedule-nav="dashboard" href="schedule_management.php?panel=dashboard"><i class="fa-solid fa-chart-line" aria-hidden="true"></i><span>Schedule Dashboard</span></a></li>
            <li data-schedule-module="monthly"><a data-schedule-nav="monthly" href="schedule_management.php" <?=$schedulePage==='schedule_management.php'&&empty($_GET['panel'])?'class="active" aria-current="page"':''?>><i class="fa-solid fa-calendar-days" aria-hidden="true"></i><span>Monthly Duty Schedule</span></a></li>
            <?php if($navSchedule['edit']):?><li><a data-schedule-nav="groups" href="schedule_management.php#scheduleEmployeeSetup"><i class="fa-solid fa-users" aria-hidden="true"></i><span>Duty Group Management</span></a></li><?php endif;?>
            <?php if($navSchedule['approve']):?><li><a data-schedule-nav="approval" href="schedule_management.php#scheduleWorkflow"><i class="fa-solid fa-check" aria-hidden="true"></i><span>Schedule Approval</span></a></li><?php endif;?>
            <?php endif; ?>
            <li class="rt-nav-heading">REQUEST MANAGEMENT</li>
            <li data-schedule-module="my-requests"><a href="my_requests.php" <?=$schedulePage==='my_requests.php'&&!($approvals??false)?'class="active" aria-current="page"':''?>><i class="fa-solid fa-list-check" aria-hidden="true"></i><span>My Requests</span></a></li>

            <?php if(nrCanEncode($navActor,'OFF')):?><li data-schedule-module="off"><a href="request_application.php?kind=OFF&amp;view=new" <?=$schedulePage==='request_application.php'&&$scheduleRequestKind==='OFF'?'class="active" aria-current="page"':''?>><i class="bi bi-calendar-x" aria-hidden="true"></i><span>Request-Off</span></a></li><?php endif;?>
            <li data-schedule-module="leave"><a href="request_application.php?kind=LEAVE&amp;view=<?=nrCanEncode($navActor,'LEAVE')?'new':'mine'?>" <?=$schedulePage==='request_application.php'&&$scheduleRequestKind==='LEAVE'?'class="active" aria-current="page"':''?>><i class="fa-solid fa-calendar-minus" aria-hidden="true"></i><span>Leave Management</span></a></li>
            <?php require_once __DIR__.'/duty_exchange_navigation.php';if(exNavigationLinks($_SESSION??[])): ?><li data-schedule-module="exchange"><a href="exchange_application.php?view=new" <?=$scheduleExchangePage&&$scheduleExchangeView!=='configuration'?'class="active" aria-current="page"':''?>><i class="fa-solid fa-right-left" aria-hidden="true"></i><span>Exchange of Duty</span></a></li><?php endif; ?>
            <?php if(naApprovalAccess($navActor)):?><li><a href="my_requests.php?view=approvals" <?=$schedulePage==='my_requests.php'&&($approvals??false)?'class="active" aria-current="page"':''?>><i class="fa-solid fa-user-check" aria-hidden="true"></i><span>Requests for Approval</span></a></li><?php endif;?>
            <?php if($navSchedule['view']):?><li class="rt-nav-heading">MANAGEMENT</li><li><a data-schedule-nav="employees" href="schedule_management.php?panel=employees#scheduleEmployeeSetup"><i class="fa-solid fa-address-book" aria-hidden="true"></i><span>Employee Masterlist</span></a></li><li><a data-schedule-nav="hours" href="schedule_management.php?panel=hours"><i class="fa-solid fa-clock" aria-hidden="true"></i><span>Duty Hours Summary</span></a></li><li><a data-schedule-nav="reports" href="schedule_management.php?panel=reports#scheduleSummaryTitle"><i class="fa-solid fa-file-lines" aria-hidden="true"></i><span>Reports</span></a></li><?php endif;?>
            <li class="rt-nav-heading">SYSTEM</li><li><a <?=in_array($schedulePage,['schedule_configuration.php'],true)||$scheduleExchangeView==='configuration'?'class="active" aria-current="page"':''?> href="<?=naRole($navActor)==='SUPER_ADMIN'?'schedule_configuration.php':'account_settings.php'?>"><i class="fa-solid fa-gear" aria-hidden="true"></i><span>Settings</span></a></li><li><a href="system_menu.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i><span>Back to Main Menu</span></a></li>
        </ul>
    </nav>
</aside>

<script defer src="../public/js/schedule-navigation.js?v=1"></script>
