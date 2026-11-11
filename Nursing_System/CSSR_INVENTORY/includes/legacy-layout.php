<?php
require_once __DIR__.'/design-system.php';
function cssrLegacyStart(array $ui): void
{
    $asset=$ui['assets']??'../assets';
    $ui['baseStyles']=array_merge([$asset.'/css/bootstrap.min.css',$asset.'/css/app.css?v='.filemtime(__DIR__.'/../assets/css/app.css'),$asset.'/css/forms.css?v='.filemtime(__DIR__.'/../assets/css/forms.css')], $ui['baseStyles']??[]);
    if (!empty($ui['charts'])) $ui['headScripts'][]=$asset.'/js/chart.umd.js';
    $ui['styles'][]=$asset.'/css/legacy-unified.css?v='.filemtime(__DIR__.'/../assets/css/legacy-unified.css');
    $ui['scripts'][]=$asset.'/js/legacy-unified.js?v='.filemtime(__DIR__.'/../assets/js/legacy-unified.js');
    $ui['bodyClass']=trim('cssr-forms cu-legacy '.($ui['bodyClass']??''));
    $ui['workspaceClass']='main-content';
    cssrUiStart($ui);
    if(empty($ui['embedded'])){ ?><div class="cu-page-heading no-print cu-legacy-heading"><div><p class="cu-eyebrow">CSSR Inventory Management</p><h1><?=cssrUiEscape($ui['title'])?></h1><p class="cu-lead cu-muted"><?=cssrUiEscape($ui['description']??'Manage hospital supplies and inventory operations.')?></p></div></div><?php }
}
function cssrLegacyEnd(string $asset='../assets'): void
{ ?>
<script src="<?=cssrUiEscape($asset)?>/js/bootstrap.bundle.min.js"></script>
<script src="<?=cssrUiEscape($asset)?>/js/app.js?v=<?=filemtime(__DIR__.'/../assets/js/app.js')?>"></script>
<?php cssrUiEnd(); }
