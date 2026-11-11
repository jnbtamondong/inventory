<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$file=__DIR__.'/../config/runtime-database.php';
if(is_file($file)){echo "Runtime database credentials already configured; no credentials replaced.\n";exit;}
$admin=new PDO('mysql:host=localhost;port=3306;charset=utf8mb4',getenv('NURSING_ADMIN_DB_USER')?:'root',getenv('NURSING_ADMIN_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if(realpath($admin->query('SELECT @@datadir')->fetchColumn())!==realpath('C:/xampp/mysql/data'))throw new RuntimeException('Unexpected MySQL instance.');
$settings=[];
foreach(['nursing_system'=>'nursing_web','cssr_inventory'=>'cssr_web'] as $database=>$user){
    $check=$admin->prepare('SELECT COUNT(*) FROM mysql.user WHERE User=?');$check->execute([$user]);
    if((int)$check->fetchColumn()>0)throw new RuntimeException('Service account already exists; refusing to replace it.');
    $password=bin2hex(random_bytes(32));$account=$admin->quote($user)."@'localhost'";
    $admin->exec('CREATE USER '.$account.' IDENTIFIED BY '.$admin->quote($password));
    // Legacy maintenance screens require schema privileges within their own DB.
    // No global administration, FILE, SHUTDOWN, or GRANT OPTION is granted.
    $admin->exec('GRANT ALL PRIVILEGES ON `'.$database.'`.* TO '.$account);
    $admin->exec('GRANT SELECT ON mysql.proc TO '.$account); // mysqldump --routines
    $candidate=new PDO('mysql:host=localhost;dbname='.$database.';charset=utf8mb4',$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $candidate->query('SELECT COUNT(*) FROM users')->fetchColumn();
    try {$candidate->query('SELECT User FROM mysql.user');throw new RuntimeException('Service account unexpectedly reads server credentials.');}
    catch(PDOException $denied){if(($denied->errorInfo[1]??0)!==1142)throw $denied;}
    $other=$database==='nursing_system'?'cssr_inventory':'nursing_system';
    try {$candidate->query('SELECT COUNT(*) FROM `'.$other.'`.users');throw new RuntimeException('Service account unexpectedly accesses the other database.');}
    catch(PDOException $denied){if(($denied->errorInfo[1]??0)!==1142)throw $denied;}
    $settings[$database]=['host'=>'localhost','port'=>3306,'database'=>$database,'user'=>$user,'password'=>$password];
}
$content="<?php\n// Local server credentials. Do not publish or commit.\nreturn ".var_export($settings,true).";\n";
if(file_put_contents($file.'.new',$content,LOCK_EX)===false||!rename($file.'.new',$file))throw new RuntimeException('Cannot activate runtime credentials.');
@chmod($file,0600);
$admin->prepare('INSERT INTO cssr_inventory.activity_logs(user_id,action,module,details) VALUES(NULL,?,?,?)')->execute(['SECURITY_MAINTENANCE','Database','Configured separate database-scoped nursing_web and cssr_web service accounts; no global administration grants. Hospital user roles unchanged.']);
echo "Configured and verified two database-scoped service accounts. Credentials saved privately; passwords were not printed.\n";
