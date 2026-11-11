<?php
// CLI only: render the actual account templates with synthetic data, bypassing all backend code.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function hasPermission($module,$action='view'){return true;}
function canonicalRole($role){return $role;}
function roleLabel($role){return ucwords(strtolower(str_replace('_',' ',$role)));}
$_SESSION=['user_id'=>1,'role'=>'SUPER_ADMIN','first_name'=>'Sample','last_name'=>'Admin'];
$cssrWorkspace=true;$error='';$success='';$pendingResetRequest=($argv[1]??'')==='settings-pending'?['id'=>1]:null;
$departmentOptions=['CSSR'=>'CSSR','ER'=>'Emergency Room'];$roleOptions=['SUPER_ADMIN'=>'Super Admin','CSSR_STAFF'=>'CSSR Staff'];$jobTitleOptions=['Nurse'=>'Nurse'];
$users=[['user_id'=>2,'employee_id'=>'TEST-002','username'=>'sample.staff','first_name'=>'Sample','last_name'=>'Staff','position'=>'Nurse','role'=>'CSSR_STAFF','department'=>'CSSR','status'=>'ACTIVE','is_active'=>1,'email'=>'','updated_at'=>'2026-10-07']];
$user=$users[0];$activeUserCount=1;$inactiveUserCount=0;$departmentCount=2;$roleCount=2;$jobTitleCount=1;$passwordResetRequests=[];
$staffRoleOptions=$roleOptions;$users[0]['employment_status']='PERMANENT';$users[0]['date_hired']='2026-01-01';$user=$users[0];
$page=str_starts_with($argv[1]??'','settings')?'account_settings':'admin_users';
$dir=realpath(__DIR__.'/../../pages');$source=file_get_contents($dir.'/'.$page.'.php');
$start=strpos($source,'<?php if($cssrWorkspace):');if($start===false)throw new RuntimeException('Template not found');
$template=substr($source,$start);
$template=str_replace('__DIR__',var_export($dir,true),$template);
ob_start();eval('?>'.$template);$html=ob_get_clean();
// The fixture lives outside pages; keep shared root assets resolving as in production.
$html=str_replace(['src="../public/','href="../public/'],['src="/Nursing_System/public/','href="/Nursing_System/public/'],$html);
echo $html;
