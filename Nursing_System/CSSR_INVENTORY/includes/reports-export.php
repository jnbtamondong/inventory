<?php
require_once __DIR__.'/report-letterhead.php';
function cssrReportCell(string $key,$value): string
{
    if(in_array($key,['value','cost'],true))return number_format((float)$value,2);
    if(is_int($value)||is_float($value))return number_format($value);
    return (string)$value;
}
// Uncompressed ZIP keeps XLSX export independent of optional PHP extensions.
function cssrReportZip(array $files): string
{
    $body='';$directory='';$count=0;
    foreach($files as $name=>$contents){$offset=strlen($body);$size=strlen($contents);$crc=crc32($contents);$length=strlen($name);
        $body.=pack('VvvvvvVVVvv',0x04034b50,20,0,0,0,33,$crc,$size,$size,$length,0).$name.$contents;
        $directory.=pack('VvvvvvvVVVvvvvvVV',0x02014b50,20,20,0,0,0,33,$crc,$size,$size,$length,0,0,0,0,0,$offset).$name;$count++;}
    return $body.$directory.pack('VvvvvVVv',0x06054b50,0,0,$count,$count,strlen($directory),strlen($body),0);
}
function cssrReportXlsx(array $d): string
{
    $xml=static fn($s)=>htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u','',(string)$s),ENT_XML1|ENT_QUOTES,'UTF-8');
    $h=cssrReportLetterhead();
    // Excel limits a header to 255 characters, including formatting codes.
    $header='&L&G&C&8'.implode("\n",array_map(static fn($line,$i)=>in_array($i,[1,3],true)?'&B'.$line.'&B':$line,$h['lines'],array_keys($h['lines']))).'&R&G';
    $footer='&L&"Arial,Regular"&10'.implode("\n",$h['note']).'&C&"Arial,Regular"&9Page &P of &N&R&"Arial,Regular"&9'.$h['code']."\nRev Number: __________\nEff Date: __________";
    $sheet=static function(array $rows,int $columns)use($xml,$header,$footer,$d){$out='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheetPr><pageSetUpPr fitToPage="1"/></sheetPr><sheetViews><sheetView workbookViewId="0"/></sheetViews><sheetFormatPr defaultRowHeight="18"/><cols>';
        $weights=[];foreach(array_keys($d['columns']) as $key)$weights[]=$key==='status'?1.8:(in_array($key,['name','party','department'],true)?2.2:(in_array($key,['reference','code','date','location','type'],true)?1.5:1));
        foreach($weights as $i=>$weight)$out.='<col min="'.($i+1).'" max="'.($i+1).'" width="'.round(95*$weight/array_sum($weights),2).'" customWidth="1"/>';$out.='</cols><sheetData>';
        foreach($rows as $index=>$values){$out.='<row r="'.($index+1).'"'.($index===6?' ht="32" customHeight="1"':'').'>';foreach(array_values($values) as $value){if(is_int($value)||is_float($value))$out.='<c><v>'.$value.'</v></c>';else $out.='<c'.($index===6?' s="1"':'').' t="inlineStr"><is><t xml:space="preserve">'.$xml($value).'</t></is></c>';}$out.='</row>';}
        $merges='';$mergeCount=0;foreach($rows as $index=>$values)if(count($values)===1){$r=$index+1;$merges.='<mergeCell ref="A'.$r.':'.chr(64+$columns).$r.'"/>';$mergeCount++;}$out.='</sheetData><mergeCells count="'.$mergeCount.'">'.$merges;
        return $out.'</mergeCells><printOptions horizontalCentered="1"/><pageMargins left="0.47" right="0.47" top="1.35" bottom="0.85" header="0.2" footer="0.2"/><pageSetup paperSize="9" orientation="portrait" fitToWidth="1" fitToHeight="0"/><headerFooter scaleWithDoc="0" alignWithMargins="1"><oddHeader>'.$xml($header).'</oddHeader><oddFooter>'.$xml($footer).'</oddFooter></headerFooter><legacyDrawingHF r:id="rIdHeader"/></worksheet>';};
    $rows=array_map(static fn($line)=>[$line],$h['lines']);$rows[]=[$d['title']];
    $rows[]=[];$rows[]=array_values($d['columns']);$tableHeader=count($rows);foreach($d['rows'] as $r){$line=[];foreach($d['columns'] as $key=>$label)$line[]=$r[$key]??'';$rows[]=$line;}
    $printEnd=count($rows);
    // Keep the letterhead/footer visible in ordinary worksheet view too. The native
    // print header/footer repeats on each page; exclude these duplicate cells from print areas.
    foreach([$h['note'][0],$h['note'][1],$h['code'],'Rev Number:','Eff Date:'] as $line)$rows[]=[$line];
    $last=chr(64+count($d['columns']));$definitions='<definedName name="_xlnm.Print_Area" localSheetId="0">'.$xml("'Report'!\$A\$7:\$$last\$".$printEnd).'</definedName><definedName name="_xlnm.Print_Titles" localSheetId="0">'.$xml("'Report'!\$".$tableHeader.':$'.$tableHeader).'</definedName>';
    $headerRels='<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdHeader" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/vmlDrawing" Target="../drawings/header.vml"/></Relationships>';
    $vml='<xml xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office"><v:shapetype id="_x0000_t75" coordsize="21600,21600" o:spt="75" o:preferrelative="t" path="m0,0l21600,0,21600,21600,0,21600xe" filled="f" stroked="f"><v:stroke joinstyle="miter"/><v:path o:extrusionok="f" gradientshapeok="t" o:connecttype="rect"/><o:lock v:ext="edit" aspectratio="t"/></v:shapetype>';
    foreach(['LH','RH'] as $i=>$id){[$w,$height]=getimagesize(__DIR__.'/../assets/img/'.$h['logos'][$i]);$scale=54/max($w,$height);$vml.='<v:shape id="'.$id.'" o:spid="_x0000_s'.(1025+$i).'" type="#_x0000_t75" style="position:absolute;margin-left:0;margin-top:0;width:'.round($w*$scale,2).'pt;height:'.round($height*$scale,2).'pt;z-index:1"><v:imagedata o:relid="rId'.($i+1).'" o:title="Hospital logo"/><o:lock v:ext="edit" rotation="t"/></v:shape>';}$vml.='</xml>';
    return cssrReportZip([
        '[Content_Types].xml'=>'<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="png" ContentType="image/png"/><Default Extension="vml" ContentType="application/vnd.openxmlformats-officedocument.vmlDrawing"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>',
        '_rels/.rels'=>'<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/workbook.xml'=>'<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets><definedNames>'.$definitions.'</definedNames></workbook>',
        'xl/_rels/workbook.xml.rels'=>'<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
        'xl/worksheets/sheet1.xml'=>$sheet($rows,count($d['columns'])),
        'xl/styles.xml'=>'<?xml version="1.0"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="10"/><name val="Arial"/></font><font><b/><sz val="18"/><name val="Arial"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
        'xl/worksheets/_rels/sheet1.xml.rels'=>$headerRels,
        'xl/drawings/header.vml'=>$vml,
        'xl/drawings/_rels/header.vml.rels'=>'<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/doh-logo.png"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/jvgh-seal.png"/></Relationships>',
        'xl/media/doh-logo.png'=>file_get_contents(__DIR__.'/../assets/img/doh-logo.png'),
        'xl/media/jvgh-seal.png'=>file_get_contents(__DIR__.'/../assets/img/jvgh-seal.png'),
    ]);
}
require_once __DIR__.'/report-pdf.php';
function cssrDownloadReport(array $data,string $format): void
{
    if($data['errors']||!in_array($format,['pdf','xlsx'],true)){http_response_code(422);header('Content-Type: text/plain; charset=utf-8');echo 'Correct the report filters before exporting.';exit;}
    try { $content=$format==='pdf'?cssrReportPdf($data):cssrReportXlsx($data); }
    catch(RuntimeException $error){
        error_log('CSSR report export: '.$error->getMessage());
        http_response_code(503);header('Content-Type: text/plain; charset=utf-8');header('Cache-Control: private, no-store');
        echo 'PDF generation is temporarily unavailable. Use Print and choose Save as PDF, or try again.';exit;
    }
    header('Content-Type: '.($format==='pdf'?'application/pdf':'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));
    header('Content-Disposition: attachment; filename="cssr-'.$data['filters']['report'].'-'.date('Ymd').'.'.$format.'"');header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Content-Length: '.strlen($content));echo $content;exit;
}
