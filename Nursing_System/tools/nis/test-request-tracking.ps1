$ErrorActionPreference='Stop'
$project=(Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$testRoot=Join-Path $project 'outputs/nis-reengineering/test-db'
$output=Join-Path $project 'outputs/request-tracking-20261026'
if(Get-NetTCPConnection -LocalPort 33317 -State Listen -ErrorAction SilentlyContinue){throw 'Port 33317 is occupied. No running database will be changed.'}
if(!(Test-Path (Join-Path $testRoot 'my.ini'))){
 & C:/xampp/mysql/bin/mysql_install_db.exe "--datadir=$testRoot" '--port=33317' '--silent'
 if($LASTEXITCODE -ne 0){throw 'Isolated database initialization failed.'}
}
$process=Start-Process C:/xampp/mysql/bin/mysqld.exe -ArgumentList "--defaults-file=$testRoot/my.ini",'--bind-address=127.0.0.1','--port=33317','--console' -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $output 'test-db.stdout.log') -RedirectStandardError (Join-Path $output 'test-db.stderr.log')
$previousTestPort=$env:REQUEST_TEST_PORT
try{
 $ready=$false
 for($attempt=0;$attempt -lt 40;$attempt++){
  & C:/xampp/mysql/bin/mysqladmin.exe --host=127.0.0.1 --port=33317 --user=root ping 2>$null | Out-Null
  if($LASTEXITCODE -eq 0){$ready=$true;break}
  Start-Sleep -Milliseconds 250
 }
 if(!$ready){throw 'Isolated database did not start.'}
 $env:REQUEST_TEST_PORT='33317'
 foreach($test in @('request-tracking.test.php','nursing-scheduling-integration.test.php','schedule-storage.test.php','duty-exchange.test.php')){
  & C:/xampp/php/php.exe -d mysqli.default_port=33317 -d log_errors=1 -d "error_log=$output/test-php-errors.log" (Join-Path $project "tests/$test")
  if($LASTEXITCODE -ne 0){throw "Failed: $test"}
 }
 foreach($test in @('schedule-engine.test.cjs','schedule-policy-engine.test.cjs')){
  & node (Join-Path $project "tests/$test")
  if($LASTEXITCODE -ne 0){throw "Failed: $test"}
 }
}finally{
 $env:REQUEST_TEST_PORT=$previousTestPort
 if(!$process.HasExited){Stop-Process -Id $process.Id;$process.WaitForExit()}
}
