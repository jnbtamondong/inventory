<?php
if(PHP_SAPI!=='cli')exit;
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
session_save_path(sys_get_temp_dir());$_SERVER['REQUEST_METHOD']='CLI';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/employee_documents_schema.php';
$conn=new mysqli('localhost','root','');$database='nursync_doc_access_'.bin2hex(random_bytes(6));
$conn->query("CREATE DATABASE `$database`");$conn->select_db($database);$checks=0;
function docAccessCheck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo "PASS $label\n";}
try{
 foreach(['SECRETARY','SUPER_ADMIN','HEAD_NURSE','SUPERVISOR_NURSE','ASSISTANT_CHIEF_NURSE','CHIEF_NURSE','STAFF_NURSE','NURSING_ATTENDANT'] as $role){
    $canManageAll=in_array($role,['SECRETARY','SUPER_ADMIN'],true);$canVerify=employeeDocumentCanVerify($role);$canMarkResigned=$canManageAll;$userId=1;$_GET=['view'=>'profile'];
    ob_start();require __DIR__.'/../includes/employee_documents_sidebar.php';$sidebar=ob_get_clean();
    docAccessCheck(str_contains($sidebar,'>Profile</em>')&&str_contains($sidebar,'>Upload Documents</em>'),'self document links for '.$role);
    docAccessCheck(str_contains($sidebar,'>Employee Master List</em>')===$canManageAll&&str_contains($sidebar,'>Audit Trail</em>')===$canManageAll,'full sidebar restricted for '.$role);
    docAccessCheck(str_contains($sidebar,'>For Verification</em>')===in_array($role,['SECRETARY','SUPER_ADMIN','CHIEF_NURSE','ASSISTANT_CHIEF_NURSE','SUPERVISOR_NURSE'],true),'verification queue restricted for '.$role);
 }
 $conn->query('CREATE TABLE users(user_id INT,first_name VARCHAR(40),last_name VARCHAR(40))');
 $conn->query("INSERT INTO users VALUES(1,'Test','Owner'),(2,'Test','Other')");
 $conn->query('CREATE TABLE nursing_employee_documents(id INT,employee_id INT,document_type VARCHAR(80),expiry_date DATE,archived_at DATETIME NULL,document_status VARCHAR(30))');
 $conn->query("INSERT INTO nursing_employee_documents VALUES(1,1,'PRC License',DATE_ADD(CURDATE(),INTERVAL 42 DAY),NULL,'ACTIVE'),(2,2,'BLS',DATE_ADD(CURDATE(),INTERVAL 12 DAY),NULL,'ACTIVE'),(3,1,'ACLS',DATE_ADD(CURDATE(),INTERVAL 80 DAY),NULL,'ACTIVE'),(4,1,'Old BLS',CURDATE(),NULL,'SUPERSEDED')");
 $conn->query("CREATE TABLE nursing_document_notifications(id INT AUTO_INCREMENT PRIMARY KEY,employee_document_id INT,employee_id INT,notification_type VARCHAR(60),message VARCHAR(255),notification_date DATE,status VARCHAR(20) DEFAULT 'UNREAD',UNIQUE KEY notice(employee_document_id,notification_type,notification_date))");
 $source=file_get_contents(__DIR__.'/../pages/employee_documents.php');
 preg_match('/\$conn->query\("(INSERT IGNORE INTO nursing_document_notifications[^\r\n]+)"\);/',$source,$query);
 $conn->query($query[1]);$conn->query($query[1]);
 docAccessCheck((int)$conn->query('SELECT COUNT(*) n FROM nursing_document_notifications')->fetch_assoc()['n']===2,'expiry window catches non-milestone dates without duplicate alerts');
 $userId=1;ob_start();require __DIR__.'/../includes/employee_document_alerts.php';$alerts=ob_get_clean();
 docAccessCheck(str_contains($alerts,'PRC License')&&!str_contains($alerts,'Test Other')&&!str_contains($alerts,'ACLS'),'alerts show only owners current expiring documents');
 echo "$checks document access and notification checks passed.\n";
}finally{if(preg_match('/^nursync_doc_access_[a-f0-9]{12}$/D',$database))$conn->query("DROP DATABASE `$database`");}
