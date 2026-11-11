<?php
// Pure functions and in-memory DB only; never load production authentication.
require_once __DIR__.'/../includes/chat-attachments.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec("CREATE TABLE chat_messages(attachment_path TEXT,sender_user_id INTEGER,receiver_user_id INTEGER)");
$db->exec("INSERT INTO chat_messages VALUES('sample.jpg',1,2)");
$checks=0;function securityCheck($ok,$message){global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
securityCheck(cssrChatAttachmentRecord($db,'sample.jpg',1)!==null,'Sender can view');
securityCheck(cssrChatAttachmentRecord($db,'sample.jpg',2)!==null,'Recipient can view');
foreach([0,3,999] as $viewer)securityCheck(cssrChatAttachmentRecord($db,'sample.jpg',$viewer)===null,'Other users cannot view');
foreach(['../sample.jpg','sample.jpg.php','sample.jpg\r\nHeader: x',"' OR 1=1 --",['sample.jpg']] as $file)securityCheck(cssrChatAttachmentRecord($db,$file,1)===null,'Malformed attachment denied');
function log_error(Throwable $e,string $context='APP'):void{}
$source=file_get_contents(__DIR__.'/../includes/auth.php');
$start=strpos($source,'function safe_error_message(');$end=strpos($source,'function shared_db(', $start);eval(substr($source,$start,$end-$start));
securityCheck(safe_error_message(new PDOException('SQLSTATE sensitive SQL path'),'Friendly error')==='Friendly error','SQL errors hidden');
securityCheck(safe_error_message(new TypeError('C:\\server\\private.php'),'Friendly error')==='Friendly error','Engine errors hidden');
securityCheck(safe_error_message(new RuntimeException('Quantity exceeds available stock.'))==='Quantity exceeds available stock.','Business validation preserved');
securityCheck(str_contains($source,"ini_set('session.use_strict_mode', '1')"),'Strict sessions enabled');
securityCheck(str_contains($source,"PHP_SAPI === 'cli' && getenv('NURSING_RUN_SCHEMA_SETUP') === '1'"),'No web schema migration');
$rootAuth=file_get_contents(__DIR__.'/../../includes/auth.php');
securityCheck(str_contains($rootAuth,'session_regenerate_id')||str_contains(file_get_contents(__DIR__.'/../../pages/login.php'),'session_regenerate_id(true)'),'Login regenerates session');
securityCheck(str_contains($rootAuth,'hash_equals($_SESSION[\'csrf_token\'], $token)'),'CSRF uses constant time comparison');
securityCheck(str_contains($rootAuth,'password_verify($password, $hash)'),'Passwords verified with password API');
foreach(['get_patients'=>'view','get_alarms'=>'view','get_patient_code'=>'create','resolve_alarm'=>'edit'] as $api=>$action){
 $code=file_get_contents(__DIR__.'/../../api/'.$api.'.php');securityCheck(str_contains($code,"requireApiPermission('patients', '$action')"),'Server API permission '.$api);
}
echo "Passed $checks attachment ownership, safe errors, session, CSRF and API guard checks.\n";
