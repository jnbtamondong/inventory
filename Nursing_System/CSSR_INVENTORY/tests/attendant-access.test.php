<?php
// Isolate the real permission functions without bootstrapping sessions, schemas or live databases.
require_once __DIR__.'/../../includes/cssr-access.php';
require_once __DIR__.'/../includes/design-system.php';
require_once __DIR__.'/../includes/design-navigation.php';
const ROLE_ADMIN = 'SUPER_ADMIN';
$identity=[];$checks=0;
function current_user(): array { return $GLOBALS['identity']; }
function shared_value($sql,$params) { return $GLOBALS['databasePermission'] ?? 0; }
function isLoggedIn() { return !empty($_SESSION['user_id']); }
function isDepartmentAllowed($department) { return $department===null||$department===($_SESSION['department']??''); }
function isSuperAdmin() { return ($_SESSION['role']??'')==='SUPER_ADMIN'; }
function check($ok,$message) { global $checks; if(!$ok)throw new RuntimeException($message);$checks++; }
function loadFunctions($path,$start,$end) {
    $source=file_get_contents($path);$from=strpos($source,$start);$to=strpos($source,$end,$from);
    if($from===false||$to===false)throw new RuntimeException('Permission function boundary missing');
    eval(substr($source,$from,$to-$from));
}
loadFunctions(__DIR__.'/../includes/auth.php','function is_cssr_inventory_admin(', 'function cssr_landing_page(');
loadFunctions(__DIR__.'/../../includes/auth.php','function hasPermission(', 'function requirePermission(');
$actions=['view','create','edit','delete','approve','print'];
foreach(['NURSING_ATTENDANT','NURSE','STAFF_NURSE','MIDWIFE','HEAD_NURSE'] as $role){
    foreach(['CSSR','ER','WARD_2F',''] as $department){
        $identity=['role'=>$role==='HEAD_NURSE'?$role:'STAFF_NURSE','original_role'=>$role,'department_name'=>$department];
        $_SESSION=['user_id'=>7,'role'=>$identity['role'],'original_role'=>$role,'department'=>$department];
        $eligible=$role==='NURSING_ATTENDANT'&&$department==='CSSR';
        check(is_cssr_inventory_admin()===$eligible,'Exact role and department admin gate');
        foreach($actions as $action){
            foreach(['cssr_dashboard','cssr_inventory','ris_requests','reports','audit_trail'] as $module){
                check(shared_has_permission($module,$action)===($eligible&&$module!=='audit_trail'),'Local capability '.$role.' '.$department.' '.$module.'.'.$action);
                $globalExpected=$eligible&&in_array($module,['cssr_dashboard','cssr_inventory','ris_requests'],true);
                check(hasPermission($module,$action)===$globalExpected,'Shared capability stays scoped: '.$module);
            }
            foreach(['user_management','procurement','ppmp','purchase_requests','backup','nursing','patients','*'] as $module){
                check(!shared_has_permission($module,$action)&&!hasPermission($module,$action),'No unrelated elevation: '.$module);
            }
        }
        check($_SESSION['role']===$identity['role'],'Hospital account role unchanged');
    }
}
$identity=['role'=>'STAFF_NURSE','original_role'=>'NURSING_ATTENDANT','department_name'=>'CSSR'];
$_SESSION=['user_id'=>7,'role'=>'STAFF_NURSE','original_role'=>'NURSING_ATTENDANT','department'=>'CSSR'];
$nav=cssrDashboardNavigation(false,'shared_has_permission');$modules=array_column($nav,null,'id');
foreach(['dashboard','inventory','inbound','storage','requisitions','reports','stock-levels','bin-card','report-tools','history','settings'] as $id)check(isset($modules[$id]),'CSSR administrator menu: '.$id);
check(!in_array('backup',array_column($modules['settings']['children'],'id'),true),'Database maintenance remains restricted');
check(!hasPermission('cssr_inventory','view','ER'),'Department scope remains enforced');
check(!cssrAttendantPermission($identity,'cssr_inventory','unknown',true),'Unknown actions fail closed');
$_SESSION=[];check(!hasPermission('cssr_inventory','view'),'Anonymous requests denied');
$identity=['role'=>'SUPER_ADMIN'];check(is_cssr_inventory_admin()&&shared_has_permission('user_management','view'),'Existing system administrator preserved');

// Even an existing database grant must not expose User Activity to a non-admin.
$databasePermission=1;
foreach(['SUPER_ADMIN','CSSR_STAFF','CSSR_HEAD','CHIEF_NURSE','ASSISTANT_CHIEF_NURSE','SUPERVISOR_NURSE','HEAD_NURSE','STAFF_NURSE','NURSING_ATTENDANT','MIDWIFE','ADMITTING_STAFF'] as $role){
    $identity=['role'=>$role==='NURSING_ATTENDANT'?'STAFF_NURSE':$role,'original_role'=>$role,'department_name'=>'CSSR'];
    foreach($actions as $action)check(shared_has_permission('audit_trail',$action)===($role==='SUPER_ADMIN'),'User Activity administrator-only: '.$role.' '.$action);
    foreach([true,false] as $requests){
        $nav=cssrDashboardNavigation(false,static fn($module,$action)=>$module!=='ris_requests'||$requests,$role,'CSSR');
        $links=[];foreach($nav as $entry)foreach($entry['children']??[$entry] as $child)$links[]=$child['id'];
        check(in_array('audit',$links,true)===($role==='SUPER_ADMIN'),'User Activity menu and fallback administrator-only: '.$role);
    }
}
loadFunctions(__DIR__.'/../includes/auth.php','function cssr_page_permission_map(', 'function enforce_current_page_permission(');
check(cssr_page_permission_map()['activity_logs.php']===['audit_trail','view'],'Direct activity URL uses the restricted permission');
check(!cssrAttendantPermission(['role'=>'NURSING_ATTENDANT','department'=>'CSSR'],'audit_trail','view',true),'CSSR attendant capability excludes User Activity');
echo "Passed $checks scoped attendant permission checks without database writes.\n";
