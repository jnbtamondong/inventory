param([string]$OutputDirectory='', [string]$ArchiveName='uploaded-files.zip', [switch]$VerifyArchiveOnly)
$ErrorActionPreference='Stop'
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$workspace=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$reviewBase=(Resolve-Path (Join-Path $workspace 'storage/reviews')).Path
if($OutputDirectory){
    $recoveryRoot=(Resolve-Path -LiteralPath $OutputDirectory).Path
    if($recoveryRoot -ne (Join-Path $workspace 'database\automatic_backups')){throw 'Invalid automatic backup path'}
}else{
    $recoveryRoot=(Resolve-Path ((Get-Content (Join-Path $reviewBase 'recovery-current.txt') -Raw).Trim())).Path
    if(-not $recoveryRoot.StartsWith($reviewBase+'\recovery-',[StringComparison]::OrdinalIgnoreCase)){throw 'Invalid recovery path'}
}
if($ArchiveName -notmatch '^(uploaded-files|uploads_automatic_\d{8}_\d{6})\.zip$'){throw 'Invalid archive name'}
$archive=Join-Path $recoveryRoot $ArchiveName
if(Test-Path -LiteralPath $archive){throw 'Archive already exists; refusing overwrite'}
$entries=@{}
$zip=[IO.Compression.ZipFile]::Open($archive,[IO.Compression.ZipArchiveMode]::Create)
try {
    foreach($relative in @('CSSR_INVENTORY/uploads','public/uploads','storage/employee_documents')){
        $directory=Join-Path $workspace $relative
        if(-not (Test-Path -LiteralPath $directory)){continue}
        foreach($file in Get-ChildItem -LiteralPath $directory -File -Recurse -Force){
            if($file.Attributes -band [IO.FileAttributes]::ReparsePoint){throw 'Symbolic links require separate review'}
            $name=$file.FullName.Substring($workspace.Length+1).Replace('\','/')
            [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip,$file.FullName,$name,[IO.Compression.CompressionLevel]::Optimal) | Out-Null
            $entries[$name]=(Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256).Hash
        }
    }
} finally {$zip.Dispose()}
$restore=Join-Path $recoveryRoot 'restored-uploads'
if($VerifyArchiveOnly){
    $reader=[IO.Compression.ZipFile]::OpenRead($archive)
    try {
        foreach($name in $entries.Keys){
            $entry=$reader.GetEntry($name);if(-not $entry){throw 'Archived file missing'}
            $stream=$entry.Open();$sha=[Security.Cryptography.SHA256]::Create()
            try {$hash=[BitConverter]::ToString($sha.ComputeHash($stream)).Replace('-','')}finally{$stream.Dispose();$sha.Dispose()}
            if($hash -ne $entries[$name]){throw 'Archived file hash mismatch'}
        }
    }finally{$reader.Dispose()}
}else{
    if(Test-Path -LiteralPath $restore){throw 'Restore folder already exists'}
    [IO.Compression.ZipFile]::ExtractToDirectory($archive,$restore)
    foreach($name in $entries.Keys){
        $restored=[IO.Path]::GetFullPath((Join-Path $restore $name))
        if(-not $restored.StartsWith($restore+'\',[StringComparison]::OrdinalIgnoreCase)){throw 'Invalid restored path'}
        if((Get-FileHash -LiteralPath $restored -Algorithm SHA256).Hash -ne $entries[$name]){throw 'Restored file hash mismatch'}
    }
}
@{created_at=(Get-Date -Format o);sha256=(Get-FileHash -LiteralPath $archive -Algorithm SHA256).Hash;files=$entries;verified=$true;restored_and_verified=(-not $VerifyArchiveOnly)} | ConvertTo-Json -Depth 4 | Set-Content -LiteralPath ($archive+'.manifest.json') -Encoding UTF8
Write-Output ('Uploaded-file backup verified: '+$entries.Count+' files.')
