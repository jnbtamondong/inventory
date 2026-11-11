<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/database-backup.php';
$config=nursingBackupConfig('cssr_inventory');
$db=new PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$config['database'].';charset=utf8mb4',$config['user'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$rows=$db->query('SELECT id,username,password FROM users')->fetchAll(PDO::FETCH_ASSOC);
$affected=array_values(array_filter($rows,static fn($r)=>password_verify('admin123',$r['password'])));
if(!in_array('--apply',$argv,true)){echo 'Legacy default-password records: '.count($affected).". Dry run only.\n";exit;}
// This local password is not used for shared hospital sign-in or admin verification.
// Preserve the local user ID, identity, permissions, history, and shared password.
$root=realpath(trim(file_get_contents(__DIR__.'/../storage/reviews/recovery-current.txt')));
$backup=$root.'/cssr_inventory.sql';
$manifest=json_decode(file_get_contents($backup.'.manifest.json'),true,512,JSON_THROW_ON_ERROR);
if(!hash_equals($manifest['sha256'],hash_file('sha256',$backup)))throw new RuntimeException('Verified backup required before retiring the legacy credential.');
$db->beginTransaction();
try {
    $count=0;
    foreach($affected as $row){
        $statement=$db->prepare('UPDATE users SET password=? WHERE id=? AND password=?');
        $statement->execute([password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT),$row['id'],$row['password']]);
        if($statement->rowCount()!==1)throw new RuntimeException('Account changed during review; update rolled back.');
        $db->prepare('INSERT INTO activity_logs(user_id,action,module,details) VALUES(NULL,?,?,?)')->execute([
            'SECURITY_MAINTENANCE','Authentication',json_encode(['record_id'=>(int)$row['id'],'change'=>'Retired unused local default-password hash','old'=>'documented default','new'=>'random unrecoverable credential','reason'=>'Shared hospital identity remains the sole authentication source; no shared password or permissions changed.'],JSON_THROW_ON_ERROR)]);
        $count++;
    }
    $db->commit();echo "Retired $count legacy local credentials and recorded audit entries. Shared hospital passwords and roles were not changed.\n";
} catch(Throwable $error){if($db->inTransaction())$db->rollBack();throw $error;}
