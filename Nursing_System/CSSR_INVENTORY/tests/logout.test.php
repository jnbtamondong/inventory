<?php
$helper=realpath(__DIR__.'/../../includes/logout-confirmation.php');
$checks=0;
foreach([
 ['GET', 'valid-token', false], ['HEAD','valid-token',false],
 ['POST','wrong-token',false], ['POST','valid-token',true], ['DELETE','valid-token',false],
] as [$method,$submitted,$allowed]) {
 $code='require '.var_export($helper,true).'; $_SERVER["REQUEST_METHOD"]='.var_export($method,true).'; $_POST["csrf_token"]='.var_export($submitted,true).'; nursingRequireLogoutPost("valid-token"); echo "LOGOUT_EXECUTED";';
 $process=proc_open([PHP_BINARY,'-r',$code],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
 fclose($pipes[0]);$output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
 if($exit!==0||$error!==''||str_contains($output,'LOGOUT_EXECUTED')!==$allowed)throw new RuntimeException('Logout guard failed: '.$method);
 if($method==='GET'&&(!str_contains($output,'method="post"')||!str_contains($output,'name="csrf_token"')))throw new RuntimeException('Missing confirmation form');
 $checks++;
}
echo "Passed $checks logout method/token checks without loading authentication or changing sessions.\n";
