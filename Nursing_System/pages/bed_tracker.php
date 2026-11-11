<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';

requireLogin();
requireRole(array_merge(admissionRoles(), ['staff_nurse']));
ensurePatientWorkflowSchema($conn);
ensureEmergencyRoomBeds($conn);
$bedOccupyingStatuses = "'" . implode("','", bedOccupyingPatientStatuses()) . "'";

$beds_query = "SELECT 
    b.bed_id,
    b.bed_number,
    b.room_number,
    b.bed_type,
    b.status,
    p.patient_id,
    p.patient_code,
    p.queue_number,
    p.age,
    p.gender,
    p.phone,
    p.status as patient_status,
    p.final_diagnosis,
    p.specific_diagnosis,
    COALESCE(CONCAT(p.last_name, ', ', p.first_name, IFNULL(CONCAT(' ', p.middle_name), '')), 'Available') as patient_name,
    t.triage_level,
    t.chief_complaint,
    t.assessment
FROM beds b
LEFT JOIN patients p ON b.bed_id = p.current_bed AND p.status IN ($bedOccupyingStatuses)
LEFT JOIN triage t ON p.patient_id = t.patient_id
ORDER BY b.room_number, b.bed_number";

$beds = $conn->query($beds_query)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bed Tracker - Nursing Service System</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css">
    <style>
        .bed-tracker-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }
        .bed-tracker-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: var(--shadow);
            border-top: 4px solid #4CAF50;
            padding: 18px;
        }
        .bed-tracker-card.occupied {
            border-top-color: #ff9800;
        }
        .bed-tracker-card.maintenance {
            border-top-color: #777;
        }
        .bed-tracker-card h3 {
            margin-bottom: 12px;
            color: #2c3e50;
        }
        .bed-detail-row {
            display: grid;
            grid-template-columns: 110px 1fr;
            gap: 8px;
            padding: 7px 0;
            border-bottom: 1px solid #eef2f5;
            font-size: 13px;
        }
        .bed-detail-row:last-child {
            border-bottom: 0;
        }
        .bed-detail-row span:first-child {
            color: #667;
            font-weight: 700;
        }
        .bed-status-pill {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            background: #eef6ff;
            color: #1565c0;
            font-weight: 700;
        }
    </style>
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
            <section class="beds-section">
                <h2>Bed Tracker</h2>
                <div class="bed-tracker-grid">
                    <?php if (empty($beds)): ?>
                        <p>No beds found</p>
                    <?php else: ?>
                        <?php foreach ($beds as $bed): ?>
                            <div class="bed-tracker-card <?php echo htmlspecialchars($bed['status']); ?>">
                                <h3><?php echo htmlspecialchars($bed['bed_number']); ?></h3>
                                <div class="bed-detail-row"><span>Room</span><strong><?php echo htmlspecialchars($bed['room_number']); ?></strong></div>
                                <div class="bed-detail-row"><span>Bed Type</span><strong><?php echo htmlspecialchars($bed['bed_type']); ?></strong></div>
                                <div class="bed-detail-row"><span>Bed Status</span><strong class="bed-status-pill"><?php echo ucfirst(htmlspecialchars($bed['status'])); ?></strong></div>
                                <?php if (!empty($bed['patient_id'])): ?>
                                    <div class="bed-detail-row"><span>Patient</span><strong><?php echo htmlspecialchars($bed['patient_name']); ?></strong></div>
                                    <div class="bed-detail-row"><span>Patient ID</span><strong><?php echo htmlspecialchars($bed['patient_code'] ?? 'N/A'); ?></strong></div>
                                    <div class="bed-detail-row"><span>Queue No.</span><strong><?php echo htmlspecialchars(formatQueueNumber($bed['queue_number'] ?? null)); ?></strong></div>
                                    <div class="bed-detail-row"><span>Details</span><strong><?php echo htmlspecialchars(($bed['age'] ?? 'N/A') . ' yrs / ' . ($bed['gender'] ?? 'N/A')); ?></strong></div>
                                    <div class="bed-detail-row"><span>Phone</span><strong><?php echo htmlspecialchars($bed['phone'] ?? 'N/A'); ?></strong></div>
                                    <div class="bed-detail-row"><span>Status</span><strong><?php echo htmlspecialchars(statusLabel($bed['patient_status'] ?? '')); ?></strong></div>
                                    <div class="bed-detail-row"><span>Triage</span><strong><?php echo htmlspecialchars(triageLevelLabel($bed['triage_level'] ?? '')); ?></strong></div>
                                    <div class="bed-detail-row"><span>Complaint</span><strong><?php echo htmlspecialchars($bed['chief_complaint'] ?? 'N/A'); ?></strong></div>
                                    <div class="bed-detail-row"><span>Diagnosis</span><strong><?php echo htmlspecialchars($bed['final_diagnosis'] ?? 'Pending'); ?></strong></div>
                                    <div class="bed-detail-row"><span>Specific Dx</span><strong><?php echo htmlspecialchars($bed['specific_diagnosis'] ?? 'N/A'); ?></strong></div>
                                    <div style="margin-top: 12px;">
                                        <a href="patient_detail.php?id=<?php echo (int) $bed['patient_id']; ?>" class="btn btn-sm btn-info">View Patient</a>
                                    </div>
                                <?php else: ?>
                                    <div class="bed-detail-row"><span>Patient</span><strong>Available</strong></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

    <script src="../public/js/sidebar.js"></script>
</body>
</html>
