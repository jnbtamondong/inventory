<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin_or_cssr();
$pageTitle = 'Bin Card';
$activePage = 'bin_card';
$items = db_all('SELECT DISTINCT i.*, COALESCE(NULLIF(TRIM(i.item_name),""),NULLIF(TRIM(i.item_description),""),i.item_code) AS item_description FROM items i JOIN item_lots l ON l.item_id=i.id WHERE l.storage_location="CSSR" ORDER BY i.item_description');
$isBlankForm = isset($_GET['blank']);
$fromDate = trim((string) ($_GET['from'] ?? ''));
$toDate = trim((string) ($_GET['to'] ?? ''));
require_once __DIR__.'/../includes/ledger-card-data.php';
$printAll = isset($_GET['all']) && !$isBlankForm;
$printItems = cssrLedgerSelectedItems($items,$_GET['selected_items'] ?? []);
$ledgerScopeLabel = 'CSSR STORAGE';
$ledgerFormType = 'bin';
$printError = $printAll ? cssrLedgerPrintError($fromDate,$toDate,$printItems) : '';
extract(cssrBinCardData($items,$isBlankForm ? 0 : (int)($_GET['item_id'] ?? 0),$fromDate,$toDate));

include __DIR__ . '/../includes/header.php';
?>
<style>@media print{.bin-workspace>.bin-card-sheet{break-after:page!important;page-break-after:always!important}.bin-workspace>.bin-card-sheet:last-child{break-after:auto!important;page-break-after:auto!important}}</style>
<section class="page-panel bin-workspace">
    <div class="pos-panel-title no-print">
        <div>
            <span class="eyebrow">Ledger Filters</span>
            <h3>CSSR Storage Bin Card</h3>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-light" href="bin_card.php?blank=1">Print Blank</a>
            <button type="button" class="btn btn-light" onclick="printPage()">Print Preview</button>
            <button type="button" class="btn btn-primary" data-ledger-picker-open>Print All Items</button>
        </div>
    </div>
    <form class="toolbar" method="get" id="binCardFilters">
        <div class="field"><label class="form-label" for="binCardItemSearch">Item Name</label><div class="ledger-combobox"><input class="form-control" id="binCardItemSearch" type="search" placeholder="Type item code or name" autocomplete="off" required data-ledger-combobox="binCardItemSelect"><div class="ledger-combobox-options" data-ledger-options></div></div><select class="ledger-native-select" id="binCardItemSelect" name="item_id" aria-hidden="true" tabindex="-1"><option value="">Select Item</option><?php foreach ($items as $item): ?><option value="<?= (int)$item['id'] ?>" <?= (($_GET['item_id'] ?? '')==$item['id'])?'selected':'' ?>><?= e($item['item_code'].' - '.$item['item_description']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label class="form-label">From</label><input class="form-control" type="date" name="from" value="<?= e($_GET['from'] ?? '') ?>"></div>
        <div class="field"><label class="form-label">To</label><input class="form-control" type="date" name="to" value="<?= e($_GET['to'] ?? '') ?>"></div>
        <button class="btn btn-primary">Filter</button><a class="btn btn-light" href="bin_card.php">Reset</a>
    </form>
    <?php if ($printError): ?><p class="alert alert-danger" role="alert"><?= e($printError) ?></p><?php endif; ?>
    <?php if (!$items && !$isBlankForm): ?>
        <div class="empty-state">No items are currently assigned to CSSR storage.</div>
    <?php elseif (!$selectedItem && !$isBlankForm && !$printAll): ?>
        <div class="empty-state">Select a CSSR storage item to view its Bin Card.</div>
    <?php endif; ?>
    <?php if ((!$printAll && ($selectedItem || $isBlankForm)) || ($printAll && $printItems && !$printError)): ?>
<?php foreach ($printAll ? $printItems : [$selectedItem] as $cardItem): ?>
<?php if ($printAll) extract(cssrBinCardData($items,(int)$cardItem['id'],$fromDate,$toDate)); ?>
    <?php foreach ($rowPages as $pageRows): ?>
    <?php $pageBlankRows = max(0, $ledgerRowTarget - count($pageRows)); ?>
    <?php $displayItem=$selectedItem;include __DIR__.'/../includes/ledger-card-form.php'; ?>
    <?php endforeach; ?>
    <?php endforeach; ?>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/ledger-print-picker.php'; cssrLedgerPrintPicker($items,'bin_card.php','binCardFilters',$fromDate,$toDate); ?>
<?php if ($isBlankForm || ($printAll && $printItems && !$printError)): ?>
<script>
window.addEventListener('load', function(){
    setTimeout(function(){ window.print(); }, 250);
});
</script>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
