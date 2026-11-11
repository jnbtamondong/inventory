<?php
// Uses the caller's already-authorized navigation. Never grants module access.
function cssrMobileNavigation(array $ui): void
{
    $modules=array_column($ui['navigation'],null,'id');$active=$ui['active']??'';
    // Transaction links may now live inside a workflow submenu.
    $transactionLinks=$modules;
    foreach($ui['navigation'] as $module)foreach($module['children']??[] as $child){
        if(isset($child['mobile_create']))$transactionLinks[$child['id']]=$child;
    }
    $link=static function(string $id,string $label,string $icon)use($modules,$active){
        if(!isset($modules[$id]))return;
        $item=$modules[$id];$href=!empty($item['single_link'])?$item['href']:($item['children'][0]['href']??$item['href']);
        $selected=in_array($active,array_merge([$id],array_column($item['children']??[],'id')),true);
        echo '<a href="'.cssrUiEscape($href).'"'.($selected?' aria-current="page"':'').'>'.cssrUiIcon($icon).'<span>'.$label.'</span></a>';
    };
    $actions=[];
    foreach(['inbound'=>['Receive supplies','inbound'],'requisitions'=>['Request supplies','request'],'returns'=>['Return supplies','return']] as $id=>[$label,$icon]){
        if(!isset($transactionLinks[$id])||($transactionLinks[$id]['mobile_create']??true)===false)continue;
        $href=$transactionLinks[$id]['href'];
        if($id==='inbound')$href.=(str_contains($href,'?')?'&':'?').'new=1';
        if($id==='requisitions')$href=preg_replace('/requisitions\.php(?:\?.*)?$/','item_requests.php',$href);
        // The preview does not provide a separate request creation form.
        if(!empty($ui['environment'])&&str_contains(strtolower($ui['environment']),'preview')&&$id==='requisitions')$href='../pages/item_requests.php';
        $actions[]=[$label,$icon,$href];
    }
?>
<nav class="cm-bottom-nav" aria-label="Mobile navigation">
<?php $link('dashboard','Home','dashboard');$link('inventory','Inventory','box'); ?>
<?php if($actions): ?><button type="button" data-cm-transaction aria-controls="cm-transactions" aria-haspopup="dialog"><?=cssrUiIcon('plus')?><span>New Transaction</span></button><?php endif; ?>
<?php $link('reports','Reports','report'); ?>
<button type="button" data-cm-more aria-label="More modules" aria-controls="<?=!empty($ui['legacy'])?'appSidebar':'cu-sidebar'?>" aria-expanded="false"><?=cssrUiIcon('menu')?><span>More</span></button>
</nav>
<?php if($actions): ?><dialog class="cm-transaction-sheet" id="cm-transactions" aria-labelledby="cm-transactions-title"><header><div><small>CSSR INVENTORY</small><h2 id="cm-transactions-title">New Transaction</h2></div><button type="button" data-cm-dismiss aria-label="Close transaction menu"><?=cssrUiIcon('close')?></button></header><div class="cm-transaction-links"><?php foreach($actions as [$label,$icon,$href]): ?><a href="<?=cssrUiEscape($href)?>"><?=cssrUiIcon($icon)?><span><?=cssrUiEscape($label)?></span><?=cssrUiIcon('chevron')?></a><?php endforeach; ?></div><footer><button type="button" data-cm-dismiss>Cancel</button></footer></dialog><?php endif; ?>
<?php }
