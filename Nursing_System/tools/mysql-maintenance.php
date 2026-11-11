<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/database-backup.php';
$c=nursingBackupConfig('cssr_inventory');
if($c['port']!==3306||!in_array($c['host'],['localhost','127.0.0.1'],true))throw new RuntimeException('This maintenance tool is restricted to local XAMPP.');
$db=new PDO('mysql:host='.$c['host'].';port='.$c['port'],getenv('NURSING_ADMIN_DB_USER')?:'root',getenv('NURSING_ADMIN_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if(realpath($db->query('SELECT @@datadir')->fetchColumn())!==realpath('C:/xampp/mysql/data'))throw new RuntimeException('Unexpected database directory.');
if(($argv[1]??'status')==='shutdown'){$db->exec('SHUTDOWN');echo "Local MySQL shutdown requested.\n";exit;}
$mode=(int)$db->query('SELECT @@innodb_force_recovery')->fetchColumn();
echo 'Local MySQL ready; recovery mode '.$mode.PHP_EOL;
exit($mode===0?0:3);
