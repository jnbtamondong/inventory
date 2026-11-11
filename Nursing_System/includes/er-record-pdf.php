<?php
require_once __DIR__.'/nis-tracker.php';
function erRecordCanAccess(mysqli $db,int $id): bool {
    if(!hasPermission('patients','view','ER')||!hasPermission('patients','print','ER'))return false;
    $rows=nisTrackerRows($db,'SELECT t.department '.nisTrackerPatientFrom().' WHERE t.patient_id=?',[(string)$id]);
    if(!$rows)return false;
    if($rows[0]['department']!==null)return nisTrackerCanOpenChart($db,$id);
    if(isSuperAdmin()||hasRole('CHIEF_NURSE'))return true;
    // A completed, known ER registration remains printable by its authorized ER staff.
    $exists=nisTrackerRows($db,"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='nis_registrations'");
    return $exists&&(bool)nisTrackerRows($db,'SELECT registration_id FROM nis_registrations WHERE patient_id=?',[(string)$id]);
}
function erRecordPdfHtml(string $html): string {
    $html=preg_replace('~<script\b[^>]*>.*?</script>~is','',$html);
    $html=preg_replace('~<link\b[^>]*>~i','',$html);
    foreach(['er_record_left.png','er_record_right.png'] as $file){
        $html=str_replace('../public/images/branding/'.$file,'data:image/png;base64,'.base64_encode(file_get_contents(__DIR__.'/../public/images/branding/'.$file)),$html);
    }
    return str_replace('<head>','<head><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src data:; style-src \'unsafe-inline\'">',$html);
}
function erRecordRemoveJob(string $directory,string $root): void {
    $path=realpath($directory);$base=realpath($root);
    if(!$path||!$base||($path!==$base&&!str_starts_with($path,$base.DIRECTORY_SEPARATOR)))return;
    foreach(new DirectoryIterator($path) as $entry){if($entry->isDot())continue;$file=$entry->getPathname();if($entry->isLink()||!$entry->isDir())@unlink($file);else erRecordRemoveJob($file,$root);}
    @rmdir($path);
}
function erRecordPdf(string $html): string {
    $configured=getenv('NURSING_PDF_BROWSER');
    $candidates=$configured?[$configured]:['C:/Program Files/Google/Chrome/Application/chrome.exe','C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe','C:/Program Files/Microsoft/Edge/Application/msedge.exe','/usr/bin/chromium','/usr/bin/google-chrome'];
    $browser=null;foreach($candidates as $candidate)if(is_file($candidate)){$browser=$candidate;break;}
    if(!$browser)throw new RuntimeException('A server PDF browser is unavailable.');
    $job=sys_get_temp_dir().DIRECTORY_SEPARATOR.'nis-er-pdf-'.bin2hex(random_bytes(12));
    if(!mkdir($job,0700))throw new RuntimeException('Cannot prepare PDF workspace.');
    $process=null;
    try{
        $input=$job.DIRECTORY_SEPARATOR.'record.html';$output=$job.DIRECTORY_SEPARATOR.'record.pdf';
        if(file_put_contents($input,erRecordPdfHtml($html))===false)throw new RuntimeException('Cannot prepare PDF form.');
        $url='file:///'.str_replace(['%2F','%3A'],['/',':'],rawurlencode(str_replace('\\','/',$input)));
        $args=[$browser,'--headless','--disable-gpu','--no-first-run','--no-default-browser-check','--disable-extensions','--disable-background-networking','--disable-sync','--no-pdf-header-footer','--user-data-dir='.$job.DIRECTORY_SEPARATOR.'profile','--print-to-pdf='.$output];
        // Only the disposable CLI test server may opt out of nested Windows
        // sandbox restrictions. Apache/production exports always retain it.
        if(PHP_SAPI==='cli-server'&&getenv('NIS_ER_PDF_TEST_NO_SANDBOX')==='1'&&getenv('NURSING_APP_ENV')!=='production'&&preg_match('/^nis_tracker_test_[a-f0-9]{10}$/D',getenv('NIS_TEST_DATABASE')?:''))$args[]='--no-sandbox';
        $args[]=$url;
        $process=proc_open($args,[0=>['file',PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null','r'],1=>['file',$job.DIRECTORY_SEPARATOR.'out.log','w'],2=>['file',$job.DIRECTORY_SEPARATOR.'error.log','w']],$pipes,$job,null,['bypass_shell'=>true,'create_no_window'=>true]);
        if(!is_resource($process))throw new RuntimeException('Cannot start PDF renderer.');
        $deadline=microtime(true)+25;
        $pdf='';
        do {
            clearstatcache(true,$output);
            $pdf=is_file($output)?file_get_contents($output):'';
            if(str_starts_with($pdf,'%PDF-')&&str_contains(substr($pdf,-1024),'%%EOF'))break;
            if(microtime(true)>$deadline)throw new RuntimeException('PDF rendering timed out.');
            usleep(100000);
        }while(true);
        proc_close($process);$process=null;
        if(!str_starts_with($pdf,'%PDF-'))throw new RuntimeException('PDF rendering failed.');
        return $pdf;
    }finally{if(is_resource($process)){proc_terminate($process);proc_close($process);}erRecordRemoveJob($job,$job);}
}
