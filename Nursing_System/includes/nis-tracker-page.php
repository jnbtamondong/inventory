<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/patient_helpers.php';
require_once __DIR__.'/nis-tracker.php';
require_once __DIR__.'/nis-tracker-view.php';
requireLogin();requirePermission($trackerType==='patients'?'patients':'beds','view');
$error='';
try{$data=$trackerType==='patients'?nisPatientTrackerData($conn,$_GET):nisBedTrackerData($conn,$_GET);}
catch(Throwable $e){
    http_response_code(503);error_log('NIS tracker read failed; code '.(int)$e->getCode());
    $error='Current information could not be loaded. Refresh to retry or contact support.';
    $data=['rows'=>[],'history'=>[],'notices'=>[],'total'=>0,'pages'=>1,'filter'=>nisTrackerFilter($_GET),'summary'=>[],'reservation_supported'=>false];
}
?><!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php echo $trackerType==='patients'?'Patient Tracker':'Bed Tracker'; ?> | NURSYNC</title><link rel="stylesheet" href="../public/css/style.css"><link rel="stylesheet" href="../public/css/dashboard.css"><link rel="stylesheet" href="../public/css/module-theme.css?v=18"><link rel="stylesheet" href="../public/css/nis-clinical.css?v=1"><script defer src="../public/js/nursync-ui.js?v=4"></script><script defer src="../public/js/nis-trackers.js?v=1"></script></head><body class="module-ui"><nav class="navbar"><div class="navbar-brand"><div class="navbar-title-stack"><h1>Joni Villanueva General Hospital</h1><span class="navbar-system-subtitle">Nursing Information System</span></div></div><div class="navbar-menu"><span class="user-info"><?php echo h($_SESSION['first_name']??$_SESSION['username']??''); ?></span></div></nav><div class="dashboard-container"><?php require __DIR__.'/sidebar.php'; ?><main class="main-content"><?php nisRenderTracker($data,$trackerType,$error); ?></main></div><script src="../public/js/sidebar.js"></script></body></html>
