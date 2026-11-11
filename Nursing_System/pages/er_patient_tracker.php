<?php
require_once __DIR__.'/../config/db.php';require_once __DIR__.'/../includes/auth.php';require_once __DIR__.'/../includes/er-live-tracker.php';
requireLogin();requirePermission('patients','view','ER');
foreach([$_GET,$_POST] as $fields)foreach($fields as $value)if(!is_scalar($value)){http_response_code(400);exit('Invalid ER tracker request.');}
$ready=erTrackerReady($conn);$error='';$success=$_SESSION['er_tracker_flash']??'';unset($_SESSION['er_tracker_flash']);
if(!$ready){http_response_code(503);$error='ER live tracker setup is required. Contact the system administrator.';}
if($ready&&$_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $actor=(int)$_SESSION['user_id'];$action=(string)($_POST['action']??'');
        if($action==='open_public'){
            $token=erIssueDevice($conn,$actor,(string)($_POST['device_label']??''));
            $base=rtrim(str_replace('\\','/',dirname(dirname($_SERVER['SCRIPT_NAME']))),'/');
            setcookie('er_kiosk',$token,['expires'=>time()+43200,'path'=>$base.'/','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Strict']);
            header('Location: er_public_display.php',true,303);exit;
        }elseif($action==='retention'){
            $minutes=filter_var($_POST['minutes']??'',FILTER_VALIDATE_INT);
            if($minutes===false)throw new DomainException('Enter a whole number of minutes.');
            erConfigureRetention($conn,$minutes*60,$actor);
        }elseif($action==='revoke_device'){
            erRevokeDevice($conn,(int)($_POST['device_id']??0),$actor);
        }else{erTrackerAction($conn,$action,$_POST,$actor);}
        $_SESSION['er_tracker_flash']='ER tracker change saved.';header('Location: er_patient_tracker.php',true,303);exit;
    }catch(DomainException $e){$error=$e->getMessage();}
    catch(Throwable $e){error_log('ER tracker action failed; code '.(int)$e->getCode());$error='The change could not be saved. Refresh and retry.';}
}
$devices=[];$retention=10;
if($ready){$devices=erQuery($conn,'SELECT device_id,label,expires_at FROM er_display_devices WHERE revoked_at IS NULL AND expires_at>NOW() AND (issued_by=? OR ?=1) ORDER BY device_id DESC',[(int)$_SESSION['user_id'],isSuperAdmin()?1:0])->get_result()->fetch_all(MYSQLI_ASSOC);$retention=(int)erQuery($conn,'SELECT completed_seconds FROM er_display_settings WHERE settings_id=1')->get_result()->fetch_assoc()['completed_seconds']/60;}
?><!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ER Patient Tracker | NURSYNC</title><?=csrfMetaTag()?><link rel="stylesheet" href="../public/css/style.css"><link rel="stylesheet" href="../public/css/dashboard.css"><link rel="stylesheet" href="../public/css/module-theme.css?v=18"><link rel="stylesheet" href="../public/css/nis-clinical.css"><link rel="stylesheet" href="../public/css/er-staff-tracker.css"><script defer src="../public/js/nursync-ui.js?v=4"></script><?php if($ready):?><script defer src="../public/js/er-staff-tracker.js"></script><?php endif;?></head>
<body class="module-ui"><nav class="navbar"><div class="navbar-brand"><div class="navbar-title-stack"><h1>Joni Villanueva General Hospital</h1><span class="navbar-system-subtitle">Nursing Information System</span></div></div><div class="navbar-menu"><span class="user-info"><?=h($_SESSION['first_name']??'')?></span></div></nav><div class="dashboard-container"><?php require __DIR__.'/../includes/sidebar.php';?><main class="main-content er-staff-workspace"><header><h2>ER Patient Tracker</h2><p>Verified ER encounter progress and anonymous waiting-area display.</p><p id="er-staff-connection" role="status">Connecting to current ER information…</p></header>
<?php if($error):?><div class="alert alert-danger" role="alert"><?=h($error)?></div><?php endif;?><?php if($success):?><div class="alert alert-success" role="status"><?=h($success)?></div><?php endif;?>
<?php if($ready):?>
<?php if(erStaffAllowed('edit')):?><section class="form-section er-display-controls"><h3>Public Display</h3><form method="POST" target="_blank"><?=csrfField()?><input type="hidden" name="action" value="open_public"><label for="device-label">Display Device</label><input id="device-label" name="device_label" maxlength="80" required value="ER Waiting Area TV"><button class="btn btn-primary">Open Public Display</button></form><p>Authorizes this browser for 12 hours. The public board shows anonymous numbers and general ER stages only. Communicate tracking numbers privately.</p>
<?php if($devices):?><details><summary>Authorized display devices</summary><?php foreach($devices as $device):?><form method="POST"><?=csrfField()?><input type="hidden" name="action" value="revoke_device"><input type="hidden" name="device_id" value="<?=(int)$device['device_id']?>"><span><?=h($device['label'])?> · Expires <?=h($device['expires_at'])?></span><button class="btn btn-secondary">Revoke</button></form><?php endforeach;?></details><?php endif;?>
<?php if(isSuperAdmin()):?><details><summary>Completion display policy</summary><form method="POST"><?=csrfField()?><input type="hidden" name="action" value="retention"><label for="retention">Minutes to keep completed ER phases visible (0–120)</label><input id="retention" type="number" min="0" max="120" name="minutes" value="<?=(int)$retention?>" required><button class="btn btn-secondary">Save Display Duration</button></form><p>Hiding completed entries preserves their internal encounter and tracking history.</p></details><?php endif;?></section><?php endif;?>
<section class="form-section"><form method="GET" class="er-filter-form"><div><label for="er-search">Name, Hospital Number or Public Tracking Number</label><input id="er-search" name="q" maxlength="100" value="<?=h($_GET['q']??'')?>"></div><div><label for="er-stage">ER Care Stage</label><select id="er-stage" name="stage"><option value="">All stages</option><?php foreach(erStages() as $code=>$label):?><option value="<?=h($code)?>" <?=($_GET['stage']??'')===$code?'selected':''?>><?=h($label)?></option><?php endforeach;?></select></div><button class="btn btn-primary">Search / Filter</button><a class="btn btn-secondary" href="er_patient_tracker.php">Clear</a></form>
<p>Active ER phases and completions from the past 24 hours. Search by name or identifier for older tracking history.</p><div class="er-table-scroll"><table class="er-staff-table"><thead><tr><th>Patient / Hospital Number</th><th>Public Number</th><th>ER Status / Location</th><th>Arrival</th><th>Last Status Update / Time in Status</th><th>Authorized Actions</th></tr></thead><tbody id="er-staff-rows"><tr><td colspan="6">Loading ER encounters…</td></tr></tbody></table></div><nav class="er-pagination" id="er-pagination" aria-label="ER tracker pages"></nav></section>
<section class="form-section" id="er-tracker-history" hidden><h3>ER Status History</h3><p>Most recent 100 events. Clinical workflow, actor and effective timestamps are retained.</p><div class="er-table-scroll"><table><thead><tr><th>Care Stage</th><th>Clinical Code</th><th>Source / Reason</th><th>Actor</th><th>Effective Time</th></tr></thead><tbody id="er-history-rows"></tbody></table></div><a class="btn btn-secondary" href="movement_history.php">Authorized Transfers &amp; Handover</a></section>
<?php if(erStaffAllowed('edit')):?><section class="form-section"><h3>Existing ER Encounters Without a Public Number</h3><p>New and returning registrations receive a number automatically. For legacy encounters, verify the patient and public visibility before assigning a number. Up to 50 current encounters are listed.</p><div id="er-untracked">Loading…</div></section><?php endif;?>
<?php endif;?></main></div><script src="../public/js/sidebar.js"></script></body></html>
