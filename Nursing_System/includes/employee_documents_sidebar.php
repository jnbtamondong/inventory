<?php
$documentFilter = (string)($_GET['filter'] ?? '');
$documentView = (string)($_GET['view'] ?? 'dashboard');
$documentNav = [
    'Overview' => [
        ['dashboard', '', 'Dashboard', 'fa-chart-line'],
        ['profile', '', 'Profile', 'fa-user'],
        ['employees', '', 'Employee Master List', 'fa-users'],
        ['attendance_leave', '', 'Attendance & Leave', 'fa-calendar-check'],
        ['resigned', '', 'Resigned Employees', 'fa-user-slash'],
    ],
    'Document Workflow' => [
        ['upload', '', 'Upload Documents', 'fa-cloud-arrow-up'],
        ['documents', 'pending', 'For Verification', 'fa-clipboard-check'],
        ['expiring', '', 'Expiring Soon', 'fa-hourglass-half'],
        ['expired', '', 'Expired Documents', 'fa-triangle-exclamation'],
    ],
    'Administration' => [],
];
$documentNav['Administration'][] = ['reports', '', 'Reports', 'fa-file-export'];
if ($canManageAll) $documentNav['Administration'][] = ['audit', '', 'Audit Trail', 'fa-clock-rotate-left'];
if (!$canManageAll) $documentNav = ['My Documents' => [
    ['profile', '', 'Profile', 'fa-user'],
    ['upload', '', 'Upload Documents', 'fa-cloud-arrow-up'],
]];
if (!$canManageAll && $canVerify) $documentNav['Document Workflow'] = [
    ['documents', 'pending', 'For Verification', 'fa-clipboard-check'],
];
?>
<aside class="tc-sidebar" id="documentSidebar" <?=$canMarkResigned?'data-can-mark-resigned="1"':''?>>
    <div class="tc-brand">
        <img src="../public/images/branding/jvgh_seal.png" alt="JVGH seal">
        <div><strong>JVGH Nursing</strong><small>Employee Documents</small></div>
        <button type="button" class="tc-sidebar-close" data-ed-close aria-label="Close navigation">×</button>
    </div>
    <div class="tc-status"><span></span><div><strong>Secure Records</strong><small>Document compliance center</small></div></div>
    <nav>
        <?php foreach ($documentNav as $section => $items): ?>
            <?php if (!$items) continue; ?>
            <div class="tc-nav-section"><?=h($section)?></div>
            <?php foreach ($items as [$key, $filter, $label, $icon]):
                $active = $documentView === $key && $documentFilter === $filter;
                $href = 'employee_documents.php' . (($key === 'dashboard' && $filter === '') ? '' : '?view=' . $key . ($filter ? '&amp;filter=' . $filter : ''));
                if ($key === 'profile') $href .= '&amp;employee_id=' . (int)$userId;
            ?>
                <a class="<?=$active?'active':''?>" href="<?=$href?>"><span><i class="fa-solid <?=$icon?>"></i></span><em><?=h($label)?></em></a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
</aside>
<div class="tc-sidebar-scrim" data-ed-close></div>
<?php if($documentView==='attendance_leave'):$attendanceAbsentMap=[];$attendanceLateBands=[];$attendanceRecordDetails=[];$attendanceEmployeeKeys=[];foreach(($employees??[])as$attendanceEmployee){$attendanceKey=(string)($attendanceEmployee['employee_id']?:$attendanceEmployee['user_id']);$attendanceEmployeeKeys[(int)$attendanceEmployee['user_id']]=$attendanceKey;$attendanceAbsentMap[$attendanceKey]=(int)($leaveSummaries[(int)$attendanceEmployee['user_id']]['absent_count']??0);$attendanceLateBands[$attendanceKey]=['under15'=>0,'oneHour'=>0];$attendanceRecordDetails[$attendanceKey]=[];}$attendancePeriod="YEAR(record_date)=".(int)($leaveYear??date('Y'));if((int)($leaveMonth??0)>0)$attendancePeriod.=" AND MONTH(record_date)=".(int)$leaveMonth;$attendanceBandResult=$conn->query("SELECT employee_id,SUM(late_minutes>0 AND late_minutes<15) under15,SUM(late_minutes>=60) one_hour FROM nursing_employee_attendance WHERE $attendancePeriod GROUP BY employee_id");foreach($attendanceBandResult->fetch_all(MYSQLI_ASSOC)as$attendanceBand){$attendanceKey=$attendanceEmployeeKeys[(int)$attendanceBand['employee_id']]??null;if($attendanceKey!==null)$attendanceLateBands[$attendanceKey]=['under15'=>(int)$attendanceBand['under15'],'oneHour'=>(int)$attendanceBand['one_hour']];}$attendanceDetailResult=$conn->query("SELECT employee_id,record_date,leave_end_date,duty_schedule,actual_time_in,late_minutes,leave_type,leave_days,remarks FROM nursing_employee_attendance WHERE $attendancePeriod ORDER BY record_date DESC,id DESC");foreach($attendanceDetailResult->fetch_all(MYSQLI_ASSOC)as$attendanceDetail){$attendanceKey=$attendanceEmployeeKeys[(int)$attendanceDetail['employee_id']]??null;if($attendanceKey!==null)$attendanceRecordDetails[$attendanceKey][]=$attendanceDetail;}?><script>window.attendanceAbsentCounts=<?=json_encode($attendanceAbsentMap,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;window.attendanceLateBands=<?=json_encode($attendanceLateBands,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;window.attendanceRecordDetails=<?=json_encode($attendanceRecordDetails,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;</script><script defer src="../public/js/attendance-leave-range.js?v=20260922-8"></script><?php endif;?>
<?php if($documentView==='upload'):?><script defer src="../public/js/upload-other-document.js?v=20260922-3"></script><?php endif;?>
