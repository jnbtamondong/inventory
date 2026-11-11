param([switch]$RegistrationOnly,[switch]$EROnly)
$ErrorActionPreference='Stop'
$project=(Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$testRoot=Join-Path $project 'outputs/nis-reengineering/test-db'
$mysqlRoot='C:/xampp/mysql/bin'
if(Get-NetTCPConnection -LocalPort 33317 -State Listen -ErrorAction SilentlyContinue){throw 'Port 33317 is in use. No existing server will be modified.'}
if(!(Test-Path (Join-Path $testRoot 'my.ini'))){
    & "$mysqlRoot/mysql_install_db.exe" "--datadir=$testRoot" '--port=33317' '--silent'
    if($LASTEXITCODE -ne 0){throw 'Could not initialize isolated test database.'}
}
$process=Start-Process -FilePath "$mysqlRoot/mysqld.exe" -ArgumentList "--defaults-file=$testRoot/my.ini",'--bind-address=127.0.0.1','--port=33317','--console' -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $project 'outputs/nis-reengineering/test-db.stdout.log') -RedirectStandardError (Join-Path $project 'outputs/nis-reengineering/test-db.stderr.log')
try {
    $ready=$false
    for($attempt=0;$attempt -lt 40;$attempt++){
        & "$mysqlRoot/mysqladmin.exe" '--host=127.0.0.1' '--port=33317' '--user=root' 'ping' 2>$null | Out-Null
        if($LASTEXITCODE -eq 0){$ready=$true;break}
        Start-Sleep -Milliseconds 250
    }
    if(!$ready){throw 'Isolated test server did not start.'}
    if(!$RegistrationOnly -and !$EROnly){
    & 'C:/xampp/php/php.exe' (Join-Path $project 'tests/nis-workflow.test.php')
    if($LASTEXITCODE -ne 0){throw 'NIS workflow tests failed.'}
    & 'C:/xampp/php/php.exe' (Join-Path $project 'tests/nis-trackers.test.php')
    if($LASTEXITCODE -ne 0){throw 'NIS tracker tests failed.'}
    }
    if(!$EROnly){
    & 'C:/xampp/php/php.exe' (Join-Path $project 'tests/nis-registration.test.php')
    if($LASTEXITCODE -ne 0){throw 'NIS registration tests failed.'}
    }
    if(!$RegistrationOnly){
        & 'C:/xampp/php/php.exe' (Join-Path $project 'tests/er-live-tracker.test.php')
        if($LASTEXITCODE -ne 0){throw 'ER live tracker tests failed.'}
    }
} finally {
    if(!$process.HasExited){Stop-Process -Id $process.Id; $process.WaitForExit()}
}
