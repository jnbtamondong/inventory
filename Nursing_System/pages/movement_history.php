<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';
require_once '../includes/clinical_modules.php';
require_once '../includes/nis-movement-view.php';
requireLogin(); requirePermission('patient_movements','view');
ensureClinicalModuleSchema($conn);
$error='';$success='';consumeFlashMessages($error,$success);
$units=['ER','OPD','WARD_2F','WARD_3F','OR','LR_DR','ICU','NICU'];
$statusOptions=['PENDING','ACCEPTED','REJECTED','IN TRANSIT','RECEIVED','ARRIVED','COMPLETED','CANCELLED'];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    requireCsrfToken();
    try {
        if (isset($_POST['update_movement'])) {
            nisTransitionMovement($conn,(int)($_POST['movement_id']??0),sanitizeText($_POST['status']??'',30),sanitizeText($_POST['remarks']??'',2000),(int)($_POST['bed_id']??0));
            setFlashMessage('success','Movement updated. Patient and encounter identity preserved.');
        } elseif (isset($_POST['create_movement'])) {
            $source=sanitizeText($_POST['source_department']??'',60);$destination=sanitizeText($_POST['destination_department']??'',60);
            nisRequestMovement($conn,(int)($_POST['patient_id']??0),sanitizeText($_POST['encounter_id']??'',30),$source,$destination,$source.' TO '.$destination,sanitizeText($_POST['reason']??'',1000),sanitizeText($_POST['remarks']??'',2000));
            setFlashMessage('success','Transfer requested. The patient remains at the sending location until arrival is confirmed.');
        } else throw new DomainException('Choose a valid movement action.');
    } catch (DomainException $e) { setFlashMessage('error',$e->getMessage()); }
    catch (Throwable $e) { error_log('NIS movement operation failed; code '.(int)$e->getCode());setFlashMessage('error','The movement could not be saved. Refresh and retry. Contact support if the problem continues.'); }
    redirectAfterPost('movement_history.php');
}
$where='1=1';$params=[];$types='';
// Department filtering is mandatory even when a role has movement-view permission.
if (!isSuperAdmin() && !hasRole('CHIEF_NURSE')) {
    $department=nisDepartment(canonicalDepartment($_SESSION['department']??''));
    $aliases=$department==='ICU'?['ICU','MICU']:[$department];
    $marks=implode(',',array_fill(0,count($aliases),'?'));
    $where.=" AND (pm.source_department IN ($marks) OR pm.destination_department IN ($marks))";
    array_push($params,...$aliases,...$aliases);$types.=str_repeat('s',count($aliases)*2);
}
$from=' FROM patient_movements pm INNER JOIN patients p ON p.patient_id=pm.patient_id';
$counts=[];
foreach (clinicalRows($conn,"SELECT pm.status,COUNT(*) AS total $from WHERE $where GROUP BY pm.status",$params,$types) as $count) $counts[$count['status']]=(int)$count['total'];
$search=sanitizeText($_GET['q']??'',100);$filterStatus=sanitizeText($_GET['status']??'',30);
if (!in_array($filterStatus,$statusOptions,true)) $filterStatus='';
$filterDepartment=nisDepartment(sanitizeText($_GET['department']??($_GET['destination']??''),60));
if (!in_array($filterDepartment,$units,true)) $filterDepartment='';
if ($search!=='') {
    $where.=" AND (p.first_name LIKE ? ESCAPE '=' OR p.last_name LIKE ? ESCAPE '=' OR p.permanent_patient_id LIKE ? ESCAPE '=' OR pm.encounter_id LIKE ? ESCAPE '=')";
    $term='%'.strtr($search,['='=>'==','%'=>'=%','_'=>'=_']).'%';array_push($params,$term,$term,$term,$term);$types.='ssss';
}
if ($filterStatus!=='') {$where.=' AND pm.status=?';$params[]=$filterStatus;$types.='s';}
if ($filterDepartment!=='') {$where.=' AND (pm.source_department=? OR pm.destination_department=?)';array_push($params,$filterDepartment,$filterDepartment);$types.='ss';}
$total=clinicalScalar($conn,"SELECT COUNT(*) $from WHERE $where",$params,$types);
$pages=max(1,(int)ceil($total/25));$page=max(1,min($pages,(int)($_GET['page']??1)));$offset=($page-1)*25;
$rows=clinicalRows($conn,"SELECT pm.*,p.permanent_patient_id,CONCAT(p.last_name,', ',p.first_name) AS patient_name $from WHERE $where ORDER BY pm.created_at DESC,pm.id DESC LIMIT 25 OFFSET $offset",$params,$types);
$beds=clinicalRows($conn,"SELECT bed_id,bed_number,room_number,bed_type FROM beds WHERE status='available' AND patient_id IS NULL ORDER BY room_number,bed_number");
$patientScopes=[];
foreach ($units as $unit) {
    if (!hasPermission('patient_movements','edit',$unit)) continue;
    $patientScopes[]=match($unit) {
        'WARD_2F'=>"(p.status='admit_inpatient' AND p.ward_department IN ('OB','PEDIA','NEWBORN'))",
        'WARD_3F'=>"(p.status='admit_inpatient' AND p.ward_department IN ('MEDICAL','NON-TRAUMA SURGERY','TRAUMA SURGERY'))",
        'ER'=>"(p.status IN ('triage','waiting_for_consultation','consultation','treatment','waiting_results','ready_for_disposition','for_admission') AND (p.current_bed IS NULL OR b.bed_type='Emergency') AND NOT EXISTS(SELECT 1 FROM opd_records o WHERE o.patient_id=p.patient_id AND o.encounter_id=p.patient_code))",
        'ICU'=>"EXISTS(SELECT 1 FROM micu_records r WHERE r.patient_id=p.patient_id AND r.encounter_id=p.patient_code AND r.status='ACTIVE')",
        'NICU'=>"EXISTS(SELECT 1 FROM nicu_records r WHERE r.patient_id=p.patient_id AND r.encounter_id=p.patient_code AND r.status='ACTIVE')",
        'OR'=>"EXISTS(SELECT 1 FROM or_schedule r WHERE r.patient_id=p.patient_id AND r.encounter_id=p.patient_code AND r.status IN ('ACCEPTED','IN OR','RECOVERY'))",
        'LR_DR'=>"EXISTS(SELECT 1 FROM delivery_room_records r WHERE r.patient_id=p.patient_id AND r.encounter_id=p.patient_code)",
        'OPD'=>"EXISTS(SELECT 1 FROM opd_records r WHERE r.patient_id=p.patient_id AND r.encounter_id=p.patient_code AND r.status='ACTIVE')",
    };
}
$patientChoices=[];
if ($patientScopes) {
    $patientScope='('.implode(' OR ',$patientScopes).')';
    $patientChoices=clinicalRows($conn,"SELECT p.patient_id,p.patient_code,p.permanent_patient_id,CONCAT(p.last_name,', ',p.first_name) AS patient_name FROM patients p LEFT JOIN beds b ON b.bed_id=p.current_bed WHERE $patientScope AND (p.first_name LIKE ? ESCAPE '=' OR p.last_name LIKE ? ESCAPE '=' OR p.permanent_patient_id LIKE ? ESCAPE '=' OR p.patient_code LIKE ? ESCAPE '=') ORDER BY p.updated_at DESC LIMIT 50",array_fill(0,4,'%'.strtr($search,['='=>'==','%'=>'=%','_'=>'=_']).'%'),'ssss');
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Transfers & handover | NURSYNC</title>
<link rel="stylesheet" href="../public/css/style.css"><link rel="stylesheet" href="../public/css/dashboard.css"><link rel="stylesheet" href="../public/css/module-theme.css?v=18"><link rel="stylesheet" href="../public/css/nis-clinical.css?v=1"><script defer src="../public/js/nursync-ui.js?v=4"></script><script defer src="../public/js/nis-clinical.js?v=1"></script></head>
<body class="module-ui"><nav class="navbar"><div class="navbar-brand"><div class="navbar-title-stack"><h1>Joni Villanueva General Hospital</h1><span class="navbar-system-subtitle">Nursing Information System</span></div></div><div class="navbar-menu"><span class="user-info"><?php echo h($_SESSION['first_name']??$_SESSION['username']??''); ?></span></div></nav>
<div class="dashboard-container"><?php include '../includes/sidebar.php'; ?><main class="main-content"><?php nisRenderMovementWorkspace(compact('error','success','counts','total','rows','beds','patientChoices','page','pages','search','filterStatus','filterDepartment','statusOptions','units')); ?></main></div><script src="../public/js/sidebar.js"></script></body></html>
