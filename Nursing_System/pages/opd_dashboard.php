<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';
require_once '../includes/opd_helpers.php';

requireLogin();
requirePermission('opd', 'view', 'OPD');
ensureOpdSchema($conn);

$today = date('Y-m-d');
$total = opdScalar($conn, "SELECT COUNT(*) FROM opd_records WHERE DATE(consultation_date_time)=?", [$today], 's');
$completed = opdScalar($conn, "SELECT COUNT(*) FROM opd_records WHERE disposition='COMPLETED CONSULTATION' AND DATE(consultation_date_time)=?", [$today], 's');
$toEr = opdScalar($conn, "SELECT COUNT(*) FROM opd_records WHERE disposition='REFERRED TO ER' AND status <> 'CANCELLED'");
$toWard = opdScalar($conn, "SELECT COUNT(*) FROM opd_records WHERE disposition='FOR ADMISSION TO WARD' AND status <> 'CANCELLED'");
$toOr = opdScalar($conn, "SELECT COUNT(*) FROM opd_records WHERE disposition='FOR OR SCHEDULE' AND status <> 'CANCELLED'");
$followUp = opdScalar($conn, "SELECT COUNT(*) FROM opd_records WHERE disposition='FOR FOLLOW-UP' AND status <> 'CANCELLED'");
$other = opdScalar($conn, "SELECT COUNT(*) FROM opd_records WHERE disposition='REFERRED TO OTHER FACILITY' AND status <> 'CANCELLED'");
$recent = opdRecordRows($conn, 'o.status <> ?', ['ARCHIVED'], 's');
$recent = array_slice($recent, 0, 10);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OPD Dashboard - Nursing Information System</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css">
<link rel="stylesheet" href="../public/css/module-theme.css?v=18">
<script defer src="../public/js/nursync-ui.js?v=4"></script></head>
<body class="module-ui">
    <nav class="navbar"><div class="navbar-brand"><div class="navbar-title-stack"><h1>Joni Villanueva General Hospital</h1><span class="navbar-system-subtitle">Nursing Information System</span></div></div><div class="navbar-menu"><span class="user-info">Welcome, <?php echo h($_SESSION['first_name'] ?? $_SESSION['username']); ?> (<?php echo h(roleLabel($_SESSION['role'])); ?>)</span></div></nav>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        <main class="main-content">
            <section class="page-header">
                <h2>OPD Dashboard</h2>
                <p class="compact-meta">Out Patient Department consultation summary and movement status.</p>
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px;">
                    <a class="btn btn-primary" href="opd_consultation.php">New OPD Consultation</a>
                    <a class="btn btn-info" href="movement_history.php">View Movement History</a>
                </div>
            </section>
            <section class="stats-grid">
                <div class="stat-card"><div class="stat-info"><span class="stat-label">Total OPD Consultations</span><span class="stat-value"><?php echo h($total); ?></span></div></div>
                <div class="stat-card available"><div class="stat-info"><span class="stat-label">Completed</span><span class="stat-value"><?php echo h($completed); ?></span></div></div>
                <div class="stat-card emergency"><div class="stat-info"><span class="stat-label">Referred to ER</span><span class="stat-value"><?php echo h($toEr); ?></span></div></div>
                <div class="stat-card waiting"><div class="stat-info"><span class="stat-label">For Admission</span><span class="stat-value"><?php echo h($toWard); ?></span></div></div>
                <div class="stat-card"><div class="stat-info"><span class="stat-label">For OR Schedule</span><span class="stat-value"><?php echo h($toOr); ?></span></div></div>
                <div class="stat-card"><div class="stat-info"><span class="stat-label">Follow-Up</span><span class="stat-value"><?php echo h($followUp); ?></span></div></div>
                <div class="stat-card"><div class="stat-info"><span class="stat-label">Other Facility</span><span class="stat-value"><?php echo h($other); ?></span></div></div>
            </section>
            <section class="patients-section">
                <h3>Recent OPD Consultations</h3>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead><tr><th>OPD No.</th><th>Patient</th><th>Clinic</th><th>Diagnosis</th><th>Disposition</th><th>Status</th><th>Date/Time</th></tr></thead>
                        <tbody>
                        <?php foreach ($recent as $row): ?>
                            <tr><td><?php echo h($row['opd_number']); ?></td><td><?php echo h($row['patient_name']); ?><br><small><?php echo h($row['permanent_patient_id']); ?></small></td><td><?php echo h($row['clinic_type']); ?></td><td><?php echo h($row['diagnosis']); ?></td><td><?php echo h($row['disposition']); ?></td><td><?php echo h($row['status']); ?></td><td><?php echo h(date('M d, Y h:i A', strtotime($row['consultation_date_time']))); ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (empty($recent)): ?><tr><td colspan="7" class="text-center">No OPD consultations yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
    <script src="../public/js/sidebar.js"></script>
</body>
</html>
