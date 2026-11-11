<?php
// Exercise date policy and production form/save expressions without authentication or database writes.
require_once __DIR__.'/../../includes/cssr-access.php';
$checks=0;$identity=[];
function check($ok,$message){global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
function current_user(){return $GLOBALS['identity'];}
function e($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
$now=new DateTimeImmutable('2026-10-08T16:30:00Z');
check(cssrTransactionToday($now)==='2026-10-09','Philippine date advances at local midnight');
$cases=[
    ['SUPER_ADMIN','',true],['CSSR_STAFF','',true],['CSSR_HEAD','CSSR',true],
    ['STAFF_NURSE','CSSR',true],['NURSING_ATTENDANT',' cssr ',true],
    ['NURSE','ER',false],['STAFF_NURSE','WARD_2F',false],['NURSING_ATTENDANT','ER',false],
    ['MIDWIFE','WARD_3F',false],['HEAD_NURSE','ER',false],['CHIEF_NURSE','NSO',false],['SUPERVISOR_NURSE','NSO',false],
];
foreach($cases as [$role,$department,$editable]){
    $identity=['role'=>$role,'department_name'=>$department];
    check(cssrCanEditTransactionDate($identity)===$editable,'Date permission '.$role.' '.$department);
    foreach(['2025-01-01','2030-12-31'] as $submitted){
        check(cssrTransactionDate($identity,$submitted,$now)===($editable?$submitted:'2026-10-09'),'Submitted date is restricted by identity');
    }
    foreach(['','2026-02-30','2026-13-01','not-a-date',null,[],"2026-10-08\0"] as $invalid){
        try{
            $actual=cssrTransactionDate($identity,$invalid,$now);
            check(!$editable&&$actual==='2026-10-09','Department date comes only from server');
        }catch(InvalidArgumentException $e){check($editable,'Invalid editable date rejected');}
    }
    foreach(['item_requests'=>'request_date','storage_requests'=>'request_date','department_returns'=>'return_date'] as $page=>$field){
        $source=file_get_contents(__DIR__.'/../pages/'.$page.'.php');
        preg_match('/\$(requestDate|returnDate) = cssrTransactionDate\([^;]+;/',$source,$assignment);
        check(!empty($assignment),'Production save uses date policy: '.$page);
        $_POST=[$field=>'2025-01-01'];$requestDate=$returnDate=null;
        eval($assignment[0]);
        check(${$assignment[1]}===($editable?'2025-01-01':cssrTransactionToday()),'Production save ignores forged date: '.$page);
        preg_match('/<input[^\r\n]*name="'.$field.'"[^\r\n]*?required>/',$source,$input);
        check(!empty($input),'Production date field found: '.$page);
        ob_start();eval('?>'.$input[0]);$html=ob_get_clean();
        check(str_contains($html,'readonly')===!$editable,'Production field lock: '.$page);
    }
}
check(cssrTransactionDate(['role'=>'SUPER_ADMIN'],'2028-02-29')==='2028-02-29','Valid leap day accepted');
echo "Passed $checks date authorization, form and tampered-submit checks without database writes.\n";
