<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/item-editor.php';
require_once __DIR__ . '/../includes/inventory-list-data.php';
require_once __DIR__ . '/../design-system/inventory-samples.php';
$pdo = cssrInventorySampleConnection(); $options = cssrItemEditorOptions(['PCS']); $checks = 0;
function editorExpect($value,$expected,string $label): void {
    global $checks; $checks++;
    if ($value !== $expected) throw new RuntimeException($label . ': ' . json_encode($value));
}
$valid = ['id'=>'0','item_name'=>'New sterile dressing','item_code'=>'','auto_code'=>'1','classification'=>'MEDICAL SUPPLIES','unit'=>'Box','item_description'=>'','minimum_stock'=>'0','storage_location'=>'STORAGE ROOM 1','procurement_type'=>'Procured'];
editorExpect(cssrValidateItemEditor($valid,$options)['errors'], [], 'Zero reorder level and optional description are valid');
foreach (['item_name','classification','unit','minimum_stock','storage_location','procurement_type'] as $required) {
    $test=$valid; $test[$required]=''; editorExpect(isset(cssrValidateItemEditor($test,$options)['errors'][$required]),true,'Required '.$required);
}
foreach (['-1','1.2','2147483648','1e2'] as $bad) {
    $test=$valid; $test['minimum_stock']=$bad; editorExpect(isset(cssrValidateItemEditor($test,$options)['errors']['minimum_stock']),true,'Invalid reorder '.$bad);
}
$test=$valid; $test['minimum_stock']='00'; editorExpect(cssrValidateItemEditor($test,$options)['values']['minimum_stock'],0,'Leading-zero integer matches browser validation');
$test=$valid; $test['item_description']=str_repeat('a',1001); editorExpect(isset(cssrValidateItemEditor($test,$options)['errors']['item_description']),true,'Description limit');
$test=$valid; $test['item_description']=str_repeat('é',1000); editorExpect(cssrValidateItemEditor($test,$options)['errors'],[],'UTF-8 description length');
$test=$valid; $test['auto_code']='0'; editorExpect(isset(cssrValidateItemEditor($test,$options)['errors']['item_code']),true,'Manual code required');
$test=$valid; $test['unit']='Unapproved unit'; editorExpect(isset(cssrValidateItemEditor($test,$options)['errors']['unit']),true,'Unit whitelist');
$test=$valid; $test['unit']='PCS'; editorExpect(cssrValidateItemEditor($test,$options)['errors'],[],'Existing unit retained in dropdown');
$deduplicated = cssrItemEditorOptions(['BOX','box',' Box ','PCS','pcs',' PCS ','Custom Unit','custom unit','',null]);
editorExpect(count(array_filter($deduplicated['unit'], static fn($unit) => strtolower($unit) === 'box')),1,'Case and whitespace variants appear once');
editorExpect(count(array_filter($deduplicated['unit'], static fn($unit) => strtolower($unit) === 'pcs')),1,'Existing custom unit variants appear once');
editorExpect(in_array('Custom Unit',$deduplicated['unit'],true),true,'Distinct custom units retained');
$test=$valid; $test['unit']=' BOX '; editorExpect(cssrValidateItemEditor($test,$deduplicated)['values']['unit'],'Box','Existing unit casing resolves to canonical option');
$test=$valid; $test['item_name']=[]; editorExpect(isset(cssrValidateItemEditor($test,$options)['errors']['item_name']),true,'Array payload rejected');
$test=$valid; $test['id']=-1; editorExpect(isset(cssrValidateItemEditor($test,$options)['errors']['_form']),true,'Invalid item ID rejected');
$created=cssrSaveItemEditor($pdo,$valid,$options);
editorExpect($created['ok'],true,'Create item'); editorExpect($created['item_code'],'CSSR-000029','Generated sequential code');
$row=$pdo->query('SELECT * FROM items WHERE id='.(int)$created['id'])->fetch(PDO::FETCH_ASSOC);
editorExpect([(int)$row['current_stock'],$row['status'],$row['procurement_type'],$row['bin_location']],[0,'Active','Procured','STORAGE ROOM 1'],'New item starts with zero stock and saves defaults');
$second=cssrSaveItemEditor($pdo,$valid,$options); editorExpect($second['item_code'],'CSSR-000030','Subsequent automatic code differs');
$manual=$valid; $manual['auto_code']='0'; $manual['item_code']='ER-CUSTOM-1'; $manual['procurement_type']='Emergency Purchase';
$manualResult=cssrSaveItemEditor($pdo,$manual,$options); editorExpect($manualResult['item_code'],'ER-CUSTOM-1','Manual code saved');
$duplicate=cssrSaveItemEditor($pdo,$manual,$options); editorExpect(isset($duplicate['errors']['item_code']),true,'Duplicate manual code shows field error');
$manual['item_code']='er-custom-1'; editorExpect(isset(cssrSaveItemEditor($pdo,$manual,$options)['errors']['item_code']),true,'Case-insensitive duplicate validation');
$pdo->exec("ALTER TABLE items ADD COLUMN item_image TEXT; UPDATE items SET item_image='existing.png' WHERE id=1");
$beforeLots=$pdo->query('SELECT * FROM item_lots WHERE item_id=1')->fetchAll(PDO::FETCH_ASSOC);
$edit=$valid; $edit['id']='1'; $edit['auto_code']='0'; $edit['item_code']='CSSR-0001'; $edit['item_name']='Updated alcohol'; $edit['minimum_stock']='25'; $edit['procurement_type']='Donation'; $edit['storage_location']='STORAGE ROOM 1';
$updated=cssrSaveItemEditor($pdo,$edit,$options); editorExpect($updated['ok'],true,'Edit existing item');
$row=$pdo->query('SELECT * FROM items WHERE id=1')->fetch(PDO::FETCH_ASSOC);
editorExpect([$row['item_name'],(int)$row['minimum_stock'],$row['procurement_type'],$row['bin_location']],['Updated alcohol',25,'Donation','STORAGE ROOM 1'],'Edit saves fields');
editorExpect([(int)$row['current_stock'],$row['item_image']],[130,'existing.png'],'Edit preserves stock and images');
editorExpect($pdo->query('SELECT * FROM item_lots WHERE item_id=1')->fetchAll(PDO::FETCH_ASSOC),$beforeLots,'Default location edit does not move stock lots');
$edit['id']='99999'; editorExpect(isset(cssrSaveItemEditor($pdo,$edit,$options)['errors']['_form']),true,'Missing item is not silently created');
$test=$valid; $test['current_stock']=900; $test['status']='Inactive'; $guard=cssrSaveItemEditor($pdo,$test,$options);
$row=$pdo->query('SELECT current_stock,status FROM items WHERE id='.(int)$guard['id'])->fetch(PDO::FETCH_ASSOC);
editorExpect([(int)$row['current_stock'],$row['status']],[0,'Active'],'Extra payload fields cannot change stock or status');
editorExpect(cssrInventoryList($pdo,['procurement_type'=>'Emergency Purchase'])['summary']['total'],1,'New procurement default is filterable without receipt records');
echo "Passed {$checks} item editor data checks in an isolated in-memory database.\n";
