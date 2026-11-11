<?php
function exNavigationLinks(array $session,?array $access=null): array {
 $role=canonicalRole($session['role']??'');if(!in_array($role,['STAFF_NURSE','HEAD_NURSE','SUPERVISOR_NURSE','CHIEF_NURSE','SUPER_ADMIN'],true))return [];
 if($access===null){global $conn;$access=['view'=>true,'submit'=>$role!=='SUPER_ADMIN'];if(isset($conn)&&$conn instanceof mysqli){$exists=$conn->query("SHOW TABLES LIKE 'nursing_exchange_permissions'")->num_rows;if($exists){$statement=$conn->prepare('SELECT can_view,can_submit FROM nursing_exchange_permissions WHERE role=?');$statement->bind_param('s',$role);$statement->execute();$permission=$statement->get_result()->fetch_assoc();if($permission)$access=['view'=>(bool)$permission['can_view'],'submit'=>(bool)$permission['can_submit']];}}}
 if(!$access['view'])return [];
 $links=[['Dashboard','dashboard'],['My Requests','mine'],['Pending Confirmation','confirmation']];
 if($access['submit'])array_splice($links,1,0,[['New Exchange Request','new']]);
 if($role!=='STAFF_NURSE')$links[]=['Pending Approvals','approvals'];$links[]=['Exchange History','history'];
 if($role==='SUPER_ADMIN')$links[]=['Permissions & Approvers','configuration'];return $links;
}
