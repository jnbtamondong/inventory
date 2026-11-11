<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/patient-registration.php';
requireLogin();requirePermission('patients','create','ER');
foreach([$_GET,$_POST] as $fields)foreach($fields as $value)if(!is_scalar($value)){http_response_code(400);exit('Invalid registration request.');}
header('Cache-Control: no-store');header('Referrer-Policy: same-origin');
$error='';$matches=[];$duplicates=[];$source=null;$receipt=null;
$v=$_SERVER['REQUEST_METHOD']==='POST'?$_POST:[];
$ready=registrationReady($conn);
if(!$ready){http_response_code(503);$error='Patient registration setup is required. Contact the system administrator.';}
if($ready){
    if(isset($_GET['search'])) {
        try{$matches=registrationSearch($conn,$_GET);}catch(DomainException $e){$error=$e->getMessage();}
    }
    $sourceId=filter_var($v['source_patient_id']??$_GET['existing']??0,FILTER_VALIDATE_INT);
    if($sourceId){
        $source=registrationQuery($conn,'SELECT patient_id,permanent_patient_id,first_name,last_name,middle_name,date_of_birth,gender,civil_status FROM patients WHERE patient_id=?',[$sourceId])->get_result()->fetch_assoc();
        if(!$source)$error='Patient identification could not be found.';
    }
    if($_SERVER['REQUEST_METHOD']==='POST'){
        try{
            if(isset($v['correct_arrival'])){
                $stored=$_SESSION['registration_receipts'][(string)($_GET['receipt']??'')]??null;
                if(!$stored||$stored['user']!==(int)$_SESSION['user_id'])throw new DomainException('Open the saved registration before correcting arrival.');
                registrationCorrectArrival($conn,(int)$stored['result']['patient_id'],$v,(int)$_SESSION['user_id']);
                $saved=$stored['result'];
            }else{$saved=registrationSave($conn,$v,(int)$_SESSION['user_id']);}
            $receiptKey=bin2hex(random_bytes(16));
            $_SESSION['registration_receipts'][$receiptKey]=['user'=>(int)$_SESSION['user_id'],'result'=>$saved];
            header('Location: patient_registration.php?receipt='.$receiptKey);exit;
        }catch(DomainException $e){$error=$e->getMessage();}
        catch(Throwable $e){http_response_code(500);error_log('Registration failed; code '.(int)$e->getCode());$error='Registration could not be saved. Your entries are preserved. Please retry.';}
        if(!$source&&!isset($v['correct_arrival'])){try{$duplicates=registrationDuplicates($conn,registrationValidate($v));}catch(DomainException $e){/* Validation message above is authoritative. */}}
    }
    $receiptKey=(string)($_GET['receipt']??'');
    $stored=$_SESSION['registration_receipts'][$receiptKey]??null;
    if($stored&&$stored['user']===(int)$_SESSION['user_id']) $receipt=$stored['result'];
}
$openRegistration=$ready&&!$receipt&&($source||isset($_GET['existing'])||isset($_GET['new'])||$_SERVER['REQUEST_METHOD']==='POST');
$openSearch=$ready&&!$receipt&&!$openRegistration&&(isset($_GET['search'])||isset($_GET['search_form']));
$showForm=$ready&&!$receipt;
if($showForm&&empty($v['request_key'])){
    $v['request_key']=bin2hex(random_bytes(32));
    $_SESSION['registration_keys'][$v['request_key']]=time();
    if(count($_SESSION['registration_keys'])>30) array_shift($_SESSION['registration_keys']);
}
if($source){foreach(['first_name','last_name','middle_name','date_of_birth','gender','civil_status'] as $key)$v[$key]=$source[$key];}
$v['arrival_at']=$v['arrival_at']??date('Y-m-d\TH:i');
function regField(string $key,string $label,string $type='text',bool $required=false,int $max=100): void {
    global $v,$source;
    $readonly=$source&&in_array($key,['first_name','last_name','middle_name','date_of_birth'],true);
    echo '<div class="form-group"><label for="reg-'.h($key).'">'.h($label).($required?' *':'').'</label><input id="reg-'.h($key).'" name="'.h($key).'" type="'.h($type).'" value="'.h($v[$key]??'').'" maxlength="'.$max.'"'.($required?' required':'').($readonly?' readonly':'').'></div>';
}
function regMatches(array $rows): void {
    if(!$rows)return;
    echo '<div class="registration-matches" role="region" aria-label="Matching patient identification">';
    foreach($rows as $row) echo '<article><strong>'.h($row['last_name'].', '.$row['first_name'].' '.$row['middle_name']).'</strong><p>Hospital Number: '.h($row['permanent_patient_id']?:'Requires identity review').' · Born '.h($row['date_of_birth']).' · '.h($row['gender']).'</p><a class="btn btn-secondary" href="patient_registration.php?existing='.(int)$row['patient_id'].'">Select Existing Patient</a></article>';
    echo '</div>';
}
function regLocation(string $key,string $label,string $placeholder): void {
    global $v;
    $selected=(string)($v[$key]??'');
    echo '<div class="form-group"><label for="reg-'.h($key).'">'.h($label).'</label><select id="reg-'.h($key).'" name="'.h($key).'" data-selected="'.h($selected).'" aria-describedby="registration-address-status"><option value="">'.h($placeholder).'</option>';
    if($selected!=='')echo '<option value="'.h($selected).'" selected>'.h($selected).'</option>';
    echo '</select></div>';
}
?><!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Patient Registration | NURSYNC</title>
<link rel="stylesheet" href="../public/css/style.css"><link rel="stylesheet" href="../public/css/dashboard.css"><link rel="stylesheet" href="../public/css/module-theme.css?v=18"><link rel="stylesheet" href="../public/css/nis-clinical.css"><link rel="stylesheet" href="../public/css/patient-registration.css?v=2"><script defer src="../public/js/nursync-ui.js?v=4"></script><script defer src="../public/js/patient-registration.js?v=2"></script><script defer src="../public/js/registration-popups.js?v=1"></script><script defer src="../public/js/registration-address.js?v=1"></script></head>
<body class="module-ui"><nav class="navbar"><div class="navbar-brand"><div class="navbar-title-stack"><h1>Joni Villanueva General Hospital</h1><span class="navbar-system-subtitle">Nursing Information System</span></div></div><div class="navbar-menu"><span class="user-info"><?=h($_SESSION['first_name']??'')?></span></div></nav><div class="dashboard-container"><?php require __DIR__.'/../includes/sidebar.php';?><main class="main-content"><div class="registration-workspace">
<header><h2>Patient Registration</h2><p>Identify the patient, register the ER encounter, then proceed to triage.</p></header>
<?php if($error&&!$openRegistration&&!$openSearch):?><div class="alert alert-danger" role="alert"><?=h($error)?></div><?php endif;?>
<?php if($receipt):?><section class="form-section" role="status"><h3>Registration saved</h3><p>Hospital Number: <strong><?=h($receipt['permanent_patient_id'])?></strong></p><p>Encounter ID: <strong><?=h($receipt['patient_code'])?></strong></p><?php if(!empty($receipt['public_tracking_number'])):?><p>ER tracking number: <strong><?=h($receipt['public_tracking_number'])?></strong></p><p>Communicate this number privately to the patient or authorized companion.</p><?php endif;?><div class="registration-actions"><a class="btn btn-primary" href="triage.php?module=triage&amp;id=<?=(int)$receipt['patient_id']?>">Proceed to Triage</a><?php if(hasPermission('patients','print','ER')):?><a class="btn btn-secondary" href="er_record.php?id=<?=(int)$receipt['patient_id']?>">View / Print ER Record</a><?php endif;?><a class="btn btn-secondary" href="patient_registration.php">Register Another Patient</a><a class="btn btn-secondary" href="patients.php?module=er">ER Patients</a></div>
<?php if(hasPermission('patients','edit','ER')):?><details><summary>Correct recorded arrival</summary><form method="POST"><?=csrfField()?><input type="hidden" name="correct_arrival" value="1"><div class="registration-grid"><?php regField('arrival_at','Corrected Arrival','datetime-local',true);regField('correction_reason','Correction Reason','text',true,500);?></div><button class="btn btn-secondary">Save Arrival Correction</button></form></details><?php endif;?></section>
<?php elseif($ready):?><section class="form-section registration-start"><h3>Register a Patient</h3><p>Search for an existing patient first, or open a new patient registration.</p><div class="registration-actions"><a class="btn btn-secondary" href="patient_registration.php?search_form=1" data-registration-open="registration-search-modal">Search Existing Patient</a><a class="btn btn-primary" href="patient_registration.php?new=1" data-registration-open="registration-patient-modal">New Patient</a><a class="btn btn-secondary" href="patients.php?module=er">ER Patients</a></div></section>
<dialog id="registration-search-modal" class="registration-modal" aria-labelledby="registration-search-title" <?=$openSearch?'open data-registration-auto-open':''?>><div class="registration-modal-heading"><h2 id="registration-search-title" tabindex="-1">Search Existing Patient</h2><button type="button" class="btn btn-secondary" data-registration-close aria-label="Close patient search">Close</button></div><div class="registration-modal-body"><?php if($error&&$openSearch):?><div class="alert alert-danger" role="alert"><?=h($error)?></div><?php endif;?><section class="form-section"><p>Check identification before creating a new identity. Search results contain identification details only.</p><form method="GET"><input type="hidden" name="search" value="1"><div class="registration-grid">
<?php foreach(['permanent_patient_id'=>'Hospital Number','first_name'=>'First Name','middle_name'=>'Middle Name','last_name'=>'Last Name','date_of_birth'=>'Date of Birth'] as $key=>$label):?><div class="form-group"><label for="search-<?=h($key)?>"><?=h($label)?></label><input id="search-<?=h($key)?>" name="<?=h($key)?>" type="<?=$key==='date_of_birth'?'date':'text'?>" value="<?=h(is_scalar($_GET[$key]??'')?($_GET[$key]??''):'')?>" maxlength="100"></div><?php endforeach;?></div><div class="registration-actions"><button class="btn btn-primary">Search Patients</button><a class="btn btn-secondary" href="patient_registration.php?new=1" data-registration-open="registration-patient-modal">New Patient</a></div></form><?php regMatches($matches);?><?php if(isset($_GET['search'])&&!$matches):?><p role="status">No matching identification found. Check the search details or choose New Patient.</p><?php endif;?></section></div></dialog>
<?php if($showForm):?><dialog id="registration-patient-modal" class="registration-modal" aria-labelledby="registration-patient-title" <?=$openRegistration?'open data-registration-auto-open':''?>><div class="registration-modal-heading"><h2 id="registration-patient-title" tabindex="-1"><?=$source?'Register Existing Patient':'New Patient Registration'?></h2><button type="button" class="btn btn-secondary" data-registration-close aria-label="Close patient registration">Close</button></div><div class="registration-modal-body"><?php if($error&&$openRegistration):?><div class="alert alert-danger" role="alert"><?=h($error)?></div><?php endif;?><form method="POST" id="registration-form" class="form-section" autocomplete="off"><?=csrfField()?><input type="hidden" name="request_key" value="<?=h($v['request_key'])?>"><input type="hidden" name="source_patient_id" value="<?=(int)($source['patient_id']??0)?>">
<h3>2. <?=$source?'Existing Patient':'New Patient'?> — Patient Identification</h3><p>Hospital Number: <strong><?=h($source['permanent_patient_id']??'Assigned securely when saved')?></strong></p><div class="registration-grid">
<?php regField('last_name','Last Name','text',true,50);regField('first_name','First Name','text',true,50);regField('middle_name','Middle Name','text',false,50);if(!$source)regField('suffix','Name Extension / Suffix','text',false,20);regField('date_of_birth','Date of Birth','date',true);?>
<div class="form-group"><label for="reg-age">Age</label><input id="reg-age" readonly aria-live="polite"></div><div class="form-group"><label for="reg-gender">Sex *</label><select id="reg-gender" name="gender" required <?=$source?'disabled':''?>><option value="">Select sex</option><?php foreach(['Male','Female','Other'] as $sex):?><option <?=($v['gender']??'')===$sex?'selected':''?>><?=h($sex)?></option><?php endforeach;?></select><?php if($source):?><input type="hidden" name="gender" value="<?=h($source['gender'])?>"><?php endif;?></div>
<div class="form-group"><label for="reg-civil">Civil Status</label><select id="reg-civil" name="civil_status" <?=$source?'disabled':''?>><option value="">Not recorded</option><?php foreach(['Single','Married','Widowed','Separated','Divorced'] as $civil):?><option <?=($v['civil_status']??'')===$civil?'selected':''?>><?=h($civil)?></option><?php endforeach;?></select></div></div>
<?php if(!$source):?><h3>3. Contact Information</h3><p>Optional fields may be left blank. Contact numbers accept N/A. Select a province, then a city / municipality, then a barangay.</p><div class="registration-grid"><?php regField('house_no_street','House / Street');regLocation('province_name','Province','Select province');regLocation('municipality_name','City / Municipality','Select province first');regLocation('barangay_name','Barangay','Select city / municipality first');regField('phone','Contact Number','tel',false,20);regField('emergency_contact','Emergency Contact Person');regField('emergency_phone','Emergency Contact Number','tel',false,20);regField('relationship','Relationship to Patient','text',false,50);?></div><p id="registration-address-status" role="status" aria-live="polite"></p><button id="registration-address-retry" type="button" class="btn btn-secondary" hidden>Retry loading locations</button><h3>4. Additional Information</h3><div class="registration-grid"><?php regField('religion','Religion','text',false,50);?></div><?php else:?><p>The permanent identity is reused. This encounter form does not change existing demographics.</p><?php endif;?>
<h3>ER Encounter Details</h3><div class="registration-grid"><?php regField('arrival_at','Date and Time of Arrival','datetime-local',true);regField('referred_by','Referred By','text',false,150);?><div class="form-group"><label>Registered By</label><input readonly value="<?=h(trim(($_SESSION['first_name']??'').' '.($_SESSION['last_name']??'')))?>"></div><div class="form-group"><label>Initial Status</label><input readonly value="Triage — awaiting assessment"></div></div><div class="form-group"><label for="reg-complaint">Chief Complaint *</label><textarea id="reg-complaint" name="chief_complaint" required maxlength="2000"><?=h($v['chief_complaint']??'')?></textarea></div><div class="registration-actions"><?php foreach(['medicolegal'=>'Medicolegal','ip'=>'IP','coordinated'=>'Coordinated Patient'] as $flag=>$label):?><label><input type="checkbox" name="<?=h($flag)?>" value="1" <?=($v[$flag]??'')==='1'?'checked':''?>> <?=h($label)?></label><?php endforeach;?></div>
<p>Vital signs and clinical assessment are recorded in Triage. Registration does not assign a bed or admit the patient.</p>
<label><input type="checkbox" name="public_excluded" value="1" <?=($v['public_excluded']??'')==='1'?'checked':''?>> Exclude this encounter from the public ER board for privacy. Internal clinical tracking continues.</label>
<?php if($duplicates):?><section class="registration-duplicates"><h3>Review Possible Duplicate Identities</h3><p>Compare the identification below. Select an existing patient when it is the same person.</p><?php regMatches($duplicates);?><label><input type="checkbox" name="duplicate_verified" value="1" <?=($v['duplicate_verified']??'')==='1'?'checked':''?>> I verified that this is a separate individual.</label><div class="form-group"><label for="reg-review">Verification details / reason for separate identity</label><textarea id="reg-review" name="duplicate_reason" maxlength="500"><?=h($v['duplicate_reason']??'')?></textarea></div></section><?php endif;?>
<div class="registration-actions registration-save"><button class="btn btn-primary" type="submit">Save Registration</button><a class="btn btn-secondary" href="patients.php?module=er" data-registration-close>Cancel</a><span role="status" id="registration-saving"></span></div></form></div></dialog><?php endif;?><?php endif;?></div></main></div><script src="../public/js/sidebar.js"></script></body></html>
