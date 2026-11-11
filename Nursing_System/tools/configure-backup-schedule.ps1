param([ValidatePattern('^(?:[01]\d|2[0-3]):[0-5]\d$')][string]$At='02:00', [ValidateRange(1,365)][int]$RetentionDays=30, [switch]$Disable)
$ErrorActionPreference='Stop'
$workspace=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$script=Join-Path $workspace 'database\automated_backup.ps1'
$settingsFile=Join-Path $workspace 'database\backup_settings.json'
$taskName='JVGH Nursing System Daily Backup'
$settings=@{}
if(Test-Path -LiteralPath $settingsFile){
    $existing=Get-Content -LiteralPath $settingsFile -Raw | ConvertFrom-Json
    foreach($property in $existing.PSObject.Properties){$settings[$property.Name]=$property.Value}
}
$task=Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
if($task -and -not(@($task.Actions | Where-Object {$_.Arguments -like ('*'+$script+'*')}).Count)){throw 'Refusing to replace an unrelated scheduled task'}
if($Disable){
    if($task){Disable-ScheduledTask -TaskName $taskName | Out-Null}
}else{
    if((Get-TimeZone).Id -ne 'Singapore Standard Time'){throw 'Configure the server timezone as Singapore Standard Time (UTC+08:00) before scheduling this backup.'}
    $action=New-ScheduledTaskAction -Execute "$env:SystemRoot\System32\WindowsPowerShell\v1.0\powershell.exe" -Argument ('-NoProfile -NonInteractive -ExecutionPolicy Bypass -File "'+$script+'"') -WorkingDirectory $workspace
    $trigger=New-ScheduledTaskTrigger -Daily -At $At
    $options=New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Hours 2) -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 15) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
    # Preserve an existing dedicated backup-service identity if the hospital adds one.
    $principal=if($task){$task.Principal}else{New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest}
    Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $options -Principal $principal -Description 'Daily verified Nursing and CSSR SQL/upload backups with a verified secondary copy; 02:00 UTC+08 by default.' -Force | Out-Null
}
$settings['enabled']=(-not $Disable);$settings['time']=$At;$settings['retention_days']=$RetentionDays;$settings['timezone']='Asia/Singapore'
[IO.File]::WriteAllText($settingsFile,($settings | ConvertTo-Json -Depth 6),(New-Object Text.UTF8Encoding($false)))
Write-Output $(if($Disable){'Daily backup disabled.'}else{'Daily backup scheduled at '+$At+' Asia/Singapore; retention '+$RetentionDays+' days.'})
