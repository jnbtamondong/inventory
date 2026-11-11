<?php
declare(strict_types=1);
// Display-only guidance: no permissions, status transitions, or database access.
function nursingWorkflow(string $type, string $status): array
{
    $status=strtoupper(trim($status));
    if($type==='ppmp') {
        $steps=['Prepare plan','Approval','Approved plan'];
        $states=[
            'DRAFT'=>[0,'Complete the plan and submit it for approval.'],
            'FOR REVISION'=>[0,'Review the comments, update the plan, and resubmit it for approval.'],
            'FOR APPROVAL'=>[1,'The authorized approver reviews the submitted plan.'],
            'RESUBMITTED FOR APPROVAL'=>[1,'The authorized approver reviews the revised plan.'],
            'APPROVED'=>[2,'Use the approved plan for a Purchase Request, or open its linked Purchase Request.'],
        ];
    } elseif($type==='requisition') {
        $steps=['Submitted','CSSR review','Approved','Released'];
        $states=['PENDING'=>[1,'The CSSR team reviews the quantities and approves or rejects the request.'],
            'FOR APPROVAL'=>[1,'The CSSR team reviews the quantities and approves or rejects the request.'],
            'APPROVED'=>[2,'The CSSR team checks the approved lots and completes the release.'],
            'FOR RELEASE'=>[2,'The CSSR team completes the release of the approved quantities.'],
            'RELEASED'=>[3,'Release is recorded. Review the released quantities and the printable record.']];
    } elseif($type==='return') {
        $steps=['Return submitted','CSSR review','Received'];
        $states=['PENDING'=>[1,'The CSSR team checks the returned quantities and records receipt.'],
            'RECEIVED'=>[2,'Receipt is recorded. Review the received quantities and the printable record.']];
    } elseif($type==='purchase') {
        $steps=['Quotation','Delivery','Received'];
        $states=['AWAITING QUOTATION'=>[0,'Complete the supplier quotation and award details in Abstract of Canvass.'],
            'PENDING DELIVERY'=>[1,'Monitor the expected delivery and record received supplies.'],
            'PARTIALLY DELIVERED'=>[1,'Check the remaining quantities and record the next delivery.'],
            'PARTIAL DELIVERY'=>[1,'Check the remaining quantities and record the next delivery.'],
            'OVERDUE'=>[1,'Follow up on the overdue delivery and review the remaining quantities.'],
            'DELIVERED'=>[2,'Delivery is recorded. Review the delivery and receiving records.']];
    } else {
        $steps=['Submitted',$type==='storage'?'Storage review':'CSSR review','Released'];
        $review=$type==='storage'?'The CSSR team selects available storage lots and release quantities, then releases stock to CSSR.':'The CSSR team checks available lots and release quantities, then releases the supplies.';
        $states=['PENDING'=>[1,$review],'FOR APPROVAL'=>[1,$review],
            'APPROVED'=>[1,'The CSSR team completes the release of the approved quantities.'],
            'FOR RELEASE'=>[1,'The CSSR team completes the release of the approved quantities.'],
            'RELEASED'=>[2,'Release is recorded. Review the released quantities and the printable record.']];
    }
    $closed=['DELETED'=>'This request was deleted. No further processing is available.',
        'REJECTED'=>'This request was rejected. Review the recorded remarks.',
        'CANCELLED'=>'This request was cancelled. Review its history.',
        'CANCELED'=>'This request was cancelled. Review its history.'];
    [$current,$next]=$states[$status]??[null,$closed[$status]??'Review the current status and the actions available for your role.'];
    return ['steps'=>$steps,'current'=>$current,'next'=>$next,'status'=>$status];
}
function nursingRenderWorkflow(string $type,string $status): void
{
    $data=nursingWorkflow($type,$status);
    $escape=static fn(string $text):string=>htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    echo '<section class="ns-workflow no-print" aria-label="Request progress">';
    if($data['current']!==null){
        echo '<ol aria-label="Request stages">';
        foreach($data['steps'] as $index=>$step)echo '<li data-step="'.($index+1).'"'.($index===$data['current']?' aria-current="step"':'').'>'.$escape($step).'</li>';
        echo '</ol>';
    }
    echo '<p><strong>'.($data['current']===null?'Status note: ':'Next: ').'</strong>'.$escape($data['next']).'</p></section>';
}
