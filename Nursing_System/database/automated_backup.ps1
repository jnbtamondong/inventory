$ErrorActionPreference='Stop'
$workspace=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$settingsFile=Join-Path $PSScriptRoot 'backup_settings.json'
$statusFile=Join-Path $PSScriptRoot 'backup_status.json'
$lock=$null
$status=@{started_at=(Get-Date -Format o);state='running';local_verified=$false;secondary_verified=$false;secondary_kind='not_configured'}
try {
    try{$lock=[IO.File]::Open((Join-Path $PSScriptRoot '.backup-running.lock'),[IO.FileMode]::OpenOrCreate,[IO.FileAccess]::ReadWrite,[IO.FileShare]::None)}catch{throw 'Another backup is running, or its lock is inaccessible.'}
    $settings=Get-Content -LiteralPath $settingsFile -Raw | ConvertFrom-Json
    $retentionDays=[Math]::Min(365,[Math]::Max(1,[int]$settings.retention_days))
    $status | ConvertTo-Json | Set-Content -LiteralPath $statusFile -Encoding UTF8
    $result=& 'C:/xampp/php/php.exe' (Join-Path $workspace 'tools/automatic-database-backup.php')
    if($LASTEXITCODE -ne 0){throw 'Database backup failed; earlier backups were retained.'}
    $run=$result | ConvertFrom-Json
    $backupRoot=(Resolve-Path (Join-Path $PSScriptRoot 'automatic_backups')).Path
    if($run.directory -ne $backupRoot){throw 'Unexpected backup directory'}
    & (Join-Path $workspace 'tools/backup-upload-files.ps1') -OutputDirectory $backupRoot -ArchiveName ('uploads_automatic_'+$run.timestamp+'.zip') -VerifyArchiveOnly
    $run | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath (Join-Path $backupRoot ('backup_set_'+$run.timestamp+'.json')) -Encoding UTF8
    $status.local_verified=$true;$status['timestamp']=$run.timestamp
    if($settings.secondary_copy -and $settings.secondary_copy.enabled){
        . (Join-Path $workspace 'tools/backup-copy.ps1')
        $status.secondary_kind=[string]$settings.secondary_copy.kind
        $copied=Copy-NursingBackupSet -SourceRoot $backupRoot -Destination $settings.secondary_copy.destination -Stamp $run.timestamp -RetentionDays $retentionDays
        $status.secondary_verified=$copied.verified;$status['secondary_destination']=$copied.destination
    }
    # Retain previous local backups if a configured secondary copy fails.
    $cutoff=(Get-Date).AddDays(-$retentionDays)
    foreach($file in Get-ChildItem -LiteralPath $backupRoot -File){
        if($file.Name -notmatch '^(?:(?:nursing_system|cssr_inventory)_automatic_\d{8}_\d{6}\.sql(?:\.manifest\.json)?|uploads_automatic_\d{8}_\d{6}\.zip(?:\.manifest\.json)?|backup_set_\d{8}_\d{6}\.json)$'){continue}
        if($file.LastWriteTime -ge $cutoff -or ($file.Attributes -band [IO.FileAttributes]::ReparsePoint)){continue}
        $resolved=(Resolve-Path -LiteralPath $file.FullName).Path
        if([IO.Path]::GetDirectoryName($resolved) -ne $backupRoot){throw 'Retention target escaped backup directory'}
        Remove-Item -LiteralPath $resolved
    }
    $status.state='success';$status['finished_at']=(Get-Date -Format o)
    $status | ConvertTo-Json -Depth 6 | Set-Content -LiteralPath $statusFile -Encoding UTF8
    Write-Output ('Verified backup set created: '+$run.timestamp)
    if($status.secondary_verified){Write-Output ('Verified '+$status.secondary_kind+' copy: '+$status.secondary_destination)}
    Write-Warning 'Twelve legacy CSSR backup tables are excluded from logical SQL exports; their preserved offline snapshot remains separate.'
}catch{
    if($lock){
        $status.state='failed';$status['finished_at']=(Get-Date -Format o);$status['error']=$_.Exception.Message
        $status | ConvertTo-Json -Depth 6 | Set-Content -LiteralPath $statusFile -Encoding UTF8
    }
    Write-Error $_ -ErrorAction Continue
    exit 1
}finally{
    if($lock){$lock.Dispose()}
}
