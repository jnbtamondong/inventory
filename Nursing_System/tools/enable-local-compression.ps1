$ErrorActionPreference='Stop'
$apache='C:\xampp\apache\bin\httpd.exe'
$config='C:\xampp\apache\conf\httpd.conf'
$workspace=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$backup=Join-Path $workspace ('storage/reviews/httpd-before-compression-'+(Get-Date -Format 'yyyyMMdd-HHmmss')+'.conf')
$original=[IO.File]::ReadAllText($config)
$updated=$original.Replace('#LoadModule deflate_module modules/mod_deflate.so','LoadModule deflate_module modules/mod_deflate.so').Replace('#LoadModule filter_module modules/mod_filter.so','LoadModule filter_module modules/mod_filter.so')
if($updated -eq $original){Write-Output 'Compression modules are already configured.';exit}
Copy-Item -LiteralPath $config -Destination $backup
[IO.File]::WriteAllText($config,$updated,(New-Object Text.UTF8Encoding($false)))
& $apache -t
if($LASTEXITCODE -ne 0){Copy-Item -LiteralPath $backup -Destination $config -Force;throw 'Apache syntax failed; original configuration restored.'}
$processes=@(Get-CimInstance Win32_Process -Filter "Name='httpd.exe'" | Where-Object {$_.ExecutablePath -eq $apache})
# XAMPP runs Apache in console mode. Stop only its verified executable instances.
$ids=@($processes.ProcessId)
foreach($process in ($processes | Sort-Object @{Expression={if($ids -contains $_.ParentProcessId){1}else{0}}})){
    Stop-Process -Id $process.ProcessId -ErrorAction SilentlyContinue
}
foreach($processId in $ids){Wait-Process -Id $processId -Timeout 10 -ErrorAction SilentlyContinue}
$started=Start-Process -FilePath $apache -WorkingDirectory C:\xampp\apache -WindowStyle Hidden -PassThru
if($started.WaitForExit(2500)){throw 'Apache exited after restart; inspect its error log.'}
Write-Output ('Compression enabled; Apache restarted. Original configuration: '+$backup)
