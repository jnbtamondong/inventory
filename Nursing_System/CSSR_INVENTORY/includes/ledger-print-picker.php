<?php function cssrLedgerPrintPicker(array $items,string $route,string $filterForm,string $from,string $to): void { ?>
<link rel="stylesheet" href="../assets/css/ledger-print-picker.css?v=<?=filemtime(__DIR__.'/../assets/css/ledger-print-picker.css')?>">
<dialog class="cu-dialog ledger-print-picker" id="ledgerPrintPicker" aria-labelledby="ledgerPrintTitle" data-ledger-filter-form="<?=e($filterForm)?>">
<form method="get" action="<?=e($route)?>" id="ledgerPrintForm">
<input type="hidden" name="all" value="1">
<header><div><h2 id="ledgerPrintTitle">Select items to print</h2><p>Print and Print Preview use the same <?=$route==='stock_card.php'?'Stock Card':'Bin Card'?> form and filtered dates.</p></div><button type="button" class="btn btn-light" data-ledger-picker-close aria-label="Close item selection">Close</button></header>
<div class="ledger-picker-body">
<div class="ledger-picker-dates"><label>From<input class="form-control" type="date" name="from" value="<?=e($from)?>"></label><label>To<input class="form-control" type="date" name="to" value="<?=e($to)?>"></label></div>
<div class="ledger-picker-tools"><input class="form-control" type="search" data-ledger-picker-search placeholder="Search item code or name" aria-label="Search items to print"><button type="button" class="btn btn-light" data-ledger-select-all>Select All</button><button type="button" class="btn btn-light" data-ledger-clear-all>Clear All</button></div>
<p role="status" aria-live="polite" data-ledger-selection-count>0 items selected</p>
<p class="alert alert-danger" role="alert" data-ledger-picker-error hidden></p>
<div class="ledger-picker-items"><?php foreach($items as $item): ?><label class="ledger-picker-item"><input type="checkbox" name="selected_items[]" value="<?=(int)$item['id']?>"><span><strong><?=e($item['item_description'])?></strong><small><?=e($item['item_code'].' · '.$item['unit'])?></small></span></label><?php endforeach; ?><?php if(!$items): ?><p>No items are available to print.</p><?php endif; ?></div>
</div><footer><button type="button" class="btn btn-light" data-ledger-picker-close>Cancel</button><button class="btn btn-light" type="submit" name="print_mode" value="preview" data-ledger-print-submit disabled>Print Preview</button><button class="btn btn-primary" type="submit" name="print_mode" value="print" data-ledger-print-submit disabled>Print</button></footer>
</form></dialog>
<script defer src="../assets/js/ledger-print-picker.js?v=<?=filemtime(__DIR__.'/../assets/js/ledger-print-picker.js')?>"></script>
<?php } ?>
