<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles([ROLE_ADMIN, ROLE_HEAD, ROLE_STAFF]);
if (!can_manage()) {
    http_response_code(403);
    exit('Access denied.');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Blank Request Voucher</title>
    <style>
        @page{size:A4 portrait;margin:0 5mm 5mm}
        *{box-sizing:border-box}
        html,body{margin:0;background:#fff;color:#12344d;font-family:"Segoe UI",Arial,Helvetica,sans-serif}
        .sheet{position:relative;width:100%;height:285mm;overflow:hidden}
        .voucher{position:absolute;left:0;right:0;height:134mm;overflow:hidden;background:#fff}
        .voucher:first-child{top:4mm}.voucher:last-child{top:146mm;height:138mm}
        .header{display:grid;grid-template-columns:52px minmax(0,1fr) 52px;align-items:center;gap:6px;padding:2mm 7px 2px}
        .logo{width:48px;height:48px;object-fit:contain}.logo:first-child{grid-column:1}.facility{grid-column:2;text-align:center;line-height:1.08}.logo:nth-of-type(2){grid-column:3;justify-self:end}
        .facility span,.facility small{display:block;font-size:7px;font-weight:700}.facility strong{display:block;font-size:8.5px;font-weight:800}
        .facility.hospital-card-letterhead{font-family:"Times New Roman",Times,serif;color:#000}.facility.hospital-card-letterhead strong{font-size:16px;font-weight:700;line-height:1.1}.facility.hospital-card-letterhead span{font-size:12px;font-weight:400;line-height:1.2}
        h1{margin:2px 0 4px;text-align:center;color:#000;font-size:12px;line-height:1}
        .meta{display:grid;grid-template-columns:1fr 1fr;gap:3px;margin-bottom:3px}.meta div{height:18px;padding:4px 6px;border:1px solid #d4e1dc;border-radius:4px;font-size:8.5px;line-height:10px}.meta b{color:#12344d}
        table{width:100%;border-collapse:collapse;table-layout:fixed;border:1px solid #d4e1dc}th,td{height:17px;max-height:17px;padding:2px 4px;border-bottom:1px solid #dce8f2;font-size:7px;line-height:9px;text-align:center}th{font-size:7.5px;text-transform:uppercase;background:#f3f8f6}th:first-child,td:first-child{text-align:left}
        .signatures{position:absolute;top:113mm;left:5mm;right:5mm;display:flex;justify-content:space-between}.voucher:last-child .signatures{top:116mm}.signature{width:150px;font-size:7px}.signature strong{display:block;height:15px;margin-top:2px;border-bottom:1px solid #000;text-align:center;font-size:7px;line-height:11px}.signature small{display:block;margin-top:1px;text-align:center;font-size:5.5px}
        .footer{position:absolute;top:124mm;left:5mm;right:5mm;height:9mm;display:grid;grid-template-columns:1fr 78px;align-items:end;gap:10px;padding-top:1mm;border-top:.35px solid #64748b}.voucher:last-child .footer{top:128mm}.note{padding-left:3mm;font-family:"Times New Roman",serif;font-size:6px;font-weight:700;font-style:italic;line-height:1.1}.code{text-align:right;font-family:"Times New Roman",serif;font-size:5.5px;font-weight:700}.code strong{display:block;margin-bottom:3px}.code table{font-family:Arial,sans-serif;font-size:4.5px}.code th,.code td{height:8px;padding:0 2px;border:1px solid #000;font-size:4.5px;line-height:5px}
        @media print{html,body{height:285mm!important;overflow:hidden!important}.sheet{break-after:avoid-page;page-break-after:avoid}}
        @media screen{body{background:#eef3f1}.sheet{margin:auto;max-width:210mm;box-shadow:0 5px 20px #9aa}.voucher{padding:0}}
    </style>
</head>
<body>
<main class="sheet">
<?php for ($copy = 0; $copy < 2; $copy++): ?>
    <section class="voucher">
        <header class="header">
            <img class="logo" src="../assets/img/doh-logo.png" alt="Department of Health logo">
            <div class="facility hospital-card-letterhead"><strong>DEPARTMENT OF HEALTH</strong><strong>JONI VILLANUEVA GENERAL HOSPITAL</strong><span>National Road, Igulot, Bocaue, Bulacan 3018</span></div>
            <img class="logo" src="../assets/img/jvgh-seal.png" alt="Joni Villanueva General Hospital seal">
        </header>
        <h1>VOUCHER'S SLIP</h1>
        <div class="meta"><div><b>Department:</b></div><div><b>Requested By:</b></div><div><b>Date:</b></div><div><b>Request No.:</b></div></div>
        <table><thead><tr><th>Description</th><th>Unit</th><th>Lot Number</th><th>Requested Qty</th><th>Release Qty</th></tr></thead><tbody>
        <?php for ($row = 0; $row < 17; $row++): ?><tr><td>&nbsp;</td><td></td><td></td><td></td><td></td></tr><?php endfor; ?>
        </tbody></table>
        <div class="signatures"><div class="signature">Prepared By:<strong>&nbsp;</strong><small>Signature over printed name</small></div><div class="signature">Received By:<strong>&nbsp;</strong><small>Signature over printed name</small></div></div>
        <footer class="footer"><div class="note">This is a computer-generated form.<br>No stamping is needed.</div><div class="code"><strong>JVGH-CSSR-XX-XXX</strong><table><tr><th>Rev Number</th><td></td></tr><tr><th>Eff Date</th><td></td></tr></table></div></footer>
    </section>
<?php endfor; ?>
</main>
<script>window.addEventListener('load',function(){setTimeout(function(){window.print()},250)});</script>
</body>
</html>
