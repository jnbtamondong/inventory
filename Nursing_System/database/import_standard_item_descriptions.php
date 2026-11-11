<?php
declare(strict_types=1);
require_once __DIR__.'/../CSSR_INVENTORY/config/database.php';

$source=$argv[1]??'';
if($source===''||!is_file($source))throw new RuntimeException('Provide the item-description text file.');
$descriptions=array_values(array_filter(array_map('trim',file($source,FILE_IGNORE_NEW_LINES)),fn($line)=>$line!==''));
if(count($descriptions)!==66)throw new RuntimeException('Expected 66 descriptions; found '.count($descriptions).'.');

$codes=[
 1=>'CSSR-000001',2=>'CSSR-000187',3=>'CSSR-000282',4=>null,5=>'CSSR-000298',6=>null,7=>'CSSR-000091',8=>'CSSR-000018',9=>'CSSR-000205',10=>null,
 11=>'CSSR-000074',12=>'CSSR-000191',13=>'CSSR-000163',14=>null,15=>'CSSR-000171',16=>null,17=>'CSSR-000170',18=>'CSSR-000169',19=>null,20=>null,
 21=>null,22=>null,23=>null,24=>null,25=>null,26=>'CSSR-000104',27=>'CSSR-000199',28=>'CSSR-000236',29=>null,30=>'CSSR-000237',31=>null,32=>null,
 33=>'CSSR-000246',34=>'CSSR-000167',35=>null,36=>'CSSR-000258',37=>null,38=>'CSSR-000185',39=>'CSSR-000186',40=>'CSSR-000010',41=>'CSSR-000238',
 42=>'CSSR-000060',43=>'CSSR-000061',44=>'CSSR-000062',45=>'CSSR-000206',46=>'CSSR-000176',47=>'CSSR-000053',48=>'CSSR-000260',49=>'CSSR-000213',
 50=>'CSSR-000208',51=>'CSSR-000189',52=>null,53=>'CSSR-000209',54=>'CSSR-000207',55=>'CSSR-000216',56=>'CSSR-000210',57=>'CSSR-000217',
 58=>'CSSR-000139',59=>'CSSR-000017',60=>'CSSR-000057',61=>'CSSR-000203',62=>'CSSR-000299',63=>'CSSR-000056',64=>'CSSR-000121',65=>'CSSR-000281',66=>'CSSR-000250'
];
$originalNames=[
'CSSR-000001'=>'ADHESIVE PLASTER 1 INCH X 10 YARDS','CSSR-000187'=>'ALCOHOL ISOPROPHYL 70% 1 LITER','CSSR-000282'=>'ANTIBACTERIAL DISINFECTANT LIQUID SOAP, 1 GALLON','CSSR-000298'=>'BONE MARROW ASPIRATION NEEDLE','CSSR-000091'=>'CAUTERY PAD DISPOSABLE','CSSR-000018'=>'CAUTERY PEN AND TIP','CSSR-000205'=>'CHEST DRAIN 1.5 METER TUBE,1000ML','CSSR-000074'=>'COTTONS BALLS','CSSR-000191'=>'DISTILLED WATER','CSSR-000163'=>'PLURAZYME, 5 LITERS','CSSR-000171'=>'EPIDURAL ANESTHEISA KIT G18','CSSR-000170'=>'FOLEY CATHETER FR 22, 3 WAY','CSSR-000169'=>'FOLEY CATHETER FR 24, 3 WAY','CSSR-000104'=>'HEPLOCK','CSSR-000199'=>'INTUBATING STYLET FR6','CSSR-000236'=>'INTUBATING STYLET FR10','CSSR-000237'=>'INTUBATING STYLET FR14','CSSR-000246'=>'MATERNITY PAD','CSSR-000167'=>'MUCCUS COLLECTOR FR14','CSSR-000258'=>'NYLON 1-0 CUTTING BOX OF 12S','CSSR-000185'=>'NYLON 3-0 CUTTING BOX OF 12S','CSSR-000186'=>'NYLON 4-0 CUTTING BOX OF 12S','CSSR-000010'=>'PATIENT ID BANDS - PINK','CSSR-000238'=>'PATIENT ID BANDS - WHITE','CSSR-000060'=>'POLYGLACTIN ABSORBABLE SUTURE 0 ROUND NEEDLE','CSSR-000061'=>'POLYGLACTIN ABSORBABLE SUTURE 1-0 ROUND NEEDLE','CSSR-000062'=>'POLYGLACTIN ABSORBABLE SUTURE 2-0 ROUND NEEDLE','CSSR-000206'=>'POLYGLACTIN ABSORBABLE SUTURE 3-0 CUTTING NEEDLE','CSSR-000176'=>'POLYGLACTIN ABSORBABLE SUTURE 4-0 CUTTING NEEDLE','CSSR-000053'=>'DISPOSABLE RAZOR','CSSR-000260'=>'SILK 0 STRANDS','CSSR-000213'=>'SILK 1 STRANDS','CSSR-000208'=>'SILK 2-0 STRANDS','CSSR-000189'=>'SILK 3-0 STRANDS','CSSR-000209'=>'SILK 2-0 CUTTING','CSSR-000207'=>'SILK 2-0 ROUND','CSSR-000216'=>'SILK 3-0 CUTTING','CSSR-000210'=>'SILK 3-0 ROUND','CSSR-000217'=>'SILK 4-0 CUTTING','CSSR-000139'=>'SUCTION CATHETER FR 16','CSSR-000017'=>'SUCTION SET POOLE DRAIN','CSSR-000057'=>'SURGICAL SCRUB BRUSH W/ POVIDONE','CSSR-000203'=>'TRACHEOSTOMY TUBE, ADULT, FENESTRATED 6.5','CSSR-000299'=>'TRACHEOSTOMY TUBE, ADULT, FENESTRATED 7.5','CSSR-000056'=>'EFFERVESCENT DISINFECTANT TABLETS 100 TABLET PER CAN','CSSR-000121'=>'WEEBAG URINE COLLECTOR WITH COMFORT PADDING','CSSR-000281'=>'CHLORHEXIDINE GLUCONATE 4%','CSSR-000250'=>'RUBBER SUCTION BULB SYRINGE 30ML'];

$next=(int)db_value('SELECT COALESCE(MAX(CAST(SUBSTRING(item_code,6) AS UNSIGNED)),0)+1 FROM items WHERE item_code REGEXP "^CSSR-[0-9]+$"');
$updated=0;$inserted=0;
db()->beginTransaction();
try{
    foreach($descriptions as $offset=>$description){
        $position=$offset+1;$code=$codes[$position];
        if($code!==null){
            $item=db_one('SELECT id FROM items WHERE item_code=? FOR UPDATE',[$code]);
            if(!$item)throw new RuntimeException('Mapped item '.$code.' was not found.');
            db_statement('UPDATE items SET item_name=COALESCE(?,item_name),item_description=? WHERE id=?',[$originalNames[$code]??null,$description,(int)$item['id']]);
            $updated++;
        }else{
            $existing=db_one('SELECT id FROM items WHERE item_description=?',[$description]);
            if($existing){$updated++;continue;}
            do{$code='CSSR-'.str_pad((string)$next++,6,'0',STR_PAD_LEFT);}while((int)db_value('SELECT COUNT(*) FROM items WHERE item_code=?',[$code])>0);
            db_statement('INSERT INTO items(item_code,item_name,item_description,category,unit,status) VALUES(?,?,?,?,?,"Active")',[$code,mb_substr($description,0,255),$description,'MEDICAL SUPPLIES','PIECE']);
            $inserted++;
        }
    }
    db()->commit();
    $verified=0;foreach($descriptions as $description)$verified+=(int)db_value('SELECT COUNT(*)>0 FROM items WHERE item_description=?',[$description]);
    echo "Updated: $updated\nInserted: $inserted\nTotal processed: ".($updated+$inserted)."\nExact descriptions verified: $verified\n";
}catch(Throwable $error){if(db()->inTransaction())db()->rollBack();throw $error;}
