<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
// Presentation-only snapshots of server-rendered markup. No application code,
// queries, permissions, or transactions execute in these fixtures.
$pages=['dashboard'=>'dashboard.php','reports'=>'reports.php','backup'=>'data_backup.php','settings'=>'account_settings.php'];
$page=$pages[$argv[1]]??null;if(!$page)exit(1);
$source=file_get_contents(__DIR__.'/../pages/'.$page);
$start=strripos($source,'<!doctype html>');
if($start===false)throw new RuntimeException('Template boundary missing: '.$page);
$source=substr($source,$start);
$source=str_replace("<?php include '../includes/sidebar.php'; ?>",file_get_contents(__DIR__.'/../includes/schedule_sidebar.php'),$source);
$source=preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i','',$source);
$source=preg_replace('/<\?(?:php|=)[\s\S]*?\?>/','',$source);
$source=str_replace('</body>','<script src="../public/js/sidebar.js"></script></body>',$source);
echo $source;
