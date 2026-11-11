<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';

requireLogin();
requireRole(doctorRoles());

$reportMonth = $_GET['report_month'] ?? date('Y-m');
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
    <title>Top 10 Cases - Nursing Service System</title>
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
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 20px;">
                    <h2>Monthly Top 10 Diagnosis</h2>
                    <form method="GET" class="report-filter" style="display: flex; align-items: end; gap: 8px;">
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
