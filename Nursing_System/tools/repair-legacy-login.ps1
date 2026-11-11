$ErrorActionPreference='Stop'
$workspace=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$root=(Get-Content (Join-Path $workspace 'storage/reviews/recovery-current.txt') -Raw).Trim()
$verified=Get-Content -LiteralPath ($root+'/clone-rebuild-verified.json') -Raw | ConvertFrom-Json
if(-not $verified.normal_mode -or $verified.rows -ne 0 -or $verified.check_table -ne 'OK'){throw 'Verified empty-table repair required'}
$php='C:/xampp/php/php.exe'
$flag=Join-Path $workspace 'storage/maintenance.flag'
if(Test-Path -LiteralPath $flag){throw 'A maintenance window is already active'}
function Stop-LocalDatabase {
    $listeners=@(Get-NetTCPConnection -State Listen -LocalPort 3306 -ErrorAction SilentlyContinue)
    if(-not $listeners.Count){return}
    $processIds=@($listeners.OwningProcess | Select-Object -Unique)
    & $php (Join-Path $PSScriptRoot 'mysql-maintenance.php') shutdown
    if($LASTEXITCODE -ne 0){throw 'Safe database shutdown refused'}
    foreach($processId in $processIds){Wait-Process -Id $processId -Timeout 25 -ErrorAction SilentlyContinue}
    if(Get-NetTCPConnection -State Listen -LocalPort 3306 -ErrorAction SilentlyContinue){throw 'MySQL has not stopped'}
}
function Start-LocalDatabase([bool]$recovery) {
    $arguments=@('--defaults-file=C:/xampp/mysql/bin/my.ini','--standalone','--console')
    if($recovery){$arguments+='--innodb-force-recovery=1'}
    $label=if($recovery){'repair'}else{'normal'}
    $process=Start-Process -FilePath C:/xampp/mysql/bin/mysqld.exe -ArgumentList $arguments -WorkingDirectory C:/xampp -WindowStyle Hidden -RedirectStandardOutput ($root+'/maintenance-'+$label+'-output.log') -RedirectStandardError ($root+'/maintenance-'+$label+'-error.log') -PassThru
    if($process.WaitForExit(4000)){throw ('MySQL startup failed: '+$label)}
}
$normal=$false
try {
    'Database maintenance' | Set-Content -LiteralPath $flag
    Stop-LocalDatabase
    Start-LocalDatabase $true
    & $php (Join-Path $PSScriptRoot 'rebuild-empty-legacy-login-table.php') --apply-live
    if($LASTEXITCODE -ne 0){throw 'Legacy table rebuild failed'}
    Stop-LocalDatabase
    Start-LocalDatabase $false
    & $php (Join-Path $PSScriptRoot 'mysql-maintenance.php') status
    if($LASTEXITCODE -ne 0){throw 'Normal database mode verification failed'}
    $normal=$true
    & $php (Join-Path $PSScriptRoot 'rebuild-empty-legacy-login-table.php') --apply-live --verify-only
    if($LASTEXITCODE -ne 0){throw 'Final table verification failed'}
} finally {
    if(-not $normal){
        try {Stop-LocalDatabase; Start-LocalDatabase $false; & $php (Join-Path $PSScriptRoot 'mysql-maintenance.php') status; $normal=($LASTEXITCODE -eq 0)} catch {Write-Warning 'MySQL requires operator attention; maintenance mode remains enabled.'}
    }
    if($normal -and (Test-Path -LiteralPath $flag)){Remove-Item -LiteralPath $flag}
}
Write-Output 'Verified empty legacy table repaired; MySQL normal mode restored; maintenance window closed.'
