<?php
require_once __DIR__.'/../includes/request-notifications.php';
$checks=0;
function checkNotifications($ok,$message){global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
foreach(['SUPER_ADMIN','CSSR_HEAD','CSSR_STAFF','NURSING_ATTENDANT','STAFF_NURSE','HEAD_NURSE','CHIEF_NURSE','ADMITTING_STAFF'] as $role){
    foreach(['CSSR','ER','WARD_2F',''] as $department){
        foreach([true,false] as $canView)foreach([true,false] as $canManage){
            $can=static fn($module,$action)=>$action==='view'?$canView:$canManage;
            $expected=($department==='CSSR'||in_array($role,['SUPER_ADMIN','CSSR_HEAD','CSSR_STAFF'],true))&&$canView&&$canManage;
            checkNotifications(cssrCanReceiveRequestNotifications(['role'=>$role,'department_name'=>$department],$can)===$expected,"Recipient authorization: $role/$department");
        }
    }
}
checkNotifications(!cssrCanReceiveRequestNotifications([],fn()=>false),'Anonymous denied');
$queries=[];
$query=static function($sql)use(&$queries){$queries[]=$sql;return match($sql){
    'SELECT COUNT(*) FROM item_requests WHERE status IN ("Pending","Approved")'=>4,
    'SELECT COUNT(*) FROM storage_requests WHERE status="Pending"'=>2,
    'SELECT COUNT(*) FROM department_returns WHERE status="Pending"'=>1,
    default=>throw new RuntimeException('Unexpected count query'),
};};
checkNotifications(cssrRequestNotificationCounts($query)===['department'=>4,'storage'=>2,'returns'=>1,'total'=>7],'Counts match queue states without counting item lines');
$queries=[];
checkNotifications(cssrRequestNotificationCounts($query,false)['total']===3&&count($queries)===2,'No department data queried without access');
checkNotifications(cssrRequestNotificationCounts(fn()=>0)['total']===0,'Completed requests produce zero badge');
checkNotifications(str_contains(cssrRequestNotificationBadge(true,0),'hidden'),'Zero badge hidden');
checkNotifications(cssrRequestNotificationBadge(false,12)==='','Unauthorized badge absent');
checkNotifications(str_contains(cssrRequestNotificationBadge(true,120),'99+'),'Large badge fits');
foreach(['SUPER_ADMIN','CSSR_STAFF','NURSING_ATTENDANT','STAFF_NURSE','HEAD_NURSE'] as $role){
    checkNotifications(cssrCanReceiveChatNotifications(['role'=>$role],fn($module,$action)=>$module==='ris_requests'),'Chat notifications also serve department users');
    checkNotifications(!cssrCanReceiveChatNotifications(['role'=>$role],fn()=>false),'No chat notification without CSSR workspace access');
}
checkNotifications(!cssrCanReceiveChatNotifications(['role'=>'ADMITTING_STAFF'],fn()=>true),'Clerk remains excluded from CSSR');
checkNotifications(!cssrCanReceiveChatNotifications([],fn()=>true),'Anonymous chat notification denied');
$messages=[['receiver'=>42,'read'=>null],['receiver'=>42,'read'=>null],['receiver'=>7,'read'=>null],['receiver'=>42,'read'=>'2026-10-08']];
$chatQuery=static function($sql,$params)use($messages){
    checkNotifications($sql==='SELECT COUNT(*) FROM chat_messages WHERE receiver_user_id=? AND read_at IS NULL','Unread query is private and read-only');
    return count(array_filter($messages,fn($message)=>$message['receiver']===$params[0]&&$message['read']===null));
};
checkNotifications(cssrChatUnreadCount($chatQuery,42)===2,'Counts only unread messages for the receiver');
checkNotifications(cssrChatUnreadCount($chatQuery,7)===1,'Shared accounts have separate counts');
checkNotifications(cssrChatUnreadCount(fn()=>throw new RuntimeException('Must not query missing user'),0)===0,'No query for missing shared identity');
checkNotifications(str_contains(cssrRequestNotificationBadge(true,2,'chat'),'data-cu-chat-count'),'Dedicated chat badge');
echo "Passed $checks request-notification authorization and count checks without database writes.\n";
