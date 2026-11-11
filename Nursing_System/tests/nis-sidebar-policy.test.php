<?php
if(PHP_SAPI!=='cli')exit;
ob_start();require __DIR__.'/nis-sidebar-fixture.php';ob_end_clean();
$checks=0;
function sidebarCheck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo "PASS $label\n";}
foreach(['SUPER_ADMIN','CHIEF_NURSE'] as $role){
    $_SESSION=['user_id'=>1,'role'=>$role,'department'=>'NSO'];$_GET=[];
    foreach(['ER','OPD','WARD_2F','WARD_3F','OR','LR_DR','ICU','NICU'] as $unit){
        sidebarCheck(nursingSidebarCan(['href'=>'#','departments'=>[$unit]]),$role.' department visibility '.$unit);
    }
}
$_SESSION=['user_id'=>1,'role'=>'STAFF_NURSE','department'=>'ER'];$_GET=['module'=>'opd'];
sidebarCheck(nursingSidebarCan(['href'=>'#','departments'=>['ER']]),'assigned department stays visible when module query differs');
sidebarCheck(!nursingSidebarCan(['href'=>'#','departments'=>['OPD']]),'module query cannot expose unrelated department');
sidebarCheck(!nursingSidebarCan(['href'=>'#','departments'=>['ER'],'permission'=>['nursing','view','ER']]),'department match does not bypass module permission');
$_GET=[];
sidebarCheck(nursingSidebarActive(['href'=>'emergency_room_dashboard.php?module=er'],'emergency_room_dashboard.php'),'ER dashboard highlights without optional module query');
$_SESSION['department']='WARD_2F';
sidebarCheck(nursingSidebarActive(['href'=>'ward_dashboard.php?module=ob_pedia_ward'],'ward_dashboard.php'),'assigned ward highlights without optional module query');
echo "$checks sidebar policy checks passed.\n";
