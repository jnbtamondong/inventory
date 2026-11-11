<?php
// Test production menu and entry guard without sessions, schemas or live database access.
require_once __DIR__.'/../../includes/procurement-access.php';
$checks=0;
function checkProcurement($condition,$message){global $checks;if(!$condition)throw new RuntimeException($message);$checks++;}
function roleLabel($role){return $role;}
$menu=file_get_contents(__DIR__.'/../../pages/system_menu.php');
$template=substr($menu,strpos($menu,'<!DOCTYPE html>'));
$canAccessNursing=false;$canAccessSchedule=false;$canAccessReports=false;$isAdmin=false;$_SESSION=[];
$allowed=['SUPER_ADMIN','CSSR_HEAD','CSSR_STAFF','HEAD_NURSE','SUPERVISOR_NURSE','ASSISTANT_CHIEF_NURSE','CHIEF_NURSE'];
foreach(array_merge($allowed,['STAFF_NURSE','NURSE','NURSING_ATTENDANT','MIDWIFE','ADMITTING_STAFF','DOCTOR']) as $role){
    foreach(['CSSR','ER','WARD_2F',''] as $department){
        $expected=$department==='CSSR'||in_array($role,$allowed,true);
        $canAccessProcurement=canAccessProcurementWorkspace(['role'=>$role,'department'=>$department]);
        checkProcurement($canAccessProcurement===$expected,"Workspace access: $role/$department");
        foreach([false,true] as $canAccessCssr){
            ob_start();eval('?>'.$template);$html=ob_get_clean();
            checkProcurement(str_contains($html,'<strong>Procurement</strong>')===$expected,"Menu visibility: $role/$department");
            checkProcurement(str_contains($html,'href="/Nursing_System/PROCUREMENT/"')===$expected,"Clickable workspace: $role/$department");
        }
    }
}
checkProcurement(!canAccessProcurementWorkspace([]),'Missing identity fails closed');
checkProcurement(canAccessProcurementWorkspace(['role'=>'staff_nurse','department_name'=>' cssr ']),'CSSR identity from inventory session');

$source=file_get_contents(__DIR__.'/../../PROCUREMENT/includes/bootstrap.php');
$guard=substr($source,strpos($source,'require_login();'),strpos($source,'function can_approve_ppmp(')-strpos($source,'require_login();'));
foreach([['STAFF_NURSE','ER',false],['NURSING_ATTENDANT','WARD_2F',false],['DOCTOR','ER',false],['CSSR_STAFF','CSSR',true],['HEAD_NURSE','ER',true],['SUPERVISOR_NURSE','',true],['SUPER_ADMIN','',true]] as [$role,$department,$expected]){
    $child='require '.var_export(realpath(__DIR__.'/../../includes/procurement-access.php'),true).';'
        .'function require_login(){} function current_user(){return '.var_export(['role'=>$role,'department_name'=>$department],true).';}'
        .'register_shutdown_function(function(){echo "|HTTP:".http_response_code();});'
        .$guard.'echo "ALLOWED";';
    $process=proc_open([PHP_BINARY,'-r',$child],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $output=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    checkProcurement(proc_close($process)===0&&$errors==='','Guard runs without errors');
    checkProcurement(str_contains($output,'ALLOWED')===$expected,"Direct access: $role/$department");
    if(!$expected)checkProcurement(str_contains($output,'|HTTP:403'),'Direct access denied with HTTP 403');
}
echo "Passed $checks Procurement visibility and direct-access checks without database writes.\n";
