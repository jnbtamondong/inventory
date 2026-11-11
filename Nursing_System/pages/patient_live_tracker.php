<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';

requireLogin();
requireRole(array_merge(nurseRoles(), admissionRoles()));
ensureStatusTrackingSchema($conn);
ensurePatientWorkflowSchema($conn);
$activeStatuses = "'" . implode("','", activePatientStatuses()) . "'";
$currentWaitSql = patientCurrentWaitMinutesSql('p');

$patients_query = "SELECT 
    p.patient_id,
    p.queue_number,
    CONCAT(p.last_name, ', ', p.first_name, IFNULL(CONCAT(' ', p.middle_name), '')) as patient_name,
    p.arrival_time,
    $currentWaitSql as wait_time,
    t.triage_level,
    t.chief_complaint,
    p.status
FROM patients p
LEFT JOIN triage t ON p.patient_id = t.patient_id
WHERE p.status IN ($activeStatuses)
ORDER BY CASE
    WHEN t.triage_level = 'red' THEN 1
    WHEN t.triage_level = 'yellow' THEN 2
    WHEN t.triage_level = 'green' THEN 3
    WHEN t.triage_level = 'black' THEN 4
    ELSE 4
END, p.arrival_time ASC";

$patients = $conn->query($patients_query)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Live Tracker - Nursing Service System</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">
            <h1>Nursing Service System</h1>
        </div>
        <div class="navbar-menu">
            <span class="user-info">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?> (<?php echo htmlspecialchars(roleLabel($_SESSION['role'])); ?>)</span>
            <a href="../api/logout.php" class="btn btn-sm btn-danger">Logout</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>

        <main class="main-content">
            <section class="patients-section">
                <h2>Patient Live Tracker</h2>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Queue No.</th>
                                <th>Patient Name</th>
                                <th>Triage Level</th>
                                <th>Waiting Time</th>
                                <th>Complaint</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($patients)): ?>
                                <tr><td colspan="7" class="text-center">No active patients</td></tr>
                            <?php else: ?>
                                <?php foreach ($patients as $patient): ?>
                                    <tr class="patient-row triage-<?php echo htmlspecialchars($patient['triage_level'] ?? 'none'); ?> <?php echo (int) ($patient['wait_time'] ?? 0) >= 30 ? 'wait-alert-row' : ''; ?>">
                                        <td><strong><?php echo htmlspecialchars(formatQueueNumber($patient['queue_number'] ?? null)); ?></strong></td>
                                        <td><?php echo htmlspecialchars($patient['patient_name']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo htmlspecialchars($patient['triage_level'] ?? 'secondary'); ?>">
                                                <?php echo htmlspecialchars(triageLevelLabel($patient['triage_level'] ?? '')); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="wait-time <?php echo (int) ($patient['wait_time'] ?? 0) >= 30 ? 'wait-long' : ''; ?>">
                                                <?php echo htmlspecialchars(formatWaitTime($patient['wait_time'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($patient['chief_complaint'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars(statusLabel($patient['status'])); ?></td>
                                        <td>
                                            <a href="patient_detail.php?id=<?php echo $patient['patient_id']; ?>" class="btn btn-sm btn-info">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <script src="../public/js/sidebar.js"></script>
    <script src="../public/js/dashboard.js"></script>
</body>
</html>
