<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';

requireLogin();

$reportMonth = $_GET['report_month'] ?? date('Y-m');
$reportArea = $_GET['area'] ?? 'emergency_room';
$reportArea = in_array($reportArea, ['emergency_room', 'ward'], true) ? $reportArea : 'emergency_room';
$reportAreaLabel = $reportArea === 'ward' ? 'Ward' : 'Emergency Room';
$reportAreaCondition = $reportArea === 'ward'
    ? "AND (p.status = 'admit_inpatient' OR EXISTS (SELECT 1 FROM beds area_b WHERE area_b.bed_id = p.current_bed AND area_b.room_number LIKE 'WARD-%'))"
    : "AND p.status <> 'admit_inpatient' AND NOT EXISTS (SELECT 1 FROM beds area_b WHERE area_b.bed_id = p.current_bed AND area_b.room_number LIKE 'WARD-%')";
$monthCheck = DateTime::createFromFormat('Y-m', $reportMonth);
if (!$monthCheck || $monthCheck->format('Y-m') !== $reportMonth) {
    $reportMonth = date('Y-m');
    $monthCheck = new DateTime($reportMonth . '-01');
}
$startDate = $monthCheck->format('Y-m-01');
$endDate = $monthCheck->format('Y-m-t');

$topCasesStmt = $conn->prepare("
    SELECT
        p.final_diagnosis as case_name,
        COUNT(*) as total
    FROM patients p
    WHERE DATE(COALESCE(p.arrival_time, p.admission_date, p.created_at)) BETWEEN ? AND ?
        AND p.final_diagnosis IS NOT NULL
        AND p.final_diagnosis <> ''
        $reportAreaCondition
    GROUP BY case_name
    ORDER BY total DESC, case_name ASC
    LIMIT 10
");
$topCasesStmt->bind_param("ss", $startDate, $endDate);
$topCasesStmt->execute();
$topCases = $topCasesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$totalCases = array_sum(array_column($topCases, 'total'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Top 10 Cases - Nursing Information System</title>
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
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 20px;">
                    <h2><?php echo htmlspecialchars($reportAreaLabel); ?> Monthly Top 10 Diagnosis</h2>
                    <form method="GET" class="report-filter" style="display: flex; align-items: end; gap: 8px;">
                        <input type="hidden" name="area" value="<?php echo htmlspecialchars($reportArea); ?>">
                        <div style="min-width: 170px;">
                            <label for="report-month">Month</label>
                            <input type="month" id="report-month" name="report_month" value="<?php echo htmlspecialchars($reportMonth); ?>" style="height: 38px;">
                        </div>
                        <button type="submit" class="btn btn-primary" style="height: 38px;">View</button>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Case</th>
                                <th>Total</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($topCases)): ?>
                                <tr><td colspan="4" class="text-center">No cases found</td></tr>
                            <?php else: ?>
                                <?php foreach ($topCases as $index => $case): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($case['case_name']); ?></td>
                                        <td class="number-cell"><?php echo htmlspecialchars($case['total']); ?></td>
                                        <td class="number-cell">
                                            <?php echo $totalCases > 0 ? htmlspecialchars(number_format(($case['total'] / $totalCases) * 100, 2) . '%') : '0.00%'; ?>
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
