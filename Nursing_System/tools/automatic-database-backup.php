<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/database-backup.php';
date_default_timezone_set('Asia/Singapore');
$directory=__DIR__.'/../database/automatic_backups';
if(!is_dir($directory)&&!mkdir($directory,0750,true))throw new RuntimeException('Backup directory unavailable.');
$directory=realpath($directory);$stamp=date('Ymd_His');$results=[];
foreach(['nursing_system','cssr_inventory'] as $logical){
    $file=$directory.DIRECTORY_SEPARATOR.$logical.'_automatic_'.$stamp.'.sql';
    $results[$logical]=nursingCreateDatabaseBackup(nursingBackupConfig($logical),$file,nursingLegacyBackupExclusions($logical));
}
echo json_encode(['directory'=>$directory,'timestamp'=>$stamp,'databases'=>$results],JSON_THROW_ON_ERROR);
