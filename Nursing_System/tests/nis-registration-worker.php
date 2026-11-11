<?php
if(PHP_SAPI!=='cli'||!preg_match('/^nis_tracker_test_[a-f0-9]{10}$/D',getenv('NIS_TEST_DATABASE')?:'')){exit(1);}
session_save_path(sys_get_temp_dir());$_SERVER['REQUEST_METHOD']='CLI';
require __DIR__.'/../config/db.php';require __DIR__.'/../includes/auth.php';require __DIR__.'/../includes/patient-registration.php';
$_SESSION=['user_id'=>1,'role'=>'STAFF_NURSE','department'=>'ER'];
$input=json_decode(base64_decode($argv[1]),true,512,JSON_THROW_ON_ERROR);$_SESSION['registration_keys'][$input['request_key']]=time();
echo json_encode(registrationSave($conn,$input,1),JSON_THROW_ON_ERROR);
