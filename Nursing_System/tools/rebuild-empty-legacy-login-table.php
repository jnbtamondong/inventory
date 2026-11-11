<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/database-backup.php';
$root=realpath(trim(file_get_contents(__DIR__.'/../storage/reviews/recovery-current.txt')));
$rows=json_decode(file_get_contents($root.'/recovered-login-attempts.json'),true,512,JSON_THROW_ON_ERROR);
if($rows!==[])throw new RuntimeException('Recovery contains rows; automatic empty-table rebuild refused.');
$original=$root.'/source-data-snapshot/cssr_inventory/login_attempts.ibd';
$bytes=file_get_contents($original);$indexes=0;
for($offset=0;$offset<strlen($bytes);$offset+=16384){
    $type=unpack('n',substr($bytes,$offset+24,2))[1];
    if($type===17855){$indexes++;if(unpack('n',substr($bytes,$offset+54,2))[1]!==0)throw new RuntimeException('Index records exist; manual recovery required.');}
}
if($indexes!==3)throw new RuntimeException('Unexpected original index layout.');
$ddl=file_get_contents($root.'/recovered-login-attempts-schema.sql');
if(!str_starts_with($ddl,'CREATE TABLE `login_attempts` ('))throw new RuntimeException('Unexpected recovered schema.');
$live=in_array('--apply-live',$argv,true);
$config=$live?nursingBackupConfig('cssr_inventory'):['host'=>'127.0.0.1','port'=>33318,'user'=>'root','password'=>'','database'=>'cssr_inventory'];
if($live){
    $verified=json_decode(file_get_contents($root.'/clone-rebuild-verified.json'),true,512,JSON_THROW_ON_ERROR);
    if(($verified['schema_sha256']??'')!==hash('sha256',$ddl)||empty($verified['normal_mode']))throw new RuntimeException('Verified normal-mode clone rebuild is required.');
    if(!in_array('--verify-only',$argv,true)&&!hash_equals(hash_file('sha256',$original),hash_file('sha256','C:/xampp/mysql/data/cssr_inventory/login_attempts.ibd')))throw new RuntimeException('Live table changed since offline backup; rebuild refused.');
}
$db=new PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$config['database'].';charset=utf8mb4',$config['user'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$expected=$live?realpath('C:/xampp/mysql/data'):realpath($root.'/repair-clone');
$mode=(int)$db->query('SELECT @@innodb_force_recovery')->fetchColumn();
if(realpath($db->query('SELECT @@datadir')->fetchColumn())!==$expected||!in_array($mode,[0,1],true))throw new RuntimeException('Unexpected target server or recovery mode.');
// Only this verified-empty, unused legacy cache is rebuilt. The source .ibd/.frm
// remain in the offline snapshot; no clinical, inventory, or shared audit tables change.
if(!in_array('--verify-only',$argv,true)){
    $db->exec('DROP TABLE `login_attempts`');
    $db->exec($ddl);
}
$rebuilt=$db->query('SHOW CREATE TABLE `login_attempts`')->fetch(PDO::FETCH_NUM)[1];
if($rebuilt!==$ddl||(int)$db->query('SELECT COUNT(*) FROM `login_attempts`')->fetchColumn()!==0)throw new RuntimeException('Rebuilt table verification failed.');
$check=$db->query('CHECK TABLE `login_attempts`')->fetchAll(PDO::FETCH_ASSOC);
if(!array_filter($check,static fn($r)=>$r['Msg_type']==='status'&&$r['Msg_text']==='OK'))throw new RuntimeException('Table health check failed.');
$evidence=['schema_sha256'=>hash('sha256',$ddl),'original_ibd_sha256'=>hash_file('sha256',$original),'rows'=>0,'normal_mode'=>$mode===0,'check_table'=>'OK','completed_at'=>date(DATE_ATOM)];
file_put_contents($root.'/'.($live?'live':'clone').'-rebuild-verified.json',json_encode($evidence,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
if($live)$db->prepare('INSERT INTO activity_logs(user_id,action,module,details) VALUES(NULL,?,?,?)')->execute(['SECURITY_MAINTENANCE','Database','Rebuilt verified-empty legacy login_attempts after isolated recovery; original schema preserved, original physical files retained, normal-mode CHECK TABLE OK.']);
echo ($live?'Live':'Clone')." legacy login-attempt table rebuilt with identical schema; 0 rows; CHECK TABLE OK.\n";
