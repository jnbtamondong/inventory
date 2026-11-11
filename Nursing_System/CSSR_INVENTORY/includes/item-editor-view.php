<?php
require_once __DIR__ . '/item-editor.php';
function cssrRenderItemEditor(array $items, array $actions, bool $preview): void
{
    $options = cssrItemEditorOptions($actions['units'] ?? array_column($items,'unit'));
    $labels = ['classification'=>'Classification','unit'=>'Unit','storage_location'=>'Storage Location','procurement_type'=>'Procurement Type'];
    $select = static function (string $name) use ($options,$labels): void { ?>
        <div class="cu-field"><label for="item-editor-<?=$name?>"><?=$labels[$name]?> <span class="cu-required" aria-hidden="true">*</span></label><select class="cu-input" id="item-editor-<?=$name?>" name="<?=$name?>" required aria-describedby="item-error-<?=$name?>"><option value="">Select <?=strtolower($labels[$name])?></option><?php foreach ($options[$name] as $option): ?><option value="<?=cssrUiEscape($option)?>"><?=cssrUiEscape($name === 'storage_location' && $option !== 'CSSR' ? ucwords(strtolower($option)) : ($name === 'classification' ? ucwords(strtolower($option)) : $option))?></option><?php endforeach; ?></select><small class="cu-error" id="item-error-<?=$name?>" hidden></small></div>
    <?php };
?>
<dialog class="cu-dialog cu-item-editor" id="item-editor-dialog" aria-labelledby="item-editor-title" aria-describedby="item-editor-intro">
    <form id="item-editor-form" method="post" action="<?=cssrUiEscape($actions['save'] ?? '../api/item-editor.php')?>" novalidate>
        <div class="cu-dialog-head"><div><p class="cu-eyebrow">Inventory item</p><h2 id="item-editor-title">Add New Item</h2></div><button class="cu-icon-button" type="button" data-item-editor-close aria-label="Close item form"><?=cssrUiIcon('close')?></button></div>
        <div class="cu-item-editor-body">
            <p id="item-editor-intro" class="cu-muted">Enter the item details. Fields marked <span class="cu-required">*</span> are required.</p>
            <?php if ($preview): ?><p class="cu-item-editor-preview">Design preview: saving demonstrates the interaction without changing hospital records.</p><?php endif; ?>
            <div class="cu-item-editor-error" id="item-editor-error" role="alert" tabindex="-1" hidden></div>
            <input type="hidden" name="id" value="0"><input type="hidden" name="csrf_token" value="<?=cssrUiEscape($actions['csrf'] ?? '')?>">
            <fieldset class="cu-item-editor-fields">
                <legend class="cu-sr">Item details</legend>
                <div class="cu-form-grid">
                    <div class="cu-field cu-span-2"><label for="item-editor-item_name">Item Name <span class="cu-required" aria-hidden="true">*</span></label><input class="cu-input" id="item-editor-item_name" name="item_name" required maxlength="255" placeholder="e.g. Sterile gauze 4 × 4" aria-describedby="item-error-item_name" autofocus><small class="cu-error" id="item-error-item_name" hidden></small></div>
                    <div class="cu-field cu-span-2"><div class="cu-item-code-label"><label for="item-editor-item_code">Item Code <span class="cu-required" data-code-required aria-hidden="true" hidden>*</span></label><label class="cu-check cu-auto-code"><input type="checkbox" name="auto_code" value="1" checked> Auto-generate</label></div><input class="cu-input" id="item-editor-item_code" name="item_code" maxlength="80" placeholder="Generated when saved" readonly autocomplete="off" aria-describedby="item-code-help item-error-item_code"><small id="item-code-help">A unique code will be assigned when you save.</small><small class="cu-error" id="item-error-item_code" hidden></small></div>
                    <?php $select('classification'); $select('unit'); ?>
                    <div class="cu-field cu-span-2"><label for="item-editor-item_description">Item Description <span class="cu-muted">(optional)</span></label><textarea class="cu-input" id="item-editor-item_description" name="item_description" rows="3" maxlength="1000" placeholder="Add specifications, material, or packaging details" aria-describedby="item-description-count item-error-item_description"></textarea><small id="item-description-count">0 / 1,000 characters</small><small class="cu-error" id="item-error-item_description" hidden></small></div>
                    <div class="cu-field"><label for="item-editor-minimum_stock">Reorder Level <span class="cu-required" aria-hidden="true">*</span></label><input class="cu-input" id="item-editor-minimum_stock" name="minimum_stock" type="number" min="0" max="2147483647" step="1" inputmode="numeric" required value="0" aria-describedby="item-reorder-help item-error-minimum_stock"><small id="item-reorder-help">Minimum stock threshold. Enter 0 if none.</small><small class="cu-error" id="item-error-minimum_stock" hidden></small></div>
                    <?php $select('storage_location'); $select('procurement_type'); ?>
                </div>
                <p class="cu-item-editor-stock-note">Storage Location is the item's default receiving location. Existing stock remains in its recorded location.</p>
            </fieldset>
        </div>
        <div class="cu-dialog-foot cu-item-editor-footer"><small id="item-editor-readiness" role="status">Complete required fields to enable saving.</small><div class="cu-actions"><button class="cu-btn cu-btn-secondary" type="button" data-item-editor-close>Cancel</button><button class="cu-btn" type="submit" id="item-editor-save" disabled>Save Item</button></div></div>
    </form>
</dialog>
<script type="application/json" id="item-editor-data"><?=json_encode(['preview'=>$preview,'items'=>$items], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR)?></script>
<?php }
