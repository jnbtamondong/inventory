<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../includes/patient_helpers.php';require __DIR__.'/../includes/nis-workflow.php';require __DIR__.'/../includes/nis-movement-view.php';
function h($value):string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function csrfField():string{return '<input type="hidden" name="csrf_token" value="synthetic-only">';}
function hasPermission($module,$action,$department=null):bool{return true;}
$error='';$success='';$counts=['PENDING'=>1,'IN TRANSIT'=>1];$total=2;$page=1;$pages=1;$search='';$filterStatus='';$filterDepartment='';
$units=['ER','OPD','WARD_2F','WARD_3F','OR','LR_DR','ICU','NICU'];$statusOptions=['PENDING','ACCEPTED','REJECTED','IN TRANSIT','RECEIVED','CANCELLED'];
$base=['permanent_patient_id'=>'SYNTHETIC-HN-001','encounter_id'=>'SYNTHETIC-ENC-001','source_department'=>'ER','destination_department'=>'WARD_2F','movement_type'=>'ER TO WARD','created_at'=>'2026-10-26 08:00:00','reason'=>'Synthetic admission reason','remarks'=>'Synthetic handover only'];
$rows=[array_merge($base,['id'=>1,'patient_name'=>'Synthetic, Patient A','status'=>'PENDING']),array_merge($base,['id'=>2,'patient_name'=>'Synthetic, Patient B','status'=>'IN TRANSIT','permanent_patient_id'=>'SYNTHETIC-HN-002','encounter_id'=>'SYNTHETIC-ENC-002'])];
$beds=[['bed_id'=>1,'bed_number'=>'201-B1','bed_type'=>'General','room_number'=>'201']];
$patientChoices=[['patient_id'=>1,'patient_code'=>'SYNTHETIC-ENC-001','permanent_patient_id'=>'SYNTHETIC-HN-001','patient_name'=>'Synthetic, Patient A']];
?><!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><script src="/Nursing_System/public/js/app-theme.js"></script><link rel="stylesheet" href="/Nursing_System/public/css/nis-clinical.css"><script defer src="/Nursing_System/public/js/nis-clinical.js"></script><style>body{margin:0;padding:32px;background:#f4f7f8}html[data-theme=dark] body{background:#0c1d17}@media(max-width:600px){body{padding:12px}}</style></head><body><?php nisRenderMovementWorkspace(compact('error','success','counts','total','rows','beds','patientChoices','page','pages','search','filterStatus','filterDepartment','statusOptions','units')); ?></body></html>
