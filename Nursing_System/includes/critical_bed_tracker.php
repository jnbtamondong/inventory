<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/patient_helpers.php';
require_once __DIR__ . '/clinical_modules.php';

requireLogin();

$currentPage = basename($_SERVER['SCRIPT_NAME']);
$module = clinicalModuleByPage($currentPage);
if (!$module || !in_array($module['key'], ['micu', 'nicu'], true)) {
    header('Location: dashboard.php');
    exit();
}

requirePermission($module['permission'], 'view', $module['department']);
ensureClinicalModuleSchema($conn);

$canEditModule = hasPermission($module['permission'], 'edit', $module['department']);
$error = '';
$success = '';
consumeFlashMessages($error, $success);

$table = $module['table'];
$areaName = $module['key'] === 'micu' ? 'ICU / MICU' : 'NICU';
$bedPrefix = $module['key'] === 'micu' ? 'MICU' : 'NICU';
$patientKind = $module['key'] === 'micu' ? 'Critical Care Patient' : 'Neonate';

function criticalBedCapacity(mysqli $conn, string $areaName): int {
    $capacity = clinicalScalar($conn, "SELECT total_bed_capacity FROM bed_capacity_settings WHERE area_name=? AND status='ACTIVE'", [$areaName], 's');
    if ($capacity <= 0) {
        $capacity = clinicalDefaultBedCapacities()[$areaName] ?? 5;
    }
    return max(1, $capacity);
}

function criticalBedSlots(string $prefix, int $capacity): array {
    $slots = [];
    for ($i = 1; $i <= $capacity; $i++) {
        $slots[] = $prefix . '-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
    }
    return $slots;
}

function criticalTrackerRecords(mysqli $conn, string $moduleKey, string $table): array {
    $extraSelect = $moduleKey === 'nicu'
        ? "r.baby_type, r.inborn_or_outborn, mother.patient_code AS mother_code, CONCAT(mother.last_name, ', ', mother.first_name) AS mother_name,"
        : "r.admission_source,";

    $extraJoin = $moduleKey === 'nicu'
        ? "LEFT JOIN patients mother ON mother.patient_id = r.mother_patient_id"
        : "";

    return clinicalRows($conn, "
        SELECT
            r.*,
            $extraSelect
            p.permanent_patient_id,
            p.patient_code,
            p.age,
            p.gender,
            CONCAT(p.last_name, ', ', p.first_name, IFNULL(CONCAT(' ', p.middle_name), '')) AS patient_name
        FROM $table r
        INNER JOIN patients p ON p.patient_id = r.patient_id
        $extraJoin
        WHERE r.status='ACTIVE'
        ORDER BY
            CASE WHEN r.bed_no IS NULL OR r.bed_no='' THEN 1 ELSE 0 END,
            r.bed_no,
            r.admission_date_time DESC
    ");
}

function criticalPatientSummary(array $record, string $moduleKey): string {
    if ($moduleKey === 'nicu') {
        $parts = array_filter([$record['baby_type'] ?? '', $record['inborn_or_outborn'] ?? '']);
        return $parts ? implode(' / ', $parts) : 'NICU patient';
    }
    return $record['admission_source'] ? 'From ' . $record['admission_source'] : 'MICU patient';
}

$capacity = criticalBedCapacity($conn, $areaName);
$bedSlots = criticalBedSlots($bedPrefix, $capacity);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEditModule) {
        $error = 'You do not have permission to update this bed tracker.';
    } elseif (isset($_POST['assign_critical_bed'])) {
        $recordId = (int) ($_POST['record_id'] ?? 0);
        $bedNo = sanitizeText($_POST['bed_no'] ?? '', 40);

        if ($recordId <= 0 || !in_array($bedNo, $bedSlots, true)) {
            $error = 'Please select a valid patient and bed.';
        } else {
            $record = clinicalRows($conn, "SELECT id, patient_id, encounter_id, bed_no FROM $table WHERE id=? AND status='ACTIVE'", [$recordId], 'i')[0] ?? null;
            $occupied = clinicalRows($conn, "SELECT id FROM $table WHERE bed_no=? AND status='ACTIVE' AND id<>? LIMIT 1", [$bedNo, $recordId], 'si');

            if (!$record) {
                $error = 'Active patient record not found.';
            } elseif ($occupied) {
                $error = 'Selected bed is already occupied.';
            } else {
                $stmt = $conn->prepare("UPDATE $table SET bed_no=? WHERE id=? AND status='ACTIVE'");
                $stmt->bind_param("si", $bedNo, $recordId);
                if ($stmt->execute()) {
                    $trackStmt = $conn->prepare("INSERT INTO patient_tracking (patient_id, action, description, user_id) VALUES (?, ?, ?, ?)");
                    $action = $module['short'] . ' Bed Assigned';
                    $description = 'Assigned to ' . $bedNo;
                    $userId = $_SESSION['user_id'] ?? null;
                    $trackStmt->bind_param("issi", $record['patient_id'], $action, $description, $userId);
                    $trackStmt->execute();
                    clinicalAudit($conn, $action, $module['label'], (int) $record['patient_id'], $record['encounter_id'] ?? null, $record['bed_no'] ?? null, $bedNo);
                    $success = $bedNo . ' assigned successfully.';
                } else {
                    $error = 'Unable to assign bed.';
                }
            }
        }
    } elseif (isset($_POST['release_critical_bed'])) {
        $recordId = (int) ($_POST['record_id'] ?? 0);
        $record = $recordId > 0 ? (clinicalRows($conn, "SELECT id, patient_id, encounter_id, bed_no FROM $table WHERE id=? AND status='ACTIVE'", [$recordId], 'i')[0] ?? null) : null;

        if (!$record) {
            $error = 'Active patient record not found.';
        } else {
            $stmt = $conn->prepare("UPDATE $table SET bed_no=NULL WHERE id=? AND status='ACTIVE'");
            $stmt->bind_param("i", $recordId);
            if ($stmt->execute()) {
                $trackStmt = $conn->prepare("INSERT INTO patient_tracking (patient_id, action, description, user_id) VALUES (?, ?, ?, ?)");
                $action = $module['short'] . ' Bed Released';
                $description = 'Released from ' . ($record['bed_no'] ?: 'unassigned bed');
                $userId = $_SESSION['user_id'] ?? null;
                $trackStmt->bind_param("issi", $record['patient_id'], $action, $description, $userId);
                $trackStmt->execute();
                clinicalAudit($conn, $action, $module['label'], (int) $record['patient_id'], $record['encounter_id'] ?? null, $record['bed_no'] ?? null, null);
                $success = 'Bed released successfully.';
            } else {
                $error = 'Unable to release bed.';
            }
        }
    }

    if ($error) { setFlashMessage('error', $error); }
    if ($success) { setFlashMessage('success', $success); }
    redirectAfterPost($currentPage);
}

$records = criticalTrackerRecords($conn, $module['key'], $table);
$recordsByBed = [];
$unassignedRecords = [];
$customBedRecords = [];

foreach ($records as $record) {
    $bedNo = trim((string) ($record['bed_no'] ?? ''));
    if ($bedNo === '') {
        $unassignedRecords[] = $record;
    } elseif (in_array($bedNo, $bedSlots, true)) {
        $recordsByBed[$bedNo][] = $record;
    } else {
        $customBedRecords[] = $record;
    }
}

$occupiedCount = 0;
foreach ($bedSlots as $bedNo) {
    if (!empty($recordsByBed[$bedNo])) {
        $occupiedCount++;
    }
}
$availableCount = max(0, $capacity - $occupiedCount);
$activeCount = count($records);
$occupancyPct = $capacity > 0 ? round(($occupiedCount / $capacity) * 100) : 0;
$pageTitle = $module['short'] . ' Bed Tracker';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pageTitle); ?> - Nursing Information System</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css">
    <style>
        .critical-bed-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(285px, 1fr));
            gap: 16px;
        }
        .critical-bed-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: var(--shadow);
            border-top: 4px solid #328CC1;
            padding: 18px;
            min-height: 244px;
        }
        .critical-bed-card.occupied { border-top-color: #D32F2F; }
        .critical-bed-card.conflict { border-top-color: #F59E0B; }
        .critical-bed-card h3 {
            margin: 0 0 10px;
            color: #0B3C5D;
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: center;
        }
        .bed-status-pill {
            display: inline-flex;
            padding: 4px 9px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            background: #EAF6FF;
            color: #0B3C5D;
            text-transform: uppercase;
        }
        .critical-bed-card.occupied .bed-status-pill {
            background: #FDECEC;
            color: #B42318;
        }
        .critical-bed-card.conflict .bed-status-pill {
            background: #FFF7E6;
            color: #9A5B10;
        }
        .critical-patient {
            border: 1px solid #E1E7EF;
            border-radius: 8px;
            padding: 12px;
            margin-top: 10px;
            background: #FAFCFF;
        }
        .critical-patient strong { color: #0B3C5D; }
        .critical-meta {
            display: grid;
            gap: 5px;
            margin-top: 8px;
            color: #526477;
            font-size: 13px;
        }
        .critical-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px;
        }
        .critical-assignment-panel {
            background: #fff;
            border: 1px solid #E1E7EF;
            border-radius: 8px;
            padding: 18px;
            box-shadow: var(--shadow);
            margin-top: 18px;
        }
        .critical-assignment-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            align-items: end;
        }
        .occupancy-meter {
            width: 100%;
            height: 10px;
            border-radius: 999px;
            background: #E8EEF5;
            overflow: hidden;
            margin-top: 8px;
        }
        .occupancy-meter span {
            display: block;
            height: 100%;
            background: #1E88E5;
        }
        @media (max-width: 860px) {
            .critical-assignment-grid { grid-template-columns: 1fr; }
        }
    </style>
<link rel="stylesheet" href="../public/css/module-theme.css?v=18">
<script defer src="../public/js/nursync-ui.js?v=4"></script></head>
<body class="module-ui">
<nav class="navbar">
    <div class="navbar-brand"><div class="navbar-title-stack"><h1>Joni Villanueva General Hospital</h1><span class="navbar-system-subtitle">Nursing Information System</span></div></div>
    <div class="navbar-menu"><span class="user-info">Welcome, <?php echo h($_SESSION['first_name'] ?? $_SESSION['username']); ?></span></div>
</nav>
<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    <main class="main-content">
        <section class="page-header">
            <div class="jvgh-brand dashboard-heading">
                <div>
                    <h2><?php echo h($pageTitle); ?></h2>
                    <p class="compact-meta"><?php echo h($areaName); ?> live bed occupancy from active <?php echo h($module['short']); ?> records.</p>
                </div>
                <div class="module-actions">
                    <a href="<?php echo h($module['key']); ?>_patients.php?module=<?php echo h($module['key']); ?>" class="btn btn-secondary"><i class="fa-solid fa-users"></i> Patients</a>
                    <a href="<?php echo h($module['key']); ?>_dashboard.php?module=<?php echo h($module['key']); ?>" class="btn btn-info"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
                </div>
            </div>
        </section>

        <?php if ($error): ?><div class="alert alert-danger"><?php echo h($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo h($success); ?></div><?php endif; ?>

        <section class="stats-grid">
            <div class="stat-card"><div class="stat-info"><span class="stat-label">Capacity Beds</span><span class="stat-value"><?php echo h($capacity); ?></span></div></div>
            <div class="stat-card available"><div class="stat-info"><span class="stat-label">Available Beds</span><span class="stat-value"><?php echo h($availableCount); ?></span></div></div>
            <div class="stat-card occupied"><div class="stat-info"><span class="stat-label">Occupied Beds</span><span class="stat-value"><?php echo h($occupiedCount); ?></span></div></div>
            <div class="stat-card waiting"><div class="stat-info"><span class="stat-label">Unassigned Active Patients</span><span class="stat-value"><?php echo h(count($unassignedRecords)); ?></span></div></div>
            <div class="stat-card"><div class="stat-info"><span class="stat-label">Active <?php echo h($module['short']); ?> Records</span><span class="stat-value"><?php echo h($activeCount); ?></span><div class="occupancy-meter"><span style="width: <?php echo h(min(100, $occupancyPct)); ?>%;"></span></div></div></div>
        </section>

        <?php if ($canEditModule): ?>
            <section class="critical-assignment-panel">
                <h3>Assign Patient to Bed</h3>
                <form method="POST" class="critical-assignment-grid">
                    <input type="hidden" name="assign_critical_bed" value="1">
                    <div class="form-group">
                        <label><?php echo h($patientKind); ?></label>
                        <select name="record_id" required>
                            <option value="">Select active patient</option>
                            <?php foreach ($records as $record): ?>
                                <option value="<?php echo (int) $record['id']; ?>">
                                    <?php echo h($record['patient_name'] . ' - ' . ($record['permanent_patient_id'] ?: $record['patient_code']) . (($record['bed_no'] ?? '') ? ' (' . $record['bed_no'] . ')' : ' (Unassigned)')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Bed</label>
                        <select name="bed_no" required>
                            <option value="">Select bed</option>
                            <?php foreach ($bedSlots as $bedNo): ?>
                                <?php $isOccupied = !empty($recordsByBed[$bedNo]); ?>
                                <option value="<?php echo h($bedNo); ?>" <?php echo $isOccupied ? 'disabled' : ''; ?>>
                                    <?php echo h($bedNo . ($isOccupied ? ' - Occupied' : ' - Available')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-bed-pulse"></i> Assign Bed</button>
                </form>
            </section>
        <?php endif; ?>

        <section class="page-header" style="margin-top:18px;">
            <h2>Bed Layout</h2>
            <p class="compact-meta">Bed slots follow the configured <?php echo h($areaName); ?> capacity.</p>
        </section>

        <section class="critical-bed-grid">
            <?php foreach ($bedSlots as $bedNo): ?>
                <?php
                    $assignedRecords = $recordsByBed[$bedNo] ?? [];
                    $isOccupied = !empty($assignedRecords);
                    $hasConflict = count($assignedRecords) > 1;
                ?>
                <article class="critical-bed-card <?php echo $isOccupied ? 'occupied' : 'available'; ?> <?php echo $hasConflict ? 'conflict' : ''; ?>">
                    <h3>
                        <?php echo h($bedNo); ?>
                        <span class="bed-status-pill"><?php echo $hasConflict ? 'Conflict' : ($isOccupied ? 'Occupied' : 'Available'); ?></span>
                    </h3>
                    <?php if (!$isOccupied): ?>
                        <div class="empty-state">No active patient assigned.</div>
                    <?php else: ?>
                        <?php foreach ($assignedRecords as $record): ?>
                            <div class="critical-patient">
                                <strong><?php echo h($record['patient_name']); ?></strong>
                                <div class="critical-meta">
                                    <span><?php echo h($record['permanent_patient_id'] ?: $record['patient_code']); ?></span>
                                    <span><?php echo h(criticalPatientSummary($record, $module['key'])); ?></span>
                                    <span>Admitted: <?php echo h(date('M d, Y h:i A', strtotime($record['admission_date_time']))); ?></span>
                                    <?php if (!empty($record['diagnosis'])): ?><span>Diagnosis: <?php echo h($record['diagnosis']); ?></span><?php endif; ?>
                                    <?php if ($module['key'] === 'nicu' && !empty($record['mother_name'])): ?><span>Mother: <?php echo h($record['mother_name']); ?></span><?php endif; ?>
                                </div>
                                <?php if ($canEditModule): ?>
                                    <form method="POST" class="critical-actions">
                                        <input type="hidden" name="release_critical_bed" value="1">
                                        <input type="hidden" name="record_id" value="<?php echo (int) $record['id']; ?>">
                                        <button class="btn btn-sm btn-secondary" type="submit">Release Bed</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>

        <?php if ($unassignedRecords || $customBedRecords): ?>
            <section class="critical-assignment-panel">
                <h3>Needs Review</h3>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead><tr><th>Patient</th><th>Current Bed Entry</th><th>Admission</th><th>Diagnosis / Type</th></tr></thead>
                        <tbody>
                            <?php foreach (array_merge($unassignedRecords, $customBedRecords) as $record): ?>
                                <tr>
                                    <td><?php echo h($record['patient_name']); ?><br><small><?php echo h($record['permanent_patient_id'] ?: $record['patient_code']); ?></small></td>
                                    <td><?php echo h(($record['bed_no'] ?? '') ?: 'Unassigned'); ?></td>
                                    <td><?php echo h(date('M d, Y h:i A', strtotime($record['admission_date_time']))); ?></td>
                                    <td><?php echo h($record['diagnosis'] ?: criticalPatientSummary($record, $module['key'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </main>
</div>
<script src="../public/js/sidebar.js"></script>
</body>
</html>
