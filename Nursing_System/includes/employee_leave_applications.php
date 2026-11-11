<?php
// Uses only the employee IDs already authorized by Employee Documents.
$leaveApplicationIds=array_map('intval',array_column($employees,'user_id'));
if($view==='profile')$leaveApplicationIds=array_values(array_intersect($leaveApplicationIds,[(int)($pid??0)]));
$leaveApplications=[];
if($leaveApplicationIds){
    $leaveApplications=exRows($conn,"SELECT r.*,p.label FROM nursing_service_requests r LEFT JOIN nursing_leave_policy p ON p.code=r.leave_type WHERE r.kind='LEAVE' AND r.status='APPROVED_FOR_HR_FILING' AND r.employee_id IN (".implode(',',$leaveApplicationIds).") ORDER BY r.created_at DESC,r.id DESC");
    if($view!=='profile')$leaveApplications=array_values(array_filter($leaveApplications,static function(array $row)use($leaveYear,$leaveMonth):bool{
        foreach(json_decode($row['dates_json'],true) as $date)if((int)substr($date,0,4)===$leaveYear&&(!$leaveMonth||(int)substr($date,5,2)===$leaveMonth))return true;
        return false;
    }));
}
$leaveApplicationEscape=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');
?>
<section class="panel" id="leave-applications">
<h2>Approved Leave Applications</h2>
<p>Leave requests that have completed all applicable internal approvals.</p>
<div class="table-wrap"><table class="table"><thead><tr><th>Employee</th><th>Reference</th><th>Leave type</th><th>Dates</th><th>Days</th><th>Status</th></tr></thead><tbody>
<?php foreach($leaveApplications as $application):$applicationDates=json_decode($application['dates_json'],true); ?>
<tr><td><?=$leaveApplicationEscape($application['employee_name'])?></td><td><?=$leaveApplicationEscape($application['request_no'])?></td><td><?=$leaveApplicationEscape($application['label']??$application['leave_type'])?></td><td><?=$leaveApplicationEscape(implode(', ',array_map(static fn($date)=>date('m-d-Y',strtotime($date)),$applicationDates)))?></td><td><?=count($applicationDates)?></td><td><?=$leaveApplicationEscape(str_replace('_',' ',$application['status']))?></td></tr>
<?php endforeach; if(!$leaveApplications): ?><tr><td colspan="6">No approved leave applications for this selection.</td></tr><?php endif; ?>
</tbody></table></div></section>
