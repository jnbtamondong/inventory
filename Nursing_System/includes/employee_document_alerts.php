<?php
$personalDocumentAlerts=$conn->prepare("SELECT CONCAT(d.document_type,CASE WHEN d.expiry_date<CURDATE() THEN ' expired on ' ELSE ' expires on ' END,DATE_FORMAT(d.expiry_date,'%M %d, %Y'),'.') AS message FROM nursing_document_notifications n JOIN nursing_employee_documents d ON d.id=n.employee_document_id WHERE n.employee_id=? AND n.status='UNREAD' AND n.notification_type=CASE WHEN d.expiry_date<CURDATE() THEN 'DOCUMENT EXPIRED' ELSE 'DOCUMENT EXPIRING SOON' END AND d.archived_at IS NULL AND d.document_status<>'SUPERSEDED' AND d.expiry_date=n.notification_date ORDER BY d.expiry_date,n.id DESC LIMIT 25");
$personalDocumentAlerts->bind_param('i',$userId);$personalDocumentAlerts->execute();
$personalDocumentAlerts=$personalDocumentAlerts->get_result()->fetch_all(MYSQLI_ASSOC);
if($personalDocumentAlerts): ?>
<section class="panel" aria-label="Document expiry notifications" role="status"><h2><i class="fa-solid fa-bell" aria-hidden="true"></i> Your document alerts</h2>
<?php foreach($personalDocumentAlerts as $personalDocumentAlert): ?><p><?=h($personalDocumentAlert['message'])?></p><?php endforeach; ?>
<a class="btn light" href="employee_documents.php?view=profile&amp;employee_id=<?=$userId?>">View my documents</a></section>
<?php endif; ?>
