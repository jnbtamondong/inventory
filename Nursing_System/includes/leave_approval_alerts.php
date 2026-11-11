<?php
$leaveApprovalRecipient=(int)($_SESSION['user_id']??0);
$leaveApprovalNotices=exRows($conn,"SELECT message FROM nursing_document_notifications WHERE employee_id=? AND notification_type='LEAVE_APPROVED' AND status='UNREAD' ORDER BY id DESC LIMIT 10",[$leaveApprovalRecipient]);
if($leaveApprovalNotices): ?>
<section class="schedule-notice" data-tone="success" role="status" aria-label="Leave approval notifications">
<strong>Leave cleared for HR filing</strong>
<?php foreach($leaveApprovalNotices as $leaveApprovalNotice): ?><p><?=h($leaveApprovalNotice['message'])?></p><?php endforeach; ?>
<a href="my_requests.php?type=LEAVE">View my leave applications</a>
</section>
<?php endif; ?>
