<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/database-backup.php';
$root=realpath(trim(file_get_contents(__DIR__.'/../storage/reviews/recovery-current.txt')));
$allowed=realpath(__DIR__.'/../storage/reviews');
if(!$root||!$allowed||!str_starts_with($root,$allowed.DIRECTORY_SEPARATOR.'recovery-'))throw new RuntimeException('Invalid isolated recovery path.');
$port=33317;
$credentialFile=$root.'/isolated-password.txt';
$password=is_file($credentialFile)?file_get_contents($credentialFile):'';
$stage=new PDO('mysql:host=127.0.0.1;port='.$port.';charset=utf8mb4','root',$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$actual=realpath((string)$stage->query('SELECT @@datadir')->fetchColumn());
if($actual!==realpath($root.'/data')||(int)$stage->query('SELECT @@port')->fetchColumn()!==$port)throw new RuntimeException('Refusing restore: instance does not match isolated directory and port.');
if($password===''){
    $password=bin2hex(random_bytes(24));
    $stage->exec('SET PASSWORD = PASSWORD('.$stage->quote($password).')');
    file_put_contents($credentialFile,$password,LOCK_EX);@chmod($credentialFile,0600);
}
// Events restored from the source must never run in the recovery instance.
$stage->exec('SET GLOBAL event_scheduler=OFF');
$result=['created_at'=>date(DATE_ATOM),'isolated_port'=>$port,'databases'=>[]];
$automaticStamp=null;
if(in_array('--latest-automatic',$argv,true)){
    $sets=glob(__DIR__.'/../database/automatic_backups/backup_set_*.json');rsort($sets,SORT_STRING);
    if(!$sets)throw new RuntimeException('No completed automatic backup set found.');
    $set=json_decode(preg_replace('/^\xEF\xBB\xBF/','',file_get_contents($sets[0])),true,512,JSON_THROW_ON_ERROR);
    $automaticStamp=$set['timestamp'];
    if(!preg_match('/^\d{8}_\d{6}$/D',$automaticStamp))throw new RuntimeException('Invalid backup set timestamp.');
}
foreach(['nursing_system','cssr_inventory'] as $logical){
    $config=nursingBackupConfig($logical);
    if($config['port']===$port)throw new RuntimeException('Source must not be the isolated recovery instance.');
    $dump=$automaticStamp?__DIR__.'/../database/automatic_backups/'.$logical.'_automatic_'.$automaticStamp.'.sql':$root.'/'.$logical.'.sql';
    $excluded=nursingLegacyBackupExclusions($logical);
    if(!is_file($dump))$manifest=nursingCreateDatabaseBackup($config,$dump,$excluded);
    else {$manifest=json_decode(file_get_contents($dump.'.manifest.json'),true,512,JSON_THROW_ON_ERROR);if(!hash_equals($manifest['sha256'],hash_file('sha256',$dump)))throw new RuntimeException('Backup checksum mismatch.');}
    $clientOptions=$root.'/restore-client.cnf';
    file_put_contents($clientOptions,"[client]\nhost=127.0.0.1\nport=$port\nuser=root\npassword=$password\n",LOCK_EX);@chmod($clientOptions,0600);
    try {
        $process=proc_open(['C:/xampp/mysql/bin/mysql.exe','--defaults-file='.$clientOptions,'--binary-mode'],[0=>['file',$dump,'rb'],1=>['file',$root.'/restore-output.log','ab'],2=>['pipe','w']],$pipes);
        if(!is_resource($process))throw new RuntimeException('Restore client unavailable.');
        $error=stream_get_contents($pipes[2]);fclose($pipes[2]);$code=proc_close($process);
        if($code!==0){file_put_contents($root.'/restore-error.log',$error);throw new RuntimeException('Isolated restore failed; see private restore-error.log.');}
    } finally {if(is_file($clientOptions))unlink($clientOptions);}
    $source=new PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$config['database'].';charset=utf8mb4',$config['user'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $source->exec('SET TRANSACTION READ ONLY');$source->beginTransaction();
    $tables=[];$omitted=[];
    try {
        foreach($source->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM) as [$table,$type]){
            if(in_array($table,$excluded,true)){$omitted[]=$table;continue;}
            $quoted='`'.str_replace('`','``',$table).'`';$schema='`'.str_replace('`','``',$config['database']).'`';
            $originalCount=(int)$source->query('SELECT COUNT(*) FROM '.$quoted)->fetchColumn();
            $restoredCount=(int)$stage->query('SELECT COUNT(*) FROM '.$schema.'.'.$quoted)->fetchColumn();
            if($originalCount!==$restoredCount)throw new RuntimeException('Row count mismatch for '.$logical.'.'.$table.'. Source may have changed since backup.');
            // Compare order-independent row digests, without writing row contents to logs.
            $digest=static function(PDOStatement $rows):string{$hashes=[];while($row=$rows->fetch(PDO::FETCH_NUM))$hashes[]=hash('sha256',serialize($row));sort($hashes,SORT_STRING);return hash('sha256',implode('',$hashes));};
            if($digest($source->query('SELECT * FROM '.$quoted))!==$digest($stage->query('SELECT * FROM '.$schema.'.'.$quoted)))throw new RuntimeException('Content mismatch for '.$logical.'.'.$table);
            $tables[]=['table'=>$table,'type'=>$type,'rows'=>$originalCount,'content_matches'=>true];
        }
    } finally {$source->rollBack();}
    $result['databases'][$logical]=['sha256'=>$manifest['sha256'],'tables'=>$tables,'excluded_legacy_tables'=>$omitted];
    file_put_contents($root.'/verification'.($automaticStamp?'-'.$automaticStamp:'').'.json',json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX);
    echo $logical.': restored and verified '.count($tables).' tables/views; '.count($omitted)." legacy table exclusions.\n";
}
echo "Isolated schema/data restore and row-content comparison passed. Live databases were not restored or changed.\n";
