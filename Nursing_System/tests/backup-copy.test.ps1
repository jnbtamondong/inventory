$ErrorActionPreference='Stop'
. (Join-Path $PSScriptRoot '../tools/backup-copy.ps1')
$root=Join-Path (Resolve-Path (Join-Path $PSScriptRoot '../storage/reviews')).Path ('backup-copy-test-'+[guid]::NewGuid().ToString('N'))
$source=Join-Path $root 'source';$destination=Join-Path $root 'copies'
[void](New-Item -ItemType Directory -Path $source -Force)
$stamp=Get-Date -Format 'yyyyMMdd_HHmmss';$checks=0
function Assert-Check($Value,[string]$Message){if(-not $Value){throw $Message};$script:checks++}
function Make-Set([string]$Id){
    foreach($file in @("nursing_system_automatic_$Id.sql","cssr_inventory_automatic_$Id.sql","uploads_automatic_$Id.zip")){
        Set-Content -LiteralPath (Join-Path $source $file) -Value ('synthetic '+$file)
        @{sha256=(Get-FileHash -LiteralPath (Join-Path $source $file) -Algorithm SHA256).Hash} | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $source ($file+'.manifest.json'))
    }
    @{timestamp=$Id} | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $source "backup_set_$Id.json")
}
Make-Set $stamp
Assert-Check (@(Get-NursingBackupFileNames $stamp).Count -eq 7) 'Expected seven complete-set files'
$result=Copy-NursingBackupSet -SourceRoot $source -Destination $destination -Stamp $stamp
Assert-Check ($result.verified -and $result.files -eq 7) 'Complete copy verification failed'
Assert-Check (Test-Path -LiteralPath (Join-Path $result.destination 'verified-copy.json')) 'No completion marker'
$repeat=Copy-NursingBackupSet -SourceRoot $source -Destination $destination -Stamp $stamp
Assert-Check $repeat.verified 'Idempotent verification failed'
Set-Content -LiteralPath (Join-Path $result.destination "nursing_system_automatic_$stamp.sql") -Value 'tampered'
$rejected=$false;try{Copy-NursingBackupSet -SourceRoot $source -Destination $destination -Stamp $stamp | Out-Null}catch{$rejected=$true}
Assert-Check $rejected 'Corrupt completed copy was silently overwritten'
$second=(Get-Date).AddMinutes(1).ToString('yyyyMMdd_HHmmss');Make-Set $second
Set-Content -LiteralPath (Join-Path $source "uploads_automatic_$second.zip") -Value 'tampered source'
$rejected=$false;try{Copy-NursingBackupSet -SourceRoot $source -Destination $destination -Stamp $second | Out-Null}catch{$rejected=$true}
Assert-Check $rejected 'Corrupt source was accepted'
Assert-Check (-not(Test-Path -LiteralPath (Join-Path $destination $second))) 'Failed source check wrote destination'
$old='20000101_000000';Make-Set $old
Copy-NursingBackupSet -SourceRoot $source -Destination $destination -Stamp $old -RetentionDays 365 | Out-Null
Assert-Check (-not(Test-Path -LiteralPath (Join-Path $destination $old))) 'Owned expired set not removed'
$foreign=Join-Path $destination '20000102_000000';[void](New-Item -ItemType Directory -Path $foreign)
Set-Content -LiteralPath (Join-Path $foreign 'hospital-notes.txt') -Value 'preserve'
Remove-NursingOldCopies -Destination $destination -RetentionDays 30
Assert-Check (Test-Path -LiteralPath (Join-Path $foreign 'hospital-notes.txt')) 'Unrelated folder was removed'
$rejected=$false;try{Copy-NursingBackupSet -SourceRoot $source -Destination $source -Stamp $stamp | Out-Null}catch{$rejected=$true}
Assert-Check $rejected 'Copy into source was accepted'
Write-Output ('Passed '+$checks+' backup copy/checksum/retention checks. Synthetic artifacts retained at '+$root)
