<?php
require_once __DIR__.'/../../includes/workflow-presentation.php';
$checks=0;
$assert=static function(bool $valid,string $message) use (&$checks):void {if(!$valid)throw new RuntimeException($message);$checks++;};
foreach(['Deleted','Rejected','Cancelled','unknown future status'] as $status){
    $data=nursingWorkflow('supply',$status);
    $assert($data['current']===null,'Closed/unknown states must not look like an active release.');
}
$legacy=nursingWorkflow('supply','Pending');$approval=nursingWorkflow('requisition','For Approval');
$assert(count($legacy['steps'])===3&&count($approval['steps'])===4,'Distinct real approval/release paths must be preserved.');
$assert(str_contains($approval['next'],'approves or rejects'),'Approval flow needs review guidance before release.');
$return=nursingWorkflow('return','Received');
$assert($return['steps'][$return['current']]==='Received','A received return must not be shown as an outgoing release.');
$revision=nursingWorkflow('ppmp','FOR REVISION');
$assert($revision['current']===0&&str_contains($revision['next'],'comments'),'Returned plans must direct the user to review comments and resubmit.');
$partial=nursingWorkflow('purchase','PARTIALLY DELIVERED');
$assert($partial['current']!==count($partial['steps'])-1,'Partial delivery must not appear complete.');
ob_start();nursingRenderWorkflow('requisition','Approved');$html=ob_get_clean();
$assert(substr_count($html,'aria-current="step"')===1,'Exactly one current stage is announced.');
$assert(!str_contains($html,'<button')&&!str_contains($html,'<form'),'Guidance must not introduce unguarded transaction actions.');
echo "Passed $checks workflow presentation checks without loading authentication or a database.\n";
