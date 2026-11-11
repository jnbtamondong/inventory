<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/nursing_reports.php';

requireLogin();
requirePermission('reports', 'view');
if (!nursingReportCanAccess($reportKey)) {
    http_response_code(403);
    exit('Access denied for this nursing report.');
}
ensureStatusTrackingSchema($conn);
ensurePatientWorkflowSchema($conn);
ensureClinicalModuleSchema($conn);
nursingReportEnsureSchema($conn);

$reportKey = $reportKey ?? 'bed_tracker';
[$startSql, $endSql, $startInput, $endInput] = nrDateRange();
$report = nursingReportConfig($reportKey, $conn, $startSql, $endSql);
$preparedBy = sanitizeText($_GET['prepared_by'] ?? ($_SESSION['first_name'] ?? $_SESSION['username'] ?? ''), 150);
$reviewedBy = sanitizeText($_GET['reviewed_by'] ?? '', 150);
$approvedBy = sanitizeText($_GET['approved_by'] ?? '', 150);
$generatedAt = date('Y-m-d H:i:s');

function nursingReportTotals(array $columns, array $rows): array {
    $totals = [];
    foreach ($columns as $index => $column) {
        if ($index === 0) {
            $totals[$column] = 'TOTAL';
            continue;
        }
        $sum = 0;
        $numeric = false;
        foreach ($rows as $row) {
            $value = $row[$column] ?? '';
            if (is_numeric($value)) {
                $sum += (float) $value;
                $numeric = true;
            }
        }
        $totals[$column] = $numeric ? $sum : '';
    }
    return $totals;
}

function renderReportTable(array $columns, array $rows): void {
    $totals = nursingReportTotals($columns, $rows);
    echo '<div class="table-responsive report-table-wrap"><table class="table report-table"><thead><tr>';
    foreach ($columns as $column) {
        echo '<th>' . h($column) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($columns as $column) {
            echo '<td>' . h((string) ($row[$column] ?? '')) . '</td>';
        }
        echo '</tr>';
    }
    if ($rows) {
        echo '<tr class="total-row">';
        foreach ($columns as $column) {
            echo '<td>' . h((string) ($totals[$column] ?? '')) . '</td>';
        }
        echo '</tr>';
    }
    if (!$rows) {
        echo '<tr><td colspan="' . count($columns) . '" class="text-center">No records found for this coverage.</td></tr>';
    }
    echo '</tbody></table></div>';
}

if (($_GET['export'] ?? '') === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $report['title']) . '.xls"');
    echo '<html><body class="module-ui"><h2>' . h($report['title']) . '</h2>';
    echo '<p>Coverage: ' . h($startSql) . ' to ' . h($endSql) . '</p>';
    renderReportTable($report['columns'], $report['rows']);
    foreach ($report['extra'] ?? [] as $section) {
        echo '<h3>' . h($section['title']) . '</h3>';
        renderReportTable($section['columns'], $section['rows']);
    }
    echo '</body></html>';
    exit;
}

include '../includes/locations.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($report['title']); ?></title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css">
    <style>
        .report-document{background:#fff;border:1px solid #DCE8F2;border-radius:10px;padding:22px;box-shadow:0 12px 28px rgba(18,52,81,.08)}
        .report-letterhead{text-align:center;border-bottom:2px solid #0B3C5D;padding-bottom:12px;margin-bottom:14px}
        .report-letterhead img{width:68px;height:68px;object-fit:contain}
        .report-letterhead h1{font-size:18px;margin:8px 0 2px;color:#0B3C5D}
        .report-letterhead h2{font-size:15px;margin:0;color:#1F3446}
        .report-title{font-size:22px;text-align:center;color:#0B3C5D;margin:18px 0}
        .report-meta{display:grid;grid-template-columns:repeat(4,1fr);border:1px solid #BFD0E0;margin-bottom:16px}
        .report-meta div{padding:8px;border-right:1px solid #BFD0E0;border-bottom:1px solid #BFD0E0}
        .report-meta strong{display:block;color:#0B3C5D;font-size:12px;text-transform:uppercase}
        .report-actions{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}
        .report-summary{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px;margin-bottom:16px}
        .report-summary .summary-card{border:1px solid #DCE8F2;border-radius:8px;padding:12px;background:#F4F7FA}
        .report-summary strong{display:block;font-size:20px;color:#0B3C5D}
        .report-table th{background:#F4F7FA;color:#0B3C5D;border:1px solid #BFD0E0}
        .report-table td{border:1px solid #DCE8F2}
        .report-table .total-row td{font-weight:900;background:#F1F7F4}
        .signature-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin-top:28px}
        .signature-box{border-top:1px solid #0B3C5D;text-align:center;padding-top:8px}
        @media(max-width:900px){.report-meta,.signature-grid,.report-summary{grid-template-columns:1fr}.report-document{padding:14px}}
        @media print{.navbar,.sidebar,.report-actions,.page-header,.no-print{display:none!important}.dashboard-container{display:block}.main-content{padding:0}.report-document{box-shadow:none;border:0;border-radius:0}.report-table th,.report-table td{font-size:10px;padding:4px}@page{size:A4 landscape;margin:10mm}}
    </style>
<link rel="stylesheet" href="../public/css/module-theme.css?v=18">
<script defer src="../public/js/nursync-ui.js?v=4"></script></head>
<body class="module-ui">
<nav class="navbar"><div class="navbar-brand"><div class="navbar-title-stack"><h1>Joni Villanueva General Hospital</h1><span class="navbar-system-subtitle">Nursing Information System</span></div></div><div class="navbar-menu"><span class="user-info">Welcome, <?php echo h($_SESSION['first_name'] ?? $_SESSION['username']); ?></span></div></nav>
<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    <main class="main-content">
        <section class="page-header no-print"><h2><?php echo h($report['title']); ?></h2><p class="compact-meta">Nursing Service Division official report</p></section>
        <form class="toolbar no-print" method="get">
            <div class="form-group"><label>Coverage Start Date and Time</label><input type="datetime-local" name="coverage_start" value="<?php echo h($startInput); ?>"></div>
            <div class="form-group"><label>Coverage End Date and Time</label><input type="datetime-local" name="coverage_end" value="<?php echo h($endInput); ?>"></div>
            <div class="form-group"><label>Prepared By</label><input name="prepared_by" value="<?php echo h($preparedBy); ?>"></div>
            <div class="form-group"><label>Reviewed By</label><input name="reviewed_by" value="<?php echo h($reviewedBy); ?>"></div>
            <div class="form-group"><label>Approved By</label><input name="approved_by" value="<?php echo h($approvedBy); ?>"></div>
            <button class="btn btn-primary">Generate</button>
        </form>
        <div class="report-actions no-print">
            <button class="btn btn-primary" onclick="window.print()">Print / Export PDF</button>
            <a class="btn btn-secondary" href="?<?php echo h(http_build_query(array_merge($_GET, ['export' => 'excel']))); ?>">Export to Excel</a>
        </div>
        <section class="report-document">
            <div class="report-letterhead">
                <img src="../public/images/branding/jvgh_seal.png" alt="JVGH seal">
                <h1>Joni Villanueva General Hospital</h1>
                <h2>Nursing Service Division</h2>
            </div>
            <h1 class="report-title"><?php echo h($report['title']); ?></h1>
            <div class="report-meta">
                <div><strong>Coverage Start</strong><?php echo h($startSql); ?></div>
                <div><strong>Coverage End</strong><?php echo h($endSql); ?></div>
                <div><strong>Prepared By</strong><?php echo h($preparedBy); ?></div>
                <div><strong>Date Generated</strong><?php echo h($generatedAt); ?></div>
                <div><strong>Reviewed By</strong><?php echo h($reviewedBy); ?></div>
                <div><strong>Approved By</strong><?php echo h($approvedBy); ?></div>
            </div>
            <?php if (!empty($report['summary'])): ?>
                <div class="report-summary">
                    <?php foreach ($report['summary'] as $label => $value): ?><div class="summary-card"><span><?php echo h($label); ?></span><strong><?php echo h((string) $value); ?></strong></div><?php endforeach; ?>
                </div>
            <?php endif; ?>
            <h2>I. <?php echo h($report['title']); ?></h2>
            <?php renderReportTable($report['columns'], $report['rows']); ?>
            <?php $sectionNo = 2; foreach ($report['extra'] ?? [] as $section): ?>
                <h2><?php echo h(str_repeat('I', $sectionNo)); ?>. <?php echo h($section['title']); ?></h2>
                <?php renderReportTable($section['columns'], $section['rows']); ?>
            <?php $sectionNo++; endforeach; ?>
            <div class="signature-grid">
                <div class="signature-box">Prepared by<br><?php echo h($preparedBy); ?></div>
                <div class="signature-box">Reviewed by<br><?php echo h($reviewedBy); ?></div>
                <div class="signature-box">Approved by<br><?php echo h($approvedBy); ?></div>
            </div>
        </section>
    </main>
</div>
<script src="../public/js/sidebar.js"></script>
</body>
</html>
