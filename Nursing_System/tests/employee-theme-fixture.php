<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function csrfField(){return '<input type="hidden" name="csrf_token" value="preview">';}
$canSeeSystemAlerts=$canVerify=$canMarkResigned=$canSelectEmployee=$canManageAll=false;
$role='NURSE';$view='employees';$userId=1;$error=$success='';
$employees=$compliance=$departments=$requirements=$categories=$pendingVerificationDocs=$types=$expiringTypes=[];
$_GET=['view'=>'employees'];$_SESSION=['first_name'=>'Preview','role'=>'NURSE'];
$source=file_get_contents(__DIR__.'/../pages/employee_documents.php');
$source=substr($source,strpos($source,'<!doctype html>'));
chdir(__DIR__);
eval('?>'.$source);
