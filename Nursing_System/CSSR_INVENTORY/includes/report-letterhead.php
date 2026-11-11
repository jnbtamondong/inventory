<?php
// Matches the approved Stock Card letterhead and document-control footer.
function cssrReportLetterhead(): array
{
    return [
        'lines'=>['Republic of the Philippines','DEPARTMENT OF HEALTH','Central Luzon Center for Health Development',
            'JONI VILLANUEVA GENERAL HOSPITAL','National Rd. Igulot, Bocaue, Bulacan',
            'Contact #: 0960 302 6389 E-mail: jvgh@centralluzon.doh.gov.ph'],
        'note'=>['This is a computer-generated form.','No stamping is needed.'],
        'code'=>'JVGH-CSSR-XX-XXX',
        'logos'=>['doh-logo.png','jvgh-seal.png'],
    ];
}
function cssrReportPrintLetterhead(string $assets='../assets',string $part='header'): void
{
    $h=cssrReportLetterhead();$e=static fn($v)=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    ?>
    <?php if($part==='header'): ?><header class="rp-print-header" aria-label="Hospital letterhead">
        <img src="<?=$e($assets.'/img/'.$h['logos'][0])?>" alt="Department of Health logo">
        <div><?php foreach($h['lines'] as $i=>$line): ?>
            <?php if(in_array($i,[1,3],true)): ?><strong><?=$e($line)?></strong><?php else: ?><span><?=$e($line)?></span><?php endif; ?>
        <?php endforeach; ?></div>
        <img src="<?=$e($assets.'/img/'.$h['logos'][1])?>" alt="Joni Villanueva General Hospital seal">
    </header><?php else: ?>
    <footer class="rp-print-footer">
        <div><?=$e($h['note'][0])?><br><?=$e($h['note'][1])?></div>
        <div class="rp-document-code"><strong><?=$e($h['code'])?></strong><table><tbody><tr><th>Rev Number</th><td></td></tr><tr><th>Eff Date</th><td></td></tr></tbody></table></div>
    </footer><?php endif; ?>
    <?php
}
function cssrReportPdfLogos(): array
{
    static $logos=null;if($logos!==null)return $logos;$logos=[];
    foreach(cssrReportLetterhead()['logos'] as $file){
        $source=imagecreatefrompng(__DIR__.'/../assets/img/'.$file);
        $scale=280/max(imagesx($source),imagesy($source));$w=(int)round(imagesx($source)*$scale);$h=(int)round(imagesy($source)*$scale);
        $canvas=imagecreatetruecolor($w,$h);imagefill($canvas,0,0,imagecolorallocate($canvas,255,255,255));
        imagecopyresampled($canvas,$source,0,0,0,0,$w,$h,imagesx($source),imagesy($source));
        ob_start();imagejpeg($canvas,null,94);$bytes=ob_get_clean();imagedestroy($canvas);imagedestroy($source);
        $logos[]=['width'=>$w,'height'=>$h,'data'=>$bytes];
    }
    return $logos;
}
