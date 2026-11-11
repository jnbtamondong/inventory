$ErrorActionPreference='Stop'
$workspace=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$recordPath=Join-Path $workspace 'storage/reviews/backup-schedule-verification.json'
$record=@{started_at=(Get-Date -Format o);state='starting'}
try {
    & (Join-Path $PSScriptRoot 'configure-backup-schedule.ps1') -At '02:00' -RetentionDays 30
    $taskName='JVGH Nursing System Daily Backup'
    $started=Get-Date
    Start-ScheduledTask -TaskName $taskName
    $record.state='verifying';$record | ConvertTo-Json | Set-Content -LiteralPath $recordPath -Encoding UTF8
    $verified=$false
    for($i=0;$i -lt 60;$i++){
        Start-Sleep -Seconds 2
        $task=Get-ScheduledTask -TaskName $taskName
        $info=Get-ScheduledTaskInfo -TaskName $taskName
        if($info.LastRunTime -ge $started.AddSeconds(-1) -and $task.State -ne 'Running'){
            if($info.LastTaskResult -ne 0){throw ('Scheduled backup returned exit code '+$info.LastTaskResult+'. See database/backup_status.json.')}
            $status=Get-Content -LiteralPath (Join-Path $workspace 'database/backup_status.json') -Raw | ConvertFrom-Json
            if($status.state -ne 'success' -or -not $status.local_verified -or -not $status.secondary_verified){throw 'Scheduled task did not confirm both backup copies.'}
            $record.state='verified';$record['timestamp']=$status.timestamp;$record['run_as']=$task.Principal.UserId;$record['last_result']=$info.LastTaskResult;$record['next_run']=$info.NextRunTime.ToString('o');$record['secondary_destination']=$status.secondary_destination
            $verified=$true;break
        }
    }
    if(-not $verified){throw 'Verification timed out. Check the scheduled task and backup status before rerunning.'}
}catch{
    $record.state='failed';$record['error']=$_.Exception.Message
    Write-Error $_ -ErrorAction Continue
}finally{
    $record['finished_at']=(Get-Date -Format o)
    $record | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $recordPath -Encoding UTF8
}
if($record.state -ne 'verified'){exit 1}
