<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=realpath(trim(file_get_contents(__DIR__.'/../storage/reviews/recovery-current.txt')));
$allowed=realpath(__DIR__.'/../storage/reviews');
if(!$root||!$allowed||!str_starts_with($root,$allowed.DIRECTORY_SEPARATOR.'recovery-'))throw new RuntimeException('Invalid recovery path.');
$db=new PDO('mysql:host=127.0.0.1;port=33317;dbname=nursing_system;charset=utf8mb4','root',file_get_contents($root.'/isolated-password.txt'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if((int)$db->query('SELECT @@port')->fetchColumn()!==33317||realpath($db->query('SELECT @@datadir')->fetchColumn())!==realpath($root.'/data'))throw new RuntimeException('Refusing to prepare credentials outside the isolated restore.');
$user=$db->query("SELECT user_id,username,password FROM users WHERE role='SUPER_ADMIN' AND is_active=1 AND status='ACTIVE' ORDER BY user_id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if(!$user)throw new RuntimeException('No restored administrator available for session verification.');
$undo=$root.'/auth-test-original.json';
if(($argv[1]??'')==='restore'){
    if(is_file($undo)){
        $original=json_decode(file_get_contents($undo),true,512,JSON_THROW_ON_ERROR);
        $db->prepare('UPDATE users SET password=? WHERE user_id=?')->execute([$original['password'],$original['user_id']]);
        unlink($undo);
    }
    exit;
}
if(is_file($undo))throw new RuntimeException('Restore the previous isolated test credential first.');
file_put_contents($undo,json_encode($user,JSON_THROW_ON_ERROR),LOCK_EX);
$password=bin2hex(random_bytes(24));
$db->prepare('UPDATE users SET password=? WHERE user_id=?')->execute([password_hash($password,PASSWORD_BCRYPT),$user['user_id']]);
// Consumed privately by the test process; never write this output to a review log.
echo json_encode(['username'=>$user['username'],'password'=>$password],JSON_THROW_ON_ERROR);
