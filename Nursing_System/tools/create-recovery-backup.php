<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/database-backup.php';
$root=realpath(trim(file_get_contents(__DIR__.'/../storage/reviews/recovery-current.txt')));
$base=realpath(__DIR__.'/../storage/reviews');
if(!$root||!str_starts_with($root,$base.DIRECTORY_SEPARATOR.'recovery-'))throw new RuntimeException('Invalid recovery directory.');
foreach(['nursing_system','cssr_inventory'] as $database){
    $target=$root.'/'.$database.'.sql';
    if(!is_file($target))nursingCreateDatabaseBackup(nursingBackupConfig($database),$target,nursingLegacyBackupExclusions($database));
    $manifest=json_decode(file_get_contents($target.'.manifest.json'),true,512,JSON_THROW_ON_ERROR);
    if(!hash_equals($manifest['sha256'],hash_file('sha256',$target)))throw new RuntimeException('Backup checksum mismatch.');
    echo $database.': SQL backup checksum verified; legacy exclusions recorded in manifest.'.PHP_EOL;
}
if(!class_exists('ZipArchive')){echo "Run tools/backup-upload-files.ps1 to archive and verify uploads (PHP ZIP is unavailable).\n";exit;}
$workspace=realpath(__DIR__.'/..');$archive=$root.'/uploaded-files.zip';$entries=[];
if(is_file($archive))throw new RuntimeException('Upload archive already exists; do not overwrite recovery evidence.');
$zip=new ZipArchive();if($zip->open($archive,ZipArchive::CREATE|ZipArchive::EXCL)!==true)throw new RuntimeException('Cannot create upload archive.');
try {
    foreach(['CSSR_INVENTORY/uploads','public/uploads','storage/employee_documents'] as $relative){
        $directory=$workspace.'/'.$relative;if(!is_dir($directory))continue;
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory,FilesystemIterator::SKIP_DOTS)) as $file){
            if(!$file->isFile()||$file->isLink())continue;
            $name=str_replace('\\','/',substr($file->getPathname(),strlen($workspace)+1));
            if(!$zip->addFile($file->getPathname(),$name))throw new RuntimeException('Cannot archive an uploaded file.');
            $entries[$name]=hash_file('sha256',$file->getPathname());
        }
    }
} finally {if(!$zip->close())throw new RuntimeException('Cannot finalize uploaded-file archive.');}
$verify=new ZipArchive();if($verify->open($archive)!==true)throw new RuntimeException('Cannot reopen archive.');
foreach($entries as $name=>$digest){$stream=$verify->getStream($name);if(!$stream)throw new RuntimeException('Missing archived file.');$hash=hash_init('sha256');hash_update_stream($hash,$stream);fclose($stream);if(!hash_equals($digest,hash_final($hash)))throw new RuntimeException('Archived content mismatch.');}
$verify->close();
file_put_contents($archive.'.manifest.json',json_encode(['created_at'=>date(DATE_ATOM),'sha256'=>hash_file('sha256',$archive),'files'=>$entries],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX);
echo 'Uploaded-file archive verified: '.count($entries).' files.'.PHP_EOL;
