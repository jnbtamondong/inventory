# Shared verified-copy and retention operations. Dot-sourcing performs no I/O.
function Get-NursingBackupFileNames([string]$Stamp) {
    if($Stamp -notmatch '^\d{8}_\d{6}$'){throw 'Invalid backup timestamp'}
    @("nursing_system_automatic_$Stamp.sql","nursing_system_automatic_$Stamp.sql.manifest.json",
      "cssr_inventory_automatic_$Stamp.sql","cssr_inventory_automatic_$Stamp.sql.manifest.json",
      "uploads_automatic_$Stamp.zip","uploads_automatic_$Stamp.zip.manifest.json","backup_set_$Stamp.json")
}
function Assert-NursingPlainDirectory([string]$Path) {
    $item=Get-Item -LiteralPath $Path -Force -ErrorAction Stop
    if(-not $item.PSIsContainer){throw 'Expected a backup directory'}
    for($node=$item;$null -ne $node;$node=$node.Parent){
        if($node.Attributes -band [IO.FileAttributes]::ReparsePoint){throw 'Backup directories cannot use junctions or symbolic links'}
    }
    return $item.FullName.TrimEnd('\')
}
function Copy-NursingBackupSet {
    param([string]$SourceRoot,[string]$Destination,[string]$Stamp,[int]$RetentionDays=30)
    $source=Assert-NursingPlainDirectory $SourceRoot
    if(-not [IO.Path]::IsPathRooted($Destination)){throw 'Backup destination must be an absolute folder path'}
    $destinationPath=[IO.Path]::GetFullPath($Destination).TrimEnd('\')
    if($destinationPath -eq $source -or $destinationPath.StartsWith($source+'\',[StringComparison]::OrdinalIgnoreCase)){throw 'Backup copy must be outside its source folder'}
    $names=@(Get-NursingBackupFileNames $Stamp)
    $hashes=@{}
    foreach($name in $names){
        $file=Get-Item -LiteralPath (Join-Path $source $name) -ErrorAction Stop
        if($file.PSIsContainer -or ($file.Attributes -band [IO.FileAttributes]::ReparsePoint)){throw 'Invalid backup source file'}
        $hashes[$name]=(Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256).Hash
    }
    foreach($name in @("nursing_system_automatic_$Stamp.sql","cssr_inventory_automatic_$Stamp.sql","uploads_automatic_$Stamp.zip")){
        $manifest=Get-Content -LiteralPath (Join-Path $source ($name+'.manifest.json')) -Raw | ConvertFrom-Json
        if($hashes[$name] -ne $manifest.sha256){throw 'Source backup checksum does not match its manifest'}
    }
    # An unavailable network share fails here; no local/remote retention occurs.
    if(-not(Test-Path -LiteralPath $destinationPath -PathType Container)){[void](New-Item -ItemType Directory -Path $destinationPath -ErrorAction Stop)}
    $destinationPath=Assert-NursingPlainDirectory $destinationPath
    $setPath=Join-Path $destinationPath $Stamp
    if(-not(Test-Path -LiteralPath $setPath)){[void](New-Item -ItemType Directory -Path $setPath -ErrorAction Stop)}
    $setPath=Assert-NursingPlainDirectory $setPath
    if([IO.Path]::GetDirectoryName($setPath) -ne $destinationPath){throw 'Backup set escaped destination'}
    $marker=Join-Path $setPath 'verified-copy.json'
    # A complete existing set is immutable. A failed partial copy may be retried.
    $complete=Test-Path -LiteralPath $marker
    foreach($name in $names){
        $target=Join-Path $setPath $name
        if(Test-Path -LiteralPath $target){
            $existing=Get-Item -LiteralPath $target -Force
            if($existing.PSIsContainer -or ($existing.Attributes -band [IO.FileAttributes]::ReparsePoint)){throw 'Invalid copy target'}
        }
        if(-not $complete){Copy-Item -LiteralPath (Join-Path $source $name) -Destination $target -Force -ErrorAction Stop}
        if((Get-FileHash -LiteralPath $target -Algorithm SHA256).Hash -ne $hashes[$name]){throw 'Copied backup checksum mismatch'}
    }
    if(-not $complete){
        @{format='nursing-verified-copy-v1';timestamp=$Stamp;verified_at=(Get-Date -Format o);files=$hashes} | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $marker -Encoding UTF8 -ErrorAction Stop
    }
    Remove-NursingOldCopies -Destination $destinationPath -RetentionDays $RetentionDays
    return @{destination=$setPath;files=$names.Count;verified=$true}
}
function Remove-NursingOldCopies {
    param([string]$Destination,[int]$RetentionDays)
    if($RetentionDays -lt 1 -or $RetentionDays -gt 365){throw 'Invalid retention period'}
    $root=Assert-NursingPlainDirectory $Destination
    $cutoff=(Get-Date).AddDays(-$RetentionDays)
    foreach($directory in Get-ChildItem -LiteralPath $root -Directory -Force){
        if($directory.Name -notmatch '^\d{8}_\d{6}$' -or ($directory.Attributes -band [IO.FileAttributes]::ReparsePoint)){continue}
        $created=[datetime]::MinValue
        if(-not [datetime]::TryParseExact($directory.Name,'yyyyMMdd_HHmmss',[Globalization.CultureInfo]::InvariantCulture,[Globalization.DateTimeStyles]::None,[ref]$created) -or $created -ge $cutoff){continue}
        $resolved=Assert-NursingPlainDirectory $directory.FullName
        if([IO.Path]::GetDirectoryName($resolved) -ne $root){throw 'Retention target escaped destination'}
        $marker=Join-Path $resolved 'verified-copy.json'
        if(-not(Test-Path -LiteralPath $marker -PathType Leaf)){continue}
        try{$record=Get-Content -LiteralPath $marker -Raw | ConvertFrom-Json -ErrorAction Stop}catch{continue}
        if($record.format -ne 'nursing-verified-copy-v1' -or $record.timestamp -ne $directory.Name){continue}
        $allowed=@(Get-NursingBackupFileNames $directory.Name)+@('verified-copy.json')
        $children=@(Get-ChildItem -LiteralPath $resolved -Force)
        if(@($children | Where-Object {$_.PSIsContainer -or ($_.Attributes -band [IO.FileAttributes]::ReparsePoint) -or $_.Name -notin $allowed}).Count){continue}
        foreach($child in $children){
            $target=(Resolve-Path -LiteralPath $child.FullName).Path
            if([IO.Path]::GetDirectoryName($target) -ne $resolved){throw 'Retention file escaped backup set'}
            Remove-Item -LiteralPath $target -ErrorAction Stop
        }
        # No recursive deletion: unexpected content prevents directory removal.
        Remove-Item -LiteralPath $resolved -ErrorAction Stop
    }
}
