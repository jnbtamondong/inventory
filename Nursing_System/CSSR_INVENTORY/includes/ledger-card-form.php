    <div class="bin-card-sheet <?= $ledgerFormType==='stock'?'stock-card-sheet':'bin-card-print-page' ?>">
        <div class="bin-card-header">
            <img class="bin-logo" src="../assets/img/doh-logo.png" alt="Department of Health logo">
            <div class="facility-copy hospital-card-letterhead"><strong>DEPARTMENT OF HEALTH</strong><strong>JONI VILLANUEVA GENERAL HOSPITAL</strong><span>National Road, Igulot, Bocaue, Bulacan 3018</span></div>
            <img class="bin-logo" src="../assets/img/jvgh-seal.png" alt="Joni Villanueva General Hospital seal">
        </div>

        <h2><?= $ledgerFormType==='stock'?'STOCK CARD':'BIN CARD' ?></h2>

        <div class="bin-meta">
            <div class="meta-label">ITEM NAME:</div>
            <div class="meta-value wide"><?= e($displayItem['item_description'] ?? '') ?></div>
            <div class="meta-label">UNIT:</div>
            <div class="meta-value wide"><?= e($displayItem['unit'] ?? '') ?></div>
            <?php if($ledgerFormType==='stock'): ?><div class="meta-label">STORAGE ROOM:</div><div class="meta-value wide"><?= e($ledgerScopeLabel) ?></div><?php endif; ?>
            <div class="meta-label">DESCRIPTION:</div>
            <div class="meta-value wide small-text"><?= e($displayItem['category'] ?? '') ?></div>
        </div>

        <table class="bin-card-table<?= $ledgerFormType==='stock'?' stock-card-table':'' ?>">
            <thead>
                <tr>
                    <th>DATE</th>
                    <th>REFERENCE</th>
                    <th>LOT NUMBER</th>
                    <th>RECEIVED</th>
                    <th>ISSUED</th>
                    <th>BALANCE</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pageRows as $row): ?>
                    <tr>
                        <td><?= e(date('n/j/Y', strtotime($row['transaction_date']))) ?></td>
                        <td><?= e(
                            !empty($row['is_inspection_hold'])
                                ? ($row['reference_no'] ?? 'FOR INSPECTION')
                                : (!empty($row['is_pending_balance'])
                                ? 'INITIAL PHYSICAL COUNT'
                                : ($row['reference_no'] ?? ''))
                        ) ?></td>
                        <td><?= e($row['lot_number'] ?? '') ?></td>
                        <td><?= (int) $row['received_qty'] > 0 ? number_format((int) $row['received_qty']) : '' ?></td>
                        <td><?= (int) $row['issued_qty'] > 0 ? number_format((int) $row['issued_qty']) : '' ?></td>
                        <td><?= number_format((int) $row['balance_qty']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php for ($i = 0; $i < $pageBlankRows; $i++): ?>
                    <tr class="blank-row"><td></td><td></td><td></td><td></td><td></td><td></td></tr>
                <?php endfor; ?>
            </tbody>
        </table>
        <div class="bin-card-footer">
            <div class="bin-footer-note">This is a computer-generated form. &nbsp; <br> No stamping is needed.</div>
            <div class="bin-footer-code">
                <strong>JVGH-CSSR-XX-XXX</strong>
                <table>
                    <tr><th>Rev Number</th><td></td></tr>
                    <tr><th>Eff Date</th><td></td></tr>
                </table>
            </div>
        </div>
    </div>
