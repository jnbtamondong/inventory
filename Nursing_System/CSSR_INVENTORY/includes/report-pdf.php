<?php
// Render the same HTML/CSS used by Print. No report data is sent to a service.
function cssrReportPdfHtml(array $data): string
{
    require_once __DIR__.'/design-system.php';
    require_once __DIR__.'/reports-view.php';
    $ui=['title'=>$data['title'],'home'=>'#','active'=>'reports',
        'navigation'=>[['id'=>'reports','label'=>'Reports','icon'=>'report','href'=>'#','single_link'=>true,
            'children'=>[['id'=>cssrReportNavigationActive($data['filters']['report'])]]]],
        'searchAction'=>'#','initials'=>'','user'=>'','role'=>'','settings'=>'#'];
    ob_start();
    try { cssrRenderReports($data,$ui,['print'=>true]);$html=ob_get_contents(); }
    finally { ob_end_clean(); }
    $html=preg_replace('~<script\b[^>]*>.*?</script>~is','',$html);
    $html=preg_replace_callback('~<link\b[^>]*href="([^"]+)"[^>]*>~i',static function($m){
        $name=basename(parse_url(html_entity_decode($m[1]),PHP_URL_PATH));
        if(!in_array($name,['theme.css','design-system.css','reports.css','mobile.css','motion.css'],true))return '';
        return '<style>'.file_get_contents(__DIR__.'/../assets/css/'.$name).'</style>';
    },$html);
    foreach(cssrReportLetterhead()['logos'] as $file){
        $html=str_replace('../assets/img/'.$file,'data:image/png;base64,'.base64_encode(file_get_contents(__DIR__.'/../assets/img/'.$file)),$html);
    }
    return str_replace('<head>','<head><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src data:; style-src \'unsafe-inline\'">',$html);
}
function cssrReportPdfBrowser(): string
{
    $configured=getenv('CSSR_PDF_BROWSER');
    $candidates=$configured?[$configured]:[
        'C:/Program Files/Google/Chrome/Application/chrome.exe',
        'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
        'C:/Program Files/Microsoft/Edge/Application/msedge.exe',
        '/usr/bin/chromium','/usr/bin/chromium-browser','/usr/bin/google-chrome',
    ];
    foreach($candidates as $file)if(is_file($file))return $file;
    throw new RuntimeException('PDF rendering requires Chrome or Edge. Configure CSSR_PDF_BROWSER on the server.');
}
function cssrReportRemovePdfJob(string $path,string $root): void
{
    // Only this randomly-created job directory may be removed; never follow links.
    $resolved=realpath($path);$root=realpath($root);
    if($resolved===false||$root===false||($resolved!==$root&&!str_starts_with($resolved,$root.DIRECTORY_SEPARATOR)))return;
    foreach(new DirectoryIterator($resolved) as $entry){
        if($entry->isDot())continue;$file=$entry->getPathname();
        if($entry->isLink()||!$entry->isDir())@unlink($file);
        else cssrReportRemovePdfJob($file,$root);
    }
    @rmdir($resolved);
}
function cssrReportPdf(array $data): string
{
    if($data['errors'])throw new InvalidArgumentException('Correct the report filters before exporting.');
    $browser=cssrReportPdfBrowser();
    $job=sys_get_temp_dir().DIRECTORY_SEPARATOR.'cssr-pdf-'.bin2hex(random_bytes(12));
    if(!mkdir($job,0700))throw new RuntimeException('Cannot create the PDF workspace.');
    $process=null;
    try {
        $input=$job.DIRECTORY_SEPARATOR.'report.html';$output=$job.DIRECTORY_SEPARATOR.'report.pdf';
        if(file_put_contents($input,cssrReportPdfHtml($data))===false)throw new RuntimeException('Cannot prepare the PDF report.');
        $url='file:///'.str_replace('%2F','/',rawurlencode(str_replace('\\','/',$input)));
        $url=str_replace('%3A',':',$url);
        $args=[$browser,'--headless','--disable-gpu','--no-first-run','--no-default-browser-check',
            '--disable-extensions','--disable-background-networking','--disable-sync',
            '--no-pdf-header-footer','--user-data-dir='.$job.DIRECTORY_SEPARATOR.'profile',
            '--print-to-pdf='.$output];
        // Explicit opt-in for restricted test environments only; keep the browser sandbox by default.
        if(getenv('CSSR_BROWSER_NO_SANDBOX')==='1')$args[]='--no-sandbox';
        $args[]=$url;
        $process=proc_open($args,[0=>['file',PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null','r'],
            1=>['file',$job.DIRECTORY_SEPARATOR.'stdout.log','w'],2=>['file',$job.DIRECTORY_SEPARATOR.'stderr.log','w']],
            $pipes,$job,null,['bypass_shell'=>true]);
        if(!is_resource($process))throw new RuntimeException('Cannot start the PDF renderer.');
        $deadline=microtime(true)+25;
        while(proc_get_status($process)['running']){
            if(microtime(true)>$deadline)throw new RuntimeException('The PDF renderer timed out.');
            usleep(100000);
        }
        proc_close($process);$process=null;
        $pdf=is_file($output)?file_get_contents($output):'';
        if(!str_starts_with($pdf,'%PDF-'))throw new RuntimeException('The PDF renderer did not produce a document.');
        return $pdf;
    } finally {
        if(is_resource($process)){proc_terminate($process);proc_close($process);}
        cssrReportRemovePdfJob($job,$job);
    }
}
