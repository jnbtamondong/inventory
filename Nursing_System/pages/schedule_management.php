<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/schedule_management.php';
requireLogin();
$scheduleAccess = nsScheduleAccess($_SESSION);
if (!$scheduleAccess['view']) { http_response_code(403); exit('Scheduling is available only to Super Admin, Head Nurse, Supervisor, Chief Nurse, and Assistant Chief Nurse.'); }
$scheduleDepartments = nsScheduleDepartments();
if ($scheduleAccess['department'] !== '' && !isset($scheduleDepartments[$scheduleAccess['department']])) $scheduleDepartments[$scheduleAccess['department']] = str_replace('_', ' ', $scheduleAccess['department']);
if (!$scheduleAccess['all']) $scheduleDepartments = array_intersect_key($scheduleDepartments, [$scheduleAccess['department'] => true]);
$scheduleDepartment = isset($scheduleDepartments[$_GET['department'] ?? '']) ? $_GET['department'] : (array_key_first($scheduleDepartments) ?? '');
$scheduleCurrentMonth = nsScheduleCurrentMonth();$scheduleMonth = $scheduleCurrentMonth;
try { if (isset($_GET['month'])) { nsScheduleMonth((string) $_GET['month']); if($_GET['month'] >= $scheduleCurrentMonth)$scheduleMonth = $_GET['month']; } } catch (InvalidArgumentException $error) { /* Retain the current month for malformed links. */ }
$scheduleBoot = ['access' => $scheduleAccess, 'department' => $scheduleDepartment, 'month' => $scheduleMonth, 'currentMonth'=>$scheduleCurrentMonth, 'csrf' => csrfToken()];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Monthly Duty Scheduling | NURSYNC</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css">
    <link rel="stylesheet" href="../public/css/module-theme.css?v=18">
    <link rel="stylesheet" href="../public/css/schedule-management.css?v=<?=filemtime(__DIR__.'/../public/css/schedule-management.css')?>">
    <link rel="stylesheet" href="../public/css/monthly-schedule-design.css?v=<?=filemtime(__DIR__.'/../public/css/monthly-schedule-design.css')?>">
    <script defer src="../public/js/nursync-ui.js?v=4"></script>
    <script defer src="../public/js/schedule-engine.js?v=13"></script>
    <script defer src="../public/js/schedule-export.js?v=1"></script>
    <script defer src="../public/js/schedule-management.js?v=<?=filemtime(__DIR__.'/../public/js/schedule-management.js')?>"></script>
    <script defer src="../public/js/schedule-sidebar.js?v=1"></script>
</head>
<body class="module-ui schedule-page monthly-schedule-page">
<nav class="navbar"><div class="navbar-brand"><img class="schedule-seal" src="../public/images/branding/jvgh_seal.png" alt="JVGH seal"><div class="navbar-title-stack"><h1>NURSYNC</h1><span class="navbar-system-subtitle">Joni Villanueva General Hospital</span></div></div><div class="navbar-menu"><span class="user-info"><?=h($_SESSION['first_name'] ?? '')?></span></div></nav>
<div class="dashboard-container">
<?php include __DIR__ . '/../includes/schedule_sidebar.php'; ?>
<main class="main-content schedule-main" id="scheduleApp" aria-busy="true">

    <header class="schedule-heading" data-form-section="header">
        <div><p class="schedule-eyebrow">JVGH Nursing Service · Schedule planning</p><h2>Monthly Duty Scheduling</h2><p class="schedule-subtitle">Plan department duties, review coverage, and publish the monthly roster.</p></div>
        <div class="schedule-actions"><span id="scheduleStatus" class="schedule-status">Draft</span><span id="scheduleVersion"></span><button type="button" class="btn btn-light icon-button" id="scheduleReload" title="Reload schedule" aria-label="Reload schedule"><i class="bi bi-arrow-clockwise"></i></button><button type="button" class="btn btn-light" id="scheduleHistory">History</button></div>
    </header>
    <?php if($scheduleAccess['edit']): ?><ol class="setup-steps" aria-label="Monthly scheduling steps"><?php foreach([1=>'Select Department',2=>'Select Month',3=>'Load Department Employees',4=>'Review Request-Off and Leave',5=>'Select Shift Type',6=>'Generate or Encode Schedule',7=>'Review and Adjust',8=>'Monthly Hours Summary',9=>'Finalize and Publish'] as $step=>$label): ?><li><button type="button" data-setup-step="<?=$step?>"><span><?=$step?></span><?=h($label)?></button></li><?php endforeach;?></ol><?php endif; ?>
    <section id="scheduleSetup" class="schedule-configuration" data-form-section="configuration" aria-label="Schedule configuration">
    <section class="schedule-period" aria-label="Schedule period">
        <label>Department / Unit<select id="scheduleDepartment"><?php foreach ($scheduleDepartments as $value => $label): ?><option value="<?=h($value)?>" <?=$value === $scheduleDepartment ? 'selected' : ''?>><?=h($label)?></option><?php endforeach; ?></select></label>
        <label>Month<select id="scheduleMonth"><?php for ($m = 1; $m <= 12; $m++): ?><option value="<?=sprintf('%02d', $m)?>" <?=$m === (int)substr($scheduleMonth, 5, 2) ? 'selected' : ''?>><?=date('F', mktime(0, 0, 0, $m, 1, 2026))?></option><?php endfor; ?></select></label>
        <label>Year<input type="number" id="scheduleYear" min="<?=h(substr($scheduleCurrentMonth,0,4))?>" max="2100" value="<?=h(substr($scheduleMonth, 0, 4))?>" required></label>
        <div class="schedule-period-total"><strong id="scheduleRosterCount">0</strong><span>active staff</span></div>
    </section>

    <?php if ($scheduleAccess['edit']): ?>
        <details id="scheduleEmployeeSetup" class="schedule-options-detail"><summary>Schedule employees <span id="scheduleSetupCount"></span></summary><p>All eligible active nursing employees from the selected department load automatically. Nurses are listed before nursing attendants.</p><div class="schedule-actions"><button type="button" class="btn btn-light" id="scheduleResume">Resume Editing</button></div><div id="scheduleSetupRoster" class="setup-roster"></div></details>
        <details id="scheduleRequestSetup" class="schedule-options-detail"><summary>Review Request-Off and Request Leave</summary><a class="btn btn-light" href="request_application.php?kind=OFF&amp;view=<?=nrCanEncode($_SESSION,'OFF')?'new':'history'?>" target="_blank" rel="noopener"><?=nrCanEncode($_SESSION,'OFF')?'Encode Request-Off':'Review Request-Off'?></a><a class="btn btn-light" href="request_application.php?kind=LEAVE&amp;view=history" target="_blank" rel="noopener">Review Request Leave</a><p>Requests use their own approval process. Approved requests load when the schedule is reloaded; save your draft first. Internal leave clearance indicates HR filing and does not establish official leave authorization.</p><div id="scheduleRequestsList" class="table-responsive"></div></details>
        <div class="schedule-configuration-options">
            <fieldset class="pattern-options" id="scheduleShiftType"><legend>Duty shift</legend><div class="pattern-options-row"><label class="pattern-option"><input type="radio" name="schedulePattern" value="8" checked><span><strong>8-Hour Duty</strong><small>AM / PM / N</small></span></label><label class="pattern-option"><input type="radio" name="schedulePattern" value="12"><span><strong>12-Hour Duty</strong><small>AM / PM / AM8 / PM8</small></span></label></div></fieldset>
            <p id="scheduleFixedPattern" hidden>Fixed daytime duty &middot; 8 / M</p>
            <label>Scheduling mode<select id="scheduleMode"><option value="AUTO">Automatic Mode</option><option value="MANUAL">Manual Mode</option></select></label>
        </div>
            <details class="rest-settings"><summary>Rest settings</summary><div class="rest-fields">
                <?php if(($_SESSION['role']??'')==='SUPER_ADMIN'): ?><a class="btn btn-light" href="schedule_configuration.php">Configure shift, department and approval policies</a><?php endif;?>
                <label>Rest between duties (hours)<input type="number" id="scheduleRestHours" min="0" max="48" value="12"></label>
                <label>Maximum consecutive duties<select id="scheduleMaxConsecutive"><?php for ($n = 2; $n <= 6; $n++): ?><option value="<?=$n?>" <?=$n===6?'selected':''?>><?=$n?> days</option><?php endfor; ?></select></label>
                <label>Minimum days off per 7 days<select id="scheduleOffDays"><?php for ($n = 1; $n <= 6; $n++): ?><option value="<?=$n?>"><?=$n?></option><?php endfor; ?></select></label>
            </div></details>
    <?php endif; ?>
    </section>
    <section class="schedule-rules" data-form-section="rules"><h3>Mandatory Scheduling Rules</h3><p><span id="scheduleRuleTarget">160</span> hours reference (12-hour pattern: 14 duties / 168 hours; variance is not approved overtime) &middot; Complete required shift coverage &middot; Staggered OFF &middot; No PM &rarr; AM</p><small id="schedulePolicyNote"></small></section>
    <section class="schedule-generation schedule-actions" data-form-section="generation" aria-label="Generation controls">
    <?php if ($scheduleAccess['edit']): ?><button type="button" class="btn btn-primary" id="scheduleGenerate"><i class="bi bi-magic"></i><strong>Generate Schedule</strong></button><button type="button" class="btn btn-primary" id="scheduleManual" hidden>Open Manual Roster</button><button type="button" class="btn btn-light" id="scheduleReset">Reset Schedule</button><?php endif; ?><button type="button" class="btn btn-light" id="scheduleCancelGenerate" hidden>Cancel generation</button>
    </section>
    <div id="scheduleMetrics" class="schedule-metrics" data-form-section="summary" aria-label="Scheduling summary"></div>
    <div class="schedule-actions schedule-view-selector" data-form-section="views" aria-label="Schedule view"><button type="button" class="btn btn-light" id="scheduleListView" aria-pressed="true">List View</button><button type="button" class="btn btn-light" id="scheduleCalendarView" aria-pressed="false">Calendar View</button><button type="button" class="btn btn-light" id="scheduleHoursView" aria-pressed="false">Hours Summary</button></div>
    <section id="scheduleWorkspace">
        <section class="schedule-calendar-section" data-form-section="roster" aria-labelledby="scheduleRosterTitle">
            <header class="calendar-header"><div><h3 id="scheduleRosterTitle">Monthly Duty Roster</h3><p><span id="scheduleCalendarTitle"></span> &middot; <span id="scheduleCalendarSubtitle"></span></p></div><div class="schedule-actions"><button type="button" class="btn btn-light icon-button" id="schedulePrevious" aria-label="Previous month"><i class="bi bi-chevron-left"></i></button><button type="button" class="btn btn-light icon-button" id="scheduleNext" aria-label="Next month"><i class="bi bi-chevron-right"></i></button></div></header>
            <div id="scheduleDutyList" class="table-responsive"></div><div id="scheduleCalendar" class="schedule-calendar" hidden></div>
        </section>
        <section class="schedule-summary-section" id="scheduleHoursPanel" hidden aria-labelledby="scheduleSummaryTitle">
            <header class="section-heading"><div><p class="schedule-eyebrow">MONTHLY TOTALS</p><h3 id="scheduleSummaryTitle">Employee hours summary</h3></div><div class="schedule-actions"><span class="target-label">REFERENCE: 160 HOURS</span><button type="button" id="scheduleExportSummary" class="btn btn-light icon-button" title="Export hours" aria-label="Export hours"><i class="bi bi-download"></i></button><button type="button" id="schedulePrintSummary" class="btn btn-light icon-button" title="Print hours" aria-label="Print hours"><i class="bi bi-printer"></i></button></div></header>
            <div class="table-responsive"><table class="schedule-table schedule-summary"><thead><tr><th>Employee / Position</th><th>Schedule department</th><th>AM</th><th>PM</th><th>N</th><th>AM8</th><th>PM8</th><th>OFF</th><th>Leave days</th><th>12H duties</th><th>8H duties</th><th>Duty days</th><th>Scheduled hours</th><th>Authorized leave credits</th><th>Actual rendered hours</th><th>Target hours</th><th>Remaining</th><th>Excess</th><th>Variance</th><th>Status</th></tr></thead><tbody id="scheduleSummary"></tbody></table></div>
        </section>

        <details class="schedule-review" id="scheduleReview" data-form-section="validation" open><summary><span id="scheduleConflictsTitle">Validation Results</span><span id="scheduleWarningCount"></span></summary><label class="calendar-filter">Filter<select id="scheduleConflictFilter"><option value="">All checks</option><option value="HOURS">Hours and pattern</option><option value="REST">Rest</option><option value="ABSENCE">Absences</option><option value="OTHER">Other</option></select></label><div id="scheduleConflicts" class="schedule-conflicts"></div></details>
        <section class="schedule-legend-section" data-form-section="legend"><h3>Duty Legend</h3><div id="scheduleLegend" class="schedule-legend"></div></section>
        <?php if ($scheduleAccess['edit']): ?><footer class="schedule-savebar" data-form-section="actions"><div><span id="scheduleSaveState">No changes</span><span id="scheduleDirty" hidden>Unsaved changes</span></div><div class="schedule-actions"><button type="button" class="btn btn-light" id="scheduleValidate">Validate Schedule</button><div class="schedule-actions" id="scheduleWorkflow"></div></div></footer><?php endif; ?>
        <div class="schedule-actions workspace-toolbar"><button type="button" class="btn btn-light" id="schedulePdf">Export PDF</button><button type="button" class="btn btn-light" id="schedulePrint">Print</button><button type="button" class="btn btn-light" id="scheduleExport">Export Excel</button></div>
    </section>
    <?php if($scheduleAccess['edit']): ?><div class="setup-footer"><button type="button" class="btn btn-light" id="scheduleSetupBack">Back</button><span id="scheduleStepCount"></span><button type="button" class="btn btn-light" id="scheduleSetupNext">Next</button></div><?php endif; ?>
    <p id="scheduleNotice" class="schedule-notice" role="status" aria-live="polite">Loading schedule...</p>
</main>
</div>
<dialog id="scheduleDialog" class="schedule-dialog" aria-labelledby="scheduleDialogTitle"><header><h3 id="scheduleDialogTitle"></h3><button type="button" class="btn btn-light icon-button" id="scheduleDialogClose" aria-label="Close dialog" title="Close"><i class="bi bi-x-lg"></i></button></header><div id="scheduleDialogBody"></div></dialog>
<script type="application/json" id="scheduleBoot"><?=json_encode($scheduleBoot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?></script>
<script src="../public/js/sidebar.js"></script>
</body>
</html>
