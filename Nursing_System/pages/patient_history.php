<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';

requireLogin();
requireRole(array_merge(doctorRoles(), nurseRoles(), admissionRoles()));
ensurePatientWorkflowSchema($conn);

$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT
        p.*,
        COALESCE((
            SELECT COALESCE(pt.status_ended_at, pt.created_at)
            FROM patient_tracking pt
            WHERE pt.patient_id = p.patient_id
                AND pt.action = 'Status Updated'
                AND pt.to_status IN ('discharge', 'discharged', 'trans_in', 'trans_out', 'referred', 'ama', 'hama', 'thoc', 'absconded', 'dama', 'doa', 'death', 'expired', 'er_death')
            ORDER BY COALESCE(pt.status_ended_at, pt.created_at) DESC
            LIMIT 1
        ), CASE WHEN p.status IN ('discharge', 'trans_in', 'trans_out', 'referred', 'ama', 'hama', 'thoc', 'absconded', 'dama', 'doa', 'death', 'expired', 'er_death') THEN p.updated_at ELSE NULL END) as ended_at
    FROM patients p
    WHERE 1 = 1
";

if ($search !== '') {
    $term = '%' . $search . '%';
    $sql .= " AND (
        p.permanent_patient_id LIKE ?
        OR p.patient_code LIKE ?
        OR p.first_name LIKE ?
        OR p.last_name LIKE ?
        OR p.middle_name LIKE ?
        OR p.final_diagnosis LIKE ?
        OR p.specific_diagnosis LIKE ?
    )";
    $stmt = $conn->prepare($sql . " ORDER BY COALESCE(p.arrival_time, p.admission_date, p.created_at) DESC LIMIT 500");
    $stmt->bind_param("sssssss", $term, $term, $term, $term, $term, $term, $term);
    $stmt->execute();
    $historyRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $historyRows = $conn->query($sql . " ORDER BY COALESCE(p.arrival_time, p.admission_date, p.created_at) DESC LIMIT 500")->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient History - Nursing Information System</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css">
<link rel="stylesheet" href="../public/css/module-theme.css?v=18">
<script defer src="../public/js/nursync-ui.js?v=4"></script></head>
<body class="module-ui">
    <nav class="navbar">
        <div class="navbar-brand"><div class="navbar-title-stack"><h1>Joni Villanueva General Hospital</h1><span class="navbar-system-subtitle">Nursing Information System</span></div></div>
        <div class="navbar-menu">
            <span class="user-info">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?></span>
            <a href="../api/logout.php" class="btn btn-sm btn-danger">Logout</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>

        <main class="main-content">
            <section class="patients-section">
                <h2>Patient History</h2>
                <form method="GET" class="log-search-form">
                    <input type="text" name="search" placeholder="Search patient ID, encounter ID, name, or diagnosis" value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="patient_history.php" class="btn btn-secondary">Clear</a>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Visit Date / Time</th>
                                <th>Patient ID</th>
                                <th>Encounter ID</th>
                                <th>Name</th>
                                <th>Age / Sex</th>
                                <th>Status</th>
                                <th>Finalized At</th>
                                <th>Final Diagnosis</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($historyRows)): ?>
                                <tr><td colspan="9" class="text-center">No patient visits found</td></tr>
                            <?php else: ?>
                                <?php foreach ($historyRows as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($row['arrival_time'] ?? $row['admission_date'] ?? $row['created_at']))); ?></td>
                                        <td><?php echo htmlspecialchars($row['permanent_patient_id'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['patient_code']); ?></td>
                                        <td><?php echo htmlspecialchars(patientFullName($row)); ?></td>
                                        <td><?php echo htmlspecialchars(($row['age'] ?? calculateAge($row['date_of_birth'])) . ' / ' . ($row['gender'] ?? 'N/A')); ?></td>
                                        <td><?php echo htmlspecialchars(statusLabel($row['status'] ?? '')); ?></td>
                                        <td><?php echo !empty($row['ended_at']) ? htmlspecialchars(date('M d, Y H:i', strtotime($row['ended_at']))) : 'Ongoing'; ?></td>
                                        <td><?php echo htmlspecialchars($row['final_diagnosis'] ?? 'N/A'); ?></td>
                                        <td><a href="patient_detail.php?id=<?php echo (int) $row['patient_id']; ?>" class="btn btn-sm btn-info">View</a></td>
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
</body>
</html>
