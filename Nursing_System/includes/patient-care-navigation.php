<?php
/** Links to existing workflows; permissions are filtered by the shared sidebar. */
function nursingDepartmentCareLinks(string $label): array {
    return match($label) {
        'Emergency Room'=>[
            ['label'=>'For Admission','icon'=>'fa-hospital-user','href'=>'admitted_patients.php?module=er','roles'=>admissionRoles(),'permission'=>['patients','view','ER']],
            ['label'=>'Transfer Requests & Handover','icon'=>'fa-right-left','href'=>'movement_history.php?department=ER','permission'=>['patient_movements','view','ER']],
        ],
        'Outpatient Department'=>[
            ['label'=>'Referral & Transfer Tracking','icon'=>'fa-right-left','href'=>'movement_history.php?department=OPD','permission'=>['patient_movements','view','OPD']],
        ],
        'OB/Pedia Ward','Medical/Surgery Ward'=>[
            ['label'=>'Receive Patient / Handover','icon'=>'fa-right-left','href'=>'movement_history.php?department='.($label==='OB/Pedia Ward'?'WARD_2F':'WARD_3F').'&status=ACCEPTED','permission'=>['patient_movements','view']],
        ],
        'MICU'=>[
            ['label'=>'Receive Patient / Handover','icon'=>'fa-right-left','href'=>'movement_history.php?department=ICU','permission'=>['patient_movements','view','ICU']],
        ],
        'NICU'=>[
            ['label'=>'Receive Patient / Handover','icon'=>'fa-right-left','href'=>'movement_history.php?department=NICU','permission'=>['patient_movements','view','NICU']],
        ],
        'Labor Room / Delivery Room'=>[
            ['label'=>'Maternal Disposition','icon'=>'fa-person-pregnant','href'=>'ldr_mother_disposition.php?module=ldr','permission'=>['ldr','view','LR_DR']],
            ['label'=>'Newborn Disposition','icon'=>'fa-baby','href'=>'ldr_baby_disposition.php?module=ldr','permission'=>['ldr','view','LR_DR']],
            ['label'=>'Maternal Transfer to Ward','icon'=>'fa-right-left','href'=>'ldr_transfer_mother_ward.php?module=ldr','permission'=>['ldr','view','LR_DR']],
            ['label'=>'Newborn Transfer to NICU','icon'=>'fa-baby-carriage','href'=>'ldr_transfer_baby_nicu.php?module=ldr','permission'=>['ldr','view','LR_DR']],
        ],
        'Operating Room'=>[
            ['label'=>'Transfer to Ward','icon'=>'fa-right-left','href'=>'or_transfer_ward.php?module=or','permission'=>['operating_room','view','OR']],
            ['label'=>'Transfer to MICU','icon'=>'fa-heart-pulse','href'=>'or_transfer_micu.php?module=or','permission'=>['operating_room','view','OR']],
        ],
        default=>[],
    };
}
