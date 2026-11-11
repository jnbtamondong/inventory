<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';

requireLogin();
requireRole('admin');
ensurePatientWorkflowSchema($conn);

$search = trim($_GET['search'] ?? '');
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_patient'])) {
    if (!hasRole('admin')) {
        $error = 'Only admin users can delete discharged patients.';
    } else {
        $patient_id = intval($_POST['patient_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM patients WHERE patient_id = ? AND status IN ('discharge', 'referred', 'ama', 'thoc', 'absconded', 'dama', 'doa', 'expired', 'er_death')");
        $stmt->bind_param("i", $patient_id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $success = 'Discharged patient deleted successfully.';
        } else {
            $error = 'Unable to delete discharged patient.';
        }
    }
}

$sql = "
    SELECT
        p.*,
        (
            SELECT pt.created_at
            FROM patient_tracking pt
            WHERE pt.patient_id = p.patient_id
                AND pt.action = 'Status Updated'
                AND pt.to_status IN ('discharge', 'referred', 'ama', 'thoc', 'absconded', 'dama', 'doa', 'expired', 'er_death')
            ORDER BY pt.created_at DESC
            LIMIT 1
        ) as discharged_at
    FROM patients p
    WHERE p.status IN ('discharge', 'referred', 'ama', 'thoc', 'absconded', 'dama', 'doa', 'expired', 'er_death')
";

if ($search !== '') {
    $term = '%' . $search . '%';
    $sql .= " AND (p.patient_code LIKE ? OR p.permanent_patient_id LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR p.middle_name LIKE ?)";
    $stmt = $conn->prepare($sql . " ORDER BY COALESCE(discharged_at, p.updated_at) DESC");
    $stmt->bind_param("sssss", $term, $term, $term, $term, $term);
    $stmt->execute();
    $patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $patients = $conn->query($sql . " ORDER BY COALESCE(discharged_at, p.updated_at) DESC")->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discharge Patient - Nursing Service System</title>
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
                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

                <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 20px;">
                    <h2>Emergency Room - Discharge Patient</h2>
                </div>

                <form method="GET" class="log-search-form">
                    <input type="text" name="search" placeholder="Search encounter ID, patient ID, or name" value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="discharge_patients.php" class="btn btn-secondary">Clear</a>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Encounter ID</th>
                                <th>Name</th>
                                <th>Age</th>
                                <th>Sex</th>
                                <th>Contact</th>
                                <th>Disposition</th>
                                <th>Finalized At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($patients)): ?>
                                <tr><td colspan="8" class="text-center">No discharged patients found</td></tr>
                            <?php else: ?>
                                <?php foreach ($patients as $patient): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($patient['patient_code']); ?></td>
                                        <td><?php echo htmlspecialchars(patientFullName($patient)); ?></td>
                                        <td><?php echo htmlspecialchars($patient['age'] ?? calculateAge($patient['date_of_birth'])); ?></td>
                                        <td><?php echo htmlspecialchars($patient['gender']); ?></td>
                                        <td><?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars(statusLabel($patient['status'])); ?></td>
                                        <td><?php echo htmlspecialchars($patient['discharged_at'] ?? $patient['updated_at']); ?></td>
                                        <td>
                                            <a href="patient_detail.php?id=<?php echo $patient['patient_id']; ?>" class="btn btn-sm btn-info">View</a>
                                            <?php if (hasRole('admin')): ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this discharged patient permanently?');">
                                                    <input type="hidden" name="patient_id" value="<?php echo htmlspecialchars($patient['patient_id']); ?>">
                                                    <button type="submit" name="delete_patient" class="btn btn-sm btn-danger">Delete</button>
                                                </form>
                                            <?php endif; ?>
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
</body>
</html>
