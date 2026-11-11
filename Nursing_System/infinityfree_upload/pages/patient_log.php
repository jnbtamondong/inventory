<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';

requireLogin();
requireRole(array_merge(nurseRoles(), admissionRoles()));
ensurePatientWorkflowSchema($conn);

$search = trim($_GET['search'] ?? '');
$dispositionStatuses = inactivePatientStatuses();

$sql = "
    SELECT p.*, t.triage_level, b.bed_number, COALESCE(p.admission_date, p.created_at, p.arrival_time) as log_date
    FROM patients p
    LEFT JOIN triage t ON p.patient_id = t.patient_id
    LEFT JOIN beds b ON p.current_bed = b.bed_id
    WHERE 1 = 1
";

if ($search !== '') {
    $term = '%' . $search . '%';
    $sql .= " AND (
        p.patient_code LIKE ?
        OR p.first_name LIKE ?
        OR p.last_name LIKE ?
        OR p.middle_name LIKE ?
        OR p.municipality_name LIKE ?
        OR p.province_name LIKE ?
    )";
    $stmt = $conn->prepare($sql . " ORDER BY log_date DESC, p.patient_id DESC");
    $stmt->bind_param("ssssss", $term, $term, $term, $term, $term, $term);
    $stmt->execute();
    $patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $patients = $conn->query($sql . " ORDER BY log_date DESC, p.patient_id DESC")->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Room Masterlist - Nursing Service System</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand"><h1>Nursing Service System</h1></div>
        <div class="navbar-menu">
            <span class="user-info">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?></span>
            <a href="../api/logout.php" class="btn btn-sm btn-danger">Logout</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>

        <main class="main-content">
            <section class="patients-section">
                <h2>Emergency Room Patient Masterlist</h2>
                <form method="GET" class="log-search-form">
                    <input type="text" name="search" placeholder="Search patient ID, name, municipality, or province" value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="patient_log.php" class="btn btn-secondary">Clear</a>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Patient ID</th>
                                <th>Name</th>
                                <th>Age</th>
                                <th>Sex</th>
                                <th>Triage Level</th>
                                <th>Disposition</th>
                                <th>Date Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($patients)): ?>
                                <tr><td colspan="8" class="text-center">No patient log records found</td></tr>
                            <?php else: ?>
                                <?php foreach ($patients as $patient): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($patient['patient_code']); ?></td>
                                        <td><?php echo htmlspecialchars(patientFullName($patient)); ?></td>
                                        <td><?php echo htmlspecialchars($patient['age']); ?></td>
                                        <td><?php echo htmlspecialchars($patient['gender']); ?></td>
                                        <td><?php echo htmlspecialchars(triageLevelLabel($patient['triage_level'] ?? '')); ?></td>
                                        <td>
                                            <?php echo in_array($patient['status'], $dispositionStatuses, true) ? htmlspecialchars(statusLabel($patient['status'])) : 'Pending'; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($patient['log_date']))); ?></td>
                                        <td><a href="patient_detail.php?id=<?php echo $patient['patient_id']; ?>" class="btn btn-sm btn-info">View</a></td>
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
