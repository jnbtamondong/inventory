<?php
declare(strict_types=1);
require_once __DIR__.'/database-settings.php';

function nursingBackupConfig(string $database): array
{
    return nursingDatabaseSettings($database);
}

/** Existing orphan backup-table names; explicit exclusions are recorded beside the dump. */
function nursingLegacyBackupExclusions(string $database): array
{
    $legacy=$database==='cssr_inventory' ? [
        'bin_cards_backup_before_2026_stock','bin_cards_backup_before_metadata',
        'item_request_details_deleted_backup_20260730','item_requests_deleted_backup_20260730',
        'items_backup_20260730','items_backup_before_2026_stock','items_backup_before_lot_sync',
        'request_stock_out_logs_deleted_backup_20260730','stock_in_backup_before_2026_stock',
        'stock_in_backup_before_metadata','stock_out_bin_cards_deleted_backup_20260730',
        'stock_out_deleted_backup_20260730',
    ] : [];
    $damaged=require __DIR__.'/../config/backup-exclusions.php';
    return array_values(array_unique(array_merge($legacy,array_keys($damaged[$database]??[]))));
}

function nursingCreateDatabaseBackup(array $config, string $target, array $excluded=[]): array
{
    $binary=getenv('NURSING_MYSQLDUMP')?:'C:/xampp/mysql/bin/mysqldump.exe';
    if(!is_file($binary))throw new RuntimeException('Database backup utility is unavailable.');
    if(!preg_match('/^[A-Za-z0-9_]+$/D',$config['database']))throw new InvalidArgumentException('Invalid database name.');
    if(is_file($target))throw new RuntimeException('A backup with this name already exists. Please retry.');
    $dir=dirname($target);
    if(!is_dir($dir))throw new RuntimeException('Backup folder is unavailable.');
    $options=tempnam($dir,'.mysql-');$temporary=$target.'.partial';
    if($options===false)throw new RuntimeException('Cannot prepare the backup.');
    $quote=static fn($v)=>'"'.str_replace(['\\','"',"\r","\n"],['\\\\','\\"','\\r','\\n'],(string)$v).'"';
    try {
        $settings="[client]\n";
        foreach(['host','port','user','password'] as $name)$settings.=$name.'='.$quote($config[$name])."\n";
        if(file_put_contents($options,$settings,LOCK_EX)===false)throw new RuntimeException('Cannot prepare backup credentials.');
        @chmod($options,0600);
        $command=[$binary,'--defaults-extra-file='.$options,'--single-transaction','--quick','--hex-blob',
            '--routines','--triggers','--events','--default-character-set=utf8mb4'];
        foreach($excluded as $table){
            if(!preg_match('/^[A-Za-z0-9_]+$/D',$table))throw new InvalidArgumentException('Invalid excluded table.');
            $command[]='--ignore-table='.$config['database'].'.'.$table;
        }
        array_push($command,'--databases',$config['database']);
        $process=proc_open($command,[0=>['pipe','r'],1=>['file',$temporary,'wb'],2=>['pipe','w']],$pipes);
        if(!is_resource($process))throw new RuntimeException('Cannot start the database backup.');
        fclose($pipes[0]);$error=stream_get_contents($pipes[2]);fclose($pipes[2]);$exit=proc_close($process);
        clearstatcache(true,$temporary);
        if($exit!==0||!is_file($temporary)||filesize($temporary)<100){
            error_log('[DATABASE BACKUP] '.trim($error));
            throw new RuntimeException('Database backup failed. Details were logged; no completed backup was published.');
        }
        if(!rename($temporary,$target))throw new RuntimeException('Cannot finalize database backup.');
        @chmod($target,0600);
        $damage=require __DIR__.'/../config/backup-exclusions.php';
        $manifest=['database'=>$config['database'],'created_at'=>date(DATE_ATOM),'sha256'=>hash_file('sha256',$target),
            'bytes'=>filesize($target),'includes'=>['schema','data','views','triggers','routines','events'],
            'excluded_legacy_tables'=>array_values($excluded),'damaged_table_exclusions'=>$damage[$config['database']]??[],
            'complete_database_backup'=>count($excluded)===0,'uploads_included'=>false];
        if(file_put_contents($target.'.manifest.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX)===false)
            throw new RuntimeException('The SQL backup was saved, but its verification manifest could not be written.');
        return $manifest;
    } finally {
        if(is_file($options))unlink($options);
        if(is_file($temporary))unlink($temporary);
    }
}
