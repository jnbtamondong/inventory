<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';

requireLogin();
ensurePatientWorkflowSchema($conn);

$area = $_GET['area'] ?? 'emergency_room';
$area = $area === 'ward' ? 'ward' : 'emergency_room';
if (isWardLimitedUser() && $area !== 'ward') {
    header('Location: discharge_patients.php?area=ward');
    exit();
}
$isWardArea = $area === 'ward';
$areaLabel = $isWardArea ? 'Ward' : 'Emergency Room';

if ($isWardArea) {
    requireRole(array_merge(nurseRoles(), doctorRoles(), admissionRoles()));
    requireDepartment(['WARD_2F', 'WARD_3F', 'ICU', 'NICU']);
} else {
    requireRole('admin');
}

$search = trim($_GET['search'] ?? '');
$error = '';
$success = '';
consumeFlashMessages($error, $success);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_patient'])) {
    if (!hasRole('admin')) {
        $error = 'Only admin users can delete discharged patients.';
    } else {
        $patient_id = intval($_POST['patient_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM patients WHERE patient_id = ? AND status IN ('discharge', 'trans_in', 'trans_out', 'referred', 'ama', 'hama', 'thoc', 'absconded', 'dama', 'doa', 'death', 'expired', 'er_death')");
        $stmt->bind_param("i", $patient_id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $success = 'Discharged patient deleted successfully.';
        } else {
            $error = 'Unable to delete discharged patient.';
        }
    }

    if ($error) {
        setFlashMessage('error', $error);
    }
    if ($success) {
        setFlashMessage('success', $success);
    }
    redirectAfterPost('discharge_patients.php?area=' . urlencode($area));
}

$sql = "
    SELECT
        p.*,
        (
            SELECT pt.created_at
            FROM patient_tracking pt
            WHERE pt.patient_id = p.patient_id
                AND pt.to_status IN ('discharge', 'trans_in', 'trans_out', 'referred', 'ama', 'hama', 'thoc', 'absconded', 'dama', 'doa', 'death', 'expired', 'er_death')
            ORDER BY pt.created_at DESC
            LIMIT 1
        ) as discharged_at
    FROM patients p
    WHERE p.status IN ('discharge', 'trans_in', 'trans_out', 'referred', 'ama', 'hama', 'thoc', 'absconded', 'dama', 'doa', 'death', 'expired', 'er_death')
";

$sql .= $isWardArea
    ? " AND EXISTS (
            SELECT 1
            FROM patient_tracking ward_pt
            WHERE ward_pt.patient_id = p.patient_id
                AND ward_pt.to_status = 'admit_inpatient'
        )"
    : " AND NOT EXISTS (
            SELECT 1
            FROM patient_tracking ward_pt
            WHERE ward_pt.patient_id = p.patient_id
                AND ward_pt.to_status = 'admit_inpatient'
        )";

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
    <title><?php echo htmlspecialchars($areaLabel); ?> Discharged Patients - Nursing Information System</title>
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
                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

                <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 20px;">
                    <h2><?php echo htmlspecialchars($areaLabel); ?> - Discharged Patients</h2>
                </div>

                <form method="GET" class="log-search-form">
                    <input type="hidden" name="area" value="<?php echo htmlspecialchars($area); ?>">
                    <input type="text" name="search" placeholder="Search encounter ID, patient ID, or name" value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="discharge_patients.php?area=<?php echo htmlspecialchars($area); ?>" class="btn btn-secondary">Clear</a>
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
                                <tr><td colspan="8" class="text-center">No <?php echo htmlspecialchars(strtolower($areaLabel)); ?> discharged patients found</td></tr>
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
