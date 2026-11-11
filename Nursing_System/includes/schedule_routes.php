<?php
// Same-origin, allowlisted compatibility routes. No database writes.
function nsRequestRoute(array $query,bool $exchange=false): string {
    $view=is_string($query['view']??null)?$query['view']:'';
    $type=$exchange?'EXCHANGE':(($query['kind']??'OFF')==='LEAVE'?'LEAVE':'OFF');
    $id=filter_var($query['id']??null,FILTER_VALIDATE_INT);$id=$id&&$id>0?$id:null;
    if(!$exchange&&isset($query['document']))return 'request_application.php?'.http_build_query(['document'=>$query['document']]);
    if($view==='new'||(!$exchange&&($query['edit']??'')==='1'))return ($exchange?'exchange_application.php':'request_application.php').'?'.http_build_query(($exchange?['view'=>'new']:['kind'=>$type,'view'=>'new'])+($id?['id'=>$id]:[])+array_intersect_key($query,array_flip(['department','employee_id','leave_type'])));
    if($exchange&&$view==='configuration')return 'exchange_application.php?view=configuration';
    if(!$exchange&&($query['print']??'')==='1'&&$id)return 'request_application.php?'.http_build_query(['kind'=>$type,'id'=>$id,'print'=>'1']);
    $target=['type'=>$type];if($id)$target['id']=$id;
    elseif($view==='approvals')$target['view']='approvals';
    elseif($view==='history')$target['view']='history';
    elseif($view==='hr')$target['status']='APPROVED';
    elseif($view==='confirmation')$target['status']='PENDING';
    foreach(['status','month','from','to','department','employee_id'] as $key)if(isset($query[$key])&&is_scalar($query[$key]))$target[$key]=(string)$query[$key];
    if(isset($query['q'])||isset($query['search']))$target['q']=substr((string)($query['q']??$query['search']),0,40);
    return 'my_requests.php?'.http_build_query($target);
}
