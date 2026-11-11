<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';
require_once '../includes/opd_helpers.php';

requireLogin();
requirePermission('opd', 'view', 'OPD');
ensureOpdSchema($conn);

$filter = $_GET['disposition'] ?? '';
$where = "o.status <> 'ARCHIVED'";
$params = [];
$types = '';
if ($filter !== '' && isset(opdDispositionOptions()[$filter])) {
    $where .= " AND o.disposition = ?";
    $params[] = $filter;
    $types .= 's';
}
$rows = opdRecordRows($conn, $where, $params, $types);
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>OPD Patients</title><link rel="stylesheet" href="../public/css/style.css"><link rel="stylesheet" href="../public/css/dashboard.css"><link rel="stylesheet" href="../public/css/module-theme.css?v=18">
<script defer src="../public/js/nursync-ui.js?v=4"></script></head>
<body class="module-ui">
<nav class="navbar"><div class="navbar-brand"><div class="navbar-title-stack"><h1>Joni Villanueva General Hospital</h1><span class="navbar-system-subtitle">Nursing Information System</span></div></div><div class="navbar-menu"><span class="user-info">Welcome, <?php echo h($_SESSION['first_name'] ?? $_SESSION['username']); ?></span></div></nav>
<div class="dashboard-container"><?php include '../includes/sidebar.php'; ?><main class="main-content">
<section class="page-header"><h2>OPD Patients</h2><p class="compact-meta">OPD consultation records. Permanent deletes are not allowed.</p><div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px;"><a class="btn btn-primary" href="opd_consultation.php">New OPD Consultation</a><a class="btn btn-info" href="movement_history.php">View Movement History</a></div></section>
<section class="patients-section"><div class="table-responsive"><table class="table table-striped"><thead><tr><th>OPD No.</th><th>Patient</th><th>Clinic</th><th>Complaint</th><th>Diagnosis</th><th>Disposition</th><th>Follow-Up</th><th>Status</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><?php echo h($row['opd_number']); ?></td><td><?php echo h($row['patient_name']); ?><br><small><?php echo h($row['permanent_patient_id']); ?> / <?php echo h($row['encounter_id']); ?></small></td><td><?php echo h($row['clinic_type']); ?></td><td><?php echo h($row['chief_complaint']); ?></td><td><?php echo h($row['diagnosis']); ?></td><td><?php echo h($row['disposition']); ?></td><td><?php echo h($row['follow_up_date'] ?: 'N/A'); ?></td><td><?php echo h($row['status']); ?></td></tr><?php endforeach; ?>
<?php if (empty($rows)): ?><tr><td colspan="8" class="text-center">No OPD records found.</td></tr><?php endif; ?>
</tbody></table></div></section>
</main></div><script src="../public/js/sidebar.js"></script></body></html>
